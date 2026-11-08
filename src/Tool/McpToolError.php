<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use RuntimeException;

/**
 * A business refusal of a tool, answered as a tool result with `isError: true` and `{"ECODE", "err"}`: the model can
 * explain it. The message is static text; what the token may not see reads as {@see self::notFound()}.
 */
final class McpToolError extends RuntimeException
{
    /**
     * Creates the error.
     *
     * @param string $ecode error code such as `TOKEN_001` (`^[A-Z]+_[0-9]{3}$`)
     * @param string $message static message
     * @param array<string, bool|int|string|null> $extra more fields (e.g. `current_revision`); never text written by people
     */
    public function __construct(
        public readonly string $ecode,
        string $message,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    /**
     * The token's level or destructive tick does not allow this tool.
     *
     * @return self the error
     */
    public static function levelRefused(): self
    {
        return new self('TOKEN_001', 'This token may not do this');
    }

    /**
     * Missing, or out of the token's reach (the same answer for both).
     *
     * @return self the error
     */
    public static function notFound(): self
    {
        return new self('ACCESS_001', 'Not found');
    }
}
