<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:45:11 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;

/**
 * custom_action: true on the resource: every operation inherits it, except
 * the create operation, which sets false on itself. Without an item GET,
 * API Platform adds a NotExposed stub, which inherits the marker too but is
 * never a custom action.
 */
#[ApiResource(
    shortName: 'Thingamajig',
    extraProperties: ['custom_action' => true, 'label' => 'Thingamajigs'],
    operations: [
        new GetCollection(name: 'thingamajig_list'),
        new Post(uriTemplate: '/thingamajigs/{id}/run', name: 'thingamajig_run'),
        new Post(name: 'thingamajig_create', extraProperties: ['custom_action' => false]),
    ],
)]
class Thingamajig
{
    #[ApiProperty(identifier: true)]
    public string $id = '';
}
