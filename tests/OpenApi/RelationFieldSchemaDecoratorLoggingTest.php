<?php
// file generated with AI assistance: Claude Code - 2026-11-26 16:23:15 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
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
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Regression test: building schemas must not write debug log records. The
 * decorator runs for every schema of every resource, so per-property debug
 * output floods the log. Each test walks one logging path of the decorator
 * and also pins the produced schema, so removing the logging cannot change
 * the output.
 */
final class RelationFieldSchemaDecoratorLoggingTest extends TestCase
{
    private const RELATION = ['type' => 'string', 'format' => 'iri-reference'];

    private const TITLE = ['type' => 'string'];

    private const AUTHOR_EXTENSIONS = [
        'x-collection' => '/api/authors',
        'x-label-property' => 'name',
        'x-value-property' => '@id',
        'x-search-property' => 'name',
        'x-resource-class' => 'Author',
    ];

    private const PUBLISHER_EXTENSIONS = [
        'x-collection' => '/api/publishers',
        'x-label-property' => 'code',
        'x-value-property' => '@id',
        'x-search-property' => 'code',
        'x-resource-class' => 'Publisher',
    ];

    /** How the resource metadata factory answers for a relation target. */
    private const COLLECTION = 'collection';
    private const NOT_A_RESOURCE = 'not-a-resource';
    private const NO_OPERATIONS = 'no-operations';
    private const NO_COLLECTION = 'no-collection';

    private AbstractLogger $logger;

    /**
     * Output schema: a plain definition with properties, an allOf definition
     * without own properties, and the Hydra base schema whose `@id` is an
     * iri-reference without an association.
     */
    public function testOutputSchemaWithPlainAndAllOfDefinitionsWritesNoDebugRecords(): void
    {
        $schema = $this->build(Book::class, Schema::TYPE_OUTPUT, $this->jsonLdSchema(...));
        $definitions = $schema->getDefinitions();

        $expectedProperties = [
            'title' => self::TITLE,
            'author' => self::RELATION + self::AUTHOR_EXTENSIONS,
            'publisher' => self::RELATION + self::PUBLISHER_EXTENSIONS,
        ];
        self::assertSame(['type' => 'object', 'properties' => $expectedProperties], $definitions['Book']);
        self::assertSame([
            'allOf' => [
                ['$ref' => '#/definitions/HydraItemBaseSchema'],
                ['type' => 'object', 'properties' => $expectedProperties],
            ],
            // own label of the root definition
            'x-label-property' => 'title',
        ], $definitions['Book.jsonld']);
        self::assertSame(
            ['type' => 'object', 'properties' => ['@id' => self::RELATION]],
            $definitions['HydraItemBaseSchema'],
        );
        // the root-level pass always writes `properties` back, empty here
        self::assertSame([], $schema['properties']);

        $this->assertNoDebugRecords();
    }

