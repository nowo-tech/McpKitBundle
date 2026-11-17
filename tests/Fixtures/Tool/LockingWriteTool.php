<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Tool;

use Nowo\McpKitBundle\Tests\Fixtures\WriteUnit\FixtureWriteUnit;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitInterface;
use PDOException;

/**
 * Additive fixture tool that meets a MySQL deadlock (1213 / 40001): `once` on its first attempt only, `always` on every
 * attempt, `committed` after recording a commit in the write unit's ledger. Records each call it received.
 */
final class LockingWriteTool implements McpToolInterface
{
    /** @var list<McpToolCall> */
    public array $calls = [];

    public function __construct(
        private readonly ?WriteUnitInterface $writeUnit = null,
    ) {
    }

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_lock',
            'Lock a note',
            'Writes a note under contention.',
            McpToolKind::Additive,
            false,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['mode'], 'properties' => [
                'mode' => ['enum' => ['once', 'always', 'committed']],
            ]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['attempt'], 'properties' => [
                'attempt' => ['type' => 'integer'],
            ]],
            ['demo:write'],
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        $this->calls[] = $call;
        $mode          = $call->arguments['mode'];
        if ($mode === 'committed' && $this->writeUnit instanceof FixtureWriteUnit) {
            $this->writeUnit->ledger->record();
        }
        if ($mode !== 'once' || $call->attempt === 1) {
            $deadlock            = new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found');
            $deadlock->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];

            throw $deadlock;
        }

        return McpToolResult::ok(['attempt' => $call->attempt], ['note' => 'n-1'], ['title']);
    }
}
