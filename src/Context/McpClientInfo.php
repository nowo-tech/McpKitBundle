<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Context;

use stdClass;

use function is_string;
use function mb_substr;
use function preg_replace;
use function trim;

/**
 * What a client says it is (`clientInfo`), self-reported and never trusted: each field is cut to 64 characters and
 * stripped of control and format characters. Never reflected in an answer.
 */
final readonly class McpClientInfo
{
    public const MAX_LENGTH = 64;

    /**
     * Creates the value.
     *
     * @param string $name sanitised client name
     * @param string|null $version sanitised client version
     */
    public function __construct(
        public string $name,
        public ?string $version,
    ) {
    }

    /**
     * Reads a JSON `Implementation` object.
     *
     * @param mixed $value the decoded `clientInfo`
     *
     * @return self|null the value, or null when it has no usable name
     */
    public static function fromJson(mixed $value): ?self
    {
        if (!$value instanceof stdClass) {
            return null;
        }
        $name = self::clean($value->name ?? null);
        if ($name === null) {
            return null;
        }

        return new self($name, self::clean($value->version ?? null));
    }

    /**
     * Sanitises one self-reported string.
     *
     * @param mixed $value the raw value
     *
     * @return string|null the cleaned value, or null when empty or not a string
     */
    public static function clean(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $clean = trim(mb_substr((string) preg_replace('/\p{C}+/u', '', $value), 0, self::MAX_LENGTH));

        return $clean === '' ? null : $clean;
    }
}
