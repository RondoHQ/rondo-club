# Phase 215 — FamilyGroupingService

**Milestone:** v33.0 Fee Service Decomposition
**Depends on:** Phase 214 (snapshot infrastructure)
**Status:** In progress
**Started:** 2026-04-09
**Style:** Direct execution

## Goal

Family grouping is a standalone service and `FeeCacheInvalidator` depends on
it explicitly rather than reaching into `MembershipFees`. Family keys,
positions, and resulting fees are unchanged.

## Methods extracted

| Method | Signature unchanged? | Notes |
|---|---|---|
| `normalize_postal_code` | yes | Pure string helper |
| `extract_house_number` | yes | Pure string helper |
| `get_family_key` | yes | Reads `addresses` ACF field |
| `build_family_groups` | yes | Calls back into `MembershipFees::calculate_fee()`, `get_youth_category_slugs()`, `is_former_member_in_season()` |
| `recalculate_all_family_positions` | yes | Calls `build_family_groups()` + `MembershipFees::get_family_discount_rate()` |
| `recalculate_family_positions_for_person` | yes | Calls `get_family_key()` + back into `MembershipFees` |
| `clear_all_family_discount_meta` | yes | Pure WP meta cleanup |

## Design

`FamilyGroupingService::__construct(MembershipFees $fees)`. Explicit typed
collaborator — the service needs fee calculation and settings lookups that
still live in `MembershipFees` for now (Phase 216 extracts `FeeCalculator`,
Phase 217 extracts `MembershipFeeSettings`, at which point the dependency
gets rewired).

`MembershipFees` gains a lazy public `family_grouping()` accessor matching
the `category_resolver()` pattern from Phase 214. External callers go
through that accessor.

## Callers to rewire

### Inside `MembershipFees`
- `calculate_fee_with_family_discount()` lines 1688 + 1730 — stays in MembershipFees, routed through `$this->family_grouping()`.

### `class-rest-fees.php`
- Line ~1020 `$fees->build_family_groups($season)` → `$fees->family_grouping()->build_family_groups($season)`
- Line ~1101 `$fees->clear_all_family_discount_meta()` → `$fees->family_grouping()->clear_all_family_discount_meta()`

### `class-fee-cache-invalidator.php` (STRU-04)
This is the coupling smell the milestone is trying to remove. Currently
`FeeCacheInvalidator` owns only a `$this->fees` (MembershipFees) and reaches
through it for family grouping. After Phase 215 it will own an explicit
`$this->family_grouping` (FamilyGroupingService) obtained via
`$this->fees->family_grouping()`, and all 5 family call sites (lines 134,
158, 173, 360, 387) will go through that.

## Snapshot discipline

1. Pre-phase: `bin/fee-snapshot.sh --output .../pre-phase-215.json`
2. Deploy the refactor.
3. Post-phase: `bin/fee-snapshot.sh --output .../post-phase-215.json`
4. Diff against the v33.0 baseline AND against pre-phase-215 — both must be clean. (`pre-phase-215` should match `v33.0-baseline` too; any drift there signals prod state changed between phases.)

## Success criteria (from roadmap)

- [x] `Rondo\Fees\FamilyGroupingService` class exists with 7 extracted methods (446 lines)
- [x] `FeeCacheInvalidator` owns an explicit `FamilyGroupingService` property
  obtained at construction time via `$this->fees->family_grouping()`.
  All 5 family call sites in the invalidator (lines 148, 172, 187, 374,
  401) go through `$this->family_grouping->X()` instead of
  `$this->fees->X()`.
- [x] Family key mapping identical before and after — the fee snapshot's
  `final_fee`/`family_discount` columns are derived from the family key, so
  the clean fee diff below implicitly proves family key stability. (If any
  family key had changed, at least one family member's final_fee would have
  shifted.)
- [x] Fee snapshot diff against the v33.0 baseline still clean (verified
  2026-04-09 post-deploy; also clean against `pre-phase-215.json`)
- [x] `MembershipFees` no longer contains any of the 7 extracted methods
  (file shrunk 1,882 → 1,520 lines)
- [x] `composer lint` clean

## Outcome

Phase 215 shipped 2026-04-09. Combined with Phase 214, MembershipFees has
shed 617 lines (29%) and 15 methods. The `FeeCacheInvalidator → MembershipFees`
coupling smell tagged as STRU-04 is fixed: the invalidator now declares its
family-grouping dependency as a typed property instead of reaching through
a god-object reference.
