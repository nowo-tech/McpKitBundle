<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use JsonException;
use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Context\McpClientInfo;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpEra;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Security\McpAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Security\McpAvailabilityInterface;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpFirewallGuard;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRateLimiterInterface;
use Nowo\McpKitBundle\Security\McpRateLimitState;
use Nowo\McpKitBundle\Server\McpDispatcher;
use Nowo\McpKitBundle\Server\McpRequest;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function count;
use function ctype_digit;
use function in_array;
use function is_int;
use function is_resource;
use function is_string;
use function json_decode;
use function max;
use function property_exists;
use function stream_get_contents;
use function strlen;
use function strtolower;

use const JSON_THROW_ON_ERROR;

/**
 * The MCP endpoint: one POST per JSON-RPC message, JSON answers only, no session.
 *
 * Checks run in a fixed order and the first failure answers: availability (404) → `Origin` (403) → method (405) →
 * maintenance (503) → `Content-Length` (413) → failed tokens per IP (429) → credential (401) → authenticator (401/404)
 * → firewall user (500) → token budget (429) → `Content-Type` (415) → `Accept` (406) → body cap (413) → JSON (400
 * `-32700`) → JSON-RPC shape (400 `-32600`) → notification (202) → era and version (400 `-32022` / `-32602` /
 * `-32020`) → headers (400 `-32020`) → method. Authentication and the budget come before any parsing.
 */
