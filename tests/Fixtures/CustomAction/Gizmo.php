<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:38:31 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

/**
 * Collection only: API Platform adds a NotExposed item operation, which
 * the Hydra documentation lists as a class-level GET.
 */
#[ApiResource(
    shortName: 'Gizmo',
    operations: [
        new GetCollection(),
    ],
)]
class Gizmo
{
    #[ApiProperty(identifier: true)]
    public string $id = '';
}
