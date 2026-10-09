<!-- file generated with AI assistance: Claude Code - 2026-07-22 -->

# Changelog

## 0.4.1 (2026-10-09)

### Fixed

- `RelationFieldSchemaDecorator` also decorates output (read) schemas: `x-collection`, `x-label-property`, `x-value-property`, `x-search-property` and `x-resource-class` were only added to input schemas, so clients building forms from the read schema (resources with serialization groups have no plain schema) got no type-ahead for `iri-reference` fields

## 0.4.0 (2026-07-22)

### Added

- `hydra:operation` on collection GET responses (`hydra:Collection`): collection-level operations (create `POST`, custom collection actions), analogous to the existing item enrichment (#5)
- Security filtering for `hydra:operation`: operations whose `security` expression does not grant access to the current token are omitted, evaluated via API Platform's `ResourceAccessChecker`; item operations are checked with the loaded entity as `object` (#5)
- New config flag `hydra_operations.filter_operations_by_security` (default `true`) to switch the filtering off (#5)
- `Vary: Authorization` header on enriched responses while filtering is active — the advertised operations are user-dependent (#5)

### Fixed

- Duplicate `partial_uuid_item_provider` node in the bundle configuration tree (and the duplicate parameter assignment in the extension)

## 0.3.1 and earlier

See git history.
