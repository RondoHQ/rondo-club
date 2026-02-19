---
phase: quick-95
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-finance-config.php
  - includes/class-invoice-email-sender.php
  - includes/class-rest-invoices.php
  - includes/class-rest-api.php
  - src/pages/Finance/FinanceSettings.jsx
autonomous: true
must_haves:
  truths:
    - "Membership invoices use a contributie-specific email template without discipline references"
    - "Discipline invoices continue to use the existing discipline email template"
    - "Both templates are independently editable in Finance Settings"
    - "Existing stored discipline template data is preserved (no option key rename)"
  artifacts:
    - path: "includes/class-finance-config.php"
      provides: "New OPTION_MEMBERSHIP_EMAIL_TEMPLATE constant, getter, default, update_settings handler"
      contains: "OPTION_MEMBERSHIP_EMAIL_TEMPLATE"
    - path: "includes/class-invoice-email-sender.php"
      provides: "Template parameter support in send()"
      contains: "template"
    - path: "src/pages/Finance/FinanceSettings.jsx"
      provides: "Separate membership template editor card"
      contains: "membership_email_template"
  key_links:
    - from: "includes/class-rest-invoices.php"
      to: "includes/class-invoice-email-sender.php"
      via: "Passes correct template based on invoice_type"
      pattern: "membership_email_template|get_membership_email_template"
---

<objective>
Add a separate email template for membership (contributie) invoices so they no longer use the discipline-case template containing tuchtcommissie references.

Purpose: Membership invoices currently get sent with text about "opgelegde boetes vanuit de tuchtcommissie" which is confusing for members paying their contribution.
Output: Two independent email templates (discipline + membership) editable in settings, with automatic selection based on invoice_type.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-finance-config.php
@includes/class-invoice-email-sender.php
@includes/class-rest-invoices.php
@includes/class-rest-api.php
@src/pages/Finance/FinanceSettings.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add membership email template to backend (FinanceConfig + InvoiceEmailSender + REST)</name>
  <files>
    includes/class-finance-config.php
    includes/class-invoice-email-sender.php
    includes/class-rest-invoices.php
    includes/class-rest-api.php
  </files>
  <action>
**class-finance-config.php:**
1. Add new constant: `const OPTION_MEMBERSHIP_EMAIL_TEMPLATE = 'rondo_finance_membership_email_template';`
2. Add default in DEFAULTS array with key `membership_email_template`:
```html
<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Bijgevoegd vindt u de factuur {factuur_nummer} voor uw contributie.</p><p>Het totaalbedrag is <strong>{totaal_bedrag}</strong>.</p><p>U kunt betalen via de volgende link: {betaallink}</p>{qr_code}<p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>
```
3. Add getter method `get_membership_email_template()` following the pattern of `get_email_template()` but using `OPTION_MEMBERSHIP_EMAIL_TEMPLATE`.
4. In `get_all_settings()`: add `'membership_email_template' => $this->get_membership_email_template()` to the returned array (keep existing `email_template` key unchanged for backward compatibility).
5. In `get_setting()`: add case `'membership_email_template'` returning `$this->get_membership_email_template()`.
6. In `update_settings()`: add block for `membership_email_template` using `wp_kses_post()` sanitization, same pattern as `installment_email_template`.

IMPORTANT: Do NOT rename the existing `email_template` key or `OPTION_EMAIL_TEMPLATE` constant. The WordPress option `rondo_finance_email_template` stores user-customized discipline template data that must be preserved.

**class-invoice-email-sender.php:**
1. Add optional `template` key to the `$options` parameter in `send()`. Document it in the docblock: `- template (string) Custom email template HTML. Defaults to discipline template from FinanceConfig.`
2. Change line 96 from `$template = $config->get_email_template();` to:
```php
$template = $options['template'] ?? $config->get_email_template();
```
This way callers can pass a specific template, but the default behavior (discipline) is unchanged.

**class-rest-invoices.php:**
In `send_invoice()` (around line 919), BEFORE the `InvoiceEmailSender::send()` call:
1. Read `invoice_type`: `$invoice_type = get_field( 'invoice_type', $invoice_id );`
2. If `$invoice_type === 'membership'`, add template to email_options:
```php
if ( $invoice_type === 'membership' ) {
    $config = new FinanceConfig();
    $email_options['template'] = $config->get_membership_email_template();
}
```
Note: `$config` is already created later at line 940 — either move the creation earlier or create a new instance here (both are cheap since FinanceConfig has no constructor logic). Alternatively, check if $config already exists from context and reuse.

