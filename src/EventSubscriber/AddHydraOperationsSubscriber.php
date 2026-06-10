<?php
// file generated with AI assistance: Claude Code - 2025-11-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\EventSubscriber;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds hydra:operation array to JSON-LD item responses
 *
 * This enriches API Platform JSON-LD responses with complete operation metadata,
 * making the API more discoverable and self-documenting.
 */
final class AddHydraOperationsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly string $apiPrefix = '/api'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['addHydraOperations', -10],
        ];
    }

    public function addHydraOperations(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only process API Platform operations
        $operation = $request->attributes->get('_api_operation');
        if (!$operation instanceof HttpOperation) {
            return;
        }

        // Only process GET item operations (not collections)
        if ($operation->getMethod() !== 'GET') {
            return;
        }

        $uriTemplate = $operation->getUriTemplate();
        if (!$uriTemplate || !str_contains($uriTemplate, '{id}')) {
            return;
        }

        // Only process JSON-LD content
        $contentType = $response->headers->get('Content-Type');
        if (!$contentType || !str_contains($contentType, 'application/ld+json')) {
            return;
        }

        $content = $response->getContent();
        if (!$content) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['@id']) || !isset($data['@type'])) {
            return;
        }

        // Get resource class
        $resourceClass = $operation->getClass();
        if (!$resourceClass) {
            return;
        }

        try {
            // Get the resource ID from the @id field
            $resourceId = $data['id'] ?? null;
            if (!$resourceId) {
                // Try to extract from @id
                $atId = $data['@id'] ?? '';
                if (preg_match('/\/([^\/]+)$/', $atId, $matches)) {
                    $resourceId = $matches[1];
                }
            }

            // Get all operations for this resource
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
            $operations = [];

            foreach ($resourceMetadata as $resource) {
                // Get the route prefix from the resource (e.g., "/admin")
                $routePrefix = $resource->getRoutePrefix() ?? '';

                foreach ($resource->getOperations() as $operationName => $op) {
                    if (!$op instanceof HttpOperation) {
                        continue;
                    }

                    // Only include item operations (not collection operations)
                    $opUriTemplate = $op->getUriTemplate();
                    if (!$opUriTemplate || !str_contains($opUriTemplate, '{id}')) {
                        continue;
                    }

                    // Build the full operation URL
                    $operationUrl = $opUriTemplate;

                    // Prepend route prefix if needed and not already present
                    if ($routePrefix && !str_contains($operationUrl, $routePrefix)) {
                        $operationUrl = $routePrefix . $operationUrl;
                    }

                    // Prepend configured API prefix if not already present
                    if (!str_starts_with($operationUrl, $this->apiPrefix)) {
                        $operationUrl = $this->apiPrefix . $operationUrl;
                    }

                    // Replace {id} with actual resource ID
                    if ($resourceId) {
                        // Cast to string — non-UUID PKs (int, etc.) are valid identifiers too.
                        $operationUrl = str_replace('{id}', (string) $resourceId, $operationUrl);
                    }

                    // Remove {._format} placeholder if present
                    $operationUrl = preg_replace('/\{\._format\}/', '', $operationUrl);

                    $operationData = [
                        '@id' => $operationUrl,
                        '@type' => 'hydra:Operation',
                        'hydra:method' => $op->getMethod() ?? 'GET',
                    ];

                    // Add title/description
                    $description = $op->getDescription();
                    if ($description) {
                        $operationData['hydra:title'] = $description;
                    } else {
                        $operationData['hydra:title'] = $this->generateTitle($op);
                    }

                    // Add expects/returns
                    $shortName = $op->getShortName();
                    $method = $op->getMethod();

                    if (in_array($method, ['PUT', 'PATCH', 'POST'])) {
                        $operationData['hydra:expects'] = $shortName;
                    }

                    if ($method === 'DELETE') {
                        $operationData['hydra:returns'] = 'owl:Nothing';
                    } else {
                        $operationData['hydra:returns'] = $shortName;
                    }

                    $operations[] = $operationData;
                }
            }

            if (!empty($operations)) {
                $data['hydra:operation'] = $operations;
                $response->setContent(json_encode($data));
            }
        } catch (\Exception $e) {
            // Silently fail - don't break the API
        }
    }

    private function generateTitle(HttpOperation $operation): string
    {
        $method = $operation->getMethod() ?? 'GET';
        $name = $operation->getName();
        $uriTemplate = $operation->getUriTemplate() ?? '';

        // Detect if this is a standard CRUD operation or a custom operation
        // Custom operations have specific names (like "api_configuration_health")
        // Standard operations have names like "_api_/admin/api_configurations/{id}{._format}_get"
        $isStandardOperation = str_contains($name, '{id}') || str_contains($name, '/');

        if (!$isStandardOperation && $name) {
            // For custom operations (like api_configuration_health), use a better title
            // Remove resource prefix if present (e.g., "api_configuration_health" -> "health")
            $shortName = $operation->getShortName();
            $prefix = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $shortName)) . '_';
            $customName = str_replace($prefix, '', $name);

            // Convert to Title Case
            $title = preg_replace('/[_-]/', ' ', $customName);
            $title = ucwords($title);
            return $title;
        }

        // Standard CRUD operations
        return match($method) {
            'GET' => 'Retrieves a ' . $operation->getShortName() . ' resource',
            'PUT' => 'Replaces the ' . $operation->getShortName() . ' resource',
            'PATCH' => 'Updates the ' . $operation->getShortName() . ' resource',
            'DELETE' => 'Deletes the ' . $operation->getShortName() . ' resource',
            'POST' => 'Creates a ' . $operation->getShortName() . ' resource',
            default => $method . ' ' . $operation->getShortName(),
        };
    }
}
