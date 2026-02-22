---
phase: quick-118
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-invoice-reminder-scheduler.php
  - includes/class-invoice-reminder-sender.php
  - includes/class-finance-config.php
  - includes/class-installment-email-sender.php
  - functions.php
  - src/pages/Finance/FinanceSettings.jsx
autonomous: true
requirements: [REM-01, REM-02, REM-03, REM-04]

must_haves:
  truths:
    - "Invoices in rondo_sent status with no _installment_plan meta receive a reminder email 14 days after sent_date"
    - "Invoices in rondo_sent status with no _installment_plan meta receive a second reminder email 28 days after sent_date with BCC to treasurer"
    - "Each reminder is only sent once per invoice (tracked via post meta timestamps)"
    - "Reminder email templates are configurable in Finance Settings under a new email sub-tab"
    - "Reminder emails include the /betaling/{token} payment link"
  artifacts:
    - path: "includes/class-invoice-reminder-scheduler.php"
      provides: "Daily cron sweeper for no-plan invoice reminders"
      contains: "class InvoiceReminderScheduler"
    - path: "includes/class-invoice-reminder-sender.php"
      provides: "Email composition and sending for invoice reminders"
      contains: "class InvoiceReminderSender"
    - path: "includes/class-finance-config.php"
      provides: "New option constants and getters for invoice reminder templates"
      contains: "OPTION_INVOICE_REMINDER_1_EMAIL_TEMPLATE"
  key_links:
    - from: "includes/class-invoice-reminder-scheduler.php"
      to: "includes/class-invoice-reminder-sender.php"
      via: "InvoiceReminderSender::send_reminder_1() and send_reminder_2()"
      pattern: "InvoiceReminderSender::send_reminder"
    - from: "includes/class-invoice-reminder-sender.php"
      to: "includes/class-finance-config.php"
      via: "get_invoice_reminder_1_email_template() and get_invoice_reminder_2_email_template()"
      pattern: "get_invoice_reminder_[12]_email_template"
    - from: "functions.php"
      to: "includes/class-invoice-reminder-scheduler.php"
      via: "new InvoiceReminderScheduler() in rondo_init()"
      pattern: "new InvoiceReminderScheduler"
---

<objective>
Add reminder emails for membership (contributie) invoices where the member has not yet selected a payment plan.

Purpose: Members who receive a contributie invoice but don't visit the payment page to choose a plan (full, 3 installments, or 8 installments) need to be reminded. Without reminders, these invoices go stale.

Output: A daily cron sweeper that sends reminder emails at 14 and 28 days after the invoice sent date, configurable email templates in the finance settings UI.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-installment-scheduler.php (pattern to follow for cron sweeper)
@includes/class-installment-email-sender.php (pattern to follow for email sending, shares resolve_and_send helper logic)
@includes/class-finance-config.php (add new option constants, getters, defaults, and update_settings handling)
@includes/class-invoice-email-sender.php (uses payment_link ACF field for betaallink — same pattern for reminders)
@functions.php (register new class in rondo_init)
@src/pages/Finance/FinanceSettings.jsx (add new email sub-tab for invoice reminders)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create InvoiceReminderScheduler and InvoiceReminderSender backend classes</name>
  <files>
    includes/class-invoice-reminder-scheduler.php
    includes/class-invoice-reminder-sender.php
    includes/class-finance-config.php
    functions.php
  </files>
  <action>
**1. Create `includes/class-invoice-reminder-scheduler.php`** (namespace `Rondo\Finance`):

Follow the exact same pattern as `class-installment-scheduler.php` (cron hook, transient lock, process loop). Key differences:

- Cron hook: `rondo_invoice_reminder_sweeper` (separate from installment sweeper)
- Lock transient: `rondo_invoice_reminder_sweeper_lock` (5-minute TTL)
- Query: `rondo_invoice` posts with `post_status = 'rondo_sent'`, and importantly with TWO meta conditions:
  - `_installment_plan` does NOT EXIST (use `'compare' => 'NOT EXISTS'`) — this means the member has not yet visited the payment page
  - `invoice_type` = `membership` (ACF field stored as post meta — only membership invoices get these reminders, not discipline invoices)
