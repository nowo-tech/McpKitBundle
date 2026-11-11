<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Security\McpRequestAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpRequestAvailabilityInterface;
use Nowo\McpKitBundle\Tests\Fixtures\AuditRecorder;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAuthorizer;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRequestAuthenticator;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRequestAvailability;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteCreateTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteResource;
use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function str_starts_with;

/**
 * A DDD / hexagonal host (REQ-ARCH-001): every hook implements a framework-free port, tools read the request through
 * {@see McpHttpRequest} only, and none of those public signatures names a Symfony, Doctrine or PSR-7 type.
 */
#[CoversClass(McpToolCall::class)]
#[CoversClass(McpResourceRead::class)]
final class DddHostKernelTest extends KernelTestCase
{
    #[Test]
    public function aHostWithOnlyFrameworkFreeHooksServesToolsAndResources(): void
    {
        $kernel = $this->bootKernel(
            ['hooks' => ['authenticator' => FixtureRequestAuthenticator::class, 'availability' => FixtureRequestAvailability::class, 'authorizer' => FixtureAuthorizer::class]],
            [NoteCreateTool::class => NoteCreateTool::class, NoteResource::class => NoteResource::class, FixtureAuthorizer::class => FixtureAuthorizer::class, AuditRecorder::class => AuditRecorder::class],
        );

        $call = $this->modern($kernel, 'tools/call', ['name' => 'note_create', 'arguments' => ['title' => 'x']], [], 'tok-grace');
        self::assertSame(200, $call->getStatusCode(), (string) $call->getContent());
        self::assertCount(1, $this->service($kernel, NoteCreateTool::class)->calls);
        $toolCall = $this->service($kernel, NoteCreateTool::class)->calls[0];
        self::assertSame('POST', $toolCall->http->method);
        self::assertSame('/mcp', $toolCall->http->path);
        self::assertSame('tools/call', $toolCall->http->header('Mcp-Method'));
        self::assertSame('note_create', $toolCall->context->toolName);

        $read = $this->modern($kernel, 'resources/read', ['uri' => 'demo://note/' . NoteGetTool::ID], [], 'tok-ada');
        self::assertSame(200, $read->getStatusCode(), (string) $read->getContent());
        $resourceRead = $this->service($kernel, NoteResource::class)->reads[0];
        self::assertSame('203.0.113.9', $resourceRead->http->clientIp);
        self::assertSame('tok-ada', $resourceRead->http->credential());

        self::assertCount(2, $this->service($kernel, FixtureRequestAuthenticator::class)->seen);
    }

    #[Test]
    public function theFrameworkFreeSurfaceNamesNoFrameworkType(): void
    {
        foreach ([McpRequestAuthenticatorInterface::class, McpRequestAvailabilityInterface::class, McpHttpRequest::class] as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getParameters() as $parameter) {
                    $this->assertFrameworkFree($parameter->getType(), $class . '::' . $method->getName());
                }
                $this->assertFrameworkFree($method->getReturnType(), $class . '::' . $method->getName());
            }
        }
        foreach ([McpToolCall::class, McpResourceRead::class] as $class) {
            $this->assertFrameworkFree((new ReflectionProperty($class, 'http'))->getType(), $class . '::$http');
        }
    }

    private function assertFrameworkFree(?ReflectionType $type, string $where): void
    {
        $types = match (true) {
            $type instanceof ReflectionUnionType, $type instanceof ReflectionIntersectionType => $type->getTypes(),
            !$type instanceof ReflectionType                                                  => [],
            default                                                                           => [$type],
        };
        foreach ($types as $named) {
            self::assertInstanceOf(ReflectionNamedType::class, $named);
            foreach (['Symfony\\Component\\', 'Doctrine\\', 'Psr\\Http\\'] as $prefix) {
                self::assertFalse(str_starts_with($named->getName(), $prefix), $where . ' names ' . $named->getName());
            }
        }
    }
}
