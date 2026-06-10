<?php
// file generated with AI assistance: Claude Code - 2026-06-09 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\EventListener;

use Dmstr\ApiPlatformUtils\Service\IriReferenceArrayMapper;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

/**
 * Expands UUIDs to IRIs on entity properties annotated with
 * Dmstr\ApiPlatformUtils\Attribute\IriReferenceArray right after Doctrine hydrates them.
 *
 * Covers the read path for both ApiPlatform State Providers and any
 * Repository-driven access. The write path is handled by
 * App\State\IriReferenceArrayPersistProcessor.
 */
#[AsDoctrineListener(event: Events::postLoad)]
final class IriReferenceArrayDoctrineListener
{
    public function __construct(
        private readonly IriReferenceArrayMapper $mapper,
    ) {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->mapper->hasProperties($entity::class)) {
            return;
        }
        $this->mapper->normalizeEntity($entity);
    }
}
