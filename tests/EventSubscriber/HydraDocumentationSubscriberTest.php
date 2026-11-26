<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\EventSubscriber;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Dmstr\ApiPlatformUtils\EventSubscriber\HydraDocumentationSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Pins which documented operations get `@id` / `hydra:uriTemplate` from the
 * subscriber: every documented operation is paired with exactly the
 * metadata operation API Platform built it from, and custom actions are
 * left to the factory.
 */
final class HydraDocumentationSubscriberTest extends TestCase
{
    private const RESOURCE_CLASS = 'App\\Entity\\Thing';

    /**
     * @param list<ApiResource>|null $resources null: the default `Thing` resource
     */
    private function createSubscriber(?array $resources = null): HydraDocumentationSubscriber
    {
        if (null !== $resources) {
            return $this->createSubscriberFor($resources);
        }

        // Custom operations come first per method, so a match proves that
        // the custom candidate was skipped rather than never reached.
        $operations = new Operations([
            'scan' => new Post(uriTemplate: '/things/scan', class: self::RESOURCE_CLASS),
            'post' => new Post(uriTemplate: '/things{._format}', class: self::RESOURCE_CLASS),
            'list' => new GetCollection(uriTemplate: '/things{._format}', class: self::RESOURCE_CLASS),
            'get' => new Get(uriTemplate: '/things/{id}{._format}', class: self::RESOURCE_CLASS),
            'approve' => new Patch(uriTemplate: '/things/{id}/approve', class: self::RESOURCE_CLASS),
            // Standard for the subscriber, which strips `{._format}` before
            // classifying; the factory classifies the same template as custom.
            'put' => new Put(uriTemplate: '/things/{id}/{._format}', class: self::RESOURCE_CLASS),
        ]);

        $resource = (new ApiResource(shortName: 'Thing', class: self::RESOURCE_CLASS))
            ->withRoutePrefix('/admin')
            ->withOperations($operations);

        $names = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $names->method('create')->willReturn(new ResourceNameCollection([self::RESOURCE_CLASS]));

        $metadata = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadata->method('create')->willReturn(
            new ResourceMetadataCollection(self::RESOURCE_CLASS, [$resource])
        );

        return new HydraDocumentationSubscriber($names, $metadata, '/api');
    }

