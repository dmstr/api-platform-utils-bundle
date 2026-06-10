<?php
// file generated with AI assistance: Claude Code - 2026-06-09 14:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Attribute;

use Attribute;

/**
 * Marks a JSON-array property of UUID strings as a list of IRI references
 * to another API resource — even when no Doctrine relation exists.
 *
 * Effects (wired via IriReferenceArraySchemaFactory, IriReferenceArrayDenormalizer
 * and IriReferenceArrayDoctrineListener):
 *
 *  - OpenAPI / JSON-Schema for the property becomes
 *    `{type: "array", items: {format: "iri-reference", x-collection: ..., ...}}`
 *  - Inbound: IRIs in the request body are reduced to bare UUIDs before persistence.
 *  - Outbound: bare UUIDs loaded from the DB are expanded back to IRIs.
 *
 * Use for unordered/ordered "soft" foreign keys stored as a JSON list — keeps
 * the column small and avoids a join table while giving the admin UI a proper
 * autocomplete picker.
 *
 * Example:
 *
 *   #[ORM\Column(name: 'module_ids', type: Types::JSON)]
 *   #[IriReferenceArray(
 *       collection: '/api/admin/modules',
 *       resourceClass: 'Module',
 *   )]
 *   private array $moduleIds = [];
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class IriReferenceArray
{
    public function __construct(
        /** Collection endpoint path used by the picker, e.g. `/api/admin/modules`. */
        public readonly string $collection,
        /** Short resource name surfaced to the frontend (matches API Platform shortName). */
        public readonly string $resourceClass,
        /** Property of the target entity shown in the dropdown and searched against. */
        public readonly string $labelProperty = 'name',
        /** Property of the target entity submitted back (typically `@id`). */
        public readonly string $valueProperty = '@id',
    ) {
    }
}
