<?php
// file generated with AI assistance: Claude Code - 2026-10-10 00:15:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;

/**
 * Drops a `default` that its own (sub-)schema rejects.
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
     * Keywords whose value is a map of sub-schemas (name => schema).
     */
    private const SCHEMA_MAPS = ['properties'];

    /**
     * Keywords whose value is a list of sub-schemas.
     */
    private const SCHEMA_LISTS = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];

    /**
     * Keywords whose value is a single sub-schema; a boolean (`true`/`false`)
     * is not a schema and is skipped.
     */
    private const SCHEMA_SINGLES = ['additionalProperties'];

    /**
     * Definition objects already cleaned, per definitions object (one per
     * build). Both levels are weak: a finished build releases its entry, and
     * an entry of a freed definition disappears, so a new object that reuses
     * its object id is never mistaken for it.
     *
     * @var \WeakMap<\ArrayObject<string, mixed>, \WeakMap<\ArrayObject<array-key, mixed>, true>>
     */
    private readonly \WeakMap $cleaned;

    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
    ) {
        $this->cleaned = new \WeakMap();
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

        // The definitions object is shared by all calls of one build and grows
        // with each of them; walking all of it every time is quadratic. An
        // ArrayObject definition is cleaned in place and remembered, so it is
        // walked once per build. A definition replaced under the same name by
        // a new object (Hydra wraps definitions in `allOf`) is a new key and
        // is cleaned again; plain-array definitions are cleaned every time.
        // Remaining risk: a default added later, in place, to an already
        // cleaned object is not checked. No schema factory decorator in API
        // Platform 4.4 does that.
        $definitions = $schema->getDefinitions();
        $cleaned = $this->cleaned[$definitions] ??= new \WeakMap();
        foreach ($definitions as $key => $definition) {
            if (!$definition instanceof \ArrayObject) {
                $definitions[$key] = $this->cleanNode($definition);
                continue;
            }
            if (isset($cleaned[$definition])) {
                continue;
            }
            $this->cleanNode($definition);
            $cleaned[$definition] = true;
        }

        return $schema;
    }

    /**
     * Walks a schema node recursively and checks the default of every visited
     * schema, the definition root included. Sub-schemas are found under
     * `properties`, `allOf`/`anyOf`/`oneOf`, `items` (a single schema or the
     * tuple form), `prefixItems` and `additionalProperties`. Nodes may be
     * plain arrays or ArrayObjects at every level.
     *
     * A node with `$ref` keeps its default: the referenced schema is not
     * resolved, so the default cannot be judged.
     */
    private function cleanNode(mixed $node): mixed
    {
        if (!self::isArrayLike($node)) {
            return $node;
        }

        if (!isset($node['$ref'])) {
            $node = $this->dropInvalidDefault($node);
        }

        foreach ([...self::SCHEMA_MAPS, ...self::SCHEMA_LISTS] as $keyword) {
            if (isset($node[$keyword]) && self::isArrayLike($node[$keyword])) {
                $node[$keyword] = $this->cleanChildren($node[$keyword]);
            }
        }

        foreach (self::SCHEMA_SINGLES as $keyword) {
            if (isset($node[$keyword]) && self::isArrayLike($node[$keyword])) {
                $node[$keyword] = $this->cleanNode($node[$keyword]);
            }
        }

        if (isset($node['items']) && self::isArrayLike($node['items'])) {
            $items = $node['items'];
            $isTuple = array_is_list($items instanceof \ArrayObject ? $items->getArrayCopy() : $items);
            $node['items'] = $isTuple ? $this->cleanChildren($items) : $this->cleanNode($items);
        }

        return $node;
    }

    /**
     * @param array<array-key, mixed>|\ArrayObject<array-key, mixed> $children
     *
     * @return array<array-key, mixed>|\ArrayObject<array-key, mixed>
     */
    private function cleanChildren(array|\ArrayObject $children): array|\ArrayObject
    {
        foreach ($children as $key => $child) {
            $children[$key] = $this->cleanNode($child);
        }

        return $children;
    }

    /**
     * @phpstan-assert-if-true array<array-key, mixed>|\ArrayObject<array-key, mixed> $node
     */
    private static function isArrayLike(mixed $node): bool
    {
        return \is_array($node) || $node instanceof \ArrayObject;
    }

    private function dropInvalidDefault(mixed $property): mixed
    {
        // presence, not isset(): a `default: null` is a default as well
        if (\is_array($property)) {
            $hasDefault = \array_key_exists('default', $property);
        } elseif ($property instanceof \ArrayObject) {
            $hasDefault = $property->offsetExists('default');
        } else {
            return $property;
        }
        if (!$hasDefault) {
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

        // the string keywords below do not judge other types, `null` included:
        // a `null` default is only checked against `enum`
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
            // is escaped, everything else is passed through as written. Only
            // an unescaped `/` (after an even number of backslashes) gets a
            // backslash: `\/` stays `\/`, `\\/` becomes `\\\/`. `D` makes `$`
            // match only at the very end, as in ECMA-262, not also before a
            // trailing newline.
            $regex = '/' . preg_replace('~(?<!\\\\)((?:\\\\\\\\)*)/~', '$1\\/', $property['pattern']) . '/uD';
            $match = @preg_match($regex, $default);
            if ($match === 0) {
                return true;
            }
            // false: PHP cannot compile it — keep the default rather than guess
        }

        return false;
    }
}
