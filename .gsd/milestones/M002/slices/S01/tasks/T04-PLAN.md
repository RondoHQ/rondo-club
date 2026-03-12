---
estimated_steps: 5
estimated_files: 3
---

# T04: Deploy, verify with Mollie test payment, version bump

**Slice:** S01 — Webhook payment detail extraction + REST API + Invoice detail UI
**Milestone:** M002

## Description

Bump the version, update the changelog, build, deploy to production, and verify the full integration with a real Mollie test-mode payment. This task proves the entire vertical slice works end-to-end: webhook → meta stored → API returns → UI renders.

## Steps

1. Determine the current version from `style.css` and `package.json`. Bump the patch version (e.g., 33.0.0 → 33.0.1, or minor if features warrant). Update both files.

2. Add a changelog entry in `CHANGELOG.md` under the new version:
   ```
   ### Added
   - Mollie payment details (method, paid-at, dashboard URL, consumer info) extracted and stored when webhook confirms payment
   - "Betaalgegevens" section on invoice detail page showing payment method, timestamp, and Mollie Dashboard link
   - Per-installment payment method and Mollie Dashboard link in installment timeline table
   - Consumer name and IBAN displayed for iDEAL payments
   ```

3. Build and deploy:
   ```bash
   npm run build
   npm run lint
   bin/deploy.sh
   ```

4. On production, verify the deployment:
   - SSH to production and run `wp post meta list <test_invoice_id> | grep _mollie_` to confirm meta is stored (or will be stored after a test payment)
   - Verify REST API returns new fields for a paid invoice
   - Verify invoice detail page renders correctly for existing invoices (no errors, no empty section for non-Mollie invoices)

5. Trigger a Mollie test-mode payment (if the user has a test invoice ready):
   - Verify webhook fires and stores payment details
   - Verify `_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url` are populated
   - Verify "Betaalgegevens" card appears on the invoice detail page
   - Verify duplicate webhook call is a silent no-op

   Commit and push all changes.

## Must-Haves

- [ ] Version bumped in both `style.css` and `package.json`
- [ ] Changelog entry added in Keep a Changelog format
- [ ] `npm run build` and `npm run lint` pass
- [ ] Successfully deployed to production via `bin/deploy.sh`
- [ ] Production invoice detail page loads without errors
- [ ] Existing paid invoices (without Mollie data) show no "Betaalgegevens" section
- [ ] Changes committed and pushed

## Verification

- Production loads without errors (browser check)
- Existing invoices display correctly (no broken sections)
- After test payment: `wp post meta get <id> _mollie_payment_method` returns non-empty value on production
- After test payment: invoice detail page shows "Betaalgegevens" card with method, timestamp, and Mollie link
- Git log shows commit with version bump and changelog

## Observability Impact

- Signals added/changed: None beyond what T01 added
- How a future agent inspects this: `wp post meta list <invoice_id> | grep _mollie_` on production; REST API response inspection
- Failure state exposed: error_log on production if webhook extraction fails

## Inputs

- T01 output: `includes/class-mollie-webhook.php` with extraction methods
- T02 output: `includes/class-rest-invoices.php` with enriched REST response and reset cleanup
- T03 output: `src/pages/Finance/FactuurDetail.jsx` with Betaalgegevens section and enhanced installment table
- Current version from `style.css` and `package.json`

## Expected Output

- `style.css` — version bumped
- `package.json` — version bumped
- `CHANGELOG.md` — new entry for this version
- Production deployment verified working
- Git commit with all changes pushed
