# Roadmap: v33.0 Fee Service Decomposition

**Drafted:** 2026-04-08
**Activated:** 2026-04-08
**Status:** Active — Phase 214 in progress
**Type:** Internal refactor (no user-facing changes)

## Overview

Break the 2,204-line `Rondo\Fees\MembershipFees` god class into focused services with clear responsibilities. `MembershipFees` is the #1 god node in the codebase (66 edges, cohesion 0.08, 65 methods across ~6 distinct concerns). The precedent for this milestone was set by the SeasonKey helper extraction (commit `e25cef7b`) which validated the refactor pattern on 3 methods across 10 files with zero fee regressions.

Post-milestone, the codebase should have four or five focused services (FeeCategoryResolver, FamilyGroupingService, FeeCalculator, MembershipFeeSettings, optionally FeeCache), and `MembershipFees` itself is either deleted or reduced to a sub-200-line shell.

## Phases

- [ ] **Phase 214: FeeCategoryResolver + Snapshot Infrastructure** — Create WP-CLI snapshot script, extract 8 category-matching methods
- [ ] **Phase 215: FamilyGroupingService** — Extract 7 family-discount methods, clean up FeeCacheInvalidator coupling smell
- [ ] **Phase 216: FeeCalculator** — Extract 4 fee calculation methods (depends on 214 + 215)
- [ ] **Phase 217: MembershipFeeSettings** — Extract ~45 settings CRUD methods (biggest volume, lowest conceptual risk)
- [ ] **Phase 218: Retire MembershipFees** — Decide the fate of what remains; delete or shrink to <200 lines

## Phase Details

### Phase 214: FeeCategoryResolver + Snapshot Infrastructure
**Goal**: A WP-CLI fee snapshot tool exists and fee category resolution lives in a standalone service. Fee values for ≥20 known persons are unchanged.
**Depends on**: Nothing (first phase)
**Requirements**: TEST-01, TEST-02, STRU-02 (FeeCategoryResolver), CORR-01, QUAL-01, QUAL-02, QUAL-03
**Success Criteria** (what must be TRUE):
  1. `bin/fee-snapshot.sh` exists, runs on production SSH, and emits a JSON file with `{person_id, category, base_fee, family_discount, final_fee}` for all active members
  2. A baseline snapshot is captured before any class changes and saved as `v33.0-baseline.json` in the phase directory
  3. `Rondo\Fees\FeeCategoryResolver` class exists with the 8 extracted methods: `get_category`, `get_category_by_age_class`, `get_category_by_team_match`, `get_category_by_werkfunctie_match`, `is_donateur`, `is_recreational_team`, `predict_next_season_age_class`, `find_recreational_team_ids`
  4. Post-extraction fee snapshot diffs cleanly against the baseline — zero person has a different category or final fee
  5. `MembershipFees` no longer contains any of the 8 extracted methods
  6. `composer lint` clean; no PHP syntax errors on any modified file
**Plans**: 2 plans

Plans:
- [ ] 214-01-PLAN.md — Create `bin/fee-snapshot.sh` WP-CLI script, capture baseline snapshot, document snapshot/diff workflow
- [ ] 214-02-PLAN.md — Extract FeeCategoryResolver (8 methods), update internal MembershipFees callers, update external callers (`class-rest-fees.php` forecast endpoint), validate against baseline snapshot

---

