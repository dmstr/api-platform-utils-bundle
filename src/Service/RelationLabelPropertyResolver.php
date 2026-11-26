<?php
// file generated with AI assistance: Claude Code - 2026-10-14 21:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Service;

use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Picks the property that labels an entity when it is the target of a
 * relation. Shared by the schema hint (`x-label-property`) and the embedded
 * relation labels, so both always name the same property.
 *
 * Order: the first configured candidate the class declares, else its first
 * non-ID string property, else the first candidate.
 */
final class RelationLabelPropertyResolver
{
    /** @var array<class-string, string> */
    private array $cache = [];

    /**
     * @param list<string> $labelPropertyCandidates
     */
    public function __construct(
        private readonly array $labelPropertyCandidates = ['name', 'title', 'label', 'displayName', 'email'],
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function resolve(string $className): string
    {
        return $this->cache[$className] ??= $this->infer($className);
    }

    private function infer(string $className): string
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            foreach ($this->labelPropertyCandidates as $candidate) {
                if ($reflectionClass->hasProperty($candidate)) {
                    return $candidate;
                }
            }

            foreach ($reflectionClass->getProperties() as $property) {
                if ($property->getName() === 'id') {
                    continue;
                }
                $type = $property->getType();
                if ($type instanceof \ReflectionNamedType && $type->getName() === 'string') {
                    return $property->getName();
                }
            }
        } catch (\ReflectionException $e) {
            $this->logger?->warning('Failed to infer label property', [
                'className' => $className,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->labelPropertyCandidates[0] ?? 'name';
    }
}
