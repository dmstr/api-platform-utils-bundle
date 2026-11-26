<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\ApiPlatformUtils\Metadata\CustomOperationHydraFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test: pins which URI shapes the factory classifies as
 * custom operations today and what it writes into their `hydraContext`.
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function standardTemplates(): iterable
    {
        yield 'collection' => ['/things'];
        yield 'collection with format' => ['/things{._format}'];
        yield 'collection with slashes' => ['/things/'];
        yield 'item' => ['/things/{id}'];
        yield 'item with format' => ['/things/{id}{._format}'];
        yield 'empty' => [''];
    }

    #[DataProvider('standardTemplates')]
    public function testStandardTemplateIsLeftUntouched(string $template): void
    {
        $operation = new Post(uriTemplate: $template, name: 'op', class: self::RESOURCE_CLASS);

        $result = $this->decorate(['op' => $operation]);

        self::assertNull($result['op']->getHydraContext());
    }

    public function testOperationWithoutUriTemplateIsLeftUntouched(): void
    {
        $result = $this->decorate(['op' => new GetCollection(name: 'op', class: self::RESOURCE_CLASS)]);

        self::assertNull($result['op']->getHydraContext());
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function customTemplates(): iterable
    {
        yield 'collection action' => ['/things/scan', ['@id' => '/api/admin/things/scan']];
        yield 'collection action with format' => ['/things/scan{._format}', ['hydra:uriTemplate' => '/api/admin/things/scan{._format}']];
        yield 'item action' => ['/things/{id}/approve', ['hydra:uriTemplate' => '/api/admin/things/{id}/approve']];
        yield 'deep path' => ['/a/b/c', ['@id' => '/api/admin/a/b/c']];
        yield 'trailing slash' => ['/things/scan/', ['@id' => '/api/admin/things/scan/']];
        // The factory does NOT strip `{._format}` before classifying: a
        // format placeholder standing as its own segment adds a segment.
        yield 'item with format as own segment' => ['/things/{id}/{._format}', ['hydra:uriTemplate' => '/api/admin/things/{id}/{._format}']];
    }

    /**
     * @param array<string, string> $expectedCarrier
     */
    #[DataProvider('customTemplates')]
    public function testCustomTemplateIsMarked(string $template, array $expectedCarrier): void
    {
        $operation = new Post(uriTemplate: $template, name: 'thing_action', class: self::RESOURCE_CLASS);

        $result = $this->decorate(['thing_action' => $operation]);

        self::assertSame([
            '@type' => ['hydra:Operation', 'schema:Action'],
            'hydra:title' => 'thing_action',
            'hydra:expects' => 'owl:Nothing',
            'hydra:returns' => 'owl:Nothing',
        ] + $expectedCarrier, $result['thing_action']->getHydraContext());
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

    public function testTitleFallsBackToMethodAndLiteralSegments(): void
    {
        $operation = new Post(uriTemplate: '/things/{id}/approve', class: self::RESOURCE_CLASS);

        $result = $this->decorate(['op' => $operation]);

        self::assertSame('post_things_approve', $result['op']->getHydraContext()['hydra:title']);
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
        );

        $result = $this->decorate(['thing_scan' => $operation]);

        // The author's keys win; the factory adds nothing it already has,
        // but still adds `@id` next to an author-set `hydra:uriTemplate`.
        self::assertSame([
            '@type' => 'schema:SearchAction',
            'hydra:title' => 'Scan',
            'hydra:expects' => 'ScanInput',
            'hydra:description' => 'From the author',
            'hydra:uriTemplate' => '/custom/{x}',
            'hydra:returns' => 'owl:Nothing',
            '@id' => '/api/admin/things/scan',
        ], $result['thing_scan']->getHydraContext());
    }

    public function testOnlyCustomOperationsOfAMixedResourceAreMarked(): void
    {
        $result = $this->decorate([
            'get' => new Get(uriTemplate: '/things/{id}{._format}', name: 'get', class: self::RESOURCE_CLASS),
            'list' => new GetCollection(uriTemplate: '/things{._format}', name: 'list', class: self::RESOURCE_CLASS),
            'scan' => new Post(uriTemplate: '/things/scan', name: 'scan', class: self::RESOURCE_CLASS),
        ]);

        self::assertSame(['get', 'list', 'scan'], array_keys($result));
        self::assertNull($result['get']->getHydraContext());
        self::assertNull($result['list']->getHydraContext());
        self::assertSame('schema:Action', $result['scan']->getHydraContext()['@type'][1]);
    }
}
