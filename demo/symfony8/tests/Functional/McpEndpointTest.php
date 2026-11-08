<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class McpEndpointTest extends WebTestCase
{
    private const DISCOVER = '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';

    #[Test]
    public function discoverWithADemoToken(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp', [], [], [
            'HTTP_AUTHORIZATION'        => 'Bearer demo-read-token-change-me',
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_ACCEPT'               => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD'           => 'server/discover',
        ], self::DISCOVER);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"supportedVersions":["2026-07-28","2025-11-25","2025-06-18"]', (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function aBadTokenAnswers401(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp', [], [], ['HTTP_AUTHORIZATION' => 'Bearer nope', 'CONTENT_TYPE' => 'application/json'], self::DISCOVER);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function getAnswers405(): void
    {
        $client = self::createClient();
        $client->request('GET', '/mcp');

        self::assertResponseStatusCodeSame(405);
    }
}
