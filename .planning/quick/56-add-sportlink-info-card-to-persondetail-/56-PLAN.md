---
phase: quick-56
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/SportlinkCard.jsx
  - src/pages/People/PersonDetail.jsx
autonomous: true
must_haves:
  truths:
    - "Sportlink sync fields (lid-sinds, lid-tot, leeftijdsgroep, type-lid, datum-foto, isparent) are visible in the person detail sidebar"
    - "Card only appears when person has at least one populated Sportlink field"
    - "Empty fields are hidden, only populated fields are shown"
  artifacts:
    - path: "src/components/SportlinkCard.jsx"
      provides: "Sportlink info card component"
      min_lines: 20
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Sidebar rendering of SportlinkCard"
      contains: "SportlinkCard"
  key_links:
    - from: "src/pages/People/PersonDetail.jsx"
      to: "src/components/SportlinkCard.jsx"
      via: "import and render in sidebar"
      pattern: "SportlinkCard.*acfData"
---

<objective>
Add a read-only Sportlink info card to the PersonDetail sidebar showing synced fields from Sportlink (lid-sinds, lid-tot, leeftijdsgroep, type-lid, datum-foto, isparent).

Purpose: Give users visibility into Sportlink sync data directly on the person detail page.
Output: New SportlinkCard component rendered in sidebar.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/components/VOGCard.jsx (pattern reference for sidebar card)
@src/pages/People/PersonDetail.jsx (integration target — sidebar at line 1594-1648)
@src/utils/dateFormat.js (format utility with Dutch locale)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create SportlinkCard component and integrate in sidebar</name>
  <files>src/components/SportlinkCard.jsx, src/pages/People/PersonDetail.jsx</files>
  <action>
Create `src/components/SportlinkCard.jsx` following the VOGCard pattern:

1. **Props:** Receives `acfData` prop (the `person.acf` object).

2. **Visibility logic:** Return `null` if none of the 6 Sportlink fields have a value. Check these ACF field keys:
   - `lid-sinds` (date_picker, format Y-m-d)
   - `lid-tot` (date_picker, format Y-m-d)
   - `leeftijdsgroep` (text)
   - `type-lid` (text)
   - `datum-foto` (date_picker, format Y-m-d)
   - `isparent` (true_false)

   For the visibility check: dates and text fields are truthy when populated; `isparent` should be considered "has data" when it equals `true` or `'1'` (since `false`/`'0'` is also meaningful data from Sportlink, include isparent in the card if ANY other field is present, but don't let isparent alone trigger the card since it defaults to false).

3. **Layout:**
   - Container: `card p-6 mb-4` (same as VOGCard)
   - Header: `Database` icon from lucide-react + "Sportlink" text with `font-semibold text-brand-gradient`
   - Body: List of label/value rows using `dl` element. Each row:
     - `dt`: `text-sm text-gray-500 dark:text-gray-400`
     - `dd`: `text-sm text-gray-900 dark:text-gray-100`
   - Only render rows where the field has a value (skip empty/null/undefined)

4. **Field rendering:**
   - Date fields (`lid-sinds`, `lid-tot`, `datum-foto`): Use `format(new Date(value), 'd MMM yyyy')` from `@/utils/dateFormat`
   - Text fields (`leeftijdsgroep`, `type-lid`): Display as-is
   - Boolean `isparent`: Display "Ja" when true/`'1'`, "Nee" when false/`'0'`; only show this row if any other field is present

5. **Dutch labels for display:**
   - `lid-sinds` -> "Lid sinds"
   - `lid-tot` -> "Lid tot"
   - `leeftijdsgroep` -> "Leeftijdsgroep"
   - `type-lid` -> "Type lid"
   - `datum-foto` -> "Datum foto"
   - `isparent` -> "Ouder van lid"

Then in `src/pages/People/PersonDetail.jsx`:
- Add import: `import SportlinkCard from '@/components/SportlinkCard';`
- Add `<SportlinkCard acfData={person?.acf} />` in the sidebar, after the `VOGCard` line (line ~1601) and before the Todos card (line ~1604).
  </action>
  <verify>
Run `npm run build` from `/Users/joostdevalk/Code/rondo/rondo-club/` — build succeeds with no errors.
Run `npm run lint` — no new lint errors introduced.
Verify SportlinkCard.jsx exists and exports a default function component.
Verify PersonDetail.jsx imports and renders SportlinkCard in the sidebar.
  </verify>
  <done>
SportlinkCard component renders Sportlink sync fields in the PersonDetail sidebar. Card only shows when person has at least one populated Sportlink field. Empty fields are hidden. Dates formatted in Dutch locale. isparent shows as "Ja"/"Nee".
  </done>
</task>

</tasks>

<verification>
- `npm run build` succeeds
- `npm run lint` passes (no new errors)
- SportlinkCard.jsx follows VOGCard pattern (card p-6 mb-4, text-brand-gradient header)
- Only populated fields shown, card hidden when no data
</verification>

<success_criteria>
Sportlink info card visible in PersonDetail sidebar for persons with Sportlink data. Card hidden for persons without any Sportlink fields populated. All dates use Dutch locale formatting.
</success_criteria>

<output>
After completion, create `.planning/quick/56-add-sportlink-info-card-to-persondetail-/56-SUMMARY.md`
</output>
