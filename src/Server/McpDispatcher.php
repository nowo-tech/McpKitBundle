<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Server;

use Nowo\McpKitBundle\Http\McpEndpointOptions;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpEra;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Security\McpPrincipal;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

use function in_array;
use function is_string;
use function property_exists;

/**
 * Answers the JSON-RPC methods of v1 for one validated request.
 *
 * Modern: `server/discover`, `tools/list`, `tools/call`, `resources/list`, `resources/templates/list`, `resources/read`.
 * Legacy adds `initialize` (no session) and `ping`. Everything else is `-32601`.
 */
final readonly class McpDispatcher
{
    /**
     * Creates the dispatcher.
     *
     * @param McpServerInfo $serverInfo server identity
     * @param McpEndpointOptions $options endpoint options (served versions)
     * @param McpToolInvoker $tools tools/list and tools/call
     * @param int $discoverTtlMs `ttlMs` of `server/discover`
     * @param int $toolsListTtlMs `ttlMs` of `tools/list`
     * @param int $templatesListTtlMs `ttlMs` of `resources/templates/list`
     * @param int $resourcesListTtlMs `ttlMs` of `resources/list`
     */
    public function __construct(
        private McpServerInfo $serverInfo,
        private McpEndpointOptions $options,
        private McpToolInvoker $tools,
        private int $discoverTtlMs = 300000,
        private int $toolsListTtlMs = 300000,
        private int $templatesListTtlMs = 300000,
        private int $resourcesListTtlMs = 300000,
    ) {
    }

    /**
     * Answers one request.
     *
     * @param McpRequest $rpc the request
     * @param McpPrincipal $principal the authenticated principal
     * @param Request $request the HTTP request
     *
     * @throws JsonRpcError on a protocol error
     *
     * @return stdClass the `result`
     */
    public function dispatch(McpRequest $rpc, McpPrincipal $principal, Request $request): stdClass
    {
        $modern = $rpc->era() === McpEra::Modern;

        return match ($rpc->method) {
            'server/discover'          => $modern ? $this->discover($rpc) : throw JsonRpcError::methodNotFound(),
            'initialize'               => $modern ? throw JsonRpcError::methodNotFound() : $this->initialize($rpc),
            'ping'                     => $modern ? throw JsonRpcError::methodNotFound() : new stdClass(),
            'tools/list'               => $this->listTools($rpc, $principal),
            'tools/call'               => $this->complete($rpc, $this->tools->call($rpc, $principal, $request)),
            'resources/list'           => $this->listResources($rpc),
            'resources/templates/list' => $this->listTemplates($rpc),
            'resources/read'           => $this->readResource($rpc),
            default                    => throw JsonRpcError::methodNotFound(),
        };
    }

    /**
     * `server/discover`.
     *
     * @param McpRequest $rpc the request
     *
     * @return stdClass the result
     */
    private function discover(McpRequest $rpc): stdClass
    {
        $result                    = new stdClass();
        $result->supportedVersions = $this->options->supportedVersions();
        $result->capabilities      = $this->capabilities();
        $result->instructions      = McpProtocol::UNTRUSTED_NOTICE;

        return $this->complete($rpc, $result, $this->discoverTtlMs);
    }

    /**
     * Legacy `initialize`: negotiates a legacy revision and mints no session.
     *
     * @param McpRequest $rpc the request
     *
     * @throws JsonRpcError when `protocolVersion` is missing
     *
     * @return stdClass the result
     */
    private function initialize(McpRequest $rpc): stdClass
    {
        $requested = $rpc->params->protocolVersion ?? null;
        if (!is_string($requested)) {
            throw JsonRpcError::invalidParams('Missing protocolVersion');
        }
        $legacy = $this->options->legacyVersions();

        $result                  = new stdClass();
        $result->protocolVersion = in_array($requested, $legacy, true) ? $requested : $legacy[0];
        $result->capabilities    = $this->capabilities();
        $result->serverInfo      = $this->serverInfo->toJson();
        $result->instructions    = McpProtocol::UNTRUSTED_NOTICE;

        return $result;
    }

    /**
     * `tools/list`: the tools the principal may use, in a fixed order.
     *
     * @param McpRequest $rpc the request
     * @param McpPrincipal $principal the principal
     *
     * @return stdClass the result
     */
    private function listTools(McpRequest $rpc, McpPrincipal $principal): stdClass
    {
        $this->refuseCursor($rpc);
        $result        = new stdClass();
        $result->tools = $this->tools->listTools($principal);

        return $this->complete($rpc, $result, $this->toolsListTtlMs);
    }

    /**
     * `resources/list`: always empty.
     *
     * @param McpRequest $rpc the request
     *
     * @return stdClass the result
     */
    private function listResources(McpRequest $rpc): stdClass
    {
        $this->refuseCursor($rpc);
        $result            = new stdClass();
        $result->resources = [];

        return $this->complete($rpc, $result, $this->resourcesListTtlMs);
    }

    /**
     * `resources/templates/list`.
     *
     * @param McpRequest $rpc the request
     *
     * @return stdClass the result
     */
    private function listTemplates(McpRequest $rpc): stdClass
    {
        $this->refuseCursor($rpc);
        $result                    = new stdClass();
        $result->resourceTemplates = [];

        return $this->complete($rpc, $result, $this->templatesListTtlMs);
    }

    /**
     * `resources/read`.
     *
     * @param McpRequest $rpc the request
     *
     * @throws JsonRpcError always: no template is registered yet
     *
     * @return stdClass the result
     */
    private function readResource(McpRequest $rpc): stdClass
    {
        if (!is_string($rpc->params->uri ?? null)) {
            throw JsonRpcError::invalidParams('Missing uri');
        }

        throw JsonRpcError::invalidParamsOk('Resource not found');
    }

    /**
     * Lists are never paginated: any cursor is invalid.
     *
     * @param McpRequest $rpc the request
     *
     * @throws JsonRpcError when a cursor is given
     */
    private function refuseCursor(McpRequest $rpc): void
    {
        if (property_exists($rpc->params, 'cursor')) {
            throw JsonRpcError::invalidParamsOk('Invalid cursor');
        }
    }

    /**
     * Server capabilities: tools and resources, nothing more.
     *
     * @return stdClass the capabilities
     */
    private function capabilities(): stdClass
    {
        $capabilities            = new stdClass();
        $capabilities->tools     = new stdClass();
        $capabilities->resources = new stdClass();

        return $capabilities;
    }

    /**
     * Adds the modern result fields: `resultType`, `serverInfo` in `_meta`, and caching hints when given.
     *
     * @param McpRequest $rpc the request
     * @param stdClass $result the result
     * @param int|null $ttlMs caching hint, or null for a non-cacheable result
     *
     * @return stdClass the decorated result (unchanged on the legacy path)
     */
    public function complete(McpRequest $rpc, stdClass $result, ?int $ttlMs = null): stdClass
    {
        if ($rpc->era() !== McpEra::Modern) {
            return $result;
        }
        $decorated             = new stdClass();
        $decorated->resultType = 'complete';
        foreach ((array) $result as $key => $value) {
            $decorated->{$key} = $value;
        }
        if ($ttlMs !== null) {
            $decorated->ttlMs      = $ttlMs;
            $decorated->cacheScope = 'private';
        }
        $meta                                  = new stdClass();
        $meta->{McpProtocol::META_SERVER_INFO} = $this->serverInfo->toJson();
        $decorated->_meta                      = $meta;

        return $decorated;
    }
}
