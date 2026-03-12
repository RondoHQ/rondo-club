---
id: S02
parent: M001
milestone: M001
provides:
  - Finance pages using correct button tier hierarchy with no inline color overrides
  - People, Teams, Commissies pages with correct button tier assignments
  - Settings sub-pages with back navigation corrected from btn-primary to btn-tertiary
  - Profile, MembershipPassScanner, router with double-prefix bugs fixed
  - Redundant inline-flex/items-center classes cleaned up across 14 files
requires: []
affects: []
key_files: []
key_decisions:
  - "Spinner color uses border-b-2 border-current for all btn variants (not hardcoded color)"
  - "FinancesCard Maak factuur keeps size overrides (text-xs px-2.5 py-1.5 rounded-md) to stay compact in card context"
  - "FinanceSettings Disconnect Rabobank → btn-danger (destructive external service disconnect)"
  - "FinanceSettings Rekening toevoegen → btn-secondary (adds item to list, secondary importance)"
  - "Sync button in PersonDetail is utility (btn-tertiary) since it's a background data refresh, not a primary action"
  - "Webhook aanmaken button in Settings stays btn-secondary — creates something important and sits next to Save"
  - "Test email send button is utility/helper — btn-tertiary"
  - "Klaar button after password reveal is utility dismiss — btn-tertiary"
  - "FeedbackManagement has two back links (access-denied + header) — both changed to btn-tertiary"
patterns_established:
  - "btn-* classes include inline-flex/items-center/justify-center/px-4/py-2/rounded-lg — only add gap-2 + size overrides"
  - "Back navigation links always use btn-tertiary regardless of which Settings sub-page they appear on"
  - "Clear filters buttons always use btn-tertiary (utility, not a primary action)"
  - "Share/export/copy buttons always use btn-tertiary (utility weight)"
  - "Inline edit triggers (Bewerken/Toevoegen in detail panels) use btn-tertiary"
observability_surfaces: []
drill_down_paths: []
duration: 5min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---
# S02: Sitewide Rollout

**# Phase 213 Plan 01: Finance Button Tier Hierarchy Summary**

## What Happened

# Phase 213 Plan 01: Finance Button Tier Hierarchy Summary

**All Finance pages and components migrated to 4-tier btn-* system, eliminating inline green/red/orange/deep-midnight color overrides from ~20 buttons across 7 files**

## Performance

- **Duration:** 6 min
- **Started:** 2026-03-11T13:37:26Z
- **Completed:** 2026-03-11T13:43:48Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments
- Replaced all inline color overrides (green/red/orange on buttons) in FactuurDetail.jsx with proper tier classes
- Applied btn-primary to all primary CTAs (Send invoice, Save settings, Connect Rabobank, Maak factuur)
- Applied btn-secondary to Mark Paid actions and Cancel buttons
- Applied btn-tertiary to all utility actions (PDF download/generate, payment links, resend, kortingen aanpassen)
- Applied btn-danger to all destructive actions (Verwijder factuur, Reset factuur test, Remove line item, Disconnect Rabobank, Ontkoppelen)
- Removed all redundant flex/items-center/justify-center/px-4/py-2/rounded-lg from btn-* class strings
- Replaced hardcoded spinner border colors with border-current for theme-agnostic behavior

## Task Commits

Each task was committed atomically:

1. **Task 1: Apply tier hierarchy to FactuurDetail.jsx** - `359b36d3` (feat)
2. **Task 2: Apply tier hierarchy to remaining Finance files** - `905a8ad0` (feat)

## Files Created/Modified
- `src/pages/Finance/FactuurDetail.jsx` - ~20 buttons reassigned: send=primary, mark-paid=secondary, PDF/payment-link/resend/utility=tertiary, delete/reset=danger
- `src/pages/Finance/Facturen.jsx` - Nieuwe factuur link: removed redundant btn prefix
- `src/pages/Finance/FinanceSettings.jsx` - Verstuur/Opslaan/Koppelen=primary; Certificaat/Hulp=tertiary; Rekening toevoegen=secondary; Ontkoppelen=danger
- `src/components/finance/InvoiceDraftForm.jsx` - submit=primary, cancel=secondary, add-line=tertiary, remove-line=danger; removed all btn prefixes
- `src/components/FinancesCard.jsx` - Maak factuur: removed inline electric-cyan styling → btn-primary with compact size overrides
- `src/components/DisciplineCaseTable.jsx` - Maak factuur(en): removed bg-deep-midnight/hover:bg-obsidian → btn-primary

