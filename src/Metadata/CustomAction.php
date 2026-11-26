<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Metadata;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Operation;

/**
 * Single source of the rule "is this operation a custom action?".
 *
 * Used by {@see CustomOperationHydraFactory} (which operations get
 * `schema:Action` Hydra metadata) and by
 * {@see \Dmstr\ApiPlatformUtils\EventSubscriber\HydraDocumentationSubscriber}
 * (which operations it leaves to the factory when filling URL carriers).
 *
 * A custom action is exactly an HTTP operation, other than API Platform's
 * `NotExposed` stub, whose `extraProperties` carry {@see self::MARKER} with
 * the boolean value `true`; any other value (missing, `false`, `1`,
 * `'true'`, ...) does not mark. The URI template plays no part.
 *
 * API Platform 4.x merges `extraProperties` flatly (operation over
 * `#[ApiResource]` over `api_platform.defaults.extra_properties`), so a
 * marker on the resource marks all of its operations and `false` on an
 * operation exempts it. The `NotExposed` stub inherits a resource marker
 * too, which is why it is excluded explicitly.
 */
final class CustomAction
{
    public const MARKER = 'custom_action';

    private function __construct()
    {
    }

    public static function isCustomAction(Operation $operation): bool
    {
        if (!$operation instanceof HttpOperation || $operation instanceof NotExposed) {
            return false;
        }

        return true === ($operation->getExtraProperties()[self::MARKER] ?? null);
    }
}
