# Removing ACF from Rondo Club — Migration Plan

**Status:** Proposal (planning only — no code changes yet)
**Date:** 2026-07-31
**Supersedes / extends:** [acf-removal-hard-cutover-plan.md](acf-removal-hard-cutover-plan.md) (2026-02-27) and [acf-rest-contract-old-vs-new.md](acf-rest-contract-old-vs-new.md). Those docs remain the reference for the *eventual* REST contract rename; this plan revisits their "hard cutover" premise (see §0.2) and adds the full usage inventory that was missing.

**Companion data:** [remove-acf-usage-inventory.tsv](remove-acf-usage-inventory.tsv) — all 685 named ACF API call sites (file / line / call / field name / consumer type), regenerable with the grep in Appendix C.

---

## 0. Executive summary

ACF Pro is used as four different things in Rondo Club, and they have very different removal costs:

1. **A meta read/write API** (685 named call sites: 430 `get_field`, 237 `update_field`, 14 `get_fields`, 4 `delete_field`). Cheap to replace: **zero** row-level repeater APIs (`have_rows`/`add_row`/`update_sub_field`/`the_row`) are used anywhere — every repeater is read and written as a whole PHP array. A thin shim + codemod covers ~95% of sites mechanically.
2. **The REST transport for all field data** (`show_in_rest: 1` on every field group produces the `acf` key on `wp/v2/{people,teams,commissies,discipline-cases,…}` plus ACF's auto-generated JSON Schema for write validation). This is the real dependency: the React SPA (36 files reading `.acf`, 12 building `acf:` write payloads) and **rondo-sync (~40 files, 63 `wp/v2` call sites)** are built against ACF's exact payload shape and formatting. Replacing it means re-implementing the `acf` REST field natively with byte-compatible output.
3. **A hook bus for domain side effects** (26 `acf/*` registrations: auto-title, inverse-relationship sync, fee/volunteer cache invalidation, VOG tracking reset, shift-template expansion, phone/e-mail normalization, two uniqueness validators). Most already have REST twins; three are non-trivial (see §2.3).
4. **A runtime field-definition store for the user-facing "Custom Fields" settings feature** (`includes/customfields/` + `/rondo/v1/custom-fields/*` + `src/pages/Settings/CustomFields.jsx`). Definitions are persisted as ACF's own `acf-field-group`/`acf-field` posts **in the production database only** (not in `acf-json/`, not in git), via ACF internals (`acf_import_field_group`, `acf_update_field`, `acf_get_fields`). This subsystem has **zero test coverage**.

Storage is plain WordPress post meta everywhere (plus term meta for one `relationship_type` field). ACF's flat layout (`addresses`, `addresses_0_city`, `_addresses` reference rows) is hard-coded in raw SQL in at least 4 places. **The single most important strategic decision in this plan is: keep that storage layout byte-identical and keep the REST payload byte-identical (still keyed `acf`), so removing the plugin requires no data migration and no coordinated frontend/rondo-sync release.** The `acf` → `fields` snake_case rename from the February plan becomes a separate, optional, later project.

Headline numbers:

| Metric | Count |
|---|---|
| Field groups | 14 static (versioned in `acf-json/`) + up to 3 dynamic (`group_custom_fields_{person,team,commissie}`, production-DB only) |
| Fields | 153 data fields + 9 UI tabs (top-level) + 29 repeater sub-fields = **182 static fields**; dynamic user-created fields uncounted (must be exported from production, §4.6) |
| Repeaters | 6 (`addresses`, `work_history`, `relationships`, `contact_info` ×2, `line_items`). **No flexible content, no clones, no options pages, no user-meta fields.** |
| PHP call sites | 685 named + ~55 dynamic-name (variables/loops) |
| ACF hooks | 26 registrations across 13 files |
| Frontend | 36 JS files read `.acf`; 3 sanitizers in `src/utils/formatters.js` |
| External consumer | rondo-sync: 63 `wp/v2` call sites reading/writing `acf` across ~40 files |
| Loading | Normal plugin + `class_exists('ACF')` guard (`functions.php:182`). No Composer/npm dependency. Test suite and CI also require ACF Pro. |

Total effort: **XL — roughly 12–16 calendar weeks at one developer half-time** (≈6–8 FTE-weeks). Safe to ship in slices (one field group per PR) after the shared field layer lands. No runtime feature flag needed; the per-group cutover switch is each group's `show_in_rest` flag (§3.4).

### 0.1 Why this is worth doing

- ACF's admin UI — its core value — is unused: all editing happens in the React SPA via REST. `acf_form()`/`acf_form_head()`: **zero** call sites.
- We already duplicate ACF's job: REST controllers do their own validation, the SPA does its own rendering, and three sanitizers exist purely to work around ACF's auto-generated REST schema (the `""`-vs-enum `rest_invalid_param` failure documented in CLAUDE.md).
- Recurring ACF-shaped bugs: the `YYYYMMDD` date format trap, the select-enum trap, `update_field()`-by-name silently no-oping without a reference row (already patched ad hoc with field keys in `class-rest-invoices.php`), and two `validate_value` validators that are broken on REST writes today (§2.3.4).
- One less paid plugin dependency in prod, demo, dev, CI, and every test environment.

### 0.2 Decisions to (re)confirm before starting

The February plan "locked" a hard cutover with the `acf`→`fields` rename. This plan recommends unlocking two of those decisions; **both need Joost's sign-off**:

| # | Decision | Feb 2026 plan | This plan recommends | Why |
|---|---|---|---|---|
| D1 | REST payload | Rename `acf`→`fields`, snake_case keys, one big cutover with downtime | **Keep the `acf` key and all current key names byte-identical.** Rename later (or never) as an independent project | Decouples plugin removal from a 3-repo breaking change (theme PHP + React + rondo-sync + its 11 tools/ scripts). Enables per-group incremental shipping with zero downtime. The rename doc stays valid as a future step |
| D2 | Dynamic custom-fields feature | Remove the settings UI; definitions become code-only | **Keep the feature**, re-implement definitions as a versioned `wp_option` (JSON) + the same native field registry the static groups use | It's a live, user-facing feature (Settings → Custom Fields, drag-reorder, soft delete). Killing it is a product decision, not a tech one. Cost to keep: ~2 weeks (§4.6). If Joost confirms nobody uses it, dropping it saves that and simplifies §3 |
| D3 | wp-admin editing | (not addressed) | **Accept the loss.** No replacement metabox | Editing is frontend-only by design. Losing ACF's admin forms also deletes an entire class of hooks that exist only to guard admin edits (§2.3). If a read-only admin view is ever wanted, it's a trivial later add |

---

## 1. Phase 1 — Inventory (ground truth)

### 1.1 Field groups

All 14 static groups have `show_in_rest: 1` and location rules on exactly one post type (or taxonomy). Counts are top-level fields including tabs.

| Group key | Title | Location | Fields | Complex fields |
|---|---|---|---|---|
| `group_person_fields` | Person Fields | `post_type == person` | 60 | repeaters `addresses` (9 subs), `work_history` (8 subs), `relationships` (3 subs); gallery `photo_gallery` |
| `group_volunteer_policy_fields` | Vrijwilligersbeleid | `post_type == person` | 12 | — |
| `group_dienst_type_fields` | Inschrijftaak | `post_type == dienst_type` | 16 | — |
| `group_discipline_case_fields` | Tuchtzaak Details | `post_type == discipline_case` | 13 | — |
| `group_invoice_fields` | Factuur Details | `post_type == rondo_invoice` | 11 | repeater `line_items` (3 subs) |
| `group_feedback_fields` | Feedback Fields | `post_type == stadion_feedback` | 10 | — |
| `group_commissie_fields` | Commissie Fields | `post_type == commissie` | 9 | repeater `contact_info` (3 subs) |
| `group_dienst_shift_fields` | Inschrijftaak | `post_type == dienst_shift` | 9 | — |
| `group_shift_template_fields` | Inschrijftaaksjabloon | `post_type == shift_template` | 9 | — |
| `group_team_fields` | Team Fields | `post_type == team` | 5 | repeater `contact_info` (3 subs) |
| `group_todo_fields` | Todo Fields | `post_type == rondo_todo` | 4 | — |
| `group_team_kickoff_fields` | Vrijwilligersbeleid kickoff | `post_type == team` | 2 | — |
| `group_taakuitleg_fields` | Taakuitleg | `post_type == taakuitleg` | 1 | — |
| `group_relationship_type_fields` | Relationship Type Fields | `taxonomy == relationship_type` | 1 | **term meta**, on the inverse-relationship critical path |
| `group_custom_fields_{person,team,commissie}` | Custom Fields | dynamic, DB-only | unknown | user-created via Settings UI; **not in git** |

Field type census (static, incl. sub-fields): 59 text, 19 date_picker, 18 textarea, 18 number, 15 true_false, 15 select, 12 post_object, 9 tab, 6 repeater, 5 date_time_picker, 4 url, 2 wysiwyg, 2 time_picker, 2 taxonomy, 2 relationship, 1 gallery, 1 file, 1 color_picker.

### 1.2 API call sites

685 named call sites (430 `get_field`, 237 `update_field`, 14 `get_fields`, 4 `delete_field`) plus ~55 with variable field names. Full table: [remove-acf-usage-inventory.tsv](remove-acf-usage-inventory.tsv). By consumer type:

| Consumer type | Sites |
|---|---|
| Backend services, read (email senders, PDF, passes, fee/volunteer calculators, …) | 191 |
| REST controllers, read | 165 |
| Tooling / bulk data (`class-wp-cli`, `class-demo-import/export`, `class-todo-migration`, `bin/`) | 146 |
| REST controllers, write | 74 |
| Tests | 67 |
| Backend services, write | 35 |
| Dynamic custom-fields subsystem (ACF internals, not `get_field`) | 7 |

Heaviest files: `class-rest-invoices.php` (77), `class-demo-import.php` (56), `class-demo-export.php` (49), `class-wp-cli.php` (32), `class-rest-feedback.php` (32).

Notable patterns:

- **97% of `update_field()` calls use field *names*, not keys** (181/187). Name-based writes require ACF's `_field` reference row (or group-location fallback) to resolve. The 6 key-based calls cluster on invoice date fields — evidence someone already hit the silent-no-op bug on fresh posts.
- **`contact_info` is a name→key trap:** the same field name maps to `field_team_contact_info` on teams and `field_commissie_contact_info` on commissies (identical sub-field names, different keys). `status`, `person`, `website`, `gender` are also reused across groups. No blind find-replace is possible; the shim must resolve per post type.
- Dead hooks: `class-auto-title.php:26-27` filter `acf/update_value` on keys `field_contact_value` / `field_company_contact_value`, which exist in **no** field group — stale since a prior schema. Delete, don't migrate.

### 1.3 ACF hooks (26 registrations, 13 files)

Full semantics in §2.3. Summary: 6× `acf/save_post`, 15× `acf/update_value` (5 fee-cache, 4 phone-normalizer, 2 e-mail-lowercase, 2 dead keys, `datum-vog` reset, `relationships` ×2), 3× `acf/validate_value`, 1× `acf/prepare_field` (admin cosmetics), 2× `acf/settings/{load,save}_json`.

### 1.4 REST surface

- **Mechanism:** ACF Pro's native `show_in_rest` on field groups — the theme registers **no** `register_rest_field` for `acf` itself. Two exceptions: taxonomy terms (`class-rest-api.php:681-697` manually reads/writes `acf` on `relationship_type` — the write path loops the caller's payload into `update_field()` **with no allowlist**, a pre-existing security smell to fix during migration) and two non-ACF read-only rest fields on `dienst_shift`.
- **Endpoints emitting ACF data:** all `wp/v2` CRUD for `people`, `teams`, `commissies`, `discipline-cases`, `rondo_invoice`, `rondo_todo`, `stadion_feedback`, `dienst_*`, `shift_template`, `taakuitleg`, `relationship_type`; plus hand-built `rondo/v1` payloads that embed `get_fields()` blobs: `people/filtered` (N+1, re-applies redaction manually), `entity/{id}` (**no redaction** — unfiltered blob incl. `_shared_with`), `people/household` and `kaderlijst/people` (fixed allowlists), and ~15 `rondo/v1` controllers reading/writing individual fields (inventory TSV has the list; the four-agent analysis details are preserved in §Appendix B pointers).
- **Response-layer mutation to reproduce exactly:** `AccessControl::filter_rest_single_access` redacts `person.acf` per capability (`MEMBER_VISIBLE_ACF_FIELDS`, `SENSITIVE_ACF_FIELD_GROUPS`); `expand_person_relationships()` rewrites `acf.relationships` rows into an enriched shape; computed top-level fields (`birth_year` etc.) come from `get_field()`.
- **Write-path guards that parse the `acf` request param** (must be re-pointed at the same param name post-ACF): `block_former_member_edits`, `validate_person_identity`, `validate_sponsor_pass_variant`, `enforce_person_field_scope` (diffs submitted `acf` against `get_fields()`), `prevent_direct_assignee_writes`, `prevent_direct_rest_cancellation` (`class-shift-cancellation-service.php:147` hard-depends on `acf.status` shape).
- **rondo-sync** is a first-class `acf` consumer/producer: field mapping incl. hyphen aliases in `lib/detect-rondo-club-changes.js`, outbound `acf` construction in `steps/prepare-rondo-club-members.js`, state-transition reads (`financiele-blokkade`, `huidig-vrijwilliger`), relationship merging, `_fields=acf.datum-overlijden` projections, plus ~11 `tools/` maintenance scripts PATCHing `acf` directly. **This is why D1 (keep the wire contract) matters.**

