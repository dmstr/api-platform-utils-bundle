<?php
// file generated with AI assistance: Claude Code - 2026-05-06 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Metadata;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * Gives every custom action explicit Hydra metadata so JSON-LD clients can
 * discover it via @type: schema:Action instead of the type API Platform
 * derives from the HTTP method (e.g. schema:CreateAction for any POST).
 *
 * A custom action is an operation whose merged `extraProperties` carry
 * `custom_action: true` (set on the operation, or inherited from the
 * resource or the global defaults); see {@see CustomAction} for the exact
 * rule. Every other operation is passed through unchanged, whatever its
 * URI template.
 *
 * The factory decorates the ResourceMetadataCollectionFactory chain at low
 * priority (default 100), so extra properties are merged and URI templates
 * and operation names are already resolved when this layer runs.
 *
 * Hydra context written for every custom action:
 *   - @type:        ["hydra:Operation", "schema:Action"]
 *   - hydra:title:  literal value of operation `name` (omitted without one)
 *   - hydra:expects: input class short name, or owl:Nothing if input=false
 *   - hydra:returns: output class short name, or owl:Nothing if no output
 *   - hydra:description: from operation `description` if set
 *   - @id:           full URL (api_prefix + route_prefix + uri_template
 *                    without `{._format}`), only when it carries NO placeholder
 *   - hydra:uriTemplate: the same URL with placeholders preserved,
 *                    only when it DOES carry a placeholder
 *
 * Existing values in `hydraContext` of the operation are preserved; an
 * author-set `@id` or `hydra:uriTemplate` is the only URL carrier.
 */
final class CustomOperationHydraFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
        private readonly string $apiPrefix = '/api',
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $i => $resourceMetadata) {
            $operations = $resourceMetadata->getOperations();
            if (null === $operations || 0 === \count($operations)) {
                continue;
            }

            $routePrefix = $resourceMetadata->getRoutePrefix() ?? '';

            $rebuilt = [];
            foreach ($operations as $name => $operation) {
                $rebuilt[$name] = CustomAction::isCustomAction($operation)
                    ? $this->markAsCustom($operation, $routePrefix)
                    : $operation;
            }

            $collection[$i] = $resourceMetadata->withOperations(new Operations($rebuilt));
        }

        return $collection;
    }

    private function markAsCustom(HttpOperation $operation, string $routePrefix): HttpOperation
    {
        $existing = $operation->getHydraContext() ?? [];
        $output = $operation->getOutput() ?? [];
        $outputClass = \is_array($output) && \array_key_exists('class', $output) ? $output['class'] : null;
        $input = $operation->getInput() ?? [];
        $inputClass = \is_array($input) && \array_key_exists('class', $input) ? $input['class'] : null;

        $generated = ['@type' => ['hydra:Operation', 'schema:Action']];
        if (null !== $operation->getName()) {
            $generated['hydra:title'] = $operation->getName();
        }
        $context = $existing + $generated + [
            'hydra:expects' => null === $inputClass ? 'owl:Nothing' : $this->classShortName($inputClass),
            'hydra:returns' => null === $outputClass ? 'owl:Nothing' : $this->classShortName($outputClass),
        ];

        if (null !== $operation->getDescription() && !isset($context['hydra:description'])) {
            $context['hydra:description'] = $operation->getDescription();
        }

        if (!isset($context['@id']) && !isset($context['hydra:uriTemplate'])) {
            $context += $this->urlCarrier($operation, $routePrefix);
        }

        return $operation->withHydraContext($context);
    }

    /**
     * Build either `@id` (no placeholder in URI) or `hydra:uriTemplate`
     * (placeholder present) for a custom operation. Never both, never neither
     * (except without a URI template, where there is no URL to carry).
     * `{._format}` is removed first: it is not a placeholder a client fills,
     * the same as {@see \Dmstr\ApiPlatformUtils\EventSubscriber\HydraDocumentationSubscriber}.
     */
    private function urlCarrier(HttpOperation $operation, string $routePrefix): array
    {
        $template = $operation->getUriTemplate() ?? '';
        if ('' === $template) {
            return [];
        }

        $full = rtrim($this->apiPrefix, '/').$routePrefix.preg_replace('/\{\._format\}/', '', $template);

        return str_contains($full, '{')
            ? ['hydra:uriTemplate' => $full]
            : ['@id' => $full];
    }

    private function classShortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return false === $pos ? $fqcn : substr($fqcn, $pos + 1);
    }
}
