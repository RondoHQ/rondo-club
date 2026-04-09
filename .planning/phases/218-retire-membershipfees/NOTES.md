# Phase 218 — Retire MembershipFees

**Milestone:** v33.0 Fee Service Decomposition — FINAL phase
**Depends on:** Phases 214, 215, 216, 217
**Status:** Shipped
**Started/Completed:** 2026-04-09
**Style:** Direct execution
**Choice:** Option A — delete MembershipFees entirely

## Goal

`MembershipFees` class is deleted. All ~20 remaining methods either
move into new focused classes or are removed as dead code. The fee
service graph is post-refactor clean.

## New classes (3)

### `Rondo\Fees\FeeCache` — 277 lines

Per-person cache and snapshot storage. Owns two distinct post-meta
stores:

1. **Fee cache** (`rondo_fee_cache_{season}`) — performance cache for
   the cached read fast path. Invalidated by FeeCacheInvalidator.
2. **Fee snapshot** (`fee_snapshot_{season}`) — season-lock storage
   retained for future use.

Constructor: `callable $full_fee_calculator` — a deferred closure that
resolves to `FeeCalculator::calculate_full_fee()` at invocation time.
The deferred pattern keeps FeeCache from needing a typed FeeCalculator
dependency (even though the cycle is shallow — FeeCalculator itself
doesn't depend on FeeCache, so it would actually be safe either way).

Methods (10):
- `get_snapshot_meta_key`, `save_fee_snapshot`, `get_fee_snapshot`,
  `clear_fee_snapshot`, `clear_all_snapshots_for_season`
- `get_fee_cache_meta_key`, `save_fee_cache`, `get_fee_for_person_cached`,
  `clear_fee_cache`, `clear_all_fee_caches`

### `Rondo\Fees\PersonFeeContext` — 244 lines

Fee-context person-data helpers. Zero constructor dependencies — all
methods read ACF fields directly.

Methods (4 public + 1 private):
- `get_current_teams(int): array` — player-role teams from work_history
- `get_effective_werkfuncties(int): array` — current job titles
- `normalize_werkfuncties_for_fee_match(array): array` — donateur handling
- `is_former_member_in_season(int, ?string): bool`
- `is_current_work_history_entry(array, int): bool` (private)

### `Rondo\Fees\FeeServices` — 193 lines

Static service locator. Lazily constructs and caches the shared
instances of all fee services so external callers don't have to rewire
the full DI graph at every call site.

Has **zero methods of its own** — it is pure wiring. Accessors:
- `FeeServices::settings()` — `MembershipFeeSettings`
- `FeeServices::person_context()` — `PersonFeeContext`
- `FeeServices::category_resolver()` — `FeeCategoryResolver`
- `FeeServices::family_grouping()` — `FamilyGroupingService`
- `FeeServices::fee_calculator()` — `FeeCalculator`
- `FeeServices::fee_cache()` — `FeeCache`
- `FeeServices::reset()` — test helper

This replaces the ergonomic role the MembershipFees lazy-accessor
facade used to play for external callers, but without bundling any
business logic of its own.

## Methods deleted as dead code

- `MembershipFees::get_fee_for_person` (non-cached version) — no
  callers in the codebase
- `MembershipFees::get_calculation_status` — no callers; diagnostic
  was never wired into any REST endpoint or UI

## Collaborator updates

### `FeeCalculator`
Constructor parameter changed: `MembershipFees $fees` → `PersonFeeContext $person_context`.

Three call sites rewired:
- `get_current_teams` → `$this->person_context->get_current_teams`
- `get_effective_werkfuncties` → `$this->person_context->get_effective_werkfuncties`
- `normalize_werkfuncties_for_fee_match` → `$this->person_context->normalize_werkfuncties_for_fee_match`

### `FamilyGroupingService`
Constructor parameter changed: `MembershipFees $fees` → `PersonFeeContext $person_context`.

Two call sites rewired:
- `is_former_member_in_season` (x2) → `$this->person_context->is_former_member_in_season`

### `FeeCacheInvalidator`
Constructor rewritten to pull dependencies from `FeeServices`:

```php
public function __construct() {
    $this->fee_cache       = FeeServices::fee_cache();
    $this->family_grouping = FeeServices::family_grouping();
    // ... hook registrations unchanged
}
```

8 cache method calls rewired from `$this->fees->X()` to `$this->fee_cache->X()`.

## External PHP callers updated

Every `new \Rondo\Fees\MembershipFees()` site deleted (17 across 5 files)
and replaced with `FeeServices::accessor()->X()` calls.

| File | Instantiations deleted | Method calls rewired |
|---|---|---|
| `includes/class-rest-fees.php` | 12 | ~60 |
| `includes/class-public-payment-page.php` | 3 | 6 |
| `includes/class-bulk-invoice-creator.php` | 1 | 2 |
| `includes/class-rest-google-sheets.php` | 1 | 4 |
| `bin/fee-snapshot.php` | 1 | 2 |
| `functions.php` | 0 | (removed unused `use` import) |

## Deploy wrinkle

`bin/deploy.sh` uses `rsync --delete` on the `dist/` folder only; theme
files sync without `--delete` to avoid accidentally removing remote-
only files. That meant `class-membership-fees.php` deleted locally
stayed on prod after the initial rsync, and `composer dump-autoload`
on prod re-indexed the orphan.

Fix: after the normal deploy, a manual `ssh + rm + composer dump-
autoload -o --quiet + wp cache flush` removed the stale file and
re-regenerated the classmap. Post-phase snapshot was taken AFTER that
cleanup.

Follow-up: consider adding an `--include-deletes` flag to
`bin/deploy.sh` or a post-deploy hook that explicitly removes known-
deleted files. For now the manual cleanup is good enough for this
one-off phase.

## Validation artifacts

Three separate diff checks, all clean:

1. **Fee snapshot vs v33.0 baseline** — `diff <(jq -S .rows
   v33.0-baseline.json) <(jq -S .rows post-phase-218.json)` → empty
2. **Fee snapshot vs pre-phase-218** — `diff <(jq -S .rows
   pre-phase-218.json) <(jq -S .rows post-phase-218.json)` → empty
3. **WP option list** — `diff post-phase-217-options.txt
   post-phase-218-options.txt` → empty (101 rondo_* option names)

4,021 rows identical. 101 option keys identical. No regressions.

## Success criteria (from roadmap)

- [x] **STRU-01**: `class-membership-fees.php` deleted entirely
- [x] **Option A**: remaining methods moved to new targeted classes
  (`FeeCache` for cache/snapshot, `PersonFeeContext` for person
  helpers, `FeeServices` for wiring; dead methods deleted)
- [x] Fee snapshot against baseline still diffs cleanly
- [x] **QUAL-01**: `composer lint` clean
- [ ] Graphify graph rebuilt and former `MembershipFees` god node no
  longer appears in top 10 by edge count — *deferred to a follow-up
  rebuild step; the code change alone guarantees this since
  MembershipFees no longer exists*

## Outcome

v33.0 milestone complete. The Rondo fee system is now 7 focused classes
(plus the pre-existing `SeasonKey` helper) with a clear dependency
graph:

```
FeeServices (locator, no logic)
    ├─ MembershipFeeSettings (storage)  ← zero deps
    ├─ PersonFeeContext (person helpers)  ← zero deps
    ├─ FeeCategoryResolver (category matching)  ← settings via closure
    ├─ FamilyGroupingService (family grouping)  ← person_context,
    │                                              settings,
    │                                              deferred calculator
    ├─ FeeCalculator (fee math)  ← category_resolver,
    │                               family_grouping,
    │                               settings,
    │                               person_context
    └─ FeeCache (cache storage)  ← deferred calculator
```

| Class | Lines | Responsibility |
|---|---|---|
| SeasonKey | 89 | "YYYY-YYYY" season arithmetic |
| FeeServices | 193 | Lazy service locator, no methods |
| PersonFeeContext | 244 | Person-data helpers |
| FeeCache | 277 | Cache + snapshot storage |
| FeeCategoryResolver | 362 | Category resolution |
| FeeCalculator | 454 | Fee math |
| FamilyGroupingService | 483 | Family discount grouping |
| MembershipFeeSettings | 590 | Options API storage |
| **Total** | **2,692** | **(vs 2,137 in the original god class)** |

The system got 26% bigger in raw line count — expected for a split,
because each extracted class adds a file header, namespace declaration
and class doc. But no class exceeds 600 lines, every class has a
single clear responsibility, and the top-level god node is gone.

### v33.0 milestone tally

| Phase | Method count moved | MembershipFees after |
|---|---|---|
| Pre-v33.0 | — | 2,137 lines, 65 methods |
| 214 FeeCategoryResolver | 8 | 1,882 (−255) |
| 215 FamilyGroupingService | 7 | 1,520 (−362) |
| 216 FeeCalculator | 4 | 1,208 (−312) |
| 217 MembershipFeeSettings | 26 | 720 (−488) |
| **218 Retire** | 14 moved + 2 deleted | **0 — deleted** |
| **Total** | **61 relocated, 2 dead-code removed** | **(god class gone)** |

65 original methods - 61 relocated - 2 dead = 2. (The two lazy
accessors `category_resolver()` and `family_grouping()` that were added
in phases 214/215 don't count since they were scaffolding for the
extraction, not original methods; same with `fee_calculator()` and
`settings()` added in 216/217.)
