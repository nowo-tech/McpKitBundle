<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Schema;

use LogicException;
use Nowo\McpKitBundle\Schema\McpSchemaNormalizer;
use Nowo\McpKitBundle\Schema\McpSchemaRules;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\MisbehavingTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteArchiveTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteCreateTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(McpSchemaRules::class)]
#[CoversClass(McpSchemaNormalizer::class)]
#[CoversClass(McpToolDefinition::class)]
final class McpSchemaRulesTest extends TestCase
{
    private const INPUT  = ['type' => 'object', 'additionalProperties' => false, 'properties' => []];
    private const OUTPUT = ['type' => 'object', 'additionalProperties' => false, 'properties' => []];

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     */
    private static function tool(array $input = self::INPUT, array $output = self::OUTPUT, string $name = 'a_tool', string $title = 'T', string $description = 'D'): McpToolDefinition
    {
        return new McpToolDefinition($name, $title, $description, McpToolKind::ReadOnly, true, $input, $output);
    }

    #[Test]
    public function fixtureToolsPass(): void
    {
        foreach ([NoteGetTool::definition(), NoteCreateTool::definition(), NoteArchiveTool::definition(), MisbehavingTool::definition()] as $definition) {
            McpSchemaRules::checkTool($definition);
        }
        $this->addToAssertionCount(4);
    }

    /**
     * @return iterable<string, array{McpToolDefinition, string}>
     */
    public static function violations(): iterable
    {
        $object = static fn (array $properties): array => ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties];

