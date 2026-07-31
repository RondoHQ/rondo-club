# Removing ACF from Rondo Club — Migration Plan v2

**Status:** Proposal (planning only — no implementation changes yet)

**Date:** 2026-07-31

**Strategy:** Contract first, plugin second

**Builds on:** [remove-acf.md](remove-acf.md) and [remove-acf-usage-inventory.tsv](remove-acf-usage-inventory.tsv)

This version keeps the inventory and hook analysis from the original plan, but changes the migration strategy in two important ways:

1. The public REST contract moves from `acf` to `fields`; preserving `acf` permanently would preserve the naming and ownership collisions that motivate this work.
2. `acf` and `fields` coexist temporarily as separate REST attributes. After every consumer uses `fields`, the public `acf` attribute is removed. ACF may continue to provide the internal storage implementation until each post type is migrated to native WordPress meta access.

---

## 0. Executive summary

ACF currently provides four separate capabilities:

1. PHP field reads and writes (`get_field()`, `update_field()`, and related helpers).
2. The nested `acf` REST request/response attribute.
3. Hooks that run validation and domain side effects.
4. Runtime storage for the user-facing Custom Fields feature.

Those capabilities do not have to be replaced at the same time. The safest sequence is:

1. Define the canonical `fields` contract.
2. Add `fields` alongside `acf`, initially backed by ACF.
3. Move the React app, rondo-sync, tests, exports, and maintenance tools to `fields`.
4. Remove `acf` from the public contract while ACF still works internally.
5. Replace ACF's internal PHP, formatting, hook, and definition-store responsibilities per post type.
6. Remove the plugin when no code path depends on it.

This separates a cross-repository API migration from a high-risk storage implementation rewrite. It also gives rollback boundaries at both layers.

Storage remains WordPress-native throughout:

- Scalar values remain post meta.
- Repeaters retain ACF's existing numbered meta-row layout.
- `relationship_type.inverse_relationship_type` remains term meta.
- Dynamic field definitions move to a schema-versioned WordPress option if the feature is retained.

No static field-value migration is required. The only required data conversion is the export of dynamic field definitions if production uses that feature.

---

## 1. Locked decisions

### D1. The public `acf` contract will be removed

The target API uses `fields`, not `acf`:

```json
{
  "id": 123,
  "status": "publish",
  "title": { "rendered": "Jan Jansen" },
  "fields": {
    "first_name": "Jan",
    "knvb_id": "1234567",
    "datum_vog": "20260731"
  }
}
```

Field keys use snake_case. Existing dashes are normalized:

- `knvb-id` -> `knvb_id`
- `datum-vog` -> `datum_vog`
- `huidig-vrijwilliger` -> `huidig_vrijwilliger`
- `lid-sinds` -> `lid_sinds`

The mapping must be explicit in the field registry. Runtime string replacement is not the source of truth.

### D2. Domain-field ownership is unambiguous

- WordPress resource properties stay top-level: `id`, `title`, `slug`, `status`, `type`, `author`, dates, links, and media fields owned by the standard controller.
- Persisted Rondo domain fields live under `fields`.
- Computed Rondo domain fields also live under `fields` and are marked read-only in the REST schema.
- A domain field is not emitted both top-level and under `fields`.
- A write containing an unknown field is rejected with a field-specific error.

Some resources legitimately have both top-level `status` (the WordPress post lifecycle) and `fields.status` (the Rondo business state). They are different concepts and remain explicitly namespaced.

### D3. No top-level flattening

Domain fields will not be promoted directly onto the resource root. Flattening would create collisions with WordPress properties and make future WordPress changes harder to absorb.

### D4. `acf` and `fields` may coexist temporarily

During the compatibility period:

- GET responses may contain both `acf` and `fields`.
- A write may contain `acf` or `fields`.
- A write containing both is rejected with HTTP 400; the server never guesses which value wins.
- Both write paths must produce the same stored values and side effects.
- `acf` writes are logged as deprecated so remaining consumers can be found.
- Redaction and permission rules are applied identically to both response attributes.

After all known consumers have migrated, `acf` is removed from requests and responses. ACF may remain installed behind `fields` until the internal migration is complete.

### D5. Cutovers are per REST object type, not per ACF field group

WordPress registers one additional REST attribute named `fields` per object type. A post type can have several static and dynamic field groups, but they all contribute to the same attribute.

Therefore, native-provider cutovers are atomic per post type:

- `person` includes person fields, volunteer-policy fields, and active dynamic person fields.
- `team` includes team fields, kickoff fields, and active dynamic team fields.
- `commissie` includes commissie fields and active dynamic commissie fields.

