<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Tool;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Protocol\McpEra;
use Nowo\McpKitBundle\Security\DenyingMcpToolAuthorizer;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use Nowo\McpKitBundle\Tool\McpIdempotencyKey;
use Nowo\McpKitBundle\Tool\McpResourceLink;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(McpToolKind::class)]
#[CoversClass(McpIdempotencyKey::class)]
#[CoversClass(McpToolCall::class)]
#[CoversClass(McpToolError::class)]
#[CoversClass(McpResourceLink::class)]
#[CoversClass(DenyingMcpToolAuthorizer::class)]
final class ToolValuesTest extends TestCase
{
    #[Test]
    public function kindsTellWhetherTheyWrite(): void
    {
        self::assertFalse(McpToolKind::ReadOnly->writes());
        self::assertTrue(McpToolKind::Additive->writes());
        self::assertTrue(McpToolKind::Destructive->writes());
    }

    #[Test]
    public function theIdempotencyKeyIsOptional(): void
    {
        $principal = new McpPrincipal(new stdClass(), 'i', 'n');
        $context   = new McpCallContext(McpEra::Modern, '2026-07-28', null, null);
        $request   = Request::create('/mcp');

        self::assertNull((new McpToolCall($principal, [], $context, $request))->idempotencyKey());
        self::assertNull((new McpToolCall($principal, ['idempotency_key' => 5], $context, $request))->idempotencyKey());
        self::assertSame('k', (new McpToolCall($principal, ['idempotency_key' => 'k'], $context, $request))->idempotencyKey());
        self::assertSame(128, McpIdempotencyKey::schema()['maxLength']);
        self::assertFalse((new DenyingMcpToolAuthorizer())->allows($principal, NoteGetTool::definition()));
    }

    #[Test]
    public function errorsAreStatic(): void
    {
        self::assertSame('TOKEN_001', McpToolError::levelRefused()->ecode);
        self::assertSame('Not found', McpToolError::notFound()->getMessage());
        self::assertSame('note', (new McpResourceLink('demo://note/1', 'note'))->name);
    }
}
