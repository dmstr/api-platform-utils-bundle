<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:39:06 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Kernel;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Requests the real Hydra documentation and checks that every documented
 * operation carries exactly its own URL: class-level operations under
 * `hydra:supportedClass[].hydra:supportedOperation`, collection and POST
 * operations under `#Entrypoint` properties.
 */
final class HydraUrlCarrierTest extends KernelTestCase
{
    use HydraDocumentationTrait;

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $documentation = null;

    /**
     * @return array<string, mixed>
     */
    private static function documentation(): array
    {
        return self::$documentation ??= self::fetchHydraDocumentation();
    }

    /**
     * Class-level operations of every supported class with this title, in
     * document order.
     *
     * @return list<list<string>>
     */
    private static function classOperations(string $title): array
    {
        $out = [];
        foreach (self::documentation()['hydra:supportedClass'] as $class) {
            if ($title === ($class['hydra:title'] ?? null)) {
                $out[] = self::describe($class['hydra:supportedOperation'] ?? []);
            }
        }

        return $out;
    }

    /**
     * Operations of every Entrypoint property with this @id, in document
     * order.
     *
     * @return list<list<string>>
     */
    private static function entrypointOperations(string $propertyId): array
    {
        $out = [];
        foreach (self::entrypointProperties() as $property) {
            if ($propertyId === ($property['hydra:property']['@id'] ?? null)) {
                $out[] = self::describe($property['hydra:property']['hydra:supportedOperation'] ?? []);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function entrypointProperties(): array
    {
        $classes = self::supportedClassesByTitle(self::documentation());

        return $classes['Entrypoint']['hydra:supportedProperty'] ?? [];
    }

    /**
     * One line per operation: method, carrier key(s) and value(s).
     *
     * @param list<array<string, mixed>> $operations
     *
     * @return list<string>
     */
    private static function describe(array $operations): array
    {
        $out = [];
        foreach ($operations as $operation) {
            $line = (string) ($operation['hydra:method'] ?? '?');
            foreach (['@id', 'hydra:uriTemplate'] as $key) {
                if (isset($operation[$key])) {
                    $line .= ' '.$key.' '.$operation[$key];
                }
            }
            $out[] = $line;
        }

        return $out;
    }

    public function testEveryDocumentedOperationHasExactlyOneUrlCarrier(): void
    {
        $lists = [];
        foreach (self::documentation()['hydra:supportedClass'] as $class) {
            if ('Entrypoint' === ($class['hydra:title'] ?? null)) {
                // The Entrypoint's own operation is the index, not a resource operation.
                continue;
            }
            $lists[(string) $class['hydra:title']][] = $class['hydra:supportedOperation'] ?? [];
        }
        foreach (self::entrypointProperties() as $property) {
            $lists[(string) $property['hydra:property']['@id']][] = $property['hydra:property']['hydra:supportedOperation'] ?? [];
        }

        $checked = 0;
        foreach ($lists as $owner => $operationLists) {
            foreach ($operationLists as $operations) {
                foreach ($operations as $operation) {
                    $carriers = array_intersect(['@id', 'hydra:uriTemplate'], array_keys($operation));
                    self::assertCount(1, $carriers, $owner.': '.json_encode($operation));
                    ++$checked;
                }
            }
        }
        self::assertGreaterThan(0, $checked);
    }

    public function testItemOperationsOfOneResourceCarryTheirOwnTemplates(): void
    {
        self::assertSame([[
            'GET hydra:uriTemplate /api/gadgets/{id}',
            // hideHydraOperation: `/hidden-gadgets/{id}` is not documented.
            'GET hydra:uriTemplate /api/gadget-previews/{id}',
            // No custom_action marker: an ordinary item operation at a
            // deeper path, with its own template from the subscriber.
            'GET hydra:uriTemplate /api/gadgets/{id}/summary',
        ]], self::classOperations('Gadget'));
    }

    public function testCollectionAndPostOperationsOnTheEntrypointCarryTheirOwnUrls(): void
    {
        self::assertSame([[
            'GET @id /api/gadgets',
            'GET @id /api/gadget-archive',
            'POST @id /api/gadgets',
            'POST hydra:uriTemplate /api/gadget-copies/{id}',
        ]], self::entrypointOperations('#Entrypoint/gadget'));
    }

    public function testTwoApiResourcesOnOneClassKeepTheirOwnRoutePrefix(): void
    {
        self::assertSame([
            ['GET hydra:uriTemplate /api/v1/sprockets/{id}'],
            ['GET hydra:uriTemplate /api/v2/sprockets/{id}'],
        ], self::classOperations('Sprocket'));
        self::assertSame([
            ['GET @id /api/v1/sprockets'],
            ['GET @id /api/v2/sprockets'],
        ], self::entrypointOperations('#Entrypoint/sprocket'));
    }

    public function testNotExposedItemOperationIsDocumentedAndCarriesItsOwnTemplate(): void
    {
        self::assertSame([['GET hydra:uriTemplate /api/gizmos/{id}']], self::classOperations('Gizmo'));
        self::assertSame([['GET @id /api/gizmos']], self::entrypointOperations('#Entrypoint/gizmo'));
    }
}
