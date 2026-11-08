<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use App\Mcp\NoteStore;
use Nowo\McpKitBundle\Tool\McpIdempotencyKey;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Create a note (additive). An `idempotency_key` makes a retry return the same note instead of a second one.
 */
final readonly class NoteCreateTool implements McpToolInterface
{
    public function __construct(
        private NoteStore $notes,
    ) {
    }

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_create',
            'Create a note',
            'Creates a note with a title and a Markdown body. Pass an idempotency_key so a retry does not create a second note.',
            McpToolKind::Additive,
            false,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title'], 'properties' => [
                'title'                     => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                'body'                      => ['type' => 'string', 'maxLength' => 100000],
                McpIdempotencyKey::ARGUMENT => McpIdempotencyKey::schema(),
            ]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                'id' => ['type' => 'string', 'format' => 'uuid'],
            ]],
            ['notes:edit'],
            false,
            10,
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        $existing = $this->notes->idempotent($call->principal->tokenId, $call->idempotencyKey());
        if ($existing !== null) {
            return McpToolResult::ok(['id' => $existing], ['note' => $existing]);
        }
        $user = $call->principal->user;
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $id       = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        $this->notes->save([
            'id'         => $id,
            'owner'      => $user instanceof UserInterface ? $user->getUserIdentifier() : 'unknown',
            'title'      => (string) $call->arguments['title'],
            'body'       => (string) ($call->arguments['body'] ?? ''),
            'archived'   => false,
            'created_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
        $this->notes->remember($call->principal->tokenId, $call->idempotencyKey(), $id);

        return McpToolResult::ok(['id' => $id], ['note' => $id], ['title', 'body']);
    }
}
