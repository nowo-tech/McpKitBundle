<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Server;

use JsonException;
use LengthException;
use LogicException;
use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Envelope\McpEnvelope;
use Nowo\McpKitBundle\Event\McpCallOutcome;
use Nowo\McpKitBundle\Event\McpKitEvents;
use Nowo\McpKitBundle\Event\McpToolCallEvent;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Registry\McpToolRegistry;
use Nowo\McpKitBundle\Schema\McpSchemaNormalizer;
use Nowo\McpKitBundle\Schema\McpSchemaValidator;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRateLimiterInterface;
use Nowo\McpKitBundle\Security\McpToolAuthorizerInterface;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function array_filter;
use function array_keys;
use function array_values;
use function hrtime;
use function in_array;
use function intdiv;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * `tools/list` and `tools/call`.
 *
 * A call runs, in order: unknown name (`-32602`) → arguments not an object (`-32602`) → destructive without the tick,
 * or not allowed by the authorizer (`isError` `TOKEN_001`) → unknown argument (`INPUT_001`) → schema violation
 * (`INPUT_002`) → write gate for additive and destructive tools: no audit listener (`AUDIT_001`), write budget (429
 * `-31001`) → the tool → output schema (`-32603` on mismatch) → result cap (`OUTPUT_001`). For additive and destructive
 * tools, anything that fails after the tool returned answers a success with the write's ids and a fixed notice (the
 * write is committed). Every call that reaches the registry dispatches one {@see McpToolCallEvent}.
 */
