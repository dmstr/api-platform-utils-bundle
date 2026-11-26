<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\ApiPlatformUtils\Metadata\CustomOperationHydraFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins that the factory marks exactly the operations carrying
 * `extraProperties.custom_action: true`, at any path depth, and what it
 * writes into their `hydraContext`.
 */
final class CustomOperationHydraFactoryTest extends TestCase
{
    private const RESOURCE_CLASS = 'App\\Entity\\Thing';

    /**
     * Keys of `$operations` must equal the operation names: API Platform's
     * Operations collection re-keys every named operation by its name.
     *
     * @param array<string, HttpOperation> $operations
     *
     * @return array<string, HttpOperation>
     */
    private function decorate(array $operations, ?string $routePrefix = '/admin', string $apiPrefix = '/api'): array
    {
        $resource = (new ApiResource(shortName: 'Thing', class: self::RESOURCE_CLASS))
            ->withOperations(new Operations($operations));
        if (null !== $routePrefix) {
            $resource = $resource->withRoutePrefix($routePrefix);
        }

        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(
            new ResourceMetadataCollection(self::RESOURCE_CLASS, [$resource])
        );

        $collection = (new CustomOperationHydraFactory($decorated, $apiPrefix))->create(self::RESOURCE_CLASS);

        $out = [];
        foreach ($collection[0]->getOperations() as $name => $operation) {
            $out[$name] = $operation;
        }

        return $out;
    }

    private const MARKED = ['custom_action' => true];

    /**
     * @param array<string, mixed> $extraProperties
     */
    private static function operation(string $method, string $path, array $extraProperties, string $name = 'op'): HttpOperation
    {
        if ('POST' === $method) {
            return new Post(uriTemplate: $path, name: $name, class: self::RESOURCE_CLASS, extraProperties: $extraProperties);
        }

        return str_contains($path, '{id}')
            ? new Get(uriTemplate: $path, name: $name, class: self::RESOURCE_CLASS, extraProperties: $extraProperties)
            : new GetCollection(uriTemplate: $path, name: $name, class: self::RESOURCE_CLASS, extraProperties: $extraProperties);
    }

    /**
     * @return iterable<string, array{string, string, bool, array<string, string>}>
     */
    public static function pathMatrix(): iterable
    {
        $carriers = [
            '/a' => ['@id' => '/api/admin/a'],
            '/a/{id}' => ['hydra:uriTemplate' => '/api/admin/a/{id}'],
            '/a/b' => ['@id' => '/api/admin/a/b'],
            '/a/b/{id}' => ['hydra:uriTemplate' => '/api/admin/a/b/{id}'],
            '/a/b/verb' => ['@id' => '/api/admin/a/b/verb'],
        ];
        foreach ($carriers as $path => $carrier) {
            foreach (['GET', 'POST'] as $method) {
                yield $method.' '.$path.' marked' => [$method, $path, true, $carrier];
                yield $method.' '.$path.' unmarked' => [$method, $path, false, $carrier];
            }
        }
    }