Apply the same logic in `resend_invoice()` (around line 1002): read invoice_type and pass membership template if applicable.

**class-rest-api.php:**
In the settings endpoint args array (around line 854), add:
```php
'membership_email_template' => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
```
Place it right after the existing `email_template` arg.
  </action>
  <verify>
Run `npm run build` to verify no frontend breakage. Check PHP syntax: `php -l includes/class-finance-config.php && php -l includes/class-invoice-email-sender.php && php -l includes/class-rest-invoices.php && php -l includes/class-rest-api.php`
  </verify>
  <done>
FinanceConfig has membership_email_template constant, default, getter, and update support. InvoiceEmailSender accepts optional template override. REST invoices pass the correct template based on invoice_type. REST API args accept the new key.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add membership template editor to FinanceSettings frontend</name>
  <files>
    src/pages/Finance/FinanceSettings.jsx
  </files>
  <action>
1. Add `membership_email_template: ''` to the initial formData state (line ~141, after `email_template`).
2. In the useEffect that loads settings (line ~173), add: `membership_email_template: settings.membership_email_template || ''`
3. In `handleSubmit` payload (line ~270), add: `membership_email_template: formData.membership_email_template`

4. In the email tab section (activeTab === 'email'), add a NEW card BETWEEN the discipline template card (ending ~line 644) and the installment template card (starting ~line 647). Structure:
```jsx
{/* Membership invoice email template */}
<div className="card p-6">
  <div className="mb-4">
    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Template e-mail voor contributie</h2>
    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
      Sjabloon voor de e-mail waarmee contributiefacturen worden verstuurd.
    </p>
  </div>
  <div className="space-y-4">
    <div>
      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        E-mailtekst
      </label>
      <RichTextEditor
        value={formData.membership_email_template}
        onChange={(html) => setFormData(prev => ({ ...prev, membership_email_template: html }))}
        placeholder="Schrijf hier het e-mailsjabloon voor contributie..."
        minHeight="200px"
      />
    </div>
    {/* Variable documentation */}
    <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
      <p className="font-semibold mb-2">Beschikbare variabelen:</p>
      <div className="space-y-1 font-mono">
        <div><code>{'{naam}'}</code> - Volledige naam van het lid</div>
        <div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>
        <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
        <div><code>{'{totaal_bedrag}'}</code> - Totaalbedrag</div>
        <div><code>{'{betaallink}'}</code> - Link naar betaalverzoek</div>
        <div><code>{'{qr_code}'}</code> - QR-code afbeelding (betaallink)</div>
        <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
      </div>
    </div>
    <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
      Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
    </p>
  </div>
</div>
```

Note the membership template does NOT list `{tuchtzaken_lijst}` since it's irrelevant for membership invoices (though the variable replacement still works — it would just produce empty output).

5. Update the existing discipline template card's subtitle (line ~609) from "Sjabloon voor de e-mail waarmee facturen worden verstuurd." to "Sjabloon voor de e-mail waarmee boete-facturen worden verstuurd." — to clarify this is discipline-specific.
  </action>
  <verify>
Run `npm run build` to confirm the frontend compiles. Run `npm run lint` to confirm no lint errors.
  </verify>
  <done>
Finance Settings email tab shows 4 template sections: Boetes (discipline), Contributie (membership), Termijnbetaling (installment), Herinneringen (reminders). Each independently editable with appropriate variable documentation.
  </done>
</task>

</tasks>

<verification>
1. `php -l` passes on all 4 modified PHP files
2. `npm run build` succeeds
3. `npm run lint` passes with 0 warnings
4. After deploy: Finance Settings > E-mail tab shows the new "Template e-mail voor contributie" card between discipline and installment sections
5. After deploy: Sending a membership invoice uses the membership template (no tuchtcommissie text)
6. After deploy: Sending a discipline invoice still uses the discipline template (unchanged behavior)
</verification>

<success_criteria>
- Membership invoices use a clean contributie-specific email template
- Discipline invoices continue to use the existing template (no regression)
- Both templates are independently configurable in Finance Settings
- Existing stored template data is preserved (no data migration needed)
</success_criteria>

<output>
After completion, create `.planning/quick/95-add-separate-email-template-for-contribu/95-SUMMARY.md`
</output>
