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
- Relation label (`x-label-property`) in input and output schemas, and embedded in collection responses (`relation_labels`)
- Custom actions announced as `schema:Action` in the Hydra documentation, opted in per operation (`custom_action`)

## Hydra operations (`hydra:operation`)

`AddHydraOperationsSubscriber` enriches JSON-LD runtime responses with a `hydra:operation` array so clients can discover the available operations without parsing the static docs:

- **Item GET responses** list every operation of the resource whose URI template contains the `{id}` placeholder (e.g. `GET`, `PUT`, `PATCH`, `DELETE`, `POST /things/{id}/approve`), with `{id}` resolved to the actual identifier.
- **Collection GET responses** (`hydra:Collection`) list every operation whose URI template does not contain `{id}` (e.g. `GET`, create `POST`, `POST /things/import`).

The split is made by the `{id}` placeholder alone, at any path depth. This subscriber lists operations regardless of the `custom_action` marker (see [Custom actions](#custom-actions-custom_action)); marked and unmarked operations appear alike, typed `hydra:Operation`. The `hydra:title` is the operation's `description`, otherwise a title derived from an author-chosen operation name, otherwise a generated CRUD title such as "Retrieves a Thing resource".

### Security filtering (HATEOAS)

By default each operation is checked against the current security token via API Platform's `ResourceAccessChecker` (`api_platform.security.resource_access_checker`); operations whose `security` expression does not grant access are omitted. The server therefore only advertises what the current user may actually execute — a UI can derive `canCreate`/`canUpdate`/`canDelete` directly from the runtime `hydra:operation` instead of the role-independent static docs.

Rules:

- Operations **without** a `security` expression stay visible (default: allowed).
- Item operations are evaluated with the loaded entity as `object` (when resolvable from the request); collection operations are evaluated with `object = null`.
- Only collection-level operations are checked on collection responses — one check per operation, never per `hydra:member`.
- Expression evaluation errors fail closed (the operation is hidden).
- Without `symfony/security-bundle` the checker service is absent and filtering is skipped.
- The filter is expression-neutral: it hands the operation's `security` expression to the `ResourceAccessChecker` unchanged. A permission attribute such as `is_granted('thing:write')` (an example; this bundle defines no permissions of its own) is evaluated like any other expression. Collection operations are checked with `object = null`, so the voter answering the attribute must accept a `null` subject. With Symfony's default access-decision strategy the operation stays hidden when no voter answers the attribute.

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

## Custom actions (`custom_action`)

A custom action is an operation that is not plain CRUD on the resource, such as approving, importing or re-sending something. Clients such as an admin UI build an actions menu from the static Hydra documentation `/api/docs.jsonld`: they pick the operations typed `schema:Action`. API Platform itself types every operation by its HTTP method (`schema:CreateAction` for any `POST`), so an action cannot be told apart from a create. `CustomOperationHydraFactory` therefore gives every operation the author marked as a custom action its own Hydra metadata. Which operations those are is the author's decision, stated with the marker `custom_action`:

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(operations: [
    new Post(
        uriTemplate: '/things/{id}/approve',
        name: 'approve_thing',
        description: 'Approves the thing',
        extraProperties: ['custom_action' => true],
    ),
])]
final class Thing
{
}
```

Rules (`Dmstr\ApiPlatformUtils\Metadata\CustomAction::isCustomAction()` is the single source):

- Only the boolean `true` marks. A missing key, `false`, `1`, `'true'` or `'1'` do not; there is no type coercion.
- API Platform 4.x merges `extraProperties` flatly: operation over `#[ApiResource]` over `api_platform.defaults.extra_properties`. A marker on the resource or in the global defaults therefore marks all operations it reaches, and `'custom_action' => false` on an operation exempts that operation from an inherited marker.
- API Platform's `NotExposed` stub is never a custom action, even when it inherits a resource marker.
- The path plays no part: a marked operation is a custom action at any path depth, also on `/things` or `/things/{id}`.
- An unmarked operation stays an ordinary operation at any path depth: it keeps API Platform's own `@type` and `hydra:title`, and `HydraDocumentationSubscriber` gives it its own URL carrier.

Set the marker per operation. On the resource or in the global defaults it also marks the resource's `Get`, `Post`, `Patch` and `Delete`, and a client would offer them as actions.

Naming: the `hydra:title` of a custom action is the operation `name`, taken literally. Name the action via `name` or set `hydraContext['hydra:title']`; without an operation name no title is generated. Keys the author sets in `hydraContext` always win over the generated ones.

What the factory writes into the Hydra context of a custom action:

- `@type`: `["hydra:Operation", "schema:Action"]`
- `hydra:title`: the operation `name` (omitted without one)
- `hydra:expects` / `hydra:returns`: short class name of the input / output class, or `owl:Nothing` without one
- `hydra:description`: the operation `description`, if set
- exactly one URL carrier, built from API prefix, route prefix and URI template with `{._format}` stripped: `@id` when the URL has no placeholder, `hydra:uriTemplate` when it has one (e.g. `/api/things/{id}/approve`). An author-set `@id` or `hydra:uriTemplate` in `hydraContext` is the only carrier then.

There is no fallback based on the path. Up to 0.7 every operation deeper than `/resource` or `/resource/{id}` was treated as a custom action automatically; since 0.8.0 only the marker counts. When upgrading from 0.7 or older, set `'custom_action' => true` on every real action, or it loses `schema:Action` and drops out of the clients' actions menus without an error.

Configuration: the custom actions and the other documented operations get their URL carrier from two separate services, each with its own prefix key. Both prefixes are prepended to the URL carrier.

```yaml
dmstr_api_platform_utils:
    custom_operation_hydra:
        api_prefix: '/api'   # URL carrier of custom actions
    hydra_documentation:
        api_prefix: '/api'   # URL carrier of all other documented operations
```

The inheritance rules are verified for API Platform 4.x only; `^3.0` is allowed by the version constraint but untested.

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

Input and output schemas get the same five extensions: `x-collection`, `x-label-property`, `x-value-property`, `x-search-property` and `x-resource-class`. Forms built from the read schema (resources with serialization groups have no plain schema) get the autocomplete picker, and lists can show and sort a relation by its label (`order[<relation>.<label>]`, offered by `auto_order` only when the label is one of the candidates). The label is the first candidate the target class declares, else its first string property. Properties inside `allOf` are decorated too, which is where JSON-LD output definitions keep them. The resource's own definition gets `x-label-property` as well, so a client can title a record with the same property a relation to it uses.

### Labels in collection responses (`relation_labels`)

The schema names the label property, but a plain relation value is just the IRI. With `relation_labels` enabled, JSON-LD collection responses embed the label next to it:

```json
"parent": { "@id": "/api/ort/einheit/0b1…", "bezeichnung": "Erdgeschoss" }
```

```yaml
dmstr_api_platform_utils:
    relation_labels:
        enabled: true
```

Only collection operations are affected; item responses keep the plain IRI, because forms edit relations as `iri-reference` strings and API Platform rejects nested documents on write. Each embedded label initialises the related entity, one query per distinct related row and page unless the collection query fetch-joins it.

## Invalid defaults in schemas (`schema_default_cleanup`)

API Platform derives a property's `default` in the JSON/OpenAPI schema from the PHP property initializer. `private string $slug = '';` next to a slug `#[Assert\Regex]` therefore produces `default: ""` that the property's own `pattern` rejects, and a form client that fills in schema defaults shows a validation error before anyone typed. With `schema_default_cleanup.enabled` (default `true`), `InvalidDefaultSchemaDecorator` decorates `api_platform.json_schema.schema_factory` and removes a `default` that the property's own schema rejects. Valid defaults are untouched.

Rules:

- Checked keywords: `pattern`, `minLength`, `maxLength`, `enum`. A `default` that violates one of them is removed.
- The cleanup visits every sub-schema reachable through `properties`, `items` (single schema and tuple form), `prefixItems`, `additionalProperties` (when it is a schema), `allOf`, `anyOf` and `oneOf`.
- `default: null` is judged by `enum` only: it is removed when an `enum` exists that does not contain `null`. The typical case is a nullable enum property whose `enum` lacks `null`.
- Patterns are evaluated with PHP's PCRE, with `$` matching only at the very end of the string (ECMA reading: a trailing newline does not match).

Known limits:

- `$ref` is not resolved, so a `default` next to a `$ref` is not checked.
- A pattern PHP cannot compile keeps its default; nothing is removed.
- There is no full translation of ECMA-262 regular expressions to PCRE, so a pattern that relies on ECMA-specific syntax may be judged differently than a browser would judge it.
- Server-side validation is unchanged; only the emitted schema changes.

To switch the cleanup off:

```yaml
dmstr_api_platform_utils:
    schema_default_cleanup:
        enabled: false
```

## License

MIT © diemeisterei GmbH
