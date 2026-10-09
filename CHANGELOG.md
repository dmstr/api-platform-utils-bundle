<!-- file generated with AI assistance: Claude Code - 2026-07-22, revised 2026-10-08 13:37:13 UTC, revised 2026-10-10 00:25:00 UTC -->

# Changelog

## Unreleased

### Fixed

- `InvalidDefaultSchemaDecorator` drops a property `default` from the JSON/OpenAPI schemas when the property schema itself rejects it (`pattern`, `minLength`, `maxLength`, `enum`). API Platform derives `default` from the PHP initializer, so `private string $slug = '';` next to a slug `#[Assert\Regex]` produced `default: ""` that its own `pattern` rejects, and form clients showed a validation error before anyone typed. Valid defaults are untouched; a pattern PHP cannot compile keeps its default. On by default, switch off with `schema_default_cleanup.enabled: false`.

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
