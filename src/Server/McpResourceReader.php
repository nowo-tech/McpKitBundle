<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Server;

use LogicException;
use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Envelope\McpEnvelope;
use Nowo\McpKitBundle\Event\McpCallOutcome;
use Nowo\McpKitBundle\Event\McpToolCallEvent;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Registry\McpResourceTemplateRegistry;
use Nowo\McpKitBundle\Schema\McpSchemaNormalizer;
use Nowo\McpKitBundle\Schema\McpSchemaValidator;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpToolAuthorizerInterface;
use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function hrtime;
use function intdiv;
use function is_string;
use function preg_match;
use function strlen;

/**
 * `resources/templates/list` and `resources/read`. `resources/list` is always empty (enumerating everything a token
 * reaches is expensive and helps nobody).
 *
 * A read matches the URI against the templates the principal may use, in order; anything unmatched, hidden or refused by
 * the template answers the same `-32602` "Resource not found". Contents are one JSON text with the same envelope as
 * tools: when it holds untrusted values, its first key is `notice`.
 */
final readonly class McpResourceReader
{
    private LoggerInterface $logger;

    /**
     * Creates the reader.
     *
     * @param McpResourceTemplateRegistry $registry compiled templates
     * @param McpToolAuthorizerInterface $authorizer host per-template decision
     * @param EventDispatcherInterface $events event dispatcher (audit)
     * @param int $maxResultBytes contents cap
     * @param LoggerInterface|null $logger logger (template names and exception classes only)
     */
    public function __construct(
        private McpResourceTemplateRegistry $registry,
        private McpToolAuthorizerInterface $authorizer,
        private EventDispatcherInterface $events,
        private int $maxResultBytes = 2097152,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * The `resourceTemplates` the principal may use.
     *
     * @param McpPrincipal $principal the principal
     *
     * @return list<stdClass> the template entries
     */
    public function listTemplates(McpPrincipal $principal): array
    {
        $templates = [];
        foreach ($this->registry->all() as $definition) {
            if (!$this->authorizer->allows($principal, $definition)) {
                continue;
            }
            $entry              = new stdClass();
            $entry->uriTemplate = $definition->uriTemplate;
            $entry->name        = $definition->name;
            $entry->title       = $definition->title;
            $entry->description = $definition->description;
            $entry->mimeType    = 'application/json';
            $templates[]        = $entry;
        }

        return $templates;
    }

    /**
     * Reads a resource.
     *
     * @param McpRequest $rpc the request
     * @param McpPrincipal $principal the principal
     * @param Request $request the HTTP request
     *
     * @throws JsonRpcError `-32602` when not found, `-32603` on a broken template
     *
     * @return stdClass the `ReadResourceResult` (without the modern decoration)
     */
    public function read(McpRequest $rpc, McpPrincipal $principal, Request $request): stdClass
    {
        $uri = $rpc->params->uri ?? null;
        if (!is_string($uri)) {
            throw JsonRpcError::invalidParams('Missing uri');
        }
        foreach ($this->registry->all() as $definition) {
            if (strlen($uri) <= 2048 && preg_match($definition->pattern(), $uri, $match) === 1 && $this->authorizer->allows($principal, $definition)) {
                $variables = [];
                foreach ($definition->variables as $name => $pattern) {
                    $variables[$name] = (string) $match[$name];
                }

                return $this->readMatched($definition, $uri, $variables, $rpc, $principal, $request);
            }
        }

        throw $this->notFound();
    }

    /**
     * Reads through a matched template.
     *
     * @param McpResourceTemplateDefinition $definition the template
     * @param string $uri the URI
     * @param array<string, string> $variables matched variables
     * @param McpRequest $rpc the request
     * @param McpPrincipal $principal the principal
     * @param Request $request the HTTP request
     *
     * @throws JsonRpcError `-32602` or `-32603`
     *
     * @return stdClass the result
     */
    private function readMatched(McpResourceTemplateDefinition $definition, string $uri, array $variables, McpRequest $rpc, McpPrincipal $principal, Request $request): stdClass
    {
        $context = $rpc->context->withTool($definition->name);
        $request->attributes->set(McpCallContext::ATTRIBUTE, $context);
        $started = (int) hrtime(true);

        try {
            $result = $this->registry->template($definition->name)->read(new McpResourceRead($principal, $uri, $variables, $context, $request));
        } catch (McpToolError $error) {
            $this->audit(McpCallOutcome::ToolError, $error->ecode, null, $principal, $definition, $context, $started);

            throw $this->notFound();
        } catch (Throwable $throwable) {
            $this->fail($throwable, $principal, $definition, $context, $started);
        }

        try {
            $converted = McpEnvelope::convert($result->data);
            if (McpSchemaValidator::violation($converted['json'], McpSchemaNormalizer::toObject($definition->outputSchema)) !== null) {
                throw new LogicException('Resource output does not match its outputSchema.');
            }
        } catch (Throwable $throwable) {
            $this->fail($throwable, $principal, $definition, $context, $started);
        }

        $json = $converted['json'];
        if ($converted['untrusted'] && $json instanceof stdClass) {
            $json = (object) (['notice' => McpProtocol::UNTRUSTED_NOTICE] + (array) $json);
        }
        $text = McpEnvelope::text($json);
        if (strlen($text) > $this->maxResultBytes) {
            $this->audit(McpCallOutcome::ToolError, 'OUTPUT_001', $result, $principal, $definition, $context, $started);

            throw JsonRpcError::invalidParamsOk('Resource too large');
        }
        $this->audit(McpCallOutcome::Ok, null, $result, $principal, $definition, $context, $started);

        $answer           = new stdClass();
        $answer->contents = [['uri' => $uri, 'mimeType' => 'application/json', 'text' => $text]];

        return $answer;
    }

    /**
     * Logs, audits and answers `-32603`.
     *
     * @param Throwable $throwable what failed
     * @param McpPrincipal $principal the principal
     * @param McpResourceTemplateDefinition $definition the template
     * @param McpCallContext $context the call context
     * @param int $started `hrtime` at the start
     *
     * @throws JsonRpcError always
     */
    private function fail(Throwable $throwable, McpPrincipal $principal, McpResourceTemplateDefinition $definition, McpCallContext $context, int $started): never
    {
        $this->logger->error('MCP: resource template failed.', ['bundle' => 'nowo_mcp_kit', 'template' => $definition->name, 'exception_class' => $throwable::class]);
        $this->audit(McpCallOutcome::InternalError, null, null, $principal, $definition, $context, $started);

        throw JsonRpcError::internal();
    }

    /**
     * The one answer for anything not readable.
     *
     * @return JsonRpcError `-32602` "Resource not found"
     */
    private function notFound(): JsonRpcError
    {
        return JsonRpcError::invalidParamsOk('Resource not found');
    }

    /**
     * Dispatches the audit event; a listener that throws is logged.
     *
     * @param McpCallOutcome $outcome the outcome
     * @param string|null $ecode the error code
     * @param McpToolResult|null $result the template's result
     * @param McpPrincipal $principal the principal
     * @param McpResourceTemplateDefinition $definition the template
     * @param McpCallContext $context the call context
     * @param int $started `hrtime` at the start
     */
    private function audit(McpCallOutcome $outcome, ?string $ecode, ?McpToolResult $result, McpPrincipal $principal, McpResourceTemplateDefinition $definition, McpCallContext $context, int $started): void
    {
        try {
            $this->events->dispatch(new McpToolCallEvent($principal, $definition->name, McpToolKind::ReadOnly, $outcome, $ecode, $result->auditIds ?? [], $result->changedFields ?? [], $context, intdiv((int) hrtime(true) - $started, 1000000)));
        } catch (Throwable $throwable) {
            $this->logger->error('MCP: audit listener failed.', ['bundle' => 'nowo_mcp_kit', 'template' => $definition->name, 'exception_class' => $throwable::class]);
        }
    }
}
