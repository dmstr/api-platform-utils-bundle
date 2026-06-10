<?php
// file generated with AI assistance: Claude Code - 2026-06-09 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use Dmstr\ApiPlatformUtils\Service\IriReferenceArrayMapper;
use Dmstr\ApiPlatformUtils\Service\IriReferenceArrayProperty;

/**
 * Schema decorator that rewrites JSON schemas for properties annotated with
 * Dmstr\ApiPlatformUtils\Attribute\IriReferenceArray.
 *
 * Replaces the inner property schema (which would otherwise default to a
 * plain `{type: array, items: {type: string}}` from the PHP type hint) with
 * an array of `iri-reference` items carrying the `x-collection` /
 * `x-label-property` / `x-resource-class` extensions that the Vue admin UI
 * consumes to render autocomplete pickers.
 *
 * Applies to both INPUT and OUTPUT schemas — the admin UI reads both for
 * forms and detail views.
 */
final class IriReferenceArraySchemaFactory implements SchemaFactoryInterface
{
    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
        private readonly IriReferenceArrayMapper $mapper,
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

        $properties = $this->mapper->getProperties($className);
        if ($properties === []) {
            return $schema;
        }

        // Root-level properties (component schemas without `definitions`).
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $rootProps = $schema['properties'];
            $this->rewriteProperties($rootProps, $properties);
            $schema['properties'] = $rootProps;
        }

        // Definition-level properties (most JSON-LD schemas live here).
        $definitions = $schema->getDefinitions();
        if ($definitions !== null) {
            foreach ($definitions as $key => $definition) {
                if (!isset($definition['properties']) || !is_array($definition['properties'])) {
                    continue;
                }
                $defProps = $definition['properties'];
                $this->rewriteProperties($defProps, $properties);
                $definition['properties'] = $defProps;
                $definitions[$key] = $definition;
            }
            $schema['definitions'] = $definitions;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed>             $properties Schema properties to rewrite (by reference).
     * @param list<IriReferenceArrayProperty>  $iriProps   IRI metadata for the class being built.
     */
    private function rewriteProperties(array &$properties, array $iriProps): void
    {
        foreach ($iriProps as $prop) {
            if (!array_key_exists($prop->name, $properties)) {
                continue;
            }
            $existing = is_array($properties[$prop->name]) ? $properties[$prop->name] : [];
            $description = $existing['description'] ?? null;

            $rewritten = [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'format' => 'iri-reference',
                    'x-collection' => $prop->collection,
                    'x-label-property' => $prop->labelProperty,
                    'x-value-property' => $prop->valueProperty,
                    'x-search-property' => $prop->labelProperty,
                    'x-resource-class' => $prop->resourceClass,
                ],
            ];
            if ($description !== null) {
                $rewritten['description'] = $description;
            }
            $properties[$prop->name] = $rewritten;
        }
    }
}
