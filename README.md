<!-- file generated with AI assistance: Claude Code - 2026-06-09 19:27:00 UTC -->

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

## License

MIT © diemeisterei GmbH
