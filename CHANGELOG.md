<!-- file generated with AI assistance: Claude Code - 2026-07-22, revised 2026-10-08 13:37:13 UTC -->

# Changelog

## 0.8.0 (2026-11-26)

### Changed

- **BREAKING**: custom actions in `/api/docs.jsonld` are classified by the marker `extraProperties: ['custom_action' => true]` only; the former path heuristic (every operation deeper than `/resource` or `/resource/{id}` counted as a custom action) is gone, with no fallback of any kind (issues #7 and #8). Only the boolean `true` marks; a missing key, `false`, `1`, `'true'` or `'1'` do not, without type coercion. API Platform 4.x merges `extraProperties` flatly (operation over `#[ApiResource]` over `api_platform.defaults.extra_properties`), so a marker on the resource marks all its operations and `custom_action: false` on an operation exempts that operation from an inherited marker. API Platform's `NotExposed` stub is never a custom action, even when it inherits a resource marker: no `schema:Action`, no extra entry. A marked operation is a custom action at any path depth, also `/resource` or `/resource/{id}`; an unmarked one is an ordinary operation at any depth. Recommended: set the marker per operation, not on the resource or in the global defaults.
- Consumer action: set `custom_action: true` on every real action. Until then those operations lose `@type: schema:Action`, and clients that build an actions menu from this output (the ZA7 admin UI does) show nothing for them, without an error. Known affected bundles: `dmstr/flowable-bundle`, `dmstr/api-configuration-bundle`, `dmstr/firecrawl-bundle`, `dmstr/neuron-ai-bundle`.
- Operations that are no longer custom actions get `@type`, `hydra:title` and `hydra:expects`/`hydra:returns` from API Platform again (e.g. `schema:CreateAction` and `postThing` for a POST), and their URL carrier from `HydraDocumentationSubscriber`. A marked operation on a one-segment path (`/resource`, `/resource/{id}`) gets the custom-action keys for the first time. `hydra:title` of a custom action still comes from the operation name: name the action via `name` or `hydraContext['hydra:title']`. Without an operation name no title is generated any more (the fallback from method and URI segments is removed; in the bundle's wiring API Platform names every operation before the factory runs).
- New class `Dmstr\ApiPlatformUtils\Metadata\CustomAction` (`MARKER` = `'custom_action'`, `isCustomAction()`) as the single source of the classification, used by `CustomOperationHydraFactory` and `HydraDocumentationSubscriber`; constructors, service IDs and configuration keys are unchanged.
- README section "Custom actions" documents the marker, its rules and how to name an action.
- Unchanged: the runtime `hydra:operation` subscriber (`AddHydraOperationsSubscriber`) does not classify custom actions and ignores the marker.
- Inheritance is verified for API Platform 4.x only; `^3.0` is allowed by the constraint but untested.

### Fixed

- URL carriers in `/api/docs.jsonld`: `HydraDocumentationSubscriber` pairs each documented operation with the metadata operation API Platform built it from, by position, instead of taking the first operation with the same method and placeholder flag; every operation carries its own URL. Consumers that relied on "the first GET of the resource is the item route" must re-check. Operations under properties other than the Entrypoint's are no longer filled.
- `CustomOperationHydraFactory` strips `{._format}` before choosing the URL carrier, as the subscriber does, so a marked operation with a generated template such as `/widgets{._format}` or `/widgets/{id}{._format}` gets `@id: /api/widgets` or `hydra:uriTemplate: /api/widgets/{id}` instead of a template with `{._format}`.
- `CustomOperationHydraFactory` no longer adds a generated `@id` or `hydra:uriTemplate` when the author set either one in `hydraContext`, so a custom action never carries both.

## 0.7.0 (2026-11-26)

### Changed

- `schema_default_cleanup` (on by default): `InvalidDefaultSchemaDecorator` removes a property `default` from the JSON/OpenAPI schemas when the property's own schema rejects it (`pattern`, `minLength`, `maxLength`, `enum`). API Platform derives `default` from the PHP initializer, so `private string $slug = '';` next to a slug `#[Assert\Regex]` produced `default: ""` that its own `pattern` rejects, and form clients showed a validation error before anyone typed. Valid defaults are untouched. The cleanup descends into `properties`, `items` (single schema and tuple form), `prefixItems`, `additionalProperties` (when it is a schema), `allOf`, `anyOf` and `oneOf`. `default: null` is judged by `enum` only and is removed when an `enum` exists that does not contain `null`. Patterns are evaluated with PHP's PCRE, `$` matching only at the very end (a trailing newline does not match). Limits: `$ref` is not resolved, so a default next to a `$ref` is not checked; a pattern PHP cannot compile keeps its default; ECMA-262 regular expressions are not fully translated to PCRE. Server-side validation is unchanged. Consumers: check forms that rely on a default that is now removed, among them `default: null` on nullable enum properties whose `enum` does not contain `null`, or switch the cleanup off with `schema_default_cleanup.enabled: false`.
- Tests and documentation only, no behaviour change: the Hydra operation filter test uses a permission attribute (`is_granted('thing:write')`) instead of a role name in the `security` expression, and a regression test proves the expression reaches the `ResourceAccessChecker` unchanged with `object = null` for collection operations. The filter is expression-neutral; this bundle defines no permissions of its own. README: how to use the filter with permission attributes.

## 0.6.0 (2026-10-15)

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
