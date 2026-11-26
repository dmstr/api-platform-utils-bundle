<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:38:31 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;

/**
 * Two ApiResources with the same shortName on one class, told apart by
 * their route prefix only: API Platform documents two classes with the
 * same title and two Entrypoint properties with the same @id.
 */
#[ApiResource(
    shortName: 'Sprocket',
    routePrefix: '/v1',
    operations: [
        new Get(),
        new GetCollection(),
    ],
)]
#[ApiResource(
    shortName: 'Sprocket',
    routePrefix: '/v2',
    operations: [
        new Get(),
        new GetCollection(),
    ],
)]
class Sprocket
{
    #[ApiProperty(identifier: true)]
    public string $id = '';
}
