<?php
// file generated with AI assistance: Claude Code - 2026-05-12 12:55:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\EventSubscriber;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
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
 *    For operations missing both `@id` and `hydra:uriTemplate` we look up the
 *    matching resource operation in the metadata collection and emit either
 *    `@id` (no placeholder) or `hydra:uriTemplate` (placeholder present).
 *    Custom operations are skipped: they are already enriched by
 *    {@see \Dmstr\ApiPlatformUtils\Metadata\CustomOperationHydraFactory}.
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

        $lookup = $this->buildLookup();

        foreach ($data['hydra:supportedClass'] as &$class) {
            $classShortName = (string) ($class['hydra:title'] ?? '');

            if (isset($class['hydra:supportedOperation']) && \is_array($class['hydra:supportedOperation'])) {
                $candidates = $lookup[$classShortName] ?? [];
                foreach ($class['hydra:supportedOperation'] as &$op) {
                    if (!\is_array($op)) {
                        continue;
                    }
                    $this->renamePrefixedKeys($op);
                    $this->fillUrlCarrier($op, $candidates, true);
                }
                unset($op);
            }

            if (isset($class['hydra:supportedProperty']) && \is_array($class['hydra:supportedProperty'])) {
                foreach ($class['hydra:supportedProperty'] as &$prop) {
                    if (!isset($prop['hydra:property']['hydra:supportedOperation'])
                        || !\is_array($prop['hydra:property']['hydra:supportedOperation'])) {
                        continue;
                    }

                    $propShortName = $classShortName;
                    if ('Entrypoint' === $classShortName) {
                        $propShortName = $this->shortNameFromEntrypointProperty(
                            (string) ($prop['hydra:property']['@id'] ?? ''),
                            $lookup,
                        );
                    }
                    $candidates = $lookup[$propShortName] ?? [];

                    foreach ($prop['hydra:property']['hydra:supportedOperation'] as &$op) {
                        if (!\is_array($op)) {
                            continue;
                        }
                        $this->renamePrefixedKeys($op);
                        $this->fillUrlCarrier($op, $candidates, false);
                    }
                    unset($op);
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
     * @param list<array{method:string,uriTemplate:string,routePrefix:string}> $candidates
     */
    private function fillUrlCarrier(array &$op, array $candidates, bool $classLevel): void
    {
        if (isset($op['@id']) || isset($op['hydra:uriTemplate'])) {
            return;
        }

        $method = $op['hydra:method'] ?? null;
        if (!\is_string($method) || '' === $method) {
            return;
        }

        foreach ($candidates as $cand) {
            if ($cand['method'] !== $method) {
                continue;
            }
            $template = (string) preg_replace('/\{\._format\}/', '', $cand['uriTemplate']);
            if ($this->isCustomTemplate($template)) {
                continue;
            }

            $hasPlaceholder = str_contains($template, '{');
            if ($classLevel !== $hasPlaceholder) {
                continue;
            }

            $full = rtrim($this->apiPrefix, '/').$cand['routePrefix'].$template;

            if (str_contains($full, '{')) {
                $op['hydra:uriTemplate'] = $full;
            } else {
                $op['@id'] = $full;
            }

            return;
        }
    }

    private function isCustomTemplate(string $template): bool
    {
        $template = trim($template, '/');
        if ('' === $template) {
            return false;
        }

        $segments = explode('/', $template);
        $count = \count($segments);

        if (1 === $count) {
            return false;
        }
        if (2 === $count && str_starts_with($segments[1], '{')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, list<array{method:string,uriTemplate:string,routePrefix:string}>>
     */
    private function buildLookup(): array
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
                if (null === $shortName || '' === $shortName) {
                    continue;
                }
                $routePrefix = $resource->getRoutePrefix() ?? '';

                foreach ($resource->getOperations() ?? [] as $op) {
                    if (!$op instanceof HttpOperation) {
                        continue;
                    }
                    $template = $op->getUriTemplate() ?? '';
                    if ('' === $template) {
                        continue;
                    }
                    $out[$shortName][] = [
                        'method' => $op->getMethod() ?? 'GET',
                        'uriTemplate' => $template,
                        'routePrefix' => $routePrefix,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, list<array{method:string,uriTemplate:string,routePrefix:string}>> $lookup
     */
    private function shortNameFromEntrypointProperty(string $propId, array $lookup): string
    {
        $prefix = '#Entrypoint/';
        if (!str_starts_with($propId, $prefix)) {
            return '';
        }
        $camel = substr($propId, \strlen($prefix));
        foreach (array_keys($lookup) as $shortName) {
            if (lcfirst($shortName) === $camel) {
                return $shortName;
            }
        }

        return '';
    }
}