- For each matching invoice:
  - Read `sent_date` ACF field (stored as `Ymd` string, e.g., `20260215`). Parse it to get the sent timestamp.
  - Calculate days since sent: `(today_ts - sent_ts) / DAY_IN_SECONDS`
  - If >= 28 days AND no `_invoice_reminder_2_sent_at` meta: call `InvoiceReminderSender::send_reminder_2($invoice_id)`, log result
  - Else if >= 14 days AND no `_invoice_reminder_1_sent_at` meta: call `InvoiceReminderSender::send_reminder_1($invoice_id)`, log result
  - Check reminder 2 FIRST (same pattern as installment scheduler — 28 days also satisfies >= 14 days)
- Each call wrapped in try/catch with error_log (same format as InstallmentScheduler)
- Constructor registers cron hook, schedule on `after_switch_theme`, unschedule on `switch_theme`

**2. Create `includes/class-invoice-reminder-sender.php`** (namespace `Rondo\Finance`):

Follow InstallmentEmailSender pattern but simpler (no Mollie payment link creation needed — the betaallink already exists on the invoice).

Two public static methods:
- `send_reminder_1(int $invoice_id): true|\WP_Error` — sends first reminder
- `send_reminder_2(int $invoice_id): true|\WP_Error` — sends second reminder with BCC

Shared private helper `resolve_and_send(int $invoice_id, string $template, string $subject_prefix, bool $add_bcc)`:
- Resolve person from invoice ACF `person` field (same pattern as InstallmentEmailSender)
- Get person name (first_name, infix, last_name) and email (from contact_info repeater)
- Return WP_Error if no person or no email
- Read `payment_link` ACF field from invoice — this is the `/betaling/{token}` URL (already set when invoice was created)
- Format `{betaallink}` as styled HTML anchor: `<a href="{url}" style="color:#0891b2;text-decoration:underline;">Betaal nu</a>`
- Read `total_amount` ACF field, format as Dutch currency `&euro; X,XX`
- Read `invoice_number` ACF field
- Read `sent_date` ACF field, parse and format as Dutch long date (e.g., "15 februari 2026") using the same `$dutch_months` array as InstallmentEmailSender
- Calculate `dagen_sinds_factuur`: days since sent_date
- Template placeholders to replace: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{betaallink}`, `{factuurdatum}`, `{dagen_sinds_factuur}`, `{organisatie_naam}`
- Write idempotency timestamp BEFORE wp_mail: `_invoice_reminder_1_sent_at` or `_invoice_reminder_2_sent_at` (current_time('mysql'))
- Build headers: Content-Type text/html, From org name + contact email
- For reminder 2: add BCC to treasurer (from `$config->get_bcc_email()`)
- Subject format: `"Herinnering - Factuur {number} - {org_name}"` for reminder 1, `"Tweede herinnering - Factuur {number} - {org_name}"` for reminder 2

In `send_reminder_1()`: get template from `$config->get_invoice_reminder_1_email_template()`, write `_invoice_reminder_1_sent_at` before wp_mail, call `resolve_and_send()` with `$add_bcc = false`.

In `send_reminder_2()`: get template from `$config->get_invoice_reminder_2_email_template()`, write `_invoice_reminder_2_sent_at` before wp_mail, call `resolve_and_send()` with `$add_bcc = true`.

**3. Update `includes/class-finance-config.php`**:

Add two new option constants:
```php
const OPTION_INVOICE_REMINDER_1_EMAIL_TEMPLATE = 'rondo_finance_invoice_reminder_1_email_template';
const OPTION_INVOICE_REMINDER_2_EMAIL_TEMPLATE = 'rondo_finance_invoice_reminder_2_email_template';
```

Add defaults in the DEFAULTS array:
```php
'invoice_reminder_1_email_template' => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Op {factuurdatum} hebben wij u een factuur ({factuur_nummer}) gestuurd voor uw contributie ter hoogte van <strong>{totaal_bedrag}</strong>.</p><p>Wij hebben nog geen betaling ontvangen. Via onderstaande link kunt u uw betaalwijze kiezen en direct betalen:</p><p>{betaallink}</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
'invoice_reminder_2_email_template' => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Dit is onze tweede en laatste herinnering voor factuur {factuur_nummer} voor uw contributie ter hoogte van <strong>{totaal_bedrag}</strong>, verstuurd op {factuurdatum}.</p><p>Het is nu {dagen_sinds_factuur} dagen geleden dat deze factuur is verstuurd en wij hebben nog geen betaling ontvangen.</p><p>Wij verzoeken u dringend zo spoedig mogelijk te betalen via:<br/>{betaallink}</p><p>Indien u niet reageert, zullen wij contact met u opnemen.</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
```

Add getter methods (following existing pattern):
- `get_invoice_reminder_1_email_template(): string`
- `get_invoice_reminder_2_email_template(): string`

Update `get_all_settings()` to include the two new template fields.

Update `get_setting()` switch to handle the two new keys.

Update `update_settings()` to handle the two new template fields using `wp_kses_post()` sanitization (same as other email templates).

**4. Update `functions.php`**:

Add use statement: `use Rondo\Finance\InvoiceReminderScheduler;` (add after the existing `use Rondo\Finance\InstallmentScheduler;` line).

Add instantiation in `rondo_init()`: `new InvoiceReminderScheduler();` right after the `new InstallmentScheduler();` line, with a comment: `// Invoice reminder scheduler — daily cron sweeper for no-plan membership invoice reminders`.
  </action>
  <verify>
    Run `npm run build` to verify no frontend regressions. Check PHP syntax: `php -l includes/class-invoice-reminder-scheduler.php && php -l includes/class-invoice-reminder-sender.php && php -l includes/class-finance-config.php`.
  </verify>
  <done>
    Two new PHP classes exist following the established cron sweeper + email sender pattern. FinanceConfig exposes two new configurable email templates with sensible Dutch defaults. InvoiceReminderScheduler queries membership invoices in rondo_sent status with no _installment_plan meta and triggers reminders at 14 and 28 days. Idempotency is ensured via timestamp post meta written before wp_mail.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add invoice reminder email templates to Finance Settings UI</name>
  <files>
    src/pages/Finance/FinanceSettings.jsx
  </files>
  <action>