    /**
     * One resource name per distinct resource class, in first-seen order;
     * several ApiResources of one class form one metadata collection.
     *
     * @param list<ApiResource> $resources
     */
    private function createSubscriberFor(array $resources): HydraDocumentationSubscriber
    {
        $byClass = [];
        foreach ($resources as $resource) {
            $byClass[(string) $resource->getClass()][] = $resource;
        }

        $names = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $names->method('create')->willReturn(new ResourceNameCollection(array_keys($byClass)));

        $metadata = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadata->method('create')->willReturnCallback(
            static fn (string $class): ResourceMetadataCollection => new ResourceMetadataCollection($class, $byClass[$class] ?? [])
        );

        return new HydraDocumentationSubscriber($names, $metadata, '/api');
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\HttpOperation> $operations
     */
    private static function resource(string $shortName, array $operations, string $class = self::RESOURCE_CLASS, ?string $routePrefix = null, ?bool $hideHydraOperation = null): ApiResource
    {
        $resource = (new ApiResource(shortName: $shortName, class: $class, hideHydraOperation: $hideHydraOperation))
            ->withOperations(new Operations($operations));

        return null === $routePrefix ? $resource : $resource->withRoutePrefix($routePrefix);
    }

    /**
     * @param array<string, mixed>   $docs
     * @param list<ApiResource>|null $resources null: the default `Thing` resource
     *
     * @return array<string, mixed>
     */
    private function enrich(array $docs, ?array $resources = null): array
    {
        $response = new Response(json_encode($docs), 200, ['Content-Type' => 'application/ld+json']);
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/docs.jsonld'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $this->createSubscriber($resources)->enrich($event);

        return json_decode((string) $response->getContent(), true);
    }

    /**
     * @param list<array<string, mixed>> $classOperations
     * @param list<array<string, mixed>> $propertyOperations
     * @param list<array<string, mixed>> $entrypointOperations
     *
     * @return array<string, mixed>
     */
    private function docs(array $classOperations, array $propertyOperations = [], array $entrypointOperations = []): array
    {
        return ['hydra:supportedClass' => [
            [
                'hydra:title' => 'Thing',
                'hydra:supportedOperation' => $classOperations,
                'hydra:supportedProperty' => [
                    ['hydra:property' => ['@id' => '#Thing/owner', 'hydra:supportedOperation' => $propertyOperations]],
                ],
            ],
            [
                'hydra:title' => 'Entrypoint',
                'hydra:supportedProperty' => [
                    ['hydra:property' => ['@id' => '#Entrypoint/thing', 'hydra:supportedOperation' => $entrypointOperations]],
                ],
            ],
        ]];
    }

    public function testClassLevelOperationsArePairedInOrderWithTheItemOperations(): void
    {
        $data = $this->enrich($this->docs([
            ['hydra:method' => 'GET'],
            ['hydra:method' => 'PATCH'],
            ['hydra:method' => 'PUT'],
            ['hydra:method' => 'POST'],
            ['hydra:method' => 'DELETE'],
        ]));

        $ops = $data['hydra:supportedClass'][0]['hydra:supportedOperation'];
        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/admin/things/{id}'], $ops[0]);
        // Paired with the custom `/things/{id}/approve`: left to the factory.
        self::assertSame(['hydra:method' => 'PATCH'], $ops[1]);
        // `/things/{id}/{._format}` is standard here (format stripped first).
        self::assertSame(['hydra:method' => 'PUT', 'hydra:uriTemplate' => '/api/admin/things/{id}/'], $ops[2]);
        // The class level documents three operations only: no counterpart.
        self::assertSame(['hydra:method' => 'POST'], $ops[3]);
        self::assertSame(['hydra:method' => 'DELETE'], $ops[4]);
    }

    public function testOperationsUnderOrdinaryPropertiesHaveNoCounterpartAndAreLeftAlone(): void
    {
        // API Platform documents operations under Entrypoint properties
        // only; nothing here came from a metadata operation.
        $data = $this->enrich($this->docs([], [
            ['hydra:method' => 'POST'],
            ['hydra:method' => 'GET'],
            ['hydra:method' => 'PATCH'],
        ]));

        $ops = $data['hydra:supportedClass'][0]['hydra:supportedProperty'][0]['hydra:property']['hydra:supportedOperation'];
        self::assertSame(['hydra:method' => 'POST'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET'], $ops[1]);
        self::assertSame(['hydra:method' => 'PATCH'], $ops[2]);
    }

    public function testEntrypointPropertyResolvesTheResourceByShortName(): void
    {
        // As API Platform documents `Thing`: every POST and the collection,
        // in the order of the operations.
        $data = $this->enrich($this->docs([], [], [
            ['hydra:method' => 'POST'],
            ['hydra:method' => 'POST'],
            ['hydra:method' => 'GET'],
        ]));

        $ops = $data['hydra:supportedClass'][1]['hydra:supportedProperty'][0]['hydra:property']['hydra:supportedOperation'];
        // Paired with the custom `/things/scan`: left to the factory.
        self::assertSame(['hydra:method' => 'POST'], $ops[0]);
        self::assertSame(['hydra:method' => 'POST', '@id' => '/api/admin/things'], $ops[1]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/admin/things'], $ops[2]);
    }

    public function testExistingCarrierAndMissingMethodAreLeftAlone(): void
    {
        $data = $this->enrich($this->docs([
            ['hydra:method' => 'POST', '@id' => '/given'],
            ['hydra:method' => 'POST', 'hydra:uriTemplate' => '/given/{x}'],
            ['@type' => 'hydra:Operation'],
        ]));

        $ops = $data['hydra:supportedClass'][0]['hydra:supportedOperation'];
        self::assertSame(['hydra:method' => 'POST', '@id' => '/given'], $ops[0]);
        self::assertSame(['hydra:method' => 'POST', 'hydra:uriTemplate' => '/given/{x}'], $ops[1]);
        self::assertSame(['@type' => 'hydra:Operation'], $ops[2]);
    }

    /**
     * @param list<array<string, mixed>>                             $classes    supported classes
     * @param list<array{0: string, 1: list<array<string, mixed>>}> $entrypoint [property @id suffix, operations]
     *
     * @return array<string, mixed>
     */
    private static function documentation(array $classes, array $entrypoint = []): array
    {
        $properties = [];
        foreach ($entrypoint as [$name, $operations]) {
            $properties[] = ['hydra:property' => ['@id' => '#Entrypoint/'.$name, 'hydra:supportedOperation' => $operations]];
        }
        $classes[] = ['hydra:title' => 'Entrypoint', 'hydra:supportedProperty' => $properties];

        return ['hydra:supportedClass' => $classes];
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array<string, mixed>
     */
    private static function supportedClass(string $title, array $operations): array
    {
        return ['hydra:title' => $title, 'hydra:supportedOperation' => $operations];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private static function entrypointOperations(array $data, int $property): array
    {
        $entrypoint = $data['hydra:supportedClass'][\count($data['hydra:supportedClass']) - 1];

        return $entrypoint['hydra:supportedProperty'][$property]['hydra:property']['hydra:supportedOperation'];
    }

    public function testTwoItemOperationsOfOneResourceGetTheirOwnTemplates(): void
    {
        $resources = [self::resource('Gadget', [
            'get' => new Get(uriTemplate: '/gadgets/{id}{._format}', class: self::RESOURCE_CLASS),
            'preview' => new Get(uriTemplate: '/gadget-previews/{id}{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Gadget', [['hydra:method' => 'GET'], ['hydra:method' => 'GET']]),
        ]), $resources);

        $ops = $data['hydra:supportedClass'][0]['hydra:supportedOperation'];
        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gadgets/{id}'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gadget-previews/{id}'], $ops[1]);
    }

    public function testTwoCollectionOperationsOfOneResourceGetTheirOwnIds(): void
    {
        $resources = [self::resource('Gadget', [
            'list' => new GetCollection(uriTemplate: '/gadgets{._format}', class: self::RESOURCE_CLASS),
            'archive' => new GetCollection(uriTemplate: '/gadget-archive{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([], [
            ['gadget', [['hydra:method' => 'GET'], ['hydra:method' => 'GET']]],
        ]), $resources);

        $ops = self::entrypointOperations($data, 0);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadgets'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadget-archive'], $ops[1]);
    }

    public function testPostWithPlaceholderOnTheEntrypointLevelGetsItsUriTemplate(): void
    {
        $resources = [self::resource('Gadget', [
            'list' => new GetCollection(uriTemplate: '/gadgets{._format}', class: self::RESOURCE_CLASS),
            'post' => new Post(uriTemplate: '/gadgets{._format}', class: self::RESOURCE_CLASS),
            'attach' => new Post(uriTemplate: '/gadgets/{id}{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([], [
            ['gadget', [['hydra:method' => 'GET'], ['hydra:method' => 'POST'], ['hydra:method' => 'POST']]],
        ]), $resources);

        $ops = self::entrypointOperations($data, 0);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadgets'], $ops[0]);
        self::assertSame(['hydra:method' => 'POST', '@id' => '/api/gadgets'], $ops[1]);
        self::assertSame(['hydra:method' => 'POST', 'hydra:uriTemplate' => '/api/gadgets/{id}'], $ops[2]);
    }

    public function testOperationWithHideHydraOperationDoesNotShiftThePairing(): void
    {
        $resources = [self::resource('Gadget', [
            'get' => new Get(uriTemplate: '/gadgets/{id}{._format}', class: self::RESOURCE_CLASS),
            'hidden' => new Get(uriTemplate: '/hidden-gadgets/{id}{._format}', class: self::RESOURCE_CLASS, hideHydraOperation: true),
            'preview' => new Get(uriTemplate: '/gadget-previews/{id}{._format}', class: self::RESOURCE_CLASS),
            'list' => new GetCollection(uriTemplate: '/gadgets{._format}', class: self::RESOURCE_CLASS),
            'hiddenList' => new GetCollection(uriTemplate: '/hidden-gadgets{._format}', class: self::RESOURCE_CLASS, hideHydraOperation: true),
            'archive' => new GetCollection(uriTemplate: '/gadget-archive{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Gadget', [['hydra:method' => 'GET'], ['hydra:method' => 'GET']]),
        ], [
            ['gadget', [['hydra:method' => 'GET'], ['hydra:method' => 'GET']]],
        ]), $resources);

        $ops = $data['hydra:supportedClass'][0]['hydra:supportedOperation'];
        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gadgets/{id}'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gadget-previews/{id}'], $ops[1]);

        $ops = self::entrypointOperations($data, 0);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadgets'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadget-archive'], $ops[1]);
    }

