<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Http\McpHeaderValidator;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Security\McpFirewallGuard;
use Nowo\McpKitBundle\Server\McpDispatcher;
use Nowo\McpKitBundle\Server\McpRequest;
use Nowo\McpKitBundle\Server\McpServerInfo;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAvailability;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

use function str_repeat;

/**
 * The order of checks: each refusal, and that an earlier check wins over a later one.
 */
#[CoversClass(McpEndpointController::class)]
#[CoversClass(McpHeaderValidator::class)]
#[CoversClass(McpDispatcher::class)]
#[CoversClass(McpRequest::class)]
#[CoversClass(McpServerInfo::class)]
#[CoversClass(McpFirewallGuard::class)]
#[CoversClass(JsonRpcError::class)]
final class EndpointPipelineTest extends KernelTestCase
{
    private const DISCOVER = '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';

    /** @var array<string, string> */
    private const HEADERS = [
        'Authorization'        => 'Bearer tok-ada',
        'Content-Type'         => 'application/json',
        'Accept'               => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method'           => 'server/discover',
    ];

    #[Test]
    public function aValidRequestPassesEveryCheck(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->send($kernel, 'POST', self::DISCOVER, self::HEADERS);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('100', $response->headers->get('X-RateLimit-Limit'));
        self::assertSame('99', $response->headers->get('X-RateLimit-Remaining'));
        self::assertGreaterThan(time(), (int) $response->headers->get('X-RateLimit-Reset'));
        self::assertFalse($response->headers->has('Mcp-Session-Id'));
        self::assertFalse($response->headers->has('Set-Cookie'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function disabledAnswers404BeforeEverything(): void
    {
        $kernel                                                    = $this->bootKernel();
        $this->service($kernel, FixtureAvailability::class)->state = McpAvailability::Disabled;

        $response = $this->send($kernel, 'GET', '', ['Origin' => 'https://evil.example']);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    #[Test]
    public function originIsCheckedBeforeTheMethod(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->send($kernel, 'GET', '', ['Origin' => 'https://evil.example']), 403, -31003, false);
        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Origin' => 'null']), 403, -31003, false);
    }

    #[Test]
    public function anAllowedOriginPasses(): void
    {
        $kernel = $this->bootKernel(['allowed_origins' => ['https://App.Example:8443']]);

        self::assertSame(200, $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Origin' => 'https://app.example:8443'])->getStatusCode());
        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Origin' => 'https://app.example']), 403, -31003, false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherMethods(): iterable
    {
        foreach (['GET', 'DELETE', 'PUT', 'PATCH', 'HEAD', 'OPTIONS'] as $method) {
            yield $method => [$method];
        }
    }

    #[Test]
    #[DataProvider('otherMethods')]
    public function anythingButPostAnswers405(string $method): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->send($kernel, $method, '', ['Mcp-Session-Id' => 'abc', 'Last-Event-ID' => '3', 'Authorization' => 'Bearer nope']);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->headers->get('Allow'));
        self::assertSame('', $response->getContent());
        self::assertSame([], $this->service($kernel, FixtureRateLimiter::class)->failedIps);
    }

    #[Test]
    public function maintenanceAnswers503AfterTheMethod(): void
    {
        $kernel                                                    = $this->bootKernel(['maintenance_retry_after' => 120]);
        $this->service($kernel, FixtureAvailability::class)->state = McpAvailability::Maintenance;

        self::assertSame(405, $this->send($kernel, 'GET', '')->getStatusCode());
        $response = $this->send($kernel, 'POST', self::DISCOVER, self::HEADERS);
        $this->assertRpcError($response, 503, -31005, false);
        self::assertSame('120', $response->headers->get('Retry-After'));
    }

    #[Test]
    public function contentLengthAboveTheCapAnswers413BeforeAuthentication(): void
    {
        $kernel = $this->bootKernel(['limits' => ['max_body_bytes' => 2048]]);

        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => '', 'Content-Length' => '4096']), 413, -31004, false);
        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Content-Length' => '99999999999999999999']), 413, -31004, false);
    }

    #[Test]
    public function aBodyAboveTheCapAnswers413WithoutContentLength(): void
    {
        $kernel = $this->bootKernel(['limits' => ['max_body_bytes' => 2048]]);
        $body   = '{"jsonrpc":"2.0","id":1,"method":"x","params":{"pad":"' . str_repeat('a', 3000) . '"}}';

        $this->assertRpcError($this->send($kernel, 'POST', $body, self::HEADERS), 413, -31004, false);
    }

