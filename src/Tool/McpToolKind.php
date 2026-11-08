<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

/**
 * What a tool does, declared by every tool (no default): it decides the annotations, the write gate and whether the
 * token's destructive tick is needed.
 */
enum McpToolKind: string
{
    /** Reads only: `readOnlyHint: true`. */
    case ReadOnly = 'read_only';
    /** Writes without destroying (history keeps the old state): `destructiveHint: false`. */
    case Additive = 'additive';
    /** Deletes or hides: `destructiveHint: true`, needs the token's destructive tick. */
    case Destructive = 'destructive';

    /**
     * Whether calls write (and pass the write gate).
     *
     * @return bool true for additive and destructive tools
     */
    public function writes(): bool
    {
        return $this !== self::ReadOnly;
    }
}
