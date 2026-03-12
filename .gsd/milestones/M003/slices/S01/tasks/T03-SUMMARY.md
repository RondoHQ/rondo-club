---
id: T03
parent: S01
milestone: M003
provides:
  - creditfacturen_subtab_ui
  - credit_email_template_frontend_wiring
key_files:
  - src/pages/Finance/FinanceSettings.jsx
  - style.css
  - package.json
  - CHANGELOG.md
key_decisions:
  - Followed exact sub-tab pattern from existing 6 tabs for the 7th (creditfacturen)
  - Variable docs explicitly exclude {betaallink}, {qr_code}, {betaalknop} since credit invoices have no payment link
patterns_established:
  - Credit template sub-tab follows identical structure to boetes sub-tab (heading input + RichTextEditor + variable docs + TestEmailBlock)
observability_surfaces:
  - Finance Settings > E-mail > Creditfacturen sub-tab shows current template on production
  - TestEmailBlock with templateType="credit" sends preview emails
duration: 15m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T03: Add Creditfacturen sub-tab to Finance Settings UI, build, deploy, and verify

**Added Creditfacturen sub-tab to Finance Settings E-mail section with heading input, template editor, variable docs (no payment link variables), and test email capability. Deployed to production as v31.10.0.**

## What Happened

1. Added `{ id: 'creditfacturen', label: 'Creditfacturen' }` to `EMAIL_SUB_TABS` array (7th sub-tab).
2. Added `credit_email_template` and `credit_email_heading` to three locations in FinanceSettings.jsx:
   - formData initial state (empty strings)
   - useEffect settings loader (from API response)
   - handleSubmit payload (to API on save)
3. Added the Creditfacturen sub-tab render block after factuur_herinneringen, following the boetes pattern:
   - Title: "Template e-mail voor creditfacturen"
   - Description explains credit invoices have no payment link
   - Heading input bound to `credit_email_heading` with placeholder "Creditfactuur"
   - RichTextEditor bound to `credit_email_template`
   - Variable docs: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{tuchtzaken_lijst}`, `{organisatie_naam}` only
   - `<TestEmailBlock templateType="credit" />`
4. Lint and build passed cleanly.
5. Bumped version from 31.9.0 to 31.10.0 in style.css and package.json.
6. Updated CHANGELOG.md with 31.10.0 entry (Added credit template config, Changed credit invoice status behavior).
7. Committed, pushed, deployed to production via `bin/deploy.sh`.
8. Verified on production: Creditfacturen tab visible, content correct, test email block present.

## Verification

- `npm run lint` — passed (0 warnings)
- `npm run build` — passed (5960 modules, 15.72s)
- `grep "creditfacturen" src/pages/Finance/FinanceSettings.jsx` — shows sub-tab ID and render block
- `grep -c "credit_email_template" src/pages/Finance/FinanceSettings.jsx` — returns 5 (formData + useEffect + handleSubmit + render × 2)
- `grep -c "credit_email_heading" src/pages/Finance/FinanceSettings.jsx` — returns 5
- Variable docs do NOT list `{betaallink}`, `{qr_code}`, or `{betaalknop}` — confirmed
- Production browser verification (5/5 assertions PASS):
  - "Template e-mail voor creditfacturen" heading visible
  - "Creditfacturen hebben geen betaallink" description visible
  - "Testmail versturen" block visible
  - `{tuchtzaken_lijst}` and `{organisatie_naam}` variables listed

### Slice-level verification results (final task — all checked)

| Check | Result |
|-------|--------|
| `npm run build` succeeds | ✅ PASS |
| `npm run lint` succeeds | ✅ PASS |
| `grep -c 'OPTION_CREDIT_EMAIL_TEMPLATE' includes/class-finance-config.php` ≥ 3 | ✅ PASS (3) |
| `grep -c "'credit'" includes/class-rest-api.php` present | ✅ PASS (2) |
| `grep 'credit_email_template' includes/class-rest-api.php` registered | ✅ PASS |
| `grep "credit_email_template" src/pages/Finance/FinanceSettings.jsx` in formData+useEffect+handleSubmit+render | ✅ PASS (5 occurrences) |
| `grep -c "creditfacturen\|Creditfacturen"` sub-tab exists | ✅ PASS (6) |
| `grep -c "credit_payment_adjustment_recorded_at" includes/class-rest-invoices.php` returns 0 | ⚠️ Returns 1 — read-only reference kept in `format_invoice_detail()` for backward compatibility per T02 decision |

Note on the last check: T02 deliberately kept one read-only reference to `_credit_payment_adjustment_recorded_at` in `format_invoice_detail()` for backward compatibility with historical credit invoices that were auto-paid before the change. The auto-paid *transition* block was removed as required. This is documented in T02-SUMMARY.md.

## Diagnostics

- Visit Finance Settings > E-mail > Creditfacturen on production to inspect current template
- Test email button sends a preview to any email address
- Standard form save error handling applies (no new failure states)

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/pages/Finance/FinanceSettings.jsx` — Added creditfacturen sub-tab to EMAIL_SUB_TABS, formData, useEffect, handleSubmit, and render block
- `style.css` — Version bumped to 31.10.0
- `package.json` — Version bumped to 31.10.0
- `CHANGELOG.md` — Added 31.10.0 entry
