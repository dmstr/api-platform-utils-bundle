<?php
// file generated with AI assistance: Claude Code - 2026-05-12 12:55:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\EventSubscriber;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Dmstr\ApiPlatformUtils\Metadata\CustomAction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Patches the JSON-LD documentation response (`/api/docs.jsonld`) so every
 * operation carries a URL identifier and uses prefixed Hydra property names.
 *
 * Two transformations are applied to every entry under
 * `hydra:supportedOperation` (both at class level and inside
 * `supportedProperty[].hydra:property.hydra:supportedOperation`):
 *
 * 1. Key renames — API Platform's own DocumentationNormalizer hardcodes
 *    `expects` / `returns` without the `hydra:` prefix even when
 *    `hydra_prefix: true`. We rename them to `hydra:expects` / `hydra:returns`
 *    so the response is internally consistent.
 *
 * 2. URL carrier — every operation must answer "where do I call this?".
 *    For operations missing both `@id` and `hydra:uriTemplate` we take the
 *    metadata operation API Platform documented at exactly that position
 *    and emit its own URL as either `@id` (no placeholder) or
 *    `hydra:uriTemplate` (placeholder present). The positions follow
 *    API Platform's Hydra DocumentationNormalizer: one supportedClass and
 *    one Entrypoint property per ApiResource (resources with
 *    `hideHydraOperation` left out), operations in the order of
 *    `getOperations()` without those hidden by `hideHydraOperation`, POST
 *    and collection operations on the Entrypoint property, all others on
 *    the class. Custom actions (`extraProperties.custom_action: true`, see
 *    {@see CustomAction}) are skipped but keep their position: their
 *    carrier comes from
 *    {@see \Dmstr\ApiPlatformUtils\Metadata\CustomOperationHydraFactory}.
 *    Every other operation gets its own URL, whatever its path depth.
 *
 * 3. Display labels — every supportedClass whose resource declares
 *    `extraProperties: ['label' => '…']` gets that value as `rdfs:label`.
 *    UIs use it as the human-readable (later translatable) menu/heading
 *    text while `hydra:title` (= shortName) remains the technical key.
 *
 * Runs at low priority so the Hydra documentation is fully serialized before
 * we touch it.
 */