### 1.5 Loading & packaging

- ACF Pro is a normal `/wp-content/plugins/` plugin on prod/demo/test; not in `composer.json` or `package.json`; guarded by `class_exists('ACF')` in `rondo_check_dependencies()` (`functions.php:182`).
- `acf-json/` load/save points: `functions.php:1266,1277` (save gated on `WP_DEBUG` — which is why dynamic groups never reach git).
- Test suite (389 tests) requires ACF Pro in the WP install (docs/testing.md, CI workflow) and `RondoTestCase` carries an ACF-specific `_doing_it_wrong` silencer.
- Gutenberg/blocks coupling: **none** (no `acf_register_block`, no `acf/*` block names, no options pages, no `acf_form()`).

### 1.6 Storage confirmation & dead fields

Standard ACF post-meta layout is confirmed *by raw SQL*, not inference:

- `class-volunteer-eligibility-service.php:818-836` — `meta_key LIKE 'addresses\_%\_postal\_code'` + regex on `addresses_(\d+)_…`
- `class-rest-teams.php:405-430` — reconstructs sibling repeater keys by string surgery (`CONCAT('work_history_', REPLACE(...), '_end_date')`)
- `class-rest-capabilities.php:238-243` — `LIKE 'work_history_%_job_title'`
- Count-row semantics used in `meta_query` (`work_history > 0 NUMERIC`; `EXISTS` on `relationships`)

