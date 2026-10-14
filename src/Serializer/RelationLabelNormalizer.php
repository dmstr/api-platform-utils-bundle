<?php
// file generated with AI assistance: Claude Code - 2026-10-14 21:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Serializer;

use ApiPlatform\Metadata\CollectionOperationInterface;
use Dmstr\ApiPlatformUtils\Service\RelationLabelPropertyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Embeds the label of to-one relations in JSON-LD collection responses:
 * `"parent": "/api/einheits/0b1…"` becomes
 * `"parent": {"@id": "/api/einheits/0b1…", "bezeichnung": "Erdgeschoss"}`.
 *
 * The label property is the one the schema announces as `x-label-property`
 * (shared {@see RelationLabelPropertyResolver}), so a client reads the label
 * from exactly the property the schema names.
 *
 * Deliberately limited:
 * - collection operations only — item responses keep plain IRIs, because
 *   forms edit relations as `iri-reference` strings and API Platform rejects
 *   nested documents on write;
 * - only values the inner normalizer emitted as IRI strings — relations a
 *   resource already embeds via serialization groups are left untouched;
 * - a related row that cannot be loaded (e.g. hidden by row-level security)
 *   keeps its plain IRI.
 *
 * Every embedded label initialises the related entity: one query per distinct
 * related row and page, unless the collection query fetch-joins it.
 */
final class RelationLabelNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private const FORMAT = 'jsonld';

    public function __construct(
        private readonly NormalizerInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        private readonly RelationLabelPropertyResolver $labelPropertyResolver,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->decorated->normalize($data, $format, $context);

        if (self::FORMAT !== $format
            || !\is_array($normalized)
            || !\is_object($data)
            || !(($context['root_operation'] ?? $context['operation'] ?? null) instanceof CollectionOperationInterface)
        ) {
            return $normalized;
        }

        return $this->embedLabels($data, $normalized);
    }

    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    private function embedLabels(object $entity, array $normalized): array
    {
        if ($this->entityManager->getMetadataFactory()->isTransient($entity::class)) {
            return $normalized; // not a Doctrine entity (DTO, output class)
        }
        $metadata = $this->entityManager->getClassMetadata($entity::class);

        foreach ($metadata->getAssociationNames() as $field) {
            if (!$metadata->isSingleValuedAssociation($field) || !\is_string($normalized[$field] ?? null)) {
                continue;
            }

            $related = $metadata->getFieldValue($entity, $field);
            if (!\is_object($related)) {
                continue;
            }

            $targetMetadata = $this->entityManager->getClassMetadata($metadata->getAssociationTargetClass($field));
            $labelProperty = $this->labelPropertyResolver->resolve($targetMetadata->getName());
            if (!$targetMetadata->hasField($labelProperty)) {
                continue;
            }

            try {
                $this->entityManager->getUnitOfWork()->initializeObject($related);
            } catch (EntityNotFoundException) {
                continue;
            }

            $label = $targetMetadata->getFieldValue($related, $labelProperty);
            if (!\is_scalar($label) || '' === (string) $label) {
                continue;
            }

            $normalized[$field] = ['@id' => $normalized[$field], $labelProperty => (string) $label];
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!$this->decorated instanceof DenormalizerInterface) {
            throw new \LogicException(\sprintf('The decorated normalizer must implement "%s".', DenormalizerInterface::class));
        }

        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->decorated instanceof DenormalizerInterface
            && $this->decorated->supportsDenormalization($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
