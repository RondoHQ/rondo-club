# Roadmap: v32.0 Interface Touch-up

## Overview

Establish a four-tier button hierarchy (filled primary, outlined secondary, ghost tertiary, destructive danger) in CSS, then roll out the correct tier assignments across all ~54 JSX files in the application. The result is a visually consistent UI where button prominence always signals action weight.

## Phases

- [x] **Phase 212: Button CSS System** - Define all four button tiers and dark mode variants in src/index.css (completed 2026-03-11)
- [x] **Phase 213: Sitewide Rollout** - Apply correct tier hierarchy to every page, modal, and toolbar (completed 2026-03-11)
- [ ] **Phase 213.1: Button Rollout Closure** - Close gaps from v32.0 milestone audit: convert ~16 legacy inline-styled buttons across 7 files, fix deferred tech debt (gap-closure phase, inserted 2026-04-08)

## Phase Details

### Phase 212: Button CSS System
**Goal**: The four button tier classes are defined and visually correct across light and dark mode
**Depends on**: Nothing (first phase)
**Requirements**: BTN-01, BTN-02, BTN-03, BTN-04, BTN-05
**Success Criteria** (what must be TRUE):
  1. btn-primary renders as a filled gradient button, visually identical to the current primary style
  2. btn-secondary renders as an outlined button with brand-colored border and text and no fill
  3. btn-tertiary renders as a ghost button with no border, text-only, and a subtle hover background
  4. btn-danger renders as a red filled button with white text, used for all destructive actions
  5. All four tiers have distinct and legible appearances in both light mode and dark mode
**Plans:** 1/1 plans complete
Plans:
- [ ] 212-01-PLAN.md — Define four-tier button CSS system with DRY refactor

### Phase 213: Sitewide Rollout
**Goal**: Every button in the application uses the correct tier for its action weight, with no rogue inline styles or unclassified buttons
**Depends on**: Phase 212
**Requirements**: ROLL-01, ROLL-02, ROLL-03, ROLL-04, ROLL-05, ROLL-06, ROLL-07
**Success Criteria** (what must be TRUE):
  1. On invoice detail pages, send action uses primary, mark-paid uses secondary, PDF/payment link actions use tertiary, and delete uses danger
  2. In every modal dialog, the submit/save action uses primary and cancel uses secondary
  3. On Finance list, settings, and draft-form pages all buttons follow the tier hierarchy
  4. On People, Teams, Commissies, Feedback, VOG, Contributie, Clothing, and Settings pages all buttons follow the tier hierarchy
  5. DataTable toolbar action buttons use tertiary for utility actions, with no unlabeled or incorrectly tiered buttons remaining
**Plans:** 4/4 plans complete
Plans:
- [ ] 213-01-PLAN.md — Apply tier hierarchy to Finance pages and invoice detail
- [ ] 213-02-PLAN.md — Apply tier hierarchy to all modal dialogs
- [ ] 213-03-PLAN.md — Apply tier hierarchy to Feedback/VOG/Contributie/Clothing/Todos and DataTable toolbar
- [ ] 213-04-PLAN.md — Apply tier hierarchy to People/Teams/Commissies, Settings, and remaining pages

### Phase 213.1: Button Rollout Closure
**Goal**: Every button flagged by the v32.0 milestone audit uses the correct btn-* tier class, and the deferred tech debt items from the audit are resolved.
**Depends on**: Phases 212, 213
**Requirements**: ROLL-02, ROLL-05, ROLL-06 (reset from satisfied to pending; gap closure)
**Type**: Gap-closure phase (decimal insertion; does not shift v33.0 numbering)
**Success Criteria** (what must be TRUE):
  1. `src/pages/Settings/Settings.jsx`, `src/pages/Settings/FeeCategorySettings.jsx`, and `src/pages/VOG/VOGSettings.jsx` contain zero inline `bg-electric-cyan hover:bg-bright-cobalt` button styles — all save/cancel actions use `btn-primary`/`btn-secondary`
  2. The 3 bulk-action modals embedded in `src/pages/VOG/VOGList.jsx` (send-emails, mark-justis, send-reminders, lines 718-863) use `btn-primary`/`btn-secondary` instead of inline styles
  3. `src/components/InstallPrompt.jsx:63` uses `btn-primary` for the install button
  4. The 12 redundant `flex items-center` / `inline-flex items-center` classes alongside `btn-*` (from Phase 213 VERIFICATION) are cleaned up across TodoModal, VOGUpcoming, VOGList, FeedbackList, FeedbackDetail, ContributieList, NogTeFactureren, ContributieOverzicht, TodosList
  5. `src/index.css` btn-* variants extend via `@apply btn` instead of each duplicating the full base @apply chain (Phase 212 DRY anti-pattern fix)
  6. `213-02-SUMMARY.md` and `213-03-SUMMARY.md` have correct `requirements-completed:` frontmatter
  7. Re-running `/gsd:audit-milestone v32.0` returns status `passed` with 12/12 satisfied

## Progress

**Execution Order:**
Phases execute in numeric order: 212 -> 213 -> 213.1

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 212. Button CSS System | 1/1 | Complete    | 2026-03-11 |
| 213. Sitewide Rollout | 4/4 | Complete    | 2026-03-11 |
| 213.1. Button Rollout Closure | 0/1 | In Progress | — |
