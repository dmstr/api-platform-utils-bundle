<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:55:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Metadata;

use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\ApiPlatformUtils\Metadata\AutoOrderResourceMetadataCollectionFactory;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\Author;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\AuthorView;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\Book;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\DisabledBook;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\ExcludedBook;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\ExplicitOrderBook;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\MetadataLoader;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\OrderAllBook;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\Publisher;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

final class AutoOrderResourceMetadataCollectionFactoryTest extends TestCase
{
    private const DEFAULT_ORDER = ['name' => 'ASC', 'createdAt' => 'DESC'];

    public function testTypeWhitelistAndIdentifierExclusion(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: Book::class)));

        // integer id, text, json, vector: no parameter
        self::assertSame(
            ['order[title]', 'order[price]', 'order[available]', 'order[secret]', 'order[createdAt]', 'order[author.name]'],
            $this->keys($operation),
        );
    }

    public function testParameterShape(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: Book::class)));

        $parameter = $operation->getParameters()->get('order[author.name]');
        self::assertInstanceOf(QueryParameter::class, $parameter);
        self::assertSame('author.name', $parameter->getProperty());
        self::assertInstanceOf(SortFilter::class, $parameter->getFilter());
        self::assertFalse($parameter->getCastToArray());
        self::assertSame('Sort by `name` of the related `author` (asc or desc).', $parameter->getDescription());
        self::assertSame('Sort by `title` (asc or desc).', $operation->getParameters()->get('order[title]')->getDescription());
    }

    public function testRelationLabelOnlyFromCandidates(): void
    {
        // Publisher has only a string `code`: no "first string property" fallback
        $keys = $this->keys($this->collectionOperation($this->create(new GetCollection(class: Book::class))));
        self::assertNotContains('order[publisher.code]', $keys);
        self::assertEmpty(array_filter($keys, static fn (string $k): bool => str_starts_with($k, 'order[publisher')));

        // candidate order decides: `email` only when `name` is not a candidate
        $keys = $this->keys($this->collectionOperation($this->create(new GetCollection(class: Book::class), candidates: ['email', 'name'])));
        self::assertContains('order[author.email]', $keys);
        self::assertNotContains('order[author.name]', $keys);
    }

    public function testUnreadableAndUnexposedPropertiesAreSkipped(): void
    {
        $operation = $this->collectionOperation($this->create(
            new GetCollection(class: Book::class),
            hidden: ['secret'],
            unreadable: ['available', 'author'],
        ));

        $keys = $this->keys($operation);
        self::assertNotContains('order[secret]', $keys);
        self::assertNotContains('order[available]', $keys);
        self::assertNotContains('order[author.name]', $keys);
        self::assertContains('order[title]', $keys);
    }

    public function testNormalizationGroupsArePassedToThePropertyFactories(): void
    {
        $operation = $this->collectionOperation($this->create(
            new GetCollection(class: Book::class, normalizationContext: ['groups' => ['book:list']]),
            grouped: ['title', 'author'],
        ));

        self::assertSame(['order[title]', 'order[author.name]'], $this->keys($operation));
    }

    public function testExplicitOrderFilterWinsPerProperty(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: ExplicitOrderBook::class)));

        // title (class level) and author.name (property level) are explicit
        self::assertSame(['order[pages]'], $this->keys($operation));
    }

    public function testClassLevelOrderFilterWithoutPropertiesDisablesParameters(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: OrderAllBook::class)));

        self::assertSame([], $this->keys($operation));
        // the default sort still applies: `name` is sortable
        self::assertSame(['name' => 'ASC'], $operation->getOrder());
    }

    public function testDeclaredParameterWins(): void
    {
        $own = new QueryParameter(key: 'order[title]', description: 'mine');
        $operation = $this->collectionOperation($this->create(
            new GetCollection(class: Book::class, parameters: new Parameters(['order[title]' => $own])),
        ));

        self::assertSame('mine', $operation->getParameters()->get('order[title]')->getDescription());
        self::assertCount(1, array_filter($this->keys($operation), static fn (string $k): bool => 'order[title]' === $k));
    }

    public function testAutoOrderAttributeDisables(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: DisabledBook::class)));

        self::assertSame([], $this->keys($operation));
        self::assertNull($operation->getOrder());
    }

    public function testAutoOrderAttributeExcludes(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: ExcludedBook::class)));

        // `name` and `author` (incl. author.name) excluded
        self::assertSame(['order[pages]', 'order[createdAt]'], $this->keys($operation));
        // excluded `name` is no default sort candidate either
        self::assertSame(['createdAt' => 'DESC'], $operation->getOrder());
    }

    public function testDefaultOrderFirstSortableKeyWins(): void
    {
        $author = $this->collectionOperation($this->create(new GetCollection(class: Author::class), resourceClass: Author::class));
        self::assertSame(['name' => 'ASC'], $author->getOrder());

        $book = $this->collectionOperation($this->create(new GetCollection(class: Book::class)));
        self::assertSame(['createdAt' => 'DESC'], $book->getOrder());

        // neither key exists: API Platform's default (identifier) stays
        $publisher = $this->collectionOperation($this->create(new GetCollection(class: Publisher::class), resourceClass: Publisher::class));
        self::assertNull($publisher->getOrder());
    }

    public function testDefaultOrderKeepsOperationOrder(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: Author::class, order: ['email' => 'ASC']), resourceClass: Author::class));

        self::assertSame(['email' => 'ASC'], $operation->getOrder());
    }

    public function testDefaultOrderCanBeEmpty(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: Author::class), resourceClass: Author::class, defaultOrder: []));

        self::assertNull($operation->getOrder());
        self::assertContains('order[name]', $this->keys($operation));
    }

    public function testOperationsWithOwnProviderAndItemOperationsAreUntouched(): void
    {
        $collection = $this->create(new GetCollection(class: Book::class, provider: 'some.provider'));

        foreach ($collection[0]->getOperations() as $operation) {
            self::assertSame([], $this->keys($operation));
            self::assertNull($operation->getOrder());
        }
    }

    public function testStateOptionsEntityClass(): void
    {
        $operation = $this->collectionOperation($this->create(
            new GetCollection(class: AuthorView::class, stateOptions: new Options(entityClass: Author::class)),
            resourceClass: AuthorView::class,
        ));

        // Author's fields, filtered by what the DTO exposes (no email)
        self::assertSame(['order[name]', 'order[createdAt]'], $this->keys($operation));
    }

    public function testNonDoctrineResourceIsUntouched(): void
    {
        $operation = $this->collectionOperation($this->create(new GetCollection(class: AuthorView::class), resourceClass: AuthorView::class));

        self::assertSame([], $this->keys($operation));
        self::assertNull($operation->getOrder());
    }

    /**
     * @param list<string>      $hidden     properties missing from the name collection
     * @param list<string>      $unreadable properties with readable: false
     * @param list<string>|null $grouped    properties in the operation's groups
     * @param list<string>      $candidates
     * @param array<string, string> $defaultOrder
     */
    private function create(
        GetCollection $collectionOperation,
        string $resourceClass = Book::class,
        array $hidden = [],
        array $unreadable = [],
        ?array $grouped = null,
        array $candidates = ['name', 'title', 'label', 'displayName', 'email'],
        array $defaultOrder = self::DEFAULT_ORDER,
    ): ResourceMetadataCollection {
        $resource = (new ApiResource(class: $resourceClass))->withOperations(new Operations([
            'get_collection' => $collectionOperation,
            'get' => new Get(class: $resourceClass),
        ]));

        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturnCallback(
            static fn (string $class): ResourceMetadataCollection => new ResourceMetadataCollection($class, [$resource])
        );

        $factory = new AutoOrderResourceMetadataCollectionFactory(
            $decorated,
            $this->managerRegistry(),
            $this->nameFactory($hidden),
            $this->propertyFactory($unreadable, $grouped),
            $candidates,
            $defaultOrder,
        );

        return $factory->create($resourceClass);
    }

    private function collectionOperation(ResourceMetadataCollection $collection): GetCollection
    {
        foreach ($collection[0]->getOperations() as $operation) {
            if ($operation instanceof GetCollection) {
                return $operation;
            }
        }
        self::fail('no GetCollection');
    }

    /**
     * @return list<string>
     */
    private function keys(object $operation): array
    {
        $keys = [];
        foreach ($operation->getParameters() ?? [] as $key => $parameter) {
            $keys[] = $key;
        }

        return $keys;
    }

    private function managerRegistry(): ManagerRegistry
    {
        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturnCallback(MetadataLoader::load(...));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(
            static fn (string $class): ?ObjectManager => [] !== (new \ReflectionClass($class))->getAttributes(Entity::class) ? $manager : null
        );

        return $registry;
    }

    /**
     * @param list<string> $hidden
     */
    private function nameFactory(array $hidden): PropertyNameCollectionFactoryInterface
    {
        return new class($hidden) implements PropertyNameCollectionFactoryInterface {
            public function __construct(private readonly array $hidden)
            {
            }

            public function create(string $resourceClass, array $options = []): PropertyNameCollection
            {
                $names = [];
                foreach ((new \ReflectionClass($resourceClass))->getProperties() as $property) {
                    if (!\in_array($property->getName(), $this->hidden, true)) {
                        $names[] = $property->getName();
                    }
                }

                return new PropertyNameCollection($names);
            }
        };
    }

    /**
     * @param list<string>      $unreadable
     * @param list<string>|null $grouped
     */
    private function propertyFactory(array $unreadable, ?array $grouped): PropertyMetadataFactoryInterface
    {
        return new class($unreadable, $grouped) implements PropertyMetadataFactoryInterface {
            public function __construct(private readonly array $unreadable, private readonly ?array $grouped)
            {
            }

            public function create(string $resourceClass, string $property, array $options = []): ApiProperty
            {
                if (\in_array($property, $this->unreadable, true)) {
                    return new ApiProperty(readable: false);
                }
                if (isset($options['serializer_groups'])) {
                    return new ApiProperty(readable: \in_array($property, $this->grouped ?? [], true));
                }

                return new ApiProperty(readable: true);
            }
        };
    }
}
