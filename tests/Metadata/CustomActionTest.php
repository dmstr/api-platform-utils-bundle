<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Metadata;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use Dmstr\ApiPlatformUtils\Metadata\CustomAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the one rule: a custom action is an HTTP operation (not NotExposed)
 * whose `extraProperties.custom_action` is the boolean `true`. The URI
 * template plays no part.
 */
final class CustomActionTest extends TestCase
{
    public function testMarkerKeyIsCustomAction(): void
    {
        self::assertSame('custom_action', CustomAction::MARKER);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function markerValues(): iterable
    {
        yield 'true' => [['custom_action' => true], true];
        yield 'missing' => [[], false];
        yield 'false' => [['custom_action' => false], false];
        yield 'null' => [['custom_action' => null], false];
        yield 'int 1' => [['custom_action' => 1], false];
        yield 'string true' => [['custom_action' => 'true'], false];
        yield 'string 1' => [['custom_action' => '1'], false];
        yield 'other key true' => [['customAction' => true], false];
    }

    /**
     * @param array<string, mixed> $extraProperties
     */
    #[DataProvider('markerValues')]
    public function testOnlyBooleanTrueMarks(array $extraProperties, bool $expected): void
    {
        $operation = new Post(uriTemplate: '/things/{id}/approve', extraProperties: $extraProperties);

        self::assertSame($expected, CustomAction::isCustomAction($operation));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function pathMatrix(): iterable
    {
        foreach (['/a', '/a/{id}', '/a/b', '/a/b/{id}', '/a/b/verb', '/a{._format}', '/a/{id}{._format}', ''] as $path) {
            foreach (['GET', 'POST'] as $method) {
                yield $method.' '.$path.' marked' => [$method, $path, true];
                yield $method.' '.$path.' unmarked' => [$method, $path, false];
            }
        }
    }

    #[DataProvider('pathMatrix')]
    public function testPathDepthDoesNotMatter(string $method, string $path, bool $marked): void
    {
        $operation = self::operation($method, $path, $marked ? ['custom_action' => true] : []);

        self::assertSame($marked, CustomAction::isCustomAction($operation));
    }

    public function testOperationWithoutUriTemplateFollowsTheMarker(): void
    {
        self::assertTrue(CustomAction::isCustomAction(new Post(extraProperties: ['custom_action' => true])));
        self::assertFalse(CustomAction::isCustomAction(new Post()));
    }

    public function testNotExposedIsNeverACustomAction(): void
    {
        // The stub API Platform adds inherits a resource-level marker.
        self::assertFalse(CustomAction::isCustomAction(new NotExposed(extraProperties: ['custom_action' => true])));
        self::assertFalse(CustomAction::isCustomAction(new NotExposed(uriTemplate: '/things/{id}/x', extraProperties: ['custom_action' => true])));
        self::assertFalse(CustomAction::isCustomAction(new NotExposed()));
    }

    public function testNonHttpOperationIsNotCustom(): void
    {
        self::assertFalse(CustomAction::isCustomAction(new Query(name: 'things/scan/now', extraProperties: ['custom_action' => true])));
        self::assertFalse(CustomAction::isCustomAction(new Query(name: 'things/scan/now')));
    }

    /**
     * Unit-level mirror of API Platform 4.x's flat merge (operation over
     * `#[ApiResource]` over `api_platform.defaults.extra_properties`, see
     * `OperationDefaultsTrait`): the merged operation is what gets
     * classified. The kernel test proves the real merge.
     *
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function mergeLevels(): iterable
    {
        // defaults, resource, operation, expected
        yield 'defaults only' => [['custom_action' => true], [], [], true];
        yield 'resource only' => [[], ['custom_action' => true], [], true];
        yield 'operation only' => [[], [], ['custom_action' => true], true];
        yield 'operation false beats resource' => [[], ['custom_action' => true], ['custom_action' => false], false];
        yield 'operation false beats defaults' => [['custom_action' => true], [], ['custom_action' => false], false];
        yield 'resource false beats defaults' => [['custom_action' => true], ['custom_action' => false], [], false];
        yield 'operation true beats resource false' => [[], ['custom_action' => false], ['custom_action' => true], true];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $resource
     * @param array<string, mixed> $operation
     */
    #[DataProvider('mergeLevels')]
    public function testFlatMergeOfTheThreeLevels(array $defaults, array $resource, array $operation, bool $expected): void
    {
        $merged = array_merge($defaults, array_merge($resource, $operation));

        self::assertSame($expected, CustomAction::isCustomAction(new Post(uriTemplate: '/things', extraProperties: $merged)));
    }

    /**
     * @param array<string, mixed> $extraProperties
     */
    private static function operation(string $method, string $path, array $extraProperties): HttpOperation
    {
        if ('POST' === $method) {
            return new Post(uriTemplate: $path, extraProperties: $extraProperties);
        }

        return str_contains($path, '{id}')
            ? new Get(uriTemplate: $path, extraProperties: $extraProperties)
            : new GetCollection(uriTemplate: $path, extraProperties: $extraProperties);
    }
}
