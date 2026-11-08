<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Closure;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAvailability;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureMcpAuthenticator;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRateLimiter;
use Nowo\McpKitBundle\Tests\Fixtures\TestFirewallAuthenticator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function count;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

/**
 * Boots a fresh {@see McpKitTestKernel} per test, with a stateless firewall and the fixture hooks.
 */
abstract class KernelTestCase extends TestCase
{
    /** @var list<McpKitTestKernel> */
    private array $kernels = [];

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
        $this->kernels = [];
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-mcp-kit-tests');
    }

    /**
     * @param array<string, mixed> $config nowo_mcp_kit configuration (merged over the test defaults)
     * @param array<string, string> $services extra service id => class (autoconfigured, public)
     * @param array<string, mixed>|null $security security configuration (null: the default stateless firewall)
     * @param array<string, mixed> $framework extra FrameworkBundle configuration
     */
    protected function bootKernel(array $config = [], array $services = [], ?array $security = null, ?Closure $routes = null, bool $withSecurity = true, bool $debug = false, array $framework = []): McpKitTestKernel
    {
        $id     = (string) preg_replace('/\W+/', '_', static::class . '_' . $this->name() . '_' . count($this->kernels));
        $config = array_replace_recursive([
            'enabled' => true,
            'hooks'   => [
                'authenticator' => FixtureMcpAuthenticator::class,
                'rate_limiter'  => FixtureRateLimiter::class,
                'availability'  => FixtureAvailability::class,
            ],
            'server_info' => ['name' => 'test-server', 'version' => '1.2.3'],
        ], $config);
        $security ??= [
            'providers' => ['memory' => ['memory' => ['users' => ['ada' => ['password' => '', 'roles' => ['ROLE_USER']], 'grace' => ['password' => '', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['mcp' => ['pattern' => '^/mcp$', 'stateless' => true, 'provider' => 'memory', 'custom_authenticators' => [TestFirewallAuthenticator::class]]],
        ];
        $kernel = new McpKitTestKernel(
            $id,
            static function ($container) use ($config, $services, $security, $withSecurity): void {
                $container->extension('nowo_mcp_kit', $config);
                if ($withSecurity) {
                    $container->extension('security', $security);
                }
                $defaults = $container->services()->defaults()->autowire()->autoconfigure()->public();
                foreach ([FixtureMcpAuthenticator::class, FixtureRateLimiter::class, FixtureAvailability::class, TestFirewallAuthenticator::class] as $class) {
                    $defaults->set($class, $class);
                }
                foreach ($services as $serviceId => $class) {
                    $defaults->set($serviceId, $class);
                }
            },
            $routes ?? static function (RoutingConfigurator $routes): void {
                $routes->import('@NowoMcpKitBundle/config/routes/mcp.yaml');
            },
            $withSecurity,
            $debug,
            $framework,
        );
        $kernel->boot();
        $this->kernels[] = $kernel;

        return $kernel;
    }

    /**
     * A service of the test container.
     *
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function service(McpKitTestKernel $kernel, string $id): object
    {
        /** @var T $service */
        $service = $kernel->getContainer()->get($id);

        return $service;
    }

    /**
     * Sends one raw HTTP request.
     *
     * @param array<string, string> $headers HTTP headers
     */
    protected function send(McpKitTestKernel $kernel, string $method, string $body, array $headers = [], string $uri = '/mcp'): Response
    {
        $server = ['REMOTE_ADDR' => '203.0.113.9'];
        foreach ($headers as $name => $value) {
            $key                                                                                  = strtoupper(str_replace('-', '_', $name));
            $server[$key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH' ? $key : 'HTTP_' . $key] = $value;
        }
        $request  = Request::create($uri, $method, [], [], [], $server, $body);
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        $kernel->terminate($request, $response);

        return $response;
    }

    /**
     * Sends a modern (2026-07-28) request with every required header.
     *
     * @param array<string, mixed> $params params (the `_meta` fields are added)
     * @param array<string, string> $headers headers overriding the defaults (empty string removes one)
     */
    protected function modern(McpKitTestKernel $kernel, string $method, array $params = [], array $headers = [], string $token = 'tok-ada', int|string $id = 1): Response
    {
        $params['_meta'] = ($params['_meta'] ?? []) + [
            McpProtocol::META_PROTOCOL_VERSION    => '2026-07-28',
            McpProtocol::META_CLIENT_CAPABILITIES => new stdClass(),
            McpProtocol::META_CLIENT_INFO         => ['name' => 'test-client', 'version' => '9.9'],
        ];
        $defaults = [
            'Authorization'        => 'Bearer ' . $token,
            'Content-Type'         => 'application/json',
            'Accept'               => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method'           => $method,
        ];
        $name = $params['name'] ?? $params['uri'] ?? null;
        if (is_string($name) && in_array($method, ['tools/call', 'resources/read', 'prompts/get'], true)) {
            $defaults['Mcp-Name'] = $name;
        }

        return $this->send($kernel, 'POST', json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR), array_filter([...$defaults, ...$headers], static fn (string $v): bool => $v !== ''));
    }

    /**
     * Sends a legacy request (no `_meta` version; `MCP-Protocol-Version` header unless `initialize`).
     *
     * @param array<string, mixed>|null $params params
     * @param array<string, string> $headers headers overriding the defaults (empty string removes one)
     */
    protected function legacy(McpKitTestKernel $kernel, string $method, ?array $params = null, array $headers = [], string $version = '2025-11-25', string $token = 'tok-ada'): Response
    {
        $defaults = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json, text/event-stream',
        ];
        if ($method !== 'initialize') {
            $defaults['MCP-Protocol-Version'] = $version;
        }
        $message = ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method];
        if ($params !== null) {
            $message['params'] = $params;
        }

        return $this->send($kernel, 'POST', json_encode($message, JSON_THROW_ON_ERROR), array_filter([...$defaults, ...$headers], static fn (string $v): bool => $v !== ''));
    }

    /**
     * Decodes a JSON answer.
     *
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * Asserts a JSON-RPC error answer.
     */
    protected function assertRpcError(Response $response, int $status, int $code, bool $withId = true): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        $body = $this->json($response);
        self::assertSame($code, is_array($body['error'] ?? null) ? $body['error']['code'] : null);
        self::assertSame($withId, isset($body['id']));
    }
}
