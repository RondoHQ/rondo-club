---
phase: 180-invoice-creation-flow
verified: 2026-02-15T14:30:00Z
status: passed
score: 9/9 must-haves verified
---

# Phase 180: Invoice Creation Flow Verification Report

**Phase Goal:** User can select uninvoiced discipline cases on member's Tuchtzaken tab and create a draft invoice that sums case fees.
**Verified:** 2026-02-15T14:30:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Tuchtzaken tab shows checkboxes next to each uninvoiced discipline case | ✓ VERIFIED | DisciplineCaseTable.jsx lines 240-253: checkbox column with select-all header, conditional on canCreateInvoice prop |
| 2 | Already-invoiced discipline cases do NOT show checkboxes (they show a factuur icon/badge instead) | ✓ VERIFIED | DisciplineCaseTable.jsx lines 324-338: FileText icon shown for invoiced cases (line 328), checkbox for uninvoiced (line 331-336) |
| 3 | User can select one or more uninvoiced cases and click 'Maak factuur' button | ✓ VERIFIED | DisciplineCaseTable.jsx lines 215-234: selection toolbar with "Maak factuur" button, handleToggleCase on line 116-126 |
| 4 | Clicking 'Maak factuur' creates a Draft invoice via REST API with selected cases as line items | ✓ VERIFIED | PersonDetail.jsx lines 485-506: handleCreateInvoice calls createInvoice mutation with line_items from selected cases |
| 5 | Invoice total equals sum of selected cases' Boete (administrative_fee) fields | ✓ VERIFIED | PersonDetail.jsx line 493: amount set to parseFloat(dc.acf?.administrative_fee); Backend class-rest-invoices.php lines 291-294: total_amount sums item amounts |
| 6 | After invoice creation, selection resets and invoiced-case state updates | ✓ VERIFIED | PersonDetail.jsx line 505: setSelectedCaseIds(new Set()); useInvoices.js lines 56-58: invalidates invoiced-case-ids query |
| 7 | Member's profile sidebar shows invoices linked to that person | ✓ VERIFIED | FinancesCard.jsx lines 238-256: Facturen section renders when invoices.length > 0, uses usePersonInvoices hook |
| 8 | Each invoice shows invoice number, status badge, total amount, and date | ✓ VERIFIED | FinancesCard.jsx lines 245-252: displays invoice_number, StatusBadge(status), total_amount |
| 9 | Invoice display updates immediately after creating a new invoice on Tuchtzaken tab | ✓ VERIFIED | useInvoices.js line 58: invalidates ['invoices', 'person'] prefix, triggers FinancesCard refresh |

