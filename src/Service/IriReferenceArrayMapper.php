<?php
// file generated with AI assistance: Claude Code - 2026-06-09 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Service;

use Dmstr\ApiPlatformUtils\Attribute\IriReferenceArray;
use ReflectionClass;
use ReflectionProperty;

/**
 * Shared lookup + conversion logic for the IriReferenceArray attribute.
 *
 * Holds a per-class reflection cache and the IRI↔UUID extraction primitives
 * used by the schema factory, the denormalizer and the Doctrine postLoad
 * listener.
 */
final class IriReferenceArrayMapper
{
    /** @var array<class-string, list<IriReferenceArrayProperty>> */
    private array $cache = [];

    /** @return list<IriReferenceArrayProperty> */
    public function getProperties(string $className): array
    {
        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        if (!class_exists($className)) {
            return $this->cache[$className] = [];
        }

        $reflection = new ReflectionClass($className);
        $props = [];

        foreach ($this->collectProperties($reflection) as $rp) {
            $attrs = $rp->getAttributes(IriReferenceArray::class);
            if ($attrs === []) {
                continue;
            }
            /** @var IriReferenceArray $attr */
            $attr = $attrs[0]->newInstance();
            $props[] = new IriReferenceArrayProperty(
                name: $rp->getName(),
                reflection: $rp,
                collection: $attr->collection,
                resourceClass: $attr->resourceClass,
                labelProperty: $attr->labelProperty,
                valueProperty: $attr->valueProperty,
            );
        }

        return $this->cache[$className] = $props;
    }

    public function hasProperties(string $className): bool
    {
        return $this->getProperties($className) !== [];
    }

    /**
     * Transforms request-body data (before denormalization) — replaces IRIs
     * with bare UUIDs on every property declared as IriReferenceArray.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function denormalizeData(string $className, array $data): array
    {
        foreach ($this->getProperties($className) as $prop) {
            if (!isset($data[$prop->name]) || !is_array($data[$prop->name])) {
                continue;
            }
            $data[$prop->name] = array_values(array_map(
                fn(mixed $v): mixed => is_string($v) ? $this->extractUuid($v) : $v,
                $data[$prop->name],
            ));
        }
        return $data;
    }

    /**
     * Transforms an entity in place (after Doctrine has loaded it) — replaces
     * UUIDs with IRIs on every property declared as IriReferenceArray.
     */
    public function normalizeEntity(object $entity): void
    {
        foreach ($this->getProperties($entity::class) as $prop) {
            $value = $prop->reflection->getValue($entity);
            if (!is_array($value)) {
                continue;
            }
            $base = rtrim($prop->collection, '/');
            $iris = array_values(array_map(
                fn(mixed $v): mixed => is_string($v) && $v !== '' && !str_starts_with($v, '/')
                    ? $base . '/' . $v
                    : $v,
                $value,
            ));
            $prop->reflection->setValue($entity, $iris);
        }
    }

    /**
     * Transforms an entity in place (before persisting) — replaces IRIs
     * with bare UUIDs on every property declared as IriReferenceArray.
     */
    public function denormalizeEntity(object $entity): void
    {
        foreach ($this->getProperties($entity::class) as $prop) {
            $value = $prop->reflection->getValue($entity);
            if (!is_array($value)) {
                continue;
            }
            $uuids = array_values(array_map(
                fn(mixed $v): mixed => is_string($v) ? $this->extractUuid($v) : $v,
                $value,
            ));
            $prop->reflection->setValue($entity, $uuids);
        }
    }

    /** Pulls the trailing UUID off an IRI; passes through bare UUIDs unchanged. */
    private function extractUuid(string $iriOrUuid): string
    {
        // Already a full canonical UUID — leave it as-is.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $iriOrUuid) === 1) {
            return $iriOrUuid;
        }
        // Strip the leading collection path: keep what's after the last `/`.
        $pos = strrpos($iriOrUuid, '/');
        if ($pos === false) {
            return $iriOrUuid;
        }
        return substr($iriOrUuid, $pos + 1);
    }

    /**
     * Iterate over the class hierarchy so private parent properties are
     * also picked up.
     *
     * @return iterable<ReflectionProperty>
     */
    private function collectProperties(ReflectionClass $class): iterable
    {
        $seen = [];
        $current = $class;
        while ($current !== false) {
            foreach ($current->getProperties() as $rp) {
                $key = $rp->getDeclaringClass()->getName() . '::' . $rp->getName();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                yield $rp;
            }
            $current = $current->getParentClass();
        }
    }
}
