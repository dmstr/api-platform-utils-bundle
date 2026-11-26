<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Metadata;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;

/**
 * Single source of the rule "is this operation a custom action?".
 *
 * Used by {@see CustomOperationHydraFactory} (which operations get
 * `schema:Action` Hydra metadata) and by
 * {@see \Dmstr\ApiPlatformUtils\EventSubscriber\HydraDocumentationSubscriber}
 * (which operations it leaves to the factory when filling URL carriers).
 *
 * Classification is URI-based (no annotation needed):
 *   /resource              → standard collection (not custom)
 *   /resource/{param}      → standard item       (not custom)
 *   anything else          → custom action
 *
 * Only HTTP operations with a non-empty URI template can be custom actions.
 */
final class CustomAction
{
    private const FORMAT_PLACEHOLDER = '/\{\._format\}/';

    private function __construct()
    {
    }

    /**
     * @param bool $ignoreFormatPlaceholder remove every `{._format}` from the
     *                                      URI template before classifying;
     *                                      only differs where `{._format}`
     *                                      stands as a whole path segment
     */
    public static function isCustomAction(Operation $operation, bool $ignoreFormatPlaceholder = false): bool
    {
        if (!$operation instanceof HttpOperation) {
            return false;
        }

        $template = $operation->getUriTemplate() ?? '';
        if ($ignoreFormatPlaceholder) {
            $template = (string) preg_replace(self::FORMAT_PLACEHOLDER, '', $template);
        }

        return self::isCustomUriTemplate($template);
    }

    private static function isCustomUriTemplate(string $template): bool
    {
        $template = trim($template, '/');
        if ('' === $template) {
            return false;
        }

        $segments = explode('/', $template);
        $count = \count($segments);

        // /resource
        if (1 === $count) {
            return false;
        }

        // /resource/{id}
        if (2 === $count && str_starts_with($segments[1], '{')) {
            return false;
        }

        return true;
    }
}
