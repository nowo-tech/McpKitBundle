<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Paging;

use Nowo\McpKitBundle\Paging\McpCursorSigner;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Tool\McpToolError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function str_repeat;

#[CoversClass(McpCursorSigner::class)]
final class McpCursorSignerTest extends TestCase
{
    private static function principal(string $tokenId): McpPrincipal
    {
        return new McpPrincipal(new stdClass(), $tokenId, 'n');
    }

    #[Test]
    public function signedCursorsRoundTripForTheSameTokenAndTool(): void
    {
        $signer = new McpCursorSigner('test-secret');
        $cursor = $signer->encode(['o' => 50], self::principal('t1'), 'note_list');

        self::assertMatchesRegularExpression('/' . McpCursorSigner::schema()['pattern'] . '/', $cursor);
        self::assertSame(['o' => 50], $signer->decode($cursor, self::principal('t1'), 'note_list'));
        self::assertNull($signer->decode(null, self::principal('t1'), 'note_list'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refused(): iterable
    {
        $signer = new McpCursorSigner('test-secret');
        $good   = $signer->encode(['o' => 50], self::principal('t1'), 'note_list');

        yield 'other token' => [$good, 't2', 'note_list'];
        yield 'other tool' => [$good, 't1', 'task_search'];
        yield 'other secret' => [(new McpCursorSigner('other'))->encode(['o' => 50], self::principal('t1'), 'note_list'), 't1', 'note_list'];
        yield 'unsigned' => ['eyJvIjo1MH0', 't1', 'note_list'];
        yield 'tampered position' => ['eyJvIjo5OX0.' . explode('.', $good)[1], 't1', 'note_list'];
        yield 'too long' => [str_repeat('a', 600) . '.' . explode('.', $good)[1], 't1', 'note_list'];
    }

    #[Test]
    #[DataProvider('refused')]
    public function forgedOrForeignCursorsAreInvalid(string $cursor, string $tokenId, string $tool): void
    {
        $this->expectException(McpToolError::class);
        $this->expectExceptionMessage('Invalid field: cursor');
        (new McpCursorSigner('test-secret'))->decode($cursor, self::principal($tokenId), $tool);
    }
}
