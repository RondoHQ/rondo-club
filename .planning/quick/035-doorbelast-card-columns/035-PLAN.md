---
phase: 035-doorbelast-card-columns
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/DisciplineCaseTable.jsx
autonomous: true

must_haves:
  truths:
    - "Discipline cases table shows Doorbelast column with Ja/Nee"
    - "Discipline cases table shows Card column with yellow or red card"
    - "Yellow card shown when charge_codes ends with -1"
    - "Red card shown when charge_codes does not end with -1"
  artifacts:
    - path: "src/components/DisciplineCaseTable.jsx"
      provides: "Updated table with Doorbelast and Card columns"
      contains: "Doorbelast"
  key_links: []
---

<objective>
Add Doorbelast and Card columns to the discipline cases table.

Purpose: Allow users to quickly see if a case has been charged back and what type of card was given
Output: Updated DisciplineCaseTable with two new columns between Sanctie and Boete
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/components/DisciplineCaseTable.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add Doorbelast and Card columns to DisciplineCaseTable</name>
  <files>src/components/DisciplineCaseTable.jsx</files>
  <action>
Update DisciplineCaseTable.jsx to add two new columns between Sanctie and Boete:

1. **Card column** (after Sanctie):
   - Header: "Kaart" (centered)
   - Logic: Check if `acf.charge_codes` ends with `-1`
     - If ends with `-1`: Show yellow card emoji (use the literal Unicode character, not HTML entity)
     - Otherwise: Show red card emoji (use the literal Unicode character, not HTML entity)
   - If no charge_codes: Show `-`
   - Center-align the cell content

2. **Doorbelast column** (after Card):
   - Header: "Doorbelast" (centered)
   - Value: Show "Ja" if `acf.is_charged` is truthy, otherwise "Nee"
   - Center-align the cell content

3. Update the colspan in the expanded row from 5/4 to 7/6 (to account for 2 new columns when showPersonColumn is true/false)

Column order should be: Persoon (optional) | Wedstrijd | Sanctie | Kaart | Doorbelast | Boete | Expand icon
  </action>
  <verify>
1. Run `npm run lint` - no errors
2. Run `npm run build` - builds successfully
3. Deploy and verify on production that:
   - Card column shows yellow card for charge_codes ending in -1
   - Card column shows red card for other charge_codes
   - Doorbelast column shows Ja/Nee appropriately
   - Expanded rows still span full width
  </verify>
  <done>
Discipline cases table displays Kaart and Doorbelast columns with correct values and styling
  </done>
</task>

</tasks>

<verification>
- [ ] Lint passes with no errors
- [ ] Build completes successfully
- [ ] Card column logic works correctly (yellow for -1 suffix, red otherwise)
- [ ] Doorbelast column shows Ja/Nee based on is_charged field
- [ ] Expanded row spans all columns correctly
- [ ] Changes deployed to production
</verification>

<success_criteria>
- Discipline cases table has Kaart and Doorbelast columns
- Yellow card shown when charge_codes ends with -1
- Red card shown when charge_codes does not end with -1
- Doorbelast shows Ja when is_charged is true, Nee otherwise
- UI is consistent with existing table styling
</success_criteria>

<output>
After completion, create `.planning/quick/035-doorbelast-card-columns/035-SUMMARY.md`
</output>
