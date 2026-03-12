---
estimated_steps: 7
estimated_files: 5
---

# T03: Add Creditfacturen sub-tab to Finance Settings UI, build, deploy, and verify

**Slice:** S01 — Credit Invoice Email Template & Status Fix
**Milestone:** M003

## Description

Add the 'Creditfacturen' sub-tab to the Finance Settings email section following the exact established pattern used by the 6 existing sub-tabs. Wire formData state, settings loader, and submit payload. Bump version, update changelog, run build + lint, commit, push, deploy, and verify on production.

## Steps

1. In `FinanceSettings.jsx`, add `{ id: 'creditfacturen', label: 'Creditfacturen' }` to the `EMAIL_SUB_TABS` array (after 'factuur_herinneringen').

2. Add `credit_email_template` and `credit_email_heading` to three locations in `FinanceSettings.jsx`:
   - **formData initial state** (useState, around line 240): `credit_email_template: '', credit_email_heading: ''`
   - **useEffect settings loader** (around line 310): `credit_email_template: settings.credit_email_template || '', credit_email_heading: settings.credit_email_heading || ''`
   - **handleSubmit payload** (around line 500): `credit_email_template: formData.credit_email_template, credit_email_heading: formData.credit_email_heading`

3. Add the Creditfacturen sub-tab render block (after the last `{emailSubTab === 'factuur_herinneringen' && (...)}` block). Copy the exact structure from e.g. the 'boetes' sub-tab but:
   - Title: "Template e-mail voor creditfacturen"
   - Description: "Template voor de e-mail waarmee creditfacturen worden verstuurd. Creditfacturen hebben geen betaallink."
   - Heading input bound to `credit_email_heading` with placeholder "Creditfactuur"
   - RichTextEditor bound to `credit_email_template`
   - Variable docs box listing ONLY: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{tuchtzaken_lijst}`, `{organisatie_naam}` — explicitly NO `{betaallink}`, `{qr_code}`, `{betaalknop}`
   - `<TestEmailBlock templateType="credit" />`

4. Run `npm run lint` — fix any issues. Run `npm run build` — verify it compiles.

5. Bump version in `style.css` and `package.json` from 31.9.0 to 31.10.0 (minor: new configurable feature).

6. Update `CHANGELOG.md` with a 31.10.0 entry:
   - **Added**: Credit invoice email template configurable in Finance Settings (E-mail > Creditfacturen)
   - **Changed**: Credit invoices now stay in "Verstuurd" status after sending (no longer auto-marked as paid)

7. Git commit + push. Deploy via `bin/deploy.sh`. Verify on production: Finance Settings > E-mail shows Creditfacturen tab, test email works.

## Must-Haves

- [ ] 'Creditfacturen' sub-tab in EMAIL_SUB_TABS
- [ ] `credit_email_template` and `credit_email_heading` in formData initial state
- [ ] `credit_email_template` and `credit_email_heading` in useEffect settings loader
- [ ] `credit_email_template` and `credit_email_heading` in handleSubmit payload
- [ ] Sub-tab render block with heading input, RichTextEditor, correct variable docs, and TestEmailBlock
- [ ] Variable docs do NOT list `{betaallink}`, `{qr_code}`, or `{betaalknop}`
- [ ] `npm run lint` passes
- [ ] `npm run build` passes
- [ ] Version bumped to 31.10.0
- [ ] CHANGELOG updated
- [ ] Deployed to production

## Verification

- `npm run build` succeeds
- `npm run lint` succeeds
- `grep "creditfacturen" src/pages/Finance/FinanceSettings.jsx` shows sub-tab ID
- `grep "credit_email_template" src/pages/Finance/FinanceSettings.jsx` shows formData + useEffect + handleSubmit + render
- `grep "betaallink" src/pages/Finance/FinanceSettings.jsx` does NOT appear in the creditfacturen sub-tab block
- Production Finance Settings page loads and shows Creditfacturen sub-tab

## Observability Impact

- Signals added/changed: None
- How a future agent inspects this: Visit Finance Settings > E-mail > Creditfacturen on production
- Failure state exposed: None — standard form save error handling applies

## Inputs

- `src/pages/Finance/FinanceSettings.jsx` — existing sub-tab pattern (EMAIL_SUB_TABS, formData, useEffect, handleSubmit, render blocks)
- `includes/class-finance-config.php` — T01 output: credit keys exposed in get_all_settings()
- `includes/class-rest-api.php` — T01 output: credit REST args registered, test email supports 'credit'
- `includes/class-rest-invoices.php` — T02 output: credit template routing, auto-paid removed
- `includes/class-invoice-email-sender.php` — T02 output: credit heading_type support

## Expected Output

- `src/pages/Finance/FinanceSettings.jsx` — Creditfacturen sub-tab with full template editing
- `style.css` — Version 31.10.0
- `package.json` — Version 31.10.0
- `CHANGELOG.md` — 31.10.0 entry
- Production deployment complete and verified
