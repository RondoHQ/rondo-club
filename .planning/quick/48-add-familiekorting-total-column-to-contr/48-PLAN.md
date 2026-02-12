---
phase: quick-48
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
    - "Overzicht table shows Familiekorting column between Basis totaal and Netto totaal"
    - "Each category row shows the sum of family_discount_amount for that category as a negative value"
    - "Grand total row shows the total family discount across all categories"
    - "Familiekorting column works correctly in both current season and forecast mode"
  artifacts:
    - path: "includes/class-rest-api.php"
      provides: "family_discount aggregation in get_fee_summary"
      contains: "family_discount"
    - path: "src/pages/Contributie/ContributieOverzicht.jsx"
      provides: "Familiekorting column in overview table"
      contains: "Familiekorting"
  key_links:
    - from: "includes/class-rest-api.php"
      to: "src/pages/Contributie/ContributieOverzicht.jsx"
      via: "family_discount field in aggregates response"
      pattern: "family_discount"
---

<objective>
Add a Familiekorting (family discount) total column to the Contributie Overzicht tab, showing the aggregated family discount per category and as a grand total.

Purpose: Makes the fee breakdown transparent — users can see Basis totaal - Familiekorting = Netto totaal for each category.
Output: Updated API endpoint with family_discount aggregation, updated frontend table with new column.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-rest-api.php (lines 3146-3260 — get_fee_summary method)
@src/pages/Contributie/ContributieOverzicht.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add family_discount aggregation to /fees/summary endpoint</name>
  <files>includes/class-rest-api.php</files>
  <action>
In the `get_fee_summary` method (line 3146), add `family_discount` to the per-category aggregation:

1. On line 3219, update the initial aggregate array to include `family_discount`:
   ```php
   $aggregates[ $cat ] = [ 'count' => 0, 'base_fee' => 0, 'family_discount' => 0, 'final_fee' => 0 ];
   ```

2. After the `base_fee` accumulation (line 3222) and before the forecast/current final_fee block (line 3225), add:
   ```php
   $aggregates[ $cat ]['family_discount'] += $fee_data['family_discount_amount'] ?? 0;
   ```

3. In the rounding loop (lines 3234-3237), add rounding for the new field:
   ```php
   $agg['family_discount'] = round( $agg['family_discount'], 2 );
   ```
  </action>
  <verify>
Deploy and test the endpoint: `curl` the `/rondo/v1/fees/summary` endpoint (or check via browser devtools on the Contributie page) and confirm each category aggregate now includes a `family_discount` numeric field.
  </verify>
  <done>The /fees/summary endpoint returns `family_discount` in each category's aggregate object, representing the sum of all family_discount_amount values for members in that category.</done>
</task>

<task type="auto">
  <name>Task 2: Add Familiekorting column to ContributieOverzicht table</name>
  <files>src/pages/Contributie/ContributieOverzicht.jsx</files>
  <action>
1. Update the `grandTotal` reduce (lines 48-55) to include `familyDiscount`:
   ```js
   const grandTotal = sortedCategories.reduce(
     (acc, [, agg]) => ({
       count: acc.count + agg.count,
       baseFee: acc.baseFee + agg.base_fee,
       familyDiscount: acc.familyDiscount + (agg.family_discount ?? 0),
       finalFee: acc.finalFee + agg.final_fee,
     }),
     { count: 0, baseFee: 0, familyDiscount: 0, finalFee: 0 }
   );
   ```

2. Add a new `<th>` column header between "Basis totaal" and "Netto totaal" (between lines 123 and 124):
   ```jsx
   <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
     Familiekorting
   </th>
   ```

3. Add a new `<td>` in each category row between the base_fee cell and final_fee cell (between lines 147 and 148). Display as negative with a minus sign when the value is > 0:
   ```jsx
   <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
     {agg.family_discount > 0 ? `- ${formatCurrency(agg.family_discount, 2)}` : formatCurrency(0, 2)}
   </td>
   ```

4. Add a new `<td>` in the tfoot grand total row between the baseFee cell and finalFee cell (between lines 163 and 165). Same negative display pattern:
   ```jsx
   <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
     {grandTotal.familyDiscount > 0 ? `- ${formatCurrency(grandTotal.familyDiscount, 2)}` : formatCurrency(0, 2)}
   </td>
   ```

5. Run `npm run build` from `/Users/joostdevalk/Code/rondo/rondo-club/` to compile production assets.
  </action>
  <verify>
Run `npm run lint` and `npm run build` successfully. Then deploy via `bin/deploy.sh` and verify on production that the Contributie > Overzicht tab shows 5 columns: Categorie, Leden, Basis totaal, Familiekorting, Netto totaal — with family discount values displayed as negative numbers.
  </verify>
  <done>The Contributie Overzicht table displays a Familiekorting column showing per-category and grand total family discount amounts as negative values, in both current season and forecast mode.</done>
</task>

</tasks>

<verification>
1. `npm run lint` passes with no new errors
2. `npm run build` succeeds
3. On production Contributie page, Overzicht tab shows the Familiekorting column
4. Categories with family discounts show negative values (e.g., "- EUR 125,00")
5. Categories with zero family discount show EUR 0,00
6. Grand total row sums family discount correctly
7. Switching between current season and forecast both work correctly
</verification>

<success_criteria>
- API returns family_discount in each aggregate
- Table has 5 columns with Familiekorting between Basis totaal and Netto totaal
- Values display as negative amounts
- Works in both current and forecast mode
</success_criteria>

<output>
After completion, create `.planning/quick/48-add-familiekorting-total-column-to-contr/48-SUMMARY.md`
</output>
