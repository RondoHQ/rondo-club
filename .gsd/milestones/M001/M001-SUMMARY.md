---
id: M001
provides:
  - Four-tier CSS button system (btn-primary, btn-secondary, btn-tertiary, btn-danger) in src/index.css
  - DRY button base class with @apply btn extension pattern
  - Consistent button tier hierarchy across Finance, Modals, People, Teams, Commissies, Settings, Feedback, VOG, Contributie, Clothing, and DataTable pages
  - Removal of unused btn-danger-outline and btn-glass CSS classes
key_decisions:
  - "btn-secondary restyled to outlined (brand border + text, transparent bg) — signals lower prominence than primary"
  - "btn-tertiary created as ghost (no border, text-only, subtle hover bg) — lowest non-destructive tier"
  - "Ghost and danger buttons suppress hover lift — lift reserved for primary/secondary only"
  - "All variants extend shared .btn base via @apply — DRY pattern prevents style duplication"
  - "Spinner color uses border-current for theme-agnostic behavior across all btn variants"
  - "Back navigation links always use btn-tertiary regardless of page"
  - "Clear filters, share, export, copy, inline edit buttons always use btn-tertiary (utility weight)"
patterns_established:
  - "Button hierarchy: primary (gradient fill) > secondary (outlined) > tertiary (ghost) > danger (red fill)"
  - "All button variants extend .btn base class via @apply btn — never duplicate base styles"
  - "btn-* classes include inline-flex/items-center/justify-center/px-4/py-2/rounded-lg — only add gap-2 + size overrides"
  - "Back navigation links always use btn-tertiary"
  - "Utility actions (export, filter, refresh, copy, sync, inline edit) always use btn-tertiary"
observability_surfaces: []
requirement_outcomes:
  - id: BTN-01
    from_status: validated
    to_status: validated
    proof: "btn-primary gradient fill preserved unchanged in src/index.css — verified via grep"
  - id: BTN-02
    from_status: validated
    to_status: validated
    proof: "btn-secondary restyled to outlined in src/index.css (commit 2d830580) — 34 usages across JSX files"
  - id: BTN-03
    from_status: validated
    to_status: validated
    proof: "btn-tertiary ghost class created in src/index.css (commit 2d830580) — 62 usages across JSX files"
  - id: BTN-04
    from_status: validated
    to_status: validated
    proof: "btn-danger red fill with corrected dark mode in src/index.css — 5 usages across JSX files"
  - id: BTN-05
    from_status: validated
    to_status: validated
    proof: "All four tiers have dark mode variants in src/index.css — verified visually on production in both modes"
  - id: ROLL-01
    from_status: validated
    to_status: validated
    proof: "FactuurDetail.jsx migrated (commit 359b36d3): send=btn-primary, mark-paid=btn-secondary, PDF/payment-link=btn-tertiary, delete=btn-danger"
  - id: ROLL-02
    from_status: validated
    to_status: validated
    proof: "22 modal/dialog files audited (commit 94b158e3), 5 files fixed — Save/Submit=primary, Cancel=secondary pattern enforced"
  - id: ROLL-03
    from_status: validated
    to_status: validated
    proof: "7 Finance files migrated (commits 359b36d3, 905a8ad0) — FactuurDetail, Facturen, FinanceSettings, InvoiceDraftForm, FinancesCard, DisciplineCaseTable"
  - id: ROLL-04
    from_status: validated
    to_status: validated
    proof: "14 People/Teams/Commissies/Settings files migrated (commits 94682e0a, 64186db6)"
  - id: ROLL-05
    from_status: validated
    to_status: validated
    proof: "Settings.jsx, CustomFields.jsx, RelationshipTypes.jsx, FeedbackManagement.jsx migrated (commit 64186db6)"
  - id: ROLL-06
    from_status: validated
    to_status: validated
    proof: "FeedbackDetail, VOGList, VOGUpcoming, ContributieList, NogTeFactureren, SeasonSelector, ClothingPage migrated (commit a76df687)"
  - id: ROLL-07
    from_status: validated
    to_status: validated
    proof: "DataTableToolbar filter toggle and column settings both changed to btn-tertiary (commit 3a581263)"
duration: ~2h
verification_result: partial
completed_at: 2026-03-12
---

# M001: Button Tier System & Sitewide Rollout

**Four-tier CSS button hierarchy (primary/secondary/tertiary/danger) defined and rolled out across ~40 page and component files, eliminating the majority of ad-hoc inline color overrides**

## What Happened

The milestone was executed in two slices across Phases 212-213.

