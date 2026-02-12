---
quick: 55
type: execute
files_modified:
  - src/components/SearchableMultiSelect.jsx
  - src/pages/VOG/VOGSettings.jsx
autonomous: true

must_haves:
  truths:
    - "Selected commissies appear as removable chips/tags above the input"
    - "Typing in the search input filters the dropdown options"
    - "Clicking an option adds it to the selection and shows it as a chip"
    - "Clicking the X on a chip removes it from the selection"
    - "Clicking outside the dropdown closes it"
    - "Component works in dark mode"
  artifacts:
    - path: "src/components/SearchableMultiSelect.jsx"
      provides: "Reusable multi-select with search, chips, and dropdown"
    - path: "src/pages/VOG/VOGSettings.jsx"
      provides: "VOGSettings using SearchableMultiSelect instead of checkbox list"
  key_links:
    - from: "src/pages/VOG/VOGSettings.jsx"
      to: "src/components/SearchableMultiSelect.jsx"
      via: "import and usage"
      pattern: "SearchableMultiSelect"
---

<objective>
Replace the scrollable checkbox list for "Vrijgestelde commissies" with a searchable multi-select component featuring chips for selected items, a search input, and a filtered dropdown.

Purpose: The current scrollable checkbox list is hard to navigate with many commissies. A chip-based searchable selector is much more intuitive.
Output: Reusable `SearchableMultiSelect` component + updated VOGSettings.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/components/AddressEditModal.jsx (SearchableCountrySelector pattern — single-select with search, dropdown, click-outside handling)
@src/pages/VOG/VOGSettings.jsx (current checkbox list implementation, lines 191-228)
@src/pages/Settings/FeeCategorySettings.jsx (same checkbox list pattern used for age classes, teams, werkfuncties — future reuse target)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create SearchableMultiSelect component</name>
  <files>src/components/SearchableMultiSelect.jsx</files>
  <action>
Create a new reusable `SearchableMultiSelect` component at `src/components/SearchableMultiSelect.jsx`.

**Props API:**
- `options` — array of `{ id, label }` objects (all available options)
- `selectedIds` — array of currently selected IDs
- `onChange(newIds)` — callback with updated ID array when selection changes
- `placeholder` — search input placeholder text (default: "Zoeken...")
- `emptyMessage` — message when no options match (default: "Geen opties gevonden")

**Component structure:**

1. **Selected chips area** (above the input): Render each selected option as a chip/tag showing its label with an X button to remove. Use `flex flex-wrap gap-1.5` for chip layout. Chips should use a subtle background (e.g., `bg-cyan-50 dark:bg-cyan-900/30 text-electric-cyan dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800`) with a small X icon from lucide-react. Only show the chips area when there are selected items.

2. **Search input**: A text input that filters the dropdown. Use the same input styling as the rest of the app (`px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-electric-cyan focus:ring-electric-cyan sm:text-sm`). On focus, open the dropdown.

3. **Dropdown list**: Positioned absolutely below the input. Shows options filtered by search term (case-insensitive match on label). Already-selected options should still appear but with a checkmark and highlighted background (similar to `SearchableCountrySelector` selected state). Clicking an unselected option adds it; clicking a selected option removes it (toggle behavior). Max height `max-h-48 overflow-y-auto`. Use `z-20` for z-index. Use `bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg`.

4. **Click-outside handling**: Use `useRef` for the container and `useEffect` with `mousedown` listener to close dropdown when clicking outside (follow the same pattern as `SearchableCountrySelector` in `AddressEditModal.jsx`).

5. **Empty state**: When `options` is empty, show `emptyMessage` text instead of the dropdown.

6. **After selecting/deselecting**: Keep the dropdown open and clear the search term so the user can quickly select multiple items.

Use `useState` for `searchTerm` and `isOpen`. Use `useMemo` for filtered options. Use `useRef` + `useEffect` for click-outside. Import `{ X, Check }` from `lucide-react`.

Export as default: `export default function SearchableMultiSelect({ ... })`.
  </action>
  <verify>
Run `npm run lint -- --no-warn -- src/components/SearchableMultiSelect.jsx` to check for lint errors. Verify the file exports a default function component with the correct prop signature.
  </verify>
  <done>SearchableMultiSelect.jsx exists with the documented props API, renders chips + search input + filtered dropdown, handles click-outside, supports dark mode.</done>
</task>

<task type="auto">
  <name>Task 2: Replace VOGSettings checkbox list with SearchableMultiSelect</name>
  <files>src/pages/VOG/VOGSettings.jsx</files>
  <action>
In `src/pages/VOG/VOGSettings.jsx`:

1. Add import at top: `import SearchableMultiSelect from '@/components/SearchableMultiSelect';`

2. Replace lines 191-228 (the "Vrijgestelde commissies" section with the checkbox list) with the `SearchableMultiSelect` component:

```jsx
{/* Exempt commissies */}
<div>
  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
    Vrijgestelde commissies
  </label>
  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
    Selecteer commissies die vrijgesteld zijn van de VOG-verplichting. Leden van deze commissies verschijnen niet in de VOG-lijst.
  </p>
  <SearchableMultiSelect
    options={commissies.map(c => ({ id: c.id, label: c.title?.rendered || c.title }))}
    selectedIds={vogSettings.exempt_commissies || []}
    onChange={(newIds) => setVogSettings(prev => ({ ...prev, exempt_commissies: newIds }))}
    placeholder="Commissie zoeken..."
    emptyMessage="Geen commissies gevonden"
  />
</div>
```

3. Keep all other code unchanged — the state management (`vogSettings.exempt_commissies`), fetching, and save logic remain the same.
  </action>
  <verify>
Run `npm run build` from `/Users/joostdevalk/Code/rondo/rondo-club` to verify the build succeeds with no errors. Run `npm run lint` to check for lint issues.
  </verify>
  <done>VOGSettings uses SearchableMultiSelect for the "Vrijgestelde commissies" field. The checkbox list is gone. Build passes. Selected commissies show as chips, search filters the dropdown, click-outside closes it.</done>
</task>

</tasks>

<verification>
1. `npm run build` completes without errors
2. `npm run lint` shows no new errors in the modified files
3. Visual verification on production after deploy: VOG Instellingen tab shows chips for selected commissies, search input filters the list, clicking X on a chip removes it, clicking outside closes dropdown
</verification>

<success_criteria>
- SearchableMultiSelect component exists at src/components/SearchableMultiSelect.jsx with the documented API
- VOGSettings.jsx uses SearchableMultiSelect instead of the scrollable checkbox list
- Selected commissies display as removable chips
- Search input filters available commissies
- Click-outside closes dropdown
- Dark mode works correctly
- Build passes without errors
</success_criteria>

<output>
After completion, create `.planning/quick/55-improve-vrijgestelde-commissies-multi-se/55-SUMMARY.md`
</output>
