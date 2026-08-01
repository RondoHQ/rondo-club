# Remove ACF persisted-reference report

**Audit date:** 2026-08-01
**Repositories:** `rondo-club` and `rondo-sync`
**Production population data:** captured and reviewed

## Repository findings

| Store or surface | Owner | Legacy identifiers | Canonical identifiers | Repository evidence | Population count | Migration strategy |
|---|---|---|---|---:|---:|---|
| `rondo_people_list_preferences` user meta | rondo-club | Field-derived column IDs such as `knvb-id`, `datum-vog` | `knvb_id`, `datum_vog` | Read and validated by `REST\UserSettings` | 3 populated; all 3 contain known legacy IDs | Versioned on-read migration and dry-run/apply CLI migration |
| `rondo_people_list_column_order` user meta | rondo-club | Same field-derived column IDs | Canonical field names | Read and validated by `REST\UserSettings` | 1 populated; 1 contains known legacy IDs | Preserve order while mapping each known identifier; supplemental inventory found no unknown IDs |
| `rondo_people_list_column_widths` user meta | rondo-club | Associative keys using field-derived column IDs | Canonical field names | Read and validated by `REST\UserSettings` | 1 populated; 0 legacy or unknown IDs | Map associative keys without changing numeric widths |
| `stadion_column_widths` localStorage | React app | Legacy field-derived width keys | Canonical keys under a versioned Rondo key | `src/hooks/useListPreferences.js` | Browser-only; not server observable | One-time in-browser copy/mapping, then remove the old key |
| `rondo-col-*` localStorage values | React data tables | Field-derived visibility object keys | Canonical field names | `DataTable.jsx` and `useColumnVisibility.js` | Browser-only; not server observable | One-time per-key value migration, preserving booleans |
| People/VOG URL `orderby` state and bookmarks | React app and external bookmarks | `custom_{storage-name}` | `field_{canonical_name}` | Hard-coded and dynamic aliases in `PeopleList`, VOG pages, and `REST\People` | Not enumerable | Generate canonical URLs; accept the explicit legacy aliases temporarily |
| Dashboard visible-card/order user meta | rondo-club | Card IDs only | Unchanged | The allowlist contains `stats`, `reminders`, `anniversaries`, `todos`, `awaiting`, `meetings`, `recent-contacted`, `recent-edited` | Run audit to confirm | No field-name migration expected; audit command still scans options/user meta |
| Google Sheets saved definitions | Removed subsystem | Historical ACF column IDs | None | Deleted in commit `68c63652599a06e546c8145f09acf36b600ac13e` after a production query found zero connected users | 0 at deletion | No migration. The endpoint in the February contract is dead, not moved |
| WordPress options, transients, cron arguments | WordPress and theme services | Unknown until production scan | Registry-derived names | No active schema in the checkout intentionally stores REST field IDs | 0 option hits | No option migration required |
| Demo fixtures/import/export | rondo-club | `acf` envelopes and storage names | `fields` envelopes and canonical names | Active production tooling in `class-demo-export.php` and `class-demo-import.php` | N/A (code artifacts) | Migrate contract artifacts; use the PHP field API for storage operations |
| `free_field_mappings.target_field` and `target_scope` in SQLite | rondo-sync | Storage names; scope value `acf` | Canonical names; scope value `fields` | Schema and migrations in `lib/rondo-club-db.js` | 7 `acf` rows and 1 `meta` row; active dashed targets are `freescout-id` and `datum-vog` | Versioned SQLite migration rebuilds the constraint, maps non-meta target names, and preserves all rows |
| rondo-sync cached `data_json`, fixtures, and state | rondo-sync | `acf` response envelopes | `fields` response envelopes | Production code has no remaining ACF-envelope consumer | 1,077/3,773 member rows and 443/443 parent rows contain `acf` | Idempotently rewrite cached envelopes and nested field keys in place while preserving sync hashes, so deployment does not schedule a mass sync |
| rondo-sync maintenance tools | rondo-sync | ACF projections, payloads and dashed names | Canonical projections, payloads and snake_case names | Included in the 35-file inventory; not limited to the main pipelines | N/A (code artifacts) | Migrate and test every tool before the public compatibility layer is removed |

## Baseline code counts

- React production source: 36 files and 230 `.acf`/`acf:`/projection occurrences.
- rondo-sync production pipelines and tools: 35 files and 170 occurrences.
- Theme PHP: 73 production files and 628 direct ACF field-helper calls before the shared-API codemod.
- Field-bearing browser storage: three source files manage `stadion_column_widths` or `rondo-col-*` data.

These counts are migration baselines, not completion evidence. The Phase C exit gate requires the corresponding production-consumer searches to reach zero outside compatibility code and explicit legacy tests.

## Repository completion evidence

- React, theme tooling, rondo-sync pipelines, and rondo-sync maintenance tools contain no production `.acf`, `acf:`, or `_fields=acf` consumer.
- rondo-sync upgrades `free_field_mappings.target_scope = 'acf'` to `fields` by rebuilding the SQLite constraint and preserving every mapping row; a regression test covers the deployed legacy schema.
- People-list user preferences, order, and associative widths migrate through the registry-backed version 3 reader. The browser migration preserves old `stadion_column_widths` and `rondo-col-*` visibility values while generating canonical identifiers afterward.
- Demo fixtures and import/export code use the canonical `fields` envelope, canonical nested names, and canonical date/time wire formats.
- The remaining literal `acf` references in production code are bounded server compatibility guards that strip the retired response key and reject old writes, plus the one-time importer for legacy definition post types.

## Production evidence captured

The temporary preflight release was deployed first. The following read-only reports were captured from the production WordPress installation into the protected rollout record on 2026-08-01:

- `dynamic-fields.json`: zero dynamic definitions in `person`, `team`, and `commissie`; no definition import is required.
- `persisted-field-references.json`: the user-meta counts recorded above, zero WordPress-option hits, and the bounded browser/URL compatibility strategies.
- `persisted-identifier-inventory.json`: aggregate identifier-only supplement; every identifier is a valid core column, canonical registry name, or known legacy alias. No unresolved identifiers were found.
- `collect-persisted-identifiers.php`: the read-only aggregate collector used for the supplemental unknown-ID gate. It emits no user IDs, member values, or saved widths.

Checksums:

```text
f3c74df4619a0a378f2f0fc292575b53237785feb8f99b16e4cc7bce54eeecd2  dynamic-fields.json
e0a5dc6ed162f4bf52a2fc5ee7f73e3906d5793ef2d4cc77375e92fa1b1b1f5c  persisted-field-references.json
ced25861b257a59cdde1b295bea40078c8f8e2770f3705e58977f4d38a22de17  persisted-identifier-inventory.json
6cd9bc3d62e9b9df7bb2b785b0444f33a61f63ef4c00eaf2f79e9561c3608149  collect-persisted-identifiers.php
```

The raw production reports are intentionally not committed to Git.

The deployed rondo-sync SQLite database was inspected read-only on the same date. The audit found the mapping and cache populations recorded in the table above. The release branch now migrates both stores idempotently; its 74-test suite includes regression coverage for preserving mapping rows and sync hashes.

## Evidence disposition

The production field export and persisted-reference audit are complete. No dynamic-field retain/remove decisions remain, no option owner needs investigation, and no unresolved people-list identifiers block the cutover. The remaining gates are operational: take fresh Club and Sync database backups, pause Sync cron, deploy the paired Club/Sync releases in one window, verify both schema migrations and smoke tests, then resume Sync cron.
