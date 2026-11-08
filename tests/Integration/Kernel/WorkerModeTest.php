<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Server\McpToolInvoker;
use Nowo\McpKitBundle\Tests\Fixtures\AuditRecorder;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAuthorizer;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteArchiveTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteCreateTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

use function array_column;

/**
 * SC-007 (kit side): two tokens alternating on one kernel never see each other's tools or context.
 */
#[CoversClass(McpEndpointController::class)]
#[CoversClass(McpToolInvoker::class)]
final class WorkerModeTest extends KernelTestCase
{
    #[Test]
    public function alternatingTokensNeverSeeEachOthersTools(): void
    {
        $kernel = $this->bootKernel(['hooks' => ['authorizer' => FixtureAuthorizer::class]], [
            NoteGetTool::class       => NoteGetTool::class,
            NoteCreateTool::class    => NoteCreateTool::class,
            NoteArchiveTool::class   => NoteArchiveTool::class,
            FixtureAuthorizer::class => FixtureAuthorizer::class,
            AuditRecorder::class     => AuditRecorder::class,
        ]);

        for ($i = 0; $i < 250; ++$i) {
            $ada = array_column($this->json($this->modern($kernel, 'tools/list', [], [], 'tok-ada', $i))['result']['tools'], 'name');
            self::assertSame(['note_get'], $ada);

            $grace = array_column($this->json($this->modern($kernel, 'tools/list', [], [], 'tok-grace', $i))['result']['tools'], 'name');
            self::assertSame(['note_get', 'note_archive', 'note_create'], $grace);

            $refused = $this->json($this->modern($kernel, 'tools/call', ['name' => 'note_archive', 'arguments' => ['id' => NoteGetTool::ID]], [], 'tok-ada', $i))['result'];
            self::assertTrue($refused['isError']);
        }
        $events = $this->service($kernel, AuditRecorder::class)->events;
        self::assertCount(250, $events);
        foreach ($events as $event) {
            self::assertSame('id-ada', $event->principal->tokenId);
        }
    }
}
