<?php
// file generated with AI assistance: Claude Code - 2026-06-09 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Service;

use ReflectionProperty;

/**
 * Resolved metadata for one #[IriReferenceArray] property on a class.
 * Cached by IriReferenceArrayMapper to avoid repeated reflection lookups.
 */
final class IriReferenceArrayProperty
{
    public function __construct(
        public readonly string $name,
        public readonly ReflectionProperty $reflection,
        public readonly string $collection,
        public readonly string $resourceClass,
        public readonly string $labelProperty,
        public readonly string $valueProperty,
    ) {
    }
}
