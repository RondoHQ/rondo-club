# Phase 216 — FeeCalculator

**Milestone:** v33.0 Fee Service Decomposition
**Depends on:** Phase 214 (FeeCategoryResolver), Phase 215 (FamilyGroupingService)
**Status:** Shipped
**Started/Completed:** 2026-04-09
**Style:** Direct execution

## Goal

The actual fee math lives in a testable standalone calculator service
with explicit collaborators. This is the most sensitive extraction in
the milestone — every invoice, forecast, and payment link runs through
it. Fee snapshot must diff cleanly before/after.

## Methods extracted (4)

| Method | Signature unchanged? | Notes |
|---|---|---|
| `calculate_fee` | yes | Base category + price lookup |
| `calculate_fee_with_family_discount` | yes | Applies family discount |
| `calculate_full_fee` | yes | Applies pro-rata on top |
| `get_prorata_percentage` | yes | Period-based prorating |

## Design

`FeeCalculator::__construct(FeeCategoryResolver, FamilyGroupingService,
MembershipFees)` — per roadmap STRU-02, explicit typed collaborators.

The MembershipFees reference is interim: it's only used for helper
methods that still live on the god class (`get_youth_category_slugs`,
`get_fee`, `get_current_teams`, `get_effective_werkfuncties`,
`normalize_werkfuncties_for_fee_match`, `get_family_discount_rate`,
`get_entry_discount_config`). Phase 217 moves the settings helpers to
`MembershipFeeSettings` and most of this goes away.

### Breaking the FamilyGroupingService ↔ FeeCalculator cycle

- `FeeCalculator::calculate_fee_with_family_discount()` calls
  `FamilyGroupingService::get_family_key()` and
  `FamilyGroupingService::build_family_groups()`.
- `FamilyGroupingService::build_family_groups()` needs to call the base
  `calculate_fee()` for every person.

This is a circular dependency if both use typed properties.

**Solution:** `FamilyGroupingService` takes a deferred
`callable $fee_calculator` instead of a typed `FeeCalculator` property.
The closure is `fn($id, $season) => $fees->fee_calculator()->calculate_fee($id, $season)`
set in `MembershipFees::family_grouping()`. The closure is not invoked at
construction time — only when `build_family_groups` actually iterates
and needs a base fee. By then, both services are fully constructed.

Both lazy accessors in `MembershipFees` are idempotent, so whichever is
called first just sets up the other on demand without recursion.

### Visibility promotions

Two private helpers on `MembershipFees` had to go public so
`FeeCalculator` can call them:

- `get_effective_werkfuncties(int $person_id): array`
- `normalize_werkfuncties_for_fee_match(array $werkfuncties): array`

Both are person-data helpers that will likely migrate to a `Person`
helper class in Phase 218. For now they are public.

## Callers rewired

### Inside `MembershipFees`
- `get_fee_for_person` → `$this->fee_calculator()->calculate_fee()`
- `get_fee_for_person_cached` → `$this->fee_calculator()->calculate_full_fee()`
- `get_calculation_status` → `$this->fee_calculator()->calculate_fee()`

### Inside `FamilyGroupingService`
- `build_family_groups` and `recalculate_family_positions_for_person`
  now call `$this->calculate_base_fee()` which dispatches through the
  deferred closure.

### External PHP files
- `includes/class-rest-fees.php:615` — persoon detail forecast
- `includes/class-rest-google-sheets.php:898` — Google Sheets export
- `bin/fee-snapshot.php:77` — the regression harness itself

All three now go through `$fees->fee_calculator()->...`.

## Success criteria (from roadmap)

- [x] `Rondo\Fees\FeeCalculator` class exists with the 4 extracted methods (441 lines)
- [x] `FeeCalculator` takes `FeeCategoryResolver` and `FamilyGroupingService` as explicit constructor collaborators
- [x] Fee snapshot against baseline still diffs cleanly (verified 2026-04-09 post-deploy; also clean against `pre-phase-216.json`)
- [x] External callers updated (`class-rest-fees.php`, `class-rest-google-sheets.php`, `class-bulk-invoice-creator.php`, `class-fee-cache-invalidator.php`)
  - Note: `class-bulk-invoice-creator.php` and `class-fee-cache-invalidator.php` do NOT call any of the 4 extracted methods directly — they use `get_fee_for_person_cached` (which stays in MembershipFees and now delegates to FeeCalculator internally). No direct changes needed in those files.
- [x] `MembershipFees` no longer contains any of the 4 extracted methods
- [x] `composer lint` clean

## Outcome

Phase 216 shipped 2026-04-09. MembershipFees is now 1,208 lines —
down 929 lines (43%) and 19 methods from its pre-v33.0 size of 2,137.

| Phase | Lines removed | Class created | Cumulative |
|---|---|---|---|
| 214 | 255 | FeeCategoryResolver (362) | −255 |
| 215 | 362 | FamilyGroupingService (479) | −617 |
| 216 | 312 | FeeCalculator (441) | −929 |

Next up: Phase 217 (MembershipFeeSettings, ~45 methods, biggest volume
of the milestone but lowest conceptual risk since it's all storage-
layer getters/setters).
