<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Http;

use Nowo\McpKitBundle\Http\McpMediaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpMediaType::class)]
final class McpMediaTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function contentTypes(): iterable
    {
        yield 'json' => ['application/json', true];
        yield 'json utf-8' => ['application/json; charset=utf-8', true];
        yield 'json quoted UTF-8' => ['application/json;charset="UTF-8"', true];
        yield 'json other param' => ['application/json; foo=bar', true];
        yield 'json latin1' => ['application/json; charset=latin1', false];
        yield 'json bare charset' => ['application/json; charset', false];
        yield 'null' => [null, false];
        yield 'text' => ['text/json', false];
    }

    #[Test]
    #[DataProvider('contentTypes')]
    public function contentType(?string $value, bool $expected): void
    {
        self::assertSame($expected, McpMediaType::isJsonContentType($value));
    }

    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function accepts(): iterable
    {
        yield 'missing' => [null, true];
        yield 'empty' => ['  ', true];
        yield 'both' => ['application/json, text/event-stream', true];
        yield 'any' => ['*/*', true];
        yield 'application any' => ['application/*;q=0.5', true];
        yield 'sse only' => ['text/event-stream', false];
        yield 'json refused' => ['application/json;q=0', false];
        yield 'json malformed q' => ['application/json;q=high', false];
        yield 'json with other param' => ['application/json; level=1', true];
        yield 'json refused despite any' => ['application/json;q=0, */*', false];
        yield 'specific json beats refused family' => ['application/*;q=0, application/json', true];
        yield 'family refused despite any' => ['application/*;q=0, */*', false];
    }

    #[Test]
    #[DataProvider('accepts')]
    public function accept(?string $value, bool $expected): void
    {
        self::assertSame($expected, McpMediaType::acceptsJson($value));
    }
}
