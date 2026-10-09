<?php
// file generated with AI assistance: Claude Code - 2026-10-08 13:05:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\ApiPlatformUtils\OpenApi\RelationFieldSchemaDecorator;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\Author;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\Book;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\MetadataLoader;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\Publisher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RelationFieldSchemaDecoratorTest extends TestCase
{
    private const RELATION = ['type' => 'string', 'format' => 'iri-reference'];

    private const AUTHOR_EXTENSIONS = [
        'x-collection' => '/api/authors',
        'x-label-property' => 'name',
        'x-value-property' => '@id',
        'x-search-property' => 'name',
        'x-resource-class' => 'Author',
    ];

    public function testOutputSchemaGetsAllExtensionsInsideAllOf(): void
    {
        $schema = $this->build(Schema::TYPE_OUTPUT, 'Book.jsonld');
        $properties = $schema->getDefinitions()['Book.jsonld']['allOf'][1]['properties'];

        self::assertSame(self::RELATION + self::AUTHOR_EXTENSIONS, $properties['author']);
        // output keeps the "first string property" fallback like input
        self::assertSame('code', $properties['publisher']['x-label-property']);
    }

    public function testOutputSchemaDecoratesPlainDefinitionsToo(): void
    {
        $schema = $this->build(Schema::TYPE_OUTPUT, 'Book.jsonld');

        self::assertSame(self::RELATION + self::AUTHOR_EXTENSIONS, $schema->getDefinitions()['Book']['properties']['author']);
    }

    public function testNonRelationIriReferencesAreLeftAlone(): void
    {
        $schema = $this->build(Schema::TYPE_OUTPUT, 'Book.jsonld');

        // `@id` of the Hydra base schema is no Doctrine association
        self::assertSame(
            ['type' => 'string', 'format' => 'iri-reference'],
            $schema->getDefinitions()['HydraItemBaseSchema']['properties']['@id'],
        );
        self::assertSame(['$ref' => '#/definitions/HydraItemBaseSchema'], $schema->getDefinitions()['Book.jsonld']['allOf'][0]);
    }

    public function testInputSchemaKeepsAllExtensionsAndFallback(): void
    {
        $schema = $this->build(Schema::TYPE_INPUT, 'Book');
        $properties = $schema->getDefinitions()['Book']['properties'];

        self::assertSame(self::RELATION + self::AUTHOR_EXTENSIONS, $properties['author']);
        // input keeps the "first string property" fallback
        self::assertSame('code', $properties['publisher']['x-label-property']);
        self::assertSame('/api/publishers', $properties['publisher']['x-collection']);
    }

    private function build(string $type, string $rootKey, ?Operation $operation = null): Schema
    {
        $definitions = new \ArrayObject([
            'Book' => ['type' => 'object', 'properties' => [
                'title' => ['type' => 'string'],
                'author' => self::RELATION,
                'publisher' => self::RELATION,
            ]],
            'Book.jsonld' => ['allOf' => [
                ['$ref' => '#/definitions/HydraItemBaseSchema'],
                ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string'],
                    'author' => self::RELATION,
                    'publisher' => self::RELATION,
                ]],
            ]],
            'HydraItemBaseSchema' => ['type' => 'object', 'properties' => ['@id' => ['type' => 'string', 'format' => 'iri-reference']]],
        ]);

        $inner = $this->createStub(SchemaFactoryInterface::class);
        $inner->method('buildSchema')->willReturnCallback(static function () use ($definitions, $rootKey): Schema {
            $schema = new Schema();
            $schema->setDefinitions($definitions);
            $schema['$ref'] = '#/definitions/'.$rootKey;

            return $schema;
        });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnCallback(static function (string $class) {
            if (!\in_array($class, [Book::class, Author::class, Publisher::class], true)) {
                throw new \RuntimeException('not an entity');
            }

            return MetadataLoader::load($class);
        });

        $resources = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resources->method('create')->willReturnCallback(static function (string $class): ResourceMetadataCollection {
            $path = '/'.strtolower((new \ReflectionClass($class))->getShortName()).'s';

            return new ResourceMetadataCollection($class, [
                (new ApiResource(class: $class))->withOperations(new Operations([
                    'get_collection' => new GetCollection(uriTemplate: $path, class: $class),
                ])),
            ]);
        });

        $decorator = new RelationFieldSchemaDecorator(
            $inner,
            $entityManager,
            $resources,
            new NullLogger(),
            '/api',
            ['name', 'title', 'label', 'displayName', 'email'],
        );

        return $decorator->buildSchema(Book::class, 'jsonld', $type, $operation);
    }
}
// - revised 2026-10-09 (output schemas get all extensions as in 0.4.1, also inside allOf)