final class HydraDocumentationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly string $apiPrefix = '/api',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['enrich', -32],
        ];
    }

    public function enrich(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_ends_with($path, '/docs.jsonld') && !str_ends_with($path, '/docs')) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type') ?? '';
        if (!str_contains($contentType, 'application/ld+json')) {
            return;
        }

        $body = $response->getContent();
        if (!$body) {
            return;
        }

        $data = json_decode($body, true);
        if (!\is_array($data) || !isset($data['hydra:supportedClass']) || !\is_array($data['hydra:supportedClass'])) {
            return;
        }

        $documented = $this->buildDocumentedOperations();
        $labels = $this->buildLabels();
        // How often a class title / Entrypoint property @id has been seen:
        // the n-th occurrence belongs to the n-th documented ApiResource.
        $classSeen = [];
        $propertySeen = [];

        foreach ($data['hydra:supportedClass'] as &$class) {
            $classShortName = (string) ($class['hydra:title'] ?? '');

            if (isset($labels[$classShortName]) && !isset($class['rdfs:label'])) {
                $class['rdfs:label'] = $labels[$classShortName];
            }

            if (isset($class['hydra:supportedOperation']) && \is_array($class['hydra:supportedOperation'])) {
                $classIndex = $classSeen[$classShortName] = ($classSeen[$classShortName] ?? -1) + 1;
                $this->enrichOperations(
                    $class['hydra:supportedOperation'],
                    $documented['class'][$classShortName][$classIndex] ?? [],
                );
            }

            if (isset($class['hydra:supportedProperty']) && \is_array($class['hydra:supportedProperty'])) {
                foreach ($class['hydra:supportedProperty'] as &$prop) {
                    if (!isset($prop['hydra:property']['hydra:supportedOperation'])
                        || !\is_array($prop['hydra:property']['hydra:supportedOperation'])) {
                        continue;
                    }

                    // API Platform documents operations under Entrypoint
                    // properties only; anywhere else nothing is paired.
                    $pairs = [];
                    if ('Entrypoint' === $classShortName) {
                        $propId = (string) ($prop['hydra:property']['@id'] ?? '');
                        $propIndex = $propertySeen[$propId] = ($propertySeen[$propId] ?? -1) + 1;
                        $pairs = $documented['entrypoint'][$propId][$propIndex] ?? [];
                    }

                    $this->enrichOperations($prop['hydra:property']['hydra:supportedOperation'], $pairs);
                }
                unset($prop);
            }
        }
        unset($class);

        $response->setContent(json_encode($data));
    }

    private function renamePrefixedKeys(array &$op): void
    {
        if (\array_key_exists('expects', $op)) {
            if (!isset($op['hydra:expects'])) {
                $op['hydra:expects'] = $op['expects'];
            }
            unset($op['expects']);
        }
        if (\array_key_exists('returns', $op)) {
            if (!isset($op['hydra:returns'])) {
                $op['hydra:returns'] = $op['returns'];
            }
            unset($op['returns']);
        }
    }

    /**
     * Renames keys of every documented operation and pairs the n-th one with
     * the n-th metadata operation documented at the same place.
     *
     * @param array<mixed>                                        $operations
     * @param list<array{method:string,url:?string,custom:bool}> $pairs
     */
    private function enrichOperations(array &$operations, array $pairs): void
    {
        $position = 0;
        foreach ($operations as &$op) {
            if (!\is_array($op)) {
                continue;
            }
            $this->renamePrefixedKeys($op);
            $this->fillUrlCarrier($op, $pairs[$position] ?? null);
            ++$position;
        }
        unset($op);
    }

    /**
     * `$source` is the metadata operation this entry was documented from,
     * null when the documentation has no counterpart at its position.
     *
     * @param array{method:string,url:?string,custom:bool}|null $source
     */
    private function fillUrlCarrier(array &$op, ?array $source): void
    {
        if (isset($op['@id']) || isset($op['hydra:uriTemplate'])) {
            return;
        }

        if (null === $source || $source['custom'] || null === $source['url']) {
            return;
        }

        // Guards against a document that does not have the shape the
        // normalizer gives it: never fill another operation's URL.
        if (($op['hydra:method'] ?? null) !== $source['method']) {
            return;
        }

        if (str_contains($source['url'], '{')) {
            $op['hydra:uriTemplate'] = $source['url'];
        } else {
            $op['@id'] = $source['url'];
        }
    }

    /**
     * The operations API Platform's Hydra DocumentationNormalizer documents,
     * in its order: per resource class and ApiResource (without those with
     * `hideHydraOperation`), the operations of `getOperations()` (without
     * those with `hideHydraOperation`), POST and collection operations
     * under the Entrypoint property `#Entrypoint/<lcfirst shortName>`, all
     * others under the class titled with the shortName. One list per
     * ApiResource, so the n-th class or property with the same key belongs
     * to the n-th list; an ApiResource without Entrypoint operations gets
     * no Entrypoint property and no list.
     *
     * @return array{
     *     class: array<string, list<list<array{method:string,url:?string,custom:bool}>>>,
     *     entrypoint: array<string, list<list<array{method:string,url:?string,custom:bool}>>>
     * }
     */
    private function buildDocumentedOperations(): array
    {
        $out = ['class' => [], 'entrypoint' => []];
        foreach ($this->resourceNameCollectionFactory->create() as $class) {
            try {
                $metadata = $this->resourceMetadataFactory->create($class);
            } catch (\Throwable) {
                continue;
            }

            foreach ($metadata as $resource) {
                if (true === $resource->getHideHydraOperation()) {
                    continue;
                }
                $shortName = (string) $resource->getShortName();
                $routePrefix = $resource->getRoutePrefix() ?? '';

                $classOperations = [];
                $entrypointOperations = [];
                foreach ($resource->getOperations() ?? [] as $op) {
                    if (!$op instanceof HttpOperation || true === $op->getHideHydraOperation()) {
                        continue;
                    }
                    $method = $op->getMethod() ?: 'GET';
                    $source = [
                        'method' => $method,
                        'url' => $this->url($op, $routePrefix),
                        'custom' => CustomAction::isCustomAction($op),
                    ];
                    if ('POST' === $method || $op instanceof CollectionOperationInterface) {
                        $entrypointOperations[] = $source;
                    } else {
                        $classOperations[] = $source;
                    }
                }

                $out['class'][$shortName][] = $classOperations;
                if ([] !== $entrypointOperations) {
                    $out['entrypoint']['#Entrypoint/'.lcfirst($shortName)][] = $entrypointOperations;
                }
            }
        }

        return $out;
    }

    /**
     * Full URL of an operation: API prefix, route prefix and URI template
     * without `{._format}`; null without a URI template.
     */
    private function url(HttpOperation $op, string $routePrefix): ?string
    {
        $template = $op->getUriTemplate() ?? '';
        if ('' === $template) {
            return null;
        }

        return rtrim($this->apiPrefix, '/').$routePrefix.preg_replace('/\{\._format\}/', '', $template);
    }

    /**
     * Maps resource shortNames to their human-readable display label, taken
     * from the resource's `extraProperties['label']`. Consumers (e.g.
     * hrzg/vue-za7-admin-ui) prefer `rdfs:label` over `hydra:title` for menu
     * and heading text; `hydra:title` (= shortName) stays the technical key
     * for schema lookups. The label is free text and may later be run
     * through a translator here without touching the resources.
     *
     * @return array<string, string>
     */
    private function buildLabels(): array
    {
        $out = [];
        foreach ($this->resourceNameCollectionFactory->create() as $class) {
            try {
                $metadata = $this->resourceMetadataFactory->create($class);
            } catch (\Throwable) {
                continue;
            }

            foreach ($metadata as $resource) {
                $shortName = $resource->getShortName();
                $label = $resource->getExtraProperties()['label'] ?? null;
                if (null === $shortName || '' === $shortName || !\is_string($label) || '' === $label) {
                    continue;
                }
                $out[$shortName] ??= $label;
            }
        }

        return $out;
    }
}
