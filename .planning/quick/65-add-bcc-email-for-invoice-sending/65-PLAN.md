---
phase: 65-add-bcc-email-for-invoice-sending
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-finance-config.php
  - includes/class-invoice-email-sender.php
  - src/pages/Finance/FinanceSettings.jsx
autonomous: true

must_haves:
  truths:
    - "Admin can configure a BCC email address in finance settings"
    - "Invoice emails are sent with BCC to the configured address"
    - "System works with or without BCC address configured"
  artifacts:
    - path: "includes/class-finance-config.php"
      provides: "BCC email option constant, getter, and update logic"
      min_lines: 10
    - path: "includes/class-invoice-email-sender.php"
      provides: "BCC header added when BCC email configured"
      min_lines: 5
    - path: "src/pages/Finance/FinanceSettings.jsx"
      provides: "BCC email input field in settings UI"
      min_lines: 15
  key_links:
    - from: "src/pages/Finance/FinanceSettings.jsx"
      to: "FinanceConfig"
      via: "useFinanceSettings hook reading bcc_email"
      pattern: "bcc_email"
    - from: "includes/class-invoice-email-sender.php"
      to: "FinanceConfig::get_bcc_email()"
      via: "reading BCC setting and adding to headers"
      pattern: "get_bcc_email.*Bcc:"
---

<objective>
Add BCC email configuration for invoice sending to enable automatic copies to bookkeeping tools or treasurer's inbox.

Purpose: Allow club administrators to automatically receive copies of all sent invoices for record-keeping purposes.
Output: BCC email setting in finance config, UI in settings page, and implementation in email sender.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add BCC email to FinanceConfig</name>
  <files>includes/class-finance-config.php</files>
  <action>
Add BCC email configuration to FinanceConfig class following the established pattern:

1. Add constant after line 36:
   `const OPTION_BCC_EMAIL = 'rondo_finance_bcc_email';`

2. Add default in DEFAULTS array (line 51):
   `'bcc_email' => '',`

3. Add getter method after `get_accent_color()` (after line 145):
```php
/**
 * Get BCC email for invoice sending
 *
 * @return string The BCC email address (empty string if not configured)
 */
public function get_bcc_email(): string {
	return get_option( self::OPTION_BCC_EMAIL, self::DEFAULTS['bcc_email'] );
}
```

4. Add BCC email to `get_all_settings()` return array (after 'accent_color' line 190):
   `'bcc_email' => $this->get_bcc_email(),`

5. Add case to `get_setting()` switch statement (after 'accent_color' case, line 222):
```php
case 'bcc_email':
	return $this->get_bcc_email();
```

6. Add BCC email handling in `update_settings()` method (after accent_color handling, after line 285):
```php
if ( isset( $data['bcc_email'] ) ) {
	$success = update_option( self::OPTION_BCC_EMAIL, sanitize_email( $data['bcc_email'] ) ) && $success;
}
```

Pattern follows existing email field (contact_email) exactly — uses sanitize_email(), allows empty string.
  </action>
  <verify>
1. Grep for OPTION_BCC_EMAIL to confirm constant exists
2. Grep for 'bcc_email' to verify it appears in DEFAULTS, get_all_settings, get_setting, and update_settings
3. Check PHP syntax: `php -l includes/class-finance-config.php`
  </verify>
  <done>
- BCC email constant, default, getter, and update logic exist in FinanceConfig
- PHP file has no syntax errors
- All methods follow established contact_email pattern
  </done>
</task>

<task type="auto">
  <name>Task 2: Add BCC header to invoice emails</name>
  <files>includes/class-invoice-email-sender.php</files>
  <action>
Add BCC header to invoice email sending in InvoiceEmailSender::send() method.

After line 148 where headers are built (after the From header):
```php
// Add BCC if configured
$bcc_email = $config->get_bcc_email();
if ( ! empty( $bcc_email ) ) {
	$headers[] = 'Bcc: ' . $bcc_email;
}
```

Place this code immediately after the From header array initialization, before the attachments section (before line 151).

Pattern: wp_mail() accepts headers as array of strings, BCC format is 'Bcc: email@example.com'. Empty BCC setting means no BCC header added (backwards compatible).
  </action>
  <verify>
1. Grep for 'get_bcc_email' in invoice-email-sender to confirm method call exists
2. Grep for "Bcc:" to verify header format is correct
3. Check PHP syntax: `php -l includes/class-invoice-email-sender.php`
  </verify>
  <done>
- BCC header added to wp_mail headers when BCC email configured
- Empty BCC setting handled gracefully (no BCC header)
- PHP file has no syntax errors
  </done>
</task>

<task type="auto">
  <name>Task 3: Add BCC email field to Finance Settings UI</name>
  <files>src/pages/Finance/FinanceSettings.jsx</files>
  <action>
Add BCC email field to Finance Settings form following the established pattern.

1. Add `bcc_email: ''` to formData state initialization (line 137, after accent_color)

2. Load BCC email in useEffect (line 162, after accent_color):
   `bcc_email: settings.bcc_email || '',`

3. Add field to Organization Details section (Section 1: Organisatiegegevens) after the contact_email field (after line 344, before club logo):

```jsx
<div>
  <label htmlFor="bcc_email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
    BCC E-mailadres
  </label>
  <input
    type="email"
    id="bcc_email"
    value={formData.bcc_email}
    onChange={(e) => setFormData(prev => ({ ...prev, bcc_email: e.target.value }))}
    placeholder="penningmeester@vereniging.nl"
    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
  />
  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
    Alle factuurmails worden ook naar dit adres gestuurd (bv. voor de boekhouding of penningmeester)
  </p>
</div>
```

4. Add bcc_email to payload in handleSubmit (line 243, after accent_color):
   `bcc_email: formData.bcc_email,`

Pattern: Follows contact_email pattern exactly — type="email", optional field, descriptive help text explaining purpose.
  </action>
  <verify>
1. Grep for 'bcc_email' in FinanceSettings.jsx to confirm field appears in state, useEffect, form, and payload
2. Check for placeholder text 'penningmeester@vereniging.nl'
3. Run `npm run lint` to verify no new linting errors in this file
  </verify>
  <done>
- BCC email field appears in Finance Settings UI under Organization Details
- Field follows established pattern (email input with help text)
- Field is included in form state and submission payload
- No linting errors
  </done>
</task>

</tasks>

<verification>
After completing all tasks:

1. **Backend verification:**
   - Confirm FinanceConfig has OPTION_BCC_EMAIL constant and getter
   - Confirm update_settings handles bcc_email
   - Confirm InvoiceEmailSender adds Bcc header when configured

2. **Frontend verification:**
   - Confirm Finance Settings page has BCC email field
   - Confirm field is properly connected to state and API

3. **Integration verification:**
   - Settings can be saved with BCC email
   - Settings can be saved without BCC email (empty = no BCC)
   - Invoice emails include BCC header when configured
</verification>

<success_criteria>
- [ ] BCC email setting exists in FinanceConfig (constant, default, getter, update logic)
- [ ] InvoiceEmailSender adds Bcc header to wp_mail when BCC email configured
- [ ] Finance Settings UI has BCC email input field with help text
- [ ] System works correctly with or without BCC email configured
- [ ] All PHP files pass syntax check
- [ ] No new linting errors in React files
</success_criteria>

<output>
After completion, create `.planning/quick/65-add-bcc-email-for-invoice-sending/65-SUMMARY.md`
</output>
