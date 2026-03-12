# Queued Milestones

## ~~Q001: Remove CardDAV Backend Code~~ ✅

Completed 2026-03-12. Committed as `fdb6b18c`, deployed to production as v31.8.0.

Removed 6 PHP files, 1 Composer dependency (sabre/dav), CardDAV subtab from Settings UI, and all related references. Net -73K lines.

## Q002: Credit Invoice Improvements

**Priority:** Next
**Scope:** Two fixes for credit invoices (creditfacturen)

### 1. Separate email template for credit invoices

**Problem:** Credit invoices currently use the "Standaard e-mail voor gewone facturen" template, which mentions a payment link (`{betaallink}`) and QR code (`{qr_code}`) — neither of which apply to a credit invoice (the club owes the person money, not vice versa).

**Solution:**
- Add a dedicated credit invoice email template to `FinanceConfig` (new option `OPTION_CREDIT_EMAIL_TEMPLATE` + default template)
- Add a credit invoice email heading option
- Template should explain the credit amount and that the club will transfer the funds
- Available variables: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{organisatie_naam}`, `{tuchtzaken_lijst}`
- In `class-rest-invoices.php` send_invoice: select credit template when `invoice_kind === 'credit'`
- In `InvoiceEmailSender::send()`: respect this via the existing `$options['template']` mechanism
- Add credit email template editor in Finance Settings UI (alongside existing template editors)
- Expose in `get_all_settings()` and handle in the update endpoint

### 2. Credit invoices should NOT auto-transition to paid

**Problem:** In `send_invoice()`, credit invoices are immediately set to `rondo_paid` after sending. But the actual bank transfer hasn't happened yet — someone with bank account access needs to manually transfer the funds.

**Solution:**
- Remove the block in `send_invoice()` that auto-transitions credit invoices to `rondo_paid`
- Credit invoices should stay in `rondo_sent` status after sending, like normal invoices
- The admin manually marks them as paid (via existing "Markeer als betaald" button) after completing the bank transfer
- No payment link/QR code is created (this already works correctly)

### 3. Credit invoices should show "Credit" label instead of "Handmatig" on invoice list

**Problem:** On `/financien/facturen`, credit invoices show the type badge "Handmatig" (cyan) because their `invoice_type` is `manual`. There's no way to distinguish them from regular manual invoices.

**Solution:**
- In `Facturen.jsx`, check `invoice_kind === 'credit'` and override the type badge to show "Credit" with a distinct color (e.g. rose/pink to visually contrast with cyan "Handmatig")
- The `invoice_kind` field is already returned by the REST API — no backend changes needed
- Add "Credit" to the type filter options so users can filter for credit invoices specifically

### Files affected

- `includes/class-finance-config.php` — new template option + default + getter
- `includes/class-rest-invoices.php` — credit template selection + remove auto-paid transition
- `includes/class-invoice-email-sender.php` — credit template selection in `send()` (or passed via options)
- `src/pages/Finance/FinanceSettings.jsx` (or equivalent) — credit email template editor
- `src/pages/Finance/Facturen.jsx` — credit type badge + filter option
- Frontend: may need to handle credit invoices in `rondo_sent` status (verify "Markeer als betaald" works)

## Q003: Spelactiviteit Field

**Priority:** After Q002 (M004)
**Scope:** Add `spelactiviteit` (Sportlink KernelGameActivities) to person profiles and people list filter

### 1. ACF field + Sportlink card display

- Add ACF text field `spelactiviteit` in the Sportlink tab of `group_person_fields.json`
- Display in `SportlinkCard.jsx` — text type, label "Spelactiviteit"
- Hidden when empty (consistent with other Sportlink fields)

### 2. People list filter: "Spelactiviteit zonder team"

- Add compound filter: people who have a `spelactiviteit` meta value but are NOT in any team
- Backend: new `spelactiviteit_no_team` filter parameter in `class-rest-people.php`
- Frontend: boolean filter in `PeopleList.jsx` filter columns

### Context

Rondo Sync is being updated separately to import `KernelGameActivities` from Sportlink as `spelactiviteit`. This milestone makes Rondo Club ready to receive, display, and filter on that data.

### Files affected

- `acf-json/group_person_fields.json` — new field in Sportlink tab
- `src/components/SportlinkCard.jsx` — add to fields array
- `includes/class-rest-people.php` — new filter parameter + SQL clause
- `src/pages/People/PeopleList.jsx` — new filter column + state

## Q004: Markeer als betaald

**Priority:** After Q003 (M005)
**Scope:** Record and display who manually marked an invoice as paid and when

### Problem

When a user clicks "Markeer als betaald" on an invoice, the status changes to paid but no audit trail is kept. The Betaalgegevens card only shows for Mollie-paid invoices. For manually-paid invoices, there's no record of who marked it or when.

### Solution

- In `update_invoice_status()`: when transitioning to `paid`, store `_manually_marked_paid_at` (datetime) and `_manually_marked_paid_by` (user ID) as post meta
- In `format_invoice_detail()`: return these fields (with user display name resolved)
- In `FactuurDetail.jsx`: show Betaalgegevens card for manually-paid invoices too, displaying "Handmatig gemarkeerd als betaald op {date} door {user}"

### Files affected

- `includes/class-rest-invoices.php` — store meta on paid transition + return in `format_invoice_detail()`
- `src/pages/Finance/FactuurDetail.jsx` — expand Betaalgegevens card condition and add manual-paid display
