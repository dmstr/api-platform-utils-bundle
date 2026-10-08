<!-- file generated with AI assistance: Claude Code - 2026-06-09 19:27:00 UTC, revised 2026-10-08 13:35:00 UTC -->

# dmstr/api-platform-utils-bundle

Generic helpers for API Platform projects.

## Features (planned)

- Partial-UUID lookups (`UuidResolver`, `UuidSearchFilter`)
- JWT security decorator for the OpenAPI doc
- Credential encryption service (`CredentialEncryption`)
- `#[IriReferenceArray]` attribute + Doctrine listener + persist processor +
  OpenAPI schema factory for autocomplete-rendered IRI collections
- DB-JSON CLI tools (`db:export-json`, `db:import-json`)
- `AbstractJsonSchemaInputCommand` base class for JSON-Schema-validated CLI
- Generated sort parameters, default sort and a stable-pagination tie-breaker (`auto_order`, `#[AutoOrder]`, `stable_order`)
- Relation label (`x-label-property`) in input and output schemas

## Hydra operations (`hydra:operation`)

`AddHydraOperationsSubscriber` enriches JSON-LD runtime responses with a `hydra:operation` array so clients can discover the available operations without parsing the static docs:

- **Item GET responses** list the item-level operations (`GET`, `PUT`, `PATCH`, `DELETE`, custom `/resource/{id}/<verb>` actions) with the `{id}` placeholder resolved to the actual identifier.
- **Collection GET responses** (`hydra:Collection`) list the collection-level operations (`GET`, create `POST`, custom `/resource/<verb>` actions).

### Security filtering (HATEOAS)

By default each operation is checked against the current security token via API Platform's `ResourceAccessChecker` (`api_platform.security.resource_access_checker`); operations whose `security` expression does not grant access are omitted. The server therefore only advertises what the current user may actually execute — a UI can derive `canCreate`/`canUpdate`/`canDelete` directly from the runtime `hydra:operation` instead of the role-independent static docs.

Rules:

- Operations **without** a `security` expression stay visible (default: allowed).
- Item operations are evaluated with the loaded entity as `object` (when resolvable from the request); collection operations are evaluated with `object = null`.
- Only collection-level operations are checked on collection responses — one check per operation, never per `hydra:member`.
- Expression evaluation errors fail closed (the operation is hidden).
- Without `symfony/security-bundle` the checker service is absent and filtering is skipped.

Configuration:

```yaml
dmstr_api_platform_utils:
    hydra_operations:
        enabled: true
        api_prefix: '/api'
        event_priority: -10
        # set to false to restore the unfiltered (pre-0.4) behavior
        filter_operations_by_security: true
```

### HTTP caching

With filtering active, item and collection responses become **user-dependent**: the subscriber emits `Vary: Authorization` so shared HTTP caches never serve one user's operation set to another. The static `/api/docs.jsonld` stays role-independent and cacheable. If your stack caches API responses in a reverse proxy, verify it honors `Vary` — otherwise exclude these responses from caching.

## Sorting

### Generated sort parameters (`auto_order`)

With `auto_order.enabled`, every `GetCollection` operation of a Doctrine ORM resource gets one `order[<property>]` query parameter per sortable property, backed by API Platform's `SortFilter`. They appear in the OpenAPI document and in `hydra:search`, so a client can derive its sortable columns from the API instead of guessing. Requires API Platform >= 4.3 (nested `SortFilter` support).

Which properties get a parameter:

- **Scalar fields** whose Doctrine type is sortable: `string`, `ascii_string`, `integer`, `smallint`, `bigint`, `float`, `smallfloat`, `decimal`, `boolean`, `date`, `datetime`, `datetimetz`, `time` (and their `_immutable` variants). Not offered: identifiers of any type, `text`, `json`, `array`/`simple_array`, `blob`/`binary`, `guid`/`uuid`, custom types such as `vector`, and embedded fields.
- **To-one relations** (`ManyToOne`, owning `OneToOne`) as `order[<relation>.<label>]`, sorted by the related entity's label via a `LEFT JOIN` (rows without a relation stay in the list). The label is the first entry of `relation_field_decorator.label_property_candidates` that is a sortable, readable field of the target entity. There is no fallback: a relation without a candidate gets no parameter.
- Only properties the API exposes: the property must be in API Platform's property name collection and readable with the operation's normalization groups. `#[ApiProperty(readable: false)]` and properties outside the groups get no parameter.

Precedence and exclusions:

- A parameter the operation declares itself under the same key wins.
- An explicit `#[ApiFilter(OrderFilter::class, properties: [...])]` (class or property level) wins per property; a class-level `OrderFilter` without `properties` covers all properties, so no parameter is generated then. Order filters registered by service id (`ApiResource(filters: [...])`) are not recognised.
- Operations with their own `provider` are skipped. DTO resources are mapped to their entity via `stateOptions: new Options(entityClass: ...)`; the DTO's properties decide what is exposed.

```yaml
dmstr_api_platform_utils:
    relation_field_decorator:
        label_property_candidates: [name, title, label, displayName, email]
    auto_order:
        enabled: true
        # default sort for GetCollection operations without an own `order`
        default_order:
            name: ASC
            createdAt: DESC
```

### Default sort (`auto_order.default_order`)

A `GetCollection` operation without an own `order` gets the first entry of `default_order` that is a sortable property of the resource as its `order` (default: `name ASC`, else `createdAt DESC`). API Platform's `OrderExtension` applies it only when the client sends no sort parameter, so a client sort always replaces it. Without a match the operation keeps API Platform's own default (identifier). An empty map (`default_order: {}`) switches the default sort off. The map is replaced, not merged, when several config files set it.

### Per class: `#[AutoOrder]`

```php
use Dmstr\ApiPlatformUtils\Attribute\AutoOrder;

#[AutoOrder(enabled: false)]                      // no parameters, no default sort
#[AutoOrder(exclude: ['hash', 'collection'])]     // no order[hash], no order[collection.<label>]
```

The attribute is read from the resource class and, for DTO resources, from the entity class too. An `exclude` entry matches the path and everything below it; excluded properties are no default sort candidates either.

### Stable pagination (`stable_order`)

Sorting by a non-unique column leaves the order of equal rows to the database, which may differ between two page requests: rows show up twice or never while paging. With `stable_order.enabled`, `StableOrderExtension` appends `ORDER BY <root>.<identifier> ASC` to every Doctrine ORM collection query whose ORDER BY does not contain the identifier yet. It is tagged with priority -40, after the sort (`OrderExtension`, -32) and before pagination (-64). Entities with a composite or foreign identifier and queries with `GROUP BY` are left alone.

```yaml
dmstr_api_platform_utils:
    stable_order:
        enabled: true
```

## Relation labels in schemas (`x-label-property`)

`RelationFieldSchemaDecorator` adds `x-*` extensions to relation properties (`format: iri-reference`) of Doctrine resources:

- **Input schemas** (forms): `x-collection`, `x-label-property`, `x-value-property`, `x-search-property`, `x-resource-class`, for autocomplete pickers. The label is the first candidate the target class declares, else its first string property.
- **Output schemas** (read models, list columns): only `x-label-property` and `x-resource-class`, label from the candidate list only (no extension without a candidate). Without `x-collection` a read-only relation is not turned into a picker, while a list can still show and sort the relation by its label (`order[<relation>.<label>]`).

Output processing touches only the definitions the built schema references, read with the Doctrine metadata of the operation's output class. When input and output share a definition name (no serialization groups, same format), that definition carries the input extensions.

## License

MIT © diemeisterei GmbH
