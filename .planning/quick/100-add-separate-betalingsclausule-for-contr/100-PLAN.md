---
phase: quick-100
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-finance-config.php
  - includes/class-rest-api.php
  - includes/class-invoice-pdf-generator.php
  - src/pages/Finance/FinanceSettings.jsx
autonomous: true

must_haves:
  truths:
    - "Finance settings has two distinct betalingsclausule fields: one labeled 'Betalingsclausule tuchtzaken' and one labeled 'Betalingsclausule contributie'"
    - "Saving a membership_payment_clause value persists and reloads correctly"
    - "Membership PDF includes the membership payment clause text below the payment section when configured"
    - "Discipline PDF still shows the existing payment_clause (unchanged behavior)"
  artifacts:
    - path: "includes/class-finance-config.php"
      provides: "OPTION_MEMBERSHIP_PAYMENT_CLAUSE constant, get_membership_payment_clause() getter, updated get_all_settings(), get_setting(), update_settings()"
    - path: "includes/class-rest-api.php"
      provides: "membership_payment_clause REST arg registered"
    - path: "includes/class-invoice-pdf-generator.php"
      provides: "membership_payment_clause fetched and passed to build_html(), rendered in membership payment section"
    - path: "src/pages/Finance/FinanceSettings.jsx"
      provides: "membership_payment_clause in form state, load, save payload, and UI with renamed existing label"
  key_links:
    - from: "FinanceSettings.jsx"
      to: "REST POST /rondo/v1/finance/settings"
      via: "membership_payment_clause in payload"
    - from: "class-invoice-pdf-generator.php generate()"
      to: "build_html()"
      via: "membership_payment_clause parameter"
---

<objective>
Add a separate betalingsclausule setting for contributie (membership) invoices and rename the existing one to distinguish it as the tuchtzaken (discipline) clause.

Purpose: Currently only discipline invoices have a configurable payment clause in PDFs. Membership invoices need their own clause for different payment terms / legal text.
Output: New `membership_payment_clause` option stored in WP options, exposed via REST, rendered in membership PDF, configurable in FinanceSettings UI.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add membership_payment_clause to FinanceConfig (PHP backend)</name>
  <files>
    includes/class-finance-config.php
    includes/class-rest-api.php
  </files>
  <action>
In `includes/class-finance-config.php`:

1. Add constant after `OPTION_MEMBERSHIP_EMAIL_TEMPLATE` (line 61):
   ```php
   const OPTION_MEMBERSHIP_PAYMENT_CLAUSE   = 'rondo_finance_membership_payment_clause';
   ```

2. Add default in `DEFAULTS` array after `'membership_email_template'` (line 84):
   ```php
   'membership_payment_clause'  => '',
   ```

3. Add getter method after `get_membership_email_template()` (~line 194):
   ```php
   /**
    * Get membership (contributie) payment clause text
    *
    * Shown at the bottom of the membership invoice PDF payment section.
    *
    * @return string The membership payment clause text (empty string if not configured)
    */
   public function get_membership_payment_clause(): string {
       return get_option( self::OPTION_MEMBERSHIP_PAYMENT_CLAUSE, self::DEFAULTS['membership_payment_clause'] );
   }
   ```

4. Add to `get_all_settings()` return array after `'payment_clause'` entry:
   ```php
   'membership_payment_clause' => $this->get_membership_payment_clause(),
   ```

5. Add case to `get_setting()` switch after `case 'payment_clause':`:
   ```php
   case 'membership_payment_clause':
       return $this->get_membership_payment_clause();
   ```

6. Add to `update_settings()` after the `payment_clause` block:
   ```php
   if ( isset( $data['membership_payment_clause'] ) ) {
       $success = update_option( self::OPTION_MEMBERSHIP_PAYMENT_CLAUSE, sanitize_textarea_field( $data['membership_payment_clause'] ) ) && $success;
   }
   ```

In `includes/class-rest-api.php`:

After the `'payment_clause'` arg registration (~line 853), add:
```php
'membership_payment_clause' => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
```
  </action>
  <verify>Search for `OPTION_MEMBERSHIP_PAYMENT_CLAUSE` in class-finance-config.php confirms it exists. Search for `membership_payment_clause` in class-rest-api.php confirms the arg is registered.</verify>
  <done>Both files updated; `membership_payment_clause` is a first-class FinanceConfig option with constant, default, getter, get_all_settings entry, get_setting case, update_settings handler, and REST arg.</done>
</task>

<task type="auto">
  <name>Task 2: Render membership_payment_clause in PDF and update FinanceSettings UI</name>
  <files>
    includes/class-invoice-pdf-generator.php
    src/pages/Finance/FinanceSettings.jsx
  </files>
  <action>
In `includes/class-invoice-pdf-generator.php`:

1. In `generate()` method, after line 111 (`$payment_clause = $finance_config->get_payment_clause();`), add:
   ```php
   $membership_payment_clause = $finance_config->get_membership_payment_clause();
   ```

2. In the `build_html()` call (~line 141), add `$membership_payment_clause` as the last positional argument after `$payment_link`:
   ```php
   $membership_payment_clause
   ```

3. In `build_html()` signature (~line 230), add parameter after `$payment_link = ''`:
   ```php
   $membership_payment_clause = ''
   ```

4. In the membership payment section of `build_html()` (~line 517-529), after the `<p>` tag about the betaallink but before the `</td>` closing tag, append the clause:

   Current membership block:
   ```php
   ' . ( $is_membership ? '
   <div class="payment-section">
       <h2>Betaalgegevens</h2>
       <table style="width: 100%; border: none;"><tr>
           <td style="border: none; vertical-align: top; padding: 0;">
               <p style="margin: 0; line-height: 1.6;">Je ontvangt per e-mail een betaallink waarmee je direct kunt betalen of een betaalplan kunt kiezen.</p>
           </td>'
   ```

   Change the `<td>` block to:
   ```php
   ' . ( $is_membership ? '
   <div class="payment-section">
       <h2>Betaalgegevens</h2>
       <table style="width: 100%; border: none;"><tr>
           <td style="border: none; vertical-align: top; padding: 0;">
               <p style="margin: 0; line-height: 1.6;">Je ontvangt per e-mail een betaallink waarmee je direct kunt betalen of een betaalplan kunt kiezen.</p>
               ' . ( ! empty( $membership_payment_clause ) ? '<div class="payment-clause">' . nl2br( esc_html( $membership_payment_clause ) ) . '</div>' : '' ) . '
           </td>'
   ```

   The `.payment-clause` CSS class already exists in the stylesheet (~line 434), so no CSS changes needed.

In `src/pages/Finance/FinanceSettings.jsx`:

1. Add `membership_payment_clause: ''` to the `useState` formData object (~line 139), after `payment_clause: ''`.

2. Add `membership_payment_clause: settings.membership_payment_clause || ''` to the `setFormData` in `useEffect` (~line 172), after `payment_clause: settings.payment_clause || ''`.

3. Add `membership_payment_clause: formData.membership_payment_clause` to the payload in `handleSubmit` (~line 271), after `payment_clause: formData.payment_clause`.

4. Rename the existing "Betalingsclausule" label (~line 560) to "Betalingsclausule tuchtzaken". Also update the placeholder to "Tekst die onderaan de tuchtzaakfactuur wordt getoond over de betalingsvoorwaarden".

5. After the existing `payment_clause` textarea div (~line 570, closing `</div>`), add a new textarea field:
   ```jsx
   <div>
     <label htmlFor="membership_payment_clause" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
       Betalingsclausule contributie
     </label>
     <textarea
       id="membership_payment_clause"
       value={formData.membership_payment_clause}
       onChange={(e) => setFormData(prev => ({ ...prev, membership_payment_clause: e.target.value }))}
       placeholder="Tekst die onderaan de contributiefactuur wordt getoond"
       rows={3}
       className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent resize-none"
     />
   </div>
   ```
  </action>
  <verify>Run `npm run build` from `/Users/joostdevalk/Code/rondo/rondo-club/` — must complete with no errors. Grep for `membership_payment_clause` in both PHP files confirms it's present. Grep confirms "Betalingsclausule tuchtzaken" in FinanceSettings.jsx.</verify>
  <done>Build passes. PDF generator passes `membership_payment_clause` to `build_html()` and renders it in the membership payment section. FinanceSettings UI shows two distinct betalingsclausule fields with updated labels.</done>
</task>

</tasks>

<verification>
- `npm run build` passes with no errors
- `grep -n "membership_payment_clause" includes/class-finance-config.php` shows: constant, default, getter, get_all_settings entry, get_setting case, update_settings handler
- `grep -n "membership_payment_clause" includes/class-rest-api.php` shows the REST arg
- `grep -n "membership_payment_clause" includes/class-invoice-pdf-generator.php` shows: fetch in generate(), parameter in build_html() call and signature, render in membership section
- `grep -n "membership_payment_clause\|Betalingsclausule" src/pages/Finance/FinanceSettings.jsx` shows two textarea fields with correct labels
</verification>

<success_criteria>
- Two separate betalingsclausule fields visible in FinanceSettings under the payment section
- Existing field labeled "Betalingsclausule tuchtzaken" — discipline PDFs unaffected
- New field labeled "Betalingsclausule contributie" — membership PDFs render clause when non-empty
- Saving persists both values independently via the REST API
- Build is clean (no ESLint/TypeScript errors)
</success_criteria>

<output>
After completion, create `.planning/quick/100-add-separate-betalingsclausule-for-contr/100-SUMMARY.md`
</output>
