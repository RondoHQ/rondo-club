---
phase: quick-88
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/DisciplineCaseTable.jsx
  - src/pages/DisciplineCases/DisciplineCasesList.jsx
autonomous: true

must_haves:
  truths:
    - "When boete (administrative_fee) is €0,00 and case is not charged, Doorbelast column shows 'n.v.t.' instead of 'Nee'"
    - "Cases with 'n.v.t.' do NOT appear when filtering by 'Nee'"
    - "A 'n.v.t.' filter option exists and correctly shows only zero-fee uncharged cases"
    - "Expanded row details also show 'n.v.t.' for these cases"
  artifacts:
    - path: "src/components/DisciplineCaseTable.jsx"
      provides: "Updated Doorbelast display and sort logic"
    - path: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      provides: "Updated filter logic and filter dropdown"
  key_links:
    - from: "DisciplineCasesList.jsx doorbelast filter"
      to: "DisciplineCaseTable.jsx is_charged display"
      via: "filteredCases passed as cases prop"
      pattern: "doorbelastFilter.*nvt|nvt.*is_charged"
---

<objective>
When a discipline case has a boete (administrative_fee) of €0,00 and is not charged (is_charged is falsy), the Doorbelast column should show "n.v.t." (not applicable) instead of "Nee". These cases should not appear in the "Nee" filter and should have their own "n.v.t." filter option.

Purpose: Accurately reflects that a €0 fine has no charging applicable, rather than implying a charge was deliberately not passed on.
Output: Updated DisciplineCaseTable display + updated filter dropdown and logic.
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
  <name>Task 1: Update DisciplineCaseTable to show n.v.t. for zero-fee uncharged cases</name>
  <files>src/components/DisciplineCaseTable.jsx</files>
  <action>
    Define a helper at the top of the component (or inline) to determine if a case is "n.v.t.":
    `const isNotApplicable = (acf) => !acf.is_charged && (parseFloat(acf.administrative_fee) || 0) === 0`

    Update the Doorbelast display in the table row (line ~389) from:
    `{acf.is_charged === 'sportlink' ? 'Ja, Sportlink' : acf.is_charged === 'rondo' ? 'Ja, Rondo' : acf.is_charged ? 'Ja' : 'Nee'}`
    To a function/helper that returns 'n.v.t.' when isNotApplicable(acf) is true, otherwise the existing logic.

    Update the same display in the expanded row details section (line ~457) identically.

    Update the sort case 'charged' (line ~179) so that n.v.t. cases sort separately from true "Nee" cases — assign value -1 for n.v.t., 0 for Nee, 1 for charged:
    `cmp = (isNVT(acfA) ? -1 : acfA.is_charged ? 1 : 0) - (isNVT(acfB) ? -1 : acfB.is_charged ? 1 : 0)`

    Extract a module-level helper function `isDoorbelastNVT(acf)` used by both display locations and the sort logic to keep code DRY.
  </action>
  <verify>
    Run `npm run lint` from /Users/joostdevalk/Code/rondo/rondo-club — must pass with 0 warnings.
    Visually confirm: cases with €0,00 boete and no is_charged value show "n.v.t." in the Doorbelast column.
  </verify>
  <done>
    Table column shows "n.v.t." for zero-fee uncharged cases, "Nee" for non-zero-fee uncharged cases, and "Ja, Sportlink"/"Ja, Rondo" for charged cases. Expanded row also shows "n.v.t." correctly.
  </done>
</task>

<task type="auto">
  <name>Task 2: Update DisciplineCasesList filter dropdown and filter logic</name>
  <files>src/pages/DisciplineCases/DisciplineCasesList.jsx</files>
  <action>
    Add import or inline the same `isDoorbelastNVT` helper (import from DisciplineCaseTable if exported, or duplicate the one-liner).

    Update the doorbelast filter dropdown (lines ~170-179) to add a "n.v.t." option:
    ```jsx
    <option value="">Alle doorbelast</option>
    <option value="nvt">n.v.t.</option>
    <option value="none">Nee</option>
    <option value="sportlink">Ja, Sportlink</option>
    <option value="rondo">Ja, Rondo</option>
    ```

    Update the filter logic in filteredCases (lines ~107-112) to handle the new 'nvt' filter value and to exclude n.v.t. cases from the 'none' filter:
    ```js
    if (doorbelastFilter !== '') {
      const fee = parseFloat(acf.administrative_fee) || 0;
      const isNVT = !acf.is_charged && fee === 0;
      if (doorbelastFilter === 'nvt' && !isNVT) return false;
      if (doorbelastFilter === 'none' && (acf.is_charged || isNVT)) return false;
      if (doorbelastFilter === 'sportlink' && acf.is_charged !== 'sportlink') return false;
      if (doorbelastFilter === 'rondo' && acf.is_charged !== 'rondo') return false;
    }
    ```

    The key change to the 'none' filter: it now also returns false when `isNVT` is true, so n.v.t. cases are excluded from "Nee" results.
  </action>
  <verify>
    Run `npm run lint` from /Users/joostdevalk/Code/rondo/rondo-club — must pass with 0 warnings.
    Test filter: selecting "Nee" should NOT show zero-fee uncharged cases. Selecting "n.v.t." should show only zero-fee uncharged cases.
  </verify>
  <done>
    Doorbelast filter "Nee" excludes n.v.t. cases. New "n.v.t." option in dropdown shows only applicable cases. "Alle doorbelast" still shows everything.
  </done>
</task>

</tasks>

<verification>
- `npm run lint` passes with 0 warnings
- `npm run build` completes successfully
- On /tuchtzaken: cases with €0,00 boete and no is_charged show "n.v.t." in Doorbelast column
- Filter "Nee" excludes n.v.t. cases
- Filter "n.v.t." shows only zero-fee uncharged cases
- Expanded row for a zero-fee case shows "n.v.t." in the Details section
</verification>

<success_criteria>
Discipline cases with €0,00 boete and no is_charged value display "n.v.t." in the Doorbelast column (table + expanded detail). The "Nee" filter excludes these cases. A new "n.v.t." filter option exists and works correctly.
</success_criteria>

<output>
After completion, create `.planning/quick/88-show-doorbelast-as-n-v-t-when-boete-is-z/88-SUMMARY.md`
</output>
