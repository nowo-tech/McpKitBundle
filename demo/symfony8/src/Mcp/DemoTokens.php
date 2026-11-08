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
     * The token entry of a bearer credential.
     *
     * @return array{user: string, name: string, destructive: bool, grants: array<string, string>}|null
     */
    public static function find(?string $credential): ?array
    {
        return $credential === null ? null : (self::TOKENS[$credential] ?? null);
    }
}