## Decisions Made
- Spinner animation color changed from hardcoded `border-green-700`/`border-red-600`/`border-orange-600` to `border-current` — works correctly with any btn variant's text color
- FinancesCard "Maak factuur" keeps explicit size overrides (`text-xs px-2.5 py-1.5 rounded-md`) since it's a compact card action that must stay small

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Fixed additional inline-styled buttons in FinanceSettings.jsx**
- **Found during:** Task 2 (remaining Finance files audit)
- **Issue:** Plan specified only the "Verstuur" test email button in FinanceSettings, but audit found 5 more inline-styled buttons (Koppelen met Rabobank, Rekening toevoegen, Certificaat tonen, Hulp bij instellen, Opslaan save button) with bg-electric-cyan or bg-gray-100 inline overrides
- **Fix:** Applied correct tier classes to all 6 buttons in FinanceSettings.jsx
- **Files modified:** src/pages/Finance/FinanceSettings.jsx
- **Verification:** grep finds no remaining inline-flex bg- overrides on buttons; lint and build pass
- **Committed in:** 905a8ad0 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 2 - missing critical)
**Impact on plan:** Necessary for complete correctness. All Finance buttons now use tier system, not just the ones called out in the plan.

## Issues Encountered
None - plan executed as expected.

## Self-Check: PASSED

All files exist, both commits verified in git history.

## Next Phase Readiness
- Finance module fully migrated to btn-* tier system
- Ready for remaining sitewide rollout phases (213-02 through 213-04)

---
*Phase: 213-sitewide-rollout*
*Completed: 2026-03-11*

# Phase 213 Plan 02: Modal Button Tier Hierarchy Summary

Applied correct btn-* tier hierarchy to all 22 modal/dialog files, with targeted cleanup of redundant Tailwind utility classes on buttons that already use btn-* base classes.

## What Was Built

Audited all 22 modal and dialog files for button tier correctness. The vast majority (17 of 22) were already fully correct with clean btn-primary/btn-secondary patterns. Five files had issues requiring fixes:

1. **DeleteFieldDialog.jsx** — "Delete Permanently" button had full inline style override (`bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2 transition-colors`). Replaced with `btn-danger gap-2`. Also removed redundant `inline-flex items-center` from the archive btn-secondary button.

2. **CustomFieldsEditModal.jsx** — `MediaInput` upload button had `btn-secondary text-sm inline-flex items-center gap-1`. Removed redundant `inline-flex items-center` (already provided by btn-secondary base).

3. **FieldFormPanel.jsx** — Submit button had `btn-primary inline-flex items-center gap-2`. Removed redundant `inline-flex items-center`.

4. **AccountCard.jsx** — Provision button had `btn-primary flex items-center gap-2`. Removed redundant `flex items-center`.

5. **ColumnSettingsModal.jsx** — "Sluiten" (Close) button was btn-secondary but it is the only/confirming action in the footer. Changed to btn-primary per plan spec.

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Apply btn-* tier hierarchy to all modal dialogs (22 files) | 94b158e3 |

## Verification

- `grep -c 'bg-red-600 hover:bg-red-700 text-white rounded-lg' src/components/DeleteFieldDialog.jsx` returns 0
- `npm run build` passes
- `npm run lint` passes (0 warnings)

## Deviations from Plan

None — plan executed exactly as written. All 22 files reviewed; 5 files required changes, 17 were already correct.

## Self-Check: PASSED

- All 5 modified files exist with correct changes
- Commit 94b158e3 exists and verified
- Build and lint pass

# Phase 213 Plan 03: Feedback, VOG, Contributie, Clothing, Todos & DataTable Toolbar Button Tiers Summary

Applied btn-tertiary to all utility/export/filter buttons on Feedback, VOG, Contributie, Clothing pages and the shared DataTable toolbar component.

## What Was Built

All 9 remaining files now use the correct four-tier button hierarchy:
- `btn-primary` — create/send/submit CTAs
- `btn-tertiary` — export, filter, refresh, clear, inline row utilities

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Apply tier hierarchy to Feedback, VOG, Contributie, Clothing pages | a76df687 | 8 files |
| 2 | Apply tier hierarchy to DataTable toolbar | 3a581263 | 1 file |

## Changes By File

**DataTableToolbar.jsx:** Filter toggle button and column settings cog both changed from btn-secondary to btn-tertiary. Active state conditional styling preserved on filter button.

**FeedbackDetail.jsx:** Edit button (inline utility on header) changed to btn-tertiary. Reply/submit (Send) stays btn-primary.

**VOGList.jsx:** Download CSV button and Opnieuw proberen error-state button changed to btn-tertiary.

**VOGUpcoming.jsx:** Opnieuw proberen error-state button changed to btn-tertiary.

**ContributieList.jsx:** Export CSV button, Opnieuw proberen, and Filters wissen (empty state) changed to btn-tertiary.

**NogTeFactureren.jsx:** Per-row Maak factuur button (text-xs utility) and Opnieuw proberen changed to btn-tertiary. Bulk Maak facturen CTA stays btn-primary.

**SeasonSelector.jsx:** select element changed from btn-secondary to btn-tertiary; removed redundant hover:translate-y-0 hover:shadow-none overrides (btn-tertiary has no lift).

**ClothingPage.jsx:** Nieuw item uitgeven (toggle), Retourneren, Verwijder, and Exporteer CSV changed to btn-tertiary. Uitgeven and Item opslaan submit buttons stay btn-primary.