    /**
     * @param array<string, string> $carrier
     */
    #[DataProvider('pathMatrix')]
    public function testMarkerAloneDecidesAtAnyPathDepth(string $method, string $path, bool $marked, array $carrier): void
    {
        $result = $this->decorate(['op' => self::operation($method, $path, $marked ? self::MARKED : [])]);

        if (!$marked) {
            // API Platform's own @type, title and expects/returns stay.
            self::assertNull($result['op']->getHydraContext());

            return;
        }

        $context = $result['op']->getHydraContext();
        self::assertSame([
            '@type' => ['hydra:Operation', 'schema:Action'],
            'hydra:title' => 'op',
            'hydra:expects' => 'owl:Nothing',
            'hydra:returns' => 'owl:Nothing',
        ] + $carrier, $context);
        self::assertCount(1, array_intersect(['@id', 'hydra:uriTemplate'], array_keys($context)));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonMarkingValues(): iterable
    {
        yield 'false' => [false];
        yield 'int 1' => [1];
        yield 'string true' => ['true'];
        yield 'string 1' => ['1'];
    }

    #[DataProvider('nonMarkingValues')]
    public function testOnlyBooleanTrueMarks(mixed $value): void
    {
        $result = $this->decorate(['op' => self::operation('POST', '/things/{id}/approve', ['custom_action' => $value])]);

        self::assertNull($result['op']->getHydraContext());
    }

    public function testOperationWithoutUriTemplateIsLeftUntouchedUnlessMarked(): void
    {
        $result = $this->decorate([
            'plain' => new GetCollection(name: 'plain', class: self::RESOURCE_CLASS),
            'marked' => new GetCollection(name: 'marked', class: self::RESOURCE_CLASS, extraProperties: self::MARKED),
        ]);

        self::assertNull($result['plain']->getHydraContext());
        // Marked but without a URL: nothing to carry.
        self::assertSame([
            '@type' => ['hydra:Operation', 'schema:Action'],
            'hydra:title' => 'marked',
            'hydra:expects' => 'owl:Nothing',
            'hydra:returns' => 'owl:Nothing',
        ], $result['marked']->getHydraContext());
    }

    public function testMarkedNotExposedIsLeftUntouched(): void
    {
        $result = $this->decorate([
            'stub' => new NotExposed(uriTemplate: '/things/{id}{._format}', name: 'stub', class: self::RESOURCE_CLASS, extraProperties: self::MARKED),
        ]);

        self::assertNull($result['stub']->getHydraContext());
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function carrierTemplates(): iterable
    {
        yield 'collection with format' => ['/things{._format}', ['@id' => '/api/admin/things']];
        yield 'item with format' => ['/things/{id}{._format}', ['hydra:uriTemplate' => '/api/admin/things/{id}']];
        yield 'collection action with format' => ['/things/scan{._format}', ['@id' => '/api/admin/things/scan']];
        yield 'item action' => ['/things/{id}/approve', ['hydra:uriTemplate' => '/api/admin/things/{id}/approve']];
        yield 'format as own segment' => ['/things/{id}/{._format}', ['hydra:uriTemplate' => '/api/admin/things/{id}/']];
        yield 'trailing slash' => ['/things/scan/', ['@id' => '/api/admin/things/scan/']];
    }

    /**
     * `{._format}` is stripped before the carrier is chosen, as the
     * subscriber does: it is not a placeholder a client fills.
     *
     * @param array<string, string> $expectedCarrier
     */
    #[DataProvider('carrierTemplates')]
    public function testCarrierOfMarkedOperationIgnoresTheFormatPlaceholder(string $template, array $expectedCarrier): void
    {
        $result = $this->decorate(['op' => self::operation('POST', $template, self::MARKED)]);

        $context = $result['op']->getHydraContext();
        self::assertSame($expectedCarrier, array_intersect_key($context, ['@id' => true, 'hydra:uriTemplate' => true]));
    }

    public function testFullContextOfCustomOperation(): void
    {
        $operation = new Patch(
            uriTemplate: '/things/{id}/approve',
            name: 'thing_approve',
            class: self::RESOURCE_CLASS,
            description: 'Approve a thing',
            input: ['class' => 'App\\Dto\\ApproveInput'],
            output: ['class' => 'ShortOutput'],
            extraProperties: self::MARKED,
        );

        $result = $this->decorate(['thing_approve' => $operation], routePrefix: null, apiPrefix: '/api/');

        self::assertSame([
            '@type' => ['hydra:Operation', 'schema:Action'],
            'hydra:title' => 'thing_approve',
            'hydra:expects' => 'ApproveInput',
            'hydra:returns' => 'ShortOutput',
            'hydra:description' => 'Approve a thing',
            'hydra:uriTemplate' => '/api/things/{id}/approve',
        ], $result['thing_approve']->getHydraContext());
    }

    public function testUnnamedOperationGetsNoGeneratedTitle(): void
    {
        // In the bundle's wiring API Platform has named every operation
        // before this factory runs; without a name no title is invented.
        $operation = new Post(uriTemplate: '/things/{id}/approve', class: self::RESOURCE_CLASS, extraProperties: self::MARKED);

        $result = $this->decorate(['op' => $operation]);

        self::assertArrayNotHasKey('hydra:title', $result['op']->getHydraContext());
    }

    public function testAuthorSetHydraContextWins(): void
    {
        $operation = new Post(
            uriTemplate: '/things/scan',
            name: 'thing_scan',
            class: self::RESOURCE_CLASS,
            description: 'From the operation',
            hydraContext: [
                '@type' => 'schema:SearchAction',
                'hydra:title' => 'Scan',
                'hydra:expects' => 'ScanInput',
                'hydra:description' => 'From the author',
                'hydra:uriTemplate' => '/custom/{x}',
            ],
            extraProperties: self::MARKED,
        );

        $result = $this->decorate(['thing_scan' => $operation]);

        // The author's keys win and the factory adds nothing it already
        // has; an author-set carrier is the only carrier.
        self::assertSame([
            '@type' => 'schema:SearchAction',
            'hydra:title' => 'Scan',
            'hydra:expects' => 'ScanInput',
            'hydra:description' => 'From the author',
            'hydra:uriTemplate' => '/custom/{x}',
            'hydra:returns' => 'owl:Nothing',
        ], $result['thing_scan']->getHydraContext());
    }

    public function testAuthorSetIdIsTheOnlyCarrier(): void
    {
        $operation = new Post(
            uriTemplate: '/things/{id}/approve',
            name: 'thing_approve',
            class: self::RESOURCE_CLASS,
            hydraContext: ['@id' => '/given'],
            extraProperties: self::MARKED,
        );

        $context = $this->decorate(['thing_approve' => $operation])['thing_approve']->getHydraContext();

        self::assertSame('/given', $context['@id']);
        self::assertArrayNotHasKey('hydra:uriTemplate', $context);
    }

    public function testOnlyMarkedOperationsOfAMixedResourceAreMarked(): void
    {
        $result = $this->decorate([
            'get' => new Get(uriTemplate: '/things/{id}{._format}', name: 'get', class: self::RESOURCE_CLASS),
            'list' => new GetCollection(uriTemplate: '/things{._format}', name: 'list', class: self::RESOURCE_CLASS),
            'parts' => new GetCollection(uriTemplate: '/things/{id}/parts', name: 'parts', class: self::RESOURCE_CLASS),
            'scan' => new Post(uriTemplate: '/things', name: 'scan', class: self::RESOURCE_CLASS, extraProperties: self::MARKED),
        ]);

        self::assertSame(['get', 'list', 'parts', 'scan'], array_keys($result));
        self::assertNull($result['get']->getHydraContext());
        self::assertNull($result['list']->getHydraContext());
        self::assertNull($result['parts']->getHydraContext());
        self::assertSame('schema:Action', $result['scan']->getHydraContext()['@type'][1]);
        self::assertSame('/api/admin/things', $result['scan']->getHydraContext()['@id']);
    }
}
