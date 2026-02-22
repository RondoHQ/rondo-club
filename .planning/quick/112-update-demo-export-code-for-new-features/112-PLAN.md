---
phase: 112-update-demo-export
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-demo-export.php
  - includes/class-demo-import.php
autonomous: false
must_haves:
  truths:
    - "Invoices (both discipline and membership) are exported with anonymized financial data"
    - "Invoice installment meta is exported and imported with date shifting"
    - "Finance settings are exported and imported for demo site"
    - "Capability maps (functie + commissie) are exported and imported"
    - "Demo site clean() removes invoices and finance options before fresh import"
    - "Demo site shows realistic invoices on Facturen and Contributie pages"
  artifacts:
    - path: "includes/class-demo-export.php"
      provides: "Invoice export + finance settings export + capability map export"
    - path: "includes/class-demo-import.php"
      provides: "Invoice import + finance settings import + capability map import + invoice cleanup"
  key_links:
    - from: "includes/class-demo-export.php"
      to: "rondo_invoice post type"
      via: "export_invoices() method"
      pattern: "export_invoices"
    - from: "includes/class-demo-import.php"
      to: "rondo_invoice post type"
      via: "import_invoices() method"
      pattern: "import_invoices"
---

<objective>
Update the demo export/import system to support all features added since the demo system was created: invoices (both discipline case invoices and membership/contributie invoices with installment plans), finance configuration settings, and capability maps. Then run a fresh export on production and import on the demo site.

Purpose: The demo site at demo.rondo.club needs to showcase all current features including the Facturen page, Contributie invoicing, and finance settings.
Output: Updated export/import code, fresh demo fixture, working demo site with all features populated.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-demo-export.php
@includes/class-demo-import.php
@includes/class-demo-anonymizer.php
@includes/class-finance-config.php
@acf-json/group_invoice_fields.json
@includes/class-post-types.php
@includes/class-bulk-invoice-creator.php
@includes/class-rest-invoices.php
@bin/deploy-demo.sh
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add invoice export/import and new settings to demo system</name>
  <files>includes/class-demo-export.php, includes/class-demo-import.php</files>
  <action>
**In class-demo-export.php:**

1. Add `rondo_invoice` to `$ref_maps` array (key: `'invoice'`).

2. In `build_ref_maps()`, add: `$this->build_ref_map_for_type( 'rondo_invoice', ['rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue'], 'invoice' );`

