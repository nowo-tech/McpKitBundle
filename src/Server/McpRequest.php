<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Server;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Protocol\McpEra;
use stdClass;

/**
 * One validated JSON-RPC request: id, method, params (decoded JSON objects kept as `stdClass`), era and version.
 */
final readonly class McpRequest
{
    /**
     * Creates the request.
     *
     * @param int|string $id the request id, echoed in the answer
     * @param string $method the method
     * @param stdClass $params the params (an empty object when absent)
     * @param McpCallContext $context era, version and client of the call
     */
    public function __construct(
        public int|string $id,
        public string $method,
        public stdClass $params,
        public McpCallContext $context,
    ) {
    }

    /**
     * The protocol era.
     *
     * @return McpEra the era
     */
    public function era(): McpEra
    {
        return $this->context->era;
    }
}