### Phase 215: FamilyGroupingService
**Goal**: Family grouping is a standalone service, and `FeeCacheInvalidator` depends on it explicitly rather than reaching into `MembershipFees`. Family keys and positions are unchanged.
**Depends on**: Phase 214 (snapshot infrastructure)
**Requirements**: STRU-02 (FamilyGroupingService), STRU-04, CORR-01, CORR-02, QUAL-01, QUAL-02, QUAL-03
**Success Criteria** (what must be TRUE):
  1. `Rondo\Fees\FamilyGroupingService` class exists with the 7 extracted methods: `build_family_groups`, `get_family_key`, `recalculate_all_family_positions`, `recalculate_family_positions_for_person`, `clear_all_family_discount_meta`, `normalize_postal_code`, `extract_house_number`
  2. `FeeCacheInvalidator` injects or instantiates `FamilyGroupingService` explicitly — it no longer calls `$this->fees->build_family_groups()` or any other family-group method through the `MembershipFees` instance
  3. Family snapshot (family_key → member_ids mapping) is identical before and after the phase for all active members
  4. Fee snapshot against the baseline still diffs cleanly
  5. `MembershipFees` no longer contains any of the 7 extracted methods
  6. `composer lint` clean
**Plans**: 1 plan

Plans:
- [ ] 215-01-PLAN.md — Extract FamilyGroupingService, update `class-fee-cache-invalidator.php` to depend on it directly, update `class-rest-fees.php`, update MembershipFees internal callers, validate family snapshot + fee snapshot

---

### Phase 216: FeeCalculator
**Goal**: The actual fee math lives in a testable standalone calculator service. Depends on Phase 214's FeeCategoryResolver and Phase 215's FamilyGroupingService.
**Depends on**: Phase 214, Phase 215
**Requirements**: STRU-02 (FeeCalculator), CORR-01, QUAL-01, QUAL-02, QUAL-03
**Success Criteria** (what must be TRUE):
  1. `Rondo\Fees\FeeCalculator` class exists with the 4 extracted methods: `calculate_fee`, `calculate_full_fee`, `calculate_fee_with_family_discount`, `get_prorata_percentage`
  2. `FeeCalculator` takes `FeeCategoryResolver` and `FamilyGroupingService` as explicit collaborators (constructor injection or explicit method args — no magic lookups)
  3. Fee snapshot against baseline still diffs cleanly — this is the most sensitive extraction in the milestone
  4. External callers updated: `class-rest-fees.php`, `class-rest-google-sheets.php`, `class-bulk-invoice-creator.php`, `class-fee-cache-invalidator.php`
  5. `MembershipFees` no longer contains any of the 4 extracted methods
  6. `composer lint` clean
**Plans**: 1 plan

Plans:
- [ ] 216-01-PLAN.md — Extract FeeCalculator, wire in FeeCategoryResolver + FamilyGroupingService dependencies, update all external callers, validate against fee snapshot baseline

---

### Phase 217: MembershipFeeSettings
**Goal**: All ~45 settings CRUD methods live in a standalone settings repository. WordPress option keys are unchanged. Fee settings pages round-trip unchanged.
**Depends on**: Phase 214 (can technically run in parallel with 215-216 but sequencing is cleaner)
**Requirements**: STRU-02 (MembershipFeeSettings), STRU-03, CORR-03, CORR-04, CORR-01, QUAL-01, QUAL-02, QUAL-03
**Success Criteria** (what must be TRUE):
  1. `Rondo\Fees\MembershipFeeSettings` class exists with the ~45 extracted CRUD methods: billing method get/set, installment plan 3/8 get/set, installment admin fee get/set, categories get/save for season, family discount config get/save, entry discount config get/save, option_key_for_season, get_all_settings, update_settings, get/update_settings_for_season, get_category_sort_order, get_youth_category_slugs, get_valid_category_slugs, and the remaining storage-layer getters
  2. `wp option list --fields=option_name | grep rondo | sort` diff against pre-phase state is empty
  3. REST response JSON for `/rondo/v1/membership-fees/settings`, `/rondo/v1/fees/list`, `/rondo/v1/fees/summary`, and related endpoints is byte-identical before and after the phase (verified with curl + `jq --sort-keys`)
  4. All ~13 `new MembershipFees()` instantiations in `class-rest-fees.php` have been replaced with the appropriate service (may be multiple services per handler)
  5. Fee snapshot against baseline still diffs cleanly
  6. `MembershipFees` no longer contains any of the ~45 extracted methods
  7. `MembershipFeeSettings` class is under 600 lines
  8. `composer lint` clean