Also: `wp prm` CLI has a raw `DELETE … LIKE 'work_history%'` that already orphans `_work_history` reference rows today.

**Dead-field pass:** only 5 static fields have zero code references in rondo-club (`_nikki_{2022..2025}_status`, `publicteamid`) — but `publicteamid` and the Nikki fields are **written by rondo-sync** through the REST payload, so they are sync-owned, not dead. Genuine removal candidates are limited to the two dead hook keys (§1.2) and whatever the production-DB dynamic-fields export turns up as unused. Everything else migrates.

---

## 2. Phase 2 — Analysis

### 2.1 Native replacement path per storage shape

| Shape | Today (ACF) | Post-ACF | Data migration |
|---|---|---|---|
| Scalar fields (text/number/select/date/true_false/url/…) | post meta, key = field name | `get_post_meta`/`update_post_meta`, same key | **None** |
| Repeaters (6) | `{name}` = row count, `{name}_{i}_{sub}` rows | Keep identical layout; new `Repeater::get()/set()` helper reads/writes the numbered rows + count | **None** (raw-SQL consumers keep working untouched) |
| `_field` reference rows | required by name-based `update_field()` | not needed by the shim (name+post-type → schema lookup); left in place, swept in final cleanup | Optional cleanup only |
| `relationship_type.inverse_relationship_type` | term meta via `'relationship_type_'.$id` context | `get_term_meta`/`update_term_meta` | **None** |
| Dynamic custom fields — *values* | plain post meta, bare `sanitize_title(label)` key | unchanged | **None** |
| Dynamic custom fields — *definitions* | `acf-field-group`/`acf-field` posts (prod DB only) | versioned `wp_option` JSON consumed by the same registry | **One-time export/convert** (§4.6) |

