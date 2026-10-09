<?php
// file generated with AI assistance: Claude Code - 2026-10-10 00:20:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use Dmstr\ApiPlatformUtils\OpenApi\InvalidDefaultSchemaDecorator;
use PHPUnit\Framework\TestCase;

final class InvalidDefaultSchemaDecoratorTest extends TestCase
{
    private const SLUG = ['type' => 'string', 'pattern' => '^([a-z0-9]+(?:-[a-z0-9]+)*)$'];

    public function testDropsDefaultThatViolatesPatternInsideAllOf(): void
    {
        // the shape of a `.jsonld` read schema: properties inside allOf[1]
        $properties = $this->build(['Node.jsonld' => ['allOf' => [
            ['$ref' => '#/definitions/HydraItemBaseSchema'],
            ['type' => 'object', 'properties' => ['slug' => self::SLUG + ['default' => '']]],
        ]]])['Node.jsonld']['allOf'][1]['properties'];

        self::assertSame(self::SLUG, $properties['slug']);
    }

    public function testKeepsDefaultsTheSchemaAccepts(): void
    {
        $properties = $this->build(['Node' => ['type' => 'object', 'properties' => [
            'slug' => self::SLUG + ['default' => 'home'],
            'title' => ['type' => 'string', 'default' => ''],
            'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'default' => 'draft'],
            'position' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
        ]]])['Node']['properties'];

        self::assertSame('home', $properties['slug']['default']);
        self::assertSame('', $properties['title']['default']);
        self::assertSame('draft', $properties['status']['default']);
        self::assertSame(0, $properties['position']['default']);
    }

    public function testDropsDefaultsViolatingLengthAndEnum(): void
    {
        $properties = $this->build(['Node' => ['type' => 'object', 'properties' => [
            'name' => ['type' => 'string', 'minLength' => 1, 'default' => ''],
            'code' => ['type' => 'string', 'maxLength' => 2, 'default' => 'abc'],
            'status' => ['type' => 'string', 'enum' => ['draft'], 'default' => 'gone'],
        ]]])['Node']['properties'];

        self::assertArrayNotHasKey('default', $properties['name']);
        self::assertArrayNotHasKey('default', $properties['code']);
        self::assertArrayNotHasKey('default', $properties['status']);
    }

    public function testWalksNestedObjectProperties(): void
    {
        $nested = $this->build(['Node' => ['type' => 'object', 'properties' => [
            'meta' => ['type' => 'object', 'properties' => ['key' => self::SLUG + ['default' => '']]],
        ]]])['Node']['properties']['meta']['properties'];

        self::assertArrayNotHasKey('default', $nested['key']);
    }

    public function testKeepsDefaultWhenPhpCannotCompileThePattern(): void
    {
        // ECMA-only syntax PHP's PCRE rejects: keep rather than guess
        $properties = $this->build(['Node' => ['type' => 'object', 'properties' => [
            'odd' => ['type' => 'string', 'pattern' => '(?<=a', 'default' => ''],
        ]]])['Node']['properties'];

        self::assertSame('', $properties['odd']['default']);
    }

    public function testPatternWithSlashIsEscaped(): void
    {
        $properties = $this->build(['Node' => ['type' => 'object', 'properties' => [
            'path' => ['type' => 'string', 'pattern' => '^/[a-z]+$', 'default' => '/home'],
            'bad' => ['type' => 'string', 'pattern' => '^/[a-z]+$', 'default' => ''],
        ]]])['Node']['properties'];

        self::assertSame('/home', $properties['path']['default']);
        self::assertArrayNotHasKey('default', $properties['bad']);
    }

    /**
     * @param array<string, mixed> $definitions
     */
    private function build(array $definitions): \ArrayObject
    {
        $inner = new class(new \ArrayObject($definitions)) implements SchemaFactoryInterface {
            public function __construct(private readonly \ArrayObject $definitions)
            {
            }

            public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
            {
                $schema = new Schema();
                $schema->setDefinitions($this->definitions);

                return $schema;
            }
        };

        return (new InvalidDefaultSchemaDecorator($inner))->buildSchema('Node')->getDefinitions();
    }
}