3. Add `export_invoices()` method that queries all `rondo_invoice` posts (statuses: `rondo_draft`, `rondo_sent`, `rondo_paid`, `rondo_overdue`) and exports each with:
   - `_ref` (from invoice ref map)
   - `title` (invoice number, e.g. "T-2026-0001")
   - `status` (post_status)
   - ACF fields: `invoice_number`, `invoice_type` (discipline/membership), `person` (resolved to person ref), `status` (ACF status field), `total_amount`, `sent_date`, `due_date`
   - `line_items`: For each line item, export `discipline_case` as discipline_case ref (resolve via ref map), `description`, `amount`. For membership invoices, line_items may reference discipline_cases that don't exist — skip null refs.
   - `payment_link`: Set to null (demo shouldn't have real payment links)
   - `pdf_path`: Set to null (no real PDFs in demo)
   - `qr_code_path`: Set to null
   - Post meta (non-ACF): `_installment_plan`, `_installment_count`, `_invoice_season`. For installment data, loop from 1 to `_installment_count` and export `_installment_N_amount`, `_installment_N_admin_fee`, `_installment_N_status`, `_installment_N_due_date`. Strip `_installment_N_sent_at`, `_installment_N_paid_at`, `_installment_N_mollie_payment_id`, `_installment_N_payment_link` (Mollie-specific, not for demo). Strip `_payment_token`, `_mollie_payment_link_id`, and any `_mollie_pid_*` keys.
   - `seizoen`: Get the `seizoen` taxonomy term slug (same pattern as discipline_cases export)
   - Anonymize: randomize `total_amount` to a realistic range (50-300 for membership, 10-100 for discipline), randomize installment amounts proportionally, set `invoice_number` to a demo sequence like `DEMO-{year}-{seq}`.

4. Call `export_invoices()` in the `export()` method between discipline_cases and todos. Add `'invoices' => count($invoices)` to `record_counts` in meta.

5. Add invoices to the fixture array: `'invoices' => $invoices`.

6. In `export_settings()`, add export of finance configuration options (non-sensitive ones only):
   - `rondo_finance_org_name` -> anonymize to "Demo Club"
   - `rondo_finance_org_address` -> anonymize to "Sportlaan 1, 1234 AB Amsterdam"
   - `rondo_finance_contact_email` -> "penningmeester@rondo-demo.nl"
   - `rondo_finance_iban` -> "NL00DEMO0000000000" (fake IBAN)
   - `rondo_finance_payment_term_days` -> export as-is (number)
   - `rondo_finance_payment_clause` -> export as-is (text)
   - `rondo_finance_membership_payment_clause` -> export as-is
   - `rondo_finance_email_template` -> replace with `'<p>Dit is een demo e-mailtemplate voor facturen.</p>'`
   - `rondo_finance_membership_email_template` -> replace with `'<p>Dit is een demo e-mailtemplate voor contributie.</p>'`
   - `rondo_finance_installment_email_template` -> replace with `'<p>Dit is een demo e-mailtemplate voor termijnen.</p>'`
   - `rondo_finance_reminder_1_email_template` -> replace with `'<p>Dit is een demo herinnering.</p>'`
   - `rondo_finance_reminder_2_email_template` -> replace with `'<p>Dit is een demo tweede herinnering.</p>'`
   - `rondo_finance_accent_color` -> export as-is
   - `rondo_finance_admin_fee` -> export as-is (number)
   - `rondo_finance_installment_admin_fee` -> export as-is (number)
   - `rondo_finance_active_payment_provider` -> set to "mollie" (demo default)
   - DO NOT export: `rondo_finance_rabobank_credentials`, `rondo_finance_mollie_api_key`, `rondo_finance_mollie_redirect_url`, `rondo_finance_club_logo_id`, `rondo_finance_bcc_email` (sensitive/site-specific)

7. In `export_settings()`, also add export of capability maps:
   - `rondo_functie_capability_map` -> export as-is (JSON array stored as option)
   - `rondo_commissie_capability_map` -> export as-is (JSON array stored as option)

8. In `export_person_post_meta()`, also capture the `_exclude_from_contributie` meta field.

**In class-demo-import.php:**

1. In `clean()` method:
   - Add `'rondo_invoice'` to the `$post_types` array for deletion
   - Add dynamic option cleanup: `"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'rondo_finance_%'"` and delete those too
   - Add `'rondo_functie_capability_map'` and `'rondo_commissie_capability_map'` to the static `$option_keys` array for deletion

2. Add to `read_fixture()`: Add `'invoices'` to `$required_keys` array. Add invoices count to the record counts log line.

3. Add `import_invoices()` method:
   - Called in `import()` after `import_discipline_cases()` and before `import_todos()`
   - For each invoice in fixture:
     - `wp_insert_post` with `post_type => 'rondo_invoice'`, `post_title` from title, `post_status` from status
     - Store ref mapping
     - Set ACF fields: `invoice_number`, `invoice_type`, `total_amount`, `sent_date` (shift date with Ymd format), `due_date` (shift date with Ymd format), `status`
     - Resolve `person` ref to WordPress ID and set via `update_field('person', ...)`
     - Resolve `line_items`: for each item, resolve `discipline_case` ref to WP ID, set description and amount. Write via `update_field('line_items', ...)`
     - Set post meta: `_installment_plan`, `_installment_count`, `_invoice_season` (shift season slug)
     - For installment data: loop and set `_installment_N_amount`, `_installment_N_admin_fee`, `_installment_N_status`, `_installment_N_due_date` (shift date)
     - Set seizoen taxonomy term (shift season slug, same as discipline case import)

4. In `import_settings()`: The existing generic loop already handles options. The finance options and capability maps will be imported automatically since they're just option key-value pairs. But add handling for season shifting in `rondo_finance_*` keys if they contain season slugs (they don't — fee configs use `rondo_membership_fees_{season}` which is already handled).

5. In `import_people()` Pass 1: Add `_exclude_from_contributie` to the post_meta import handling (it will come through the generic meta loop, just ensure boolean values are handled).

6. Update the `import()` method to call `import_invoices()` in the correct order (after discipline_cases, before todos).
  </action>
  <verify>
