# M003: Credit Invoice Improvements — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Fix two issues with credit invoices (creditfacturen): they use the wrong email template (mentions payment links/QR codes that don't apply), and they auto-transition to paid status before the actual bank transfer happens.

## Why This Milestone

Credit invoices represent money the club owes to a person — the opposite of a normal invoice. The current implementation reuses the normal invoice email template which mentions `{betaallink}` and `{qr_code}`, creating a confusing email. Additionally, credit invoices are auto-marked as paid on send, but the actual bank transfer still needs to happen manually by someone with bank access.

## User-Visible Outcome

### When this milestone is complete, the user can:

- Send a credit invoice with a dedicated email template that explains the credit (no payment link/QR code references)
- Configure the credit invoice email template in Finance Settings
- See a sent credit invoice remain in "Verstuurd" status until manually marked as paid after the bank transfer

### Entry point / environment

- Entry point: Invoice detail page at `/financien/facturen/:id` and Finance Settings
- Environment: Production (https://rondo.svawc.nl)
- Live dependencies involved: none (email via existing wp_mail/Lettermint)

## Completion Class

- Contract complete means: Credit invoices use their own template; sent credit invoices stay in rondo_sent status
- Integration complete means: A credit invoice can be created, sent (with correct email), then manually marked paid
- Operational complete means: Existing credit invoices already marked paid are unaffected

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Creating and sending a credit invoice uses the new credit-specific email template (no betaallink/QR references)
- A sent credit invoice stays in "Verstuurd" status and can be manually marked as "Betaald"
- The credit email template is configurable in Finance Settings

## Risks and Unknowns

- **Minimal** — Both changes are small, well-understood modifications to existing code paths

## Existing Codebase / Prior Art

- `includes/class-finance-config.php` — Stores email templates as WP options with defaults. Has patterns for discipline, membership, manual, installment, and reminder templates.
- `includes/class-rest-invoices.php` — `send_invoice()` method handles template selection (line ~1375) and credit auto-paid transition (line ~1440).
- `includes/class-invoice-email-sender.php` — `send()` method selects template based on invoice_type. Needs credit invoice_kind check.
- `src/pages/Finance/FinanceSettings.jsx` — Finance settings UI with email template editors.

> See `.gsd/DECISIONS.md` for all architectural and pattern decisions.

## Scope

### In Scope

- New credit email template option in FinanceConfig (option, default, getter)
- Credit email heading option
- Template selection in send_invoice() for credit invoices
- Remove auto-paid transition for credit invoices after send
- Credit email template editor in Finance Settings UI
- Expose credit template in get_all_settings() and handle in update endpoint

### Out of Scope / Non-Goals

- Changing credit invoice PDF generation
- Changing how credit invoices are created
- Backfilling existing paid credit invoices to sent status

## Technical Constraints

- Follow existing template pattern (OPTION constant + DEFAULTS entry + getter method)
- Template variables available: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{organisatie_naam}`, `{tuchtzaken_lijst}`
- No `{betaallink}`, `{qr_code}`, `{betaalknop}` in default credit template

## Integration Points

- **FinanceConfig** — New option + default + getter for credit email template
- **REST API** — get_all_settings() exposes template, update endpoint saves it
- **InvoiceEmailSender** — Template selection based on invoice_kind
- **Finance Settings UI** — New template editor section