**Plans**: 2 plans

Plans:
- [ ] 217-01-PLAN.md — Extract the ~45 settings CRUD methods into MembershipFeeSettings, move migrations (`maybe_migrate_age_classes`, `maybe_migrate_matching_rules`) along with them or to a dedicated `MembershipFeeMigrations` class, update MembershipFees internal callers
- [ ] 217-02-PLAN.md — Update all external callers (biggest: `class-rest-fees.php` ~13 sites, `class-public-payment-page.php`, `class-bulk-invoice-creator.php`), validate option key stability + REST response stability + fee snapshot

---

### Phase 218: Retire MembershipFees
**Goal**: `MembershipFees` class is deleted or reduced to a <200-line shell with a single clear purpose. All surviving methods have a justified home.
**Depends on**: Phases 214, 215, 216, 217
**Requirements**: STRU-01, QUAL-01, QUAL-02, QUAL-03
**Success Criteria** (what must be TRUE):
  1. After phases 214-217, a decision is made (documented in the phase plan) about what remains: migrations, fee caching methods, work-history helpers
  2. Option A (preferred): `class-membership-fees.php` is deleted entirely, with remaining methods moved to new targeted classes (`FeeCache` by merging with `FeeCacheInvalidator`; work-history helpers to a Person helper; migrations to `MembershipFeeMigrations`)
  3. Option B (fallback): `MembershipFees` is reduced to fewer than 200 lines with a single clear purpose (likely: migrations only)
  4. Fee snapshot against baseline still diffs cleanly
  5. Graphify graph rebuilt and the former `MembershipFees` god node no longer appears in the top 10 by edge count
  6. `composer lint` clean
**Plans**: 1 plan

Plans:
- [ ] 218-01-PLAN.md — Audit remaining `MembershipFees` methods, decide delete vs. shrink, execute the decision, rebuild graphify graph, update developer docs at `../developer/src/content/docs/architecture/`

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 214 | 0/2 | Planned | — |
| 215 | 0/1 | Planned | — |
| 216 | 0/1 | Planned | — |
| 217 | 0/2 | Planned | — |
| 218 | 0/1 | Planned | — |

**Total:** 0/7 plans across 5 phases

## Execution notes

### Cross-repo impact
None. `rondo-sync` consumes the REST API, not PHP classes. No rondo-sync changes needed.

### Version bump
v33.0.0 at milestone shipped. Point releases between phases if desired.

### Deployment strategy
Each phase ships to production after UAT (per Rule 8), same as the SeasonKey precedent. No feature flags needed because nothing is user-facing. Rollback per phase is a simple `git revert`.

### Regression test discipline
Every phase that touches fee math or settings storage MUST follow this discipline:
1. Run `bin/fee-snapshot.sh` against production before making changes → save as `pre-phase-NNN.json`
2. Deploy the refactor
3. Run `bin/fee-snapshot.sh` again → save as `post-phase-NNN.json`
4. `diff pre-phase-NNN.json post-phase-NNN.json` — must be empty
5. Additionally for Phase 217: capture `wp option list | grep rondo` before/after

Non-empty diff = do not move to the next phase. Investigate and fix.

### Parallelism potential
Phases 214 and 215 are independent in principle (different files, different methods) and could be executed in parallel by a capable executor. Phase 217 is also independent of 215-216. The sequential ordering above is the safer path and matches the SeasonKey precedent's "one thing at a time" approach.

---
*Roadmap drafted: 2026-04-08*
*Status: DRAFT — this is a lightweight scoping exercise, not a formally kicked-off GSD milestone. To start execution, review this doc, adjust as needed, then run `/gsd:plan-phase 214` to enter the normal GSD execution flow.*
