<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Context;

use Nowo\McpKitBundle\Protocol\McpEra;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Symfony\Component\HttpFoundation\Request;

use function is_string;
use function preg_match;
use function substr;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * What the host may know about the MCP call in progress: era, protocol version, the client as reported, and the tool.
 * It lives on the request attributes (never on a service), so listeners of the host's own write events can add the tool
 * and the client to their audit rows.
 */
final readonly class McpCallContext
{
    public const ATTRIBUTE = '_nowo_mcp_kit_context';

    /**
     * Creates the context.
     *
     * @param McpEra $era protocol era
     * @param string $protocolVersion protocol version of the request
     * @param McpClientInfo|null $client the client as reported (modern `_meta` or legacy `initialize`), sanitised
     * @param string|null $userAgent first `product[/version]` token of `User-Agent`, at most 64 characters
     * @param string|null $toolName the tool or resource template being called
     */
    public function __construct(
        public McpEra $era,
        public string $protocolVersion,
        public ?McpClientInfo $client,
        public ?string $userAgent,
        public ?string $toolName = null,
    ) {
    }

    /**
     * The context of a request, when it is an MCP call.
     *
     * @param Request $request the request
     *
     * @return self|null the context
     *
     * @deprecated since 1.1.0, use {@see McpCallContextProvider::current()}, {@see fromAttribute()} or the context of
     *             the call ({@see McpToolCall::$context}); removed in 2.0.0
     */
    public static function fromRequest(Request $request): ?self
    {
        @trigger_error('Since nowo-tech/mcp-kit-bundle 1.1.0: McpCallContext::fromRequest() is deprecated, use McpCallContextProvider::current() or McpCallContext::fromAttribute() instead.', E_USER_DEPRECATED);

        return self::fromAttribute($request->attributes->get(self::ATTRIBUTE));
    }

    /**
     * The context stored under {@see ATTRIBUTE}, from a value the host read from its own request attributes.
     *
     * @param mixed $attribute the value of the {@see ATTRIBUTE} request attribute
     *
     * @return self|null the context, or null when the value is not one
     */
    public static function fromAttribute(mixed $attribute): ?self
    {
        return $attribute instanceof self ? $attribute : null;
    }

    /**
     * A copy naming the tool being called.
     *
     * @param string $toolName the tool or resource template name
     *
     * @return self the copy
     */
    public function withTool(string $toolName): self
    {
        return new self($this->era, $this->protocolVersion, $this->client, $this->userAgent, $toolName);
    }

    /**
     * The cleaned `User-Agent`: its first `product[/version]` token, at most 64 characters.
     *
     * @param mixed $userAgent the raw header value
     *
     * @return string|null the product token, or null
     */
    public static function cleanUserAgent(mixed $userAgent): ?string
    {
        $token = "[!#$%&'*+.^_`|~0-9A-Za-z-]+";
        if (!is_string($userAgent) || preg_match('@^\s*(' . $token . '(?:/' . $token . ')?)@', $userAgent, $match) !== 1) {
            return null;
        }

        return substr($match[1], 0, McpClientInfo::MAX_LENGTH);
    }
}
