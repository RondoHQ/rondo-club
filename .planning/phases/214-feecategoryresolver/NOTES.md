# Phase 214 — FeeCategoryResolver + Snapshot Infrastructure

**Milestone:** v33.0 Fee Service Decomposition
**Status:** In progress
**Started:** 2026-04-09
**Style:** Direct execution (no /gsd:plan-phase ceremony)

## Goal

A WP-CLI fee snapshot tool exists and fee category resolution lives in a
standalone service. Fee values for every active person are unchanged.

## Plans

### Plan 01 — Snapshot Infrastructure

- `bin/fee-snapshot.php` — PHP payload run via `wp eval-file -` over SSH.
  Reads every active person, calls `MembershipFees::calculate_full_fee()`,
  emits a deterministic JSON envelope sorted by `person_id`.
- `bin/fee-snapshot.sh` — Bash wrapper that pipes the PHP payload over SSH
  using `.env` credentials and writes a local JSON file.
- `v33.0-baseline.json` — captured here BEFORE any class changes. This is the
  reference file every phase in v33.0 diffs against.

### Plan 02 — FeeCategoryResolver Extraction

Extract 8 methods from `includes/class-membership-fees.php` into a new
`Rondo\Fees\FeeCategoryResolver` class at `includes/class-fee-category-resolver.php`:

| Method | Old visibility | New visibility |
|---|---|---|
| `predict_next_season_age_class` | public | public |
| `get_category_by_age_class` | public | public |
| `get_category` | public | public |
| `get_category_by_team_match` | private | public |
| `get_category_by_werkfunctie_match` | private | public |
| `is_recreational_team` | private | public |
| `is_donateur` | private → `array $werkfuncties` param | public |
| `find_recreational_team_ids` | private | public |

#### Design decision: categories provider

`FeeCategoryResolver` needs the season's categories array to do its work, and
`get_categories_for_season()` lives in `MembershipFees` (and will move to
`MembershipFeeSettings` in Phase 217). To avoid a hard type dependency on the
god class, `FeeCategoryResolver::__construct()` takes a `callable
$categories_provider` — a closure signature `(string $season): array`. In
Phase 214 that's `[$membership_fees, 'get_categories_for_season']`; in Phase
217 it becomes `[$settings, 'get_categories_for_season']` with no other
change.

#### Design decision: is_donateur signature change

`is_donateur` currently takes `int $person_id` and reaches into
`$this->get_effective_werkfuncties()` (a helper that does NOT move in this
phase). Since we want `FeeCategoryResolver` to be stateless with respect to
Person-level helpers, `is_donateur` now takes `array $werkfuncties` instead.
Callers compute werkfuncties first, then pass them in. This is the only
call-site signature change in Phase 214.

Two callers exist:
1. `MembershipFees::find_recreational_team_ids()` — unaffected (uses
   `is_recreational_team`, not `is_donateur`).
2. `MembershipFees::get_calculation_status()` at the former line 2097 —
   updated to compute `$werkfuncties = $this->get_effective_werkfuncties(
   $person_id )` first and pass them in.

#### External callers

Grep-verified: only one external file calls any of the 8 methods.
- `includes/class-rest-fees.php:842-843` — forecast endpoint calls
  `predict_next_season_age_class()` and `get_category_by_age_class()`. Both
  wired through a `FeeCategoryResolver` instance obtained from
  `MembershipFees` (lazy getter) so nothing else in the file has to change.

## Snapshot / diff workflow (shared across v33.0 phases)

1. **Before a phase**:
   ```bash
   bin/fee-snapshot.sh --output .planning/phases/214-feecategoryresolver/pre-phase-NNN.json
   ```
   Or for the milestone baseline (Phase 214 only):
   ```bash
   bin/fee-snapshot.sh --output .planning/phases/214-feecategoryresolver/v33.0-baseline.json
   ```

2. **After the phase is deployed**:
   ```bash
   bin/fee-snapshot.sh --output .planning/phases/214-feecategoryresolver/post-phase-NNN.json
   diff <(jq -S . .planning/phases/214-feecategoryresolver/v33.0-baseline.json) \
        <(jq -S . .planning/phases/214-feecategoryresolver/post-phase-NNN.json)
   ```
   The `generated_at` field will differ — ignore it. Everything in `rows`
   must be identical. Anything else in the diff blocks the next phase.

3. **If the diff is non-empty**: investigate BEFORE proceeding to the next
   phase. Possibilities include an extraction bug, a migration side-effect,
   or a production data change during the capture window. Do not rebaseline
   without understanding what changed.

## Success criteria (from roadmap)

- [x] `bin/fee-snapshot.sh` exists and emits a JSON file with the required
  fields for all active members
- [x] A baseline snapshot captured before any class changes and saved as
  `v33.0-baseline.json` in this directory (4,021 persons, 844 resolvable
  across 7 categories, 2026-04-09)
- [x] `Rondo\Fees\FeeCategoryResolver` class exists with the 8 extracted
  methods
- [x] Post-extraction fee snapshot diffs cleanly against the baseline — zero
  person has a different category or final fee (verified 2026-04-09:
  `diff <(jq -S .rows v33.0-baseline.json) <(jq -S .rows post-phase-214.json)`
  returns empty)
- [x] `MembershipFees` no longer contains any of the 8 extracted methods
  (file shrunk 2,137 → 1,882 lines)
- [x] `composer lint` clean

## Outcome

Phase 214 shipped 2026-04-09. MembershipFees god node count should drop
from 66 edges (next graphify rebuild). Baseline and post-phase snapshots
are preserved in this directory as reference files for phases 215-217.
