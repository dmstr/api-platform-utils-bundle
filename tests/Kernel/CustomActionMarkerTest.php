<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:45:11 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Kernel;

use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Dmstr\ApiPlatformUtils\Tests\Fixtures\CustomAction\Thingamajig;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Requests the real `/api/docs.jsonld` and checks that a custom action is
 * exactly an operation with `extraProperties.custom_action: true`, with
 * API Platform's own inheritance (operation over `#[ApiResource]` over
 * `api_platform.defaults.extra_properties`), at any path depth, and that
 * every operation carries exactly one URL.
 *
 * Fixtures: `Doohickey` (no marker), `Contraption` (marker on every
 * operation), `Thingamajig` (marker on the resource, `false` on one
 * operation, NotExposed stub), `Whatsit` (`false` on the resource).
 */
final class CustomActionMarkerTest extends KernelTestCase
{
    use HydraDocumentationTrait;

    private const ACTION = ['hydra:Operation', 'schema:Action'];
    private const FIND = ['hydra:Operation', 'schema:FindAction'];
    private const CREATE = ['hydra:Operation', 'schema:CreateAction'];

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $documentation = [];

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    /**
     * @return array<string, mixed>
     */
    private static function documentation(string $environment = 'test'): array
    {
        if (!isset(self::$documentation[$environment])) {
            $kernel = static::bootKernel(['environment' => $environment]);
            $response = $kernel->handle(Request::create('/api/docs.jsonld', 'GET', server: [
                'HTTP_ACCEPT' => 'application/ld+json',
            ]));
            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            self::$documentation[$environment] = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        }

        return self::$documentation[$environment];
    }

    /**
     * Every documented operation of a resource: class level and its
     * Entrypoint property.
     *
     * @return list<array<string, mixed>>
     */
    private static function operationsOf(string $shortName, string $environment = 'test'): array
    {
        $classes = self::supportedClassesByTitle(self::documentation($environment));
        $operations = $classes[$shortName]['hydra:supportedOperation'] ?? [];
        foreach ($classes['Entrypoint']['hydra:supportedProperty'] ?? [] as $property) {
            if ('#Entrypoint/'.lcfirst($shortName) === ($property['hydra:property']['@id'] ?? null)) {
                $operations = [...$operations, ...($property['hydra:property']['hydra:supportedOperation'] ?? [])];
            }
        }

        return $operations;
    }

    /**
     * The one documented operation with this method and URL; fails unless
     * it exists exactly once and carries exactly one URL carrier.
     *
     * @return array<string, mixed>
     */
    private static function operation(string $shortName, string $method, string $url, string $environment = 'test'): array
    {
        $matches = array_values(array_filter(
            self::operationsOf($shortName, $environment),
            static fn (array $op): bool => $method === ($op['hydra:method'] ?? null)
                && \in_array($url, [$op['@id'] ?? null, $op['hydra:uriTemplate'] ?? null], true),
        ));
        self::assertCount(1, $matches, $shortName.' '.$method.' '.$url);

        $op = $matches[0];
        $expectedKey = str_contains($url, '{') ? 'hydra:uriTemplate' : '@id';
        self::assertSame([$expectedKey], array_values(array_intersect(['@id', 'hydra:uriTemplate'], array_keys($op))), json_encode($op));

        return $op;
    }

