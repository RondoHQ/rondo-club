---
phase: quick-74
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/DisciplineCases/DisciplineCasesList.jsx
  - src/components/DisciplineCaseTable.jsx
autonomous: true

must_haves:
  truths:
    - "User can filter discipline cases by Doorbelast status (Nee/Ja, Sportlink/Ja, Rondo)"
    - "User can filter discipline cases by Sanctie text content"
    - "Filters work together with existing season filter"
    - "Filter state persists during user's session"
  artifacts:
    - path: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      provides: "Filter state management and UI for Doorbelast and Sanctie"
      min_lines: 200
    - path: "src/components/DisciplineCaseTable.jsx"
      provides: "Filtered case rendering based on parent filter state"
      min_lines: 470
  key_links:
    - from: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      to: "src/components/DisciplineCaseTable.jsx"
      via: "filtered cases prop"
      pattern: "cases={filteredCases}"
---

<objective>
Add client-side filtering capabilities to the Tuchtzaken (Discipline Cases) page for Doorbelast and Sanctie columns.

Purpose: Enable users to quickly filter the discipline cases list by charge-back status and sanction description without pagination overhead
Output: Interactive filter controls that work alongside the existing season filter
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@src/pages/DisciplineCases/DisciplineCasesList.jsx
@src/components/DisciplineCaseTable.jsx
@acf-json/group_discipline_case_fields.json
</context>

<tasks>

<task type="auto">
  <name>Add filter UI and state to DisciplineCasesList</name>
  <files>src/pages/DisciplineCases/DisciplineCasesList.jsx</files>
  <action>
Add two new filter controls to the Tuchtzaken list page:

1. **Doorbelast filter** - dropdown matching ACF field values:
   - Options: "Alle" (empty/""), "Nee" (empty/""), "Ja, Sportlink" ("sportlink"), "Ja, Rondo" ("rondo")
   - State: `const [doorbelastFilter, setDoorbelastFilter] = useState('')`
   - ACF field: `is_charged` (select field with choices: "", "sportlink", "rondo")

2. **Sanctie filter** - text input for case-insensitive search:
   - Placeholder: "Zoek op sanctie..."
   - State: `const [sanctieFilter, setSanctieFilter] = useState('')`
   - Searches: `acf.sanction_description` field

**UI placement:** Add filter controls next to existing season dropdown in the header section (currently lines 124-139). Use flexbox layout to show all three filters inline on desktop, wrap on mobile.

**Filter logic:** Create `filteredCases` memo that applies all three filters (season already handled by API query, doorbelast and sanctie on client):

```jsx
const filteredCases = useMemo(() => {
  if (!cases) return [];
  return cases.filter(dc => {
    const acf = dc.acf || {};

    // Doorbelast filter
    if (doorbelastFilter !== '') {
      if (doorbelastFilter === 'none' && acf.is_charged) return false;
      if (doorbelastFilter === 'sportlink' && acf.is_charged !== 'sportlink') return false;
      if (doorbelastFilter === 'rondo' && acf.is_charged !== 'rondo') return false;
    }

    // Sanctie filter (case-insensitive)
    if (sanctieFilter.trim() !== '') {
      const sanctionText = (acf.sanction_description || '').toLowerCase();
      if (!sanctionText.includes(sanctieFilter.toLowerCase().trim())) return false;
    }

    return true;
  });
}, [cases, doorbelastFilter, sanctieFilter]);
```

**Styling:** Match existing filter dropdown styles (electric-cyan focus ring, dark mode support). Use `<Filter />` icon from lucide-react for visual consistency.

**Pass to table:** Update `<DisciplineCaseTable cases={filteredCases} ... />` (line 154).

**Empty state:** Update empty state check to use `filteredCases` instead of `cases` (line 162).
  </action>
  <verify>
Visit http://localhost:5173/tuchtzaken (or production URL after deploy), confirm:
- Two new filter controls appear in header
- Doorbelast dropdown filters correctly for all four states
- Sanctie text input filters case-insensitively
- Filters work together (all must pass)
- Count display updates to reflect filtered results
  </verify>
  <done>
DisciplineCasesList.jsx manages doorbelast and sanctie filter state, applies client-side filtering via useMemo, passes filtered cases to table component, and displays filter controls in page header
  </done>
</task>

<task type="auto">
  <name>Update DisciplineCaseTable to handle filtered cases</name>
  <files>src/components/DisciplineCaseTable.jsx</files>
  <action>
No structural changes needed to DisciplineCaseTable.jsx — component already receives `cases` prop and renders whatever is passed.

**Verify expectations:**
- Component renders the `cases` prop as-is (already true, see line 144-189 sorting logic)
- Empty state works when `cases.length === 0` (already true, see line 204-209)
- Selection/invoice logic works with filtered subset (already true, uses `cases` for calculations)

**If needed:** Add JSDoc comment to clarify that `cases` prop may be pre-filtered:
```jsx
/**
 * @param {Array} props.cases - Array of discipline case objects (may be pre-filtered by parent)
```

This is defensive documentation — no code changes required.
  </action>
  <verify>
Check that DisciplineCaseTable.jsx receives filtered cases correctly:
- View component in browser with filters applied
- Confirm table only shows cases matching all active filters
- Confirm sorting still works on filtered subset
- Confirm selection/invoice features work on filtered cases
  </verify>
  <done>
DisciplineCaseTable.jsx correctly handles pre-filtered cases prop, with updated JSDoc documentation clarifying filter behavior
  </done>
</task>

</tasks>

<verification>
**Functional checks:**
1. Navigate to /tuchtzaken page
2. Test Doorbelast filter: "Alle", "Nee", "Ja, Sportlink", "Ja, Rondo"
3. Test Sanctie filter: partial text match, case insensitivity
4. Test combined filters: season + doorbelast + sanctie
5. Verify table updates immediately (useMemo triggers re-render)
6. Verify empty state shows when filters exclude all cases

**Code quality:**
- No console errors
- Filter state properly managed via useState
- Memo dependencies correct (cases, doorbelastFilter, sanctieFilter)
- Dark mode styling works
- Mobile responsive (filters wrap on small screens)
</verification>

<success_criteria>
Users can filter the Tuchtzaken list by:
- Doorbelast status (4 options: all, no, sportlink, rondo)
- Sanctie description (text search, case-insensitive)
- All filters work together with season filter
- Filter state persists during session (standard React state)
- UI matches existing design system (electric-cyan accents, dark mode)
</success_criteria>

<output>
After completion, create `.planning/quick/74-filter-tuchtzaken-page-by-doorbelast-and/74-SUMMARY.md`
</output>
