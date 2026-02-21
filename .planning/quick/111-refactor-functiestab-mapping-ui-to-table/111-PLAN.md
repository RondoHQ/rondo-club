---
phase: 111-refactor-functiestab-mapping-ui-to-table
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Settings/Settings.jsx
autonomous: true
must_haves:
  truths:
    - "Functie" column header appears on the same row as role column headers
    - Each functie name appears on the same row as its checkboxes
    - Scrollable body still works (max-h-96 overflow-y-auto)
    - Stale functie indicator still renders on the same row
    - Dark mode, hover states, and rounded border are preserved
  artifacts:
    - path: src/pages/Settings/Settings.jsx
      provides: FunctiesTab with proper table markup
      contains: "<table>"
  key_links:
    - from: "<thead><tr>"
      to: "<tbody><tr>"
      via: "shared <table> column alignment"
      pattern: "<th>.*Functie"
---

<objective>
Replace the CSS-grid div layout in FunctiesTab's mapping section with a proper HTML `<table>` so column alignment is handled natively by the browser — functie name and checkboxes are guaranteed to be on the same row.

Purpose: The grid-cols-[1fr,auto] trick causes the header "Functie" label and body functie names to sit in separate layout contexts, making alignment fragile. A `<table>` solves this structurally.
Output: Modified FunctiesTab render in src/pages/Settings/Settings.jsx using semantic table markup.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Replace div grid with HTML table in FunctiesTab mapping section</name>
  <files>src/pages/Settings/Settings.jsx</files>
  <action>
    Replace lines 1666–1706 (the `div.border.rounded-md` mapping container) with a proper `<table>`.

    Structure to produce:

    ```jsx
    <div className="border rounded-md border-gray-300 dark:border-gray-600 overflow-hidden">
      <table className="w-full border-collapse">
        <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-600">
          <tr>
            <th className="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2">
              Functie
            </th>
            {roles.map(role => (
              <th key={role.slug} className="text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2 w-24">
                {role.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto block">
          {allFuncties.map(functie => {
            const isStale = !availableFuncties.includes(functie);
            return (
              <tr key={functie} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 table w-full">
                <td className="px-4 py-2.5 align-middle">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="text-sm text-gray-900 dark:text-gray-100 truncate">{functie}</span>
                    {isStale && (
                      <span className="text-xs text-gray-400 dark:text-gray-500 italic whitespace-nowrap">(niet meer actief)</span>
                    )}
                  </div>
                </td>
                {roles.map(role => (
                  <td key={role.slug} className="px-4 py-2.5 w-24 text-center align-middle">
                    <label className="flex items-center justify-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={!!(functieMapState[functie]?.[role.slug])}
                        onChange={(e) => handleCheckboxChange(functie, role.slug, e.target.checked)}
                        className="h-4 w-4 rounded text-electric-cyan focus:ring-electric-cyan border-gray-300"
                      />
                    </label>
                  </td>
                ))}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
    ```

    Notes:
    - Remove the inline `style={{ gridTemplateColumns }}` from both header and body rows — `<table>` handles column width natively via the `w-24` on `<th>`/`<td>`.
    - Use `display: block` + `overflow-y: auto` on `<tbody>` to preserve the scrollable body (max-h-96). Rows inside need `display: table; width: 100%` so they still fill the table width — use `className="hover:... table w-full"` on `<tr>` inside tbody.
    - Keep the outer `div.border.rounded-md.overflow-hidden` wrapper unchanged — it provides the rounded border and clip.
    - Everything outside lines 1666–1706 (save button, capability sync section) stays untouched.
  </action>
  <verify>
    1. Run `npm run lint` from /Users/joostdevalk/Code/rondo/rondo-club — must pass with 0 warnings.
    2. Run `npm run build` from /Users/joostdevalk/Code/rondo/rondo-club — must complete without errors.
    3. In browser: navigate to Settings > Functies tab. Confirm "Functie" header aligns with functie name column, role headers align with their checkboxes on the same row.
  </verify>
  <done>
    Lint and build pass. The mapping section uses `&lt;table&gt;`/`&lt;thead&gt;`/`&lt;tbody&gt;`/`&lt;tr&gt;`/`&lt;th&gt;`/`&lt;td&gt;` markup. Each functie name and its checkboxes are in a single `&lt;tr&gt;`. Stale indicator, hover states, dark mode, and scrollable body all still work.
  </done>
</task>

</tasks>

<verification>
- `npm run lint` exits 0
- `npm run build` exits 0
- No other component or section of Settings.jsx is modified
</verification>

<success_criteria>
FunctiesTab mapping section uses semantic HTML table markup. Column alignment is enforced by the browser's table layout engine, not CSS grid hacks. Functie name and checkboxes are always on the same row.
</success_criteria>

<output>
After completion, create `.planning/quick/111-refactor-functiestab-mapping-ui-to-table/111-SUMMARY.md`
</output>
