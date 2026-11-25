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

    public function testDashboardMatchesWwwMonitorToBareLocalDomain(): void
    {
        // A monitor added manually at uptimeify.io as "www.website.de" must be
        // recognised as monitoring the bare Plesk domain "website.de", so it is
        // NOT re-synced as a duplicate.
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([
            ['publicId' => 'web-www', 'url' => 'https://www.website.de', 'status' => 'active'],
        ]);
        $api->method('listCustomers')->willReturn([]);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'website.de', 'displayName' => 'website.de', 'displayUrl' => 'https://website.de', 'ip' => '', 'clientId' => 1, 'clientName' => 'Acme', 'clientEmail' => 'a@website.de'],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $rows = $service->getDashboardRows();

        self::assertCount(1, $rows);
        self::assertTrue($rows[0]['monitored']);
        self::assertSame('web-www', $rows[0]['websitePublicId']);
    }

    public function testDashboardCachesStatusWithOpenIncidentCount(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([
            ['publicId' => 'w1', 'url' => 'https://acme.example', 'status' => 'active'],
            ['publicId' => 'w2', 'url' => 'https://beta.example', 'status' => 'active', 'isDown' => true],
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

    public function testDashboardCachesServerScopedStatus(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        // Org has many monitors; only one of them is on THIS server.
        $api->method('listWebsites')->willReturn([
            ['publicId' => 'w1', 'url' => 'https://acme.example', 'status' => 'active', 'isDown' => true],
            ['publicId' => 'w2', 'url' => 'https://elsewhere.example', 'status' => 'active', 'isDown' => true],
            ['publicId' => 'w3', 'url' => 'https://another.example', 'status' => 'active'],
        ]);
        $api->method('listCustomers')->willReturn([]);
        // One open incident on a server domain, one on a foreign domain, one resolved.
        $api->method('listIncidents')->willReturn([
            ['id' => 1, 'status' => 'open', 'website' => ['url' => 'https://acme.example']],
            ['id' => 2, 'status' => 'open', 'website' => ['url' => 'https://elsewhere.example']],
            ['id' => 3, 'status' => 'resolved', 'website' => ['url' => 'https://acme.example']],
        ]);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'acme.example', 'displayName' => 'acme.example', 'displayUrl' => 'https://acme.example', 'ip' => '', 'clientId' => 1, 'clientName' => 'Acme', 'clientEmail' => 'a@acme.example'],
            ['name' => 'unmonitored.example', 'displayName' => 'unmonitored.example', 'displayUrl' => 'https://unmonitored.example', 'ip' => '', 'clientId' => 2, 'clientName' => 'Beta', 'clientEmail' => 'b@beta.example'],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $service->getDashboardRows();

        // Account-wide: 3 monitors, 2 down, 2 open incidents.
        self::assertSame(3, \Modules_Uptimeify_Settings::getStatusTotal());
        self::assertSame(2, \Modules_Uptimeify_Settings::getStatusDown());
        self::assertSame(2, \Modules_Uptimeify_Settings::getStatusIncidents());

        // This server: 2 domains, 1 monitored, that one needs attention, 1 incident.
        self::assertSame(2, \Modules_Uptimeify_Settings::getServerTotal());
        self::assertSame(1, \Modules_Uptimeify_Settings::getServerMonitored());
        self::assertSame(1, \Modules_Uptimeify_Settings::getServerAttention());
        self::assertSame(1, \Modules_Uptimeify_Settings::getServerIncidents());
    }

    public function testIncidentFailureKeepsLastCachedCount(): void
    {
        \Modules_Uptimeify_Settings::setStatus(0, 5, 7);
        \Modules_Uptimeify_Settings::setServerStatus(3, 2, 0, 4);

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

        // The best-effort incident counts must not be wiped on API error.
        self::assertSame(7, \Modules_Uptimeify_Settings::getStatusIncidents());
        self::assertSame(4, \Modules_Uptimeify_Settings::getServerIncidents());
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

    /**
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function healthRows(): iterable
    {
        yield 'healthy' => [['status' => 'active', 'isDown' => false, 'hasCritical' => false, 'hasWarning' => false], 0];
        yield 'down' => [['status' => 'active', 'isDown' => true], 1];
        yield 'critical' => [['status' => 'active', 'hasCritical' => true], 1];
        yield 'warning' => [['status' => 'active', 'hasWarning' => true], 1];
        yield 'down in a maintenance window' => [['status' => 'maintenance', 'isDown' => true, 'inMaintenance' => true], 0];
        yield 'paused' => [['status' => 'inactive'], 1];
        yield 'status "down" is not an API value' => [['status' => 'down'], 0];
    }

    /**
     * @param array<string, mixed> $monitor
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('healthRows')]
    public function testAttentionComesFromTheHealthFlags(array $monitor, int $expected): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([$monitor + ['publicId' => 'w1', 'url' => 'https://acme.example']]);
        $api->method('listCustomers')->willReturn([]);
        $api->method('listIncidents')->willReturn([]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $this->oneDomain());
        $service->getDashboardRows();

        self::assertSame($expected, \Modules_Uptimeify_Settings::getStatusDown());
        self::assertSame($expected, \Modules_Uptimeify_Settings::getServerAttention());
    }

    public function testAcknowledgedIncidentsCountAsOpen(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([]);
        $api->method('listCustomers')->willReturn([]);
        $api->method('listIncidents')->willReturn([
            ['id' => 1, 'status' => 'open'],
            ['id' => 2, 'status' => 'acknowledged'],
            ['id' => 3, 'status' => 'resolved'],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $this->oneDomain());
        $service->getDashboardRows();

        self::assertSame(2, \Modules_Uptimeify_Settings::getStatusIncidents());
    }

    public function testExistingMonitorIsAdoptedOn409(): void
    {
        $conflict          = new \Modules_Uptimeify_Api_Exception_ApiException('conflict', 409);
        $conflict->apiCode = 'monitorAlreadyExists';
        $conflict->apiData = ['code' => 'monitorAlreadyExists', 'existingId' => 7, 'existingPublicId' => 'web-existing'];

        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([]);
        $api->method('listCustomers')->willReturn([['publicId' => 'cust-uuid-1', 'name' => 'Acme', 'email' => 'a@acme.example']]);
        $api->method('listIncidents')->willReturn([]);
        $api->method('createWebsite')->willThrowException($conflict);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $this->oneDomain());
        $summary = $service->mirrorAndSyncAll();

        self::assertSame([], $summary['errors']);
        self::assertSame(0, $summary['websitesCreated']);
        $mapping = \Modules_Uptimeify_Settings::getMappingFor('acme.example');
        self::assertNotNull($mapping);
        self::assertSame('web-existing', $mapping['websitePublicId']);
        self::assertSame('cust-uuid-1', $mapping['customerPublicId']);
    }

    public function testOther409StaysAnError(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->method('listWebsites')->willReturn([]);
        $api->method('listCustomers')->willReturn([['publicId' => 'cust-uuid-1', 'name' => 'Acme', 'email' => 'a@acme.example']]);
        $api->method('listIncidents')->willReturn([]);
        $api->method('createWebsite')->willThrowException(new \Modules_Uptimeify_Api_Exception_ApiException('conflict', 409));

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $this->oneDomain());
        $summary = $service->mirrorAndSyncAll();

        self::assertSame(['acme.example: conflict'], $summary['errors']);
        self::assertNull(\Modules_Uptimeify_Settings::getMappingFor('acme.example'));
    }

    public function testStoredMonitoringTypeWithSpaceIsReadAsApiValue(): void
    {
        \pm_Settings::set(\Modules_Uptimeify_Settings::KEY_MONITORING_TYPE, 'http status');
        self::assertSame('http_status', \Modules_Uptimeify_Settings::getDefaultMonitoringType());

        \pm_Settings::set(\Modules_Uptimeify_Settings::KEY_MONITORING_TYPE, 'ssl check');
        self::assertSame('ssl_check', \Modules_Uptimeify_Settings::getDefaultMonitoringType());

        \pm_Settings::reset();
        self::assertSame('combined', \Modules_Uptimeify_Settings::getDefaultMonitoringType());
    }

    private function oneDomain(): \Modules_Uptimeify_Plesk_DomainRepository
    {
        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'acme.example', 'displayName' => 'acme.example', 'displayUrl' => 'https://acme.example', 'ip' => '', 'clientId' => 1, 'clientName' => 'Acme', 'clientEmail' => 'a@acme.example'],
        ]);
        return $domains;
    }
}
