<?php

declare(strict_types=1);

namespace Uptimeify\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClientUnwrapListTest extends TestCase
{
    private const ROWS = [['publicId' => 'a'], ['publicId' => 'b']];

    public function testReadsTheNewListForm(): void
    {
        $data = ['items' => self::ROWS, 'total' => 2, 'page' => 1, 'perPage' => 2];
        self::assertSame(self::ROWS, \Modules_Uptimeify_Api_Client::unwrapList($data));
        self::assertSame(self::ROWS, \Modules_Uptimeify_Api_Client::unwrapList($data, 'incidents'));
    }

    public function testReadsTheOldBareArray(): void
    {
        self::assertSame(self::ROWS, \Modules_Uptimeify_Api_Client::unwrapList(self::ROWS));
    }

    public function testReadsTheOldNamedKey(): void
    {
        $data = ['incidents' => self::ROWS, 'total' => 2];
        self::assertSame(self::ROWS, \Modules_Uptimeify_Api_Client::unwrapList($data, 'incidents'));
    }

    public function testNeverReturnsTheEnvelopeAsARow(): void
    {
        // The old bare-array reader would have turned the new envelope into one row: the items list.
        $data = ['items' => self::ROWS, 'total' => 2];
        self::assertCount(2, \Modules_Uptimeify_Api_Client::unwrapList($data));
        self::assertSame([], \Modules_Uptimeify_Api_Client::unwrapList(null));
    }
}
