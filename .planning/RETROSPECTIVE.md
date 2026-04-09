# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v33.0 — Fee Service Decomposition

**Shipped:** 2026-04-09
**Phases:** 5 (214, 215, 216, 217, 218) | **Plans:** 7 | **Commits:** 11 atomic

### What Was Built

- Deleted `Rondo\Fees\MembershipFees` (2,137 lines, 65 methods, #1 graphify god node with 66 edges and cohesion 0.08) by decomposing it into 8 focused classes: `FeeServices` (locator), `PersonFeeContext`, `FeeCache`, `FeeCategoryResolver`, `FeeCalculator`, `FamilyGroupingService`, `MembershipFeeSettings`, plus the pre-existing `SeasonKey` helper.
- `bin/fee-snapshot.sh` + `bin/fee-snapshot.php` regression harness — WP-CLI JSON snapshot of `{person_id, category, base_fee, family_discount, final_fee}` for every active member (4,021 rows).
- STRU-04 coupling fix: `FeeCacheInvalidator` no longer reaches through a MembershipFees god-object reference; it holds typed `FeeCache` + `FamilyGroupingService` properties resolved from `FeeServices` at construction time.
- 42 fee-settings call sites + 17 `new MembershipFees()` instantiations rewired across 5 files (class-rest-fees, class-public-payment-page, class-bulk-invoice-creator, class-rest-google-sheets, bin/fee-snapshot).

### What Worked

- **Direct-style execution was dramatically faster than the GSD orchestration.** 5 phases, 7 plans, 11 atomic commits, zero regressions, all shipped in under 24 hours end-to-end. The GSD ceremony per phase would have been at least an order of magnitude more tokens and elapsed time for no additional safety — the mechanical nature of a refactor (extract + rewire + diff) doesn't need that machinery. Direct-style worked because (a) the scope was well-defined in the roadmap drafts, (b) the fee snapshot catches semantic drift instantly, and (c) the phases are linearly dependent so there was no parallel work to coordinate.
- **The `bin/fee-snapshot.sh` regression harness was the right investment.** Building it in Phase 214 Plan 01 cost ~30 minutes and then every subsequent phase could validate in under 2 minutes. Zero false positives — every clean diff was a real clean diff. Five phases × 4,021 rows = 20,105 verified fee calculations. Cheaper than unit-test backfill and equally effective.
- **Deferred-closure pattern for circular dependencies is elegant.** `FeeCalculator` needs `FamilyGroupingService` for family discount, `FamilyGroupingService` needs `FeeCalculator` for base fee — classic cycle. Instead of typed properties + construction-time recursion, `FamilyGroupingService` takes a `callable $fee_calculator` that resolves `FeeServices::fee_calculator()->calculate_fee()` at invocation time. The same pattern broke `FeeCache ↔ FeeCalculator` in Phase 218.
- **Triple-clean diff validation in phases 217 + 218.** Fee snapshot vs baseline + fee snapshot vs pre-phase + `wp option list | grep rondo | sort` byte-for-byte diff. Three independent signals that the refactor preserved behaviour.
- **Sub-200-line `FeeServices` locator replaces the MembershipFees facade role without becoming a god node itself.** FeeServices has 6 lazy accessors and zero methods of its own.

### What Was Inefficient

- **`bin/deploy.sh` doesn't `--delete` theme files.** Phase 218 deleted `includes/class-membership-fees.php` locally; the rsync didn't prune it on prod. Required a manual `ssh + rm + composer dump-autoload + wp cache flush`. **Lesson:** Add a `--prune-deleted` flag that runs `git ls-files` + `rsync --delete --files-from` or a post-deploy hook that explicitly removes known-deleted files.
- **GSD `gsd-tools milestone complete` CLI doesn't handle direct-style phases.** It expects per-phase SUMMARY.md files with `requirements-completed` YAML frontmatter; direct-style phases have NOTES.md instead. The CLI produced a minimal MILESTONES.md entry with `(none recorded)` and mangled STATE.md. Required manual fix-up. **Lesson:** Direct-style phases need their own completion path, or the GSD CLI needs to fall back to NOTES.md when SUMMARY.md is absent.
- **Graphify rebuild stuck at ~100% CPU for 3+ hours on a 121-file corpus.** Killed at 181 minutes. Unclear root cause — pipx `graphifyy` vs `graphify` binary, or a pathological rebuild. Don't block milestones on graphify; the code change itself guarantees MembershipFees is gone.
- **Repeated "graphify: Knowledge graph exists. Read GRAPH_REPORT.md first" system reminders on every Grep.** Significant token bloat — the reminder fires on every Grep call regardless of session history. Once per session would be ideal.
- **Mass rename with multiple variable name variants required multiple Edit passes.** `class-rest-fees.php` used `$fees` and `$membership_fees`, `class-public-payment-page.php` used `$fees_service`. **Lesson:** For mass rename refactors, grep all variants up front and build the replacement list once.

### Patterns Established

- **Direct-style extraction loop.** Pre-phase snapshot → research callers via grep → create new class with methods copied verbatim → add lazy accessor on god class → update internal callers → update external callers → delete old methods → composer lint → deploy → post-phase snapshot → diff → commit → push. Zero test backfill, zero planning ceremony; the snapshot diff is the verification.
- **Deferred-closure for circular dependencies.** When service A needs service B and service B needs service A, break the cycle by making one side take a `callable` that resolves to a locator call at invocation time.
- **Triple-signal refactor validation.** Fee snapshot diff + option-list diff + explicit grep verification that the deleted methods have no remaining call sites.
- **`FeeServices` static locator pattern.** Lightweight lazy service graph wiring that replaces god-class facade accessors without reintroducing coupling.

### Key Lessons

- **Investing in a custom regression harness beats unit-test backfill for big mechanical refactors.** Fee snapshot took ~30 min to build and validated 5 phases × 4,021 rows = 20,105 fee calculations.
- **The GSD ceremony can be skipped when the regression net is independent.** Direct-style works because the snapshot diff would catch any behaviour change regardless of whether the orchestrator chain also ran. Skip ceremony when verification strategy is proven.
- **Delete-or-shrink decisions should default to delete.** Option A (delete + 3 new focused classes) is cleaner than Option B (shrink to <200-line facade) because the facade would still be a bridge node in graphify.
- **Deploy scripts that don't `--delete` are a latent trap.** First time in 33 versions that a PHP class was deleted; the tooling should handle the deletion case too.
- **Direct-style execution produces NOTES.md, not VERIFICATION.md + SUMMARY.md.** Worth building an audit template that reads NOTES.md if direct-style becomes the default for refactor milestones.

### Cost Observations

- Model mix: Claude Opus 4.6 (1M context) driving directly, no subagent delegation — single context window held the full milestone end-to-end.
- Sessions: single continuous session for the entire milestone (no `/clear` between phases).
- Notable: holding the full milestone in one context enabled continuity at the cost of token accumulation. For a 5-phase mechanical refactor this was the right trade-off.

---

## Milestone: v32.0 — Interface Touch-up

**Shipped:** 2026-04-08
**Phases:** 3 (212, 213, 213.1) | **Plans:** 6 (1 + 4 + 1 gap closure)

### What Was Built

- Four-tier button CSS system (btn-primary filled gradient, btn-secondary outlined, btn-tertiary ghost, btn-danger red filled) with light/dark mode variants
- Tier hierarchy rolled out across ~54 JSX files spanning Finance, People, Teams, Commissies, Feedback, VOG, Contributie, Clothing, Settings, modals, and DataTable toolbar
- Gap-closure phase 213.1: 21 additional button conversions across Settings sub-pages, VOG bulk modals, InstallPrompt; DRY refactor of src/index.css via CSS selector lists; missing SUMMARY frontmatter fixes

### What Worked

- **Audit-driven gap closure caught real misses.** The post-milestone audit found 21 buttons Phase 213's 4-plan audit missed — Settings.jsx deeply-nested save blocks, FeeCategorySettings.jsx (not in plan inventory), VOGSettings.jsx (not in plan inventory), VOGList.jsx bulk modals (embedded in a list-page file rather than a top-level modal file), InstallPrompt.jsx (PWA component not considered). Without the audit, these would have shipped as silent rollout drift.
- **Decimal phase insertion (213.1) preserved v33.0 draft numbering.** Using 213.1 instead of 214 meant the v33.0 draft roadmap at phases 214-218 didn't need renumbering. Semantic clarity: "213.1 closes 213's gaps."
- **Direct execution for mechanical refactors is fast and low-risk.** Phase 213.1 skipped RESEARCH.md / PLAN.md ceremony and went straight from audit → code → deploy → commit. Total execution time ~40 minutes for 21 button conversions + CSS DRY refactor + docs fixes.
- **Graphify knowledge graph integration revealed the next target.** Running `/graphify` on `includes/` + `src/` during this milestone identified `MembershipFees` as the #1 god node (66 edges, cohesion 0.08, 2,204 lines, 65 methods), directly prompting the v33.0 Fee Service Decomposition drafts.

### What Was Inefficient

- **Initial rollout inventory in Phase 213 was incomplete.** Phase 213's 4 plans walked ~54 JSX files but missed 7 files entirely. The audit was the second pass; shipping without one would have missed these permanently. **Lesson:** For rollout-style phases, add an explicit discovery pass (grep all candidate files) before splitting into plan chunks.
- **Tailwind v4 @apply limitation discovered at build time, not planning time.** First DRY refactor attempt used `@apply btn` extension syntax; Tailwind v4 rejected it with "Cannot apply unknown utility class `btn`". Had to switch to CSS selector lists. **Lesson:** When Tailwind version matters for a refactor, verify syntax assumptions before committing to an approach.
- **MILESTONES.md got stuck at v20.0.** Entries for v21.0-v31.0 were never added to the main log; audits existed but never flowed into MILESTONES.md. v32.0 entry was auto-created as a stub with empty accomplishments and had to be manually filled. **Lesson:** `gsd-tools milestone complete` accomplishment extraction doesn't work reliably for this project; budget time to fill MILESTONES.md manually (per existing memory note).
- **STATE.md drifted for ~4 weeks.** STATE.md said `last_activity: 2026-03-11` and `status: planning` at phase 212, even though phases 212 and 213 were both completed by 2026-03-11. GSD's state tracker didn't update during execution. Not a blocker, but noisy.

### Patterns Established

- **Audit → plan-milestone-gaps → direct execution → re-audit → archive.** This is now the template for milestones where the initial rollout might miss files. Make audit a mandatory step, not optional.
- **Decimal phase numbering for gap closures and insertions.** 213.1 style keeps downstream milestone numbering stable. Matches `/gsd:insert-phase` convention.
- **Direct-style refactor with minimal GSD ceremony.** For mechanical refactors (pure className swaps, pure method moves, verbatim extractions), skip RESEARCH.md / PLAN.md / plan-checker loop. Create phase directory, write SUMMARY.md after execution, document decisions and deliberately-not-done items in frontmatter. Precedent: SeasonKey extraction (commit `e25cef7b`), phase 213.1 (commit `53b31894`).
- **CSS selector lists for DRY component classes in Tailwind v4.** `.btn, .btn-primary, .btn-secondary, .btn-tertiary, .btn-danger { @apply base utilities; }` + variant-specific rules. Idiomatic for v4 CSS-first mode where `@apply` cannot extend custom class names.
- **Graph-driven refactor targeting.** Run `/graphify` before planning a cleanup milestone; the god-nodes list identifies actual candidates with objective metrics (edge count, cohesion score) rather than gut feel.

### Key Lessons

1. **Audit EVERY rollout milestone before archiving.** Phase verification is done per-plan; it doesn't catch files that weren't in ANY plan's inventory. Milestone audit with cross-phase integration check catches inventory gaps.
2. **Memory note "fix MILESTONES.md manually" is load-bearing.** The CLI stubs the entry; the human (or AI-in-session) fills it with actual accomplishments, stats, and "what's next". Budget for this.
3. **Decimal phases are cheap and semantic.** Don't reserve them for emergencies — use them freely for any insertion that closes recent phase gaps. `213.1` reads better than `214` when the work closes `213`.
4. **Tailwind v4 `@apply` is strict about what counts as a utility.** Only Tailwind-registered utilities work; custom component classes don't. Use CSS selector lists instead.
5. **Short milestones with one post-audit gap-closure phase are more honest than long milestones with creeping tech debt.** The gap-closure phase 213.1 + re-audit loop took ~1 hour end-to-end and closed 3 partial requirements. That's less time than pretending "sitewide" meant sitewide in the original 4 plans.

### Cost Observations

- Model mix: Mostly Opus for orchestration + Sonnet for some tool-heavy subagents (GSD profile: balanced)
- Sessions: 2 notable sessions — 2026-03-11 (initial 212+213 rollout) + 2026-04-08 (SeasonKey extraction + audit + 213.1 gap closure + v32.0 archive)
- Notable: The audit subagent (gsd-integration-checker) used ~69K tokens to find the gaps. Direct execution of 213.1 afterward was cheap — mechanical className swaps don't need subagent reasoning, just edits.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Plans | Key Change |
|-----------|----------|--------|-------|------------|
| v30.0 User Accounts | ~1-2 | 6 | 8 | PROV/CAPS/PROF requirement naming pattern solidified |
| v31.0 Editable Contact Fields | ~1-2 | 3 | 7 | Tech debt identified in audit but not closed pre-archive (contact_info repeater legacy) |
| v32.0 Interface Touch-up | 2 | 3 (+1 decimal) | 6 | **Audit-driven gap closure via decimal phase — new pattern** |

### Cumulative Quality

| Milestone | Audit Status | Gap Closure | Nyquist |
|-----------|--------------|-------------|---------|
| v30.0 | passed | — | — |
| v31.0 | tech_debt | deferred to rondo-sync | MISSING (209-211) |
| v32.0 | passed (after 213.1) | **done pre-archive** | MISSING (212, 213) |

### Tech Debt Queue Entering v33.0

- `MembershipFees` god class (2,204 lines, 65 methods) — **target of v33.0**
- Orphaned Google Sheets backend (4 dead client.js methods, 5 unreachable REST routes) — carried from v29.0
- CAPS-05 manual capability override UI — carried from v30.0
- Nyquist VALIDATION.md missing for phases 209-213 — consistent project pattern, decision deferred
- 3 active debug sessions: filter-dropdown-cutoff, invoice-email-missing-qr-and-payment-link, membership-fee-non-player-assignment

---
*Retrospective started: 2026-04-08 with v32.0 milestone close*
