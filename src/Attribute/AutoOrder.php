<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:05:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Attribute;

use Attribute;

/**
 * Per-class switch for the auto-generated `order[<property>]` parameters
 * and the default sort (config `auto_order`, see
 * {@see \Dmstr\ApiPlatformUtils\Metadata\AutoOrderResourceMetadataCollectionFactory}).
 *
 * Put it on the API resource class or on its Doctrine entity class (when
 * they differ, e.g. a DTO resource with `stateOptions`); both are read.
 *
 * Examples:
 *
 *   // no generated sort parameters and no default sort for this resource
 *   #[AutoOrder(enabled: false)]
 *
 *   // no order[hash] and no order[collection.<label>]
 *   #[AutoOrder(exclude: ['hash', 'collection'])]
 *
 * An `exclude` entry matches the property path itself and everything below
 * it: `collection` excludes `collection.name`. Excluded properties are not
 * used for the default sort either.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AutoOrder
{
    /**
     * @param list<string> $exclude property paths that get no generated parameter
     */
    public function __construct(
        public readonly bool $enabled = true,
        public readonly array $exclude = [],
    ) {
    }

    public function excludes(string $path): bool
    {
        foreach ($this->exclude as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded.'.')) {
                return true;
            }
        }

        return false;
    }
}
