<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Envelope;

use DateTimeImmutable;
use LogicException;
use Nowo\McpKitBundle\Envelope\McpEnvelope;
use Nowo\McpKitBundle\Tool\McpUntrusted;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(McpEnvelope::class)]
#[CoversClass(McpUntrusted::class)]
final class McpEnvelopeTest extends TestCase
{
    #[Test]
    public function untrustedGroupsBecomeObjectsInRowsToo(): void
    {
        $converted = McpEnvelope::convert([
            'rows' => [
                ['id' => 1, 'untrusted' => McpUntrusted::of(['title' => 'a'])],
                ['id' => 2, 'untrusted' => McpUntrusted::of(['title' => 'b', 'nested' => ['untrusted' => 'kept as data']])],
            ],
            'empty' => new stdClass(),
            'list'  => [],
        ]);

        self::assertTrue($converted['untrusted']);
        self::assertSame('{"rows":[{"id":1,"untrusted":{"title":"a"}},{"id":2,"untrusted":{"title":"b","nested":{"untrusted":"kept as data"}}}],"empty":{},"list":[]}', json_encode($converted['json']));
        self::assertFalse(McpEnvelope::convert(['id' => 1])['untrusted']);
    }

    #[Test]
    public function theTextBlockEscapesHiddenCharacters(): void
    {
        $value = "a\u{0085}b\u{200B}c\u{202E}d\u{2066}e\u{FEFF}f\u{E0041}g</untrusted>&'é\u{2028}";
        $text  = McpEnvelope::text(['v' => $value]);

        self::assertSame('{"v":"a\\u0085b\\u200bc\\u202ed\\u2066e\\ufefff\\udb40\\udc41g\\u003C/untrusted\\u003E\\u0026\'é\\u2028"}', $text);
        self::assertSame(['v' => $value], json_decode($text, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function aMisplacedUntrustedGroupIsRefused(): void
    {
        $this->expectException(LogicException::class);
        McpEnvelope::convert(['title' => McpUntrusted::of(['a' => 'b'])]);
    }

    #[Test]
    public function anUntrustedKeyWithoutAGroupIsRefused(): void
    {
        $this->expectException(LogicException::class);
        McpEnvelope::convert(['untrusted' => ['title' => 'x']]);
    }

    #[Test]
    public function objectsOtherThanStdClassAreRefused(): void
    {
        $this->expectException(LogicException::class);
        McpEnvelope::convert(['at' => new DateTimeImmutable()]);
    }

    #[Test]
    public function resourcesAreRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('a resource');
        $handle = fopen('php://memory', 'r');
        McpEnvelope::convert(['h' => $handle]);
    }

    #[Test]
    public function invisibleCarriersAreEscaped(): void
    {
        $value = "a\u{FE0F}b\u{E0101}c\u{061C}d\u{00AD}e\u{3164}f\u{007F}g\u{FFF9}h\u{034F}i\u{1D173}j";
        $text  = McpEnvelope::text(['v' => $value]);

        self::assertSame('{"v":"a\\ufe0fb\\udb40\\udd01c\\u061cd\\u00ade\\u3164f\\u007fg\\ufff9h\\u034fi\\ud834\\udd73j"}', $text);
        self::assertSame(['v' => $value], json_decode($text, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function invalidUtf8CanBeSubstituted(): void
    {
        self::assertSame("{\"v\":\"\u{FFFD}(\"}", McpEnvelope::text(['v' => "\xC3\x28"], true));
    }
}
