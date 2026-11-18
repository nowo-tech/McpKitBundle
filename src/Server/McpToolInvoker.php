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
use Nowo\McpKitBundle\Security\McpPrincipalRefresherInterface;
use Nowo\McpKitBundle\Security\McpRateLimiterInterface;
use Nowo\McpKitBundle\Security\McpToolAuthorizerInterface;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Nowo\McpKitBundle\Write\McpWriteConflict;
use Nowo\McpKitBundle\Write\McpWriteRunnerInterface;
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
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_substr;
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
 * tools, anything that fails after the tool returned answers `SAVED_001` with the write's ids and the fixed "saved, do
 * not repeat" text (the write is committed). Every call that reaches the registry dispatches one {@see McpToolCallEvent}.
 *
 * With a write runner (option `write_unit.enabled`), the execution of an additive or destructive tool, and only that, runs
 * as a write unit: a lock conflict replays the tool, not the request. The precheck, the write budget and the audit event
 * stay outside the unit, so each happens once per tool call; the audit event is dispatched after the final outcome
 * (`ok`, `tool_error`, `conflict`, …), never per attempt. McpKit's own call data is rebuilt per attempt; the HTTP
 * request, principal and host context are the same objects. After a unit that cleared the entity managers (a replay, or
 * any conflict), the principal is refreshed before the event ({@see McpPrincipalRefresherInterface}); a failed refresh
 * is logged and the event still goes out. A conflict that left committed writes answers the `isError` result
 * `CONFLICT_002` (saved, do not repeat), never a protocol error (audit IH-L2, SZ-L1).
 */
