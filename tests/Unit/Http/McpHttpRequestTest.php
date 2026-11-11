<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Http;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Context\McpCallContextProvider;
use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Http\McpHttpRequestFactory;
use Nowo\McpKitBundle\Protocol\McpEra;
use Nowo\McpKitBundle\Security\AlwaysAvailable;
use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function json_encode;
use function print_r;
use function serialize;
use function strlen;
use function substr;
use function unserialize;
use function var_export;

use const JSON_THROW_ON_ERROR;

#[CoversClass(McpHttpRequest::class)]
#[CoversClass(McpHttpRequestFactory::class)]
#[CoversClass(McpCredential::class)]
#[CoversClass(AlwaysAvailable::class)]
#[CoversClass(McpCallContext::class)]
#[CoversClass(McpCallContextProvider::class)]
#[CoversClass(McpToolCall::class)]
#[CoversClass(McpResourceRead::class)]
final class McpHttpRequestTest extends TestCase
{
    private const SECRET = 'REDACTED-TEST-x';

    #[Test]
    public function headersAreReadCaseInsensitively(): void
    {
        $request = new McpHttpRequest('POST', '/mcp', ['Content-Type' => 'application/json', 'X_Multi' => ['a', 'b']], '203.0.113.9', 'https', 'mcp.example');

        self::assertSame('application/json', $request->header('content-type'));
        self::assertSame('a', $request->header('X-MULTI'));
        self::assertSame(['a', 'b'], $request->headerValues('x-multi'));
        self::assertSame([], $request->headerValues('accept'));
        self::assertNull($request->header('accept'));
        self::assertTrue($request->hasHeader('CONTENT-TYPE'));
        self::assertFalse($request->hasHeader('accept'));
        self::assertSame(['content-type', 'x-multi'], $request->headerNames());
        self::assertSame('POST', $request->method);
        self::assertSame('/mcp', $request->path);
        self::assertSame('203.0.113.9', $request->clientIp);
        self::assertSame('https', $request->scheme);
        self::assertSame('mcp.example', $request->host);
        self::assertSame('POST /mcp', (string) $request);
    }

    #[Test]
    public function everyLoggableFormRedactsCredentials(): void
    {
        $request = new McpHttpRequest('POST', '/mcp', [
            'Authorization'       => 'Bearer ' . self::SECRET,
            'Proxy-Authorization' => self::SECRET,
            'Cookie'              => 'sid=' . self::SECRET,
            'X-Api-Key'           => self::SECRET,
            'X-Custom-Token'      => self::SECRET,
            'X-Session-Ref'       => self::SECRET,
            'Accept'              => 'application/json',
        ], '203.0.113.9');

        self::assertSame(self::SECRET, $request->credential());
        $dumps = [
            (string) $request,
            json_encode($request, JSON_THROW_ON_ERROR),
            print_r($request, true),
            var_export($request->__debugInfo(), true),
            serialize($request),
        ];
        foreach ($dumps as $dump) {
            self::assertStringNotContainsString(self::SECRET, $dump);
        }
        self::assertSame(['application/json'], $request->redactedHeaders()['accept']);
        self::assertSame([McpHttpRequest::REDACTED], $request->redactedHeaders()['authorization']);
        self::assertSame([McpHttpRequest::REDACTED], $request->jsonSerialize()['headers']['x-custom-token']);
        self::assertTrue(McpHttpRequest::isSensitiveHeader('PHP_AUTH_PW'));
        self::assertFalse(McpHttpRequest::isSensitiveHeader('Mcp-Method'));
    }

    #[Test]
    public function aSerializedCopyKeepsOnlyRedactedValues(): void
    {
        $request = new McpHttpRequest('POST', '/mcp', ['Authorization' => 'Bearer ' . self::SECRET, 'Accept' => 'application/json'], null, 'http', 'localhost');
        $copy    = unserialize(serialize($request));

        self::assertInstanceOf(McpHttpRequest::class, $copy);
        self::assertSame('POST', $copy->method);
        self::assertSame('/mcp', $copy->path);
        self::assertNull($copy->clientIp);
        self::assertSame('http', $copy->scheme);
        self::assertSame('localhost', $copy->host);
        self::assertSame('application/json', $copy->header('Accept'));
        self::assertSame(McpHttpRequest::REDACTED, $copy->credential());

        // A hand-made payload with missing and malformed fields.
        $class = McpHttpRequest::class;
        $empty = unserialize('O:' . strlen($class) . ':"' . $class . '":' . substr(serialize(['headers' => ['X-A' => ['ok', 5], 'X-B' => 'one']]), 2));
        self::assertInstanceOf(McpHttpRequest::class, $empty);
        self::assertSame('', $empty->method);
        self::assertSame('', $empty->path);
        self::assertSame('https', $empty->scheme);
        self::assertSame('', $empty->host);
        self::assertSame(['ok'], $empty->headerValues('x-a'));
        self::assertSame(['one'], $empty->headerValues('x-b'));
    }

