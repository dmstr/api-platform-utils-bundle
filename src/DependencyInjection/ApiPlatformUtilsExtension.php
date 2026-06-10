<?php
// file generated with AI assistance: Claude Code - 2025-11-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * DependencyInjection extension for API Platform Utils Bundle
 */
class ApiPlatformUtilsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration as parameters
        $container->setParameter('dmstr_api_platform_utils.credential_encryption.enabled', $config['credential_encryption']['enabled']);
        $container->setParameter('dmstr_api_platform_utils.credential_encryption.key', $config['credential_encryption']['key']);

        $container->setParameter('dmstr_api_platform_utils.relation_field_decorator.enabled', $config['relation_field_decorator']['enabled']);
        $container->setParameter('dmstr_api_platform_utils.relation_field_decorator.api_prefix', $config['relation_field_decorator']['api_prefix']);
        $container->setParameter('dmstr_api_platform_utils.relation_field_decorator.decoration_priority', $config['relation_field_decorator']['decoration_priority']);
        $container->setParameter('dmstr_api_platform_utils.relation_field_decorator.label_property_candidates', $config['relation_field_decorator']['label_property_candidates']);

        $container->setParameter('dmstr_api_platform_utils.hydra_operations.enabled', $config['hydra_operations']['enabled']);
        $container->setParameter('dmstr_api_platform_utils.hydra_operations.api_prefix', $config['hydra_operations']['api_prefix']);
        $container->setParameter('dmstr_api_platform_utils.hydra_operations.event_priority', $config['hydra_operations']['event_priority']);

        $container->setParameter('dmstr_api_platform_utils.custom_operation_hydra.enabled', $config['custom_operation_hydra']['enabled']);
        $container->setParameter('dmstr_api_platform_utils.custom_operation_hydra.api_prefix', $config['custom_operation_hydra']['api_prefix']);

        $container->setParameter('dmstr_api_platform_utils.hydra_documentation.enabled', $config['hydra_documentation']['enabled']);
        $container->setParameter('dmstr_api_platform_utils.hydra_documentation.api_prefix', $config['hydra_documentation']['api_prefix']);

        $container->setParameter('dmstr_api_platform_utils.partial_uuid_item_provider.enabled', $config['partial_uuid_item_provider']['enabled']);

        $container->setParameter('dmstr_api_platform_utils.partial_uuid_item_provider.enabled', $config['partial_uuid_item_provider']['enabled']);

        // Load service definitions
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // Conditionally load the partial UUID item provider decorator.
        // Loaded only when explicitly enabled, so the default install does
        // not change item-lookup semantics for existing bundle consumers.
        if ($config['partial_uuid_item_provider']['enabled']) {
            $loader->load('services_partial_uuid.yaml');
        }
    }

    public function getAlias(): string
    {
        return 'dmstr_api_platform_utils';
    }
}
