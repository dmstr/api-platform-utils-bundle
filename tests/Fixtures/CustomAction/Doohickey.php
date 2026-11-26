<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:45:11 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;

/**
 * No custom_action marker anywhere: every operation is an ordinary one,
 * however deep its path, and gets its own URL from the subscriber. GET
 * paths with a placeholder are item operations, the others collections.
 */
#[ApiResource(
    shortName: 'Doohickey',
    operations: [
        new GetCollection(uriTemplate: '/doohickey'),
        new Get(uriTemplate: '/doohickey/{id}'),
        new GetCollection(uriTemplate: '/doohickey/b'),
        new Get(uriTemplate: '/doohickey/b/{id}'),
        new GetCollection(uriTemplate: '/doohickey/b/verb'),
        new Get(uriTemplate: '/doohickey/{id}/b'),
        new Post(uriTemplate: '/doohickey'),
        new Post(uriTemplate: '/doohickey/{id}'),
        new Post(uriTemplate: '/doohickey/b'),
        new Post(uriTemplate: '/doohickey/b/{id}'),
        new Post(uriTemplate: '/doohickey/b/verb'),
        new Post(uriTemplate: '/doohickey/{id}/b'),
    ],
)]
class Doohickey
{
    #[ApiProperty(identifier: true)]
    public string $id = '';
}
