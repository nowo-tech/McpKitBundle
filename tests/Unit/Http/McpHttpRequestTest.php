<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Http;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Context\McpCallContextProvider;
use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Http\McpHttpRequestFactory;
use Nowo\McpKitBundle\Http\McpHttpRequestSecrets;
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
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function json_encode;
use function ob_get_clean;
use function ob_start;
use function print_r;
use function serialize;
use function strlen;
use function substr;
use function unserialize;
use function var_dump;
use function var_export;

use const JSON_THROW_ON_ERROR;

#[CoversClass(McpHttpRequest::class)]
#[CoversClass(McpHttpRequestFactory::class)]
#[CoversClass(McpHttpRequestSecrets::class)]
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
    public function everyViewOfTheObjectRedactsCredentials(): void
    {
        $request = new McpHttpRequest('POST', '/mcp', [
            'Authorization'       => 'Bearer ' . self::SECRET,
            'Proxy-Authorization' => self::SECRET,
            'Cookie'              => 'sid=' . self::SECRET,
            'X-Api-Key'           => self::SECRET,
            'X-Mcp-Key'           => self::SECRET,
            'X-Jwt'               => self::SECRET,
            'X-Client-Cert'       => self::SECRET,
            'Mcp-Session-Id'      => self::SECRET,
            'Accept'              => 'application/json',
            'Mcp-Method'          => 'tools/call',
            'X-Forwarded-For'     => '198.51.100.7',
        ], '203.0.113.9');

        self::assertSame(self::SECRET, $request->credential());
        self::assertSame(self::SECRET, $request->header('x-jwt'));
        self::assertSame([self::SECRET], $request->headerValues('Mcp-Session-Id'));

        $cloner = new VarCloner();
        $dumper = new CliDumper();
        ob_start();
        var_dump($request);
        $varDump = (string) ob_get_clean();
        $dumps   = [
            'string'     => (string) $request,
            'json'       => json_encode($request, JSON_THROW_ON_ERROR),
            'print_r'    => print_r($request, true),
            'var_dump'   => $varDump,
            'var_export' => var_export($request, true),
            'array cast' => var_export((array) $request, true),
            'serialize'  => serialize($request),
            'VarDumper'  => (string) $dumper->dump($cloner->cloneVar($request), true),
        ];
        foreach ($dumps as $form => $dump) {
            self::assertStringNotContainsString(self::SECRET, $dump, $form);
        }
        self::assertStringContainsString('tools/call', $dumps['VarDumper']);
        self::assertSame(['application/json'], $request->redactedHeaders()['accept']);
        self::assertSame(['tools/call'], $request->redactedHeaders()['mcp-method']);
        self::assertSame(['198.51.100.7'], $request->redactedHeaders()['x-forwarded-for']);
        self::assertSame([McpHttpRequest::REDACTED], $request->redactedHeaders()['authorization']);
        self::assertSame([McpHttpRequest::REDACTED], $request->jsonSerialize()['headers']['x-mcp-key']);

        foreach (['PHP_AUTH_PW', 'X-Access-Key', 'X-Bearer', 'X-Pass', 'Mcp-Session-Id', 'Anything-Else'] as $name) {
            self::assertTrue(McpHttpRequest::isSensitiveHeader($name), $name);
        }
        foreach (['Mcp-Method', 'MCP-Protocol-Version', 'Accept', 'Content-Type', 'Content-Length', 'Origin', 'User-Agent', 'Host', 'X-Forwarded-Proto'] as $name) {
            self::assertFalse(McpHttpRequest::isSensitiveHeader($name), $name);
        }

        $copy = clone $request;
        self::assertNull($copy->credential(), 'a clone carries no secret values');
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
        self::assertNull($copy->credential());
        self::assertTrue($copy->hasHeader('Authorization'));

        // A hand-made payload with missing and malformed fields.
        $class = McpHttpRequest::class;
        $empty = unserialize('O:' . strlen($class) . ':"' . $class . '":' . substr(serialize(['headers' => ['Accept' => ['ok', 5], 'Mcp-Method' => 'one', 'Authorization' => 'Bearer x']]), 2));
        self::assertInstanceOf(McpHttpRequest::class, $empty);
        self::assertSame('', $empty->method);
        self::assertSame('', $empty->path);
        self::assertSame('https', $empty->scheme);
        self::assertSame('', $empty->host);
        self::assertSame(['ok'], $empty->headerValues('accept'));
        self::assertSame(['one'], $empty->headerValues('mcp-method'));
        self::assertNull($empty->credential());
        self::assertSame([McpHttpRequest::REDACTED], $empty->redactedHeaders()['authorization']);
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

    #[Test]
    public function conflictingForwardingHeadersGiveAnUnknownClientIp(): void
    {
        $proxies = Request::getTrustedProxies();
        /** @var int<0, 63> $headerSet */
        $headerSet = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['203.0.113.9'], Request::HEADER_FORWARDED | Request::HEADER_X_FORWARDED_FOR);
        try {
            $symfony = Request::create('/mcp', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_FORWARDED' => 'for=198.51.100.8', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7']);
            self::assertNull(McpHttpRequestFactory::fromRequest($symfony)->clientIp);

            $forwarded = Request::create('/mcp', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7']);
            self::assertSame('198.51.100.7', McpHttpRequestFactory::fromRequest($forwarded)->clientIp);
        } finally {
            /* @var int<0, 63> $headerSet */
            Request::setTrustedProxies($proxies, $headerSet);
        }
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
