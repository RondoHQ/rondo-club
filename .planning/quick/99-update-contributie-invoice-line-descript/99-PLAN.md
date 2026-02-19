---
phase: quick-99
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-bulk-invoice-creator.php
autonomous: true

must_haves:
  truths:
    - "Membership invoice line shows 'Contributie 2025-2026 - Senioren' (season included)"
    - "Pro-rata discount line shows 'Instapkorting (X%) - omdat je later in het seizoen start'"
  artifacts:
    - path: "includes/class-bulk-invoice-creator.php"
      provides: "Updated line item descriptions with season and explanation text"
      contains: "'Contributie ' . $season"
  key_links:
    - from: "create_membership_invoice method"
      to: "line_items array"
      via: "$season parameter injected into description string"
      pattern: "Contributie.*\\$season"
---

<objective>
Update the two contributie invoice line item description strings in the bulk invoice creator:
1. Main fee line: add the season year (e.g. "Contributie 2025-2026 - Senioren")
2. Pro-rata (instapkorting) line: add explanation text "- omdat je later in het seizoen start"

Purpose: Members reading their invoice should immediately understand what season they are being charged for and why a discount was applied.
Output: Updated `class-bulk-invoice-creator.php` with two changed description strings.
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
  <name>Task 1: Update invoice line item descriptions</name>
  <files>includes/class-bulk-invoice-creator.php</files>
  <action>
    In `create_membership_invoice()` (method starting at line 170), make two targeted string changes:

    1. Line 265 — main fee description:
       Change: `'description' => 'Contributie - ' . $category_label,`
       To:     `'description' => 'Contributie ' . $season . ' - ' . $category_label,`

    `$season` is already the second parameter of the method (format: "2025-2026"), so no additional variable lookup is needed.

    2. Line 289 — pro-rata discount description:
       Change: `'description' => 'Instapkorting (' . $prorata_discount_pct . '%)',`
       To:     `'description' => 'Instapkorting (' . $prorata_discount_pct . '%) - omdat je later in het seizoen start',`

    No other changes required. Do not touch surrounding logic.
  </action>
  <verify>
    Run `npm run build` (frontend unaffected, but confirms no PHP parse errors sneak into the build).
    Grep to confirm both strings are updated:
    - `grep -n "Contributie" includes/class-bulk-invoice-creator.php` should show `'Contributie ' . $season . ' - '`
    - `grep -n "Instapkorting" includes/class-bulk-invoice-creator.php` should show `'omdat je later in het seizoen start'`
  </verify>
  <done>
    Both description strings are updated exactly as specified. A new bulk invoice created for a member with a pro-rata discount will have line items reading "Contributie 2025-2026 - [Category]" and "Instapkorting (X%) - omdat je later in het seizoen start".
  </done>
</task>

</tasks>

<verification>
- `grep "Contributie.*season" includes/class-bulk-invoice-creator.php` returns line with `$season`
- `grep "omdat je later" includes/class-bulk-invoice-creator.php` returns the instapkorting line
- PHP syntax valid (no parse errors)
</verification>

<success_criteria>
Both line item descriptions updated, code committed and deployed to production.
</success_criteria>

<output>
After completion, create `.planning/quick/99-update-contributie-invoice-line-descript/99-SUMMARY.md`
</output>
