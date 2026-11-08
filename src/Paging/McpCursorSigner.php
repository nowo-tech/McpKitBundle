<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Paging;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Tool\McpToolError;

use function base64_encode;
use function count;
use function explode;
use function hash_equals;
use function hash_hmac;
use function rtrim;
use function strlen;
use function strtr;
use function substr;

/**
 * Optional signed cursors: an {@see McpCursor} plus an HMAC (key derived from `kernel.secret`) bound to the token and
 * the tool, so a cursor cannot be forged or replayed by another token or tool. Still a position, never a permission:
 * check access on every call, whatever the cursor says.
 */
final readonly class McpCursorSigner
{
    private string $key;

    /**
     * Creates the signer.
     *
     * @param string $secret the application secret (`kernel.secret`)
     */
    public function __construct(string $secret)
    {
        $this->key = hash_hmac('sha256', 'nowo_mcp_kit.cursor.v1', $secret, true);
    }

    /**
     * Encodes and signs a position for one token and tool.
     *
     * @param array<string, bool|int|string|null> $state the position
     * @param McpPrincipal $principal the token
     * @param string $tool the tool name
     *
     * @return string `<cursor>.<mac>`
     */
    public function encode(array $state, McpPrincipal $principal, string $tool): string
    {
        $cursor = McpCursor::encode($state);

        return $cursor . '.' . $this->mac($cursor, $principal, $tool);
    }

    /**
     * Verifies and decodes a signed cursor.
     *
     * @param string|null $signed the cursor argument
     * @param McpPrincipal $principal the token
     * @param string $tool the tool name
     *
     * @throws McpToolError `INPUT_002` "Invalid field: cursor" when malformed, forged, or bound to another token or tool
     *
     * @return array<string, bool|int|string|null>|null the position, or null when none was given
     */
    public function decode(?string $signed, McpPrincipal $principal, string $tool): ?array
    {
        if ($signed === null) {
            return null;
        }
        $parts = explode('.', $signed);
        if (count($parts) !== 2 || strlen($signed) > McpCursor::MAX_LENGTH || !hash_equals($this->mac($parts[0], $principal, $tool), $parts[1])) {
            throw new McpToolError('INPUT_002', 'Invalid field: cursor');
        }

        return McpCursor::decode($parts[0]);
    }

    /**
     * The input-schema fragment of a signed `cursor` argument (also a safe trusted output pattern).
     *
     * @return array{type: string, maxLength: int, pattern: string, description: string}
     */
    public static function schema(): array
    {
        return [
            'type'        => 'string',
            'maxLength'   => McpCursor::MAX_LENGTH,
            'pattern'     => '^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$',
            'description' => 'Opaque cursor from next_cursor of the previous page.',
        ];
    }

    /**
     * The MAC of a cursor for a token and a tool: 16 bytes, base64url.
     *
     * @param string $cursor the encoded position
     * @param McpPrincipal $principal the token
     * @param string $tool the tool name
     *
     * @return string the MAC
     */
    private function mac(string $cursor, McpPrincipal $principal, string $tool): string
    {
        $mac = substr(hash_hmac('sha256', $principal->tokenId . "\0" . $tool . "\0" . $cursor, $this->key, true), 0, 16);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }
}
