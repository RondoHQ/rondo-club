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
    "datum_vog": "2026-07-31"
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
- A domain field is not emitted both top-level and under `fields`.
- A write containing an unknown field is rejected with a field-specific error.

The existing computed response fields are grandfathered at the top level and remain read-only:

- `is_deceased`
- `birth_year`
- `player_count`
- `staff_count`
- `member_count`

Moving these fields would expand the consumer migration without helping remove the `acf` namespace. Their names are reserved so static or dynamic fields cannot reuse them. New computed domain fields default to read-only properties under `fields` unless a separate contract decision intentionally places one at the top level.

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
        "start_date": "2024-08-01",
        "end_date": null,
        "is_current": true
      }
    ]
  }
}
```

### 3.2 Canonical relationship shape

`fields.relationships` has one stable read shape:

```json
{
  "related_person_id": 123,
  "relationship_type_id": 7,
  "relationship_label": "Ouder",
  "person_name": "Jan Jansen",
  "person_thumbnail": "https://example.org/photo.jpg",
  "relationship_name": "Ouder van",
  "relationship_slug": "ouder-van"
}
```

Write requests may contain only:

- `related_person_id`
- `relationship_type_id`
- `relationship_label`

The enriched name, thumbnail, and relationship-type properties are read-only. They are produced after visibility checks and are never persisted as repeater values. Sending a read-only relationship property in a write returns a field-specific HTTP 400 error.

This replaces the current asymmetric, undocumented contract in which reads are enriched after ACF serialization while writes expect the raw IDs and label.

### 3.3 Date and time wire formats

The new contract does not expose ACF storage formats:

- Date-only values: `YYYY-MM-DD`.
- Date/time values: RFC 3339 with an explicit UTC `Z` or numeric offset.
- Time-only values: `HH:mm`.
- Empty date/time values: `null`.

Storage remains unchanged (`Ymd` and the existing WordPress/ACF meta formats). The registry parses storage values and formats the canonical wire value in both the compatibility and native providers. This removes the need for consumers to understand both `YYYYMMDD` and `YYYY-MM-DD`.

The timezone policy for each existing date/time field must be recorded in the registry before its contract is enabled. Tests must cover daylight-saving transitions for local club times and UTC timestamps.

### 3.4 Partial writes

`fields` writes are partial updates. Callers do not need to round-trip the complete object merely to change one value.

Rules:

- Omitted field: leave unchanged.
- `null`: clear the field when the schema allows it.
- Empty string: retain only where it is a valid field value; nullable enum fields normalize it to `null`.
- Empty repeater array: remove all rows and update the repeater count consistently.
- Read-only field in a request: reject with HTTP 400.

### 3.5 Error contract

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

### 3.6 Filtering, sorting, and projections

- `_fields=fields.knvb_id` must work wherever `_fields=acf.knvb-id` worked previously.
- Filter and order parameters move to canonical snake_case names according to an explicit mapping table.
- Redaction happens before serialization and cannot be bypassed through collection, single-item, embedded, or hand-built Rondo endpoints.

Initial `people/filtered` sort mappings:

| Legacy `orderby` | Canonical `orderby` |
|---|---|
| `custom_knvb-id` | `field_knvb_id` |
| `custom_type-lid` | `field_type_lid` |
| `custom_datum-vog` | `field_datum_vog` |
| `custom_huidig-vrijwilliger` | `field_huidig_vrijwilliger` |
| `custom_financiele-blokkade` | `field_financiele_blokkade` |
| `custom_lid-sinds` | `field_lid_sinds` |
| `custom_{dynamic-storage-name}` | `field_{canonical_name}` |

The complete table must cover every hard-coded and dynamic sort/filter identifier before Phase C. URL query state and saved/bookmarked links receive the same compatibility treatment as persisted settings.

---

## 4. Phase A — Safety net and contract definition

### A1. Freeze the mapping

- Extend the existing old/new contract document into a complete machine-readable registry.
- Include static fields, nested sub-fields, the canonical relationship shape, date/time wire formats, the grandfathered top-level computed fields, and active production dynamic fields.
- Detect canonical-name collisions during registry boot and fail loudly in development and CI.
- Reserve WordPress top-level properties and the grandfathered computed-field names against static and dynamic field registration.

### A2. Inventory persisted field-name references

Field names are stored outside entity payloads. Before enabling canonical names, inventory every persisted reference in this repository, rondo-sync, and production data.

Confirmed stores include:

- `rondo_people_list_preferences` user meta.
- `rondo_people_list_column_order` user meta.
- Keys inside `rondo_people_list_column_widths` user meta.
- Browser `localStorage`, including `stadion_column_widths` and `rondo-col-*` table-visibility values where column IDs are field-derived.
- URL query parameters and bookmarked filter/sort links.

The audit must also confirm whether any of these contain field names before adding migration logic:

- Dashboard or report settings.
- Saved Google Sheets/export definitions and scheduled exports.
- Transients, options, cron arguments, import mappings, and demo fixtures.
- rondo-sync state, fixtures, and rarely run `tools/` scripts.

The Google Sheets endpoint described in the February contract is not present in the current theme checkout. Determine whether it was removed or moved to another repository; do not migrate a documented-but-dead endpoint blindly.

Produce a persisted-reference report listing the store, owner, legacy identifiers, canonical identifiers, population count, and migration strategy.

### A3. Export production dynamic definitions

Run a read-only export before touching the Custom Fields subsystem. Record:

- Field ID/key and storage name.
- Label and type.
- Active state and order.
- Choices, defaults, required/unique settings, and type-specific configuration.
- Counts of posts with a populated value for each field.

The population report determines whether D7 uses the retain or remove branch.

### A4. Contract tests

Build canonical structural tests rather than raw JSON byte snapshots. Test:

- GET schema and formatted values for every supported field type.
- Empty, null, false, zero, and absent values.
- Partial writes and write echoes.
- Repeaters with zero, one, and several rows.
- Canonical relationship reads plus rejection of enriched/read-only relationship properties on write.
- Date, date/time, and time normalization, including nulls and timezone transitions.
- Media, post-object, relationship, and taxonomy return shapes.
- Grandfathered computed fields remaining top-level and read-only.
- Person redaction for every capability class.
- Collection, single, embedded, filtered, household, kaderlijst, and entity endpoints.
- `_fields` projections.
- A write containing both `acf` and `fields` is rejected.
- Persisted identifiers migrating without resetting visibility, ordering, or widths.

Tests may canonicalize object-key ordering, volatile timestamps, and `_links`, but may not ignore field values or permissions.

### A5. Fix independent security issues

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

For each standard WordPress REST object type:

- Keep ACF's existing `acf` provider active.
- Register Rondo's new `fields` provider.
- Initially read and write through ACF so hook behavior stays unchanged.
- Apply canonical key mapping and one central redaction policy.
- Add a deprecation signal when `acf` is submitted. Server logs must identify the route and authenticated integration/user without logging sensitive values.

`register_rest_field()` does not affect hand-built response arrays. Phase B2 therefore also owns manual dual-emission for every custom response that currently embeds an ACF blob or field-derived structure, including:

- `GET /rondo/v1/people/filtered`
- `GET /rondo/v1/entity/{id}`
- Household responses
- `GET /rondo/v1/kaderlijst/people`
- Dashboard, search, and timeline responses identified by the inventory

These endpoints must use shared serializers and the same redaction policy; they may not grow independent one-off ACF-to-fields mappers. `people/filtered` also implements the complete legacy/canonical filter and `orderby` mapping from §3.6.

### B3. Prove equivalence

For every field and fixture:

- Normalize `response.acf` through the registry and compare it structurally with `response.fields`.
- Write through `acf`, read through `fields`.
- Write through `fields`, read through `acf`.
- Assert identical stored meta and side effects.

Approved differences are limited to canonical names and intentional bug/security fixes recorded in the contract.

---

## 6. Parallel workstream P — Shared PHP field API

This workstream begins as soon as A1 freezes the canonical mapping. It does not wait for public `acf` removal and runs in coordination with Phase C.

Introduce a context-aware PHP API, initially backed by ACF:

```php
Fields::get_for_post( $post_id, $canonical_name );
Fields::update_for_post( $post_id, $canonical_name, $value );
Fields::delete_for_post( $post_id, $canonical_name );
Fields::get_for_term( $taxonomy, $term_id, $canonical_name );
Fields::update_for_term( $taxonomy, $term_id, $canonical_name, $value );
```

Avoid an untyped `$post_id` argument that sometimes contains strings such as `relationship_type_123`.

Migrate named call sites mechanically, then review dynamic field-name and field-key sites individually. The React/REST contract migration and PHP codemod touch overlapping controllers and tests, so they must share an integration order rather than proceeding as unrelated branches. With multiple developers the workstreams can reduce elapsed calendar time; with one developer they primarily improve sequencing and do not reduce total effort.

---

## 7. Phase C — Consumer migration

Migrate consumers while both attributes exist.

### C1. React application

- Replace `.acf` reads with `.fields`.
- Replace `acf:` write payloads with `fields:`.
- Remove full-object round trips; send only changed fields.
- Replace ACF-specific enum sanitizers when the canonical schema accepts nullable values correctly.
- Update query keys, sort fields, forms, and optimistic cache updates.
- Keep the grandfathered computed fields at their existing top-level paths.
- Remove mixed-format date parsing only after all relevant responses use the canonical formats.
- Migrate field-derived localStorage keys and values once, preserving column visibility and widths.

### C2. rondo-sync

- Move all reads, writes, and `_fields` projections to `fields`.
- Apply the explicit legacy-to-canonical map.
- Update state-transition fields and relationship merging.
- Normalize date-only, date/time, and time values at the integration boundary.
- Update every maintenance script, not only the main sync pipeline.
- Run a complete demo sync twice: once with no changes expected and once with controlled updates in both directions.

### C3. Persisted references, theme tooling, and tests

- Update exports/imports and public data contracts to canonical names where they consume REST-shaped data.
- Update CLI commands and fixtures that construct `acf` payloads.
- Keep storage-oriented tools on the shared field API rather than making them depend on REST naming.
- Implement one persisted-name migration service backed by the canonical registry; do not duplicate mapping tables in user settings, exports, and the frontend.
- Bump `LIST_PREFERENCES_VERSION` and migrate all three people-list user-meta structures on read, including associative width keys.
- Add a dry-run WP-CLI audit/migration command that reports users and settings changed before applying bulk migration.
- Add a one-time frontend migration for field-derived localStorage identifiers.
- Accept legacy URL sort/filter aliases for a bounded compatibility period, while generating only canonical URLs.
- Migrate populated saved export/report configurations identified by A2; do not silently discard unknown identifiers.
- Confirm dashboard card settings are field-name independent before excluding them from migration.

### C4. Exit gate

Do not remove `acf` until:

- Repository searches find no production consumer of `.acf`, `acf:`, or `_fields=acf` outside the compatibility layer and explicit legacy tests.
- rondo-sync and all of its tools have shipped.
- Deprecation logging shows no `acf` writes for an agreed observation period.
- Demo and production smoke tests pass using `fields` only.
- The persisted-reference migration report contains no unresolved populated legacy identifiers.

---

## 8. Phase D — Remove the public `acf` contract

Once the exit gate passes:

- Stop emitting `acf`.
- Reject `acf` writes with HTTP 400 and an actionable error identifying `fields` as the replacement.
- Keep ACF installed temporarily as the internal implementation behind `fields`.
- Remove legacy-name knowledge from consumers, but retain it in the storage registry until the plugin removal is complete.

Rollback is limited to restoring the compatibility provider; stored data is unchanged.

This is the milestone where the application-level ACF/non-ACF collision ends. Plugin removal is no longer on the critical path for consumer correctness.

---

## 9. Phase E — Replace ACF internally

The native provider work begins after the public contract and shared PHP API are stable. Unlike workstream P, the per-object-type backend cutovers follow Phase D so consumers have only one public contract while the implementation changes underneath it.

### E1. Native schema and formatting layer

Generate initial definitions from `acf-json/`, then maintain them as PHP configuration. The registry must include all behavior required for parity:

- Type, defaults, required, nullability, choices, min/max, and multiplicity.
- Date/time storage formats and the canonical wire formats from §3.3.
- Media, post-object, taxonomy, and the canonical relationship return shape from §3.2.
- Repeater sub-fields and row formatting.
- Sensitivity, capabilities, editability, the grandfathered top-level computed fields, and new read-only computed values.
- Sanitizers, validators, and domain callbacks.

Use `register_post_meta()` for exact scalar keys where its sanitization and authorization hooks help. Do not expose duplicate values under WordPress's `meta` response property. Repeater sub-keys are dynamic and must be sanitized by the repeater service rather than relying on wildcard meta registration.

### E2. Repeater helper

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

### E3. Move hooks to domain services

Use the hook analysis from the original plan, with these requirements:

- Extract inverse relationships into an atomic `RelationshipService::set_relationships()` workflow that reads old, validates, writes, and synchronizes inverses.
- Preserve re-entrancy protection and deletion detection.
- Capture old address-family state before repeater writes.
- Move normalization to shared sanitizers.
- Move uniqueness checks to request-aware validators with correct self-exclusion.
- Keep side-effect ordering explicit and tested.

### E4. Cut over per object type

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

## 10. Phase F — Dynamic custom fields

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

## 11. Phase G — Remove the ACF plugin

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

## 12. Test matrix

| Layer | Required coverage |
|---|---|
| Contract coexistence | `acf` read, `fields` read, either write path, both-write rejection, key mapping |
| Canonical schema | Types, null/empty/default behavior, partial writes, unknown/read-only fields |
| Computed fields | Grandfathered fields remain top-level, stable, and read-only |
| Dates and times | Canonical formats, storage conversion, nulls, timezone and DST boundaries |
| Permissions | Person field redaction and write scope across every capability class |
| Repeaters | Add/change/remove/reorder rows and stale-row cleanup |
| Relationships | Canonical read shape, writable subset, add/change/remove, inverse types, sibling cascade, re-entrancy |
| Persisted references | User meta, localStorage, URL aliases, exports, dry-run and idempotent migrations |
| Raw-meta consumers | Household/family calculations, team role queries, capability queries |
| Financial | Invoice totals, line items, PDFs, e-mail, Mollie links/webhooks |
| Dynamic fields | Definition CRUD, stable storage keys, values, uniqueness, soft deletion |
| External | React build/e2e smoke tests and rondo-sync demo pipelines |
| ACF-less | Focused native tests from the first native slice; full suite before plugin deactivation |

---

## 13. Observability and rollback

Every migration phase must define what “watch” means:

- Count deprecated `acf` writes by route and consumer identity.
- Count rejected unknown, read-only, and ambiguous field writes.
- Log registry resolution failures without values.
- Count migrated and unresolved persisted legacy identifiers by storage location.
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

## 14. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Consumer writes both contracts with conflicting values | High | Reject mixed payloads; never define precedence |
| Key mapping drift | High | Machine-readable registry, collision checks, cross-read/write tests |
| Redaction differs between `acf` and `fields` | High | One central policy and capability matrix tests |
| Hand-built endpoints keep emitting legacy or unredacted shapes | High | B2 explicitly owns every custom serializer; shared serializer tests cover each route |
| Persisted field-name references are silently discarded | High | A2 inventory, versioned user-meta/localStorage migration, dry-run report, unresolved-ID exit gate |
| Date/time normalization changes meaning or timezone | High | Explicit per-field timezone policy, canonical formats, storage/wire round-trip and DST tests |
| Inverse relationship deletion detection breaks | Highest | Dedicated atomic service and regression suite |
| Formatted values drift after native cutover | High | Type-specific structural contract tests |
| Dynamic definitions or values are orphaned | High if feature is used | Production export, immutable storage keys, option migrations |
| ACF fallback masks incomplete native code | Medium | Focused ACF-less tests per slice; full ACF-less gate before removal |
| Repeater stale rows or excessive hook execution | Medium | Logical repeater service, layout assertions, batched side effects |
| Cross-repository migration is incomplete | Medium | Deprecation telemetry and an explicit zero-usage exit gate |
| Project cost exceeds the value of removing ACF | Medium | Decide D7 early and reforecast after A2; stopping after Phase D explicitly means retaining ACF indefinitely and does not complete the original removal brief |

---

## 15. Milestone outcomes

The public-contract migration and plugin removal are separate outcomes:

| Milestone | What has been achieved | What still remains |
|---|---|---|
| Phases A–B | `fields` exists beside `acf`; mappings and compatibility behavior are tested | Every consumer and all internal ACF dependencies remain |
| Phase C + workstream P | React, rondo-sync, persisted settings, tools, and PHP callers use canonical names/shared APIs | The public `acf` compatibility contract and ACF implementation remain |
| Phase D | The application no longer exposes or consumes the `acf` REST shape | ACF JSON, hooks, formatting, field definitions, plugin code, license, and ACF-backed field API remain |
| Phases E–F | Every `fields` backend and retained dynamic definition is native | Plugin/test/CI/deployment cleanup remains |
| Phase G | ACF is absent from code, CI, demo, and production | Project complete |

Stopping after Phase D is a valid product choice only if Rondo is willing to keep ACF indefinitely as an internal implementation. It solves the consumer collision but does not deliver the original ACF-removal brief.

The contract rename adds material work compared with a byte-compatible plugin-only removal. That cost is intentional: it removes the collision-prone public model rather than preserving it.

---

## 16. Effort and scheduling

Planning range, assuming one developer working roughly half-time:

| Milestone | Half-time working weeks |
|---|---:|
| Safety net, mapping, persisted-reference inventory, and dynamic-field export | 2–3 |
| `fields` compatibility provider and coexistence tests | 2–3 |
| React migration, including partial mutations and optimistic caches | 3–4 |
| rondo-sync, persisted settings, URLs, exports, tools, and fixtures | 2–3 |
| Public `acf` removal | 0.5–1 |
| Shared PHP field API and call-site migration (overlaps Phase C in elapsed time) | 1.5–2 |
| Native registry, formatter, REST provider, and repeater service | 3–4 |
| Simple/medium object-type cutovers | 2–3 |
| Invoice cutover | 1–2 |
| Dynamic fields | 0.5 if removed; 2–3 if retained |
| Person, term meta, and relationship service | 3–4 |
| ACF-less rollout, cleanup, and documentation | 1–2 |

Total planning range:

- Dynamic fields removed: approximately 21–30 half-time developer-weeks.
- Dynamic fields retained: approximately 23–33 half-time developer-weeks.

These are effort ranges, not a promise of elapsed calendar time. Workstream P can overlap Phase C when contributors are available, but a single developer still performs the same work and must account for overlapping files. Reforecast after A2 establishes the number of populated persisted references and after the first React slice measures the real optimistic-update cost.

The contract migration produces value before plugin removal: the application stops exposing or consuming `acf`, field ownership becomes explicit, partial writes become possible, and collision-prone dual models disappear from consumer code.

---

## 17. Completion criteria

The project is complete only when:

- No public request or response contains `acf`.
- React, rondo-sync, and maintenance tools use canonical `fields` names only.
- Existing top-level computed fields remain compatible and no registered field collides with their reserved names.
- Date, date/time, and time values use the canonical wire formats while storage remains compatible.
- Relationship reads and writes follow the pinned canonical/read-only shape.
- User meta, localStorage, URL aliases, and populated export/report settings contain no unresolved legacy field identifiers.
- No production PHP code calls ACF APIs or registers ACF hooks.
- Every REST-enabled object type uses the native `fields` provider.
- The complete PHP suite runs without ACF installed.
- Dynamic field definitions are either migrated with stable identities or explicitly retired.
- Production and demo no longer load ACF.
- Documentation describes the native registry, storage layout, REST contract, field creation workflow, and testing strategy.
- The optional REST rename document is no longer “future work”; its final mappings match the implemented registry.
