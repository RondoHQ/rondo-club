---
phase: quick-64
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-api.php
  - src/pages/Contributie/ContributieOverzicht.jsx
autonomous: true
must_haves:
  truths:
    - "Overzicht table shows the full fee calculation chain: Basis -> Familiekorting -> Na korting -> Pro-rata -> Netto"
    - "Totaal footer row shows correct sums for all columns including the new ones"
    - "Forecast mode still works correctly (no pro-rata column values, since forecast assumes full season)"
  artifacts:
    - path: "includes/class-rest-api.php"
      provides: "fee_after_discount and prorata_amount aggregates in get_fee_summary()"
      contains: "fee_after_discount"
    - path: "src/pages/Contributie/ContributieOverzicht.jsx"
      provides: "Updated table with 7 columns showing full calculation chain"
      contains: "Na korting"
  key_links:
    - from: "includes/class-rest-api.php"
      to: "src/pages/Contributie/ContributieOverzicht.jsx"
      via: "REST API response shape"
      pattern: "fee_after_discount|prorata_amount"
---

<objective>
Rework the Contributie Overzicht tab to show the full fee calculation chain transparently.

Purpose: Currently the table jumps from "Basis totaal" to "Familiekorting" to "Netto totaal", but "Netto totaal" actually includes both family discount AND pro-rata reduction without showing the pro-rata step. This makes the fee breakdown unclear. Adding intermediate columns makes the calculation chain transparent: Basis -> Familiekorting -> Na korting -> Pro-rata korting -> Netto.

Output: Updated REST endpoint and frontend table showing all five financial columns.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-rest-api.php (get_fee_summary method, lines 3194-3339)
@includes/class-membership-fees.php (calculate_full_fee method showing cache structure: base_fee, family_discount_amount, fee_after_discount, prorata_percentage, final_fee)
@src/pages/Contributie/ContributieOverzicht.jsx (current frontend table)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add fee_after_discount and prorata_amount to get_fee_summary() endpoint</name>
  <files>includes/class-rest-api.php</files>
  <action>
In `get_fee_summary()` (line 3194), update the aggregation to include two new fields per category:

1. **Non-forecast path** (lines 3298-3307): The serialized fee cache already contains `fee_after_discount` (base minus family discount) and `prorata_percentage`. Add aggregation for:
   - `fee_after_discount`: sum of `$fee_data['fee_after_discount']` (fallback: `$fee_data['base_fee'] - $fee_data['family_discount_amount']` for caches that predate this field)
   - `prorata_amount`: compute as `fee_after_discount - final_fee` per person (this is the pro-rata reduction amount)

2. **Forecast path** (lines 3268-3297): Forecast assumes full-season membership (no pro-rata). Set:
   - `fee_after_discount` = `$final_fee` (which is already base minus discount)
   - `prorata_amount` = 0

3. **Initialize aggregates** (lines 3292 and 3301): Add `'fee_after_discount' => 0, 'prorata_amount' => 0` to the initial aggregate arrays.

4. **Round the new fields** in the rounding loop (lines 3312-3316).

5. **Response** stays the same shape -- the aggregates objects just get two new keys. No schema changes needed.
  </action>
  <verify>
Deploy to production and test the endpoint directly:
`curl -s "https://rondo.svawc.nl/wp-json/rondo/v1/fees/summary" | python3 -m json.tool` (with auth cookie)
Verify each category aggregate now has: base_fee, family_discount, fee_after_discount, prorata_amount, final_fee.
Verify: fee_after_discount = base_fee - family_discount (per category).
Verify: final_fee = fee_after_discount - prorata_amount (per category).
  </verify>
  <done>API returns all five financial aggregate fields per category, math checks out: base_fee - family_discount = fee_after_discount, fee_after_discount - prorata_amount = final_fee.</done>
</task>

<task type="auto">
  <name>Task 2: Update ContributieOverzicht table to show full calculation chain</name>
  <files>src/pages/Contributie/ContributieOverzicht.jsx</files>
  <action>
Update the table to show 7 columns (2 info + 5 financial):

**Column headers (thead):**
1. Categorie (left-aligned, existing)
2. Leden (right-aligned, existing)
3. Basis totaal (right-aligned, existing)
4. Familiekorting (right-aligned, existing)
5. Na korting (right-aligned, NEW -- fee_after_discount)
6. Pro-rata (right-aligned, NEW -- prorata_amount, displayed as negative like familiekorting)
7. Netto totaal (right-aligned, existing -- final_fee, bold)

**Data rows (tbody):** Add cells for `agg.fee_after_discount` and `agg.prorata_amount`. Format pro-rata like family discount: show `- EUR X.XX` when > 0, show `EUR 0,00` when zero.

**Grand totals (tfoot):** Add `feeAfterDiscount` and `prorataAmount` to the reduce accumulator. Display in footer row with same styling as existing columns.

**Styling note:** The two new "intermediate" columns (Na korting, Pro-rata) should use `text-gray-500 dark:text-gray-400` (same as existing Basis totaal and Familiekorting). Only the final Netto totaal column should be bold/prominent.

Run `npm run build` after changes.
  </action>
  <verify>
Run `npm run build` -- must succeed with no errors.
Run `npm run lint` -- no new warnings/errors introduced.
Deploy to production and visually verify the overzicht table at /contributie shows all 7 columns with correct values.
  </verify>
  <done>The Contributie Overzicht table shows the complete fee chain: Categorie | Leden | Basis totaal | Familiekorting | Na korting | Pro-rata | Netto totaal, with correct totals in the footer row.</done>
</task>

</tasks>

<verification>
1. Navigate to /contributie -> Overzicht tab
2. Verify 7-column table renders: Categorie, Leden, Basis totaal, Familiekorting, Na korting, Pro-rata, Netto totaal
3. Math check: for any row, Basis totaal - Familiekorting = Na korting, and Na korting - Pro-rata = Netto totaal
4. Toggle to forecast mode -- Pro-rata column should show EUR 0,00 for all categories
5. Grand totals row at bottom should sum correctly across all categories
</verification>

<success_criteria>
- Full fee calculation chain visible in overzicht table (5 financial columns)
- Mathematical relationships hold: base - discount = after_discount, after_discount - prorata = netto
- Forecast mode works correctly (pro-rata is zero)
- No build or lint regressions
- Deployed to production
</success_criteria>

<output>
After completion, create `.planning/quick/64-using-the-new-membership-fees-system-rew/64-SUMMARY.md`
</output>
