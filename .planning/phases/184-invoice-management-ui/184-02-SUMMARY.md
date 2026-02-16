---
phase: 184-invoice-management-ui
plan: 02
subsystem: invoice-management
tags: [frontend, react, ui, invoice-list, invoice-detail, navigation]
dependency_graph:
  requires:
    - phase: 184
      plan: 01
      reason: "Uses invoice React hooks, router infrastructure, and enabled navigation"
  provides:
    - "Facturen list page with status filter and sortable columns"
    - "FactuurDetail page with full invoice management actions"
    - "Clickable invoice links on member profile FinancesCard"
  affects:
    - subsystem: member-profile
      nature: "FinancesCard invoice items now link to detail view"
tech_stack:
  added:
    - "Facturen.jsx list page (283 lines)"
    - "FactuurDetail.jsx detail page (415 lines)"
  patterns:
    - "URL-based filter state persistence (useSearchParams)"
    - "Client-side sorting with visual indicators"
    - "Status-driven action button rendering"
    - "Mutation loading states with disabled buttons"
    - "Auto-hiding success messages (3s timeout)"
key_files:
  created: []
  modified:
    - src/pages/Finance/Facturen.jsx
    - src/pages/Finance/FactuurDetail.jsx
    - src/components/FinancesCard.jsx
    - style.css
    - package.json
    - CHANGELOG.md
decisions:
  - decision: "Facturen list page uses client-side sorting instead of API-based"
    rationale: "Invoice lists are typically small (dozens, not thousands), and client-side sorting provides instant feedback without network round-trips"
    alternatives: ["Server-side sorting with query params", "Hybrid approach with client cache"]
    chosen: "Client-side sorting with useMemo"
  - decision: "Success messages auto-hide after 3 seconds using setTimeout"
    rationale: "Follows established pattern from FeedbackDetail for temporary user feedback without requiring manual dismissal"
    alternatives: ["Manual dismiss button", "Toast notification library", "Persistent success state"]
    chosen: "Auto-hide with useEffect cleanup"
  - decision: "Status-driven button rendering (draft/sent/paid each show different actions)"
    rationale: "Each invoice status has distinct valid operations — draft can send, sent can mark paid/resend, paid only downloads"
    alternatives: ["Show all buttons, disable invalid ones", "Single action button that changes", "Context menu"]
    chosen: "Conditional rendering per status"
metrics:
  duration: 232
  tasks_completed: 2
  files_modified: 6
  commits: 2
  lines_added: 701
  lines_removed: 13
  completed_at: "2026-02-16T11:47:22Z"
---

# Phase 184 Plan 02: Invoice Management UI Summary

**One-liner:** Facturen list and detail pages with full invoice lifecycle management (send, mark paid, resend, download PDF), plus clickable invoice history on member profiles.

## What Was Built

Complete invoice management UI with list view, detail view, and all status-based actions:

1. **Facturen.jsx list page** (283 lines)
   - Status filter dropdown: All, Concept, Verstuurd, Betaald, Verlopen
   - Sortable table columns: Factuurnummer, Lid, Bedrag, Status, Verstuurd, Aangemaakt
   - Click column header to toggle sort direction (asc/desc)
   - Visual sort indicators (ChevronUp/ChevronDown)
   - Responsive: hides Verstuurd and Aangemaakt columns on mobile
   - Invoice number links to detail view at `/financien/facturen/:id`
   - Person name links to member profile
   - StatusBadge component with Dutch labels
   - Empty state: "Geen facturen gevonden" with Receipt icon
   - Pull-to-refresh support

2. **FactuurDetail.jsx detail page** (415 lines)
   - Two-column info card layout: Factuurgegevens (left), Lid (right)
   - Line items table with discipline case details
   - Status-driven action buttons:
     - **Draft:** "Verstuur factuur" (Send icon), "Download PDF" (Download icon)
     - **Sent/Overdue:** "Markeer als betaald" (green CheckCircle), "Opnieuw versturen" (RefreshCw), "Download PDF"
     - **Paid:** "Download PDF" only
   - Confirmation dialogs on destructive actions (send, mark paid, resend)
   - Loading states: spinner replaces icon while mutation pending
   - All buttons disabled during any mutation
   - Success message: auto-hides after 3 seconds
   - Error message: persists until cleared
   - Date formatting: ACF dates (Ymd) parsed via `parse()`, WordPress dates via `new Date()`
   - Payment link: shows as external link if present
   - Person name links to member profile

3. **FinancesCard updates**
   - Invoice items wrapped in `<Link to="/financien/facturen/:id">`
   - Hover effect: `hover:bg-gray-50 dark:hover:bg-gray-700/50`
   - Entire invoice row clickable (number, badge, amount)

