---
phase: quick-80
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-invoices.php
autonomous: true
must_haves:
  truths:
    - "Reset button deletes the PDF file from disk"
    - "Reset button clears the pdf_path ACF field"
    - "Reset button clears sent_date and due_date fields"
    - "After reset, invoice detail shows no PDF (pdf_path empty)"
  artifacts:
    - path: "includes/class-rest-invoices.php"
      provides: "Updated reset_payment_state with PDF cleanup and date clearing"
      contains: "clear_pdf"
  key_links:
    - from: "reset_payment_state()"
      to: "clear_pdf()"
      via: "method call in reset flow"
      pattern: "clear_pdf"
---

<objective>
Extend the reset_payment_state() endpoint to also delete the invoice PDF file from disk, clear the pdf_path ACF field, and clear sent_date and due_date fields so the invoice is fully reset to a clean draft state.

Purpose: When resetting an invoice in test mode, all generated artifacts (payment links, QR codes, PDFs) and sending metadata (dates) should be cleared so the invoice can be re-sent fresh.
Output: Updated reset_payment_state() method in class-rest-invoices.php
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-rest-invoices.php (lines 959-1003 for reset_payment_state, lines 1073-1083 for clear_qr_code pattern)
@src/pages/Finance/FactuurDetail.jsx (line 167 for confirmation dialog text)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add PDF deletion and date clearing to reset_payment_state</name>
  <files>includes/class-rest-invoices.php, src/pages/Finance/FactuurDetail.jsx</files>
  <action>
1. In `includes/class-rest-invoices.php`, add a `clear_pdf()` private method following the exact same pattern as `clear_qr_code()` (lines 1073-1083). Place it right after `clear_qr_code()`:
   - Read `pdf_path` ACF field for the invoice
   - If non-empty, build full path using `wp_upload_dir()['basedir'] . '/' . $pdf_path`
   - If file exists, `unlink()` it
   - Clear the `pdf_path` ACF field with `update_field('pdf_path', '', $invoice_id)`

2. In `reset_payment_state()` method (lines 959-1003), add three new operations between the QR code clearing (line 989) and the status reset (line 991-998):
   - Call `$this->clear_pdf( $invoice_id )` to delete PDF file and clear field
   - Call `update_field( 'sent_date', '', $invoice_id )` to clear sent date
   - Call `update_field( 'due_date', '', $invoice_id )` to clear due date
   - Also call `delete_post_meta( $invoice_id, 'sent_date' )` and `delete_post_meta( $invoice_id, 'due_date' )` since these may also be stored as post meta (the format_invoice method reads them via get_post_meta)

   Add a comment block: `// Clear PDF file and field` before clear_pdf, and `// Clear sending dates` before the date clearing.

3. In `src/pages/Finance/FactuurDetail.jsx`, update the confirmation dialog text (line 167) from:
   `'Weet je zeker dat je de betaalstatus wilt resetten? Dit wist de betaallink en betaalstatus (alleen in testmodus).'`
   to:
   `'Weet je zeker dat je de betaalstatus wilt resetten? Dit wist de betaallink, factuur-PDF, en betaalstatus (alleen in testmodus).'`
   (adds "factuur-PDF" to make it clear the PDF will also be deleted)
  </action>
  <verify>
    Run `npm run build` from /Users/joostdevalk/Code/rondo/rondo-club to verify frontend compiles.
    Grep for `clear_pdf` in class-rest-invoices.php to confirm the new method exists and is called.
    Grep for `sent_date.*''` in the reset_payment_state method to confirm date clearing is present.
  </verify>
  <done>
    reset_payment_state() deletes PDF from disk, clears pdf_path, sent_date, and due_date fields.
    Confirmation dialog mentions PDF deletion.
    Frontend builds successfully.
  </done>
</task>

</tasks>

<verification>
- `clear_pdf()` method exists following same pattern as `clear_qr_code()`
- `reset_payment_state()` calls `clear_pdf()`, clears `sent_date`, clears `due_date`
- Confirmation dialog text updated to mention PDF
- `npm run build` passes
</verification>

<success_criteria>
After reset, invoice has: no PDF file on disk, empty pdf_path, empty sent_date, empty due_date, draft status. Frontend reflects this correctly (no PDF buttons shown, no dates displayed).
</success_criteria>

<output>
After completion, create `.planning/quick/80-reset-button-deletes-pdf-and-resets-pdf-/80-SUMMARY.md`
</output>
