---
phase: 66
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Finance/FactuurDetail.jsx
autonomous: true

must_haves:
  truths:
    - "User can regenerate PDF for any invoice status (draft, sent, paid, overdue)"
    - "Regenerating updates the PDF with current branding (logo, accent color)"
    - "User sees success feedback after regeneration"
  artifacts:
    - path: "src/pages/Finance/FactuurDetail.jsx"
      provides: "Regenerate PDF button in action buttons section"
      contains: "handleRegeneratePdf"
  key_links:
    - from: "src/pages/Finance/FactuurDetail.jsx"
      to: "/rondo/v1/invoices/{id}/generate-pdf"
      via: "useGenerateInvoicePdf hook"
      pattern: "generatePdf\\.mutateAsync"
---

<objective>
Add a "Regenerate PDF" button to the invoice detail page that allows regenerating the PDF for any invoice status.

Purpose: Users need to regenerate PDFs after changing club branding (logo, accent color) or if a PDF becomes corrupted. The existing "Generate PDF" button only shows for drafts without PDFs.

Output: Button available on all invoice detail pages that regenerates the PDF using current settings.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@src/pages/Finance/FactuurDetail.jsx
@src/hooks/useInvoices.js
@includes/class-rest-invoices.php
@includes/class-invoice-pdf-generator.php
</context>

<tasks>

<task type="auto">
  <name>Add Regenerate PDF button to invoice detail page</name>
  <files>src/pages/Finance/FactuurDetail.jsx</files>
  <action>
Add a "Regenerate PDF" button to the invoice detail page action buttons section.

**Button specifications:**
- Icon: RefreshCw (already imported)
- Label: "PDF opnieuw genereren"
- Handler: `handleRegeneratePdf` - calls `generatePdf.mutateAsync(id)` (reuse existing hook)
- Shows spinner when `generatePdf.isPending`
- Disabled when `isPending` (any action in progress)
- Uses btn-secondary style

**Placement logic:**
- Show for ALL invoice statuses (draft, sent, overdue, paid)
- Add as last button in each status section (after existing buttons)
- For draft: after "Download PDF" / "Genereer PDF"
- For sent/overdue: after "Download PDF"
- For paid: after "Download PDF"

**User confirmation:**
Add window.confirm before regenerating: "Weet je zeker dat je de PDF opnieuw wilt genereren? Dit overschrijft de bestaande PDF."

**Success message:**
Use existing setSuccessMessage: "PDF opnieuw gegenereerd!"

**Error handling:**
Use existing setErrorMessage pattern with error.response?.data?.message fallback

**Implementation notes:**
- The existing `/invoices/{id}/generate-pdf` endpoint (used by `useGenerateInvoicePdf`) already handles regeneration - it overwrites the existing PDF
- The `generatePdf` hook and isPending state are already imported and used
- No backend changes needed - endpoint supports regeneration
- Button should be visually distinct from "Download PDF" (different icon: RefreshCw vs Download)
  </action>
  <verify>
```bash
# Check button is added with correct structure
grep -A 10 "handleRegeneratePdf" src/pages/Finance/FactuurDetail.jsx

# Verify it's in all status sections
grep -B 5 "PDF opnieuw genereren" src/pages/Finance/FactuurDetail.jsx

# Build frontend to verify no errors
npm run build
```
  </verify>
  <done>
- handleRegeneratePdf function exists with confirmation dialog
- Button added to action sections for all statuses (draft, sent, overdue, paid)
- Button uses RefreshCw icon and btn-secondary style
- Button shows loading spinner when generatePdf.isPending
- Frontend builds without errors
  </done>
</task>

</tasks>

<verification>
1. Button appears on invoice detail pages for all statuses
2. Clicking regenerate shows confirmation dialog
3. Confirming regenerates PDF and shows success message
4. Regenerated PDF reflects current branding settings
5. Error handling works (try with invalid invoice)
</verification>

<success_criteria>
- Regenerate PDF button visible on all invoice detail pages
- Button successfully regenerates PDFs for all invoice statuses
- User sees confirmation before regeneration
- Success/error messages display appropriately
- Frontend builds without errors
</success_criteria>

<output>
After completion, create `.planning/quick/66-add-regenerate-invoice-button/66-SUMMARY.md`
</output>
