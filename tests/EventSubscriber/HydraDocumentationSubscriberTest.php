<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\EventSubscriber;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
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
 * Characterization test: pins which documented operations get `@id` /
 * `hydra:uriTemplate` from the subscriber and which are skipped today.
 */
final class HydraDocumentationSubscriberTest extends TestCase
{
    private const RESOURCE_CLASS = 'App\\Entity\\Thing';

    private function createSubscriber(): HydraDocumentationSubscriber
    {
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
     * @param array<string, mixed> $docs
     *
     * @return array<string, mixed>
     */
    private function enrich(array $docs): array
    {
        $response = new Response(json_encode($docs), 200, ['Content-Type' => 'application/ld+json']);
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/docs.jsonld'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $this->createSubscriber()->enrich($event);

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

    public function testClassLevelOperationsGetTheFirstNonCustomTemplateWithPlaceholder(): void
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
        // Only candidate is the custom `/things/{id}/approve`: skipped.
        self::assertSame(['hydra:method' => 'PATCH'], $ops[1]);
        // `/things/{id}/{._format}` is standard here (format stripped first).
        self::assertSame(['hydra:method' => 'PUT', 'hydra:uriTemplate' => '/api/admin/things/{id}/'], $ops[2]);
        // POST candidates carry no placeholder.
        self::assertSame(['hydra:method' => 'POST'], $ops[3]);
        // No candidate for the method at all.
        self::assertSame(['hydra:method' => 'DELETE'], $ops[4]);
    }

    public function testPropertyLevelOperationsGetTheFirstNonCustomTemplateWithoutPlaceholder(): void
    {
        $data = $this->enrich($this->docs([], [
            ['hydra:method' => 'POST'],
            ['hydra:method' => 'GET'],
            ['hydra:method' => 'PATCH'],
        ]));

        $ops = $data['hydra:supportedClass'][0]['hydra:supportedProperty'][0]['hydra:property']['hydra:supportedOperation'];
        // The custom `/things/scan` comes first and would match: skipped.
        self::assertSame(['hydra:method' => 'POST', '@id' => '/api/admin/things'], $ops[0]);
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/admin/things'], $ops[1]);
        self::assertSame(['hydra:method' => 'PATCH'], $ops[2]);
    }

    public function testEntrypointPropertyResolvesTheResourceByShortName(): void
    {
        $data = $this->enrich($this->docs([], [], [
            ['hydra:method' => 'GET'],
            ['hydra:method' => 'POST'],
        ]));

        $ops = $data['hydra:supportedClass'][1]['hydra:supportedProperty'][0]['hydra:property']['hydra:supportedOperation'];
        self::assertSame(['hydra:method' => 'GET', '@id' => '/api/admin/things'], $ops[0]);
        self::assertSame(['hydra:method' => 'POST', '@id' => '/api/admin/things'], $ops[1]);
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
}
