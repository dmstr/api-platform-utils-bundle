<?php
// file generated with AI assistance: Claude Code - 2026-06-09 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\ApiPlatformUtils\Service\IriReferenceArrayMapper;

/**
 * Wraps the default ORM persist processor so that IRI-strings reaching the
 * write path on Dmstr\ApiPlatformUtils\Attribute\IriReferenceArray properties are reduced to
 * bare UUIDs before flush — and re-expanded on the returned entity so the
 * response body carries IRIs.
 *
 * Together with App\EventListener\IriReferenceArrayDoctrineListener this
 * keeps the on-wire representation as IRIs and the on-disk representation
 * as UUIDs, transparently to the resource code.
 */
final class IriReferenceArrayPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly IriReferenceArrayMapper $mapper,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): mixed {
        if (is_object($data) && $this->mapper->hasProperties($data::class)) {
            $this->mapper->denormalizeEntity($data);
        }

        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        if (is_object($result) && $this->mapper->hasProperties($result::class)) {
            $this->mapper->normalizeEntity($result);
        }

        return $result;
    }
}
