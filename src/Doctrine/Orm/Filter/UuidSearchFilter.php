<?php
// file generated with AI assistance: Claude Code - 2026-06-07

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface as OrmFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\AbstractUid;

/**
 * Decorator around {@see SearchFilter} that fixes IRI-based association
 * filtering on Symfony Uid identifier columns.
 *
 * The stock SearchFilter passes the resolved identifier (a `Uid\Uuid` object)
 * to `QueryBuilder::setParameter()` without a type hint. Doctrine ORM has no
 * built-in inferer for `AbstractUid`, so the value is bound as a string via
 * its `__toString()` — the canonical 36-character form with dashes. MySQL
 * then compares the `BINARY(16)` column to that string and silently returns
 * 0 rows (no SQL error).
 *
 * This decorator delegates `apply()` to the wrapped SearchFilter and then
 * walks the resulting QueryBuilder parameters. Any parameter whose value is
 * an `AbstractUid` (or array of `AbstractUid`) with the default string type
 * is re-bound with the explicit `uuid` Doctrine type so the registered
 * UuidType converts the value to the correct binary representation.
 *
 * Wired in transparently via {@see \Dmstr\ApiPlatformUtils\DependencyInjection\Compiler\UuidSearchFilterCompilerPass}.
 */
final class UuidSearchFilter implements OrmFilterInterface
{
    public function __construct(
        private readonly SearchFilter $inner,
    ) {
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->inner->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);

        foreach ($queryBuilder->getParameters() as $param) {
            $value = $param->getValue();
            $type = $param->getType();

            if ($value instanceof AbstractUid && (null === $type || ParameterType::STRING === $type)) {
                $queryBuilder->setParameter($param->getName(), $value, 'uuid');
                continue;
            }

            if (\is_array($value) && [] !== $value && reset($value) instanceof AbstractUid
                && (null === $type || ArrayParameterType::STRING === $type)
            ) {
                $binary = array_map(
                    static fn (mixed $v): mixed => $v instanceof AbstractUid ? $v->toBinary() : $v,
                    $value,
                );
                $queryBuilder->setParameter($param->getName(), $binary, ArrayParameterType::BINARY);
            }
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return $this->inner->getDescription($resourceClass);
    }
}
