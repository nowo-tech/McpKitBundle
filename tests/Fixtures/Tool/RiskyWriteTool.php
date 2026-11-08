<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Tool;

use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Nowo\McpKitBundle\Tool\McpUntrusted;

use function str_repeat;

/**
 * Additive fixture tool that saves, then returns a result the kit cannot show (audit IH-L2).
 */
final class RiskyWriteTool implements McpToolInterface
{
    public const ID = '9f1e2d3c-4b5a-4968-8776-655443322110';

    public int $writes = 0;

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'risky_write',
            'Risky write',
            'Saves, then returns a broken result.',
            McpToolKind::Additive,
            false,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['mode'], 'properties' => ['mode' => ['enum' => ['ok', 'schema', 'misplaced', 'big', 'utf8']]]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                'id'        => ['type' => 'string', 'format' => 'uuid'],
                'untrusted' => ['type' => 'object'],
            ]],
            ['demo:write'],
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        ++$this->writes;
        $ids = ['note' => self::ID];

        return match ($call->arguments['mode']) {
            'ok'        => McpToolResult::ok(['id' => self::ID], $ids, ['title']),
            'schema'    => McpToolResult::ok(['id' => 'not-a-uuid'], $ids, ['title']),
            'misplaced' => McpToolResult::ok(['id' => self::ID, 'untrusted' => 'plain'], $ids, ['title']),
            'big'       => McpToolResult::ok(['id' => self::ID, 'untrusted' => McpUntrusted::of(['t' => str_repeat('x', 5000)])], $ids, ['title']),
            default     => McpToolResult::ok(['id' => self::ID, 'untrusted' => McpUntrusted::of(['t' => "\xC3\x28"])], $ids, ['title']),
        };
    }
}
