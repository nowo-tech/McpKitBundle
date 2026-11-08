<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Paging;

/**
 * The configured page sizes (`limits.max_text_chars`, `limits.max_list_rows`), for tools to inject.
 */
final readonly class McpLimits
{
    /**
     * Creates the limits.
     *
     * @param int $maxTextChars characters per text page
     * @param int $maxListRows rows per list page
     */
    public function __construct(
        public int $maxTextChars = 100000,
        public int $maxListRows = 50,
    ) {
    }
}
