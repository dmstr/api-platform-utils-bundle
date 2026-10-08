<?php
// file generated with AI assistance: Claude Code - 2026-10-08 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Dmstr\ApiPlatformUtils\Doctrine\Orm\Extension\StableOrderExtension;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\Book;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\CompositeKey;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder\MetadataLoader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class StableOrderExtensionTest extends TestCase
{
    public function testAppendsIdentifierAfterTheSort(): void
    {
        $queryBuilder = $this->queryBuilder(Book::class)->addOrderBy('o.title', 'DESC');

        $this->apply($queryBuilder);

        self::assertSame('SELECT o FROM '.Book::class.' o ORDER BY o.title DESC, o.id ASC', $queryBuilder->getDQL());
    }

    public function testAppendsIdentifierWithoutAnySort(): void
    {
        $queryBuilder = $this->queryBuilder(Book::class);

        $this->apply($queryBuilder);

        self::assertSame('SELECT o FROM '.Book::class.' o ORDER BY o.id ASC', $queryBuilder->getDQL());
    }

    public function testKeepsAnExistingIdentifierSort(): void
    {
        $queryBuilder = $this->queryBuilder(Book::class)->addOrderBy('o.title', 'ASC')->addOrderBy('o.id', 'DESC');

        $this->apply($queryBuilder);

        self::assertSame('SELECT o FROM '.Book::class.' o ORDER BY o.title ASC, o.id DESC', $queryBuilder->getDQL());
    }

    public function testJoinedColumnWithIdentifierNameIsNotTheRootIdentifier(): void
    {
        $queryBuilder = $this->queryBuilder(Book::class)
            ->leftJoin('o.author', 'author_a1')
            ->addOrderBy('author_a1.id', 'ASC');

        $this->apply($queryBuilder);

        self::assertStringEndsWith('ORDER BY author_a1.id ASC, o.id ASC', $queryBuilder->getDQL());
    }

    public function testSkipsCompositeIdentifier(): void
    {
        $queryBuilder = $this->queryBuilder(CompositeKey::class)->addOrderBy('o.name', 'ASC');

        $this->apply($queryBuilder);

        self::assertSame('SELECT o FROM '.CompositeKey::class.' o ORDER BY o.name ASC', $queryBuilder->getDQL());
    }

    public function testSkipsForeignIdentifier(): void
    {
        $metadata = clone MetadataLoader::load(Book::class);
        $metadata->containsForeignIdentifier = true;
        $queryBuilder = $this->queryBuilder(Book::class, $metadata)->addOrderBy('o.title', 'ASC');

        $this->apply($queryBuilder);

        self::assertSame('SELECT o FROM '.Book::class.' o ORDER BY o.title ASC', $queryBuilder->getDQL());
    }

    public function testSkipsGroupBy(): void
    {
        $queryBuilder = $this->queryBuilder(Book::class)->groupBy('o.title')->addOrderBy('o.title', 'ASC');

        $this->apply($queryBuilder);

        self::assertStringEndsWith('GROUP BY o.title ORDER BY o.title ASC', $queryBuilder->getDQL());
    }

    private function apply(QueryBuilder $queryBuilder): void
    {
        (new StableOrderExtension())->applyToCollection(
            $queryBuilder,
            $this->createStub(QueryNameGeneratorInterface::class),
            Book::class,
        );
    }

    /**
     * @param class-string $class
     */
    private function queryBuilder(string $class, ?ClassMetadata $metadata = null): QueryBuilder
    {
        $metadata ??= MetadataLoader::load($class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        return (new QueryBuilder($entityManager))->select('o')->from($class, 'o');
    }
}