4. **Version bump to 26.0.0**
   - `style.css` — Theme header Version field
   - `package.json` — version field
   - `CHANGELOG.md` — v26.0.0 entry with complete feature list

## Deviations from Plan

**None.** Plan executed exactly as written.

## Verification Results

All verification checks passed:

1. ✅ Frontend build: `npm run build` — Compiled successfully in 17.20s
2. ✅ Facturen.jsx: 283 lines, includes status filter and sortable columns
3. ✅ FactuurDetail.jsx: 415 lines, includes status-appropriate action buttons
4. ✅ Status filter: All, Concept, Verstuurd, Betaald, Verlopen options
5. ✅ Sortable columns: invoice_number, person_name, total_amount, status, sent_date, created
6. ✅ StatusBadge: draft (gray), sent (blue), paid (green), overdue (red)
7. ✅ Draft actions: "Verstuur factuur", "Download PDF"
8. ✅ Sent/Overdue actions: "Markeer als betaald", "Opnieuw versturen", "Download PDF"
9. ✅ Paid actions: "Download PDF" only
10. ✅ Mutation loading states: spinners and disabled buttons
11. ✅ FinancesCard: invoice items are clickable Links
12. ✅ Version: 26.0.0 in both style.css and package.json
13. ✅ CHANGELOG.md: v26.0.0 entry with full feature list

## Task Breakdown

**Task 1: Create Facturen list page and FactuurDetail page** (Duration: ~116s)
- Created `src/pages/Finance/Facturen.jsx` — Invoice list with status filter, sortable columns, and clickable rows
- Created `src/pages/Finance/FactuurDetail.jsx` — Invoice detail view with status-appropriate actions (send, mark paid, resend, download PDF)
- Followed FeedbackList/FeedbackDetail patterns for structure
- StatusBadge component with Dutch labels and matching colors from FinancesCard
- Date formatting: ACF dates parsed as 'yyyyMMdd', WordPress dates as ISO
- Action buttons render conditionally based on invoice status
- Loading states and error handling for all mutations
- Success messages auto-hide after 3 seconds
- Verified frontend compilation
- Commit: bec85938

**Task 2: Link FinancesCard invoices to detail view and finalize** (Duration: ~116s)
- Modified `src/components/FinancesCard.jsx` — Wrapped invoice items in Link with hover styling
- Updated `style.css` — Version 25.2.0 → 26.0.0
- Updated `package.json` — Version 25.2.0 → 26.0.0
- Updated `CHANGELOG.md` — Added v26.0.0 entry with complete feature list
- Verified frontend compilation and Link imports
- Commit: 06264da6

## Output Artifacts

**React Components:**
- `src/pages/Finance/Facturen.jsx` — Invoice list page with filters and sorting
- `src/pages/Finance/FactuurDetail.jsx` — Invoice detail page with lifecycle actions
- `src/components/FinancesCard.jsx` — Updated with invoice links

**UI Features:**
- Status filter: All, Concept, Verstuurd, Betaald, Verlopen
- Sortable columns: Factuurnummer, Lid, Bedrag, Status, Verstuurd, Aangemaakt
- Status badges: draft (gray), sent (blue), paid (green), overdue (red)
- Action buttons: send, mark paid, resend, download PDF (status-driven)
- Clickable invoice history on member profiles

**Version:**
- v26.0.0 — Invoice Management System

## Success Criteria

✅ **Complete Facturen UI** — List view with status filter and sortable columns
✅ **Detail view with all actions** — Send, mark paid, resend, download PDF
✅ **Status-appropriate buttons** — Draft/Sent/Paid each show correct actions
✅ **Mutation loading states** — Spinners and disabled buttons during operations
✅ **FinancesCard links** — Invoice items clickable to detail view
✅ **Version bumped to 26.0.0** — Both style.css and package.json
✅ **CHANGELOG updated** — Complete v26.0 feature list

## Self-Check: PASSED

**Files verified to exist:**
```
✓ FOUND: src/pages/Finance/Facturen.jsx
✓ FOUND: src/pages/Finance/FactuurDetail.jsx
✓ FOUND: src/components/FinancesCard.jsx
✓ FOUND: style.css
✓ FOUND: package.json
✓ FOUND: CHANGELOG.md
```

**Commits verified:**
```
✓ FOUND: bec85938 (Task 1 - Facturen and FactuurDetail pages)
✓ FOUND: 06264da6 (Task 2 - FinancesCard links and version bump)
```

All claimed files exist on disk and all commits exist in git history.

## Next Steps

Phase 184 is complete. The invoice management system is fully functional:
- Finance settings page (phase 178)
- Invoice creation from discipline cases (phase 180)
- PDF generation with mPDF (phase 181)
- Rabobank payment link integration (phase 182)
- Email delivery (phase 183)
- Invoice UI pages (this phase)

**Ready for production deployment and user acceptance testing.**