final readonly class McpToolInvoker
{
    /** The fixed notice of a committed write whose result cannot be shown (audit IH-L2). */
    public const SAVED_WITHOUT_RESULT = 'The change was saved, but its result could not be returned. Do not repeat this call; read the item again if you need it.';

    private LoggerInterface $logger;

    /**
     * Creates the invoker.
     *
     * @param McpToolRegistry $registry compiled tools
     * @param McpToolAuthorizerInterface $authorizer host per-tool decision
     * @param McpRateLimiterInterface $rateLimiter host budgets (write budget)
     * @param EventDispatcherInterface $events event dispatcher (audit)
     * @param ClockInterface $clock clock for `Retry-After`
     * @param int $maxResultBytes serialized result cap
     * @param int $slowCallMs slow-call warning threshold
     * @param LoggerInterface|null $logger logger (tool names, outcomes and exception classes only)
     */
    public function __construct(
        private McpToolRegistry $registry,
        private McpToolAuthorizerInterface $authorizer,
        private McpRateLimiterInterface $rateLimiter,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
        private int $maxResultBytes = 2097152,
        private int $slowCallMs = 2000,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Whether the principal may see and call a tool.
     *
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     *
     * @return bool true when listed
     */
    public function isVisible(McpPrincipal $principal, McpToolDefinition $definition): bool
    {
        return ($definition->kind !== McpToolKind::Destructive || $principal->destructiveAllowed)
            && $this->authorizer->allows($principal, $definition);
    }

    /**
     * The `tools` of `tools/list`, in the registry's fixed order.
     *
     * @param McpPrincipal $principal the principal
     *
     * @return list<stdClass> the tool entries
     */
    public function listTools(McpPrincipal $principal): array
    {
        $tools = [];
        foreach ($this->registry->all() as $definition) {
            if (!$this->isVisible($principal, $definition)) {
                continue;
            }
            $tool               = new stdClass();
            $tool->name         = $definition->name;
            $tool->title        = $definition->title;
            $tool->description  = $definition->description;
            $tool->inputSchema  = McpSchemaNormalizer::toObject($definition->inputSchema);
            $tool->outputSchema = McpSchemaNormalizer::toObject($definition->outputSchema);
            $tool->annotations  = (object) $definition->annotations();
            $tools[]            = $tool;
        }

        return $tools;
    }

    /**
     * Calls a tool.
     *
     * @param McpRequest $rpc the request
     * @param McpPrincipal $principal the principal
     * @param Request $request the HTTP request
     *
     * @throws JsonRpcError on a protocol error or a refused write budget
     *
     * @return stdClass the `CallToolResult` (without the modern decoration)
     */
    public function call(McpRequest $rpc, McpPrincipal $principal, Request $request): stdClass
    {
        $name = $rpc->params->name ?? null;
        if (!is_string($name)) {
            throw JsonRpcError::invalidParams('Missing tool name');
        }
        $arguments = $rpc->params->arguments ?? new stdClass();
        if (!$arguments instanceof stdClass) {
            throw JsonRpcError::invalidParams('Arguments must be an object');
        }
        $definition = $this->registry->get($name);
        if (!$definition instanceof McpToolDefinition) {
            throw JsonRpcError::invalidParamsOk('Unknown tool');
        }

        $context = $rpc->context->withTool($name);
        $request->attributes->set(McpCallContext::ATTRIBUTE, $context);
        $started = (int) hrtime(true);

        try {
            $error = $this->precheck($principal, $definition, $arguments);
            if ($error !== null) {
                return $this->finish($error[0], $error[1], null, $principal, $definition, $context, $started);
            }
            if ($definition->kind->writes()) {
                $state = $this->rateLimiter->consumeWrite($principal, $name);
                if (!$state->accepted) {
                    $this->audit(McpCallOutcome::RateLimited, null, null, $principal, $definition, $context, $started);

                    throw JsonRpcError::rateLimited($state, 'writes', $this->clock->now()->getTimestamp());
                }
            }
            try {
                $result = $this->registry->tool($name)->call(new McpToolCall($principal, $this->toArray($arguments), $context, $request));
            } catch (McpToolError $toolError) {
                return $this->finish(McpCallOutcome::ToolError, $toolError, null, $principal, $definition, $context, $started);
            }

            if (!$definition->kind->writes()) {
                return $this->success($result, $principal, $definition, $context, $started);
            }
            try {
                return $this->success($result, $principal, $definition, $context, $started);
            } catch (Throwable $throwable) {
                // The write is committed: answering an error would invite a retry that writes twice (audit IH-L2).
                return $this->savedWithoutResult($throwable, $result, $principal, $definition, $context, $started);
            }
        } catch (JsonRpcError $jsonRpcError) {
            throw $jsonRpcError;
        } catch (Throwable $throwable) {
            $this->logger->error('MCP: tool failed.', ['bundle' => 'nowo_mcp_kit', 'tool' => $name, 'exception_class' => $throwable::class]);
            $this->audit(McpCallOutcome::InternalError, null, null, $principal, $definition, $context, $started);

            throw JsonRpcError::internal();
        }
    }

    /**
     * Checks before running: visibility, arguments, audit listener.
     *
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     * @param stdClass $arguments the arguments
     *
     * @return array{McpCallOutcome, McpToolError}|null the refusal, or null to go on
     */
    private function precheck(McpPrincipal $principal, McpToolDefinition $definition, stdClass $arguments): ?array
    {
        if (!$this->isVisible($principal, $definition)) {
            return [McpCallOutcome::Refused, McpToolError::levelRefused()];
        }
        $properties = is_array($definition->inputSchema['properties'] ?? null) ? array_keys($definition->inputSchema['properties']) : [];
        $unknown    = array_values(array_filter(array_keys((array) $arguments), static fn (int|string $key): bool => !in_array($key, $properties, true)));
        if ($unknown !== []) {
            $key = (string) $unknown[0];

            return [McpCallOutcome::InvalidInput, new McpToolError('INPUT_001', preg_match('/^[a-z0-9_]{1,64}$/', $key) === 1 ? 'Unknown argument: ' . $key : 'Unknown argument')];
        }
        $violation = McpSchemaValidator::violation($arguments, McpSchemaNormalizer::toObject($definition->inputSchema));
        if ($violation !== null) {
            return [McpCallOutcome::InvalidInput, new McpToolError('INPUT_002', $violation === '' ? 'Invalid arguments' : 'Invalid field: ' . $violation)];
        }
        if ($definition->kind->writes() && !$this->events->hasListeners(McpKitEvents::TOOL_CALL)) {
            return [McpCallOutcome::ToolError, new McpToolError('AUDIT_001', 'Audit is not configured')];
        }

        return null;
    }

    /**
     * Builds a successful result: envelope, output schema, cap.
     *
     * @param McpToolResult $result the tool's result
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     * @param McpCallContext $context the call context
     * @param int $started `hrtime` at the start
     *
     * @throws LogicException when the data or the output schema is broken (answered `-32603`)
     *
     * @return stdClass the `CallToolResult`
     */
    private function success(McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started): stdClass
    {
        $converted = McpEnvelope::convert($result->data);
        if (McpSchemaValidator::violation($converted['json'], McpSchemaNormalizer::toObject($definition->outputSchema)) !== null) {
            throw new LogicException('Tool output does not match its outputSchema.');
        }

        $content = [];
        if ($converted['untrusted']) {
            $content[] = ['type' => 'text', 'text' => McpProtocol::UNTRUSTED_NOTICE];
        }
        $content[] = ['type' => 'text', 'text' => McpEnvelope::text($converted['json'])];
        foreach ($result->resourceLinks as $link) {
            $content[] = ['type' => 'resource_link', 'uri' => $link->uri, 'name' => $link->name];
        }

        $answer                    = new stdClass();
        $answer->content           = $content;
        $answer->structuredContent = $converted['json'];
        $answer->isError           = false;

        if (strlen(json_encode($answer, JSON_THROW_ON_ERROR)) > $this->maxResultBytes) {
            if ($definition->kind->writes()) {
                throw new LengthException('Tool result above max_result_bytes.');
            }

            return $this->finish(McpCallOutcome::ToolError, new McpToolError('OUTPUT_001', 'Result too large; narrow the request'), $result, $principal, $definition, $context, $started);
        }
        $this->audit(McpCallOutcome::Ok, null, $result, $principal, $definition, $context, $started);

        return $answer;
    }

    /**
     * Answers a committed write whose result cannot be shown (output schema, envelope, encoding or size): a success with
     * the write's ids and a fixed notice, never an error that would invite a duplicate retry.
     *
     * @param Throwable $throwable why the result cannot be shown
     * @param McpToolResult $result the tool's result
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     * @param McpCallContext $context the call context
     * @param int $started `hrtime` at the start
     *
     * @return stdClass the `CallToolResult`
     */
    private function savedWithoutResult(Throwable $throwable, McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started): stdClass
    {
        $this->logger->error('MCP: write saved, but its result could not be returned.', ['bundle' => 'nowo_mcp_kit', 'tool' => $definition->name, 'exception_class' => $throwable::class]);
        $this->audit(McpCallOutcome::SavedWithoutResult, null, $result, $principal, $definition, $context, $started);

        $answer          = new stdClass();
        $answer->content = [
            ['type' => 'text', 'text' => self::SAVED_WITHOUT_RESULT],
            ['type' => 'text', 'text' => McpEnvelope::text(['saved' => true, 'ids' => (object) $result->auditIds], true)],
        ];
        $answer->isError = false;

        return $answer;
    }

    /**
     * Answers a tool error (`isError: true`) and audits it.
     *
     * @param McpCallOutcome $outcome the outcome
     * @param McpToolError $error the error
     * @param McpToolResult|null $result the tool's result, when the tool ran
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     * @param McpCallContext $context the call context
     * @param int $started `hrtime` at the start
     *
     * @return stdClass the `CallToolResult`
     */
    private function finish(McpCallOutcome $outcome, McpToolError $error, ?McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started): stdClass
    {
        $this->audit($outcome, $error->ecode, $result, $principal, $definition, $context, $started);

        $body = ['ECODE' => $error->ecode, 'err' => $error->getMessage()];
        foreach ($error->extra as $key => $value) {
            if (!isset($body[$key])) {
                $body[$key] = $value;
            }
        }

        $answer          = new stdClass();
        $answer->content = [['type' => 'text', 'text' => McpEnvelope::text($body)]];
        $answer->isError = true;

        return $answer;
    }

    /**
     * Dispatches the audit event; a listener that throws is logged and does not change the answer (the call happened).
     *
     * @param McpCallOutcome $outcome the outcome
     * @param string|null $ecode the error code
     * @param McpToolResult|null $result the tool's result, when the tool ran
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     * @param McpCallContext $context the call context
     * @param int $started `hrtime` at the start
     */
    private function audit(McpCallOutcome $outcome, ?string $ecode, ?McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started): void
    {
        $durationMs = intdiv((int) hrtime(true) - $started, 1000000);
        if ($durationMs >= $this->slowCallMs) {
            $this->logger->warning('MCP: slow tool call.', ['bundle' => 'nowo_mcp_kit', 'tool' => $definition->name, 'duration_ms' => $durationMs]);
        }
        try {
            $this->events->dispatch(new McpToolCallEvent($principal, $definition->name, $definition->kind, $outcome, $ecode, $result->auditIds ?? [], $result->changedFields ?? [], $context, $durationMs));
        } catch (Throwable $throwable) {
            $this->logger->error('MCP: audit listener failed.', ['bundle' => 'nowo_mcp_kit', 'tool' => $definition->name, 'exception_class' => $throwable::class]);
        }
    }

    /**
     * Arguments as PHP arrays (types kept; empty objects become empty arrays).
     *
     * @param stdClass $arguments the arguments
     *
     * @throws JsonException never for decoded JSON
     *
     * @return array<string, mixed> the arguments
     */
    private function toArray(stdClass $arguments): array
    {
        /** @var array<string, mixed> $array */
        $array = (array) json_decode(json_encode($arguments, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $array;
    }
}
