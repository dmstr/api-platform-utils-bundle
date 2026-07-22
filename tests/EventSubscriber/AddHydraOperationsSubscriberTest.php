<?php
// file generated with AI assistance: Claude Code - 2026-07-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\EventSubscriber;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Dmstr\ApiPlatformUtils\EventSubscriber\AddHydraOperationsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AddHydraOperationsSubscriberTest extends TestCase
{
    private const ADMIN_EXPRESSION = "is_granted('ROLE_ADMIN')";

    /**
     * Item operations: GET carries no security expression, the write
     * operations require ROLE_ADMIN.
     */
    private function createItemGetOperation(): Get
    {
        return new Get(uriTemplate: '/things/{id}', shortName: 'Thing', class: DummyThing::class);
    }

    private function createCollectionGetOperation(): GetCollection
    {
        return new GetCollection(uriTemplate: '/things', shortName: 'Thing', class: DummyThing::class);
    }

    private function createMetadataFactory(): ResourceMetadataCollectionFactoryInterface
    {
        $operations = new Operations([
            'thing_get' => $this->createItemGetOperation(),
            'thing_get_collection' => $this->createCollectionGetOperation(),
            'thing_post' => new Post(uriTemplate: '/things', shortName: 'Thing', class: DummyThing::class, security: self::ADMIN_EXPRESSION),
            'thing_scan' => new Post(uriTemplate: '/things/scan', shortName: 'Thing', class: DummyThing::class, security: self::ADMIN_EXPRESSION, description: 'Scan things'),
            'thing_put' => new Put(uriTemplate: '/things/{id}', shortName: 'Thing', class: DummyThing::class, security: self::ADMIN_EXPRESSION),
            'thing_patch' => new Patch(uriTemplate: '/things/{id}', shortName: 'Thing', class: DummyThing::class, security: self::ADMIN_EXPRESSION),
            'thing_delete' => new Delete(uriTemplate: '/things/{id}', shortName: 'Thing', class: DummyThing::class, security: self::ADMIN_EXPRESSION),
        ]);

        $resource = (new ApiResource(shortName: 'Thing', class: DummyThing::class))
            ->withRoutePrefix('/admin')
            ->withOperations($operations);

        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory->method('create')->willReturn(
            new ResourceMetadataCollection(DummyThing::class, [$resource])
        );

        return $factory;
    }

    private function createSubscriber(
        ?ResourceAccessCheckerInterface $checker,
        bool $filterOperationsBySecurity = true,
    ): AddHydraOperationsSubscriber {
        return new AddHydraOperationsSubscriber(
            $this->createMetadataFactory(),
            '/api',
            $checker,
            $filterOperationsBySecurity,
        );
    }

    private function dispatch(
        AddHydraOperationsSubscriber $subscriber,
        HttpOperation $currentOperation,
        array $responseData,
        ?object $entity = null,
    ): Response {
        $request = new Request();
        $request->attributes->set('_api_operation', $currentOperation);
        if (null !== $entity) {
            $request->attributes->set('data', $entity);
        }

        $response = new Response(
            json_encode($responseData),
            200,
            ['Content-Type' => 'application/ld+json; charset=utf-8']
        );

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->addHydraOperations($event);

        return $response;
    }

    private function dispatchItem(AddHydraOperationsSubscriber $subscriber, ?object $entity = null): Response
    {
        return $this->dispatch($subscriber, $this->createItemGetOperation(), [
            '@id' => '/api/admin/things/abc12345',
            '@type' => 'Thing',
            'id' => 'abc12345',
        ], $entity);
    }

    private function dispatchCollection(AddHydraOperationsSubscriber $subscriber): Response
    {
        return $this->dispatch($subscriber, $this->createCollectionGetOperation(), [
            '@id' => '/api/admin/things',
            '@type' => 'hydra:Collection',
            'hydra:member' => [],
        ]);
    }

    /** @return string[] */
    private function methodsFrom(Response $response): array
    {
        $data = json_decode($response->getContent(), true);

        return array_map(
            static fn (array $op): string => $op['hydra:method'],
            $data['hydra:operation'] ?? []
        );
    }

    public function testItemResponseOmitsDeniedWriteOperations(): void
    {
        $subscriber = $this->createSubscriber(new SpyAccessChecker(false));
        $response = $this->dispatchItem($subscriber);

        $this->assertSame(['GET'], $this->methodsFrom($response));
    }

    public function testItemResponseKeepsAllOperationsForGrantedUser(): void
    {
        $subscriber = $this->createSubscriber(new SpyAccessChecker(true));
        $response = $this->dispatchItem($subscriber);

        $methods = $this->methodsFrom($response);
        sort($methods);
        $this->assertSame(['DELETE', 'GET', 'PATCH', 'PUT'], $methods);
    }

    public function testCollectionResponseOmitsDeniedPostOperations(): void
    {
        $subscriber = $this->createSubscriber(new SpyAccessChecker(false));
        $response = $this->dispatchCollection($subscriber);

        $this->assertSame(['GET'], $this->methodsFrom($response));
    }

    public function testCollectionResponseKeepsAllOperationsForGrantedUser(): void
    {
        $subscriber = $this->createSubscriber(new SpyAccessChecker(true));
        $response = $this->dispatchCollection($subscriber);

        $methods = $this->methodsFrom($response);
        sort($methods);
        // GetCollection + create POST + custom scan POST
        $this->assertSame(['GET', 'POST', 'POST'], $methods);

        $data = json_decode($response->getContent(), true);
        $ids = array_column($data['hydra:operation'], '@id');
        $this->assertContains('/api/admin/things/scan', $ids);
        $this->assertContains('/api/admin/things', $ids);
    }

    public function testCollectionOperationsAreCheckedWithoutObjectContext(): void
    {
        $checker = new SpyAccessChecker(true);
        $subscriber = $this->createSubscriber($checker);
        $this->dispatchCollection($subscriber);

        $this->assertNotEmpty($checker->calls);
        foreach ($checker->calls as [$resourceClass, $expression, $extraVariables]) {
            $this->assertSame(DummyThing::class, $resourceClass);
            $this->assertSame(self::ADMIN_EXPRESSION, $expression);
            $this->assertArrayHasKey('object', $extraVariables);
            $this->assertNull($extraVariables['object']);
        }
    }

    public function testItemOperationsAreCheckedWithLoadedEntityAsObject(): void
    {
        $checker = new SpyAccessChecker(true);
        $subscriber = $this->createSubscriber($checker);
        $entity = new DummyThing();
        $this->dispatchItem($subscriber, $entity);

        $this->assertNotEmpty($checker->calls);
        foreach ($checker->calls as [, , $extraVariables]) {
            $this->assertSame($entity, $extraVariables['object']);
        }
    }

    public function testOperationsWithoutSecurityExpressionSkipTheChecker(): void
    {
        $checker = new SpyAccessChecker(false);
        $subscriber = $this->createSubscriber($checker);
        $this->dispatchItem($subscriber);

        // Only the three secured write operations are evaluated; the GET
        // without a security expression must never hit the checker.
        $this->assertCount(3, $checker->calls);
    }

    public function testFilteringCanBeDisabledByConfiguration(): void
    {
        $checker = new SpyAccessChecker(false);
        $subscriber = $this->createSubscriber($checker, filterOperationsBySecurity: false);
        $response = $this->dispatchItem($subscriber);

        $methods = $this->methodsFrom($response);
        sort($methods);
        $this->assertSame(['DELETE', 'GET', 'PATCH', 'PUT'], $methods);
        $this->assertSame([], $checker->calls);
    }

    public function testMissingAccessCheckerDisablesFiltering(): void
    {
        $subscriber = $this->createSubscriber(null);
        $response = $this->dispatchItem($subscriber);

        $methods = $this->methodsFrom($response);
        sort($methods);
        $this->assertSame(['DELETE', 'GET', 'PATCH', 'PUT'], $methods);
    }

    public function testVaryAuthorizationIsSetWhenFilteringIsActive(): void
    {
        $subscriber = $this->createSubscriber(new SpyAccessChecker(false));
        $response = $this->dispatchItem($subscriber);

        $this->assertContains('Authorization', $response->getVary());
    }

    public function testVaryAuthorizationIsNotSetWhenFilteringIsInactive(): void
    {
        $subscriber = $this->createSubscriber(null);
        $response = $this->dispatchItem($subscriber);

        $this->assertNotContains('Authorization', $response->getVary());
    }

    public function testItemIdIsSubstitutedIntoOperationUrls(): void
    {
        $subscriber = $this->createSubscriber(new SpyAccessChecker(true));
        $response = $this->dispatchItem($subscriber);

        $data = json_decode($response->getContent(), true);
        foreach ($data['hydra:operation'] as $op) {
            $this->assertSame('/api/admin/things/abc12345', $op['@id']);
        }
    }
}

final class DummyThing
{
}

final class SpyAccessChecker implements ResourceAccessCheckerInterface
{
    /** @var array<int, array{0: string, 1: string, 2: array}> */
    public array $calls = [];

    public function __construct(private readonly bool $granted)
    {
    }

    public function isGranted(string $resourceClass, string $expression, array $extraVariables = []): bool
    {
        $this->calls[] = [$resourceClass, $expression, $extraVariables];

        return $this->granted;
    }
}
