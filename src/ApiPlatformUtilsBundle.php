<?php
// file generated with AI assistance: Claude Code - 2026-06-07

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils;

use Dmstr\ApiPlatformUtils\DependencyInjection\ApiPlatformUtilsExtension;
use Dmstr\ApiPlatformUtils\DependencyInjection\Compiler\UuidSearchFilterCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * API Platform Utils Bundle
 * Provides generic utilities for API Platform projects
 */
class ApiPlatformUtilsBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new ApiPlatformUtilsExtension();
        }
        return $this->extension;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Transparent decorator on every SearchFilter service — rebinds
        // AbstractUid parameters with the `uuid` Doctrine type so IRI-based
        // filtering works on BINARY(16) Symfony Uid columns.
        $container->addCompilerPass(new UuidSearchFilterCompilerPass());
    }
}
