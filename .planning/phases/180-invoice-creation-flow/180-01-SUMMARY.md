---
phase: 180-invoice-creation-flow
plan: 01
subsystem: invoices
tags: [invoice-creation, discipline-cases, ui, rest-api]
dependency_graph:
  requires: [179-invoice-data-model-rest-api]
  provides: [invoice-creation-ui]
  affects: [discipline-cases-display]
tech_stack:
  added: []
  patterns: [react-hooks, tanstack-query, set-based-selection]
key_files:
  created:
    - src/hooks/useInvoices.js
  modified:
    - includes/class-rest-invoices.php
    - src/api/client.js
    - src/components/DisciplineCaseTable.jsx
    - src/pages/People/PersonDetail.jsx
decisions:
  - Invoiced cases show FileText icon instead of checkbox with 60% opacity
  - Selection state managed via Set for efficient lookups
  - Select-all checkbox has indeterminate state when partial selection
  - Selection toolbar only shows when cases are selected
  - Both fairplay AND financieel capabilities required to create invoices
  - Invoice description uses match_description or sanction_description as fallback
metrics:
  duration_seconds: 375
  task_count: 2
  file_count: 5
  commit_count: 2
  completed_at: 2026-02-15
---

# Phase 180 Plan 01: Invoice Creation UI Summary

**Invoice selection and creation workflow for discipline cases - users can select uninvoiced cases and create draft invoices with one click.**

## Tasks Completed

### Task 1: Add invoiced-cases endpoint and API client method
- **Commit:** d0f661b4
- **Files:** includes/class-rest-invoices.php, src/api/client.js
- **Changes:**
  - Added GET /rondo/v1/invoices/invoiced-cases endpoint
  - Registered route BEFORE single invoice route to avoid pattern conflict
  - Endpoint queries all invoices for person, extracts discipline case IDs from line_items
  - Returns unique array of case IDs that already have invoices
  - Added prmApi.getInvoicedCaseIds(personId) API client method

### Task 2: Add checkbox selection and invoice creation to Tuchtzaken tab
- **Commit:** 86e5ea03
- **Files:** src/hooks/useInvoices.js (new), src/components/DisciplineCaseTable.jsx, src/pages/People/PersonDetail.jsx
- **Changes:**
  - Created useInvoices hook with useInvoicedCaseIds and useCreateInvoice mutations
  - Added checkbox column to DisciplineCaseTable (first column when canCreateInvoice=true)
  - Uninvoiced cases show checkboxes, invoiced cases show FileText icon at 60% opacity
  - Select-all header checkbox with indeterminate state support
  - Selection toolbar shows count and running total (electric-cyan background)
  - "Maak factuur" button creates Draft invoice via REST API
  - PersonDetail wires up invoice hooks, selection state, and handlers
  - Selection resets when switching away from discipline tab
  - Requires both can_access_fairplay AND can_access_financieel to show UI

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:

1. PHP syntax check: ✓ No errors in class-rest-invoices.php
2. Frontend build: ✓ No errors, all modules compiled
3. REST route registered: ✓ invoiced-cases route found
4. API client method: ✓ getInvoicedCaseIds exists
5. Hooks file exists: ✓ src/hooks/useInvoices.js created
6. DisciplineCaseTable checkbox support: ✓ canCreateInvoice prop implemented
7. PersonDetail invoice creation: ✓ handleCreateInvoice wired up

## Technical Implementation

**Backend:**
- New REST endpoint queries all non-trash invoices for person via ACF person field
- Extracts discipline_case IDs from line_items repeater field
- Returns flat array of unique IDs (deduped with array_unique)
- Route registration order matters - /invoiced-cases must precede /(?P<id>\d+) pattern

**Frontend:**
- useInvoicedCaseIds hook with 30s staleTime (invoiced state changes infrequently)
- useCreateInvoice mutation invalidates both invoiced-case-ids and invoices query keys
- DisciplineCaseTable handles both Set and Array for invoicedCaseIds (converts to Set)
- Selection state uses Set for O(1) lookup performance
- Checkbox onChange stops propagation to prevent row expand on click
- Selection toolbar calculates total by filtering cases and summing administrative_fee
- PersonDetail requires both capabilities: canAccessFairplay && canAccessFinancieel

**UX Patterns:**
- Already-invoiced cases are visually distinct (icon + reduced opacity)
- Select-all checkbox shows indeterminate state for partial selections
- Selection toolbar only appears when cases are selected
- Running total updates in real-time as selection changes
- Button shows spinner during invoice creation
- Selection auto-resets on tab switch

## Success Criteria Met

- [x] Backend endpoint returns invoiced discipline case IDs for a person
- [x] DisciplineCaseTable displays checkboxes for uninvoiced cases and badges for invoiced cases
- [x] Selection toolbar shows selected count and running total
- [x] "Maak factuur" button creates invoice and resets selection
- [x] Frontend build passes

## Self-Check: PASSED

**Created files verified:**
- FOUND: src/hooks/useInvoices.js

**Modified files verified:**
- FOUND: includes/class-rest-invoices.php
- FOUND: src/api/client.js
- FOUND: src/components/DisciplineCaseTable.jsx
- FOUND: src/pages/People/PersonDetail.jsx

**Commits verified:**
- FOUND: d0f661b4 (Task 1: invoiced-cases endpoint)
- FOUND: 86e5ea03 (Task 2: invoice selection and creation UI)

## Next Steps

Plan 180-02 will add:
- Invoice detail view with line items
- PDF generation and download
- Send invoice workflow
- Payment link integration
