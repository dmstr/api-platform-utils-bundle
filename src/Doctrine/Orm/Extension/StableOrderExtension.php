<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:20:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;

/**
 * Tie-breaker for stable pagination: appends `ORDER BY <root>.<identifier>
 * ASC` to collection queries whose ORDER BY does not contain the identifier
 * yet.
 *
 * Sorting by a non-unique column (status, a name with duplicates, a date)
 * leaves the order of equal rows to the database, which may differ between
 * two page requests, so rows can show up twice or never while paging. The
 * identifier as the last sort key makes the order total.
 *
 * Runs after API Platform's sort (filters/parameters at -16, OrderExtension
 * at -32) and before pagination (-64): tagged with priority -40.
 *
 * Skipped: entities with a composite or foreign identifier (like
 * OrderExtension does) and queries with GROUP BY, where an extra ORDER BY
 * column may not be valid SQL.
 */
final class StableOrderExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, ?string $resourceClass = null, ?Operation $operation = null, array $context = []): void
    {
        $rootEntities = $queryBuilder->getRootEntities();
        $rootAliases = $queryBuilder->getRootAliases();
        if (!isset($rootEntities[0], $rootAliases[0])) {
            return;
        }

        if ([] !== ($queryBuilder->getDQLPart('groupBy') ?? [])) {
            return;
        }

        $metadata = $queryBuilder->getEntityManager()->getClassMetadata($rootEntities[0]);
        if ($metadata->containsForeignIdentifier || $metadata->isIdentifierComposite) {
            return;
        }

        $identifiers = $metadata->getIdentifierFieldNames();
        if (1 !== \count($identifiers)) {
            return;
        }

        $field = $rootAliases[0].'.'.$identifiers[0];
        if ($this->isOrderedBy($queryBuilder, $field)) {
            return;
        }

        $queryBuilder->addOrderBy($field, 'ASC');
    }

    private function isOrderedBy(QueryBuilder $queryBuilder, string $field): bool
    {
        foreach ($queryBuilder->getDQLPart('orderBy') ?? [] as $orderBy) {
            $parts = $orderBy instanceof OrderBy ? $orderBy->getParts() : [(string) $orderBy];
            foreach ($parts as $part) {
                // a part is "<expression> <direction>", e.g. "o.id ASC"
                if (strtok(trim((string) $part), " \t") === $field) {
                    return true;
                }
            }
        }

        return false;
    }
}
