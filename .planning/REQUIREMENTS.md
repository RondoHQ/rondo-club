# Requirements: Rondo Club v33.0 Fee Service Decomposition

**Drafted:** 2026-04-08
**Activated:** 2026-04-08
**Status:** Active — Phase 214 in progress
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Milestone Type:** Internal refactor (no user-facing feature changes)

## v33.0 Requirements

Requirements for milestone v33.0. This is a code-health milestone — the user-visible behavior must remain identical, and every requirement is phrased as a correctness or structural constraint on the refactor.

### Structural

- [ ] **STRU-01**: `Rondo\Fees\MembershipFees` class is deleted OR reduced to fewer than 200 lines with a single clear purpose
- [ ] **STRU-02**: Four new focused service classes exist: `FeeCategoryResolver`, `FamilyGroupingService`, `FeeCalculator`, `MembershipFeeSettings`
- [ ] **STRU-03**: Each new service class is under 600 lines and has cohesion score >0.4 in the graphify knowledge graph
- [ ] **STRU-04**: `FeeCacheInvalidator` does not reach into fee business logic — its dependencies are explicit service classes rather than method calls into the old god object

### Correctness (zero regressions)

- [ ] **CORR-01**: Fee calculation produces identical values before and after each phase — snapshot of ≥20 known persons (across all categories, with/without family discount, with/without pro-rata) must match to the cent
- [ ] **CORR-02**: Family grouping produces identical family keys and positions before and after Phase 215 — snapshot diff is empty
- [ ] **CORR-03**: All WordPress option keys under `rondo_*` remain stable across the refactor — `wp option list --fields=option_name | grep rondo` diff is empty before and after Phase 217
- [ ] **CORR-04**: All fee-related REST endpoints return identical JSON shapes before and after the refactor — captured via curl/devtools before each phase and compared after

### Test infrastructure

- [ ] **TEST-01**: A WP-CLI fee snapshot script exists at `bin/fee-snapshot.sh` that dumps `{person_id, category, base_fee, family_discount, final_fee}` for all active members to JSON. Reusable across all phases for regression checking.
- [ ] **TEST-02**: Snapshot discipline is documented: run snapshot → run phase → run snapshot → diff must be empty. Failure to follow this is a red flag, not an edge case.

### Quality

- [ ] **QUAL-01**: `composer lint` is clean across all refactored files after each phase
- [ ] **QUAL-02**: Any existing PHP unit tests in `tests/` remain green after each phase
- [ ] **QUAL-03**: No new cross-class coupling is introduced — each new service has a single, documented reason to be called by each of its callers

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Changes to REST endpoint signatures, URLs, or JSON shapes | This is internal refactoring only — no API contract changes |
| Changes to WordPress option keys | Storage layout is a contract; migrations would drag in unrelated risk |
| Changes to ACF field definitions or database schema | Same — storage contract |
| New fee calculation features or rules | Behavior is preserved verbatim; new features go in a future milestone |
| Changes to `rondo-sync` repo | rondo-sync calls the REST API only, not PHP classes — it is untouched by internal refactors |
| Rewriting the fee caching mechanism | Cache layer is orthogonal; stays as-is (may be reshaped in Phase 218 if a clean merge with FeeCacheInvalidator presents itself) |
| Changes to the Mollie payment flow | Payment code only reads settings via the service layer; behavior stays identical |
| Unit-test backfill for legacy methods | Regression protection comes from fee snapshot diffs, not from adding tests to code that's about to be deleted |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| TEST-01 | Phase 214 (Plan 01) | Planned |
| TEST-02 | Phase 214 (Plan 01) | Planned |
| STRU-02 (FeeCategoryResolver) | Phase 214 | Planned |
| CORR-01 | Phases 214, 215, 216, 217 (every phase validates) | Planned |
| QUAL-01 | Every phase | Planned |
| QUAL-02 | Every phase | Planned |
| QUAL-03 | Every phase | Planned |
| STRU-02 (FamilyGroupingService) | Phase 215 | Planned |
| STRU-04 | Phase 215 | Planned |
| CORR-02 | Phase 215 | Planned |
| STRU-02 (FeeCalculator) | Phase 216 | Planned |
| STRU-02 (MembershipFeeSettings) | Phase 217 | Planned |
| STRU-03 | Phase 217 | Planned |
| CORR-03 | Phase 217 | Planned |
| CORR-04 | Phase 217 | Planned |
| STRU-01 | Phase 218 | Planned |

**Coverage:**
- v33.0 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0

---
*Requirements drafted: 2026-04-08*
*Status: DRAFT — awaiting kickoff. Precedent: SeasonKey helper extraction (commit `e25cef7b`) validated the same pattern on 3 methods across 10 files with zero regressions.*