    /**
     * Input schema with root-level `properties` (one relation, one plain
     * field) next to a plain definition.
     */
    public function testInputSchemaWithRootPropertiesWritesNoDebugRecords(): void
    {
        $schema = $this->build(Book::class, Schema::TYPE_INPUT, static function (): Schema {
            $schema = new Schema();
            $schema->setDefinitions(new \ArrayObject([
                'Book' => ['type' => 'object', 'properties' => [
                    'title' => self::TITLE,
                    'author' => self::RELATION,
                ]],
            ]));
            $schema['$ref'] = '#/definitions/Book';
            $schema['properties'] = [
                'title' => self::TITLE,
                'publisher' => self::RELATION,
            ];

            return $schema;
        });

        self::assertSame([
            'title' => self::TITLE,
            'publisher' => self::RELATION + self::PUBLISHER_EXTENSIONS,
        ], $schema['properties']);
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'title' => self::TITLE,
                'author' => self::RELATION + self::AUTHOR_EXTENSIONS,
            ],
            'x-label-property' => 'title',
        ], $schema->getDefinitions()['Book']);

        $this->assertNoDebugRecords();
    }

    /** Root-level iri-reference that is no association (`@id`). */
    public function testRootIriReferenceWithoutAssociationWritesNoDebugRecords(): void
    {
        $schema = $this->build(Book::class, Schema::TYPE_INPUT, static function (): Schema {
            $schema = new Schema();
            $schema['properties'] = ['@id' => self::RELATION, 'title' => self::TITLE];

            return $schema;
        });

        self::assertSame(['@id' => self::RELATION, 'title' => self::TITLE], $schema['properties']);
        // no root definition key: no own label is added
        self::assertSame([], $schema->getDefinitions()->getArrayCopy());

        $this->assertNoDebugRecords();
    }

    public function testClassWithoutDoctrineMetadataWritesNoDebugRecords(): void
    {
        $schema = $this->build('App\\NotAnEntity', Schema::TYPE_OUTPUT, $this->jsonLdSchema(...));

        // left exactly as the decorated factory built it, no root `properties` added
        self::assertEquals($this->jsonLdSchema()->getArrayCopy(), $schema->getArrayCopy());
        self::assertArrayNotHasKey('properties', $schema->getArrayCopy());

        $this->assertNoDebugRecords();
    }

    /**
     * Relation targets without a collection endpoint: `Author` is no API
     * resource, `Publisher` is a resource whose operations are null.
     */
    public function testTargetsWithoutResourceOrOperationsWriteNoDebugRecords(): void
    {
        $schema = $this->build(
            Book::class,
            Schema::TYPE_INPUT,
            $this->plainSchema(...),
            [Author::class => self::NOT_A_RESOURCE, Publisher::class => self::NO_OPERATIONS],
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'title' => self::TITLE,
                'author' => self::RELATION,
                'publisher' => self::RELATION,
            ],
            'x-label-property' => 'title',
        ], $schema->getDefinitions()['Book']);

        $this->assertNoDebugRecords();
    }

    /** `Author` is a resource with operations, but none is a GetCollection. */
    public function testTargetWithoutGetCollectionWritesNoDebugRecords(): void
    {
        $schema = $this->build(
            Book::class,
            Schema::TYPE_INPUT,
            $this->plainSchema(...),
            [Author::class => self::NO_COLLECTION],
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'title' => self::TITLE,
                'author' => self::RELATION,
                'publisher' => self::RELATION + self::PUBLISHER_EXTENSIONS,
            ],
            'x-label-property' => 'title',
        ], $schema->getDefinitions()['Book']);

        $this->assertNoDebugRecords();
    }

    private function jsonLdSchema(): Schema
    {
        $schema = new Schema();
        $schema->setDefinitions(new \ArrayObject([
            'Book' => ['type' => 'object', 'properties' => [
                'title' => self::TITLE,
                'author' => self::RELATION,
                'publisher' => self::RELATION,
            ]],
            'Book.jsonld' => ['allOf' => [
                ['$ref' => '#/definitions/HydraItemBaseSchema'],
                ['type' => 'object', 'properties' => [
                    'title' => self::TITLE,
                    'author' => self::RELATION,
                    'publisher' => self::RELATION,
                ]],
            ]],
            'HydraItemBaseSchema' => ['type' => 'object', 'properties' => ['@id' => self::RELATION]],
        ]));
        $schema['$ref'] = '#/definitions/Book.jsonld';

        return $schema;
    }

    private function plainSchema(): Schema
    {
        $schema = new Schema();
        $schema->setDefinitions(new \ArrayObject([
            'Book' => ['type' => 'object', 'properties' => [
                'title' => self::TITLE,
                'author' => self::RELATION,
                'publisher' => self::RELATION,
            ]],
        ]));
        $schema['$ref'] = '#/definitions/Book';

        return $schema;
    }

    /**
     * @param \Closure(): Schema    $innerSchema what the decorated factory returns
     * @param array<string, string> $targets     resource factory answer per target class, default COLLECTION
     */
    private function build(string $className, string $type, \Closure $innerSchema, array $targets = []): Schema
    {
        $inner = $this->createStub(SchemaFactoryInterface::class);
        $inner->method('buildSchema')->willReturnCallback(static fn (): Schema => $innerSchema());

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnCallback(static function (string $class) {
            if (!\in_array($class, [Book::class, Author::class, Publisher::class], true)) {
                throw new \RuntimeException('not an entity');
            }

            return MetadataLoader::load($class);
        });

        $resources = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resources->method('create')->willReturnCallback(static function (string $class) use ($targets): ResourceMetadataCollection {
            $path = '/'.strtolower((new \ReflectionClass($class))->getShortName()).'s';

            return match ($targets[$class] ?? self::COLLECTION) {
                self::NOT_A_RESOURCE => throw new ResourceClassNotFoundException(\sprintf('Resource "%s" not found.', $class)),
                self::NO_OPERATIONS => new ResourceMetadataCollection($class, [new ApiResource(class: $class)]),
                self::NO_COLLECTION => new ResourceMetadataCollection($class, [
                    (new ApiResource(class: $class))->withOperations(new Operations([
                        'get' => new Get(uriTemplate: $path.'/{id}', class: $class),
                    ])),
                ]),
                self::COLLECTION => new ResourceMetadataCollection($class, [
                    (new ApiResource(class: $class))->withOperations(new Operations([
                        'get_collection' => new GetCollection(uriTemplate: $path, class: $class),
                    ])),
                ]),
            };
        });

        $this->logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $decorator = new RelationFieldSchemaDecorator(
            $inner,
            $entityManager,
            $resources,
            $this->logger,
            '/api',
            ['name', 'title', 'label', 'displayName', 'email'],
        );

        return $decorator->buildSchema($className, 'jsonld', $type);
    }

    private function assertNoDebugRecords(): void
    {
        $debugMessages = array_column(
            array_filter($this->logger->records, static fn (array $record): bool => $record['level'] === LogLevel::DEBUG),
            'message',
        );

        self::assertSame([], array_values($debugMessages), 'Schema building must not write debug log records.');
    }
}
