# Remove ACF persisted-reference report

**Audit date:** 2026-07-31  
**Repositories:** `rondo-club` and `rondo-sync`  
**Production population data:** pending the read-only commands below

## Repository findings

| Store or surface | Owner | Legacy identifiers | Canonical identifiers | Repository evidence | Population count | Migration strategy |
|---|---|---|---|---:|---:|---|
| `rondo_people_list_preferences` user meta | rondo-club | Field-derived column IDs such as `knvb-id`, `datum-vog` | `knvb_id`, `datum_vog` | Read and validated by `REST\UserSettings` | Run audit | Versioned on-read migration and dry-run/apply CLI migration |
| `rondo_people_list_column_order` user meta | rondo-club | Same field-derived column IDs | Canonical field names | Read and validated by `REST\UserSettings` | Run audit | Preserve order while mapping each known identifier; report unknowns |
| `rondo_people_list_column_widths` user meta | rondo-club | Associative keys using field-derived column IDs | Canonical field names | Read and validated by `REST\UserSettings` | Run audit | Map associative keys without changing numeric widths |
| `stadion_column_widths` localStorage | React app | Legacy field-derived width keys | Canonical keys under a versioned Rondo key | `src/hooks/useListPreferences.js` | Browser-only; not server observable | One-time in-browser copy/mapping, then remove the old key |
| `rondo-col-*` localStorage values | React data tables | Field-derived visibility object keys | Canonical field names | `DataTable.jsx` and `useColumnVisibility.js` | Browser-only; not server observable | One-time per-key value migration, preserving booleans |
| People/VOG URL `orderby` state and bookmarks | React app and external bookmarks | `custom_{storage-name}` | `field_{canonical_name}` | Hard-coded and dynamic aliases in `PeopleList`, VOG pages, and `REST\People` | Not enumerable | Generate canonical URLs; accept the explicit legacy aliases temporarily |
| Dashboard visible-card/order user meta | rondo-club | Card IDs only | Unchanged | The allowlist contains `stats`, `reminders`, `anniversaries`, `todos`, `awaiting`, `meetings`, `recent-contacted`, `recent-edited` | Run audit to confirm | No field-name migration expected; audit command still scans options/user meta |
| Google Sheets saved definitions | Removed subsystem | Historical ACF column IDs | None | Deleted in commit `68c63652599a06e546c8145f09acf36b600ac13e` after a production query found zero connected users | 0 at deletion | No migration. The endpoint in the February contract is dead, not moved |
| WordPress options, transients, cron arguments | WordPress and theme services | Unknown until production scan | Registry-derived names | No active schema in the checkout intentionally stores REST field IDs | Run audit | Report every populated hit; migrate only a known owning schema |
| Demo fixtures/import/export | rondo-club | `acf` envelopes and storage names | `fields` envelopes and canonical names | Active production tooling in `class-demo-export.php` and `class-demo-import.php` | N/A (code artifacts) | Migrate contract artifacts; use the PHP field API for storage operations |
| `free_field_mappings.target_field` and `target_scope` in SQLite | rondo-sync | Storage names; scope value `acf` | Canonical names; scope value `fields` | Schema and migrations in `lib/rondo-club-db.js` | Run on deployed sync DB | Versioned SQLite migration, preserving unknown rows for operator review |
| rondo-sync cached `data_json`, fixtures, and state | rondo-sync | `acf` response envelopes | `fields` response envelopes | 35 production files / 170 occurrences currently consume or construct ACF payloads | Run on deployed sync DB | Migrate code first; version or invalidate only caches proven to contain REST payloads |
| rondo-sync maintenance tools | rondo-sync | ACF projections, payloads and dashed names | Canonical projections, payloads and snake_case names | Included in the 35-file inventory; not limited to the main pipelines | N/A (code artifacts) | Migrate and test every tool before the public compatibility layer is removed |

## Baseline code counts

- React production source: 36 files and 230 `.acf`/`acf:`/projection occurrences.
- rondo-sync production pipelines and tools: 35 files and 170 occurrences.
- Theme PHP: 73 production files and 628 direct ACF field-helper calls before the shared-API codemod.
- Field-bearing browser storage: three source files manage `stadion_column_widths` or `rondo-col-*` data.

These counts are migration baselines, not completion evidence. The Phase C exit gate requires the corresponding production-consumer searches to reach zero outside compatibility code and explicit legacy tests.

## Production read-only evidence commands

Run from the production WordPress installation before changing dynamic definitions or persisted settings:

```bash
wp rondo fields export-dynamic > dynamic-fields-2026-07-31.json
wp rondo fields audit-persisted > persisted-field-references-2026-07-31.json
```

`export-dynamic` records immutable keys, storage/canonical names, active state, ordering, type configuration, and populated-post counts. `audit-persisted` reports populated user-meta and option references without changing them. Both commands are read-only and deliberately omit field values.

## Open production evidence

Phase A is not operationally complete until both command outputs have been reviewed and attached to the rollout record. In particular, the dynamic-field retain/remove decision must be based on the exported definitions and populated-value counts. The implementation takes the retain-safe path until that evidence exists: it does not delete definitions or values.
