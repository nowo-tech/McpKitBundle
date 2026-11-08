<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

/**
 * The optional `idempotency_key` argument of create tools: MCP has no idempotency header, so a retry after a lost answer
 * would otherwise write twice. The host passes the key to its idempotency store, scoped to the token.
 */
final class McpIdempotencyKey
{
    public const ARGUMENT = 'idempotency_key';

    /**
     * The input-schema fragment of the argument, to put under `properties.idempotency_key`.
     *
     * @return array{type: string, minLength: int, maxLength: int, pattern: string, description: string}
     */
    public static function schema(): array
    {
        return [
            'type'        => 'string',
            'minLength'   => 1,
            'maxLength'   => 128,
            'pattern'     => '^[A-Za-z0-9._:-]+$',
            'description' => 'Optional. A unique value per intended write (for example a UUID). Retrying with the same key does not write twice.',
        ];
    }
}
