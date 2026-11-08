<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use InvalidArgumentException;
use RuntimeException;

use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function mb_strlen;
use function preg_match;

/**
 * A business refusal of a tool, answered as a tool result with `isError: true` and `{"ECODE", "err"}`: the model can
 * explain it. The message is static text (checked: printable, at most 512 characters) and `extra` holds ids, numbers,
 * booleans or null only; what the token may not see reads as {@see self::notFound()}.
 */
final class McpToolError extends RuntimeException
{
    /**
     * Creates the error.
     *
     * @param string $ecode error code such as `TOKEN_001` (`^[A-Z]+_[0-9]{3}$`)
     * @param string $message static message
     * @param array<string, bool|float|int|string|null> $extra more fields (e.g. `current_revision`): ids, finite numbers, booleans, null
     *
     * @throws InvalidArgumentException on an invalid code, message or extra field
     */
    public function __construct(
        public readonly string $ecode,
        string $message,
        public readonly array $extra = [],
    ) {
        if (preg_match('/^[A-Z][A-Z0-9]*_[0-9]{3}$/', $ecode) !== 1 || $message === '' || mb_strlen($message) > 512 || preg_match('/\p{C}/u', $message) !== 0) {
            throw new InvalidArgumentException('McpToolError needs an ECODE such as TOKEN_001 and a static message of at most 512 printable characters.');
        }
        foreach ($extra as $key => $value) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $key) !== 1 || !$this->isIdLike($value)) {
                throw new InvalidArgumentException('McpToolError extra fields are snake_case keys with ids, numbers, booleans or null; text written by people does not belong here.');
            }
        }
        parent::__construct($message);
    }

    /**
     * Whether an extra value is an id, a finite number, a boolean or null.
     *
     * @param mixed $value the value
     *
     * @return bool true when allowed
     */
    private function isIdLike(mixed $value): bool
    {
        return $value === null || is_bool($value) || is_int($value) || (is_float($value) && is_finite($value))
            || (is_string($value) && preg_match('/^[A-Za-z0-9._:-]{0,128}$/', $value) === 1);
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