    public function testDocumentationIsServedAsJsonLd(): void
    {
        $response = self::requestHydraDocumentation();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/ld+json', (string) $response->headers->get('Content-Type'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pathMatrix(): iterable
    {
        foreach (['', '/{id}', '/b', '/b/{id}', '/b/verb'] as $suffix) {
            foreach (['GET', 'POST'] as $method) {
                yield $method.' /a'.$suffix => [$method, $suffix];
            }
        }
    }

    #[DataProvider('pathMatrix')]
    public function testUnmarkedOperationIsOrdinaryAtAnyDepth(string $method, string $suffix): void
    {
        $op = self::operation('Doohickey', $method, '/api/doohickey'.$suffix);
        $isCollection = 'GET' === $method && !str_contains($suffix, '{');

        self::assertSame('POST' === $method ? self::CREATE : self::FIND, $op['@type']);
        self::assertSame(strtolower($method).'Doohickey'.($isCollection ? 'Collection' : ''), $op['hydra:title']);
        self::assertSame($isCollection ? 'hydra:Collection' : 'Doohickey', $op['hydra:returns']);
        if ('POST' === $method) {
            self::assertSame('Doohickey', $op['hydra:expects']);
        }
    }

    #[DataProvider('pathMatrix')]
    public function testMarkedOperationIsACustomActionAtAnyDepth(string $method, string $suffix): void
    {
        $op = self::operation('Contraption', $method, '/api/contraption'.$suffix);
        $name = 'contraption_'.strtolower($method).'_a'.str_replace(['/', '{', '}'], ['_', '', ''], $suffix);

        self::assertSame(self::ACTION, $op['@type']);
        self::assertSame($name, $op['hydra:title']);
        self::assertSame('owl:Nothing', $op['hydra:expects']);
        self::assertSame('owl:Nothing', $op['hydra:returns']);
    }

    public function testMarkedOperationsWithGeneratedTemplatesCarryUrlsWithoutFormat(): void
    {
        self::assertSame(self::ACTION, self::operation('Contraption', 'GET', '/api/contraptions/{id}')['@type']);
        self::assertSame(self::ACTION, self::operation('Contraption', 'GET', '/api/contraptions')['@type']);
    }

    public function testAuthorSetHydraContextWins(): void
    {
        $op = self::operation('Contraption', 'POST', '/api/contraption/run');

        self::assertSame('schema:SearchAction', $op['@type']);
        self::assertSame('Run it', $op['hydra:title']);
    }

    public function testUnmarkedSubPathsGetTheirOwnUrls(): void
    {
        // Two item GETs and two collections at different depths, and a POST
        // on a sub-path: each carries its own URL from the subscriber.
        self::assertSame(self::FIND, self::operation('Doohickey', 'GET', '/api/doohickey/{id}')['@type']);
        self::assertSame(self::FIND, self::operation('Doohickey', 'GET', '/api/doohickey/{id}/b')['@type']);
        self::assertSame(self::FIND, self::operation('Doohickey', 'GET', '/api/doohickey')['@type']);
        self::assertSame(self::FIND, self::operation('Doohickey', 'GET', '/api/doohickey/b')['@type']);
        self::assertSame(self::CREATE, self::operation('Doohickey', 'POST', '/api/doohickey/{id}/b')['@type']);
    }

    public function testNoSchemaActionWithoutMarker(): void
    {
        foreach (['Doohickey', 'Widget', 'Gadget', 'Sprocket', 'Gizmo', 'Whatsit'] as $shortName) {
            foreach (self::operationsOf($shortName) as $op) {
                self::assertNotContains('schema:Action', (array) ($op['@type'] ?? []), $shortName.': '.json_encode($op));
            }
        }
    }

    public function testResourceMarkerIsInheritedAndFalseOnTheOperationExempts(): void
    {
        $list = self::operation('Thingamajig', 'GET', '/api/thingamajigs');
        self::assertSame(self::ACTION, $list['@type']);
        self::assertSame('thingamajig_list', $list['hydra:title']);

        $run = self::operation('Thingamajig', 'POST', '/api/thingamajigs/{id}/run');
        self::assertSame(self::ACTION, $run['@type']);
        self::assertSame('owl:Nothing', $run['hydra:expects']);
        self::assertSame('owl:Nothing', $run['hydra:returns']);

        $create = self::operation('Thingamajig', 'POST', '/api/thingamajigs');
        self::assertSame(self::CREATE, $create['@type']);
        self::assertSame('postThingamajig', $create['hydra:title']);
        self::assertSame('Thingamajig', $create['hydra:expects']);
        self::assertSame('Thingamajig', $create['hydra:returns']);

        self::assertSame('Thingamajigs', self::supportedClassesByTitle(self::documentation())['Thingamajig']['rdfs:label']);
    }

    public function testNotExposedStubInheritsTheMarkerButIsNoCustomAction(): void
    {
        // The stub really carries the inherited marker ...
        $stubs = [];
        $factory = static::getContainer()->get('api_platform.metadata.resource.metadata_collection_factory');
        self::assertInstanceOf(ResourceMetadataCollectionFactoryInterface::class, $factory);
        foreach ($factory->create(Thingamajig::class) as $resource) {
            foreach ($resource->getOperations() as $operation) {
                if ($operation instanceof NotExposed) {
                    $stubs[] = $operation;
                }
            }
        }
        self::assertCount(1, $stubs);
        self::assertTrue($stubs[0]->getExtraProperties()['custom_action'] ?? null);
        self::assertNull($stubs[0]->getHydraContext());

        // ... and is documented as an ordinary GET with its own template,
        // no phantom custom-action entry.
        $op = self::operation('Thingamajig', 'GET', '/api/thingamajigs/{id}');
        self::assertSame(self::FIND, $op['@type']);
        self::assertSame('getThingamajig', $op['hydra:title']);
        self::assertCount(3 + 1, self::operationsOf('Thingamajig'));
        $actions = array_filter(self::operationsOf('Thingamajig'), static fn (array $o): bool => self::ACTION === ($o['@type'] ?? null));
        self::assertCount(2, $actions);
    }

    public function testGlobalDefaultMarksOperationsWithoutOwnValue(): void
    {
        $env = TestKernel::ENV_MARKER_DEFAULTS;

        // Inherited from api_platform.defaults.extra_properties, with the
        // carrier stripped of `{._format}`.
        $item = self::operation('Widget', 'GET', '/api/widgets/{id}', $env);
        self::assertSame(self::ACTION, $item['@type']);
        self::assertSame(self::ACTION, self::operation('Widget', 'GET', '/api/widgets', $env)['@type']);
        self::assertSame(self::ACTION, self::operation('Doohickey', 'POST', '/api/doohickey', $env)['@type']);

        // `false` on the resource beats the default.
        self::assertSame(self::FIND, self::operation('Whatsit', 'GET', '/api/whatsits/{id}', $env)['@type']);
        self::assertSame(self::FIND, self::operation('Whatsit', 'GET', '/api/whatsits', $env)['@type']);

        // `false` on the operation beats the default (and the resource).
        self::assertSame(self::CREATE, self::operation('Thingamajig', 'POST', '/api/thingamajigs', $env)['@type']);

        // NotExposed stubs stay ordinary.
        self::assertSame(self::FIND, self::operation('Gizmo', 'GET', '/api/gizmos/{id}', $env)['@type']);
        self::assertSame(self::FIND, self::operation('Thingamajig', 'GET', '/api/thingamajigs/{id}', $env)['@type']);
    }

    public function testDefaultEnvironmentHasNoGlobalMarker(): void
    {
        self::assertSame(self::FIND, self::operation('Widget', 'GET', '/api/widgets/{id}')['@type']);
        self::assertSame(self::FIND, self::operation('Widget', 'GET', '/api/widgets')['@type']);
    }
}
