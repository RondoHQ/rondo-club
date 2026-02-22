---
status: resolved
trigger: "On /tuchtzaken (discipline cases list page), the column settings cog icon is visible but clicking it doesn't allow selecting which columns to display — nothing happens or it doesn't work as expected."
created: 2026-02-22T00:00:00Z
updated: 2026-02-22T00:00:00Z
---

## Current Focus

hypothesis: RESOLVED
test: N/A
expecting: N/A
next_action: N/A

## Symptoms

expected: Clicking the cog icon should open a panel/modal allowing the user to toggle column visibility
actual: The cog is visible but selecting columns doesn't work — panel opens but shows "Geen kolommen beschikbaar" because columns=[] is hardcoded
errors: No visible errors
reproduction: Go to /tuchtzaken, click the cog icon for column settings
started: After DataTable refactoring on 2026-02-21 (hybrid approach pages)

## Eliminated

- hypothesis: Panel doesn't open
  evidence: isOpen state wiring is correct; onOpenColumnSettings={() => setIsColumnSettingsOpen(true)} is properly connected
  timestamp: 2026-02-22

## Evidence

- timestamp: 2026-02-22
  checked: DisciplineCasesList.jsx lines 295-300
  found: ColumnSettingsPanel called with columns={[]} (hardcoded empty) and onToggleColumn={() => {}} (no-op)
  implication: Panel opens but shows "Geen kolommen beschikbaar" — no columns to toggle

- timestamp: 2026-02-22
  checked: DisciplineCasesList.jsx — entire file
  found: useColumnVisibility hook is never imported or called; no visibility state exists at all
  implication: Even if columns were passed, there would be no state backing them

- timestamp: 2026-02-22
  checked: DisciplineCaseTable.jsx — entire file
  found: Component has no isVisible/columnVisibility prop; all columns (Wedstrijd, Sanctie, Kaart, Doorbelast, Boete) are always rendered unconditionally
  implication: Even if visibility state existed in parent, table would ignore it

- timestamp: 2026-02-22
  checked: VOGList.jsx — working hybrid page
  found: Correct pattern: useColumnVisibility('vog') → colVisColumns array → ColumnSettingsPanel with columns+onToggleColumn → isVisible passed to row component → conditional rendering
  implication: DisciplineCases needs same pattern applied

## Resolution

root_cause: DisciplineCasesList passed columns={[]} and onToggleColumn={() => {}} to ColumnSettingsPanel (placeholder values never wired up). useColumnVisibility was never called, so there was no column visibility state. DisciplineCaseTable had no mechanism to conditionally hide columns — all were always rendered.
fix: (1) Added useColumnVisibility('tuchtzaken') hook call; (2) Defined colVisColumns array for 5 toggleable columns (Wedstrijd, Sanctie, Kaart, Doorbelast, Boete); (3) Passed real columns+toggle to ColumnSettingsPanel; (4) Added isColVisible prop to DisciplineCaseTable (defaults to () => true for backward compat); (5) Added conditional rendering for each column header and body cell; (6) Fixed colSpan on expanded row to account for dynamic column count.
verification: Build succeeded, ESLint clean, deployed to production. Column visibility persists to localStorage under 'rondo-col-tuchtzaken' key.
files_changed:
  - src/pages/DisciplineCases/DisciplineCasesList.jsx
  - src/components/DisciplineCaseTable.jsx