    #[Test]
    public function aBlockedIpAnswers429BeforeTheCredential(): void
    {
        $kernel                                                     = $this->bootKernel();
        $this->service($kernel, FixtureRateLimiter::class)->blockIp = true;

        $response = $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => '']);
        $this->assertRpcError($response, 429, -31001, false);
        self::assertSame(['limit' => 'failed_tokens'], $this->json($response)['error']['data']);
        self::assertContains($response->headers->get('Retry-After'), ['9', '10']);
    }

    #[Test]
    public function aMissingCredentialAnswers401WithoutCountingAFailure(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => '']);

        $this->assertRpcError($response, 401, -31002, false);
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        self::assertSame([], $this->service($kernel, FixtureRateLimiter::class)->failedIps);

        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => 'Bearer']), 401, -31002, false);
    }

    #[Test]
    public function anInvalidTokenAnswers401AndCountsAFailure(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => 'Bearer bt_unknown']);

        $this->assertRpcError($response, 401, -31002, false);
        self::assertSame(['203.0.113.9'], $this->service($kernel, FixtureRateLimiter::class)->failedIps);
        self::assertStringNotContainsString('bt_unknown', (string) $response->getContent());
    }

    #[Test]
    public function aDisabledTokenAnswersAnEmpty404(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => 'Bearer tok-disabled']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    #[Test]
    public function aPrincipalTheFirewallDoesNotStandBehindAnswers500(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => 'Bearer tok-mallory']), 500, -32603, false);
    }

    #[Test]
    public function anUnsecuredFirewallAnswers500(): void
    {
        $kernel = $this->bootKernel([], [], ['firewalls' => ['mcp' => ['pattern' => '^/mcp$', 'security' => false]]]);

        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, self::HEADERS), 500, -32603, false);
    }

    #[Test]
    public function theFirewallCheckCanBeTurnedOff(): void
    {
        $kernel = $this->bootKernel(['firewall_check' => false], [], null, null, false);

        self::assertSame(200, $this->send($kernel, 'POST', self::DISCOVER, self::HEADERS)->getStatusCode());
    }

    #[Test]
    public function theTokenBudgetAnswers429BeforeParsing(): void
    {
        $kernel                                                            = $this->bootKernel();
        $this->service($kernel, FixtureRateLimiter::class)->refuseRequests = true;

        $response = $this->send($kernel, 'POST', 'not json', self::HEADERS);
        $this->assertRpcError($response, 429, -31001, false);
        self::assertSame(['limit' => 'requests'], $this->json($response)['error']['data']);
        self::assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        self::assertNotNull($response->headers->get('Retry-After'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badContentTypes(): iterable
    {
        yield 'missing' => [''];
        yield 'form' => ['application/x-www-form-urlencoded'];
        yield 'latin1' => ['application/json; charset=iso-8859-1'];
        yield 'text' => ['text/plain'];
    }

    #[Test]
    #[DataProvider('badContentTypes')]
    public function aNonJsonContentTypeAnswers415(string $contentType): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Content-Type' => $contentType]), 415, -31006, false);
    }

    #[Test]
    public function jsonWithCharsetUtf8IsAccepted(): void
    {
        $kernel = $this->bootKernel();

        self::assertSame(200, $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Content-Type' => 'Application/JSON; charset="UTF-8"'])->getStatusCode());
    }

    #[Test]
    public function anAcceptWithoutJsonAnswers406(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Accept' => 'text/event-stream']), 406, -31006, false);
        self::assertSame(200, $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Accept' => ''])->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'not json' => ['{"jsonrpc":', -32700];
        yield 'invalid utf-8' => ["{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"\xC3\x28\"}", -32700];
        yield 'too deep' => [str_repeat('[', 70) . str_repeat(']', 70), -32700];
        yield 'batch' => ['[' . self::DISCOVER . ']', -32600];
        yield 'scalar' => ['"hello"', -32600];
        yield 'no jsonrpc' => ['{"id":1,"method":"server/discover"}', -32600];
        yield 'wrong jsonrpc' => ['{"jsonrpc":"1.0","id":1,"method":"server/discover"}', -32600];
        yield 'response object' => ['{"jsonrpc":"2.0","id":1,"result":{}}', -32600];
        yield 'method not a string' => ['{"jsonrpc":"2.0","id":1,"method":5}', -32600];
    }

    #[Test]
    #[DataProvider('malformedBodies')]
    public function malformedBodiesAnswer400WithoutId(string $body, int $code): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->send($kernel, 'POST', $body, self::HEADERS), 400, $code, false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIds(): iterable
    {
        yield 'null' => ['null'];
        yield 'float' => ['1.5'];
        yield 'bool' => ['true'];
        yield 'object' => ['{}'];
        yield 'long string' => ['"' . str_repeat('x', 129) . '"'];
    }

    #[Test]
    #[DataProvider('badIds')]
    public function badIdsAnswer400(string $id): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->send($kernel, 'POST', '{"jsonrpc":"2.0","id":' . $id . ',"method":"server/discover"}', self::HEADERS), 400, -32600, false);
    }

    #[Test]
    public function paramsMustBeAnObject(): void
    {
        $kernel = $this->bootKernel();

        $this->assertRpcError($this->send($kernel, 'POST', '{"jsonrpc":"2.0","id":"a","method":"server/discover","params":[1]}', self::HEADERS), 400, -32602);
    }

    #[Test]
    public function notificationsAnswer202AfterAuthentication(): void
    {
        $kernel = $this->bootKernel();
        $body   = '{"jsonrpc":"2.0","method":"notifications/initialized"}';

        $response = $this->send($kernel, 'POST', $body, ['Authorization' => 'Bearer tok-ada', 'Content-Type' => 'application/json']);
        self::assertSame(202, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame(['id-ada'], $this->service($kernel, FixtureRateLimiter::class)->consumed);

        self::assertSame(202, $this->send($kernel, 'POST', '{"jsonrpc":"2.0","method":"notifications/cancelled","params":{"requestId":3}}', ['Authorization' => 'Bearer tok-ada', 'Content-Type' => 'application/json'])->getStatusCode());
        $this->assertRpcError($this->send($kernel, 'POST', $body, ['Content-Type' => 'application/json']), 401, -31002, false);
    }

    #[Test]
    public function aStringIdIsEchoed(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->modern($kernel, 'server/discover', [], [], 'tok-ada', 'req-1');

        self::assertSame('req-1', $this->json($response)['id']);
    }

    #[Test]
    public function anUnexpectedErrorAnswers500WithoutDetail(): void
    {
        $kernel   = $this->bootKernel();
        $response = $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => 'Bearer tok-boom']);

        $this->assertRpcError($response, 500, -32603, false);
        self::assertStringNotContainsString('bt_should_not_leak', (string) $response->getContent());
    }
}
