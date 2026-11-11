<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Http\McpHttpRequestFactory;
use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAvailability;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureDualAuthenticator;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRateLimiter;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRequestAuthenticator;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRequestAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function in_array;
use function ksort;

use const ARRAY_FILTER_USE_KEY;

/**
 * Old-vs-new equivalence of the inbound auth surface (REQ-ARCH-001 rule 3): the same requests through a kernel wired
 * with the deprecated HttpFoundation hooks and through one wired with the framework-free {@see McpHttpRequest} hooks
 * give the same status, body, headers and limiter bookkeeping. Deny-by-default, token checks, budgets and availability
 * are unchanged.
 */
#[CoversClass(McpEndpointController::class)]
#[CoversClass(McpHttpRequest::class)]
#[CoversClass(McpHttpRequestFactory::class)]
final class HostPortEquivalenceTest extends KernelTestCase
{
    private const DISCOVER = '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';

    /** @var array<string, string> */
    private const HEADERS = [
        'Content-Type'         => 'application/json',
        'Accept'               => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method'           => 'server/discover',
    ];

    /**
     * @return iterable<string, array{0: array<string, string>, 1?: McpAvailability, 2?: array{blockIp?: bool, refuseRequests?: bool}, 3?: string, 4?: string, 5?: string}>
     */
    public static function scenarios(): iterable
    {
        yield 'valid token' => [['Authorization' => 'Bearer tok-ada']];
        yield 'valid token, lower-case scheme' => [['Authorization' => 'bearer tok-grace']];
        yield 'valid token, no scheme' => [['Authorization' => 'tok-ada']];
        yield 'no credential' => [[]];
        yield 'empty bearer' => [['Authorization' => 'Bearer ']];
        yield 'blank header' => [['Authorization' => '   ']];
        yield 'token in the query string only' => [[], McpAvailability::Available, [], 'POST', '/mcp?access_token=tok-ada'];
        yield 'unknown token' => [['Authorization' => 'Bearer tok-unknown']];
        yield 'disabled token' => [['Authorization' => 'Bearer tok-disabled']];
        yield 'firewall user mismatch' => [['Authorization' => 'Bearer tok-mallory']];
        yield 'authenticator throws' => [['Authorization' => 'Bearer tok-boom']];
        yield 'failed tokens per IP' => [['Authorization' => 'Bearer tok-ada'], McpAvailability::Available, ['blockIp' => true]];
        yield 'token budget spent' => [['Authorization' => 'Bearer tok-ada'], McpAvailability::Available, ['refuseRequests' => true]];
        yield 'disabled endpoint' => [['Authorization' => 'Bearer tok-ada'], McpAvailability::Disabled];
        yield 'maintenance' => [['Authorization' => 'Bearer tok-ada'], McpAvailability::Maintenance];
        yield 'GET' => [['Authorization' => 'Bearer tok-ada'], McpAvailability::Available, [], 'GET'];
        yield 'forwarded IP from an untrusted proxy' => [['Authorization' => 'Bearer tok-unknown', 'X-Forwarded-For' => '198.51.100.7']];
        yield 'foreign origin' => [['Authorization' => 'Bearer tok-ada', 'Origin' => 'https://evil.example']];
        yield 'wrong content type after auth' => [['Authorization' => 'Bearer tok-ada', 'Content-Type' => 'text/plain']];
    }

    /**
     * @param array<string, string> $headers
     * @param array{blockIp?: bool, refuseRequests?: bool} $limiter
     */
    #[Test]
    #[DataProvider('scenarios')]
    public function theFrameworkFreeHooksAnswerExactlyLikeTheDeprecatedOnes(array $headers, McpAvailability $availability = McpAvailability::Available, array $limiter = [], string $method = 'POST', string $uri = '/mcp'): void
    {
        $legacy = $this->exchange($this->bootKernel(), FixtureAvailability::class, $headers, $availability, $limiter, $method, $uri);
        $vo     = $this->exchange($this->bootVoKernel(FixtureRequestAuthenticator::class), FixtureRequestAvailability::class, $headers, $availability, $limiter, $method, $uri);
        $dual   = $this->exchange($this->bootVoKernel(FixtureDualAuthenticator::class), FixtureRequestAvailability::class, $headers, $availability, $limiter, $method, $uri);

        self::assertSame($legacy, $vo);
        self::assertSame($vo, $dual, 'A hook implementing both ports is called through the framework-free one.');
    }

    #[Test]
    public function theFrameworkFreeAuthenticatorSeesTheRequestButNeverLogsTheToken(): void
    {
        $kernel = $this->bootVoKernel(FixtureRequestAuthenticator::class);
        $this->send($kernel, 'POST', self::DISCOVER, [...self::HEADERS, 'Authorization' => 'Bearer tok-ada', 'Cookie' => 'sid=abc']);

        $seen = $this->service($kernel, FixtureRequestAuthenticator::class)->seen;
        self::assertCount(1, $seen);
        self::assertSame('POST', $seen[0]->method);
        self::assertSame('/mcp', $seen[0]->path);
        self::assertSame('203.0.113.9', $seen[0]->clientIp);
        self::assertSame('tok-ada', $seen[0]->credential());
        self::assertSame('POST /mcp', (string) $seen[0]);
        self::assertSame([McpHttpRequest::REDACTED], $seen[0]->redactedHeaders()['authorization']);
        self::assertSame([McpHttpRequest::REDACTED], $seen[0]->redactedHeaders()['cookie']);
    }

    private function bootVoKernel(string $authenticator): McpKitTestKernel
    {
        return $this->bootKernel(['hooks' => ['authenticator' => $authenticator, 'availability' => FixtureRequestAvailability::class]]);
    }

    /**
     * @param class-string<FixtureAvailability|FixtureRequestAvailability> $availabilityService
     * @param array<string, string> $headers
     * @param array{blockIp?: bool, refuseRequests?: bool} $limiter
     *
     * @return array{status: int, body: false|string, headers: array<string, list<string|null>>, failedIps: list<string>, consumed: list<string>}
     */
    private function exchange(McpKitTestKernel $kernel, string $availabilityService, array $headers, McpAvailability $availability, array $limiter, string $method, string $uri): array
    {
        $this->service($kernel, $availabilityService)->state = $availability;
        $rateLimiter                                         = $this->service($kernel, FixtureRateLimiter::class);
        $rateLimiter->blockIp                                = $limiter['blockIp'] ?? false;
        $rateLimiter->refuseRequests                         = $limiter['refuseRequests'] ?? false;

        $response = $this->send($kernel, $method, self::DISCOVER, [...self::HEADERS, ...$headers], $uri);

        return [
            'status'    => $response->getStatusCode(),
            'body'      => $response->getContent(),
            'headers'   => $this->stableHeaders($response),
            'failedIps' => $rateLimiter->failedIps,
            'consumed'  => $rateLimiter->consumed,
        ];
    }

    /**
     * The headers, without the ones that depend on the clock.
     *
     * @return array<string, list<string|null>>
     */
    private function stableHeaders(Response $response): array
    {
        $headers = array_filter($response->headers->all(), static fn (string $name): bool => !in_array($name, ['date', 'x-ratelimit-reset', 'retry-after'], true), ARRAY_FILTER_USE_KEY);
        ksort($headers);

        return $headers;
    }
}