final readonly class McpToolInvoker
{
    /** The fixed notice of a committed write whose result cannot be shown (audit IH-L2). */
    public const SAVED_WITHOUT_RESULT = 'The change was saved, but its result could not be returned. Do not repeat this call; read the item again if you need it.';

    /** `ECODE` of that answer: an error result (exempt from the output schema), although the write succeeded. */
    public const SAVED_ECODE = 'SAVED_001';

    /** The fixed notice of a write that met a lock conflict after part of it was committed (`CONFLICT_002`, audit SZ-L1). */
    public const CONFLICT_SAVED_TEXT = 'Part of the change was saved before a database conflict stopped it. Do not repeat this call; read the item again before deciding what to change.';

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
     * @param McpWriteRunnerInterface|null $writeRunner runs write tools as write units; null: they run once, unwrapped
     * @param McpPrincipalRefresherInterface|null $principalRefresher reloads the principal after a unit cleared the entity managers
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
        private ?McpWriteRunnerInterface $writeRunner = null,
        private ?McpPrincipalRefresherInterface $principalRefresher = null,
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
     * @throws JsonRpcError on a protocol error, a refused write budget or a retryable write conflict (`-31007`)
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
        $run     = null;

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
            $run = new McpToolAttempt($this->registry->tool($name), $principal, $this->toArray($arguments), $context, $request);
            try {
                $result = $this->execute($definition, $run);
            } catch (McpToolError $toolError) {
                return $this->finish(McpCallOutcome::ToolError, $toolError, null, $this->auditPrincipal($principal, $run->attempts() > 1, $name), $definition, $context, $started, $run->attempts());
            } catch (McpWriteConflict $conflict) {
                $this->logger->warning('MCP: write tool gave up after a database lock conflict.', ['bundle' => 'nowo_mcp_kit', 'tool' => $name, 'attempts' => $conflict->attempts, 'committed' => $conflict->committed]);
                // Every conflict (committed included) cleared the entity managers: refresh before the single event.
                $auditPrincipal = $this->auditPrincipal($principal, true, $name);
                if ($conflict->committed) {
                    // Part of the write is saved: a protocol error could be auto-retried by SDK clients (audit IH-L2, SZ-L1).
                    return $this->finish(McpCallOutcome::Conflict, new McpToolError(McpWriteConflict::COMMITTED_CODE, self::CONFLICT_SAVED_TEXT, ['saved' => true]), null, $auditPrincipal, $definition, $context, $started, $conflict->attempts);
                }
                $this->audit(McpCallOutcome::Conflict, $conflict->errorCode(), null, $auditPrincipal, $definition, $context, $started, $conflict->attempts);

                throw JsonRpcError::writeConflict($conflict);
            }

            $attempts  = $run->attempts();
            $principal = $this->auditPrincipal($principal, $attempts > 1, $name);
            if (!$definition->kind->writes()) {
                return $this->success($result, $principal, $definition, $context, $started, $attempts);
            }
            try {
                return $this->success($result, $principal, $definition, $context, $started, $attempts);
            } catch (Throwable $throwable) {
                // The write is committed: answering an error would invite a retry that writes twice (audit IH-L2).
                return $this->savedWithoutResult($throwable, $result, $principal, $definition, $context, $started, $attempts);
            }
        } catch (JsonRpcError $jsonRpcError) {
            throw $jsonRpcError;
        } catch (Throwable $throwable) {
            $this->logger->error('MCP: tool failed.', ['bundle' => 'nowo_mcp_kit', 'tool' => $name, 'exception_class' => $throwable::class]);
            $attempts = $run?->attempts() ?? 0;
            $this->audit(McpCallOutcome::InternalError, null, null, $this->auditPrincipal($principal, $attempts > 1, $name), $definition, $context, $started, $attempts);

            throw JsonRpcError::internal();
        }
    }

    /**
     * Runs the tool: once for a read-only tool or without a write runner; else inside the write runner, with a fresh
     * {@see McpToolCall} per attempt ({@see McpToolAttempt}).
     *
     * @param McpToolDefinition $definition the tool
     * @param McpToolAttempt $run the call's work
     *
     * @throws McpToolError a business refusal of the tool
     * @throws McpWriteConflict when the write unit gave up
     *
     * @return McpToolResult the tool's result
     */
    private function execute(McpToolDefinition $definition, McpToolAttempt $run): McpToolResult
    {
        if (!$this->writeRunner instanceof McpWriteRunnerInterface || !$definition->kind->writes()) {
            return $run(1);
        }

        return $this->writeRunner->run($run);
    }

    /**
     * The principal for the audit event: refreshed when the write unit cleared the entity managers, so listeners that
     * link `$user` get a managed object (audit SZ-M1). A failed refresh is logged and the original principal is used:
     * the event is never dropped.
     *
     * @param McpPrincipal $principal the principal of the call
     * @param bool $cleared whether the entity managers were cleared (a replay or a conflict)
     * @param string $name the tool name (for the log)
     *
     * @return McpPrincipal the principal to audit
     */
    private function auditPrincipal(McpPrincipal $principal, bool $cleared, string $name): McpPrincipal
    {
        if (!$cleared || !$this->principalRefresher instanceof McpPrincipalRefresherInterface) {
            return $principal;
        }
        try {
            return $this->principalRefresher->refresh($principal);
        } catch (Throwable $throwable) {
            $this->logger->error('MCP: principal refresh failed; auditing the original principal.', ['bundle' => 'nowo_mcp_kit', 'tool' => $name, 'exception_class' => $throwable::class]);

            return $principal;
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
            return [McpCallOutcome::InvalidInput, new McpToolError('INPUT_002', $violation === '' ? 'Invalid arguments' : 'Invalid field: ' . mb_substr($violation, 0, 400))];
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
     * @param int $attempts times the tool ran (0: refused before running)
     *
     * @throws LogicException when the data or the output schema is broken (answered `-32603`)
     *
     * @return stdClass the `CallToolResult`
     */
    private function success(McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started, int $attempts = 1): stdClass
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

            return $this->finish(McpCallOutcome::ToolError, new McpToolError('OUTPUT_001', 'Result too large; narrow the request'), $result, $principal, $definition, $context, $started, $attempts);
        }
        $this->audit(McpCallOutcome::Ok, null, $result, $principal, $definition, $context, $started, $attempts);

        return $answer;
    }

    /**
     * Answers a committed write whose result cannot be shown (output schema, envelope, encoding or size): `isError` with
     * `SAVED_001`, the write's ids and the fixed "saved, do not repeat" text, so no client retries it as a failure.
     *
     * @param Throwable $throwable why the result cannot be shown
     * @param McpToolResult $result the tool's result
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     * @param McpCallContext $context the call context
     * @param int $started `hrtime` at the start
     * @param int $attempts times the tool ran (0: refused before running)
     *
     * @return stdClass the `CallToolResult`
     */
    private function savedWithoutResult(Throwable $throwable, McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started, int $attempts): stdClass
    {
        $this->logger->error('MCP: write saved, but its result could not be returned.', ['bundle' => 'nowo_mcp_kit', 'tool' => $definition->name, 'exception_class' => $throwable::class]);
        $this->audit(McpCallOutcome::SavedWithoutResult, null, $result, $principal, $definition, $context, $started, $attempts);

        // An error result: exempt from the output schema, so SDK clients pass it to the model (audit IH-L2r). Only ids that
        // are plain ASCII are echoed, so encoding cannot fail and no second event can follow (IH-I8).
        $ids = [];
        foreach ($result->auditIds as $key => $id) {
            // Typed as strings, but a host bug must not make this answer throw (audit IH-I15).
            $id = is_int($id) ? (string) $id : $id;
            if (is_string($id) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $key) === 1 && preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $id) === 1) {
                $ids[(string) $key] = $id;
            }
        }

        $answer          = new stdClass();
        $answer->content = [['type' => 'text', 'text' => McpEnvelope::text(['ECODE' => self::SAVED_ECODE, 'err' => self::SAVED_WITHOUT_RESULT, 'saved' => true, 'ids' => (object) $ids], true)]];
        $answer->isError = true;

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
     * @param int $attempts times the tool ran (0: refused before running)
     *
     * @return stdClass the `CallToolResult`
     */
    private function finish(McpCallOutcome $outcome, McpToolError $error, ?McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started, int $attempts = 0): stdClass
    {
        $this->audit($outcome, $error->ecode, $result, $principal, $definition, $context, $started, $attempts);

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
     * @param int $attempts times the tool ran (0: refused before running)
     */
    private function audit(McpCallOutcome $outcome, ?string $ecode, ?McpToolResult $result, McpPrincipal $principal, McpToolDefinition $definition, McpCallContext $context, int $started, int $attempts = 0): void
    {
        $durationMs = intdiv((int) hrtime(true) - $started, 1000000);
        if ($durationMs >= $this->slowCallMs) {
            $this->logger->warning('MCP: slow tool call.', ['bundle' => 'nowo_mcp_kit', 'tool' => $definition->name, 'duration_ms' => $durationMs]);
        }
        try {
            $this->events->dispatch(new McpToolCallEvent($principal, $definition->name, $definition->kind, $outcome, $ecode, $result->auditIds ?? [], $result->changedFields ?? [], $context, $durationMs, $attempts, $attempts > 1));
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