final readonly class McpEndpointController
{
    public const RATE_LIMIT_ATTRIBUTE = '_nowo_mcp_kit_rate_limit';

    private LoggerInterface $logger;

    /**
     * Creates the controller.
     *
     * @param McpAuthenticatorInterface $authenticator host token check
     * @param McpRateLimiterInterface $rateLimiter host budgets
     * @param McpAvailabilityInterface $availability host switches
     * @param McpFirewallGuard $firewallGuard firewall user check
     * @param McpDispatcher $dispatcher method dispatcher
     * @param McpEndpointOptions $options endpoint options
     * @param ClockInterface $clock clock for `Retry-After`
     * @param LoggerInterface|null $logger logger (exception classes only, never bodies or credentials)
     */
    public function __construct(
        private McpAuthenticatorInterface $authenticator,
        private McpRateLimiterInterface $rateLimiter,
        private McpAvailabilityInterface $availability,
        private McpFirewallGuard $firewallGuard,
        private McpDispatcher $dispatcher,
        private McpEndpointOptions $options,
        private ClockInterface $clock,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Handles one HTTP request to the endpoint.
     *
     * @param Request $request the request
     *
     * @return Response the answer
     */
    public function __invoke(Request $request): Response
    {
        $id  = null;
        $era = null;
        try {
            $response = $this->handle($request, $id, $era);
        } catch (JsonRpcError $error) {
            $response = $this->errorResponse($error, $id, $era);
        } catch (Throwable $throwable) {
            // The class only: a message may hold user data.
            $this->logger->error('MCP: unexpected error.', ['bundle' => 'nowo_mcp_kit', 'exception_class' => $throwable::class]);
            $response = $this->errorResponse(JsonRpcError::internal(), $id, $era);
        }

        return $this->finalize($request, $response);
    }

    /**
     * Runs the pipeline.
     *
     * @param Request $request the request
     * @param int|string|null $id set to the request id once it is known
     * @param McpEra|null $era set to the era once it is known
     *
     * @throws JsonRpcError on a refusal
     *
     * @return Response the answer
     */
    private function handle(Request $request, int|string|null &$id, ?McpEra &$era): Response
    {
        $availability = $this->availability->state($request);
        if ($availability === McpAvailability::Disabled) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $this->checkOrigin($request);
        if ($request->getMethod() !== Request::METHOD_POST) {
            return new Response('', Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
        }
        if ($availability === McpAvailability::Maintenance) {
            throw new JsonRpcError(McpProtocol::UNAVAILABLE, 'Service unavailable', Response::HTTP_SERVICE_UNAVAILABLE, null, ['Retry-After' => (string) $this->options->maintenanceRetryAfter]);
        }
        $this->checkContentLength($request);

        $principal = $this->authenticate($request);

        $this->checkMediaTypes($request);
        $message = $this->decode($this->readBody($request));

        $method = $message->method ?? null;
        if (!is_string($method)) {
            throw JsonRpcError::invalidRequest();
        }
        $hasId = property_exists($message, 'id');
        if ($hasId) {
            $id = $this->readId($message->id);
        }
        $params = $message->params ?? new stdClass();
        if (!$params instanceof stdClass) {
            throw JsonRpcError::invalidParams('Params must be an object');
        }
        if (!$hasId) {
            // A notification: accepted and ignored (no era or header rules for notification POSTs).
            return new Response('', Response::HTTP_ACCEPTED);
        }

        $context = $this->context($request, $method, $params);
        $era     = $context->era;
        McpHeaderValidator::validate($request, $era, $method, $params);
        $request->attributes->set(McpCallContext::ATTRIBUTE, $context);

        $rpc    = new McpRequest($id, $method, $params, $context);
        $result = $this->dispatcher->dispatch($rpc, $principal, $request);

        return new JsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    /**
     * `Origin` check (DNS rebinding): a present `Origin` must be allowed.
     *
     * @param Request $request the request
     *
     * @throws JsonRpcError 403
     */
    private function checkOrigin(Request $request): void
    {
        $origins = $request->headers->all('origin');
        if ($origins === []) {
            return;
        }
        if (count($origins) !== 1 || !is_string($origins[0]) || !in_array(strtolower($origins[0]), $this->options->allowedOrigins, true)) {
            throw new JsonRpcError(McpProtocol::FORBIDDEN_ORIGIN, 'Origin not allowed', Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * `Content-Length` above the cap.
     *
     * @param Request $request the request
     *
     * @throws JsonRpcError 413
     */
    private function checkContentLength(Request $request): void
    {
        $length = $request->headers->get('Content-Length');
        if (is_string($length) && ctype_digit($length) && (strlen($length) > 15 || (int) $length > $this->options->maxBodyBytes)) {
            throw $this->tooLarge();
        }
    }

    /**
     * Failed-token limit, credential, authenticator, firewall user and token budget.
     *
     * @param Request $request the request
     *
     * @throws JsonRpcError 401, 404 (as an empty answer), 429 or 500
     *
     * @return McpPrincipal the principal
     */
    private function authenticate(Request $request): McpPrincipal
    {
        $clientIp = (string) $request->getClientIp();
        $blocked  = $this->rateLimiter->failedTokenState($clientIp);
        if (!$blocked->accepted) {
            throw JsonRpcError::rateLimited($blocked, 'failed_tokens', $this->clock->now()->getTimestamp());
        }
        if (McpCredential::fromRequest($request) === null) {
            throw $this->unauthenticated();
        }
        $principal = $this->authenticator->authenticate($request);
        if ($principal === McpAuthFailure::Disabled) {
            throw new JsonRpcError(0, '', Response::HTTP_NOT_FOUND);
        }
        if ($principal === McpAuthFailure::Invalid) {
            // @igor-ignore - Host hook call that records the failure in the host's limiter storage; no state in this service.
            $this->rateLimiter->registerFailedToken($clientIp);

            throw $this->unauthenticated();
        }
        if (!$this->firewallGuard->isTrusted($request, $principal)) {
            $this->firewallGuard->forget($request);
            $this->logger->error('MCP: the security user is not the token\'s user. The endpoint must sit behind a stateless, secured firewall that authenticates the token.', ['bundle' => 'nowo_mcp_kit']);

            throw JsonRpcError::internal();
        }
        $state = $this->rateLimiter->consume($principal);
        $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $state);
        if (!$state->accepted) {
            throw JsonRpcError::rateLimited($state, 'requests', $this->clock->now()->getTimestamp());
        }

        return $principal;
    }

    /**
     * `Content-Type` (415) and `Accept` (406).
     *
     * @param Request $request the request
     *
     * @throws JsonRpcError 415 or 406
     */
    private function checkMediaTypes(Request $request): void
    {
        if (!McpMediaType::isJsonContentType($request->headers->get('Content-Type'))) {
            throw new JsonRpcError(McpProtocol::NOT_ACCEPTABLE, 'Unsupported media type', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }
        if (!McpMediaType::acceptsJson($request->headers->get('Accept'))) {
            throw new JsonRpcError(McpProtocol::NOT_ACCEPTABLE, 'Not acceptable', Response::HTTP_NOT_ACCEPTABLE);
        }
    }

    /**
     * Reads the body with a hard cap, also without `Content-Length`.
     *
     * @param Request $request the request
     *
     * @throws JsonRpcError 413
     *
     * @return string the body
     */
    private function readBody(Request $request): string
    {
        $stream = $request->getContent(true);
        $body   = is_resource($stream) ? stream_get_contents($stream, $this->options->maxBodyBytes + 1) : $stream;
        $body   = is_string($body) ? $body : '';
        if (strlen($body) > $this->options->maxBodyBytes) {
            throw $this->tooLarge();
        }

        return $body;
    }

    /**
     * Decodes one JSON-RPC message object.
     *
     * @param string $body the body
     *
     * @throws JsonRpcError 400 `-32700` (JSON) or `-32600` (not a request or notification)
     *
     * @return stdClass the message
     */
    private function decode(string $body): stdClass
    {
        try {
            $message = json_decode($body, false, max(1, $this->options->maxJsonDepth), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new JsonRpcError(McpProtocol::PARSE_ERROR, 'Parse error', Response::HTTP_BAD_REQUEST);
        }
        if (!$message instanceof stdClass || ($message->jsonrpc ?? null) !== '2.0') {
            throw JsonRpcError::invalidRequest();
        }

        return $message;
    }

    /**
     * Validates the request id: a string of at most 128 characters or an integer, never null.
     *
     * @param mixed $id the raw id
     *
     * @throws JsonRpcError 400 `-32600`
     *
     * @return int|string the id
     */
    private function readId(mixed $id): int|string
    {
        if (is_int($id) || (is_string($id) && strlen($id) <= 128)) {
            return $id;
        }

        throw JsonRpcError::invalidRequest();
    }

    /**
     * Determines the era and version (modern `_meta`, legacy `initialize`, or the legacy header).
     *
     * @param Request $request the request
     * @param string $method the method
     * @param stdClass $params the params
     *
     * @throws JsonRpcError 400 `-32022`, `-32602` or `-32020`
     *
     * @return McpCallContext the call context
     */
    private function context(Request $request, string $method, stdClass $params): McpCallContext
    {
        $userAgent = McpCallContext::cleanUserAgent($request->headers->get('User-Agent'));
        $meta      = $params->_meta ?? null;
        if ($meta instanceof stdClass && property_exists($meta, McpProtocol::META_PROTOCOL_VERSION)) {
            $version = $meta->{McpProtocol::META_PROTOCOL_VERSION};
            if (!is_string($version)) {
                throw JsonRpcError::invalidParams('Invalid protocol version');
            }
            McpHeaderValidator::validateProtocolVersion($request, $version);
            if (!in_array($version, McpProtocol::MODERN_VERSIONS, true)) {
                throw $this->unsupportedVersion($version);
            }
            if (!($meta->{McpProtocol::META_CLIENT_CAPABILITIES} ?? null) instanceof stdClass) {
                throw JsonRpcError::invalidParams('Missing client capabilities');
            }

            return new McpCallContext(McpEra::Modern, $version, McpClientInfo::fromJson($meta->{McpProtocol::META_CLIENT_INFO} ?? null), $userAgent);
        }

        $legacy = $this->options->legacyVersions();
        if ($method === 'initialize') {
            if ($legacy === []) {
                throw $this->unsupportedVersion(is_string($params->protocolVersion ?? null) ? $params->protocolVersion : null);
            }

            $requested = $params->protocolVersion ?? null;

            return new McpCallContext(McpEra::Legacy, is_string($requested) && in_array($requested, $legacy, true) ? $requested : $legacy[0], McpClientInfo::fromJson($params->clientInfo ?? null), $userAgent);
        }

        $header = $request->headers->get(McpProtocol::HEADER_PROTOCOL_VERSION);
        if (is_string($header) && $this->options->isLegacyVersion($header)) {
            return new McpCallContext(McpEra::Legacy, $header, null, $userAgent);
        }
        if (is_string($header) && in_array($header, McpProtocol::MODERN_VERSIONS, true)) {
            throw JsonRpcError::invalidParams('Missing protocol version in _meta');
        }

        throw $this->unsupportedVersion($header);
    }

    /**
     * 400 `-32022` listing the supported versions.
     *
     * @param string|null $requested the requested version
     *
     * @return JsonRpcError the error
     */
    private function unsupportedVersion(?string $requested): JsonRpcError
    {
        return new JsonRpcError(McpProtocol::UNSUPPORTED_PROTOCOL_VERSION, 'Unsupported protocol version', Response::HTTP_BAD_REQUEST, [
            'supported' => $this->options->supportedVersions(),
            'requested' => $requested !== null && McpHeaderValidator::isPlain($requested) && strlen($requested) <= 32 ? $requested : null,
        ]);
    }

    /**
     * 401 with `WWW-Authenticate: Bearer`.
     *
     * @return JsonRpcError the error
     */
    private function unauthenticated(): JsonRpcError
    {
        return new JsonRpcError(McpProtocol::UNAUTHENTICATED, 'Unauthorized', Response::HTTP_UNAUTHORIZED, null, ['WWW-Authenticate' => 'Bearer']);
    }

    /**
     * 413.
     *
     * @return JsonRpcError the error
     */
    private function tooLarge(): JsonRpcError
    {
        return new JsonRpcError(McpProtocol::PAYLOAD_TOO_LARGE, 'Payload too large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    /**
     * Builds an error answer; `error.code` 0 marks an empty 404.
     *
     * @param JsonRpcError $error the error
     * @param int|string|null $id the request id, when known
     * @param McpEra|null $era the era, when known
     *
     * @return Response the answer
     */
    private function errorResponse(JsonRpcError $error, int|string|null $id, ?McpEra $era): Response
    {
        if ($error->errorCode === 0) {
            return new Response('', $error->status, $error->headers);
        }
        $body = ['jsonrpc' => '2.0'];
        if ($id !== null) {
            $body['id'] = $id;
        }
        $body['error'] = ['code' => $error->errorCode, 'message' => $error->errorMessage];
        if ($error->data !== null) {
            $body['error']['data'] = $error->data;
        }

        return new JsonResponse($body, $error->statusFor($era), $error->headers);
    }

    /**
     * Adds the headers of every answer: no caching, no sniffing, and the token's rate-limit state when known.
     *
     * @param Request $request the request
     * @param Response $response the answer
     *
     * @return Response the answer
     */
    private function finalize(Request $request, Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $state = $request->attributes->get(self::RATE_LIMIT_ATTRIBUTE);
        if ($state instanceof McpRateLimitState) {
            $response->headers->set('X-RateLimit-Limit', (string) $state->limit);
            $response->headers->set('X-RateLimit-Remaining', (string) $state->remaining);
            $response->headers->set('X-RateLimit-Reset', (string) $state->resetAt);
        }

        return $response;
    }
}
