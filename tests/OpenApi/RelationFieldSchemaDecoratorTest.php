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

    public function testOutputSchemaGetsOnlyLabelAndResourceClass(): void
    {
        $schema = $this->build(Schema::TYPE_OUTPUT, 'Book.jsonld');
        $properties = $schema->getDefinitions()['Book.jsonld']['allOf'][1]['properties'];

        self::assertSame(
            self::RELATION + ['x-label-property' => 'name', 'x-resource-class' => 'Author'],
            $properties['author'],
        );
        self::assertArrayNotHasKey('x-collection', $properties['author']);
        self::assertArrayNotHasKey('x-value-property', $properties['author']);
        self::assertArrayNotHasKey('x-search-property', $properties['author']);
    }

    public function testOutputSchemaHasNoStringFallbackLabel(): void
    {
        $schema = $this->build(Schema::TYPE_OUTPUT, 'Book.jsonld');

        // Publisher has no label candidate, only `code`
        self::assertSame(self::RELATION, $schema->getDefinitions()['Book.jsonld']['allOf'][1]['properties']['publisher']);
    }

    public function testOutputSchemaLeavesOtherDefinitionsAlone(): void
    {
        $schema = $this->build(Schema::TYPE_OUTPUT, 'Book.jsonld');

        // the input definition sharing the definitions map is not touched
        self::assertSame(self::RELATION, $schema->getDefinitions()['Book']['properties']['author']);
    }

    public function testInputSchemaKeepsAllExtensionsAndFallback(): void
    {
        $schema = $this->build(Schema::TYPE_INPUT, 'Book');
        $properties = $schema->getDefinitions()['Book']['properties'];

        self::assertSame([
            'type' => 'string',
            'format' => 'iri-reference',
            'x-collection' => '/api/authors',
            'x-label-property' => 'name',
            'x-value-property' => '@id',
            'x-search-property' => 'name',
            'x-resource-class' => 'Author',
        ], $properties['author']);
        // input keeps the "first string property" fallback
        self::assertSame('code', $properties['publisher']['x-label-property']);
        self::assertSame('/api/publishers', $properties['publisher']['x-collection']);
    }

    public function testOutputClassOfTheOperationIsUsed(): void
    {
        // an operation whose output is not a Doctrine entity: nothing added
        $schema = $this->build(Schema::TYPE_OUTPUT, 'Book.jsonld', new GetCollection(class: Book::class, output: \stdClass::class));

        self::assertSame(self::RELATION, $schema->getDefinitions()['Book.jsonld']['allOf'][1]['properties']['author']);
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
