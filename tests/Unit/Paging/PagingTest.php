<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Paging;

use InvalidArgumentException;
use Nowo\McpKitBundle\Paging\McpCursor;
use Nowo\McpKitBundle\Paging\McpLimits;
use Nowo\McpKitBundle\Paging\McpTextSlice;
use Nowo\McpKitBundle\Tool\McpToolError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function rtrim;
use function str_repeat;
use function strtr;

#[CoversClass(McpTextSlice::class)]
#[CoversClass(McpCursor::class)]
#[CoversClass(McpLimits::class)]
final class PagingTest extends TestCase
{
    #[Test]
    public function slicesCountCharactersNotBytes(): void
    {
        $text  = 'héllo wörld 😀 end';
        $first = McpTextSlice::of($text, 0, 7);
        self::assertSame('héllo w', $first->text);
        self::assertSame(['truncated' => true, 'next_offset' => 7, 'total' => 17], $first->paging());

        $last = McpTextSlice::of($text, 14, 7);
        self::assertSame('end', $last->text);
        self::assertFalse($last->truncated);
        self::assertNull($last->nextOffset);

        $beyond = McpTextSlice::of($text, 99, 7);
        self::assertSame('', $beyond->text);
        self::assertSame(['truncated' => false, 'next_offset' => null, 'total' => 17], $beyond->paging());
        self::assertSame('😀', McpTextSlice::of($text, 12, 1)->text);
    }

    #[Test]
    public function slicesRefuseNegativeOffsets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        McpTextSlice::of('x', -1, 10);
    }

    #[Test]
    public function slicesRefuseEmptyPages(): void
    {
        $this->expectException(InvalidArgumentException::class);
        McpTextSlice::of('x', 0, 0);
    }

    #[Test]
    public function cursorsRoundTrip(): void
    {
        $cursor = McpCursor::encode(['o' => 50, 'q' => 'é/+', 'd' => true, 'n' => null]);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $cursor);
        self::assertSame(['o' => 50, 'q' => 'é/+', 'd' => true, 'n' => null], McpCursor::decode($cursor));
        self::assertNull(McpCursor::decode(null));
        self::assertSame(512, McpCursor::schema()['maxLength']);
        self::assertSame(50, (new McpLimits())->maxListRows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCursors(): iterable
    {
        $b64 = static fn (string $json): string => rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 513)];
        yield 'bad alphabet' => ['abc$'];
        yield 'not base64' => ['a'];
        yield 'not json' => [$b64('nope')];
        yield 'list' => [$b64('[1,2]')];
        yield 'empty object' => [$b64('{}')];
        yield 'nested' => [$b64('{"o":{"x":1}}')];
        yield 'float' => [$b64('{"o":1.5}')];
    }

    #[Test]
    #[DataProvider('badCursors')]
    public function badCursorsAreInvalidFields(string $cursor): void
    {
        $this->expectException(McpToolError::class);
        $this->expectExceptionMessage('Invalid field: cursor');
        McpCursor::decode($cursor);
    }
}
