<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:38:31 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;

/**
 * Several operations per method and level: two item GETs, two collection
 * GETs, two POSTs (one with a placeholder), a hidden GET between them and a
 * custom action, so every documented operation has to be paired with its
 * own metadata operation to get the right URL.
 */
#[ApiResource(
    shortName: 'Gadget',
    operations: [
        new Get(),
        new Get(uriTemplate: '/hidden-gadgets/{id}{._format}', hideHydraOperation: true),
        new Get(uriTemplate: '/gadget-previews/{id}{._format}'),
        new Get(uriTemplate: '/gadgets/{id}/summary'),
        new GetCollection(),
        new GetCollection(uriTemplate: '/gadget-archive{._format}'),
        new Post(),
        new Post(uriTemplate: '/gadget-copies/{id}{._format}'),
    ],
)]
class Gadget
{
    #[ApiProperty(identifier: true)]
    public string $id = '';

    public string $name = '';
}
