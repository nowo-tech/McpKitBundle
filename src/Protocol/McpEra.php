<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Protocol;

/**
 * Protocol era of one request: modern (per-request `_meta`, 2026-07-28 and later) or legacy (`initialize` handshake,
 * 2025-11-25 and earlier), served statelessly either way.
 */
enum McpEra: string
{
    case Modern = 'modern';
    case Legacy = 'legacy';
}
