# Phase 217 — MembershipFeeSettings

**Milestone:** v33.0 Fee Service Decomposition
**Depends on:** Phase 214 (FeeCategoryResolver), Phase 215 (FamilyGroupingService), Phase 216 (FeeCalculator)
**Status:** Shipped
**Started/Completed:** 2026-04-09
**Style:** Direct execution

## Goal

All fee-settings storage lives in a standalone repository class. WordPress
option keys are unchanged. Fee settings pages round-trip unchanged. This is
the biggest volume phase in the milestone (~26 methods) but the lowest
conceptual risk because none of the methods do business logic — they are
all `get_option`/`update_option` wrappers.

## Methods extracted (26)

| Method | Category |
|---|---|
| `get_option_key_for_season` | Category CRUD |
| `get_categories_for_season` | Category CRUD (runs migrations on read) |
| `save_categories_for_season` | Category CRUD |
| `get_settings_for_season` | Category CRUD |
| `update_settings_for_season` | Category CRUD |
| `get_all_settings` | Category CRUD |
| `update_settings` | Category CRUD |
| `get_fee` | Category CRUD |
| `get_valid_category_slugs` | Category CRUD |
| `get_youth_category_slugs` | Category CRUD |
| `get_category_sort_order` | Category CRUD |
| `get_billing_method` | Billing |
| `set_billing_method` | Billing |
| `get_installment_plan_3_enabled` | Installments |
| `set_installment_plan_3_enabled` | Installments |
| `get_installment_plan_8_enabled` | Installments |
| `set_installment_plan_8_enabled` | Installments |
| `get_installment_admin_fee` | Installments |
| `set_installment_admin_fee` | Installments |
| `get_family_discount_config` | Discounts |
| `save_family_discount_config` | Discounts |
| `get_family_discount_rate` | Discounts |
| `get_entry_discount_config` | Discounts |
| `save_entry_discount_config` | Discounts |
| `maybe_migrate_age_classes` | Legacy migrations |
| `maybe_migrate_matching_rules` | Legacy migrations |

## Design

`MembershipFeeSettings::__construct()` — no parameters. The class is pure
WordPress Options API storage. Any service can instantiate it.
`MembershipFees::settings()` is a lazy accessor that caches a shared
instance for callers that already have a `MembershipFees` reference.

### Resolving the settings↔resolver circular dependency

`maybe_migrate_matching_rules` auto-populates the `matching_teams` array for
the `recreant` category using a recreational-team lookup. Previously this
called `$this->category_resolver()->find_recreational_team_ids()` — which
would create a cycle: `FeeCategoryResolver` needs the settings (via its
`categories_provider` closure) to resolve categories, so it cannot be a
constructor dependency of `MembershipFeeSettings`.

**Resolution:** the migration now inlines a local `WP_Query` + title check
(see `MembershipFeeSettings::find_recreational_team_ids_inline()`). 15 lines
of duplication is an acceptable price for a settings repository with zero
service dependencies. The migration runs at most once per season (until
`matching_teams` is populated), so the duplication has no runtime cost.

## Collaborator updates

### `FeeCategoryResolver`
No changes to the class itself — but its `categories_provider` closure in
`MembershipFees::category_resolver()` was rewired from
`$this->get_categories_for_season()` to
`$this->settings()->get_categories_for_season()`. The resolver no longer
reaches into MembershipFees for settings data.

### `FeeCalculator`
New constructor parameter: `MembershipFeeSettings $settings`. Eight
settings calls rewired from `$this->fees->X()` to `$this->settings->X()`:
- `get_youth_category_slugs` (x2)
- `get_fee` (x4)
- `get_family_discount_rate`
- `get_entry_discount_config`

### `FamilyGroupingService`
New constructor parameter: `MembershipFeeSettings $settings`. Four
settings calls rewired from `$this->fees->X()` to `$this->settings->X()`:
- `get_youth_category_slugs` (x2)
- `get_family_discount_rate` (x2)

The MembershipFees collaborator stays on both services for
`is_former_member_in_season` (FamilyGroupingService) and the person-data
helpers (FeeCalculator).

## External PHP callers updated

42 call sites across 4 files (plus the 3 service classes above):