The decisive simplification: because storage stays byte-identical, "migration" of a field group is a **code cutover, not a data move**. The only data-touching steps in the whole project are the dynamic-definition export and the optional reference-row sweep.

**Formatting parity is the real tail-risk work.** `get_field()` returns *formatted* values (booleans, ints, `post_object` expansions, select handling); `get_post_meta()` returns raw strings/serialized arrays. The shim must apply per-type casts from the schema (true_false → bool, number → int/float, repeater → array-of-rows, empty repeater → `[]`, date passthrough as `Ymd`). The ~15 relationship read sites that defensively normalize 3 shapes (int / WP_Post / term array) are evidence the codebase already tolerates variance — but the REST layer must match ACF's current output byte-for-byte, which is what the golden-master suite (§3.1) exists to prove.

### 2.2 Per-field-group effort estimates

| Group | Effort | Reason |
|---|---|---|
| `taakuitleg` | **S** | 1 wysiwyg field, one public page reads it. Ideal pipeline-proving slice |
| `team_kickoff` | **S** | 2 fields, 1 consumer each |
| `todo` | **S** | 4 scalars; REST controller already owns all writes; `class-todo-migration` already half-raw |
| `feedback` | **S/M** | 10 scalars, writes concentrated in one controller (32 sites); feedback API is agent-facing (CLAUDE.md workflow) so contract tests matter |
| `dienst_type` | **M** | 16 scalars, no hooks |
| `shift_template` | **M** | 9 scalars + `acf/save_post` expander hook (REST/cron paths already ACF-free) |
| `dienst_shift` | **M** | 9 scalars + cancellation guard pair (REST twin exists; fix its `acf.status` payload dependency) + assignee write guard |
| `team` | **M** | 5 fields incl. first repeater (`contact_info`); logo write via `class-rest-base` |
| `commissie` | **M** | 9 fields, `contact_info` twin — must ship with post-type-aware shim resolution |
| `discipline_case` | **M** | 13 scalars + `dossier_id` unique validator (fix the REST self-exclusion bug while porting) + cross-writes from invoices |
| `volunteer_policy` | **M** | 12 scalars, readers concentrated in volunteer services; on `person` CPT so cuts over together with person's REST provider flip |
| `invoice` | **L** | 11 fields + `line_items` repeater; 77 call sites in `class-rest-invoices` alone plus PDF/e-mail/bulk/fine-generator; money + Mollie state machine ⇒ needs the contract tests most |
| `relationship_type` (term) | **M/L** | 1 field but term-meta context + sits inside inverse-sync; do together with person |
| `person` (+ its 3 repeaters) | **XL** | 60 fields, redaction, scope enforcement, relationship expansion, computed fields, rondo-sync round-trip, inverse-relationship service extraction (§2.3.1), household/kaderlijst/filtered hand-built payloads |
| dynamic custom fields | **XL** | Runtime registry + REST schema + definitions storage + Settings UI backend swap; zero existing tests |

