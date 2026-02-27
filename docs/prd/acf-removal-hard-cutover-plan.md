# ACF Removal Plan (Hard Cutover)

## Scope and Decisions (Locked)

- Hard cutover only (no dual mode, no feature flags).
- Downtime + migration window is acceptable.
- `acf` payload will be removed.
- No settings UI for custom fields; definitions are fully code-based.
- Existing meta keys do not need to be preserved if migration is cleaner.

## Current Baseline (Already Completed)

### Completed cleanup before cutover

- Legacy VOG write `vog-email-verzonden` removed from API write path.
- VOG UI/status now uses `vog_email_sent_date`.
- `relatiecode` replaced with `knvb-id` in active export/sync paths.
- `industry` removed from active backend/frontend/test paths.
- Demo import keeps one compatibility fallback:
  - `knvb-id` reads from legacy fixture key `relatiecode` when present.

### Database reality checks (production snapshot)

- `knvb-id`: widely populated (active field).
- `relatiecode`: no populated rows.
- `vog_email_sent_date`: populated.
- `vog-email-verzonden`: no populated rows.
- `factuur-adres`, `factuur-email`, `factuur-referentie`: currently no populated rows found.
- `werkfuncties` as person meta: no populated rows found.

## Target Data Contract (Old -> New)

### API shape

- Old: `GET /wp/v2/people/{id}` returns fields under `acf`.
- New: same values exposed under `fields`, no `acf` object.
- Consumer impact: `rondo-sync` must switch to new keys (single consumer today).

### Key field mappings

- Canonical rule: normalize ACF dash keys to snake_case.
- Examples:
  - `acf['knvb-id']` -> `fields.knvb_id`
  - `acf['freescout-id']` -> `fields.freescout_id`
  - `acf['datum-vog']` -> `fields.datum_vog`
  - `acf['huidig-vrijwilliger']` -> `fields.huidig_vrijwilliger`
  - `acf['lid-sinds']` -> `fields.lid_sinds`
  - `acf['lid-tot']` -> `fields.lid_tot`
  - `acf['factuur-adres']` -> `fields.factuur_adres`
  - `acf['factuur-email']` -> `fields.factuur_email`
  - `acf['factuur-referentie']` -> `fields.factuur_referentie`

## Storage Strategy (No ACF)

- Storage remains WordPress-native:
  - scalar fields in post meta,
  - repeaters as normalized post meta arrays/JSON-compatible structures,
  - options via Options API.
- Field definitions live in PHP config (single source of truth):
  - schema file per entity (`person`, `team`, `commissie`, etc.),
  - includes type, validation, REST visibility, editability.
- Custom Fields settings UI removed entirely.

## Legacy Field Pass (Final)

### Remove

- `_shared_with` (confirmed removable).
- `industry` (completed in active code paths).
- `vog-email-verzonden` (completed).
- `relatiecode` (replace by `knvb-id`, completed in active code paths).

### Keep and migrate to native schema

- `knvb-id`
- `werkfuncties` behavior (derived from `work_history`, not separate stored legacy field).
- `factuur-*` keys (`factuur-adres`, `factuur-email`, `factuur-referentie`) as supported native fields even if sparsely used today.

## Execution Plan

### Phase 1: Define native field schema and serializers

- Add code-based field registry for each post type.
- Add read/write serializers that map DB meta <-> new API shape.
- Add validation/sanitization per field type.

### Phase 2: REST contract cutover

- Update `people`, `teams`, `commissies`, and related endpoints to stop emitting `acf`.
- Emit new schema-backed payload only.
- Update write endpoints to accept new keys only.

### Phase 3: Frontend cutover

- Replace all `entity.acf[...]` reads/writes with new keys.
- Remove frontend assumptions around ACF payload and ACF custom field settings.

### Phase 4: rondo-sync cutover

- Update `rondo-sync` to new request/response keys.
- Add explicit old/new mapping doc section for sync maintainers.

### Phase 5: Data migration job

- Run one migration command during downtime:
  - migrate/rename keys (for example `knvb-id` -> final canonical key if changed),
  - backfill derived fields if needed,
  - remove deprecated legacy keys.
- Produce migration report (counts per field, changed rows, skipped rows).

### Phase 6: Remove ACF dependency

- Remove ACF bootstrap/dependency checks from theme init.
- Remove ACF JSON field-group reliance from runtime.
- Remove ACF-specific helper calls where replaced.

### Phase 7: Verification and rollback window

- Smoke tests for CRUD + exports + invoices + VOG flows + membership pass.
- Verify `rondo-sync` end-to-end against production-like data.
- Keep DB backup and migration log for rollback window.

## rondo-sync Old/New Mapping Checklist

- Person fetch: `acf` access removed.
- KNVB ID key updated to final canonical new key.
- VOG fields updated to native keys.
- Invoice fields (`factuur-*`) updated to native keys.
- Work history/team role reads updated to native structure.

## Canonical Key Decision

- New API keys use snake_case.
- `acf` is replaced by `fields` everywhere.
- Detailed endpoint-level old/new mappings are specified in:
  - `docs/prd/acf-rest-contract-old-vs-new.md`

## Deliverables in This Planning Cycle

- This hard-cutover plan.
- Full old vs new contract mapping:
  - `docs/prd/acf-rest-contract-old-vs-new.md`
- Legacy field disposition list (remove/keep/migrate).
- Pretask cleanup completed (`industry`, `relatiecode`, `vog-email-verzonden`).
