---
phase: quick-84
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-invoice-email-sender.php
  - src/pages/Finance/FinanceSettings.jsx
autonomous: true

must_haves:
  truths:
    - "{voornaam} in an email template is replaced with the member's first name"
    - "Frontend variable docs show {naam} as 'Volledige naam van het lid'"
    - "Frontend variable docs show {voornaam} as 'Voornaam van het lid'"
  artifacts:
    - path: "includes/class-invoice-email-sender.php"
      provides: "{voornaam} token replacement"
      contains: "'{voornaam}'"
    - path: "src/pages/Finance/FinanceSettings.jsx"
      provides: "Updated variable documentation"
      contains: "Volledige naam van het lid"
  key_links:
    - from: "includes/class-invoice-email-sender.php"
      to: "get_field('first_name', $person_id)"
      via: "str_replace search/replace arrays"
      pattern: "voornaam.*first_name"
---

<objective>
Add {voornaam} as a new email template variable that outputs the member's first name, and update {naam}'s description to "Volledige naam van het lid" to distinguish it clearly.

Purpose: Club admins can now personalize invoice emails with just the first name. The updated {naam} description removes ambiguity.
Output: Updated PHP email sender and updated frontend variable docs.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add {voornaam} replacement in PHP email sender</name>
  <files>includes/class-invoice-email-sender.php</files>
  <action>
In the str_replace() call around line 189, add '{voornaam}' to the search array and esc_html( $first_name ) to the replace array. The variable $first_name is already available at line 64.

The search array (lines 190-198) currently contains 7 tokens. Add '{voornaam}' as the second entry (after '{naam}'):
  '{naam}',
  '{voornaam}',   ← add this

And in the replace array (lines 199-207), add esc_html( $first_name ) as the second entry (after esc_html( $person_name )):
  esc_html( $person_name ),
  esc_html( $first_name ),   ← add this

Position matters — search and replace arrays must stay aligned.
  </action>
  <verify>
    php -l includes/class-invoice-email-sender.php
    grep -n "voornaam" includes/class-invoice-email-sender.php
  </verify>
  <done>File is valid PHP, 'voornaam' appears in both the search array (as '{voornaam}') and replace array (as esc_html( $first_name )).</done>
</task>

<task type="auto">
  <name>Task 2: Update frontend variable documentation in FinanceSettings</name>
  <files>src/pages/Finance/FinanceSettings.jsx</files>
  <action>
In the "Beschikbare variabelen" section around line 596:

1. Change the {naam} description from "Naam van het lid" to "Volledige naam van het lid":
   Before: `<div><code>{'{naam}'}</code> - Naam van het lid</div>`
   After:  `<div><code>{'{naam}'}</code> - Volledige naam van het lid</div>`

2. Add a new line immediately after the {naam} line:
   `<div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>`

Keep the same JSX style and indentation as the surrounding lines.
  </action>
  <verify>
    grep -n "voornaam\|Volledige" src/pages/Finance/FinanceSettings.jsx
    npm run build 2>&1 | tail -5
  </verify>
  <done>File contains "Volledige naam van het lid" for {naam} and a new line with {voornaam} — "Voornaam van het lid". Build succeeds with no errors.</done>
</task>

</tasks>

<verification>
- PHP syntax valid: php -l includes/class-invoice-email-sender.php
- Both files updated: grep confirms 'voornaam' in both files
- Frontend build passes: npm run build exits 0
</verification>

<success_criteria>
- {voornaam} in an email template is replaced with the member's first name when an invoice is sent
- The frontend FinanceSettings page shows {naam} as "Volledige naam van het lid" and {voornaam} as "Voornaam van het lid"
- npm run build succeeds
</success_criteria>

<output>
After completion, create `.planning/quick/84-add-voornaam-email-variable-and-update-n/84-SUMMARY.md`
</output>