Update `src/pages/Finance/FinanceSettings.jsx` to expose the two new email templates:

**1. Add a new email sub-tab** for invoice reminders. Update the `EMAIL_SUB_TABS` array — add a new entry AFTER 'herinneringen':
```js
{ id: 'factuur_herinneringen', label: 'Factuurherinneringen' },
```

**2. Add the two new template fields to `formData` initial state:**
```js
invoice_reminder_1_email_template: '',
invoice_reminder_2_email_template: '',
```

**3. Add to the `useEffect` that loads settings:**
```js
invoice_reminder_1_email_template: settings.invoice_reminder_1_email_template || '',
invoice_reminder_2_email_template: settings.invoice_reminder_2_email_template || '',
```

**4. Add to `handleSubmit` payload:**
```js
invoice_reminder_1_email_template: formData.invoice_reminder_1_email_template,
invoice_reminder_2_email_template: formData.invoice_reminder_2_email_template,
```

**5. Add the new sub-tab content section** (inside the `activeTab === 'email'` block, after the 'herinneringen' section). Follow the same pattern as the existing 'herinneringen' sub-tab:

```jsx
{emailSubTab === 'factuur_herinneringen' && (
  <div className="card p-6">
    <div className="mb-6">
      <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Herinneringen voor facturen zonder betaalplan</h2>
      <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
        Deze herinneringen worden automatisch verstuurd aan leden die hun contributiefactuur hebben ontvangen maar nog geen betaalwijze hebben gekozen.
      </p>
    </div>

    {/* Reminder 1 — 14 days */}
    <div className="space-y-4 mb-8">
      <div>
        <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Eerste herinnering</h3>
        <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
          Wordt verstuurd 2 weken na de factuurdatum.
        </p>
        <RichTextEditor
          value={formData.invoice_reminder_1_email_template}
          onChange={(html) => setFormData(prev => ({ ...prev, invoice_reminder_1_email_template: html }))}
          placeholder="Schrijf hier het template voor de eerste factuurherinnering..."
          minHeight="200px"
        />
      </div>
    </div>

    {/* Reminder 2 — 28 days */}
    <div className="space-y-4 mb-6 pt-6 border-t border-gray-200 dark:border-gray-700">
      <div>
        <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Tweede herinnering</h3>
        <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
          Wordt verstuurd 4 weken na de factuurdatum. De penningmeester ontvangt een BCC.
        </p>
        <RichTextEditor
          value={formData.invoice_reminder_2_email_template}
          onChange={(html) => setFormData(prev => ({ ...prev, invoice_reminder_2_email_template: html }))}
          placeholder="Schrijf hier het template voor de tweede factuurherinnering..."
          minHeight="200px"
        />
      </div>
    </div>

    {/* Available variables */}
    <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
      <p className="font-semibold mb-2">Beschikbare variabelen:</p>
      <div className="space-y-1 font-mono">
        <div><code>{'{naam}'}</code> - Volledige naam van het lid</div>
        <div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>
        <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
        <div><code>{'{totaal_bedrag}'}</code> - Totaalbedrag</div>
        <div><code>{'{betaallink}'}</code> - Link naar betaalpagina</div>
        <div><code>{'{factuurdatum}'}</code> - Datum van de originele factuur</div>
        <div><code>{'{dagen_sinds_factuur}'}</code> - Aantal dagen sinds factuurdatum</div>
        <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
      </div>
    </div>
    <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
      Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
    </p>
  </div>
)}
```