    public function testTwoApiResourcesOnOneClassAreDocumentedSeparatelyWithTheirRoutePrefix(): void
    {
        // Same shortName twice: API Platform documents two classes with the
        // same title and two Entrypoint properties with the same @id.
        $resources = [
            self::resource('Sprocket', [
                'get' => new Get(uriTemplate: '/sprockets/{id}{._format}', class: self::RESOURCE_CLASS),
                'list' => new GetCollection(uriTemplate: '/sprockets{._format}', class: self::RESOURCE_CLASS),
            ], routePrefix: '/v1'),
            self::resource('Sprocket', [
                'get' => new Get(uriTemplate: '/sprockets/{id}{._format}', class: self::RESOURCE_CLASS),
                'list' => new GetCollection(uriTemplate: '/sprockets{._format}', class: self::RESOURCE_CLASS),
            ], routePrefix: '/v2'),
        ];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Sprocket', [['hydra:method' => 'GET']]),
            self::supportedClass('Sprocket', [['hydra:method' => 'GET']]),
        ], [
            ['sprocket', [['hydra:method' => 'GET']]],
            ['sprocket', [['hydra:method' => 'GET']]],
        ]), $resources);

        self::assertSame('/api/v1/sprockets/{id}', $data['hydra:supportedClass'][0]['hydra:supportedOperation'][0]['hydra:uriTemplate']);
        self::assertSame('/api/v2/sprockets/{id}', $data['hydra:supportedClass'][1]['hydra:supportedOperation'][0]['hydra:uriTemplate']);
        self::assertSame('/api/v1/sprockets', self::entrypointOperations($data, 0)[0]['@id']);
        self::assertSame('/api/v2/sprockets', self::entrypointOperations($data, 1)[0]['@id']);
    }

    public function testApiResourceWithHideHydraOperationIsNotDocumentedAndDoesNotShiftThePairing(): void
    {
        $resources = [
            self::resource('Sprocket', [
                'get' => new Get(uriTemplate: '/sprockets/{id}{._format}', class: self::RESOURCE_CLASS),
                'list' => new GetCollection(uriTemplate: '/sprockets{._format}', class: self::RESOURCE_CLASS),
            ], routePrefix: '/internal', hideHydraOperation: true),
            self::resource('Sprocket', [
                'get' => new Get(uriTemplate: '/sprockets/{id}{._format}', class: self::RESOURCE_CLASS),
                'list' => new GetCollection(uriTemplate: '/sprockets{._format}', class: self::RESOURCE_CLASS),
            ], routePrefix: '/v1'),
        ];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Sprocket', [['hydra:method' => 'GET']]),
        ], [
            ['sprocket', [['hydra:method' => 'GET']]],
        ]), $resources);

        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/v1/sprockets/{id}'], $data['hydra:supportedClass'][0]['hydra:supportedOperation'][0]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/v1/sprockets'], self::entrypointOperations($data, 0)[0]);
    }

    public function testRoutePrefixOfTheOwningResourceIsPrepended(): void
    {
        // Two resource classes share a shortName: each operation keeps the
        // route prefix of the resource it belongs to.
        $resources = [
            self::resource('Sprocket', [
                'get' => new Get(uriTemplate: '/sprockets/{id}{._format}', class: 'App\\Entity\\A'),
            ], class: 'App\\Entity\\A', routePrefix: '/a'),
            self::resource('Sprocket', [
                'get' => new Get(uriTemplate: '/sprockets/{id}{._format}', class: 'App\\Entity\\B'),
            ], class: 'App\\Entity\\B'),
        ];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Sprocket', [['hydra:method' => 'GET']]),
            self::supportedClass('Sprocket', [['hydra:method' => 'GET']]),
        ]), $resources);

        self::assertSame('/api/a/sprockets/{id}', $data['hydra:supportedClass'][0]['hydra:supportedOperation'][0]['hydra:uriTemplate']);
        self::assertSame('/api/sprockets/{id}', $data['hydra:supportedClass'][1]['hydra:supportedOperation'][0]['hydra:uriTemplate']);
    }

    public function testFormatPlaceholderIsStrippedBeforeChoosingTheCarrier(): void
    {
        $resources = [self::resource('Gadget', [
            'get' => new Get(uriTemplate: '/gadgets/{id}{._format}', class: self::RESOURCE_CLASS),
            'list' => new GetCollection(uriTemplate: '/gadgets{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Gadget', [['hydra:method' => 'GET']]),
        ], [
            ['gadget', [['hydra:method' => 'GET']]],
        ]), $resources);

        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gadgets/{id}'], $data['hydra:supportedClass'][0]['hydra:supportedOperation'][0]);
        // No placeholder left: `@id`, not `hydra:uriTemplate`.
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadgets'], self::entrypointOperations($data, 0)[0]);
    }

    public function testCustomActionIsLeftToTheFactoryAndDoesNotShiftThePairing(): void
    {
        // `/gadgets/{id}/summary` is a custom action: its carrier is the
        // factory's job, whether the factory wrote one (second resource
        // operation) or not (unit test without factory, first documented
        // GET after the item).
        $resources = [self::resource('Gadget', [
            'get' => new Get(uriTemplate: '/gadgets/{id}{._format}', class: self::RESOURCE_CLASS),
            'summary' => new Get(uriTemplate: '/gadgets/{id}/summary', class: self::RESOURCE_CLASS),
            'preview' => new Get(uriTemplate: '/gadget-previews/{id}{._format}', class: self::RESOURCE_CLASS),
            'list' => new GetCollection(uriTemplate: '/gadgets{._format}', class: self::RESOURCE_CLASS),
            'recent' => new GetCollection(uriTemplate: '/gadgets/recent', class: self::RESOURCE_CLASS),
            'archive' => new GetCollection(uriTemplate: '/gadget-archive{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Gadget', [['hydra:method' => 'GET'], ['hydra:method' => 'GET'], ['hydra:method' => 'GET']]),
        ], [
            ['gadget', [
                ['hydra:method' => 'GET'],
                ['hydra:method' => 'GET', '@id' => '/api/gadgets/recent'],
                ['hydra:method' => 'GET'],
            ]],
        ]), $resources);

        $ops = $data['hydra:supportedClass'][0]['hydra:supportedOperation'];
        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gadgets/{id}'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET'], $ops[1]);
        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gadget-previews/{id}'], $ops[2]);

        $ops = self::entrypointOperations($data, 0);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadgets'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadgets/recent'], $ops[1]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gadget-archive'], $ops[2]);
    }

    public function testNotExposedOperationIsDocumentedAndGetsItsOwnTemplate(): void
    {
        // API Platform's DocumentationNormalizer does not filter NotExposed:
        // the stub is documented as a class-level GET, so it gets a carrier.
        $resources = [self::resource('Gizmo', [
            'list' => new GetCollection(uriTemplate: '/gizmos{._format}', class: self::RESOURCE_CLASS),
            'notExposed' => new NotExposed(uriTemplate: '/gizmos/{id}{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Gizmo', [['hydra:method' => 'GET']]),
        ], [
            ['gizmo', [['hydra:method' => 'GET']]],
        ]), $resources);

        self::assertSame(['hydra:method' => 'GET', 'hydra:uriTemplate' => '/api/gizmos/{id}'], $data['hydra:supportedClass'][0]['hydra:supportedOperation'][0]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/gizmos'], self::entrypointOperations($data, 0)[0]);
    }

    public function testMethodMismatchWithThePairedOperationLeavesTheOperationAlone(): void
    {
        $resources = [self::resource('Gadget', [
            'get' => new Get(uriTemplate: '/gadgets/{id}{._format}', class: self::RESOURCE_CLASS),
        ])];

        $data = $this->enrich(self::documentation([
            self::supportedClass('Gadget', [['hydra:method' => 'DELETE']]),
        ]), $resources);

        self::assertSame(['hydra:method' => 'DELETE'], $data['hydra:supportedClass'][0]['hydra:supportedOperation'][0]);
    }
}
