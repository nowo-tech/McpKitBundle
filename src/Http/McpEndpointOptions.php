<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use Nowo\McpKitBundle\Protocol\McpProtocol;

use function array_merge;
use function in_array;

/**
 * Endpoint options compiled from the configuration.
 */
final readonly class McpEndpointOptions
{
    /**
     * Creates the options.
     *
     * @param list<string> $allowedOrigins lower-case `scheme://host[:port]` entries
     * @param bool $legacyEnabled whether legacy revisions are served
     * @param list<string> $legacyVersions legacy revisions served, newest first
     * @param int $maxBodyBytes request body cap
     * @param int $maxJsonDepth JSON nesting cap
     * @param int $maintenanceRetryAfter `Retry-After` seconds in maintenance
     */
    public function __construct(
        public array $allowedOrigins = [],
        public bool $legacyEnabled = true,
        public array $legacyVersions = McpProtocol::LEGACY_VERSIONS,
        public int $maxBodyBytes = 1048576,
        public int $maxJsonDepth = 64,
        public int $maintenanceRetryAfter = 60,
    ) {
    }

    /**
     * Legacy revisions served (empty when legacy is off).
     *
     * @return list<string> versions, newest first
     */
    public function legacyVersions(): array
    {
        return $this->legacyEnabled ? $this->legacyVersions : [];
    }

    /**
     * Every revision served: modern first, then legacy.
     *
     * @return list<string> versions
     */
    public function supportedVersions(): array
    {
        return array_merge(McpProtocol::MODERN_VERSIONS, $this->legacyVersions());
    }

    /**
     * Whether a revision is a served legacy one.
     *
     * @param string $version the version
     *
     * @return bool true when served as legacy
     */
    public function isLegacyVersion(string $version): bool
    {
        return in_array($version, $this->legacyVersions(), true);
    }
}