Single-group post types can still migrate in small independent slices.

### D6. wp-admin ACF editing is not replaced

Rondo's editing UI remains the React application. No replacement ACF-style metabox framework will be built.

### D7. Dynamic custom fields require a usage decision

Before implementation, export the production definitions and determine whether the feature is actively used:

- If unused, remove the Settings UI and dynamic-definition subsystem.
- If used, retain it using a schema-versioned WordPress option and the native registry.

Retained dynamic fields require an immutable field ID and immutable storage key. Labels, help text, order, and presentation settings may change. Renaming a label must never orphan stored values.

---

## 2. Why `acf` and `fields` can run side by side

ACF registers an additional REST attribute named `acf` for each applicable post type. Rondo can independently register another attribute:

```php
register_rest_field(
    'person',
    'fields',
    [
        'get_callback'    => $get_fields,
        'update_callback' => $update_fields,
        'schema'          => $fields_schema,
    ]
);
```

There is no naming conflict because `acf` and `fields` are different top-level attributes.

The limitation is narrower: ACF and Rondo cannot independently own different portions of the same `acf` attribute. Registering a second `acf` provider for a post type replaces or conflicts with the first provider at the attribute level. That is why the internal native cutover must be per post type, unless one deliberately builds a hybrid provider that aggregates both backends.

This plan uses side-by-side attributes for the public contract migration, then one `fields` provider that can change its internal backend per post type.

### Compatibility-stage read flow

```text
Stored post meta
      |
      +--> ACF formatter ----------> response.acf
      |
      +--> fields compatibility mapper
             |- reads through ACF initially
             |- renames keys through the registry
             `- applies the same redaction
                                      \
                                       -> response.fields
```

### Compatibility-stage write flow

```text
request.acf -----------------------> existing ACF write path

request.fields -> validate canonical schema
               -> map canonical names to legacy storage names
               -> existing ACF write path