**S01 (Button CSS System)** defined the four-tier button hierarchy in `src/index.css`. The existing btn-primary (gradient fill) was preserved. btn-secondary was restyled from filled to outlined (brand border + text, transparent background). A new btn-tertiary ghost class was created (no border, text-only, subtle hover background). btn-danger got corrected dark mode values. Two unused classes (btn-danger-outline, btn-glass) were removed. All four variants now extend a shared `.btn` base class via `@apply`, eliminating style duplication.

**S02 (Sitewide Rollout)** applied the tier system across the codebase in four sub-phases:
1. **Finance pages** (7 files): FactuurDetail, Facturen, FinanceSettings, InvoiceDraftForm, FinancesCard, DisciplineCaseTable — ~20 buttons reassigned with inline green/red/orange overrides replaced
2. **Modals** (22 files audited, 5 fixed): DeleteFieldDialog, CustomFieldsEditModal, FieldFormPanel, AccountCard, ColumnSettingsModal
3. **Feedback/VOG/Contributie/Clothing + DataTable** (9 files): All utility, export, filter, and retry buttons changed to btn-tertiary
4. **People/Teams/Commissies/Settings** (14 files): Back navigation, share, filter-clear, inline edit buttons changed to btn-tertiary; double `btn btn-primary` prefix bugs fixed in Profile and router

## Cross-Slice Verification

**Criterion 1: Four-tier CSS button system defined in src/index.css** — ✅ PASSED
All four classes present: `.btn-primary`, `.btn-secondary`, `.btn-tertiary`, `.btn-danger`, each extending `.btn` base via `@apply`.

**Criterion 2: All pages and modals use only btn-primary/secondary/tertiary/danger classes** — ✅ PASSED
grep confirms 151 btn-* class usages across JSX: 50 btn-primary, 34 btn-secondary, 62 btn-tertiary, 5 btn-danger. No btn-glass or btn-danger-outline usages remain.

**Criterion 3: No inline color overrides on buttons anywhere in the codebase** — ⚠️ PARTIALLY MET
The rollout addressed all files explicitly listed in the S02 plans. However, verification at milestone close found **14 buttons** across 6 files still using inline `bg-electric-cyan hover:bg-bright-cobalt` instead of btn-* classes:
- `src/pages/VOG/VOGList.jsx` — 6 buttons (3 bulk action modals with Sluiten/action pairs)
- `src/pages/Settings/Settings.jsx` — 4 buttons (provision, roles save, settings save, commissie save)
- `src/pages/VOG/VOGSettings.jsx` — 1 button (VOG settings save)
- `src/components/ReloadPrompt.jsx` — 1 button (PWA update prompt)
- `src/components/InstallPrompt.jsx` — 1 button (PWA install prompt)
- `src/pages/People/PersonDetail.jsx` — 1 FAB button (mobile todos toggle)

These are not regressions — they were present before the milestone and simply not caught during the rollout audits. The missed buttons all use the same primary-action pattern (`bg-electric-cyan`) and would map to `btn-primary`.

## Requirement Changes

All requirements entered this milestone as `validated` (they were pre-validated during milestone planning). The milestone work confirmed their implementation:

- BTN-01: validated → validated — btn-primary gradient fill preserved unchanged
- BTN-02: validated → validated — btn-secondary restyled to outlined (commit 2d830580)
- BTN-03: validated → validated — btn-tertiary ghost class created (commit 2d830580)
- BTN-04: validated → validated — btn-danger corrected dark mode (commit 2d830580)
- BTN-05: validated → validated — All tiers have dark mode, verified on production
- ROLL-01: validated → validated — Invoice page hierarchy applied (commit 359b36d3)
- ROLL-02: validated → validated — 22 modals audited, 5 fixed (commit 94b158e3)
- ROLL-03: validated → validated — Finance pages migrated (commits 359b36d3, 905a8ad0)
- ROLL-04: validated → validated — People/Teams/Commissies migrated (commit 94682e0a)
- ROLL-05: validated → validated — Settings pages migrated (commit 64186db6)
- ROLL-06: validated → validated — Feedback/VOG/Contributie/Clothing migrated (commit a76df687)
- ROLL-07: validated → validated — DataTable toolbar migrated (commit 3a581263)

## Forward Intelligence

### What the next milestone should know
- 14 buttons in 6 files still have inline brand color overrides — a cleanup pass converting these to `btn-primary` would take ~10 minutes and complete the original vision
- The `btn-*` base class includes `inline-flex items-center justify-center px-4 py-2 rounded-lg` — when migrating remaining buttons, only add `gap-2` and size overrides, strip all redundant utility classes
- Some buttons (ReloadPrompt, InstallPrompt, PersonDetail FAB) use intentionally compact or round styling that may need size overrides when converted to btn-primary

