---
phase: 183-email-delivery
plan: 01
subsystem: invoice-email-delivery
tags: [email, invoices, finance, wp_mail, orchestration]
dependency-graph:
  requires:
    - 181-01 (InvoicePdfGenerator for PDF generation)
    - 182-01 (RabobankPayment for payment link creation)
    - 179-02 (FinanceConfig for email template and settings)
  provides:
    - InvoiceEmailSender service (email delivery with template rendering)
    - Send invoice REST endpoint (orchestrates full send flow)
  affects:
    - Invoice management workflow (enables sending drafts)
tech-stack:
  added:
    - InvoiceEmailSender class (static service pattern)
  patterns:
    - wp_mail for email delivery with PDF attachment
    - Template variable replacement for configurable email body
    - Non-blocking payment link creation (logs error but continues)
    - Status transition Draft → Sent with sent_date and due_date
key-files:
  created:
    - includes/class-invoice-email-sender.php
  modified:
    - includes/class-rest-invoices.php
    - src/api/client.js
    - functions.php
decisions:
  - "wp_mail for email delivery (WordPress native, supports HTML and attachments)"
  - "Template variable replacement using str_replace (6 variables: naam, factuur_nummer, tuchtzaken_lijst, totaal_bedrag, betaallink, organisatie_naam)"
  - "Payment link creation is non-blocking (logs error if fails, email still sent)"
  - "Only draft invoices can be sent (400 error prevents re-sending)"
  - "sent_date set to current_time('Ymd'), due_date calculated using payment_term_days from FinanceConfig"
metrics:
  duration: 172s
  tasks_completed: 2
  files_modified: 3
  completed_date: 2026-02-16
---

# Phase 183 Plan 01: Email Delivery Summary

**One-liner:** Email delivery pipeline for invoices with configurable template, PDF attachment, payment link creation, and status transition orchestration.

## What Was Built

Created the complete email delivery system for invoices:

1. **InvoiceEmailSender service class** - Handles email composition and delivery:
   - Loads email template from FinanceConfig
   - Replaces 6 template variables with invoice data
   - Builds discipline cases list from line_items
   - Attaches PDF file if exists
   - Sends via wp_mail with From header

2. **Send invoice REST endpoint** - Orchestrates the full send flow:
   - Validates invoice is in draft status (prevents re-sending)
   - Generates PDF if not already generated
   - Creates payment link if Rabobank connected (non-blocking)
   - Sends email via InvoiceEmailSender
   - Transitions status from Draft to Sent
   - Sets sent_date (today) and due_date (payment_term_days from config)

3. **Frontend API method** - `sendInvoice(id)` in prmApi for UI integration

## Technical Implementation

### Email Template System

The InvoiceEmailSender processes the configurable email template with 6 variables:

- `{naam}` → Person full name (first_name + infix + last_name)
- `{factuur_nummer}` → Invoice number
- `{tuchtzaken_lijst}` → Formatted list of discipline cases with descriptions, sanctions, and amounts
- `{totaal_bedrag}` → Total amount in Dutch currency format (€ XX,XX)
- `{betaallink}` → Payment link URL or fallback text if not available
- `{organisatie_naam}` → Organization name from FinanceConfig

### Send Flow Orchestration

The send_invoice endpoint executes these steps in order:

1. **Validate** - Check invoice exists and is draft status (400 error if not)
2. **Generate PDF** - Call InvoicePdfGenerator if pdf_path empty
3. **Create payment link** - Call RabobankPayment if connected (non-blocking, logs error if fails)
4. **Send email** - Call InvoiceEmailSender::send() (returns WP_Error on failure)
5. **Transition status** - Update to Sent, set sent_date and due_date

### Non-Blocking Payment Link

Payment link creation is non-blocking: if RabobankPayment returns WP_Error, the error is logged with error_log() but the send flow continues. This ensures email delivery succeeds even if the payment API is unavailable.

### Email Person Discovery

Person email is extracted from contact_info repeater (first entry with contact_type = 'email' or 'Email'). If no email found, send() returns WP_Error('no_email').

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification criteria passed:

- ✅ PHP syntax valid for all modified files
- ✅ Frontend builds without errors (npm run build)
- ✅ InvoiceEmailSender has send() method with wp_mail, template replacement, and PDF attachment
- ✅ Send endpoint registered at /rondo/v1/invoices/{id}/send, validates draft status, orchestrates full send flow
- ✅ Status transition sets sent_date and due_date
- ✅ Frontend sendInvoice(id) method in prmApi
- ✅ InvoiceEmailSender loaded in functions.php

## Files Changed

### Created

- `includes/class-invoice-email-sender.php` (176 lines) - Email delivery service with template rendering

### Modified

- `includes/class-rest-invoices.php` (+105 lines) - Added send_invoice endpoint and route registration
- `src/api/client.js` (+1 line) - Added sendInvoice method to prmApi
- `functions.php` (+1 line) - Added InvoiceEmailSender import

## Commits

- `24c98499` - feat(183-01): create InvoiceEmailSender service class
- `4ae3e0bd` - feat(183-01): add send invoice REST endpoint and frontend API method

## Next Steps

This completes the backend email delivery system. Next phase (184) will add the frontend invoice management UI with send functionality.

## Self-Check: PASSED

✅ All created files exist on disk
✅ All commits exist in git history
✅ Build passes with no errors
✅ All verification commands passed