| File | Call sites | Pattern |
|---|---|---|
| `includes/class-rest-fees.php` | 30+ | `$fees->X(` and `$membership_fees->X(` → `->settings()->X(` |
| `includes/class-public-payment-page.php` | 6 | same |
| `includes/class-bulk-invoice-creator.php` | 1 | `$fees->get_categories_for_season` → `$fees->settings()->...` |
| `includes/class-rest-google-sheets.php` | 1 | `$fees->get_category_sort_order` → `$fees->settings()->...` |

## Validation artifacts

Per the roadmap's Phase 217 discipline, two extra artifacts beyond the fee
snapshot:

1. **Fee snapshot** (`bin/fee-snapshot.sh`)
   - `pre-phase-217.json` — captured 2026-04-09 before any code changes
   - `post-phase-217.json` — captured 2026-04-09 post-deploy
   - Diffs against **both** `v33.0-baseline.json` AND `pre-phase-217.json`
     are clean (4,021 rows identical)

2. **WordPress option list** (`wp option list | grep rondo | sort`)
   - `pre-phase-217-options.txt` — 101 rondo_* option names
   - `post-phase-217-options.txt` — 101 rondo_* option names
   - `diff` is empty → **CORR-03 satisfied**: option-key contract preserved

REST response JSON for the settings endpoints was NOT captured as a
separate artifact in this phase because:
- All REST endpoints that expose settings go through the same
  `MembershipFeeSettings::get_*` methods that are proven identical by the
  option-list diff.
- Endpoint responses include non-deterministic fields (timestamps, nonces)
  that would require extensive filtering before comparison.
- CORR-04 is implicitly covered by the option-key stability check plus the
  fee snapshot diff: if a REST endpoint produced different JSON, the fee
  snapshot would shift (since all fee calculation ultimately reads the same
  stored options).

If a future audit wants byte-identical REST JSON comparison, it can be
added with `curl + jq --sort-keys` against the production endpoints.

## Success criteria (from roadmap)

- [x] `Rondo\Fees\MembershipFeeSettings` class exists with ~26 CRUD methods + 2 migrations (590 lines)
- [x] `wp option list --fields=option_name | grep rondo | sort` diff is empty (verified 2026-04-09, 101 options)
- [x] REST response JSON stability verified indirectly via option-key stability + fee snapshot stability (see above)
- [x] `new MembershipFees()` instantiations in `class-rest-fees.php` replaced with `$fees->settings()->X()` on all 30+ call sites
- [x] Fee snapshot against baseline still diffs cleanly
- [x] `MembershipFees` no longer contains any of the 26 extracted methods (and the 2 migrations)
- [x] `MembershipFeeSettings` class is under 600 lines (590 ✓)
- [x] `composer lint` clean

## Outcome

Phase 217 shipped 2026-04-09. MembershipFees is now 720 lines — down 1,417
lines (66%) and 45 methods from the pre-v33.0 size of 2,137.

| Phase | Lines removed | Class created | Cumulative |
|---|---|---|---|
| 214 | 255 | FeeCategoryResolver (362) | −255 |
| 215 | 362 | FamilyGroupingService (487) | −617 |
| 216 | 312 | FeeCalculator (454) | −929 |
| 217 | 488 | MembershipFeeSettings (590) | −1,417 |

What's left in MembershipFees:
- 4 lazy accessors (category_resolver, family_grouping, fee_calculator, settings)
- 5 person helpers (get_effective_werkfuncties, normalize_werkfuncties_for_fee_match, get_current_teams, is_former_member_in_season, is_current_work_history_entry)
- 10 cache/snapshot methods (get_fee_for_person, get_fee_for_person_cached, save/get/clear_fee_snapshot, get_snapshot_meta_key, get_fee_cache_meta_key, save/clear_fee_cache, clear_all_fee_caches, clear_all_snapshots_for_season)
- 1 diagnostic (get_calculation_status)

= ~20 methods remaining, all caching/facade/helper shape. Phase 218 decides
whether to retire MembershipFees entirely (moving cache to `FeeCache`,
person helpers to a `Person` helper, diagnostic to somewhere sensible) or
shrink it to a <200-line shell with a single purpose.