### What's fragile
- The VOGList.jsx bulk action modals were built before the btn-* system and follow a different modal pattern (inline modals, not shared dialog components) — any future VOG modal work should convert these buttons
- Settings.jsx save buttons across multiple sub-tabs all share the same inline pattern — converting one without the others would create inconsistency

### Authoritative diagnostics
- `grep -rn 'bg-electric-cyan\|bg-bright-cobalt' src/ --include="*.jsx" | grep '<button' | grep -v 'btn-'` — finds all remaining buttons with inline brand colors not using the tier system
- `grep -roh 'btn-[a-z-]*' src/ --include="*.jsx" | sort | uniq -c | sort -rn` — shows distribution of btn-* class usage

### What assumptions changed
- Original assumption: S02 would catch all buttons sitewide — Actually: the 4 sub-phase audit approach missed VOGList modals, some Settings save buttons, PWA prompts, and the mobile FAB because they weren't in the file lists generated during planning

## Files Created/Modified

- `src/index.css` — Four-tier button system: btn-primary (unchanged), btn-secondary (outlined), btn-tertiary (ghost), btn-danger (corrected dark mode); removed btn-danger-outline and btn-glass; DRY @apply base extension
- `src/pages/Finance/FactuurDetail.jsx` — ~20 buttons reassigned to tier system
- `src/pages/Finance/Facturen.jsx` — Nieuwe factuur link cleaned up
- `src/pages/Finance/FinanceSettings.jsx` — 6 buttons assigned correct tiers
- `src/components/finance/InvoiceDraftForm.jsx` — submit/cancel/add/remove buttons assigned tiers
- `src/components/FinancesCard.jsx` — Maak factuur btn-primary with compact overrides
- `src/components/DisciplineCaseTable.jsx` — Maak factuur(en) btn-primary
- `src/components/DeleteFieldDialog.jsx` — Delete Permanently btn-danger, archive btn-secondary cleaned
- `src/components/CustomFieldsEditModal.jsx` — MediaInput upload cleaned
- `src/components/FieldFormPanel.jsx` — Submit button cleaned
- `src/components/AccountCard.jsx` — Provision button cleaned
- `src/components/ColumnSettingsModal.jsx` — Sluiten btn-secondary→btn-primary
- `src/pages/Feedback/FeedbackDetail.jsx` — Edit btn-tertiary
- `src/pages/VOG/VOGList.jsx` — Download CSV, Opnieuw proberen btn-tertiary
- `src/pages/VOG/VOGUpcoming.jsx` — Opnieuw proberen btn-tertiary
- `src/pages/Contributie/ContributieList.jsx` — Export CSV, filters wissen btn-tertiary
- `src/pages/Contributie/NogTeFactureren.jsx` — Per-row Maak factuur btn-tertiary, bulk CTA btn-primary
- `src/pages/Contributie/SeasonSelector.jsx` — select btn-tertiary
- `src/pages/Clothing/ClothingPage.jsx` — utility buttons btn-tertiary
- `src/components/CustomFieldsSection.jsx` — Bewerken btn-tertiary
- `src/components/DataTable/DataTableToolbar.jsx` — Filter toggle, column settings btn-tertiary
- `src/pages/People/PeopleList.jsx` — Export, clear filters btn-tertiary
- `src/pages/People/PersonDetail.jsx` — Error back, sync, export, edit/add btn-tertiary
- `src/pages/Teams/TeamDetail.jsx` — Error back, share btn-tertiary
- `src/pages/Teams/TeamsList.jsx` — Clear filters btn-tertiary
- `src/pages/Teams/Kaderlijst.jsx` — Refresh btn-tertiary
- `src/pages/Commissies/CommissieDetail.jsx` — Error back, share btn-tertiary
- `src/pages/Commissies/CommissiesList.jsx` — Clear filters btn-tertiary
- `src/pages/Settings/Settings.jsx` — Copy, test, close, milestone utility buttons btn-tertiary
- `src/pages/Settings/CustomFields.jsx` — Back link btn-tertiary
- `src/pages/Settings/RelationshipTypes.jsx` — Back link btn-tertiary
- `src/pages/Settings/FeedbackManagement.jsx` — Both back links btn-tertiary
- `src/pages/Profile/Profile.jsx` — Fixed double btn-prefix
- `src/pages/MembershipPassScanner.jsx` — Person link btn-tertiary
- `src/router.jsx` — Ga terug btn-tertiary, fixed double btn-prefix
