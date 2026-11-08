<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Paging;

use InvalidArgumentException;

use function mb_strlen;
use function mb_substr;

/**
 * One page of a long text, in Unicode characters (code points), so one answer cannot flood a model. Put `text` under
 * `untrusted` and the numbers outside it.
 */
final readonly class McpTextSlice
{
    /**
     * Creates the slice.
     *
     * @param string $text the page
     * @param bool $truncated whether more text follows
     * @param int|null $nextOffset offset of the next page, or null
     * @param int $total total length in characters
     */
    private function __construct(
        public string $text,
        public bool $truncated,
        public ?int $nextOffset,
        public int $total,
    ) {
    }

    /**
     * Cuts one page.
     *
     * @param string $text the whole text (UTF-8)
     * @param int $offset start, in characters
     * @param int $maxChars page size, in characters
     *
     * @throws InvalidArgumentException on a negative offset or a page size below 1
     *
     * @return self the slice
     */
    public static function of(string $text, int $offset, int $maxChars): self
    {
        if ($offset < 0 || $maxChars < 1) {
            throw new InvalidArgumentException('Offset must be >= 0 and the page size >= 1.');
        }
        $total = mb_strlen($text, 'UTF-8');
        $page  = $offset >= $total ? '' : mb_substr($text, $offset, $maxChars, 'UTF-8');
        $end   = $offset + mb_strlen($page, 'UTF-8');

        return new self($page, $end < $total, $end < $total ? $end : null, $total);
    }

    /**
     * The paging fields, to put outside `untrusted`.
     *
     * @return array{truncated: bool, next_offset: int|null, total: int}
     */
    public function paging(): array
    {
        return ['truncated' => $this->truncated, 'next_offset' => $this->nextOffset, 'total' => $this->total];
    }
}
