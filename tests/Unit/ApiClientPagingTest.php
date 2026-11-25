<?php

declare(strict_types=1);

namespace Uptimeify\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * GET /api/websites caps perPage at 200 and GET /api/customers (paged form) at 100:
 * a client that reads one request only loses every row after the first page.
 */
final class ApiClientPagingTest extends TestCase
{
    /** @var list<array<string, string>> Query of every request sent, in order. */
    private array $queries = [];

    public function testWebsitesAreReadPageByPageUntilTotal(): void
    {
        $client = $this->clientAnswering(
            ['items' => [['publicId' => 'a'], ['publicId' => 'b']], 'total' => 5, 'page' => 1, 'perPage' => 2],
            ['items' => [['publicId' => 'c'], ['publicId' => 'd']], 'total' => 5, 'page' => 2, 'perPage' => 2],
            ['items' => [['publicId' => 'e']], 'total' => 5, 'page' => 3, 'perPage' => 2],
        );

        $rows = $client->listWebsites();

        self::assertSame(['a', 'b', 'c', 'd', 'e'], array_column($rows, 'publicId'));
        self::assertSame(['1', '2', '3'], array_column($this->queries, 'page'));
        self::assertSame('200', $this->queries[0]['perPage']);
        self::assertSame('1', $this->queries[0]['skipMetrics']);
    }

    public function testSinglePageNeedsOneRequest(): void
    {
        $client = $this->clientAnswering(
            ['items' => [['publicId' => 'a']], 'total' => 1, 'page' => 1, 'perPage' => 200],
        );

        self::assertCount(1, $client->listWebsites('cust-1'));
        self::assertCount(1, $this->queries);
        self::assertSame('cust-1', $this->queries[0]['customerId']);
    }

    public function testEmptyPageEndsTheLoopEvenIfTotalIsNeverReached(): void
    {
        // A row deleted between two requests: total says 3, the pages hold 2.
        $client = $this->clientAnswering(
            ['items' => [['publicId' => 'a'], ['publicId' => 'b']], 'total' => 3, 'page' => 1, 'perPage' => 2],
            ['items' => [], 'total' => 3, 'page' => 2, 'perPage' => 2],
        );

        self::assertCount(2, $client->listWebsites());
        self::assertCount(2, $this->queries);
    }

    public function testCustomersAskForTheEnvelopeAndLoop(): void
    {
        $client = $this->clientAnswering(
            ['items' => [['publicId' => 'c1'], ['publicId' => 'c2']], 'total' => 3, 'page' => 1, 'perPage' => 2],
            ['items' => [['publicId' => 'c3']], 'total' => 3, 'page' => 2, 'perPage' => 2],
        );

        $rows = $client->listCustomers(42);

        self::assertSame(['c1', 'c2', 'c3'], array_column($rows, 'publicId'));
        self::assertSame(
            ['envelope' => 'items', 'perPage' => '100', 'organizationId' => '42', 'page' => '1'],
            $this->queries[0],
        );
        self::assertSame('2', $this->queries[1]['page']);
    }

    public function testCustomersStillAcceptThePlainArray(): void
    {
        $client = $this->clientAnswering([['publicId' => 'c1'], ['publicId' => 'c2']]);

        $rows = $client->listCustomers();

        self::assertSame(['c1', 'c2'], array_column($rows, 'publicId'));
        self::assertCount(1, $this->queries);
        self::assertArrayNotHasKey('organizationId', $this->queries[0]);
    }

    /**
     * @param array<int|string, mixed> ...$bodies One decoded JSON body per expected request.
     */
    private function clientAnswering(array ...$bodies): \Modules_Uptimeify_Api_Client
    {
        $history = [];
        $stack   = HandlerStack::create(new MockHandler(array_map(
            static fn (array $body): Response => new Response(200, [], (string) json_encode($body)),
            array_values($bodies),
        )));
        $stack->push(Middleware::mapRequest(function ($request) {
            parse_str($request->getUri()->getQuery(), $query);
            /** @var array<string, string> $query */
            $this->queries[] = $query;
            return $request;
        }));

        return new \Modules_Uptimeify_Api_Client('wsm_test', new GuzzleClient(['handler' => $stack, 'http_errors' => false]));
    }
}
