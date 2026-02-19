---
status: resolved
trigger: "Invoice post 6187 (a discipline case invoice) has no invoice_type ACF field value set. It should be 'discipline'."
created: 2026-02-19T00:00:00Z
updated: 2026-02-19T00:03:00Z
symptoms_prefilled: true
---

## Current Focus

hypothesis: RESOLVED
test: Verified via wp post meta get 6187 invoice_type
expecting: N/A
next_action: Archive

## Symptoms

expected: Invoice 6187 should have invoice_type = "discipline"
actual: The invoice_type field is empty/not set
errors: None reported
reproduction: Check invoice 6187's ACF field invoice_type — it's empty
started: Phase 192 of v28.0 added the invoice_type field; this invoice may predate the field or creation code doesn't set it

## Eliminated

- hypothesis: Invoice 6187 predates Phase 192 (created before invoice_type field existed)
  evidence: Invoice 6187 post_date is 2026-02-19 07:57:53 — it was created today, AFTER Phase 192. The field exists but creation code never sets it.
  timestamp: 2026-02-19T00:01:00Z

## Evidence

- timestamp: 2026-02-19T00:00:30Z
  checked: wp post meta list 6187 on production
  found: No invoice_type meta key at all. Has line_items_0_discipline_case=2207, confirming it's a discipline invoice. post_date=2026-02-19 07:57:53.
  implication: Invoice was created today (after Phase 192 field was added), so the field SHOULD be set. Creation code is the bug.

- timestamp: 2026-02-19T00:01:00Z
  checked: create_invoice() function in class-rest-invoices.php lines 551-556
  found: ACF field-setting block calls update_field for invoice_number, person, status, total_amount, line_items — but NOT invoice_type.
  implication: Every discipline invoice created via the REST API has no invoice_type set. This is the root cause.

- timestamp: 2026-02-19T00:01:30Z
  checked: class-wp-cli.php backfill_invoice_type command
  found: WP-CLI command `wp prm invoices backfill_invoice_type` exists and sets invoice_type='discipline' for all invoices without the field set.
  implication: Backfill can fix existing invoices including 6187.

- timestamp: 2026-02-19T00:01:45Z
  checked: class-rest-invoices.php REST API filter for discipline type
  found: OR clause already handles null/NOT EXISTS invoice_type alongside 'discipline' value — a known workaround for this gap.
  implication: The display works for now, but the data is still wrong. Fixed both: creation code + backfill existing.

- timestamp: 2026-02-19T00:02:30Z
  checked: wp post meta get 6187 invoice_type after backfill
  found: "discipline" — field now correctly set
  implication: Backfill worked. Invoice 6187 is fixed. Backfill stats: Updated 1, Skipped 2 (already set), Total 3.

## Resolution

root_cause: create_invoice() in includes/class-rest-invoices.php did not call update_field('invoice_type', 'discipline', $post_id). The BulkInvoiceCreator (contributie) correctly sets invoice_type='membership', but the discipline invoice creation path omitted this field entirely. Every discipline invoice created via the REST API lacked the invoice_type field.
fix: 1. Added update_field('invoice_type', 'discipline', $post_id) to create_invoice() in class-rest-invoices.php (after status, before total_amount). 2. Ran wp prm invoices backfill_invoice_type on production — updated 1 invoice (6187), skipped 2 already-set.
verification: wp post meta get 6187 invoice_type returns "discipline". Deployed fix to production so all future discipline invoices will have invoice_type set at creation time.
files_changed:
  - includes/class-rest-invoices.php
