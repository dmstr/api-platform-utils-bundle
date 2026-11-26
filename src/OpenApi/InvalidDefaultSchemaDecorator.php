<?php
// file generated with AI assistance: Claude Code - 2026-10-10 00:15:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;

/**
 * Drops a property `default` that its own property schema rejects.
 *
 * API Platform derives `default` from the PHP property initializer, so
 * `private string $slug = '';` next to `#[Assert\Regex(...)]` yields
 * `{"pattern": "^[a-z0-9-]+$", "default": ""}` — a schema that contradicts
 * itself. Form clients start from the default and show a validation error
 * before anyone has typed. Removing the default only removes the
 * contradiction: the field simply starts empty in the client, and the
 * server-side validation is unchanged.
 *
 * Checked keywords: `pattern`, `minLength`, `maxLength` (strings) and `enum`.
 * A pattern PHP cannot compile is treated as "unknown" and keeps the default.
 */
class InvalidDefaultSchemaDecorator implements SchemaFactoryInterface
{
    /**
     * Keywords whose value is a map of sub-schemas (name => schema); the
     * default of each child is checked.
     */
    private const SCHEMA_MAPS = ['properties'];

    /**
     * Keywords whose value is a list of sub-schemas; the branches are walked.
     */
    private const SCHEMA_LISTS = ['allOf', 'anyOf', 'oneOf'];

    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
    ) {
    }

    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        ?Operation $operation = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false,
    ): Schema {
        $schema = $this->decorated->buildSchema(
            $className,
            $format,
            $type,
            $operation,
            $schema,
            $serializerContext,
            $forceCollection,
        );

        $definitions = $schema->getDefinitions();
        foreach ($definitions as $key => $definition) {
            $definitions[$key] = $this->cleanNode($definition);
        }

        return $schema;
    }

    /**
     * Walks a schema node recursively through its sub-schemas: `properties`
     * (nested objects included) and `allOf`/`anyOf`/`oneOf` branches, the
     * places API Platform puts property schemas. Nodes may be plain arrays or
     * ArrayObjects at every level.
     */
    private function cleanNode(mixed $node, bool $checkDefault = false): mixed
    {
        if (!\is_array($node) && !$node instanceof \ArrayObject) {
            return $node;
        }

        if ($checkDefault) {
            $node = $this->dropInvalidDefault($node);
        }

        foreach (self::SCHEMA_MAPS as $keyword) {
            if (isset($node[$keyword]) && (\is_array($node[$keyword]) || $node[$keyword] instanceof \ArrayObject)) {
                $node[$keyword] = $this->cleanChildren($node[$keyword], true);
            }
        }

        foreach (self::SCHEMA_LISTS as $keyword) {
            if (isset($node[$keyword]) && \is_array($node[$keyword])) {
                $node[$keyword] = $this->cleanChildren($node[$keyword], false);
            }
        }

        return $node;
    }

    /**
     * @param array<array-key, mixed>|\ArrayObject<array-key, mixed> $children
     *
     * @return array<array-key, mixed>|\ArrayObject<array-key, mixed>
     */
    private function cleanChildren(array|\ArrayObject $children, bool $checkDefault): array|\ArrayObject
    {
        foreach ($children as $key => $child) {
            $children[$key] = $this->cleanNode($child, $checkDefault);
        }

        return $children;
    }

    private function dropInvalidDefault(mixed $property): mixed
    {
        if ((!\is_array($property) && !$property instanceof \ArrayObject) || !isset($property['default'])) {
            return $property;
        }

        if (!$this->violates($property, $property['default'])) {
            return $property;
        }

        unset($property['default']);

        return $property;
    }

    private function violates(array|\ArrayObject $property, mixed $default): bool
    {
        if (isset($property['enum']) && \is_array($property['enum']) && !\in_array($default, $property['enum'], true)) {
            return true;
        }

        if (!\is_string($default)) {
            return false;
        }

        $length = mb_strlen($default);
        if (isset($property['minLength']) && $length < (int) $property['minLength']) {
            return true;
        }
        if (isset($property['maxLength']) && $length > (int) $property['maxLength']) {
            return true;
        }

        if (isset($property['pattern']) && \is_string($property['pattern'])) {
            // JSON Schema patterns are ECMA-262 and unanchored; the delimiter
            // is escaped, everything else is passed through as written.
            $regex = '/' . str_replace('/', '\/', $property['pattern']) . '/u';
            $match = @preg_match($regex, $default);
            if ($match === 0) {
                return true;
            }
            // false: PHP cannot compile it — keep the default rather than guess
        }

        return false;
    }
}