Run `npm run lint` to check for any frontend issues (shouldn't be affected). Then verify the PHP files parse correctly:
```bash
php -l includes/class-demo-export.php
php -l includes/class-demo-import.php
```
  </verify>
  <done>
- DemoExport has `export_invoices()` method that exports all rondo_invoice posts with anonymized data
- DemoExport `export_settings()` includes finance config options (anonymized) and capability maps
- DemoImport has `import_invoices()` method called in correct order
- DemoImport `clean()` removes rondo_invoice posts and finance options
- PHP files parse without syntax errors
  </done>
</task>

<task type="auto">
  <name>Task 2: Deploy, run export on production, import on demo site</name>
  <files></files>
  <action>
1. `git pull` in rondo-club directory (safety: pull any agent changes).

2. Commit the updated export/import code: `git add -A && git commit -m "feat: add invoice and finance settings to demo export/import" && git push`

3. Deploy to production: `bin/deploy.sh` (so the updated export code is available on production).

4. Run the export on production via SSH:
   ```bash
   source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "cd $DEPLOY_REMOTE_WP_PATH && wp rondo demo export"
   ```
   This writes to `fixtures/demo-fixture.json` on the production server.

5. Copy the fixture file from production to the demo site:
   ```bash
   source .env && ssh -p 18765 "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "cat $DEPLOY_REMOTE_WP_PATH/wp-content/themes/rondo-club/fixtures/demo-fixture.json" | ssh -p 18765 "u26-b0fnaayuzqqg@$DEPLOY_SSH_HOST" "cat > ~/www/demo.rondo.club/public_html/wp-content/themes/rondo-club/fixtures/demo-fixture.json"
   ```
   Alternative if piping doesn't work: SCP from production to local, then SCP from local to demo.

6. Deploy the theme to demo site (so it has the updated import code): `bin/deploy-demo.sh`

7. Run the import on demo site via SSH (with --clean to start fresh):
   ```bash
   ssh -p 18765 "u26-b0fnaayuzqqg@c1130624.sgvps.net" "cd ~/www/demo.rondo.club/public_html && wp rondo demo import --clean"
   ```

8. Verify the demo site has data by checking a few API endpoints:
   ```bash
   # Check invoices exist
   ssh -p 18765 "u26-b0fnaayuzqqg@c1130624.sgvps.net" "cd ~/www/demo.rondo.club/public_html && wp post list --post_type=rondo_invoice --post_status=any --format=count"
   # Check people exist
   ssh -p 18765 "u26-b0fnaayuzqqg@c1130624.sgvps.net" "cd ~/www/demo.rondo.club/public_html && wp post list --post_type=person --post_status=any --format=count"
   # Check settings
   ssh -p 18765 "u26-b0fnaayuzqqg@c1130624.sgvps.net" "cd ~/www/demo.rondo.club/public_html && wp option get rondo_finance_org_name"
   ```
  </action>
  <verify>
SSH into demo site and verify:
- `wp post list --post_type=rondo_invoice --post_status=any --format=count` returns > 0
- `wp option get rondo_finance_org_name` returns "Demo Club"
- `wp option get rondo_functie_capability_map` returns the capability map
  </verify>
  <done>
- Fresh export created from production with invoices included
- Demo site has clean import with all data types including invoices
- Demo site finance settings populated with demo values
- Capability maps imported on demo site
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 3: Verify demo site has all features working</name>
  <what-built>Updated demo export/import system with invoice support, ran fresh export from production, imported on demo.rondo.club with all new features: Facturen page, Contributie invoices, finance settings, capability maps.</what-built>
  <how-to-verify>
    1. Visit https://demo.rondo.club and log in with demo credentials
    2. Navigate to Facturen page - verify invoices are listed (both discipline and membership types)
    3. Click into a discipline case invoice - verify it shows line items with linked tuchtzaken
    4. Click into a membership/contributie invoice - verify it shows amount and installment info
    5. Navigate to Contributie page - verify it shows member fee data
    6. Navigate to Tuchtzaken page - verify discipline cases are listed
    7. Navigate to Instellingen > Financien - verify finance settings are populated
    8. Spot-check a person profile - verify data looks realistic (anonymized names, addresses)
  </how-to-verify>
  <resume-signal>Type "approved" or describe any issues found</resume-signal>
</task>

</tasks>

<verification>
- Demo export includes invoices in fixture JSON
- Demo import creates invoice posts with correct ACF fields and post meta
- Demo site clean() properly removes invoices and finance options
- Finance settings are anonymized (no real emails, IBANs, or credentials)
- Installment data is properly date-shifted
- All existing data types still export/import correctly (no regressions)
</verification>

<success_criteria>
- demo.rondo.club shows Facturen page with both discipline and membership invoices
- demo.rondo.club Contributie page shows member fee data
- demo.rondo.club finance settings are populated with demo values
- No real PII or credentials leaked into demo data
- All previously-working demo features still function
</success_criteria>

<output>
After completion, create `.planning/quick/112-update-demo-export-code-for-new-features/112-SUMMARY.md`
</output>