**6. Rename the existing 'herinneringen' sub-tab** to be clearer — update its label from `'Herinneringen'` to `'Termijnherinneringen'` so it's distinct from the new "Factuurherinneringen" tab.
  </action>
  <verify>
    Run `npm run build` to verify the frontend compiles without errors. Run `npm run lint` to verify no ESLint warnings.
  </verify>
  <done>
    Finance Settings E-mail tab shows 5 sub-tabs: Boetes, Contributie, Termijnen, Termijnherinneringen, Factuurherinneringen. The new Factuurherinneringen sub-tab has two RichTextEditor fields for the first (14-day) and second (28-day) reminder templates, with the correct available variables listed. Templates are saved and loaded correctly via the existing settings save flow.
  </done>
</task>

<task type="auto">
  <name>Task 3: Version bump, changelog, deploy, and verify</name>
  <files>
    style.css
    package.json
    CHANGELOG.md
  </files>
  <action>
1. Bump patch version from current to next patch (e.g., 30.0.0 -> 30.1.0 — this is a new feature, so MINOR bump) in both `style.css` and `package.json`.
2. Add changelog entry under `## [30.1.0]` with date 2026-02-22:
   - **Added**: Automatic reminder emails for membership invoices where member hasn't selected a payment plan (first reminder at 2 weeks, second at 4 weeks with BCC to treasurer)
   - **Added**: Configurable email templates for invoice reminders in Finance Settings
3. Run `npm run build` to create production assets.
4. Commit all changes, push to main.
5. Run `git pull` then `bin/deploy.sh` to deploy to production.
6. After deploy, SSH to production and manually schedule the cron event so it doesn't wait for theme reactivation:
   ```bash
   source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "cd $DEPLOY_REMOTE_WP_PATH && wp cron event schedule rondo_invoice_reminder_sweeper $(date -d 'tomorrow' +%s) daily 2>/dev/null || wp cron event schedule rondo_invoice_reminder_sweeper \$(date -v+1d +%s) daily"
   ```
   (The cron hook auto-schedules on theme activation via `after_switch_theme`, but since we're deploying without reactivating the theme, we need to manually register it once.)
7. Verify the cron event is scheduled: `wp cron event list | grep invoice_reminder`
  </action>
  <verify>
    `npm run build` succeeds, `npm run lint` passes, `git log --oneline -1` shows the commit, production deploy completes, cron event is scheduled on production.
  </verify>
  <done>
    Version bumped to 30.1.0, changelog updated, code deployed to production, cron event registered. The invoice reminder system is live and will begin checking daily for membership invoices needing reminders.
  </done>
</task>

</tasks>

<verification>
1. Backend: Two new PHP classes exist and follow established patterns (cron sweeper + email sender)
2. FinanceConfig: Two new option constants, defaults, getters, and update handlers
3. Frontend: New "Factuurherinneringen" sub-tab in Finance Settings with two configurable templates
4. Cron: `rondo_invoice_reminder_sweeper` event scheduled daily on production
5. Idempotency: `_invoice_reminder_1_sent_at` and `_invoice_reminder_2_sent_at` meta prevent duplicate sends
6. Query correctness: Only `rondo_sent` + `invoice_type=membership` + `_installment_plan NOT EXISTS` invoices are processed
</verification>

<success_criteria>
- Membership invoices without a payment plan selected receive automated reminders at 14 and 28 days
- Second reminder includes BCC to the configured treasurer email
- Email templates are editable via Finance Settings > E-mail > Factuurherinneringen
- No duplicate emails are ever sent (timestamp-based idempotency)
- Existing installment reminders are unaffected
- Build and lint pass cleanly
</success_criteria>

<output>
After completion, create `.planning/quick/118-add-reminder-emails-for-contributie-invo/118-SUMMARY.md`
</output>
