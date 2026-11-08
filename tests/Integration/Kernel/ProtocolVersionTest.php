<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Http\McpHeaderValidator;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Security\McpFirewallGuard;
use Nowo\McpKitBundle\Server\McpDispatcher;
use Nowo\McpKitBundle\Server\McpRequest;
use Nowo\McpKitBundle\Server\McpServerInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * Era and version selection, and the mirrored headers.
 */
#[CoversClass(McpEndpointController::class)]
#[CoversClass(McpHeaderValidator::class)]
#[CoversClass(McpDispatcher::class)]
#[CoversClass(McpRequest::class)]
#[CoversClass(McpServerInfo::class)]
#[CoversClass(McpFirewallGuard::class)]
#[CoversClass(JsonRpcError::class)]
final class ProtocolVersionTest extends KernelTestCase
{
    #[Test]
    public function modernRequestsAreServed(): void
    {
        $kernel = $this->bootKernel();
        $body   = $this->json($this->modern($kernel, 'tools/list'));

        self::assertSame('complete', $body['result']['resultType']);
        self::assertSame('private', $body['result']['cacheScope']);
    }

    #[Test]
    public function anUnsupportedModernVersionListsTheSupportedOnes(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->modern($kernel, 'tools/list', ['_meta' => [McpProtocol::META_PROTOCOL_VERSION => '2099-01-01']], ['MCP-Protocol-Version' => '2099-01-01']);

        $this->assertRpcError($response, 400, -32022);
        self::assertSame(['supported' => ['2026-07-28', '2025-11-25', '2025-06-18'], 'requested' => '2099-01-01'], $this->json($response)['error']['data']);
    }

    #[Test]
    public function aLegacyVersionInModernMetaIsUnsupported(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->modern($kernel, 'tools/list', ['_meta' => [McpProtocol::META_PROTOCOL_VERSION => '2025-11-25']], ['MCP-Protocol-Version' => '2025-11-25']), 400, -32022);
    }

    #[Test]
    public function theVersionHeaderMustMatchTheBody(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->modern($kernel, 'tools/list', [], ['MCP-Protocol-Version' => '2025-11-25']), 400, -32020);
        $this->assertRpcError($this->modern($kernel, 'tools/list', [], ['MCP-Protocol-Version' => '']), 400, -32020);
    }

    #[Test]
    public function aNonStringVersionOrMissingCapabilitiesIsMalformed(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->modern($kernel, 'tools/list', ['_meta' => [McpProtocol::META_PROTOCOL_VERSION => 5]]), 400, -32602);
        $this->assertRpcError($this->modern($kernel, 'tools/list', ['_meta' => [McpProtocol::META_CLIENT_CAPABILITIES => 'none']]), 400, -32602);
    }

    #[Test]
    public function aModernHeaderWithoutMetaIsMalformed(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->legacy($kernel, 'tools/list', null, [], '2026-07-28'), 400, -32602);
    }

    #[Test]
    public function aMissingOrUnknownVersionHeaderIsUnsupported(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->legacy($kernel, 'tools/list', null, ['MCP-Protocol-Version' => '']);

        $this->assertRpcError($response, 400, -32022);
        self::assertNull($this->json($response)['error']['data']['requested']);
        $this->assertRpcError($this->legacy($kernel, 'tools/list', null, [], '2025-03-26'), 400, -32022);
    }

    #[Test]
    public function legacyRequestsAreServedWithoutModernFields(): void
    {
        $kernel = $this->bootKernel();

        foreach (['2025-11-25', '2025-06-18'] as $version) {
            $result = $this->json($this->legacy($kernel, 'tools/list', null, [], $version))['result'];
            self::assertSame(['tools' => []], $result);
        }
    }

    #[Test]
    public function legacyCanBeTurnedOff(): void
    {
        $kernel = $this->bootKernel(['legacy' => ['enabled' => false]]);

        $response = $this->legacy($kernel, 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => new stdClass()]);
        $this->assertRpcError($response, 400, -32022);
        self::assertSame(['2026-07-28'], $this->json($response)['error']['data']['supported']);
        $this->assertRpcError($this->legacy($kernel, 'initialize', ['clientInfo' => ['name' => 'x']]), 400, -32022);
        $this->assertRpcError($this->legacy($kernel, 'tools/list'), 400, -32022);
    }

    #[Test]
    public function theMethodHeaderIsRequiredOnModernRequests(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->modern($kernel, 'tools/list', [], ['Mcp-Method' => '']), 400, -32020);
        $this->assertRpcError($this->modern($kernel, 'tools/list', [], ['Mcp-Method' => 'tools/call']), 400, -32020);
        $this->assertRpcError($this->modern($kernel, 'tools/list', [], ['Mcp-Method' => ' tools/list']), 400, -32020);
    }

    #[Test]
    public function theNameHeaderIsRequiredAndCompared(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->modern($kernel, 'tools/call', ['name' => 'x'], ['Mcp-Name' => '']), 400, -32020);
        $this->assertRpcError($this->modern($kernel, 'tools/call', ['name' => 'x'], ['Mcp-Name' => 'y']), 400, -32020);
        $this->assertRpcError($this->modern($kernel, 'tools/call', [], ['Mcp-Name' => 'x']), 400, -32020);
        $this->assertRpcError($this->modern($kernel, 'resources/read', ['uri' => 'demo://a'], ['Mcp-Name' => 'demo://b']), 400, -32020);
        // Base64 sentinel: decoded before comparing.
        $this->assertRpcError($this->modern($kernel, 'tools/call', ['name' => 'héllo'], ['Mcp-Name' => '=?base64?aMOpbGxv?=']), 200, -32602);
        $this->assertRpcError($this->modern($kernel, 'tools/call', ['name' => 'x'], ['Mcp-Name' => '=?base64?eA?=']), 400, -32020);
    }

    #[Test]
    public function legacyRequestsAreCheckedOnlyForTheHeadersTheySend(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->legacy($kernel, 'tools/call', ['name' => 'x']), 200, -32602);
        $this->assertRpcError($this->legacy($kernel, 'tools/call', ['name' => 'x'], ['Mcp-Method' => 'tools/list']), 400, -32020);
        $this->assertRpcError($this->legacy($kernel, 'tools/call', ['name' => 'x'], ['Mcp-Name' => 'y']), 400, -32020);
        $this->assertRpcError($this->legacy($kernel, 'tools/call', ['name' => 'x'], ['Mcp-Method' => 'tools/call', 'Mcp-Name' => 'x']), 200, -32602);
    }

    #[Test]
    public function theCallContextIsExposedOnTheRequest(): void
    {
        $kernel  = $this->bootKernel();
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'REMOTE_ADDR'               => '203.0.113.9',
            'HTTP_AUTHORIZATION'        => 'Bearer tok-ada',
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD'           => 'tools/list',
            'HTTP_USER_AGENT'           => "claude-code/2.1.4 (linux; \x01 x)",
        ], '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{},"io.modelcontextprotocol/clientInfo":{"name":"claude-code‮","version":"2.1"}}}}');
        $kernel->handle($request);

        $context = McpCallContext::fromRequest($request);
        self::assertNotNull($context);
        self::assertSame('2026-07-28', $context->protocolVersion);
        self::assertSame('claude-code', $context->client?->name);
        self::assertSame('2.1', $context->client->version);
        self::assertSame('claude-code/2.1.4', $context->userAgent);
    }
}
