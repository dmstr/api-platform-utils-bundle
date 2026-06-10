<?php
// file generated with AI assistance: Claude Code - 2025-11-22

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
                            ->defaultValue(['name', 'title', 'label', 'displayName'])
                            ->info('Property names to check for entity labels (in order of preference)')
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
