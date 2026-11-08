<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Http;

use Nowo\McpKitBundle\Http\McpHeaderValidator;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpEra;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(McpHeaderValidator::class)]
final class McpHeaderValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function values(): iterable
    {
        yield 'plain' => ['get_weather', 'get_weather'];
        yield 'plain with inner space' => ['a b', 'a b'];
        yield 'leading space' => [' a', null];
        yield 'trailing tab' => ["a\t", null];
        yield 'control' => ["a\x01b", null];
        yield 'non ascii' => ['héllo', null];
        yield 'base64' => ['=?base64?SGVsbG8sIOS4lueVjA==?=', 'Hello, 世界'];
        yield 'base64 newline' => ['=?base64?bGluZTEKbGluZTI=?=', "line1\nline2"];
        yield 'base64 sentinel literal' => ['=?base64?PT9iYXNlNjQ/bGl0ZXJhbD89?=', '=?base64?literal?='];
        yield 'base64 bad alphabet' => ['=?base64?SGV$bG8=?=', null];
        yield 'base64 unpadded' => ['=?base64?SGVsbG8?=', null];
        yield 'base64 invalid utf-8' => ['=?base64?wyg=?=', null];
        yield 'base64 upper sentinel' => ['=?BASE64?SGVsbG8=?=', '=?BASE64?SGVsbG8=?='];
    }

    #[Test]
    #[DataProvider('values')]
    public function decode(string $raw, ?string $expected): void
    {
        self::assertSame($expected, McpHeaderValidator::decode($raw));
    }

    #[Test]
    public function aRepeatedHeaderIsAMismatch(): void
    {
        $request = Request::create('/mcp', 'POST');
        $request->headers->set('Mcp-Method', ['tools/list', 'tools/list']);

        $this->expectException(JsonRpcError::class);
        McpHeaderValidator::validate($request, McpEra::Legacy, 'tools/list', new stdClass());
    }

    #[Test]
    public function methodsWithoutANameNeedNoNameHeader(): void
    {
        $request = Request::create('/mcp', 'POST');
        $request->headers->set('Mcp-Method', 'tools/list');
        McpHeaderValidator::validate($request, McpEra::Modern, 'tools/list', new stdClass());

        $legacy = Request::create('/mcp', 'POST');
        McpHeaderValidator::validate($legacy, McpEra::Legacy, 'tools/call', (object) ['name' => 'x']);
        $this->addToAssertionCount(2);
    }
}
