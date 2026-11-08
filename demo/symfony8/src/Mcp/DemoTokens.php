<?php

declare(strict_types=1);

namespace App\Mcp;

/**
 * Throwaway demo tokens (never real secrets). A real host stores hashed personal tokens and checks them on each request.
 */
final class DemoTokens
{
    /**
     * Token => owner, display name, destructive tick and module levels.
     *
     * @var array<string, array{user: string, name: string, destructive: bool, grants: array<string, string>}>
     */
    public const TOKENS = [
        'demo-read-token-change-me' => ['user' => 'ada', 'name' => 'Ada (read)', 'destructive' => false, 'grants' => ['notes' => 'read']],
        'demo-edit-token-change-me' => ['user' => 'ada', 'name' => 'Ada (edit)', 'destructive' => false, 'grants' => ['notes' => 'edit']],
        'demo-full-token-change-me' => ['user' => 'grace', 'name' => 'Grace (edit + delete)', 'destructive' => true, 'grants' => ['notes' => 'edit']],
    ];

    /**
     * Injected by Caddy only in the conformance profile (MCP_CONFORMANCE=1), because the official runner sends no
     * credential. Never valid otherwise.
     */
    public const CONFORMANCE_TOKEN = 'demo-conformance-token-change-me';

    /**
     * The token entry of a bearer credential.
     *
     * @return array{user: string, name: string, destructive: bool, grants: array<string, string>}|null
     */
    public static function find(?string $credential): ?array
    {
        if ($credential === self::CONFORMANCE_TOKEN) {
            return ($_SERVER['MCP_CONFORMANCE'] ?? getenv('MCP_CONFORMANCE')) === '1'
                ? ['user' => 'ada', 'name' => 'Conformance runner', 'destructive' => false, 'grants' => ['notes' => 'read', 'conformance' => 'yes']]
                : null;
        }

        return $credential === null ? null : (self::TOKENS[$credential] ?? null);
    }
}
