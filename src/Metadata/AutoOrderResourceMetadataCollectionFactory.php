<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Metadata;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Doctrine\Orm\State\Options as OrmOptions;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\ApiPlatformUtils\Attribute\AutoOrder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Generates `order[<property>]` query parameters (backed by API Platform's
 * {@see SortFilter}) for the GetCollection operations of Doctrine ORM
 * resources, so that every sortable column is declared by the backend
 * without a hand-written `#[ApiFilter(OrderFilter::class, ...)]`, and sets a
 * default sort on operations that declare none.
 *
 * Generated parameters:
 *   - scalar Doctrine fields whose DBAL type is in {@see SORTABLE_TYPES}
 *     (identifiers of any type, text, json, binary, vector etc. are skipped);
 *   - to-one associations (ManyToOne, owning OneToOne) as `<rel>.<label>`,
 *     where `<label>` is the first configured label property candidate that
 *     is a sortable, readable field of the target entity. No candidate, no
 *     parameter (there is deliberately no "first string property" fallback).
 *
 * Only properties the API actually exposes are offered: a property must be
 * in the resource's property name collection and readable with the
 * operation's normalization groups (`#[ApiProperty(readable: false)]` and
 * properties outside the groups are skipped).
 *
 * Precedence: a parameter the operation already declares under the same key
 * wins, and so does an explicit `#[ApiFilter(OrderFilter::class)]` (class or
 * property level) per property. A class-level OrderFilter without
 * `properties` covers every property, so no parameter is generated then.
 * `#[AutoOrder(enabled: false)]` switches a class off, `#[AutoOrder(exclude:
 * [...])]` drops single properties.
 *
 * Default sort: when the operation has no `order`, the first key of the
 * configured default order that is a sortable property of the resource
 * becomes the operation's `order`. API Platform's OrderExtension applies it
 * only when the client sends no sort parameter. Without a match the
 * operation keeps API Platform's own default (identifier).
 *
 * Skipped operations: non-GetCollection operations, operations with their
 * own `provider`, and classes that are not Doctrine ORM entities. A DTO
 * resource is mapped to its entity via `stateOptions: new Options(entityClass: ...)`.
 *
 * Decoration priority must be higher than 1000: the factory then runs
 * BEFORE API Platform's ParameterResourceMetadataCollectionFactory, which
 * fills in schema, OpenAPI parameter and `nested_properties_info` (needed by
 * SortFilter to join the relation) for the parameters added here.
 * Consequence: `#[ApiFilter]` attributes are not merged into the operation
 * yet (that happens at priority 200), hence they are read via reflection.
 * Filters registered by service id (`ApiResource(filters: [...])`) are not
 * recognised.
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
     * @param list<string>          $labelPropertyCandidates relation label candidates, in order of preference
     * @param array<string, string> $defaultOrder            property => ASC|DESC, first sortable key wins
     */
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
        private readonly ManagerRegistry $managerRegistry,
        private readonly PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        private readonly array $labelPropertyCandidates = ['name', 'title', 'label', 'displayName', 'email'],
        private readonly array $defaultOrder = ['name' => 'ASC', 'createdAt' => 'DESC'],
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
                    $apiClass = $operation->getClass() ?? $resourceClass;
                    $stateOptions = $operation->getStateOptions();
                    $entityClass = $stateOptions instanceof OrmOptions && null !== $stateOptions->getEntityClass()
                        ? $stateOptions->getEntityClass()
                        : $apiClass;

                    $groups = $operation->getNormalizationContext()['groups'] ?? null;
                    $sortable = $this->sortableProperties($entityClass, $apiClass, null === $groups ? null : (array) $groups);

                    if (null !== $sortable) {
                        if ([] !== $sortable['generated']) {
                            $operation = $operation->withParameters($this->addParameters($operation->getParameters(), $sortable['generated']));
                            $changed = true;
                        }

                        if ([] === ($operation->getOrder() ?? []) && null !== ($order = $this->defaultOrderFor($sortable['all']))) {
                            $operation = $operation->withOrder($order);
                            $changed = true;
                        }
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
                description: $this->describe($property),
                filter: new SortFilter(),
                // a single direction, no `order[p][]` variant in OpenAPI
                castToArray: false,
            ));
        }

        return $parameters;
    }

    private function describe(string $property): string
    {
        if (false === $pos = strpos($property, '.')) {
            return \sprintf('Sort by `%s` (asc or desc).', $property);
        }

        return \sprintf(
            'Sort by `%s` of the related `%s` (asc or desc).',
            substr($property, $pos + 1),
            substr($property, 0, $pos),
        );
    }

    /**
     * @param list<string> $sortable
     *
     * @return array<string, string>|null
     */
    private function defaultOrderFor(array $sortable): ?array
    {
        foreach ($this->defaultOrder as $property => $direction) {
            if (\in_array((string) $property, $sortable, true)) {
                return [(string) $property => strtoupper($direction)];
            }
        }

        return null;
    }

    /**
     * @param list<string>|null $groups normalization groups of the operation
     *
     * @return array{all: list<string>, generated: list<string>}|null property
     *         paths (`<field>` or `<relation>.<label>`): `all` sortable ones
     *         (default sort candidates), `generated` those that get a
     *         parameter; null = not a Doctrine entity or switched off
     */
    private function sortableProperties(string $entityClass, string $apiClass, ?array $groups): ?array
    {
        $metadata = $this->classMetadata($entityClass);
        if (null === $metadata) {
            return null;
        }

        $autoOrder = $this->autoOrder($apiClass, $entityClass);
        if (false === $autoOrder?->enabled) {
            return null;
        }

        $options = null === $groups ? [] : ['serializer_groups' => $groups];
        $exposed = $this->exposedProperties($apiClass, $options);

        $all = [];
        foreach ($metadata->getFieldNames() as $field) {
            if ($this->isSortableField($metadata, $field) && $this->isReadable($apiClass, $field, $options, $exposed)) {
                $all[] = $field;
            }
        }

        foreach ($metadata->getAssociationNames() as $association) {
            if (!$metadata->isSingleValuedAssociation($association) || $metadata->isAssociationInverseSide($association)) {
                continue;
            }
            if (!$this->isReadable($apiClass, $association, $options, $exposed)) {
                continue;
            }
            $label = $this->labelProperty($metadata->getAssociationTargetClass($association));
            if (null !== $label) {
                $all[] = $association.'.'.$label;
            }
        }

        if (null !== $autoOrder) {
            $all = array_values(array_filter($all, static fn (string $path): bool => !$autoOrder->excludes($path)));
        }

        $explicit = $this->explicitOrderProperties(new \ReflectionClass($apiClass));
        $generated = null === $explicit
            ? []
            : array_values(array_filter($all, static fn (string $path): bool => !\in_array($path, $explicit, true)));

        return ['all' => $all, 'generated' => $generated];
    }

    private function isSortableField(ClassMetadata $metadata, string $field): bool
    {
        // embedded fields ("embed.field") and identifiers of any type are not offered
        if (str_contains($field, '.') || $metadata->isIdentifier($field)) {
            return false;
        }

        return \in_array($metadata->getTypeOfField($field), self::SORTABLE_TYPES, true);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>|null null = the name collection is not available
     */
    private function exposedProperties(string $apiClass, array $options): ?array
    {
        try {
            return array_values(iterator_to_array($this->propertyNameCollectionFactory->create($apiClass, $options), false));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string>|null    $exposed
     */
    private function isReadable(string $apiClass, string $property, array $options, ?array $exposed): bool
    {
        if (null === $exposed || !\in_array($property, $exposed, true)) {
            return false;
        }

        try {
            return false !== $this->propertyMetadataFactory->create($apiClass, $property, $options)->isReadable();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * First configured label candidate that is a sortable and readable field
     * of the target entity. Same candidate list as
     * RelationFieldSchemaDecorator, but without its "first string property"
     * fallback: sorting by an arbitrary string would look like a feature and
     * be noise.
     */
    private function labelProperty(string $targetClass): ?string
    {
        $target = $this->classMetadata($targetClass);
        if (null === $target) {
            return null;
        }

        foreach ($this->labelPropertyCandidates as $candidate) {
            if (!$target->hasField($candidate) || !$this->isSortableField($target, $candidate)) {
                continue;
            }
            try {
                if (false === $this->propertyMetadataFactory->create($targetClass, $candidate)->isReadable()) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * Merged `#[AutoOrder]` of the API class and the entity class: disabled
     * if either disables it, exclusions are combined.
     */
    private function autoOrder(string $apiClass, string $entityClass): ?AutoOrder
    {
        $found = [];
        foreach (array_unique([$apiClass, $entityClass]) as $class) {
            if (!class_exists($class)) {
                continue;
            }
            foreach ((new \ReflectionClass($class))->getAttributes(AutoOrder::class) as $attribute) {
                $found[] = $attribute->newInstance();
            }
        }

        if ([] === $found) {
            return null;
        }
        if (1 === \count($found)) {
            return $found[0];
        }

        $enabled = true;
        $exclude = [];
        foreach ($found as $autoOrder) {
            $enabled = $enabled && $autoOrder->enabled;
            $exclude = [...$exclude, ...$autoOrder->exclude];
        }

        return new AutoOrder($enabled, array_values(array_unique($exclude)));
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
        if (!class_exists($class)) {
            return null;
        }
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
