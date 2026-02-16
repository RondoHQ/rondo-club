---
phase: 67-invoice-columns
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-invoice-pdf-generator.php
autonomous: true

must_haves:
  truths:
    - "Invoice PDF shows card type (Gele kaart/Rode kaart with charge description) instead of sanction description"
    - "Invoice PDF shows Schorsing column with 'Ja' only when sanction_description equals 'uitsluiting'"
    - "Invoice PDF table has 4 columns with appropriate widths"
  artifacts:
    - path: "includes/class-invoice-pdf-generator.php"
      provides: "4-column invoice PDF table with card type and suspension columns"
      min_lines: 500
  key_links:
    - from: "includes/class-invoice-pdf-generator.php"
      to: "discipline_case ACF fields"
      via: "get_field() calls"
      pattern: "get_field\\(['\"](charge_codes|charge_description|sanction_description)['\"]"
---

<objective>
Replace the "Sanctie" column on invoice PDFs with "Kaart" (card type) and add a new "Schorsing" (suspension) column.

Purpose: Better match invoice layout to discipline case data structure — show card type with charge description, and flag suspensions separately.

Output: Modified invoice PDF generator with 4-column table structure.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@/Users/joostdevalk/Code/rondo/rondo-club/includes/class-invoice-pdf-generator.php
</context>

<tasks>

<task type="auto">
  <name>Update invoice PDF table structure to show card type and suspension</name>
  <files>includes/class-invoice-pdf-generator.php</files>
  <action>
Modify the invoice PDF table structure in class-invoice-pdf-generator.php:

1. **Update table header** (lines 440-445):
   - Change from 3 columns to 4 columns
   - New headers: "Omschrijving" | "Kaart" | "Schorsing" | "Bedrag"
   - Adjust widths: Omschrijving (45%), Kaart (25%), Schorsing (15%), Bedrag (15%)

2. **Update line item building logic** (lines 258-284):
   - Keep existing `$description` logic (unchanged)
   - Replace `$sanction` variable with `$card_type` variable
   - For discipline cases, fetch `charge_codes` and `charge_description` fields:
     - If `charge_codes` ends with "-1" → card type is "Gele kaart"
     - Otherwise → card type is "Rode kaart"
     - Use `charge_description` field value (e.g., "waarschuwing") in the card type cell
     - Format: "{Gele/Rode kaart}: {charge_description}"
   - Add new `$suspension` variable:
     - For discipline cases, fetch `sanction_description` field
     - If `sanction_description` equals "uitsluiting" → set to "Ja"
     - Otherwise → set to empty string
   - Update the table row HTML to include 4 cells:
     - td: description
     - td: card type with charge description
     - td: suspension ("Ja" or empty)
     - td: amount (right-aligned)

3. **Update total row colspan** (line 450):
   - Change `colspan="2"` to `colspan="3"` (now spans 3 columns before the total)

**Implementation notes:**
- Use `get_field('charge_codes', $case_id)` and `get_field('charge_description', $case_id)` for card type
- Use string comparison: `sanction_desc === 'uitsluiting'` for suspension detection
- Maintain all existing escaping and formatting (esc_html, number_format)
  </action>
  <verify>
Generate a test invoice PDF and verify:
- Table has 4 columns with headers: Omschrijving, Kaart, Schorsing, Bedrag
- Card type column shows "Gele kaart" or "Rode kaart" with charge description
- Schorsing column shows "Ja" only for uitsluiting cases, empty otherwise
- Total row spans 3 columns correctly
  </verify>
  <done>
Invoice PDF generator produces 4-column table with card type (from charge_codes + charge_description) and suspension flag (from sanction_description). Total row colspan adjusted to 3.
  </done>
</task>

</tasks>

<verification>
- [ ] Invoice PDF table header has 4 columns with appropriate widths
- [ ] Card type column shows yellow/red card with charge description
- [ ] Suspension column shows "Ja" only for uitsluiting cases
- [ ] Total row colspan is correct (spans 3 columns)
- [ ] Generated PDF renders correctly with no layout issues
</verification>

<success_criteria>
- Invoice PDF displays 4-column table structure
- Kaart column correctly identifies card color based on charge_codes ending
- Schorsing column shows "Ja" exclusively for uitsluiting sanctions
- Visual layout is balanced and readable
</success_criteria>

<output>
After completion, create `.planning/quick/67-invoice-columns-card-type-instead-of-san/67-01-SUMMARY.md`
</output>