    #[Test]
    public function theFactoryMirrorsTheHttpFoundationRequest(): void
    {
        $symfony = Request::create('https://mcp.example/mcp?x=1', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET, 'HTTP_MCP_METHOD' => 'tools/list'], '{"secret body":1}');
        $symfony->headers->set('X-Null', null);
        $request = McpHttpRequestFactory::fromRequest($symfony);

        self::assertSame('POST', $request->method);
        self::assertSame('/mcp', $request->path);
        self::assertSame('https', $request->scheme);
        self::assertSame('mcp.example', $request->host);
        self::assertSame('203.0.113.9', $request->clientIp);
        self::assertSame('tools/list', $request->header('Mcp-Method'));
        self::assertTrue($request->hasHeader('X-Null'));
        self::assertSame('', $request->header('X-Null'));
        self::assertSame(McpCredential::fromHeader($symfony->headers->get('Authorization')), $request->credential());
        self::assertStringNotContainsString('secret body', print_r($request, true));
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string}>
     */
    public static function credentialRequests(): iterable
    {
        yield 'none' => [[], '/mcp'];
        yield 'query string only' => [[], '/mcp?access_token=abc'];
        yield 'bearer with spaces' => [['HTTP_AUTHORIZATION' => 'Bearer   abc '], '/mcp'];
        yield 'raw' => [['HTTP_AUTHORIZATION' => 'abc'], '/mcp'];
        yield 'lower-case scheme, empty' => [['HTTP_AUTHORIZATION' => 'bearer '], '/mcp'];
        yield 'scheme glued to the token' => [['HTTP_AUTHORIZATION' => 'Bearerabc'], '/mcp'];
        yield 'blank' => [['HTTP_AUTHORIZATION' => '   '], '/mcp'];
        yield 'basic' => [['HTTP_AUTHORIZATION' => 'Basic YTpi'], '/mcp'];
        yield 'bearer only' => [['HTTP_AUTHORIZATION' => 'Bearer'], '/mcp'];
    }

    /**
     * @param array<string, string> $server
     */
    #[Test]
    #[DataProvider('credentialRequests')]
    public function theCredentialIsReadTheSameWayByEveryEntryPoint(array $server, string $uri): void
    {
        $symfony = Request::create($uri, 'POST', [], [], [], $server);

        $legacy = McpCredential::fromRequest($symfony);
        self::assertSame($legacy, McpCredential::fromHeader($symfony->headers->get('Authorization')));
        self::assertSame($legacy, McpHttpRequestFactory::fromRequest($symfony)->credential());
    }

    #[Test]
    public function theDefaultAvailabilityAnswersTheSameOnBothPorts(): void
    {
        $available = new AlwaysAvailable();

        self::assertSame(McpAvailability::Available, $available->availabilityOf(new McpHttpRequest('POST', '/mcp')));
        self::assertSame($available->state(Request::create('/mcp')), $available->availabilityOf(new McpHttpRequest('POST', '/mcp')));
    }

    #[Test]
    public function theCallContextIsReadWithoutTheRequest(): void
    {
        $context = new McpCallContext(McpEra::Modern, '2026-07-28', null, null, 'note_get');
        $request = Request::create('/mcp');
        $request->attributes->set(McpCallContext::ATTRIBUTE, $context);

        self::assertSame($context, McpCallContext::fromAttribute($request->attributes->get(McpCallContext::ATTRIBUTE)));
        self::assertSame($context, McpCallContext::fromRequest($request));
        self::assertNull(McpCallContext::fromAttribute('nope'));
        self::assertNull(McpCallContext::fromRequest(Request::create('/mcp')));
        self::assertNull((new McpCallContextProvider(new RequestStack()))->current());
        self::assertSame($context, (new McpCallContextProvider(new RequestStack([$request])))->current());
    }

    #[Test]
    public function toolValuesCarryTheFrameworkFreeRequest(): void
    {
        $principal = new McpPrincipal(new stdClass(), 'i', 'n');
        $context   = new McpCallContext(McpEra::Modern, '2026-07-28', null, null);
        $symfony   = Request::create('/mcp', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);
        $given     = new McpHttpRequest('POST', '/given');

        self::assertSame('/mcp', (new McpToolCall($principal, [], $context, $symfony))->http->path);
        self::assertSame(self::SECRET, (new McpToolCall($principal, [], $context, $symfony))->http->credential());
        self::assertSame($given, (new McpToolCall($principal, [], $context, $symfony, $given))->http);
        self::assertSame('/mcp', (new McpResourceRead($principal, 'demo://x', [], $context, $symfony))->http->path);
        self::assertSame($given, (new McpResourceRead($principal, 'demo://x', [], $context, $symfony, $given))->http);
    }
}
