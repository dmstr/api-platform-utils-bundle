<?php
// file generated with AI assistance: Claude Code - 2025-11-22, revised 2026-10-08 12:25:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for API Platform Utils Bundle
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dmstr_api_platform_utils');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                // Credential Encryption
                ->arrayNode('credential_encryption')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable credential encryption service')
                        ->end()
                        ->scalarNode('key')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('Base64-encoded encryption key (use sodium_crypto_secretbox_keygen())')
                        ->end()
                    ->end()
                ->end()

                // Relation Field Schema Decorator
                ->arrayNode('relation_field_decorator')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable relation field schema decorator for OpenAPI')
                        ->end()
                        ->scalarNode('api_prefix')
                            ->defaultValue('/api')
                            ->info('API prefix for collection paths')
                        ->end()
                        ->integerNode('decoration_priority')
                            ->defaultValue(10)
                            ->info('Decorator priority (higher runs first)')
                        ->end()
                        ->arrayNode('label_property_candidates')
                            ->scalarPrototype()->end()
                            ->defaultValue(['name', 'title', 'label', 'displayName', 'email'])
                            ->info('Property names to check for entity labels (in order of preference); also used by auto_order for the label of to-one relations')
                        ->end()
                    ->end()
                ->end()

                // Hydra Operations Subscriber
                ->arrayNode('hydra_operations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable Hydra operations subscriber for JSON-LD responses')
                        ->end()
                        ->scalarNode('api_prefix')
                            ->defaultValue('/api')
                            ->info('API prefix for operation paths')
                        ->end()
                        ->integerNode('event_priority')
                            ->defaultValue(-10)
                            ->info('Event subscriber priority (negative runs after API Platform)')
                        ->end()
                        ->booleanNode('filter_operations_by_security')
                            ->defaultTrue()
                            ->info('Omit operations from hydra:operation whose security expression does not grant access to the current token (requires symfony/security-bundle; operations without a security expression stay visible)')
                        ->end()
                    ->end()
                ->end()

                // Hydra Documentation Subscriber (patches /api/docs.jsonld)
                ->arrayNode('hydra_documentation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Patch /api/docs.jsonld to use prefixed hydra:expects/hydra:returns and to add @id / hydra:uriTemplate to every operation')
                        ->end()
                        ->scalarNode('api_prefix')
                            ->defaultValue('/api')
                            ->info('API prefix prepended when emitting @id / hydra:uriTemplate on standard CRUD operations')
                        ->end()
                    ->end()
                ->end()

                // Custom Operation Hydra Factory
                ->arrayNode('custom_operation_hydra')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Auto-detect custom operations (URIs other than /resource and /resource/{id}) and mark them with @type=schema:Action in the Hydra documentation')
                        ->end()
                        ->scalarNode('api_prefix')
                            ->defaultValue('/api')
                            ->info('API prefix prepended when emitting @id / hydra:uriTemplate on operations')
                        ->end()
                    ->end()
                ->end()

                // Auto-generated order[<property>] parameters and default sort
                ->arrayNode('auto_order')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Generate order[<property>] query parameters (SortFilter) for the GetCollection operations of Doctrine ORM resources: sortable scalar fields and to-one relations via their label property (relation_field_decorator.label_property_candidates). Properties with an explicit #[ApiFilter(OrderFilter::class)] are left alone; #[AutoOrder] restricts or disables it per class. Requires API Platform >= 4.3.')
                        ->end()
                        ->arrayNode('default_order')
                            ->info('Default sort for GetCollection operations without an own `order`: the first property that is sortable on the resource wins, e.g. {name: ASC, createdAt: DESC}. Empty map = no default sort (API Platform orders by identifier).')
                            ->useAttributeAsKey('property')
                            ->normalizeKeys(false)
                            ->performNoDeepMerging()
                            ->scalarPrototype()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(static fn (string $v): string => strtoupper($v))
                                ->end()
                                ->validate()
                                    ->ifNotInArray(['ASC', 'DESC'])
                                    ->thenInvalid('Invalid sort direction %s, expected ASC or DESC.')
                                ->end()
                            ->end()
                            ->defaultValue(['name' => 'ASC', 'createdAt' => 'DESC'])
                        ->end()
                    ->end()
                ->end()

                // Tie-breaker for stable pagination
                ->arrayNode('stable_order')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Append ORDER BY <identifier> ASC to Doctrine ORM collection queries whose ORDER BY does not contain the identifier, so that paging over a non-unique sort column is stable. Skipped for composite/foreign identifiers and GROUP BY queries.')
                        ->end()
                    ->end()
                ->end()

                // Partial UUID Item Provider
                ->arrayNode('partial_uuid_item_provider')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Decorate the Doctrine ORM item provider so that {id} URI variables are resolved as full OR partial UUIDs project-wide. Convenience layer — full UUIDs still take the fast indexed path. Off by default to avoid surprising existing consumers.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
