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
                'status'          => 'active',
                'monitoringType'  => 'combined',
                'customerName'    => 'Acme Corp',
                'customerPublicId' => 'cust-uuid-1',
            ],
        ]);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $domains->method('all')->willReturn([
            ['name' => 'acme.example', 'displayName' => 'acme.example', 'displayUrl' => 'https://acme.example', 'ip' => '203.0.113.10'],
            ['name' => 'unmonitored.example', 'displayName' => 'unmonitored.example', 'displayUrl' => 'https://unmonitored.example', 'ip' => ''],
        ]);

        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);
        $rows = $service->getDashboardRows();

        self::assertCount(2, $rows);

        $monitored = $rows[0];
        self::assertTrue($monitored['monitored']);
        self::assertSame('web-uuid-1', $monitored['websitePublicId']);
        self::assertSame('Acme Corp', $monitored['customerName']);
        self::assertSame('active', $monitored['status']);

        $unmonitored = $rows[1];
        self::assertFalse($unmonitored['monitored']);
        self::assertNull($unmonitored['websitePublicId']);
    }

    public function testEnablePersistsMappingAndCreatesWebsite(): void
    {
        $api = $this->createMock(\Modules_Uptimeify_Api_Client::class);
        $api->expects(self::once())
            ->method('createWebsite')
            ->with('cust-uuid-1', 'Plesk: acme.example', 'https://acme.example', 'combined', 5)
            ->willReturn(['publicId' => 'web-uuid-9']);

        $domains = $this->createMock(\Modules_Uptimeify_Plesk_DomainRepository::class);
        $service = new \Modules_Uptimeify_Sync_DomainSyncService($api, $domains);

        $service->enable('acme.example', 'cust-uuid-1', 'pro', 'combined', 5);

        $mapping = \Modules_Uptimeify_Settings::getMappingFor('acme.example');
        self::assertNotNull($mapping);
        self::assertSame('web-uuid-9', $mapping['websitePublicId']);
        self::assertSame('cust-uuid-1', $mapping['customerPublicId']);
        self::assertSame('pro', $mapping['packageType']);
    }
}
