---
phase: quick-96
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-invoice-numbering.php
  - includes/class-bulk-invoice-creator.php
autonomous: true
must_haves:
  truths:
    - "Discipline invoices use T prefix (e.g., 2026T001) — unchanged"
    - "Membership invoices use C prefix (e.g., 2026C001) — new"
    - "T and C sequences are independent (both can have 001)"
    - "Existing invoice number validation accepts both T and C prefixes"
  artifacts:
    - path: "includes/class-invoice-numbering.php"
      provides: "Type-aware invoice number generation"
      contains: "string $type = 'discipline'"
    - path: "includes/class-bulk-invoice-creator.php"
      provides: "Membership invoice creation with C prefix"
      contains: "generate_next( 'membership' )"
  key_links:
    - from: "includes/class-bulk-invoice-creator.php"
      to: "includes/class-invoice-numbering.php"
      via: "generate_next('membership') call"
      pattern: "generate_next.*membership"
---

<objective>
Add separate invoice numbering for contributie (membership) invoices using a C prefix, so discipline invoices use format 2026T001 and membership invoices use format 2026C001, each with their own independent sequence counter.

Purpose: Distinguish invoice types at a glance by number prefix and maintain separate numbering sequences.
Output: Updated InvoiceNumbering class with type parameter, updated BulkInvoiceCreator caller.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-invoice-numbering.php
@includes/class-bulk-invoice-creator.php
@includes/class-rest-invoices.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add type parameter to InvoiceNumbering and update caller</name>
  <files>includes/class-invoice-numbering.php, includes/class-bulk-invoice-creator.php</files>
  <action>
  In `includes/class-invoice-numbering.php`:

  1. Update `generate_next()` signature to accept a type parameter with discipline as default:
     `public static function generate_next( string $type = 'discipline' ): string`

  2. Replace the hardcoded `$prefix = $year . 'T'` with type-aware prefix:
     ```php
     $letter = ( $type === 'membership' ) ? 'C' : 'T';
     $prefix = $year . $letter;
     ```

  3. Update the PHPDoc to document the new parameter and C prefix format.

  4. Update `is_valid()` regex from `/^\d{4}T\d{3,}$/` to `/^\d{4}[TC]\d{3,}$/` to accept both prefixes. Update the PHPDoc comment accordingly.

  In `includes/class-bulk-invoice-creator.php`:

  5. Update line 232 from `InvoiceNumbering::generate_next()` to `InvoiceNumbering::generate_next( 'membership' )`.

  The REST invoices caller (`class-rest-invoices.php` line 527) does NOT need changes — it creates discipline invoices and the default parameter value handles this.
  </action>
  <verify>
  Run `grep -n "generate_next" includes/class-invoice-numbering.php` to confirm the type parameter exists.
  Run `grep -n "generate_next" includes/class-bulk-invoice-creator.php` to confirm it passes 'membership'.
  Run `grep -n "\[TC\]" includes/class-invoice-numbering.php` to confirm the updated regex.
  Run `npm run build` to verify no frontend breakage (this is backend-only but confirms no regressions).
  </verify>
  <done>
  - `InvoiceNumbering::generate_next()` accepts optional type parameter, defaults to 'discipline' (T prefix)
  - `InvoiceNumbering::generate_next('membership')` generates C-prefixed numbers (e.g., 2026C001)
  - T and C sequences are independent — each queries only its own prefix
  - `is_valid()` accepts both T and C prefixed numbers
  - BulkInvoiceCreator passes 'membership' type when generating invoice numbers
  - REST invoice creator unchanged (uses default 'discipline')
  </done>
</task>

</tasks>

<verification>
- `grep "string \$type" includes/class-invoice-numbering.php` shows the parameter
- `grep "'membership'" includes/class-bulk-invoice-creator.php` shows the caller update
- `grep "\[TC\]" includes/class-invoice-numbering.php` shows updated regex
- No other callers of `generate_next()` need changes (REST invoices uses default)
</verification>

<success_criteria>
Membership invoices created via BulkInvoiceCreator will receive C-prefixed numbers (2026C001, 2026C002...) while discipline invoices created via REST API continue to receive T-prefixed numbers (2026T001, 2026T002...). Both prefixes pass validation.
</success_criteria>

<output>
After completion, create `.planning/quick/96-separate-invoice-numbering-for-contribut/96-SUMMARY.md`
</output>
