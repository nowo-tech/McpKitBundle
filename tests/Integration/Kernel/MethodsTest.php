<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Http\McpHeaderValidator;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Security\McpFirewallGuard;
use Nowo\McpKitBundle\Server\McpDispatcher;
use Nowo\McpKitBundle\Server\McpRequest;
use Nowo\McpKitBundle\Server\McpServerInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

/**
 * The JSON-RPC methods on both eras.
 */
#[CoversClass(McpEndpointController::class)]
#[CoversClass(McpHeaderValidator::class)]
#[CoversClass(McpDispatcher::class)]
#[CoversClass(McpRequest::class)]
#[CoversClass(McpServerInfo::class)]
#[CoversClass(McpFirewallGuard::class)]
#[CoversClass(JsonRpcError::class)]
final class MethodsTest extends KernelTestCase
{
    #[Test]
    public function discoverAnswersVersionsCapabilitiesAndIdentity(): void
    {
        $kernel = $this->bootKernel(['server_info' => ['title' => 'Test Server'], 'cache' => ['discover_ttl_ms' => 1234]]);
        $body   = $this->json($this->modern($kernel, 'server/discover'));

        self::assertSame([
            'resultType'        => 'complete',
            'supportedVersions' => ['2026-07-28', '2025-11-25', '2025-06-18'],
            'capabilities'      => ['tools' => [], 'resources' => []],
            'instructions'      => McpProtocol::UNTRUSTED_NOTICE,
            'ttlMs'             => 1234,
            'cacheScope'        => 'private',
            '_meta'             => [McpProtocol::META_SERVER_INFO => ['name' => 'test-server', 'title' => 'Test Server', 'version' => '1.2.3']],
        ], $body['result']);
        self::assertStringContainsString('"capabilities":{"tools":{},"resources":{}}', (string) $this->modern($kernel, 'server/discover')->getContent());
    }

    #[Test]
    public function discoverIsNotALegacyMethod(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->legacy($kernel, 'server/discover'), 200, -32601);
    }

    #[Test]
    public function initializeNegotiatesALegacyVersionWithoutSession(): void
    {
        $kernel   = $this->bootKernel(['legacy' => ['versions' => ['2025-11-25', '2025-06-18']]]);
        $response = $this->legacy($kernel, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(), 'clientInfo' => ['name' => 'old', 'version' => '1']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->headers->has('Mcp-Session-Id'));
        self::assertSame([
            'protocolVersion' => '2025-06-18',
            'capabilities'    => ['tools' => [], 'resources' => []],
            'serverInfo'      => ['name' => 'test-server', 'version' => '1.2.3'],
            'instructions'    => McpProtocol::UNTRUSTED_NOTICE,
        ], $this->json($response)['result']);

        $other = $this->legacy($kernel, 'initialize', ['protocolVersion' => '2024-11-05', 'capabilities' => new stdClass()]);
        self::assertSame('2025-11-25', $this->json($other)['result']['protocolVersion']);
        $this->assertRpcError($this->legacy($kernel, 'initialize', ['capabilities' => new stdClass()]), 200, -32602);
    }

    #[Test]
    public function initializeIsNotAModernMethod(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->modern($kernel, 'initialize', ['protocolVersion' => '2025-11-25']), 404, -32601);
    }

    #[Test]
    public function pingIsLegacyOnly(): void
    {
        $kernel = $this->bootKernel();

        self::assertSame('{"jsonrpc":"2.0","id":7,"result":{}}', $this->legacy($kernel, 'ping')->getContent());
        $this->assertRpcError($this->modern($kernel, 'ping'), 404, -32601);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedMethods(): iterable
    {
        foreach (['subscriptions/listen', 'resources/subscribe', 'resources/unsubscribe', 'prompts/list', 'prompts/get', 'completion/complete', 'logging/setLevel', 'tasks/get', 'sampling/createMessage', 'elicitation/create', 'roots/list', 'nope'] as $method) {
            yield $method => [$method];
        }
    }

    #[Test]
    #[DataProvider('unsupportedMethods')]
    public function unsupportedMethodsAreNotFound(string $method): void
    {
        $kernel = $this->bootKernel();
        $params = $method === 'prompts/get' ? ['name' => 'p'] : [];

        $this->assertRpcError($this->modern($kernel, $method, $params), 404, -32601);
        $this->assertRpcError($this->legacy($kernel, $method), 200, -32601);
    }

    #[Test]
    public function listsAreEmptyAndRefuseCursors(): void
    {
        $kernel = $this->bootKernel(['cache' => ['tools_list_ttl_ms' => 1, 'templates_list_ttl_ms' => 2, 'resources_list_ttl_ms' => 3]]);

        self::assertSame(1, $this->json($this->modern($kernel, 'tools/list'))['result']['ttlMs']);
        $templates = $this->json($this->modern($kernel, 'resources/templates/list'))['result'];
        self::assertSame([], $templates['resourceTemplates']);
        self::assertSame(2, $templates['ttlMs']);
        $resources = $this->json($this->modern($kernel, 'resources/list'))['result'];
        self::assertSame([], $resources['resources']);
        self::assertSame(3, $resources['ttlMs']);

        foreach (['tools/list', 'resources/list', 'resources/templates/list'] as $method) {
            $this->assertRpcError($this->modern($kernel, $method, ['cursor' => 'abc']), 200, -32602);
        }
    }

    #[Test]
    public function callsAndReadsValidateTheirShape(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->modern($kernel, 'tools/call', ['name' => 'nope']), 200, -32602);
        $this->assertRpcError($this->modern($kernel, 'resources/read', ['uri' => 'demo://x']), 200, -32602);
        $this->assertRpcError($this->legacy($kernel, 'tools/call', ['arguments' => new stdClass()]), 200, -32602);
        $this->assertRpcError($this->legacy($kernel, 'resources/read', ['name' => 'x']), 200, -32602);
    }
}