request.acf + request.fields ------> HTTP 400 ambiguous_field_payload
```

Once `acf` is removed publicly, `request.fields` continues to use ACF internally until that post type's native provider is ready.

---

## 3. Target REST contract

### 3.1 Canonical naming

Every field has registry metadata for:

- Canonical API name (`knvb_id`).
- Legacy ACF/storage name (`knvb-id`).
- Owning post type or taxonomy.
- Type and nested sub-fields.
- Read/write visibility.
- Capability and sensitivity classification.
- Default and empty-value behavior.
- Validation and sanitization.
- Formatting and return shape.
- Whether it is computed/read-only.

Nested repeater keys also receive explicit mappings. For example:

```json
{
  "fields": {
    "work_history": [
      {
        "team_id": 45,
        "entity_type": "team",
        "job_title": "Trainer",
        "start_date": "20240801",
        "end_date": null,
        "is_current": true
      }
    ]
  }
}
```

### 3.2 Partial writes

`fields` writes are partial updates. Callers do not need to round-trip the complete object merely to change one value.

Rules:

- Omitted field: leave unchanged.
- `null`: clear the field when the schema allows it.
- Empty string: retain only where it is a valid field value; nullable enum fields normalize it to `null`.
- Empty repeater array: remove all rows and update the repeater count consistently.
- Read-only field in a request: reject with HTTP 400.

### 3.3 Error contract

Validation errors identify the canonical field path:

```json
{
  "code": "rondo_invalid_field",
  "message": "Invalid field value.",
  "data": {
    "status": 400,
    "field": "fields.work_history.0.start_date"
  }
}
```

Both legacy and canonical writes must use the same domain validators during coexistence.

### 3.4 Filtering and projections

- `_fields=fields.knvb_id` must work wherever `_fields=acf.knvb-id` worked previously.
- Filter and order parameters move to canonical snake_case names.
- Redaction happens before serialization and cannot be bypassed through collection, single-item, embedded, or hand-built Rondo endpoints.

---

## 4. Phase A — Safety net and contract definition

### A1. Freeze the mapping

- Extend the existing old/new contract document into a complete machine-readable registry.
- Include static fields, nested sub-fields, computed fields, and active production dynamic fields.
- Detect canonical-name collisions during registry boot and fail loudly in development and CI.

### A2. Export production dynamic definitions

Run a read-only export before touching the Custom Fields subsystem. Record:

- Field ID/key and storage name.
- Label and type.
- Active state and order.
- Choices, defaults, required/unique settings, and type-specific configuration.
- Counts of posts with a populated value for each field.

The population report determines whether D7 uses the retain or remove branch.

### A3. Contract tests

Build canonical structural tests rather than raw JSON byte snapshots. Test:

- GET schema and formatted values for every supported field type.
- Empty, null, false, zero, and absent values.
- Partial writes and write echoes.
- Repeaters with zero, one, and several rows.
- Media, post-object, relationship, and taxonomy return shapes.
- Person redaction for every capability class.
- Collection, single, embedded, filtered, household, kaderlijst, and entity endpoints.
- `_fields` projections.
- A write containing both `acf` and `fields` is rejected.

Tests may canonicalize object-key ordering, volatile timestamps, and `_links`, but may not ignore field values or permissions.

### A4. Fix independent security issues

Do not wait for the migration to fix existing authorization or allowlist problems. In particular:

- Allowlist writable relationship-type fields instead of looping over arbitrary request keys.
- Audit hand-built `get_fields()` responses against the same visibility policy as standard endpoints.
- Verify rather than assume whether `_shared_with` can be returned by any ACF field enumeration.

---

## 5. Phase B — Add the `fields` compatibility contract

### B1. Introduce the registry and name mapper

The first registry implementation may delegate formatting and persistence to ACF. Its immediate responsibility is stable ownership and name mapping, not plugin removal.

It must support:

- Post and taxonomy contexts.
- Field-name and legacy field-key lookups.
- Dynamic field names used by imports and CLI commands.
- Duplicate names scoped by post type (`contact_info`, `status`, `person`, `website`, `gender`).
- Explicit failure for unresolved or ambiguous fields.

### B2. Register `fields` beside `acf`

For each REST-enabled object type:

- Keep ACF's existing `acf` provider active.
- Register Rondo's new `fields` provider.
- Initially read and write through ACF so hook behavior stays unchanged.
- Apply canonical key mapping and one central redaction policy.
- Add a deprecation signal when `acf` is submitted. Server logs must identify the route and authenticated integration/user without logging sensitive values.

### B3. Prove equivalence

For every field and fixture:

- Normalize `response.acf` through the registry and compare it structurally with `response.fields`.
- Write through `acf`, read through `fields`.
- Write through `fields`, read through `acf`.
- Assert identical stored meta and side effects.

Approved differences are limited to canonical names and intentional bug/security fixes recorded in the contract.

---

## 6. Phase C — Consumer migration

Migrate consumers while both attributes exist.

### C1. React application

- Replace `.acf` reads with `.fields`.
- Replace `acf:` write payloads with `fields:`.
- Remove full-object round trips; send only changed fields.
- Replace ACF-specific enum sanitizers when the canonical schema accepts nullable values correctly.
- Update query keys, sort fields, forms, and optimistic cache updates.

### C2. rondo-sync

- Move all reads, writes, and `_fields` projections to `fields`.
- Apply the explicit legacy-to-canonical map.
- Update state-transition fields and relationship merging.
- Update every maintenance script, not only the main sync pipeline.
- Run a complete demo sync twice: once with no changes expected and once with controlled updates in both directions.

### C3. Theme tooling and tests

- Update exports/imports and public data contracts to canonical names where they consume REST-shaped data.
- Update CLI commands and fixtures that construct `acf` payloads.
- Keep storage-oriented tools on the shared field API rather than making them depend on REST naming.

### C4. Exit gate

Do not remove `acf` until:

- Repository searches find no production consumer of `.acf`, `acf:`, or `_fields=acf` outside the compatibility layer and explicit legacy tests.
- rondo-sync and all of its tools have shipped.
- Deprecation logging shows no `acf` writes for an agreed observation period.
- Demo and production smoke tests pass using `fields` only.

---

## 7. Phase D — Remove the public `acf` contract

Once the exit gate passes:

- Stop emitting `acf`.
- Reject `acf` writes with HTTP 400 and an actionable error identifying `fields` as the replacement.
- Keep ACF installed temporarily as the internal implementation behind `fields`.
- Remove legacy-name knowledge from consumers, but retain it in the storage registry until the plugin removal is complete.

Rollback is limited to restoring the compatibility provider; stored data is unchanged.

This is the milestone where the application-level ACF/non-ACF collision ends. Plugin removal is no longer on the critical path for consumer correctness.

---

## 8. Phase E — Replace ACF internally

### E1. Shared PHP field API

Introduce a context-aware API, initially backed by ACF:

```php
Fields::get_for_post( $post_id, $canonical_name );
Fields::update_for_post( $post_id, $canonical_name, $value );
Fields::delete_for_post( $post_id, $canonical_name );
Fields::get_for_term( $taxonomy, $term_id, $canonical_name );
Fields::update_for_term( $taxonomy, $term_id, $canonical_name, $value );
```

Avoid an untyped `$post_id` argument that sometimes contains strings such as `relationship_type_123`.

Migrate named call sites mechanically, then review dynamic field-name and field-key sites individually.

### E2. Native schema and formatting layer

Generate initial definitions from `acf-json/`, then maintain them as PHP configuration. The registry must include all behavior required for parity:

- Type, defaults, required, nullability, choices, min/max, and multiplicity.
- Date/time display, storage, and return formats.
- Media, post-object, taxonomy, and relationship return shapes.
- Repeater sub-fields and row formatting.
- Sensitivity, capabilities, editability, and read-only computed values.
- Sanitizers, validators, and domain callbacks.

Use `register_post_meta()` for exact scalar keys where its sanitization and authorization hooks help. Do not expose duplicate values under WordPress's `meta` response property. Repeater sub-keys are dynamic and must be sanitized by the repeater service rather than relying on wildcard meta registration.

### E3. Repeater helper

Keep the existing storage layout:

```text
addresses                     -> row count
addresses_0_city              -> value
addresses_0_postal_code       -> value
work_history_0_job_title      -> value
```

The helper must:

- Read/write the entire logical repeater.
- Remove stale rows when the new row count shrinks.
- Preserve raw-SQL consumers during the transition.
- Apply nested sanitization once per logical update.
- Avoid firing expensive side effects separately for every sub-row.
- Provide old and new logical values to domain services that need diffs.

### E4. Move hooks to domain services

Use the hook analysis from the original plan, with these requirements:

- Extract inverse relationships into an atomic `RelationshipService::set_relationships()` workflow that reads old, validates, writes, and synchronizes inverses.
- Preserve re-entrancy protection and deletion detection.
- Capture old address-family state before repeater writes.
- Move normalization to shared sanitizers.
- Move uniqueness checks to request-aware validators with correct self-exclusion.
- Keep side-effect ordering explicit and tested.

### E5. Cut over per object type

The `fields` response contract does not change. Only its backend changes from ACF to native storage helpers.

Recommended order:

1. `taakuitleg`
2. `rondo_todo`
3. `stadion_feedback`
4. `dienst_type`, `shift_template`, and `dienst_shift`
5. `discipline_case`
6. `rondo_invoice`
7. `team` including kickoff and dynamic team fields
8. `commissie` including dynamic commissie fields
9. `relationship_type`
10. `person` including volunteer policy, dynamic person fields, repeaters, redaction, and inverse relationships

Each cutover requires:

- Native field tests that run without ACF.
- Contract tests against the unchanged `fields` response.
- Storage-layout assertions.
- Domain-side-effect tests.
- Deployment and an explicit observation window.

---

## 9. Phase F — Dynamic custom fields

If D7 retains the feature:

- Store definitions in a dedicated, schema-versioned WordPress option per site.
- Give each definition an immutable ID and immutable storage key.
- Reject canonical-name collisions with static or other dynamic fields.
- Keep soft deletion; never delete stored values automatically.
- Provide export/import and backup tooling for definitions.
- Define migrations between option schema versions.
- Use the same registry, validation, redaction, and REST provider as static fields.
- Add full CRUD, reorder, deactivate/reactivate, uniqueness, permissions, and value-round-trip tests.

The production export is seed/migration input, not a permanent Git source of truth. Definitions created later through the UI remain site data in WordPress.

If D7 removes the feature:

- Export definitions and population counts first.
- Obtain explicit product approval.
- Remove the Settings UI and custom-field endpoints.
- Preserve populated values unless a separately approved cleanup removes them.

---

## 10. Phase G — Remove the ACF plugin

Only after every `fields` provider is native:

1. Add a full CI job that boots the theme without ACF and runs the entire PHP suite.
2. Run demo without ACF and exercise all CRUD, sync, invoice, Mollie, VOG/IVA, membership pass, volunteer, relationship, import/export, and custom-field flows.
3. Remove the ACF dependency check and JSON load/save hooks.
4. Remove ACF-specific hooks, helpers, test bootstrap, CI download, and license secret.
5. Remove `acf-json/` after confirming the PHP registry contains every required definition.
6. Deactivate ACF on production during a controlled deployment window.
7. Verify production and rondo-sync before deleting the plugin files.
8. Keep the database backup, registry export, and rollback release for the agreed rollback window.
9. Optionally sweep `_field` reference rows only as a separate dry-run-first maintenance task.

Rollback consists of restoring the prior release and reactivating ACF. Because field-value storage remains compatible, no reverse data migration is required.

---

## 11. Test matrix

| Layer | Required coverage |
|---|---|
| Contract coexistence | `acf` read, `fields` read, either write path, both-write rejection, key mapping |
| Canonical schema | Types, null/empty/default behavior, partial writes, unknown/read-only fields |
| Permissions | Person field redaction and write scope across every capability class |
| Repeaters | Add/change/remove/reorder rows and stale-row cleanup |
| Relationships | Add/change/remove, inverse types, sibling cascade, re-entrancy |
| Raw-meta consumers | Household/family calculations, team role queries, capability queries |
| Financial | Invoice totals, line items, PDFs, e-mail, Mollie links/webhooks |
| Dynamic fields | Definition CRUD, stable storage keys, values, uniqueness, soft deletion |
| External | React build/e2e smoke tests and rondo-sync demo pipelines |
| ACF-less | Focused native tests from the first native slice; full suite before plugin deactivation |

---

## 12. Observability and rollback

Every migration phase must define what “watch” means:

- Count deprecated `acf` writes by route and consumer identity.
- Count rejected unknown, read-only, and ambiguous field writes.
- Log registry resolution failures without values.
- Compare error rates for affected REST routes before and after deployment.
- Verify representative records after every per-CPT cutover.
- For repeaters, report row counts and stale-row deletions in migration diagnostics.

Rollback boundaries:

- Compatibility provider: remove `fields`; `acf` remains unchanged.
- Consumer migration: revert that consumer to `acf` while coexistence remains active.
- Public `acf` removal: restore the compatibility provider.
- Per-CPT native cutover: switch that CPT's `fields` backend to ACF.
- Plugin removal: restore the prior release and reactivate ACF.

No rollback path depends on rewriting field values.

---

## 13. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Consumer writes both contracts with conflicting values | High | Reject mixed payloads; never define precedence |
| Key mapping drift | High | Machine-readable registry, collision checks, cross-read/write tests |
| Redaction differs between `acf` and `fields` | High | One central policy and capability matrix tests |
| Inverse relationship deletion detection breaks | Highest | Dedicated atomic service and regression suite |
| Formatted values drift after native cutover | High | Type-specific structural contract tests |
| Dynamic definitions or values are orphaned | High if feature is used | Production export, immutable storage keys, option migrations |
| ACF fallback masks incomplete native code | Medium | Focused ACF-less tests per slice; full ACF-less gate before removal |
| Repeater stale rows or excessive hook execution | Medium | Logical repeater service, layout assertions, batched side effects |
| Cross-repository migration is incomplete | Medium | Deprecation telemetry and an explicit zero-usage exit gate |
| Project cost exceeds the value of removing ACF | Medium | Decide D7 early; stop after the contract migration if it resolves the operational problem sufficiently |

---

## 14. Effort and milestones

Planning range, assuming one developer working roughly half-time:

| Milestone | Half-time working weeks |
|---|---:|
| Safety net, mapping, and production dynamic-field export | 2–3 |
| `fields` compatibility provider and coexistence tests | 2–3 |
| React, rondo-sync, tools, and test consumer migration | 2–3 |
| Public `acf` removal | 0.5–1 |
| Shared PHP field API and call-site migration | 1.5–2 |
| Native registry, formatter, REST provider, and repeater service | 3–4 |
| Simple/medium object-type cutovers | 2–3 |
| Invoice cutover | 1–2 |
| Dynamic fields | 0.5 if removed; 2–3 if retained |
| Person, term meta, and relationship service | 3–4 |
| ACF-less rollout, cleanup, and documentation | 1–2 |

Total planning range:

- Dynamic fields removed: approximately 18–23 half-time working weeks.
- Dynamic fields retained: approximately 20–26 half-time working weeks.

The contract migration produces value before plugin removal: the application stops exposing or consuming `acf`, field ownership becomes explicit, partial writes become possible, and collision-prone dual models disappear from consumer code.

---

## 15. Completion criteria

The project is complete only when:

- No public request or response contains `acf`.
- React, rondo-sync, and maintenance tools use canonical `fields` names only.
- No production PHP code calls ACF APIs or registers ACF hooks.
- Every REST-enabled object type uses the native `fields` provider.
- The complete PHP suite runs without ACF installed.
- Dynamic field definitions are either migrated with stable identities or explicitly retired.
- Production and demo no longer load ACF.
- Documentation describes the native registry, storage layout, REST contract, field creation workflow, and testing strategy.
- The optional REST rename document is no longer “future work”; its final mappings match the implemented registry.
