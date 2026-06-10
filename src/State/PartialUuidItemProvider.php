<?php
// file generated with AI assistance: Claude Code - 2026-05-09 01:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Symfony\Component\Uid\Uuid;

/**
 * Decorates the Doctrine ORM item provider so that {id} URI variables
 * are resolved as full OR partial UUIDs project-wide.
 *
 * Routing logic:
 *   - missing/non-string id        → delegate to inner provider
 *   - Uuid object or full UUID str → delegate to inner provider (fast indexed path)
 *   - non-UUID partial string      → resolve via UuidResolver::findByPartialUuid()
 *
 * Partial-UUID resolution is a *convenience layer*. The fast path through
 * the standard Doctrine provider is preserved for full UUIDs, so production
 * traffic that always sends full identifiers pays no penalty (one regex
 * match per request).
 *
 * Multi-match collisions surface as the RuntimeException raised by
 * UuidResolver, which API Platform translates to a 5xx response. Callers
 * SHOULD send full UUIDs in service-to-service traffic; partial matching is
 * intended for CLI ergonomics, ad-hoc curl, and admin debugging.
 */
final class PartialUuidItemProvider implements ProviderInterface
{
    /**
     * RFC 4122 UUID anchor (v1-v8). Case-insensitive; hyphens required.
     */
    private const FULL_UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly ProviderInterface $inner,
        private readonly UuidResolver $uuidResolver,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = $uriVariables['id'] ?? null;

        // No id, Uuid object, or already-full UUID → standard fast path.
        if ($id === null) {
            return $this->inner->provide($operation, $uriVariables, $context);
        }
        if ($id instanceof Uuid) {
            return $this->inner->provide($operation, $uriVariables, $context);
        }
        if (!\is_string($id)) {
            return $this->inner->provide($operation, $uriVariables, $context);
        }
        if (preg_match(self::FULL_UUID_REGEX, $id) === 1) {
            return $this->inner->provide($operation, $uriVariables, $context);
        }

        // Partial UUID → resolve against the operation's own resource class.
        $class = $operation->getClass();
        if ($class === null) {
            return $this->inner->provide($operation, $uriVariables, $context);
        }

        $resolved = $this->uuidResolver->findByPartialUuid($class, $id);

        if ($resolved !== null) {
            $this->logger->debug('Partial UUID resolved', [
                'class' => $class,
                'partial' => $id,
                'operation' => $operation->getName(),
            ]);
        }

        return $resolved;
        // null → API Platform raises 404 automatically.
    }
}
