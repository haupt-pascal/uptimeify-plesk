<?php

declare(strict_types=1);

namespace Uptimeify\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DomainSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        \pm_Settings::reset();
    }

    public function testDashboardMatchesLocalDomainToRemoteMonitorByUrl(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([
            [
                'publicId'         => 'web-uuid-1',
                'url'              => 'https://acme.example',
                'status'           => 'active',
                'monitoringType'   => 'combined',
                'customerName'     => 'Acme Corp',
                'customerPublicId' => 'cust-uuid-1',
            ],
        ]);
        $api->method('listCustomers')->willReturn([]);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'acme.example', 'displayName' => 'acme.example', 'displayUrl' => 'https://acme.example', 'ip' => '203.0.113.10', 'clientId' => 1, 'clientName' => 'Acme Corp', 'clientEmail' => 'ops@acme.example'],
            ['name' => 'unmonitored.example', 'displayName' => 'unmonitored.example', 'displayUrl' => 'https://unmonitored.example', 'ip' => '', 'clientId' => 2, 'clientName' => 'Beta GmbH', 'clientEmail' => 'hi@beta.example'],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $rows = $service->getDashboardRows();

        self::assertCount(2, $rows);

        $monitored = $rows[0];
        self::assertTrue($monitored['monitored']);
        self::assertSame('web-uuid-1', $monitored['websitePublicId']);
        self::assertSame('Acme Corp', $monitored['customerName']);
        self::assertSame('Acme Corp', $monitored['ownerName']);
        self::assertSame('active', $monitored['status']);

        $unmonitored = $rows[1];
        self::assertFalse($unmonitored['monitored']);
        self::assertNull($unmonitored['websitePublicId']);
        self::assertSame('Beta GmbH', $unmonitored['ownerName']);
    }

    public function testDashboardCachesStatusWithOpenIncidentCount(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([
            ['publicId' => 'w1', 'url' => 'https://acme.example', 'status' => 'active'],
            ['publicId' => 'w2', 'url' => 'https://beta.example', 'status' => 'down'],
        ]);
        $api->method('listCustomers')->willReturn([]);
        $api->method('listIncidents')->willReturn([
            ['id' => 1, 'status' => 'open'],
            ['id' => 2, 'status' => 'resolved'],
            ['id' => 3, 'status' => 'open'],
        ]);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'acme.example', 'displayName' => 'acme.example', 'displayUrl' => 'https://acme.example', 'ip' => '', 'clientId' => 1, 'clientName' => 'Acme', 'clientEmail' => 'a@acme.example'],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $service->getDashboardRows();

        self::assertSame(2, \Modules_Uptimeify_Settings::getStatusTotal());
        self::assertSame(1, \Modules_Uptimeify_Settings::getStatusDown());
        self::assertSame(2, \Modules_Uptimeify_Settings::getStatusIncidents());
    }

    public function testIncidentFailureKeepsLastCachedCount(): void
    {
        \Modules_Uptimeify_Settings::setStatus(0, 5, 7);

        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([]);
        $api->method('listCustomers')->willReturn([]);
        $api->method('listIncidents')->willThrowException(
            new \Modules_Uptimeify_Api_Exception_ApiException('boom'),
        );

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $service->getDashboardRows();

        // The best-effort incident count must not be wiped on API error.
        self::assertSame(7, \Modules_Uptimeify_Settings::getStatusIncidents());
    }

    public function testBrandNameDefaultsToUptimeify(): void
    {
        self::assertSame('Uptimeify', \Modules_Uptimeify_Settings::getBrandName());

        \Modules_Uptimeify_Settings::setBrandName('  Acme Status  ');
        self::assertSame('Acme Status', \Modules_Uptimeify_Settings::getBrandName());

        \Modules_Uptimeify_Settings::setBrandName('');
        self::assertSame('Uptimeify', \Modules_Uptimeify_Settings::getBrandName());
    }

    public function testEnableWithExplicitCustomerCreatesWebsiteAndMapping(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([]);
        $api->method('listCustomers')->willReturn([]);
        $api->expects(self::once())
            ->method('createWebsite')
            ->with('cust-uuid-1', 'Plesk: acme.example', 'https://acme.example', 'combined', 5)
            ->willReturn(['publicId' => 'web-uuid-9']);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'acme.example', 'displayName' => 'acme.example', 'displayUrl' => 'https://acme.example', 'ip' => '', 'clientId' => 1, 'clientName' => 'Acme', 'clientEmail' => 'a@acme.example'],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $service->enable('acme.example', 'cust-uuid-1', 'pro');

        $mapping = \Modules_Uptimeify_Settings::getMappingFor('acme.example');
        self::assertNotNull($mapping);
        self::assertSame('web-uuid-9', $mapping['websitePublicId']);
        self::assertSame('cust-uuid-1', $mapping['customerPublicId']);
        self::assertSame('pro', $mapping['packageType']);

        // The admin's explicit choice is remembered for the Plesk client.
        self::assertSame('cust-uuid-1', \Modules_Uptimeify_Settings::getCustomerForClient(1));
    }

    public function testEnableAutoCreatesCustomerFromPleskClient(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([]);
        $api->method('listCustomers')->willReturn([]); // no existing customer to match
        $api->expects(self::once())
            ->method('createCustomer')
            ->with('Acme GmbH', 'ops@acme.example', 'pro')
            ->willReturn(['publicId' => 'new-cust-uuid']);
        $api->expects(self::once())
            ->method('createWebsite')
            ->with('new-cust-uuid', 'Plesk: acme.example', 'https://acme.example', 'combined', 5)
            ->willReturn(['publicId' => 'web-uuid-7']);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'acme.example', 'displayName' => 'acme.example', 'displayUrl' => 'https://acme.example', 'ip' => '', 'clientId' => 5, 'clientName' => 'Acme GmbH', 'clientEmail' => 'ops@acme.example'],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $service->enable('acme.example', 'auto', 'pro');

        self::assertSame('new-cust-uuid', \Modules_Uptimeify_Settings::getCustomerForClient(5));
    }
}
