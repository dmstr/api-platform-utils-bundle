<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:45:11 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;

/**
 * custom_action: false on the resource. Ordinary in the default
 * environment; with the marker in api_platform.defaults.extra_properties
 * (TestKernel::ENV_MARKER_DEFAULTS) the resource value still wins.
 */
#[ApiResource(
    shortName: 'Whatsit',
    extraProperties: ['custom_action' => false],
    operations: [
        new Get(),
        new GetCollection(),
    ],
)]
class Whatsit
{
    #[ApiProperty(identifier: true)]
    public string $id = '';
}
