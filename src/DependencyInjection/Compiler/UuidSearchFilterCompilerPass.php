<?php
// file generated with AI assistance: Claude Code - 2026-06-07

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\DependencyInjection\Compiler;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Dmstr\ApiPlatformUtils\Doctrine\Orm\Filter\UuidSearchFilter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * For every API Platform SearchFilter service produced by an
 * `#[ApiFilter(SearchFilter::class, …)]` attribute, register a
 * {@see UuidSearchFilter} decorator that fixes IRI binding on Symfony Uid
 * identifier columns.
 *
 * Detection covers both direct {@see Definition} instances (class set to
 * `SearchFilter`) and {@see ChildDefinition} instances inheriting from the
 * abstract `api_platform.doctrine.orm.search_filter`.
 *
 * Must run AFTER API Platform's `AttributeFilterPass`
 * (BEFORE_OPTIMIZATION, priority 101); the default `addCompilerPass()`
 * priority (0) satisfies this.
 */
final class UuidSearchFilterCompilerPass implements CompilerPassInterface
{
    private const ABSTRACT_PARENT_IDS = [
        'api_platform.doctrine.orm.search_filter',
        SearchFilter::class, // ApiPlatform AttributeFilterPass passes the FQCN as parent (resolves via container alias)
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$this->isSearchFilter($definition, $container)) {
                continue;
            }
            if ($definition->isAbstract()) {
                continue;
            }

            $decoratorId = $id.'.uuid_decorator';
            if ($container->hasDefinition($decoratorId)) {
                continue;
            }

            $decorator = (new Definition(UuidSearchFilter::class))
                ->setArguments([new Reference($decoratorId.'.inner')])
                ->setDecoratedService($id)
                ->setPublic(false);

            $container->setDefinition($decoratorId, $decorator);
        }
    }

    private function isSearchFilter(Definition $definition, ContainerBuilder $container): bool
    {
        if (SearchFilter::class === $definition->getClass()) {
            return true;
        }

        if ($definition instanceof ChildDefinition && \in_array($definition->getParent(), self::ABSTRACT_PARENT_IDS, true)) {
            return true;
        }

        return false;
    }
}
