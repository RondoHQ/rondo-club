---
phase: 66
plan: 01
subsystem: invoicing
tags: [invoice-pdf, ui-enhancement, user-request]
dependency_graph:
  requires: [invoice-pdf-generation, finance-settings]
  provides: [pdf-regeneration-ui]
  affects: [invoice-detail-page]
tech_stack:
  added: []
  patterns: [confirmation-dialog, loading-states, mutation-reuse]
key_files:
  created: []
  modified: [src/pages/Finance/FactuurDetail.jsx]
decisions:
  - "Reuse existing useGenerateInvoicePdf hook (no backend changes needed)"
  - "Show regenerate button only when PDF exists (pdf_path condition)"
  - "Add confirmation dialog to prevent accidental overwrites"
  - "Place regenerate button after download button (consistent placement across statuses)"
metrics:
  duration: 112s
  tasks_completed: 1
  files_modified: 1
  completed_date: 2026-02-16
---

# Quick Task 66: Add Regenerate Invoice Button

**One-liner:** Added "PDF opnieuw genereren" button to invoice detail page, available for all invoice statuses when a PDF exists.

## Objective

Allow users to regenerate invoice PDFs after changing club branding (logo, accent color) or if a PDF becomes corrupted. The existing "Generate PDF" button only showed for drafts without PDFs, leaving users unable to regenerate PDFs for sent, overdue, or paid invoices.

## Implementation

### UI Changes

Added regenerate PDF button to invoice detail page (`src/pages/Finance/FactuurDetail.jsx`):

**New handler:**
- `handleRegeneratePdf()` - Shows confirmation dialog, calls `generatePdf.mutateAsync(id)`, displays success/error messages
- Confirmation message: "Weet je zeker dat je de PDF opnieuw wilt genereren? Dit overschrijft de bestaande PDF."
- Success message: "PDF opnieuw gegenereerd!"

**Button specifications:**
- Icon: `RefreshCw` (refresh icon)
- Style: `btn-secondary` (consistent with other secondary actions)
- Shows loading spinner when `generatePdf.isPending`
- Disabled when any action is pending (via `isPending` state)
- Only appears when `invoice.pdf_path` exists

**Placement:**
- **Draft status:** After "Download PDF" / "Genereer PDF" buttons
- **Sent/Overdue status:** After "Download PDF" button
- **Paid status:** After "Download PDF" button

### Technical Details

**Reused existing infrastructure:**
- `useGenerateInvoicePdf()` hook (already imported)
- `/rondo/v1/invoices/{id}/generate-pdf` endpoint (supports regeneration)
- `generatePdf.isPending` state for loading indicator
- Existing error/success message patterns

**No backend changes needed:**
- The generate-pdf endpoint already overwrites existing PDFs
- Query invalidation via `useGenerateInvoicePdf` ensures UI updates after regeneration

## Deviations from Plan

None - plan executed exactly as written.

## Testing

**Build verification:**
```bash
npm run build
# ✓ built in 16.72s - no errors
```

**Expected behavior:**
1. Button appears on invoice detail pages for all statuses (when PDF exists)
2. Clicking shows confirmation dialog
3. Confirming regenerates PDF using current branding settings
4. Success message displays: "PDF opnieuw gegenereerd!"
5. Download button updates with new PDF

## Files Modified

**src/pages/Finance/FactuurDetail.jsx** (1 file)
- Added `handleRegeneratePdf()` handler with confirmation dialog
- Added regenerate button to draft status section (conditional on pdf_path)
- Added regenerate button to sent/overdue status section (conditional on pdf_path)
- Added regenerate button to paid status section (conditional on pdf_path)

## Commits

- `34ed6bc4`: feat(66): add regenerate PDF button to invoice detail page

## User Impact

Users can now:
- Regenerate PDFs after changing club logo or accent color in Finance Settings
- Fix corrupted PDFs without having to recreate the entire invoice
- Update PDFs for any invoice status (draft, sent, overdue, paid)

The button is visually distinct from "Download PDF" (uses RefreshCw icon vs Download icon) and includes a confirmation dialog to prevent accidental overwrites.

## Self-Check: PASSED

**Created files:**
None - only modified existing file

**Modified files:**
```bash
FOUND: src/pages/Finance/FactuurDetail.jsx
```

**Commits:**
```bash
FOUND: 34ed6bc4
```

All deliverables verified successfully.
