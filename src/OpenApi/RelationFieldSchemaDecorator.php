<?php
// file generated with AI assistance: Claude Code - 2025-11-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Decorator that adds OpenAPI x-* extensions to relation properties for autocomplete rendering.
 *
 * Automatically detects Doctrine relations and adds:
 * - x-collection: Collection endpoint URL
 * - x-label-property: Property to display in dropdown
 * - x-value-property: Property to use as value (usually @id)
 * - x-search-property: Property to filter by
 * - x-resource-class: Target resource short name
 *
 * Example output:
 * {
 *   "customer": {
 *     "type": ["string", "null"],
 *     "format": "iri-reference",
 *     "x-collection": "/api/admin/customers",
 *     "x-label-property": "name",
 *     "x-value-property": "@id",
 *     "x-search-property": "name",
 *     "x-resource-class": "Customer"
 *   }
 * }
 */
class RelationFieldSchemaDecorator implements SchemaFactoryInterface
{
    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly LoggerInterface $logger,
        private readonly string $apiPrefix = '/api',
        private readonly array $labelPropertyCandidates = ['name', 'title', 'label', 'displayName']
    ) {
    }

    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        ?Operation $operation = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false
    ): Schema {
        $schema = $this->decorated->buildSchema(
            $className,
            $format,
            $type,
            $operation,
            $schema,
            $serializerContext,
            $forceCollection
        );

        // Only add extensions for INPUT schemas (forms)
        if ($type !== Schema::TYPE_INPUT) {
            $this->logger->debug('Skipping RelationFieldSchemaDecorator for non-INPUT schema', [
                'className' => $className,
                'type' => $type
            ]);
            return $schema;
        }

        $this->logger->debug('Processing RelationFieldSchemaDecorator for INPUT schema', [
            'className' => $className
        ]);

        try {
            $metadata = $this->entityManager->getClassMetadata($className);
        } catch (\Exception $e) {
            // Not a Doctrine entity, skip
            $this->logger->debug('Not a Doctrine entity, skipping', [
                'className' => $className,
                'error' => $e->getMessage()
            ]);
            return $schema;
        }

        // Process both root-level and definition properties
        $this->processProperties($schema, $metadata, 'properties');

        $definitions = $schema->getDefinitions();
        $this->logger->debug('Checking definitions', [
            'className' => $metadata->getName(),
            'hasDefinitions' => $definitions !== null,
            'definitionKeys' => $definitions !== null ? array_keys((array)$definitions) : []
        ]);

        if ($definitions !== null) {
            foreach ($definitions as $key => $definition) {
                if (!isset($definition['properties'])) {
                    $this->logger->debug('Definition has no properties', [
                        'className' => $metadata->getName(),
                        'definitionKey' => $key
                    ]);
                    continue;
                }

                $this->logger->debug('Processing definition properties', [
                    'className' => $metadata->getName(),
                    'definitionKey' => $key,
                    'propertyNames' => array_keys($definition['properties'])
                ]);

                $this->processDefinitionProperties($definitions, $key, $metadata);
            }
            $schema['definitions'] = $definitions;
        }

        return $schema;
    }

    private function processProperties(Schema $schema, ClassMetadata $metadata, string $propertiesKey): void
    {
        $properties = $schema[$propertiesKey] ?? [];

        $this->logger->debug('Processing properties in schema', [
            'className' => $metadata->getName(),
            'propertiesKey' => $propertiesKey,
            'propertyNames' => array_keys($properties)
        ]);

        foreach ($properties as $propertyName => $propertyDef) {
            // Check if this is an iri-reference (relation field)
            if (!isset($propertyDef['format']) || $propertyDef['format'] !== 'iri-reference') {
                $this->logger->debug('Skipping property (not iri-reference)', [
                    'className' => $metadata->getName(),
                    'propertyName' => $propertyName,
                    'format' => $propertyDef['format'] ?? 'not set'
                ]);
                continue;
            }

            $this->logger->debug('Found iri-reference property', [
                'className' => $metadata->getName(),
                'propertyName' => $propertyName
            ]);

            $extensions = $this->getRelationExtensions($metadata, $propertyName);
            if ($extensions !== null) {
                $this->logger->debug('Adding x-* extensions to relation property', [
                    'className' => $metadata->getName(),
                    'propertyName' => $propertyName,
                    'extensions' => $extensions
                ]);
                $properties[$propertyName] = array_merge($propertyDef, $extensions);
            }
        }

        $schema[$propertiesKey] = $properties;
    }

    private function processDefinitionProperties(\ArrayObject &$definitions, string $key, ClassMetadata $metadata): void
    {
        $definition = $definitions[$key];

        foreach ($definition['properties'] as $propertyName => $propertyDef) {
            // Check if this is an iri-reference (relation field)
            if (!isset($propertyDef['format']) || $propertyDef['format'] !== 'iri-reference') {
                $this->logger->debug('Skipping definition property (not iri-reference)', [
                    'className' => $metadata->getName(),
                    'definitionKey' => $key,
                    'propertyName' => $propertyName,
                    'format' => $propertyDef['format'] ?? 'not set'
                ]);
                continue;
            }

            $this->logger->debug('Found iri-reference in definition', [
                'className' => $metadata->getName(),
                'definitionKey' => $key,
                'propertyName' => $propertyName
            ]);

            $extensions = $this->getRelationExtensions($metadata, $propertyName);
            if ($extensions !== null) {
                $this->logger->debug('Adding x-* extensions to definition property', [
                    'className' => $metadata->getName(),
                    'definitionKey' => $key,
                    'propertyName' => $propertyName,
                    'extensions' => $extensions
                ]);
                // Convert ArrayObject to array if needed
                $propertyDefArray = is_array($propertyDef) ? $propertyDef : (array) $propertyDef;
                $definitions[$key]['properties'][$propertyName] = array_merge($propertyDefArray, $extensions);
            }
        }
    }

    private function getRelationExtensions(ClassMetadata $metadata, string $propertyName): ?array
    {
        $this->logger->debug('Getting relation extensions', [
            'className' => $metadata->getName(),
            'propertyName' => $propertyName
        ]);

        // Check if it's a Doctrine association
        if (!$metadata->hasAssociation($propertyName)) {
            $this->logger->debug('Property is not a Doctrine association', [
                'className' => $metadata->getName(),
                'propertyName' => $propertyName
            ]);
            return null;
        }

        $targetClass = $metadata->getAssociationTargetClass($propertyName);
        $this->logger->debug('Found association', [
            'className' => $metadata->getName(),
            'propertyName' => $propertyName,
            'targetClass' => $targetClass
        ]);

        // Get API Platform resource metadata for target
        try {
            $resourceMetadata = $this->resourceMetadataFactory->create($targetClass);
        } catch (\Exception $e) {
            // Target entity is not an API Platform resource
            $this->logger->debug('Target entity is not an API Platform resource', [
                'sourceClass' => $metadata->getName(),
                'propertyName' => $propertyName,
                'targetClass' => $targetClass,
                'error' => $e->getMessage()
            ]);
            return null;
        }

        // Get collection endpoint
        $collectionPath = $this->getCollectionPath($resourceMetadata);
        if ($collectionPath === null) {
            $this->logger->debug('No collection path found', [
                'className' => $metadata->getName(),
                'propertyName' => $propertyName,
                'targetClass' => $targetClass
            ]);
            return null;
        }

        // Infer label property from target entity
        $labelProperty = $this->inferLabelProperty($targetClass);

        // Get short resource name
        $shortName = $this->getShortName($targetClass);

        $extensions = [
            'x-collection' => $collectionPath,
            'x-label-property' => $labelProperty,
            'x-value-property' => '@id',
            'x-search-property' => $labelProperty,
            'x-resource-class' => $shortName
        ];

        $this->logger->debug('Created extensions', [
            'className' => $metadata->getName(),
            'propertyName' => $propertyName,
            'extensions' => $extensions
        ]);

        return $extensions;
    }

    private function getCollectionPath(ResourceMetadataCollection $resourceMetadata): ?string
    {
        $this->logger->debug('Looking for collection path', [
            'resourceCount' => count($resourceMetadata)
        ]);

        // Find GetCollection operation
        foreach ($resourceMetadata as $resource) {
            $operations = $resource->getOperations();
            if ($operations === null) {
                $this->logger->debug('No operations found for resource');
                continue;
            }

            $this->logger->debug('Checking operations', [
                'operationCount' => count($operations)
            ]);

            foreach ($operations as $operation) {
                $operationClass = get_class($operation);

                if ($operationClass === 'ApiPlatform\Metadata\GetCollection') {
                    // Get route prefix from operation
                    $routePrefix = $operation->getRoutePrefix() ?? '';
                    $uriTemplate = $operation->getUriTemplate() ?? '';

                    // Build the full path the same way OpenAPI factory does
                    // rtrim() removes trailing slashes from prefix
                    // ltrim() removes leading slashes from template
                    $path = rtrim($routePrefix, '/') . '/' . ltrim($uriTemplate, '/');

                    // Remove {._format} placeholder
                    $path = str_replace('{._format}', '', $path);

                    // Add the configured API prefix if not already present
                    if (!str_starts_with($path, $this->apiPrefix)) {
                        $path = $this->apiPrefix . $path;
                    }

                    $this->logger->debug('Found GetCollection operation with URI', [
                        'uriTemplate' => $uriTemplate,
                        'routePrefix' => $routePrefix,
                        'fullPath' => $path
                    ]);

                    return $path;
                }
            }
        }

        $this->logger->debug('No GetCollection operation found');
        return null;
    }

    private function inferLabelProperty(string $className): string
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            // Priority 1: Check for properties with common label names
            foreach ($this->labelPropertyCandidates as $candidate) {
                if ($reflectionClass->hasProperty($candidate)) {
                    return $candidate;
                }
            }

            // Priority 2: Find first non-ID string property
            foreach ($reflectionClass->getProperties() as $property) {
                $propertyName = $property->getName();

                // Skip ID fields
                if ($propertyName === 'id') {
                    continue;
                }

                // Check if it's a string type (via PHP 7.4+ typed property or docblock)
                $type = $property->getType();
                if ($type !== null && $type->getName() === 'string') {
                    return $propertyName;
                }
            }
        } catch (\ReflectionException $e) {
            $this->logger->warning('Failed to infer label property', [
                'className' => $className,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to first label candidate if nothing found
        return $this->labelPropertyCandidates[0] ?? 'name';
    }

    private function getShortName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }
}
