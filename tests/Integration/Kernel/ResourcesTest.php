<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\DependencyInjection\Compiler\McpRegistryPass;
use Nowo\McpKitBundle\Event\McpCallOutcome;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Registry\McpResourceTemplateRegistry;
use Nowo\McpKitBundle\Server\McpDispatcher;
use Nowo\McpKitBundle\Server\McpResourceReader;
use Nowo\McpKitBundle\Tests\Fixtures\AuditRecorder;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAuthorizer;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteResource;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\SecretResource;
use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

use function array_column;
use function base64_encode;
use function json_decode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Resource templates through the whole kernel.
 */
#[CoversClass(McpResourceReader::class)]
#[CoversClass(McpResourceTemplateRegistry::class)]
#[CoversClass(McpRegistryPass::class)]
#[CoversClass(McpDispatcher::class)]
#[CoversClass(McpResourceRead::class)]
#[CoversClass(McpResourceTemplateDefinition::class)]
final class ResourcesTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function boot(array $config = []): McpKitTestKernel
    {
        return $this->bootKernel(array_replace_recursive(['hooks' => ['authorizer' => FixtureAuthorizer::class]], $config), [
            NoteResource::class      => NoteResource::class,
            SecretResource::class    => SecretResource::class,
            FixtureAuthorizer::class => FixtureAuthorizer::class,
            AuditRecorder::class     => AuditRecorder::class,
        ]);
    }

    #[Test]
    public function templatesFollowTheAuthorizer(): void
    {
        $kernel = $this->boot();

        $ada = $this->json($this->modern($kernel, 'resources/templates/list', [], [], 'tok-ada'))['result'];
        self::assertSame([['uriTemplate' => 'demo://note/{id}', 'name' => 'note', 'title' => 'Note', 'description' => 'One note as JSON.', 'mimeType' => 'application/json']], $ada['resourceTemplates']);
        self::assertSame('private', $ada['cacheScope']);

        $grace = $this->json($this->modern($kernel, 'resources/templates/list', [], [], 'tok-grace'))['result']['resourceTemplates'];
        self::assertSame(['note', 'secret'], array_column($grace, 'name'));
        self::assertSame([], $this->json($this->modern($kernel, 'resources/list', [], [], 'tok-grace'))['result']['resources']);
    }

    #[Test]
    public function aReadCarriesTheEnvelope(): void
    {
        $kernel = $this->boot();
        $uri    = 'demo://note/' . NoteGetTool::ID;
        $result = $this->json($this->modern($kernel, 'resources/read', ['uri' => $uri], [], 'tok-ada'))['result'];

        self::assertSame(0, $result['ttlMs']);
        self::assertSame('private', $result['cacheScope']);
        self::assertSame('complete', $result['resultType']);
        $content = $result['contents'][0];
        self::assertSame($uri, $content['uri']);
        self::assertSame('application/json', $content['mimeType']);
        $json = json_decode($content['text'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['notice' => McpProtocol::UNTRUSTED_NOTICE, 'id' => NoteGetTool::ID, 'untrusted' => ['title' => 'Runbook', 'body' => NoteGetTool::INJECTION]], $json);
        self::assertStringNotContainsString('</untrusted>', $content['text']);

        $read = $this->service($kernel, NoteResource::class)->reads[0];
        self::assertSame(['id' => NoteGetTool::ID], $read->variables);
        self::assertSame('note', $read->context->toolName);
        self::assertSame($uri, $read->uri);
        self::assertSame(McpCallOutcome::Ok, $this->service($kernel, AuditRecorder::class)->events[0]->outcome);
    }

    #[Test]
    public function contentsWithoutUntrustedValuesHaveNoNotice(): void
    {
        $kernel = $this->boot();
        $result = $this->json($this->modern($kernel, 'resources/read', ['uri' => 'demo://secret/abc/42'], [], 'tok-grace'))['result'];

        self::assertSame('{"n":42}', $result['contents'][0]['text']);
        self::assertSame('{"id":"00000000-0000-4000-8000-000000000001"}', $this->json($this->modern($kernel, 'resources/read', ['uri' => 'demo://note/00000000-0000-4000-8000-000000000001'], [], 'tok-ada'))['result']['contents'][0]['text']);
    }

    #[Test]
    public function everythingUnreadableIsTheSameNotFound(): void
    {
        $kernel = $this->boot();

        foreach ([
            ['demo://note/not-a-uuid', 'tok-ada'],
            ['demo://note/' . NoteGetTool::ID . '/x', 'tok-ada'],
            ['demo://secret/abc/42', 'tok-ada'],
            ['demo://note/0b8f3c5e-6a1d-4c7e-9f2a-1b3c5d7e9f02', 'tok-ada'],
            ['file:///etc/hosts', 'tok-grace'],
            ['demo://note/' . str_repeat('a', 3000), 'tok-grace'],
        ] as [$uri, $token]) {
            $response = $this->modern($kernel, 'resources/read', ['uri' => $uri], [], $token);
            $this->assertRpcError($response, 200, -32602);
            self::assertSame('Resource not found', $this->json($response)['error']['message']);
        }
        self::assertSame(McpCallOutcome::ToolError, $this->service($kernel, AuditRecorder::class)->events[0]->outcome);
        self::assertSame('ACCESS_001', $this->service($kernel, AuditRecorder::class)->events[0]->ecode);
    }

    #[Test]
    public function brokenTemplatesAnswerInternalErrors(): void
    {
        $kernel = $this->boot();

        foreach (['00000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000000003'] as $id) {
            $response = $this->modern($kernel, 'resources/read', ['uri' => 'demo://note/' . $id], [], 'tok-ada');
            $this->assertRpcError($response, 500, -32603);
            self::assertStringNotContainsString('bt_template_leak', (string) $response->getContent());
        }
        self::assertSame(McpCallOutcome::InternalError, $this->service($kernel, AuditRecorder::class)->events[0]->outcome);
    }

    #[Test]
    public function oversizedContentsAreRefused(): void
    {
        $kernel   = $this->boot(['limits' => ['max_result_bytes' => 2048]]);
        $response = $this->modern($kernel, 'resources/read', ['uri' => 'demo://note/00000000-0000-4000-8000-000000000004'], [], 'tok-ada');

        $this->assertRpcError($response, 200, -32602);
        self::assertSame('Resource too large', $this->json($response)['error']['message']);
    }

    #[Test]
    public function theNameHeaderCarriesTheUriBase64Encoded(): void
    {
        $kernel = $this->boot();
        $uri    = 'demo://note/' . NoteGetTool::ID;

        self::assertSame(200, $this->modern($kernel, 'resources/read', ['uri' => $uri], ['Mcp-Name' => '=?base64?' . base64_encode($uri) . '?='], 'tok-ada')->getStatusCode());
    }

    #[Test]
    public function legacyReadsHaveNoModernFields(): void
    {
        $kernel = $this->boot();
        $result = $this->json($this->legacy($kernel, 'resources/read', ['uri' => 'demo://note/' . NoteGetTool::ID]))['result'];

        self::assertArrayNotHasKey('ttlMs', $result);
        self::assertCount(1, $result['contents']);
        $this->assertRpcError($this->legacy($kernel, 'resources/read', ['uri' => 'demo://nope']), 200, -32602);
    }

    #[Test]
    public function aThrowingListenerDoesNotBreakAReadOrNotFound(): void
    {
        $kernel                                              = $this->boot();
        $this->service($kernel, AuditRecorder::class)->throw = true;

        self::assertSame(200, $this->modern($kernel, 'resources/read', ['uri' => 'demo://note/' . NoteGetTool::ID], [], 'tok-ada')->getStatusCode());
    }
}
