<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Context;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Context\McpCallContextProvider;
use Nowo\McpKitBundle\Context\McpClientInfo;
use Nowo\McpKitBundle\Protocol\McpEra;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function str_repeat;

#[CoversClass(McpCallContext::class)]
#[CoversClass(McpCallContextProvider::class)]
#[CoversClass(McpClientInfo::class)]
final class CallContextTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function userAgents(): iterable
    {
        yield 'product and version' => ['claude-code/2.1.4 (linux)', 'claude-code/2.1.4'];
        yield 'product only' => ['curl', 'curl'];
        yield 'leading spaces' => ['  node/22', 'node/22'];
        yield 'bad start' => ['(x) y', null];
        yield 'not a string' => [null, null];
        yield 'long' => [str_repeat('a', 100), str_repeat('a', 64)];
        yield 'control after product' => ["evil\x01/1", 'evil'];
    }

    #[Test]
    #[DataProvider('userAgents')]
    public function userAgentIsCleaned(mixed $raw, ?string $expected): void
    {
        self::assertSame($expected, McpCallContext::cleanUserAgent($raw));
    }

    #[Test]
    public function clientInfoIsSanitised(): void
    {
        self::assertNull(McpClientInfo::fromJson('x'));
        self::assertNull(McpClientInfo::fromJson((object) ['version' => '1']));
        self::assertNull(McpClientInfo::fromJson((object) ['name' => "\u{200B}\u{202E}"]));
        $info = McpClientInfo::fromJson((object) ['name' => " cli\nent\u{202E} ", 'version' => 5]);
        self::assertNotNull($info);
        self::assertSame('client', $info->name);
        self::assertNull($info->version);
        self::assertSame(str_repeat('é', 64), McpClientInfo::clean(str_repeat('é', 80)));
    }

    #[Test]
    public function contextLivesOnTheRequest(): void
    {
        self::assertNull((new McpCallContextProvider(new RequestStack()))->current());

        $request  = Request::create('/mcp');
        $provider = new McpCallContextProvider(new RequestStack([$request]));
        self::assertNull($provider->current());

        $context = new McpCallContext(McpEra::Legacy, '2025-11-25', null, 'curl/8');
        $request->attributes->set(McpCallContext::ATTRIBUTE, $context->withTool('note_get'));
        $current = (new McpCallContextProvider(new RequestStack([$request])))->current();
        self::assertNotNull($current);
        self::assertSame('note_get', $current->toolName);
        self::assertSame('curl/8', $current->userAgent);
        self::assertNull($context->toolName);
    }
}
