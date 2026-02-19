---
phase: quick-97
plan: 97
subsystem: invoicing
tags: [invoicing, membership-fees, discounts, pdf, email]
key-files:
  modified:
    - includes/class-bulk-invoice-creator.php
    - includes/class-invoice-pdf-generator.php
    - includes/class-invoice-email-sender.php
decisions:
  - Discount line items use negative amounts (not separate field) — consistent with standard invoice line item model
  - Family discount label includes percentage: "Gezinskorting (25%)"
  - Pro-rata discount label uses "Instapkorting" with percentage: "Instapkorting (25%)"
  - Negative amounts render as "- € X,XX" (and "- &euro; X,XX" in email) for natural readability
metrics:
  duration: 2min
  completed: 2026-02-19
  tasks: 3
  files: 3
---

# Quick Task 97: Show Family Discount and Pro-Rata Discount Line Items Summary

**One-liner:** Membership invoices now show itemized breakdown: base fee, gezinskorting, and instapkorting as separate negative line items in PDF and email renderers.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Modify BulkInvoiceCreator to build discount line items | 8acc7cc0 | class-bulk-invoice-creator.php |
| 2 | Handle negative amounts in PDF renderer | 7774b623 | class-invoice-pdf-generator.php |
| 3 | Handle negative amounts in email renderer | 00bbeed5 | class-invoice-email-sender.php |

## What Changed

### Task 1: BulkInvoiceCreator

Previously `create_membership_invoice()` created a single line item with the `$final_fee` as the amount. Now it builds multiple line items:

1. **Base line:** "Contributie {season}" at `base_fee`
2. **Gezinskorting** (if `family_discount_amount > 0`): negative amount showing the discount with percentage label
3. **Instapkorting** (if `prorata_percentage < 1.0`): negative amount showing the pro-rata discount with percentage label

The `total_amount` ACF field remains `$final_fee` — unchanged.

### Task 2: PDF Renderer

`class-invoice-pdf-generator.php` now renders negative line item amounts as `- € X,XX` instead of `€ -X,XX` for natural readability.

### Task 3: Email Renderer

`class-invoice-email-sender.php` now renders negative line item amounts as `- &euro; X,XX` instead of `&euro; -X,XX` in the HTML email table.

## Deviations from Plan

None — plan executed exactly as written.

## Verification

- `npm run build`: passed (built in 17.97s)
- `npm run lint`: passed (0 warnings, 0 errors)

## Self-Check: PASSED

Files modified:
- FOUND: includes/class-bulk-invoice-creator.php
- FOUND: includes/class-invoice-pdf-generator.php
- FOUND: includes/class-invoice-email-sender.php

Commits:
- FOUND: 8acc7cc0 (feat: BulkInvoiceCreator discount line items)
- FOUND: 7774b623 (fix: PDF negative amounts)
- FOUND: 00bbeed5 (fix: email negative amounts)
