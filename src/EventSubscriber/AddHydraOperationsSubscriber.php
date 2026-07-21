<?php
// file generated with AI assistance: Claude Code - 2025-11-22, updated 2026-07-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\EventSubscriber;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds hydra:operation array to JSON-LD item and collection responses
 *
 * This enriches API Platform JSON-LD responses with complete operation metadata,
 * making the API more discoverable and self-documenting.
 *
 * Item responses list the item-level operations (URI templates containing
 * {id}), collection responses list the collection-level operations (create +
 * custom collection actions).
 *
 * When filtering is enabled (default) and API Platform's ResourceAccessChecker
 * is available, operations whose `security` expression does not grant access
 * to the current token are omitted — the server advertises only what the
 * current user may actually execute (HATEOAS). Operations without a `security`
 * expression stay visible. Responses become user-dependent, so a
 * `Vary: Authorization` header is emitted whenever filtering is active.
 */
final class AddHydraOperationsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly string $apiPrefix = '/api',
        private readonly ?ResourceAccessCheckerInterface $resourceAccessChecker = null,
        private readonly bool $filterOperationsBySecurity = true,
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

        // Only process GET operations
        if ($operation->getMethod() !== 'GET') {
            return;
        }

        $uriTemplate = $operation->getUriTemplate();
        if (!$uriTemplate) {
            return;
        }
        $isItemRequest = str_contains($uriTemplate, '{id}');

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
        if (!is_array($data)) {
            return;
        }

        if ($isItemRequest) {
            if (!isset($data['@id']) || !isset($data['@type'])) {
                return;
            }
        } else {
            // Collection responses are typed hydra:Collection (possibly among
            // other types); skip anything else (e.g. custom GET endpoints).
            $types = (array) ($data['@type'] ?? []);
            if (!in_array('hydra:Collection', $types, true)) {
                return;
            }
        }

        // Get resource class
        $resourceClass = $operation->getClass();
        if (!$resourceClass) {
            return;
        }

        try {
            $resourceId = null;
            $subject = null;
            if ($isItemRequest) {
                // Get the resource ID from the @id field
                $resourceId = $data['id'] ?? null;
                if (!$resourceId) {
                    // Try to extract from @id
                    $atId = $data['@id'] ?? '';
                    if (preg_match('/\/([^\/]+)$/', $atId, $matches)) {
                        $resourceId = $matches[1];
                    }
                }

                // The loaded entity — API Platform stores the controller
                // result in the `data` request attribute. Used as `object`
                // when evaluating operation security expressions.
                $requestData = $request->attributes->get('data');
                if (is_object($requestData)) {
                    $subject = $requestData;
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

                    // Item responses advertise item operations, collection
                    // responses advertise collection operations.
                    $opUriTemplate = $op->getUriTemplate();
                    if (!$opUriTemplate || str_contains($opUriTemplate, '{id}') !== $isItemRequest) {
                        continue;
                    }

                    if (!$this->isOperationGranted($op, $subject)) {
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

            // With security filtering active the advertised operations depend
            // on the current token — mark the response as per-credential for
            // any shared HTTP cache. Also emitted when all operations were
            // filtered out (an empty result is user-dependent information too).
            if ($this->isFilteringActive()) {
                $vary = $response->getVary();
                if (!in_array('Authorization', $vary, true)) {
                    $response->setVary(array_merge($vary, ['Authorization']));
                }
            }
        } catch (\Exception $e) {
            // Silently fail - don't break the API
        }
    }

    private function isFilteringActive(): bool
    {
        return $this->filterOperationsBySecurity && null !== $this->resourceAccessChecker;
    }

    /**
     * An operation is advertised when filtering is inactive, when it carries
     * no security expression (default: allowed), or when the expression
     * grants access for the current token. Evaluation errors fail closed
     * (operation hidden) — the actual request would fail anyway.
     */
    private function isOperationGranted(HttpOperation $op, ?object $subject): bool
    {
        if (!$this->isFilteringActive()) {
            return true;
        }

        $security = $op->getSecurity();
        if (null === $security || '' === (string) $security) {
            return true;
        }

        $resourceClass = $op->getClass();
        if (!$resourceClass) {
            return true;
        }

        try {
            // `object` is always defined (null on collections / when the
            // loaded entity is not resolvable) so expressions referencing it
            // evaluate instead of raising an undefined-variable error.
            return $this->resourceAccessChecker->isGranted(
                $resourceClass,
                (string) $security,
                ['object' => $subject]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function generateTitle(HttpOperation $operation): string
    {
        $method = $operation->getMethod() ?? 'GET';
        $name = $operation->getName() ?? '';
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
