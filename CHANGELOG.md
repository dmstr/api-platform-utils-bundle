<!-- file generated with AI assistance: Claude Code - 2026-07-22, revised 2026-10-08 13:37:13 UTC -->

# Changelog

## Unreleased

### Added

- `relation_labels` (opt-in, default off): `RelationLabelNormalizer` decorates `api_platform.jsonld.normalizer.item` and embeds `{"@id": <iri>, <label>: <value>}` instead of the plain IRI for to-one relations in JSON-LD collection responses, so lists can show a related record by name. Item responses keep plain IRIs (forms edit relations as `iri-reference` strings, and API Platform rejects nested documents on write). Relations already embedded via serialization groups are left alone; a related row that cannot be loaded (e.g. hidden by row-level security) keeps its IRI.
- `x-label-property` on the resource's own definition, so clients can title a record the same way a relation to it is labelled. Only set when the label property is a mapped field; an existing hint is kept.

### Changed

- The label property inference moved into `RelationLabelPropertyResolver`, shared by `RelationFieldSchemaDecorator` and `RelationLabelNormalizer`, so schema hint and embedded label always name the same property.

## 0.5.0 (2026-10-09)

### Added

- `auto_order` (opt-in, default off): generated `order[<property>]` query parameters (API Platform `SortFilter`) for the `GetCollection` operations of Doctrine ORM resources — sortable scalar fields (type whitelist, identifiers excluded) and to-one relations as `order[<relation>.<label>]`, label from `relation_field_decorator.label_property_candidates` only. Only API-readable properties (name collection, `readable`, normalization groups) are offered; explicit `#[ApiFilter(OrderFilter::class)]` declarations and parameters the operation declares itself win per property; operations with their own `provider` are skipped; DTO resources are mapped via `stateOptions` `entityClass`. Requires API Platform >= 4.3.
- `auto_order.default_order` (default `{name: ASC, createdAt: DESC}`): default sort for `GetCollection` operations without an own `order`; the first key that is a sortable property of the resource wins.
- `#[AutoOrder(enabled: false)]` / `#[AutoOrder(exclude: [...])]` attribute to switch auto-ordering off or restrict it per class.
- `stable_order` (opt-in, default off): `StableOrderExtension` appends `ORDER BY <identifier> ASC` to Doctrine ORM collection queries that do not sort by the identifier yet (priority -40, between `OrderExtension` and pagination), for stable paging over non-unique sort columns. Skipped for composite/foreign identifiers and `GROUP BY` queries.

### Changed

- `email` added to the default `relation_field_decorator.label_property_candidates` (`name`, `title`, `label`, `displayName`, `email`). Projects that set the list explicitly are not affected.
- Label property candidates from several config files are de-duplicated (list nodes are appended to each other when merged).
- `symfony/yaml` moved from `require-dev` to `require`: the bundle extension always loads `config/services.yaml` via `YamlFileLoader`, so it is a runtime dependency.

### Fixed

- Relation extensions are also added to properties inside `allOf` (JSON-LD output definitions wrap their properties next to the Hydra base schema), so JSON-LD read schemas get the hints introduced in 0.4.1.

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