**CustomFieldsSection.jsx:** Bewerken edit button changed to btn-tertiary.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Error-state retry buttons changed to btn-tertiary**
- **Found during:** Task 1
- **Issue:** VOGList, VOGUpcoming, ContributieList, NogTeFactureren all had "Opnieuw proberen" buttons as btn-secondary. These are utility retry actions.
- **Fix:** Changed to btn-tertiary (utility tier) for consistency
- **Files modified:** VOGList.jsx, VOGUpcoming.jsx, ContributieList.jsx, NogTeFactureren.jsx
- **Commit:** a76df687

No other deviations — plan executed as written.

## Self-Check

**Files exist:**
- [x] src/components/DataTable/DataTableToolbar.jsx
- [x] src/pages/Feedback/FeedbackDetail.jsx
- [x] src/pages/VOG/VOGList.jsx
- [x] src/pages/VOG/VOGUpcoming.jsx
- [x] src/pages/Contributie/ContributieList.jsx
- [x] src/pages/Contributie/NogTeFactureren.jsx
- [x] src/pages/Contributie/SeasonSelector.jsx
- [x] src/pages/Clothing/ClothingPage.jsx
- [x] src/components/CustomFieldsSection.jsx

**Commits exist:**
- [x] a76df687
- [x] 3a581263

**Lint and build:** PASS

## Self-Check: PASSED

# Phase 213 Plan 04: People/Teams/Commissies/Settings Button Tier Rollout Summary

**Back navigation, share, filter-clear, and utility buttons across 14 page files migrated from btn-secondary to btn-tertiary; double btn-prefix bugs fixed in Profile and router**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-11T13:17:36Z
- **Completed:** 2026-03-11T13:22:00Z
- **Tasks:** 2
- **Files modified:** 14

## Accomplishments
- People/Teams/Commissies: all back links, share buttons, and clear-filter buttons now use btn-tertiary; inline edit/add buttons in PersonDetail use btn-tertiary
- Settings sub-pages: "Terug naar Instellingen" links corrected from btn-primary to btn-tertiary across CustomFields, RelationshipTypes, and FeedbackManagement (both access-denied and header variants)
- Settings.jsx: copy buttons, test email, password-close, and milestone-add utility actions changed to btn-tertiary; redundant flex/items-center removed from btn-primary buttons
- Fixed double-prefix `btn btn-primary` in Profile.jsx and `btn btn-secondary` in router.jsx
- Cleaned redundant `inline-flex items-center` from Kaderlijst, MembershipPassScanner, and other buttons since btn-* base already includes these styles

## Task Commits

Each task was committed atomically:

1. **Task 1: Apply tier hierarchy to People, Teams, and Commissies pages** - `94682e0a` (feat)
2. **Task 2: Apply tier hierarchy to Settings, Profile, Scanner, and router pages** - `64186db6` (feat)

## Files Created/Modified
- `src/pages/People/PeopleList.jsx` - Export=btn-tertiary, clear filters=btn-tertiary
- `src/pages/People/PersonDetail.jsx` - Error back link, sync, export vCard, all edit/add inline buttons=btn-tertiary
- `src/pages/Teams/TeamDetail.jsx` - Error back link=btn-tertiary, share=btn-tertiary
- `src/pages/Teams/TeamsList.jsx` - Clear filters=btn-tertiary
- `src/pages/Teams/Kaderlijst.jsx` - Refresh button=btn-tertiary, removed redundant inline-flex items-center
- `src/pages/Commissies/CommissieDetail.jsx` - Error back link=btn-tertiary, share=btn-tertiary
- `src/pages/Commissies/CommissiesList.jsx` - Clear filters=btn-tertiary
- `src/pages/Settings/Settings.jsx` - Copy/test/close/milestone utility buttons=btn-tertiary; redundant flex removed
- `src/pages/Settings/CustomFields.jsx` - Back link btn-primary→btn-tertiary; redundant flex removed from "Veld toevoegen"
- `src/pages/Settings/RelationshipTypes.jsx` - Back link btn-primary→btn-tertiary; redundant flex removed from all btns
- `src/pages/Settings/FeedbackManagement.jsx` - Both Terug links btn-secondary/btn-primary→btn-tertiary
- `src/pages/Profile/Profile.jsx` - Fixed `btn btn-primary` double prefix, removed redundant flex
- `src/pages/MembershipPassScanner.jsx` - Person link btn-secondary→btn-tertiary, cleaned inline-flex items-center
- `src/router.jsx` - "Ga terug" `btn btn-secondary`→btn-tertiary

## Decisions Made
- Sync button in PersonDetail is utility (btn-tertiary): background data refresh, not primary action
- Webhook aanmaken stays btn-secondary: creates something important, secondary action next to Save primary
- FeedbackManagement has two separate "Terug naar Instellingen" links — both changed to btn-tertiary
- RelationshipTypes "Standaardwaarden herstellen" utility button: left as btn-secondary per plan (meaningful add-level action)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All 14 files from plan 04 now use correct btn-* tiers
- Build and lint pass clean
- Ready for phase 213 plan 05 (Finance/Contributie pages rollout)

---
*Phase: 213-sitewide-rollout*
*Completed: 2026-03-11*
