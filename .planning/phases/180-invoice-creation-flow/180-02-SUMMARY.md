---
phase: 180-invoice-creation-flow
plan: 02
subsystem: invoices
tags: [invoice-display, finances-card, ui]
dependency_graph:
  requires: [180-invoice-creation-ui]
  provides: [invoice-profile-display]
  affects: [finances-card]
tech_stack:
  added: []
  patterns: [react-hooks, tanstack-query, status-badges]
key_files:
  created: []
  modified:
    - src/hooks/useInvoices.js
    - src/components/FinancesCard.jsx
decisions:
  - Invoice display uses FileText icon consistent with DisciplineCaseTable
  - Status badges match TanStack Query mutation status pattern (draft/sent/paid/overdue)
  - Dutch labels for status: Concept, Verstuurd, Betaald, Verlopen
  - Section hidden when no invoices exist (no empty state UI)
  - Query enabled only when user has financieel capability
metrics:
  duration_seconds: 137
  task_count: 2
  file_count: 2
  commit_count: 2
  completed_at: 2026-02-15
---

# Phase 180 Plan 02: Invoice Profile Display Summary

**Invoice list on member profile shows status and amounts - users can immediately verify invoice creation worked.**

## Tasks Completed

### Task 1: Add usePersonInvoices hook
- **Commit:** b9d762f0
- **Files:** src/hooks/useInvoices.js
- **Changes:**
  - Added usePersonInvoices(personId, options) hook
  - Query key: ['invoices', 'person', personId]
  - Calls prmApi.getInvoices({ person_id: personId })
  - Returns invoice array from response.data
  - Enabled when personId truthy and options.enabled true
  - staleTime: 30 seconds (matches useInvoicedCaseIds)
  - Updated useCreateInvoice to invalidate ['invoices', 'person'] prefix
  - Ensures FinancesCard refreshes after invoice creation

### Task 2: Display invoices in FinancesCard sidebar
- **Commit:** 7f212556
- **Files:** src/components/FinancesCard.jsx
- **Changes:**
  - Imported usePersonInvoices hook and FileText icon
  - Created StatusBadge inline component with 4 status styles
  - Fetches invoices with query enabled by can_access_financieel
  - Added "Facturen" section at bottom of card (after discipline fees)
  - Section only renders when invoices.length > 0
  - Each invoice shows: invoice_number, StatusBadge(status), total_amount
  - Status labels: Concept (draft), Verstuurd (sent), Betaald (paid), Verlopen (overdue)
  - Dark mode support for all status badge colors
  - Format currency with 2 decimal places via formatCurrency

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:

1. Frontend build: ✓ No errors, compiled successfully in 15.90s
2. usePersonInvoices hook exists: ✓ Found in src/hooks/useInvoices.js
3. Query key pattern: ✓ ['invoices', 'person', personId]
4. Cache invalidation: ✓ ['invoices', 'person'] prefix invalidated on success
5. FinancesCard uses hook: ✓ usePersonInvoices imported and called
6. StatusBadge component: ✓ Inline component defined with 4 status styles
7. Facturen section: ✓ Header with FileText icon rendered when invoices exist
8. FileText icon: ✓ Imported from lucide-react

## Technical Implementation

**Hook Design:**
- usePersonInvoices follows same pattern as useInvoicedCaseIds
- Both use 30-second staleTime (invoice data changes infrequently)
- Query enabled via options.enabled for conditional fetching
- useCreateInvoice invalidates 3 query prefixes: invoiced-case-ids, invoices (all), invoices/person

**UI Pattern:**
- StatusBadge component inline (not exported) - single-use component
- Conditional rendering: section only when invoices.length > 0
- No empty state UI - section simply doesn't appear
- Compact layout: invoice number + badge + amount per line
- Follows FinancesCard design pattern (same as discipline fees section)

**Status Colors:**
- draft: Gray (neutral state)
- sent: Blue (in-flight state)
- paid: Green (success state)
- overdue: Red (alert state)

**Cache Invalidation Flow:**
1. User creates invoice from Tuchtzaken tab
2. useCreateInvoice mutation succeeds
3. Invalidates ['invoices', 'person'] prefix
4. usePersonInvoices re-fetches
5. FinancesCard updates immediately
6. New invoice appears in Facturen section

## Success Criteria Met

- [x] usePersonInvoices hook fetches invoices for specific person
- [x] FinancesCard shows "Facturen" section when person has invoices
- [x] Each invoice displays number, status badge, and amount
- [x] Status badges use Dutch labels with appropriate colors
- [x] Section hidden when user lacks financieel capability
- [x] Section hidden when person has no invoices
- [x] Invoice list updates immediately after creation via cache invalidation
- [x] Frontend build passes

## Self-Check: PASSED

**Modified files verified:**
- FOUND: src/hooks/useInvoices.js
- FOUND: src/components/FinancesCard.jsx

**Commits verified:**
- FOUND: b9d762f0 (Task 1: usePersonInvoices hook)
- FOUND: 7f212556 (Task 2: invoice display in FinancesCard)

## Next Steps

Phase 180 plan execution complete. All 2 plans in phase 180-invoice-creation-flow finished. Next phase will likely add:
- Invoice detail view and editing
- PDF generation and download
- Send invoice workflow (email)
- Payment link integration
- Invoice status transitions