**Score:** 9/9 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-rest-invoices.php` | GET /rondo/v1/invoices/invoiced-cases endpoint returning discipline case IDs that already have invoices | ✓ VERIFIED | Lines 33-52: route registered; lines 145-182: get_invoiced_case_ids() queries invoices, extracts case IDs from line_items, returns unique array |
| `src/hooks/useInvoices.js` | useInvoicedCaseIds hook and useCreateInvoice mutation | ✓ VERIFIED | Lines 10-21: useInvoicedCaseIds; lines 46-61: useCreateInvoice with cache invalidation |
| `src/hooks/useInvoices.js` | usePersonInvoices hook for fetching invoices by person | ✓ VERIFIED | Lines 29-40: usePersonInvoices queries /rondo/v1/invoices with person_id param |
| `src/components/DisciplineCaseTable.jsx` | Checkbox column for uninvoiced cases, selection state, Maak factuur button | ✓ VERIFIED | Lines 240-253: checkbox header; lines 324-338: checkbox/icon per row; lines 215-234: selection toolbar with button |
| `src/pages/People/PersonDetail.jsx` | Passes invoicedCaseIds and onCreateInvoice to DisciplineCaseTable | ✓ VERIFIED | Lines 85-89: useInvoicedCaseIds, useCreateInvoice, selectedCaseIds state; lines 1578-1583: props passed to DisciplineCaseTable |
| `src/components/FinancesCard.jsx` | Facturen section showing person's invoices | ✓ VERIFIED | Lines 238-256: conditional section with FileText icon, invoice list with StatusBadge and amounts |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| `src/hooks/useInvoices.js` | `/rondo/v1/invoices/invoiced-cases` | prmApi.getInvoicedCaseIds | ✓ WIRED | Line 14: calls prmApi.getInvoicedCaseIds(personId); client.js line 296: API method defined |
| `src/hooks/useInvoices.js` | `/rondo/v1/invoices` (POST) | prmApi.createInvoice | ✓ WIRED | Line 51: calls prmApi.createInvoice(data); client.js line 294: POST method defined |
| `src/hooks/useInvoices.js` | `/rondo/v1/invoices` (GET) | prmApi.getInvoices with person_id param | ✓ WIRED | Line 33: calls prmApi.getInvoices({ person_id: personId }); client.js line 292: GET with params |
| `src/components/DisciplineCaseTable.jsx` | `src/hooks/useInvoices.js` | props invoicedCaseIds and onCreateInvoice | ✓ WIRED | Props defined lines 79, 82; used lines 92-93, 313, 325-337; onCreateInvoice called line 221 |
| `src/pages/People/PersonDetail.jsx` | `src/hooks/useInvoices.js` | useInvoicedCaseIds and useCreateInvoice hooks | ✓ WIRED | Line 14: imports hooks; lines 85-89: hooks called; line 485: handleCreateInvoice uses createInvoice.mutateAsync |
| `src/components/FinancesCard.jsx` | `src/hooks/useInvoices.js` | usePersonInvoices hook | ✓ WIRED | Line 6: import; line 50: hook called with personId and enabled option; line 238: invoices array used |

### Requirements Coverage

| Requirement | Status | Blocking Issue |
|-------------|--------|----------------|
| CREATE-01: User can select one or more uninvoiced discipline cases on member's Tuchtzaken tab | ✓ SATISFIED | Checkboxes render for uninvoiced cases, select-all works, selection state managed |
| CREATE-02: Selected cases' fees (Boete field) are summed into invoice total | ✓ SATISFIED | PersonDetail.jsx maps administrative_fee to amount; backend sums amounts; toolbar shows running total |
| CREATE-03: Invoice created in Draft status with case details as line items | ✓ SATISFIED | createInvoice posts line_items array; backend creates rondo_invoice with post_status 'rondo_draft', ACF status 'draft' |

### Anti-Patterns Found

None. All files clean.

- No TODO/FIXME/placeholder comments
- No empty stub implementations
- No console.log-only functions
- Route registration order correct (invoiced-cases before parameterized route)
- Cache invalidation properly configured (3 query keys invalidated)

### Summary

Phase 180 successfully delivers the complete invoice creation workflow. Users can:

1. **View invoiced state:** Discipline cases on Tuchtzaken tab show checkboxes (uninvoiced) or FileText icon (already invoiced)
2. **Select cases:** Click checkboxes to select one or more uninvoiced cases; select-all header toggles all uninvoiced
3. **See running total:** Selection toolbar shows count and sum of administrative_fee fields
4. **Create invoice:** "Maak factuur" button calls REST API, creates Draft invoice with line_items, resets selection
5. **Verify invoice:** FinancesCard immediately shows new invoice with number, status badge, and amount

**Backend implementation:**
- GET /rondo/v1/invoices/invoiced-cases returns flat array of case IDs from all person's invoices
- POST /rondo/v1/invoices creates invoice with line_items repeater, calculates total_amount
- Route registration order prevents "invoiced-cases" being matched as invoice ID
- Permission callback requires 'financieel' capability on all endpoints

**Frontend implementation:**
- useInvoicedCaseIds, useCreateInvoice, usePersonInvoices hooks with 30s staleTime
- Cache invalidation on invoice creation refreshes both invoiced state and invoice list
- DisciplineCaseTable conditionally renders checkbox column when canCreateInvoice=true
- Selection uses Set for O(1) lookup performance
- PersonDetail requires both can_access_fairplay AND can_access_financieel to show UI
- FinancesCard Facturen section only renders when invoices exist and user has financieel capability

**Requirements satisfied:**
- CREATE-01: Selection UI works
- CREATE-02: Fee summation correct
- CREATE-03: Draft invoices created

All 9 observable truths verified. All 6 artifacts substantive and wired. All 6 key links connected. No gaps found.

---

_Verified: 2026-02-15T14:30:00Z_
_Verifier: Claude (gsd-verifier)_
