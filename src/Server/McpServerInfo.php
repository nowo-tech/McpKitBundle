<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Server;

use stdClass;

/**
 * The server's identity (`serverInfo`), from configuration only: never request or user data.
 */
final readonly class McpServerInfo
{
    /**
     * Creates the value.
     *
     * @param string $name server name
     * @param string|null $title display title
     * @param string $version server version
     */
    public function __construct(
        public string $name,
        public ?string $title,
        public string $version,
    ) {
    }

    /**
     * The JSON `Implementation` object.
     *
     * @return stdClass `{name, title?, version}`
     */
    public function toJson(): stdClass
    {
        $info       = new stdClass();
        $info->name = $this->name;
        if ($this->title !== null) {
            $info->title = $this->title;
        }
        $info->version = $this->version;

        return $info;
    }
}
