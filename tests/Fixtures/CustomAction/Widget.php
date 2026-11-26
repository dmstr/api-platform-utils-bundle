<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:25:10 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;

/**
 * Plain resource with the standard read operations only — the baseline
 * class of the Hydra documentation served by TestKernel. Not a Doctrine
 * entity and without a state provider: the documentation needs metadata
 * only, no data.
 */
#[ApiResource(
    shortName: 'Widget',
    operations: [
        new Get(),
        new GetCollection(),
    ],
)]
class Widget
{
    #[ApiProperty(identifier: true)]
    public string $id = '';

    public string $name = '';
}
