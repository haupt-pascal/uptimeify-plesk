<?php

declare(strict_types=1);

namespace Uptimeify\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The API signals quota exhaustion via the machine code in the Nitro error
 * envelope (`body.data.code`), never via free text. Only the DNSBL IP limit has
 * no code of its own: it comes as 403 `data.code: generic` with a statusMessage
 * starting with "Active IP limit reached".
 */
class ApiClientErrorMappingTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function quotaCodes(): iterable
    {
        yield 'customer package limit' => ['activeMonitorLimitReached'];
        yield 'organization quota' => ['monitorQuotaReached'];
        yield 'no higher quota tier' => ['monitorQuotaNoHigherTier'];
    }

    #[DataProvider('quotaCodes')]
    public function testQuotaCodeIn403EnvelopeRaisesQuotaExceeded(string $code): void
    {
        $client = $this->clientAnswering(403, [
            'error'         => true,
            'url'           => 'https://uptimeify.io/api/websites',
            'statusCode'    => 403,
            'statusMessage' => 'Forbidden',
            'message'       => 'Forbidden',
            'data'          => ['code' => $code],
        ]);

        $this->expectException(\Modules_Uptimeify_Api_Exception_QuotaExceededException::class);
        $client->createWebsite('cust-uuid-1', 'acme.example', 'https://acme.example');
    }

    public function testActiveIpLimitWithGenericCodeRaisesQuotaExceeded(): void
    {
        $text   = 'Active IP limit reached. Your customer package allows maximum 5 active IPs for DNSBL monitoring. Please disable an IP or upgrade your package.';
        $client = $this->clientAnswering(403, [
            'error'         => true,
            'statusCode'    => 403,
            'statusMessage' => $text,
            'message'       => $text,
            'data'          => ['code' => 'generic'],
        ]);

        $this->expectException(\Modules_Uptimeify_Api_Exception_QuotaExceededException::class);
        $client->createCustomerIp('cust-uuid-1', '203.0.113.10', 'plesk');
    }

    public function testGenericCodeWithoutIpLimitPrefixIsNotQuota(): void
    {
        $text   = 'Something else failed. Active IP limit reached is not how this starts.';
        $client = $this->clientAnswering(403, [
            'statusCode'    => 403,
            'statusMessage' => $text,
            'message'       => $text,
            'data'          => ['code' => 'generic'],
        ]);

        $this->assertPlainApiException($client, 403);
    }

    public function testNon403QuotaCodeIsNotQuota(): void
    {
        $client = $this->clientAnswering(400, [
            'statusCode'    => 400,
            'statusMessage' => 'Bad Request',
            'message'       => 'Bad Request',
            'data'          => ['code' => 'monitorQuotaReached'],
        ]);

        $this->assertPlainApiException($client, 400);
    }

    public function testNonQuota403MentioningLimitReachedIsNotQuota(): void
    {
        $text   = 'Rate limit reached for this token';
        $client = $this->clientAnswering(403, [
            'statusCode'    => 403,
            'statusMessage' => $text,
            'message'       => $text,
            'data'          => ['code' => 'forbidden'],
        ]);

        $this->assertPlainApiException($client, 403);
    }

    public function testQuota403TextWithoutDataIsNotQuota(): void
    {
        $text   = 'Active IP limit reached. Monitor quota limit reached.';
        $client = $this->clientAnswering(403, [
            'error'         => true,
            'statusCode'    => 403,
            'statusMessage' => $text,
            'message'       => $text,
        ]);

        $this->assertPlainApiException($client, 403);
    }

    public function testNonJson403IsNotQuota(): void
    {
        $client = new \Modules_Uptimeify_Api_Client(
            'wsm_test',
            $this->guzzle(new Response(403, [], 'Active website limit reached')),
        );

        $this->assertPlainApiException($client, 403);
    }

    private function assertPlainApiException(\Modules_Uptimeify_Api_Client $client, int $status): void
    {
        try {
            $client->createWebsite('cust-uuid-1', 'acme.example', 'https://acme.example');
            self::fail('Expected an ApiException');
        } catch (\Modules_Uptimeify_Api_Exception_ApiException $e) {
            self::assertNotInstanceOf(\Modules_Uptimeify_Api_Exception_QuotaExceededException::class, $e);
            self::assertNotInstanceOf(\Modules_Uptimeify_Api_Exception_UnauthorizedException::class, $e);
            self::assertSame($status, $e->getCode());
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function clientAnswering(int $status, array $body): \Modules_Uptimeify_Api_Client
    {
        $response = new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));

        return new \Modules_Uptimeify_Api_Client('wsm_test', $this->guzzle($response));
    }

    private function guzzle(Response $response): GuzzleClient
    {
        return new GuzzleClient([
            'handler'     => HandlerStack::create(new MockHandler([$response])),
            'http_errors' => false,
        ]);
    }
}