### 2.3 Hook-by-hook disposition

Three buckets. ("Meta hooks" = `updated_post_meta`/`added_post_meta`/`deleted_post_meta`, which fire for *any* writer — REST, CLI, import — making them the default landing zone.)

**(a) Delete — already have complete non-ACF twins or are ACF-plumbing:**

| Hook | Why deletable |
|---|---|
| `acf/settings/{load,save}_json` (functions.php) | ACF plumbing; `acf-json/` becomes schema source material |
| auto-title `acf/save_post` + `acf/prepare_field` | `rest_after_insert_person` twin exists; title-field hiding dies with the admin form |
| volunteer-status `acf/save_post` | REST twin exists; its internal `update_field()` → shim |
| membership-pass `acf/save_post` | `save_post_person` + REST twins exist |
| volunteer-cache `acf/save_post` | 4 non-ACF registrations already do the same flush; add a meta-hook listener for the cross-person repeater case its comment describes |
| shift-cancellation `acf/validate_value` | admin-form-only; REST twin is the survivor (fix its payload-shape read) |
| dead `field_contact_value` hooks | keys don't exist |

**(b) Mechanical moves:**

| Hook | New home |
|---|---|
| phone normalizer (4× `update_value`) + e-mail lowercase (2×) | `sanitize_callback` on `register_post_meta()` — one place covers REST, CLI, import |
| fee-cache invalidation (4 of 5 `update_value`) | meta hooks with a key allowlist (pattern already used in the same class for `_exclude_from_contributie`) |
| `datum-vog` tracking reset | `VogService::set_vog_date()` called from the VOG controller + a meta-hook fallback for CLI/import |
| `dossier_id` unique + custom-field unique validators | REST `validate_callback` with post ID from the route — **fixes the live REST bug** where `$_POST['post_ID']` is absent so self-exclusion silently fails. (Also: the custom-field validator scopes uniqueness per author — confirm that's intended before porting.) |
| shift-template expander | `rest_after_insert_shift_template` (+ existing cron/REST static paths) |

**(c) The two genuinely hard ones:**

1. **Inverse relationships** (`class-inverse-relationships.php`) — a *stateful three-hook pipeline*: validate (`update_value` prio 4) → snapshot old value (prio 5, *before* meta write) → diff & sync inverses (`acf/save_post`). The REST twins that exist today only work because the ACF capture filter ran first; a naive port **silently breaks deletion detection** (empty old-snapshot ⇒ every relationship looks newly added, removals never propagate to the other person). Replacement: extract `RelationshipService::set_relationships($person_id, array $new)` that reads-old → sanitizes → writes → syncs inverses in one call, invoked from every write path (people REST controller, CLI merge/sibling commands, demo import). Preserve the re-entrancy guard; fix the O(n) full-person-scan sibling lookup with a `relationships_%_related_person` meta query while there.
2. **Family-cache invalidation on `addresses`** — needs the *pre-update* family key, so a post-write meta hook is too late. Use the `update_post_metadata` short-circuit filter to capture the old value, or fold it into the person-write service. Flagged non-mechanical.

**Ordering to preserve** on person saves: auto-title → inverse-relationships → membership-pass (all 20) → volunteer-status (25) → volunteer-cache (30).

---

## 3. Phase 3 — Migration strategy

### 3.1 Step 0: safety net first (before any behavior change)

- **Golden-master REST contract suite:** snapshot the full JSON of every ACF-emitting endpoint (wp/v2 CRUD per CPT incl. write echoes, `people/filtered`, `entity/{id}`, `household`, `kaderlijst`) against fixture data; assert byte-equality as each group cuts over. This converts "formatting parity" from a risk into a checklist.
- Backfill minimal wpunit coverage for the custom-fields subsystem (currently zero) before touching it.
- Export dynamic field definitions from production DB (read-only script) into the future `wp_option` format; commit the snapshot.

### 3.2 Step 1: the shim (read- and write-side)

`Rondo\Fields\Fields::get($name, $post_id)` / `::update($name, $value, $post_id)` / `::delete(...)` — post-type-aware (resolves the `contact_info` ambiguity), initially delegating to `get_field()`/`update_field()`. Codemod all 685 sites file-by-file (mechanical PRs, zero behavior change; tests included in the sweep). The ~55 dynamic-name sites get hand-checked. This is the cheap intermediate step: after it lands, "migrating a field group" = flipping the shim's backend for that group.

### 3.3 Step 2: the native field layer

- **Schema registry** (`includes/fields/`): per-CPT PHP definitions generated once from `acf-json/` (name, type, choices, default, unique, sub-fields). Single source of truth for the shim's type casts, `register_post_meta` sanitizers, REST schema, and the custom-fields registry (D2).
- **Native REST provider:** `register_rest_field('acf', …)` per CPT reproducing ACF's read formatting and write handling (incl. accepting the full-object round-trip and per-field JSON Schema). Opportunity, *after* parity is proven: accept `""` for nullable enums server-side and retire the `enumFields` sanitizer hack — additive, non-breaking.
- **Repeater helper** for numbered-row layout.
- **Validator:** don't invent a new `FieldValidator` interface — use WP REST arg schema + `validate_callback`/`sanitize_callback` generated from the registry, with the domain rules from §2.3(b) as explicit callbacks. That's the codebase's existing pattern (`class-rest-base`, wp-rest conventions).

### 3.4 Per-group cutover mechanics (the "dual-read period")

For each field group, in one PR each:

1. Migrate the group's hooks per §2.3 to ACF-independent hooks (they fire identically under both providers — this is the coexistence trick; ensure no double-registration).
2. Flip the group's shim backend to raw meta + registry casts.
3. Set `"show_in_rest": 0` in the group's `acf-json` and enable the native REST provider for that CPT — exactly one provider serves the `acf` key at any time. Golden-master suite must stay green.
4. Deploy; watch; next group.

ACF stays installed and serves the remaining groups throughout. Zero downtime, per-group rollback = revert one PR. Order (rationale: §2.2 — easiest first, person last, invoice isolated in the middle because money):

1. `taakuitleg` → 2. `team_kickoff` + `todo` → 3. `feedback` → 4. `dienst_type` + `shift_template` + `dienst_shift` (shift cluster) → 5. `team` + `commissie` (first repeaters) → 6. `discipline_case` → 7. `invoice` → 8. dynamic custom fields (§4.6) → 9. `relationship_type` + `person` + `volunteer_policy` (with the RelationshipService extraction) → 10. remove ACF.

### 3.5 Final removal

Only after step 9 is verified in production: delete `class_exists('ACF')` guard, JSON load/save points, `acf-json/` (schema now lives in `includes/fields/`), ACF references in tests/CI/docs (docs/testing.md, CLAUDE.md/AGENTS.md incl. "Rule 0" table and the ACF pitfall sections), deactivate/remove the plugin on prod/demo/test, optional `_field`-reference-row sweep (dry-run first), and update the developer docs site. rondo-sync needs **no change** under D1 — verify its pipelines against demo before and after anyway.

---

## 4. Phase 4 — Risks

| # | Risk | Severity | Mitigation |
|---|---|---|---|
| 1 | **Inverse-relationship old-value capture** — silent breakage of deletion detection (§2.3.c1) | **Highest** | Dedicated service extraction with its own wpunit suite (add/change/remove/sibling-cascade), shipped in the last slice; keep ACF path until proven |
| 2 | REST formatting drift breaking SPA or rondo-sync (dates, bools, empty repeaters, select labels, relationship expansion) | High | D1 contract freeze + golden-master byte-equality suite + staging run of rondo-sync pipelines per person-slice deploy |
| 3 | Redaction gaps when re-implementing the REST provider (`people/filtered` manual redaction; `entity/{id}` already unredacted) | High | Centralize redaction in the new provider; fix the `entity/{id}` hole while there |
| 4 | Silent no-ops from name-based writes during transition (shim resolves names differently than ACF's reference-row fallback) | Medium | Shim logs/throws on unresolvable name+post-type in dev; contract tests on write echoes |
| 5 | Raw-SQL layout consumers (4 sites) breaking **silently** (empty result sets, e.g. every household suddenly "has no adults") | Medium (eliminated by design) | Keeping the meta layout is the mitigation; add regression tests pinning the layout |
| 6 | Dynamic field definitions exist only in prod DB | Medium | Export first (§3.1); treat the export as the migration's canonical input |
| 7 | Repeater data safety | Low under this plan | No data move happens at all; the only destructive step (reference-row sweep) is optional, last, dry-run + DB backup |
| 8 | Blocks/site editor | None | Verified: no ACF blocks, no options pages, no `acf_form()` |
| 9 | Admin UX loss (D3) | Low | Accepted; ACF admin edits were guarded against anyway (cancellation validator etc.) |
| 10 | Latent bugs being ported faithfully (REST self-exclusion in both unique validators; per-author uniqueness scope; commissie missing from unique-validation mapping) | Low | Fix during port, with tests; noted in §2.3(b) |

---

## 5. Phase 5 — Effort & rollout

| Slice | Est. (half-time weeks) |
|---|---|
| Step 0 safety net (golden masters, custom-fields tests, prod export) | 2 |
| Shim + 685-site codemod | 1.5–2 |
| Native field layer (registry, REST provider, repeater helper, validators) | 3 |
| Groups 1–6 (simple + shift cluster + team/commissie + discipline) | 2–3 |
| Invoice group | 1.5 |
| Dynamic custom fields (D2 = keep) | 2 (0.5 if dropped) |
| Person + relationships service + term meta | 3–4 |
| ACF removal + cleanup + docs + test infra | 1 |
| **Total** | **XL ≈ 16–19 half-time weeks ⇒ 12–16 calendar weeks realistically, ~6–8 FTE-weeks** |

- **Sliceable:** yes — one field group per PR after the field layer lands; each deploys independently under the existing CI → production flow. Hard dependencies: safety net → shim → field layer → any group; hooks-migration → that group's flip; everything → removal PR.
- **Feature flag:** not needed. Per-group `show_in_rest` flip is the switch; rollback is a one-PR revert. The final removal PR is not flagged but gated on a demo-environment soak + rondo-sync pipeline verification.

---

## Appendix A — Consumers map

- React SPA: 36 files read `.acf`; write path always routes through `sanitizePersonAcf`/`sanitizeTeamAcf`/`sanitizeCommissieAcf` (full-object round-trip). Untouched under D1; the sanitizers can shrink once the native provider accepts `""` enums.
- rondo-sync: 63 `wp/v2` call sites; key files `lib/detect-rondo-club-changes.js`, `steps/prepare-rondo-club-members.js`, `steps/submit-rondo-club-sync.js`, 11 `tools/` scripts. Untouched under D1.
- Tests: 67 ACF call sites across ~20 wpunit files + ACF Pro requirement in the test WP install and CI.

## Appendix B — Where the deep analysis lives

The four-track analysis behind §§1–2 (hook-by-hook handlers, repeater read/write site lists, REST endpoint tables, customfields internals) was produced against commit `7e6d61b6` (2026-07-31). File:line references throughout this doc are from that commit.

## Appendix C — Regenerating the inventory

```bash
grep -rn --include='*.php' -E "(get_field|have_rows|the_field|get_sub_field|get_field_object|get_fields|update_field|add_row|update_sub_field|delete_field|delete_row)\(" functions.php includes bin tests
```

Classification into the TSV's consumer types: `tests/` → test; `class-wp-cli|class-demo-*|class-todo-migration|bin/` → tooling; `customfields/|class-rest-custom-fields` → dynamic subsystem; `class-rest-*` → REST read/write by call type; remainder → backend service.