        yield 'name with dot' => [self::tool(name: 'a.tool'), 'must match'];
        yield 'name upper' => [self::tool(name: 'Tool'), 'must match'];
        yield 'empty title' => [self::tool(title: ''), 'title must be'];
        yield 'title with newline' => [self::tool(title: "a\nb"), 'title must be'];
        yield 'description with bidi' => [self::tool(description: "a\u{202E}b"), 'description must be'];
        yield 'long description' => [self::tool(description: str_repeat('d', 2001)), 'description must be'];
        yield 'input root open' => [self::tool(['type' => 'object']), 'root must be'];
        yield 'input root array' => [self::tool(['type' => 'array', 'additionalProperties' => false]), 'root must be'];
        yield 'string without maxLength' => [self::tool($object(['q' => ['type' => 'string']])), 'needs "maxLength"'];
        yield 'array without maxItems' => [self::tool($object(['q' => ['type' => 'array', 'items' => ['type' => 'integer']]])), 'needs "maxItems"'];
        yield 'array without items' => [self::tool($object(['q' => ['type' => 'array', 'maxItems' => 2]])), 'needs "items"'];
        yield 'nested open object' => [self::tool($object(['q' => ['type' => 'object']])), 'additionalProperties'];
        yield 'x-mcp-header' => [self::tool($object(['q' => ['type' => 'string', 'maxLength' => 5, 'x-mcp-header' => 'Q']])), 'not allowed: x-mcp-header'];
        yield 'remote ref' => [self::tool($object(['q' => ['$ref' => 'https://evil.example/s.json']])), '$ref must point'];
        yield 'untyped input' => [self::tool($object(['q' => ['description' => 'any']])), 'declare "type"'];
        yield 'unknown type' => [self::tool($object(['q' => ['type' => 'file']])), 'unknown type'];
        yield 'list as schema' => [self::tool($object(['q' => [1, 2]])), 'must be an object'];
        yield 'too many branches' => [self::tool($object(['q' => ['oneOf' => [['const' => 1], ['const' => 2], ['const' => 3], ['const' => 4], ['const' => 5], ['const' => 6], ['const' => 7], ['const' => 8], ['const' => 9]]]])), 'at most 8'];
        yield 'wildcard pattern' => [self::tool(output: $object(['t' => ['type' => 'string', 'pattern' => '^.*$', 'maxLength' => 10]])), 'free text'];
        yield 'whitespace class pattern' => [self::tool(output: $object(['t' => ['type' => 'string', 'pattern' => '^[\\s\\S]+$', 'maxLength' => 10]])), 'free text'];
        yield 'negated class pattern' => [self::tool(output: $object(['t' => ['type' => 'string', 'pattern' => '^[^x]+$', 'maxLength' => 10]])), 'free text'];
        yield 'unanchored pattern' => [self::tool(output: $object(['t' => ['type' => 'string', 'pattern' => '[a-z]+', 'maxLength' => 10]])), 'free text'];
        yield 'pattern without maxLength' => [self::tool(output: $object(['t' => ['type' => 'string', 'pattern' => '^[a-z]+$']])), 'free text'];
        yield 'pattern with long maxLength' => [self::tool(output: $object(['t' => ['type' => 'string', 'pattern' => '^[a-z]+$', 'maxLength' => 513]])), 'free text'];
        yield 'uri without pattern' => [self::tool(output: $object(['u' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 100]])), 'format": "uri" needs'];
        yield 'data uri pattern' => [self::tool(output: $object(['u' => ['type' => 'string', 'format' => 'uri', 'pattern' => '^data:', 'maxLength' => 100]])), 'format": "uri" needs'];
        yield 'long uri' => [self::tool(output: $object(['u' => ['type' => 'string', 'format' => 'uri', 'pattern' => '^https://', 'maxLength' => 4096]])), 'format": "uri" needs'];
        yield 'free format' => [self::tool(output: $object(['e' => ['type' => 'string', 'format' => 'email', 'pattern' => '^[a-z]+$', 'maxLength' => 10]])), 'free text'];
        yield 'top-level alternation' => [self::tool(output: $object(['s' => ['type' => 'string', 'pattern' => '^open|closed$', 'maxLength' => 10]])), 'free text'];
        yield 'top-level alternation of groups' => [self::tool(output: $object(['s' => ['type' => 'string', 'pattern' => '^(?:open)|(?:closed)$', 'maxLength' => 10]])), 'free text'];
        yield 'unbalanced group' => [self::tool(output: $object(['s' => ['type' => 'string', 'pattern' => '^(a$', 'maxLength' => 10]])), 'pattern'];
        yield 'closing group first' => [self::tool(output: $object(['s' => ['type' => 'string', 'pattern' => '^a)(b$', 'maxLength' => 10]])), 'pattern'];
        yield 'mixed-class range' => [self::tool(output: $object(['s' => ['type' => 'string', 'pattern' => '^[0-z]+$', 'maxLength' => 10]])), 'free text'];
        yield 'uri with wildcard' => [self::tool(output: $object(['u' => ['type' => 'string', 'format' => 'uri', 'pattern' => '^https://.*$', 'maxLength' => 100]])), 'format": "uri" needs'];
        yield 'uri with top-level alternation' => [self::tool(output: $object(['u' => ['type' => 'string', 'format' => 'uri', 'pattern' => '^https://x|.*$', 'maxLength' => 100]])), 'format": "uri" needs'];
        yield 'output root not object' => [self::tool(output: ['type' => 'array', 'items' => ['type' => 'integer']]), 'root must be "type": "object"'];
        yield 'free text in output' => [self::tool(output: $object(['title' => ['type' => 'string']])), 'free text must sit under "untrusted"'];
        yield 'untrusted not object' => [self::tool(output: $object(['untrusted' => ['type' => 'string']])), 'must be "type": "object"'];
        yield 'free text in output defs' => [self::tool(output: ['type' => 'object', 'additionalProperties' => false, '$defs' => ['t' => ['type' => 'string']]]), 'free text'];
        yield 'invalid minimum' => [self::tool($object(['q' => ['type' => 'integer', 'minimum' => 'zero']])), 'inputSchema#/properties/q: invalid value for "minimum"'];
        yield 'negative maxLength' => [self::tool($object(['q' => ['type' => 'string', 'maxLength' => -1]])), 'invalid value for "maxLength"'];
        yield 'invalid pattern' => [self::tool($object(['q' => ['type' => 'string', 'maxLength' => 5, 'pattern' => '(']])), 'invalid value for "pattern"'];
        yield 'invalid required' => [self::tool(['type' => 'object', 'additionalProperties' => false, 'required' => [1]]), 'invalid value for "required"'];
        yield 'empty enum' => [self::tool($object(['q' => ['enum' => []]])), 'invalid value for "enum"'];
        yield 'invalid uniqueItems' => [self::tool($object(['q' => ['type' => 'array', 'maxItems' => 2, 'items' => ['type' => 'integer'], 'uniqueItems' => 'yes']])), 'invalid value for "uniqueItems"'];
        yield 'invalid format' => [self::tool(output: $object(['q' => ['type' => 'string', 'format' => 5]])), 'outputSchema#/properties/q: invalid value for "format"'];
        yield 'invalid properties' => [self::tool(['type' => 'object', 'additionalProperties' => false, 'properties' => 'x']), 'invalid value for "properties"'];

        $deep = ['type' => 'integer'];
        for ($i = 0; $i < 9; ++$i) {
            $deep = $object(['n' => $deep]);
        }
        yield 'too deep' => [self::tool($deep), 'nested deeper'];
    }

    #[Test]
    #[DataProvider('violations')]
    public function violationsStopTheBuild(McpToolDefinition $definition, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);
        McpSchemaRules::checkTool($definition);
    }

    #[Test]
    public function outputStringsOutsideUntrustedMayBeBounded(): void
    {
        $output = ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'id'     => ['type' => 'string', 'format' => 'uuid'],
            'at'     => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'number' => ['type' => 'string', 'pattern' => '^#[0-9]+$', 'maxLength' => 20],
            'link'   => ['type' => 'string', 'format' => 'uri', 'pattern' => '^https://app\\.example/[a-z0-9/-]{0,200}$', 'maxLength' => 2048],
            'state'  => ['type' => 'string', 'pattern' => '^(?:open|closed)$', 'maxLength' => 6],
            'slug'   => ['type' => 'string', 'pattern' => '^[a-z0-9_-]{1,64}$', 'maxLength' => 64],
            'status' => ['type' => 'string', 'enum' => ['open', 'done']],
            'ref'    => ['$ref' => '#/$defs/row'],
            'rows'   => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => ['untrusted' => ['type' => 'object', 'properties' => ['t' => ['type' => 'string']]]]]],
            'choice' => ['oneOf' => [['type' => 'integer'], ['type' => 'null']]],
        ], '$defs' => ['row' => ['type' => 'integer']]];
        McpSchemaRules::checkTool(self::tool(output: $output));
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function normalizerKeepsObjectsAsObjects(): void
    {
        $schema = McpSchemaNormalizer::toObject(['type' => 'object', 'properties' => [], 'required' => [], 'items' => [], 'oneOf' => [[], 'x'], 'enum' => ['a'], 'default' => ['k' => 'v'], '$defs' => ['d' => [], 'e' => true]]);

        self::assertSame('{"type":"object","properties":{},"required":[],"items":{},"oneOf":[{},"x"],"enum":["a"],"default":{"k":"v"},"$defs":{"d":{},"e":true}}', json_encode($schema));
    }
}
