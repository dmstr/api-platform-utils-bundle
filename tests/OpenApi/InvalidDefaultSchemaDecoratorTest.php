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
        // `[^]` is valid ECMA-262 (any character) but an unterminated class in
        // PCRE: PHP cannot judge it, so the default stays rather than guess
        self::assertFalse(@preg_match('/[^]/u', 'x'));

        $properties = $this->build(['Node' => ['type' => 'object', 'properties' => [
            'odd' => ['type' => 'string', 'pattern' => '[^]', 'default' => 'x'],
        ]]])['Node']['properties'];

        self::assertSame('x', $properties['odd']['default']);
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

    public function testDropsDefaultInPlainArrayDefinition(): void
    {
        // definitions may also be plain arrays (e.g. the Hydra base schemas)
        $properties = $this->build(['Node' => ['type' => 'object', 'properties' => [
            'slug' => self::SLUG + ['default' => ''],
            'title' => ['type' => 'string', 'default' => ''],
        ]]], plain: true)['Node']['properties'];

        self::assertSame(self::SLUG, $properties['slug']);
        self::assertSame('', $properties['title']['default']);
    }

    public function testCleansDefinitionsWrittenIntoThePassedSchema(): void
    {
        $schema = new Schema();
        $definitions = $schema->getDefinitions();
        $inner = $this->innerFactory(['Node' => ['type' => 'object', 'properties' => [
            'slug' => self::SLUG + ['default' => ''],
        ]]]);

        $result = (new InvalidDefaultSchemaDecorator($inner))->buildSchema('Node', schema: $schema);

        self::assertSame($schema, $result);
        self::assertSame($definitions, $result->getDefinitions());
        self::assertInstanceOf(\ArrayObject::class, $definitions['Node']);
        self::assertInstanceOf(\ArrayObject::class, $definitions['Node']['properties']['slug']);
        self::assertSame(self::SLUG, $definitions['Node']['properties']['slug']->getArrayCopy());
    }

    /**
     * Builds the definitions through the decorator and returns them as plain
     * arrays, so assertions can compare with assertSame.
     *
     * @param array<string, mixed> $definitions
     *
     * @return array<string, mixed>
     */
    private function build(array $definitions, bool $plain = false): array
    {
        $schema = (new InvalidDefaultSchemaDecorator($this->innerFactory($definitions, $plain)))->buildSchema('Node');

        return self::toArray($schema->getDefinitions());
    }

    /**
     * A stub of the inner factory that behaves like the real one: it writes
     * its definitions into the passed `$schema` (a new one when none is
     * passed) and returns that schema.
     *
     * @param array<string, mixed> $definitions
     */
    private function innerFactory(array $definitions, bool $plain = false): SchemaFactoryInterface
    {
        $shaped = [];
        foreach ($definitions as $name => $definition) {
            $shaped[$name] = $plain ? $definition : self::toApiShape($definition);
        }

        return new class($shaped) implements SchemaFactoryInterface {
            /**
             * @param array<string, mixed> $definitions
             */
            public function __construct(private readonly array $definitions)
            {
            }

            public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
            {
                $schema ??= new Schema();
                $definitions = $schema->getDefinitions();
                foreach ($this->definitions as $name => $definition) {
                    $definitions[$name] = $definition;
                }

                return $schema;
            }
        };
    }

    /**
     * Converts a schema node into the shape API Platform's SchemaFactory
     * produces: the node is an ArrayObject, `properties` is a plain array of
     * ArrayObjects, and `allOf`/`anyOf`/`oneOf` are plain arrays whose
     * `$ref`-only members stay plain arrays (as in the Hydra wrapper).
     *
     * @param array<string, mixed> $node
     */
    private static function toApiShape(array $node): \ArrayObject
    {
        if (isset($node['properties']) && \is_array($node['properties'])) {
            foreach ($node['properties'] as $name => $property) {
                $node['properties'][$name] = \is_array($property) ? self::toApiShape($property) : $property;
            }
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            if (isset($node[$keyword]) && \is_array($node[$keyword])) {
                foreach ($node[$keyword] as $index => $branch) {
                    if (\is_array($branch) && !(1 === \count($branch) && isset($branch['$ref']))) {
                        $node[$keyword][$index] = self::toApiShape($branch);
                    }
                }
            }
        }

        return new \ArrayObject($node);
    }

    private static function toArray(mixed $node): mixed
    {
        if ($node instanceof \ArrayObject) {
            $node = $node->getArrayCopy();
        }
        if (!\is_array($node)) {
            return $node;
        }

        return array_map(self::toArray(...), $node);
    }
}
