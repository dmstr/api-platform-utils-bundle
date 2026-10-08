<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Metadata;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Generates `order[<property>]` query parameters (backed by API Platform's
 * {@see SortFilter}) for the GetCollection operations of Doctrine ORM
 * resources, so that every sortable column is declared by the backend
 * without a hand-written `#[ApiFilter(OrderFilter::class, ...)]`.
 *
 * Generated parameters:
 *   - scalar Doctrine fields whose DBAL type is in {@see SORTABLE_TYPES}
 *     (identifiers, text, json, binary, vector etc. are skipped);
 *   - to-one associations (ManyToOne, owning OneToOne) as `<rel>.<label>`,
 *     where `<label>` is the first configured label property candidate that
 *     is a sortable field of the target entity. No candidate, no parameter.
 *
 * Properties covered by an explicit `#[ApiFilter(OrderFilter::class)]`
 * (class or property level) are left alone, so the explicit filter wins and
 * no variable is documented twice. A class-level OrderFilter without
 * `properties` covers every property and disables auto-ordering for the
 * resource.
 *
 * Decoration priority must be higher than 1000: the factory then runs
 * BEFORE API Platform's ParameterResourceMetadataCollectionFactory, which
 * fills in schema, OpenAPI parameter and `nested_properties_info` (needed by
 * SortFilter to join the relation) for the parameters added here.
 * Consequence: `#[ApiFilter]` attributes are not merged into the operation
 * yet (that happens at priority 200), hence they are read via reflection.
 */
final class AutoOrderResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /** DBAL type names that can be sorted meaningfully. */
    public const SORTABLE_TYPES = [
        'string', 'ascii_string',
        'integer', 'smallint', 'bigint',
        'float', 'smallfloat', 'decimal',
        'boolean',
        'date', 'date_immutable',
        'datetime', 'datetime_immutable',
        'datetimetz', 'datetimetz_immutable',
        'time', 'time_immutable',
    ];

    /**
     * @param list<string> $labelPropertyCandidates
     */
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
        private readonly ManagerRegistry $managerRegistry,
        private readonly array $labelPropertyCandidates = ['name', 'title', 'label', 'displayName'],
        private readonly string $orderParameterName = 'order',
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $i => $resource) {
            $operations = $resource->getOperations();
            if (null === $operations || 0 === \count($operations)) {
                continue;
            }

            $changed = false;
            $rebuilt = [];
            foreach ($operations as $name => $operation) {
                if ($operation instanceof GetCollection && null === $operation->getProvider()) {
                    $entityClass = $operation->getClass() ?? $resourceClass;
                    $properties = $this->sortableProperties($entityClass);
                    if ([] !== $properties) {
                        $operation = $operation->withParameters($this->addParameters($operation->getParameters(), $properties));
                        $changed = true;
                    }
                }
                $rebuilt[$name] = $operation;
            }

            if ($changed) {
                $collection[$i] = $resource->withOperations(new Operations($rebuilt));
            }
        }

        return $collection;
    }

    /**
     * One QueryParameter per property (`order[<p>]` with `property: <p>`).
     * Parameters the resource already declares under the same key win.
     *
     * A single `order[:property]` parameter with `properties: [...]` yields
     * the same metadata after API Platform's placeholder expansion, but the
     * per-key form allows the collision check below and keeps each
     * parameter independent of the others.
     *
     * @param list<string> $properties
     */
    private function addParameters(?Parameters $parameters, array $properties): Parameters
    {
        // clone: the attribute-level Parameters instance must not be mutated
        $parameters = null === $parameters ? new Parameters() : clone $parameters;

        foreach ($properties as $property) {
            $key = \sprintf('%s[%s]', $this->orderParameterName, $property);
            if ($parameters->has($key)) {
                continue;
            }
            $parameters->add($key, new QueryParameter(
                key: $key,
                property: $property,
                filter: new SortFilter(),
                // a single direction, no `order[p][]` variant in OpenAPI
                castToArray: false,
            ));
        }

        return $parameters;
    }

    /**
     * @return list<string> property paths, `<field>` or `<relation>.<label>`
     */
    private function sortableProperties(string $entityClass): array
    {
        $metadata = $this->classMetadata($entityClass);
        if (null === $metadata) {
            return [];
        }

        $explicit = $this->explicitOrderProperties($metadata->getReflectionClass());
        if (null === $explicit) {
            return [];
        }

        $properties = [];
        foreach ($metadata->getFieldNames() as $field) {
            if ($this->isSortableField($metadata, $field) && !\in_array($field, $explicit, true)) {
                $properties[] = $field;
            }
        }

        foreach ($metadata->getAssociationNames() as $association) {
            if (!$metadata->isSingleValuedAssociation($association) || $metadata->isAssociationInverseSide($association)) {
                continue;
            }
            $target = $this->classMetadata($metadata->getAssociationTargetClass($association));
            $label = null === $target ? null : $this->labelProperty($target);
            if (null === $label) {
                continue;
            }
            $path = $association.'.'.$label;
            if (!\in_array($path, $explicit, true)) {
                $properties[] = $path;
            }
        }

        return $properties;
    }

    private function isSortableField(ClassMetadata $metadata, string $field): bool
    {
        // embedded fields ("embed.field") and identifiers are not offered
        if (str_contains($field, '.') || $metadata->isIdentifier($field)) {
            return false;
        }

        return \in_array($metadata->getTypeOfField($field), self::SORTABLE_TYPES, true);
    }

    /**
     * Same candidate list as RelationFieldSchemaDecorator::inferLabelProperty(),
     * but without its "first string property" fallback: sorting by an
     * arbitrary string would look like a feature and be noise.
     */
    private function labelProperty(ClassMetadata $target): ?string
    {
        foreach ($this->labelPropertyCandidates as $candidate) {
            if ($target->hasField($candidate) && $this->isSortableField($target, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Properties covered by an explicit `#[ApiFilter(OrderFilter::class)]`.
     *
     * @return list<string>|null null = a class-level OrderFilter without
     *                           `properties` covers every property
     */
    private function explicitOrderProperties(\ReflectionClass $class): ?array
    {
        $explicit = [];

        foreach ($class->getAttributes(ApiFilter::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $filter = $attribute->newInstance();
            if (!is_a($filter->filterClass, OrderFilterInterface::class, true)) {
                continue;
            }
            if ([] === $filter->properties) {
                return null;
            }
            foreach ($filter->properties as $key => $value) {
                // list form ['createdAt'] or map form ['createdAt' => 'DESC']
                $explicit[] = \is_int($key) ? (string) $value : (string) $key;
            }
        }

        foreach ($class->getProperties() as $property) {
            foreach ($property->getAttributes(ApiFilter::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $filter = $attribute->newInstance();
                if (!is_a($filter->filterClass, OrderFilterInterface::class, true)) {
                    continue;
                }
                $explicit[] = $property->getName();
                foreach ($filter->properties as $key => $value) {
                    $explicit[] = $property->getName().'.'.(\is_int($key) ? (string) $value : (string) $key);
                }
            }
        }

        return $explicit;
    }

    private function classMetadata(string $class): ?ClassMetadata
    {
        $manager = $this->managerRegistry->getManagerForClass($class);
        if (null === $manager) {
            return null;
        }
        $metadata = $manager->getClassMetadata($class);
        if (!$metadata instanceof ClassMetadata || $metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
            return null;
        }

        return $metadata;
    }
}
