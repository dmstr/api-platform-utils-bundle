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
 * Every operation carries custom_action: true on the operation itself, so
 * each is a custom action at any path depth, also on the generated
 * templates with {._format}. The last one sets its own hydraContext.
 */
#[ApiResource(
    shortName: 'Contraption',
    operations: [
        new GetCollection(uriTemplate: '/contraption', name: 'contraption_get_a', extraProperties: ['custom_action' => true]),
        new Get(uriTemplate: '/contraption/{id}', name: 'contraption_get_a_id', extraProperties: ['custom_action' => true]),
        new GetCollection(uriTemplate: '/contraption/b', name: 'contraption_get_a_b', extraProperties: ['custom_action' => true]),
        new Get(uriTemplate: '/contraption/b/{id}', name: 'contraption_get_a_b_id', extraProperties: ['custom_action' => true]),
        new GetCollection(uriTemplate: '/contraption/b/verb', name: 'contraption_get_a_b_verb', extraProperties: ['custom_action' => true]),
        new Post(uriTemplate: '/contraption', name: 'contraption_post_a', extraProperties: ['custom_action' => true]),
        new Post(uriTemplate: '/contraption/{id}', name: 'contraption_post_a_id', extraProperties: ['custom_action' => true]),
        new Post(uriTemplate: '/contraption/b', name: 'contraption_post_a_b', extraProperties: ['custom_action' => true]),
        new Post(uriTemplate: '/contraption/b/{id}', name: 'contraption_post_a_b_id', extraProperties: ['custom_action' => true]),
        new Post(uriTemplate: '/contraption/b/verb', name: 'contraption_post_a_b_verb', extraProperties: ['custom_action' => true]),
        new Get(name: 'contraption_item', extraProperties: ['custom_action' => true]),
        new GetCollection(name: 'contraption_list', extraProperties: ['custom_action' => true]),
        new Post(
            uriTemplate: '/contraption/run',
            name: 'contraption_run',
            hydraContext: ['@type' => 'schema:SearchAction', 'hydra:title' => 'Run it'],
            extraProperties: ['custom_action' => true],
        ),
    ],
)]
class Contraption
{
    #[ApiProperty(identifier: true)]
    public string $id = '';
}
