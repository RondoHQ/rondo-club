---
phase: 200-csv-export
plan: 01
subsystem: frontend-export
tags: [csv, export, frontend, cleanup]
dependency_graph:
  requires: []
  provides: [csv-export-utility, csv-buttons-people, csv-buttons-vog, csv-buttons-contributie]
  affects: [PeopleList, VOGList, ContributieList]
tech_stack:
  added:
    - src/utils/csvExport.js (browser Blob API, URL.createObjectURL)
  patterns:
    - shared utility module for CSV serialization
    - client-side file download via anchor click
    - semicolon delimiter for Dutch Excel compatibility
    - UTF-8 BOM for correct Excel encoding detection
key_files:
  created:
    - src/utils/csvExport.js
  modified:
    - src/pages/People/PeopleList.jsx
    - src/pages/VOG/VOGList.jsx
    - src/pages/Contributie/ContributieList.jsx
decisions:
  - Export current page only (not all filtered pages) — simpler, no new API endpoint needed, consistent with visible data
  - Export fixed columns (not user-configured visible columns) — avoids coupling CSV to complex column preferences system
  - No new library — browser Blob + URL.createObjectURL is sufficient for flat CSV data
metrics:
  duration: 4min
  completed: 2026-02-20
  tasks_completed: 2
  files_created: 1
  files_modified: 3
---

# Phase 200 Plan 01: CSV Export Summary

Client-side CSV export with semicolon delimiter and UTF-8 BOM added to People, VOG, and Contributie list pages; all dead Google Sheets export code removed.

## What Was Built

A shared CSV utility module (`src/utils/csvExport.js`) exports three functions:
- `escapeCell(value)` — RFC 4180-compliant cell escaping with quote doubling
- `buildCsv(rows)` — 2D array to semicolon-delimited CSV string
- `downloadCsv(csvString, filename)` — browser file download via Blob + anchor click, prepends UTF-8 BOM

Each of the three list pages received:
- `import { buildCsv, downloadCsv } from '@/utils/csvExport'`
- `import { Download } from 'lucide-react'` (replacing `FileSpreadsheet`)
- A `handleExportCsv()` function mapping already-loaded data to CSV rows
- A Download button (`btn-secondary`, `title="Downloaden als CSV"`) replacing the Sheets button

Dead Google Sheets code removed from all three pages:
- `sheetsStatus` query (`getSheetsStatus` API call)
- `isExporting` state
- `handleExportToSheets` async function
- `handleConnectSheets` async function
- `FileSpreadsheet` lucide import
- `prmApi` import removed from ContributieList (no longer needed there)
- `useQuery` import removed from ContributieList (was only used for sheetsStatus)

## CSV Column Definitions

**People (leden-YYYY-MM-DD.csv):**
`Naam | Voornaam | Tussenvoegsel | Achternaam | Email | Telefoon | Team`

**VOG (vog-YYYY-MM-DD.csv):**
`Naam | KNVB ID | Email | Telefoon | Datum VOG | Type | 1e email | Justis | Herinnering`

**Contributie (contributie-{season}.csv):**
`Voornaam | Achternaam | Categorie | Leeftijdsgroep | Basis | Gezinskorting | Pro-rata | Bedrag [| Nikki | Saldo]`
(Nikki and Saldo columns only when `showNikkiColumns` is true)

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check

### Files Created
- `/Users/joostdevalk/Code/rondo/rondo-club/src/utils/csvExport.js` — FOUND

### Files Modified
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PeopleList.jsx` — FOUND
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/VOG/VOGList.jsx` — FOUND
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Contributie/ContributieList.jsx` — FOUND

### Commits
- `9e039d04` — feat(200-01): add CSV export to People, VOG, and Contributie list pages — FOUND

### Verification Results
- ESLint: 0 warnings, 0 errors
- Build: successful (csvExport-CYKy0hYN.js in dist)
- getSheetsStatus in list files: 0 matches
- sheetsStatus in list files: 0 matches
- handleExportToSheets in list files: 0 matches
- handleConnectSheets in list files: 0 matches
- FileSpreadsheet in list files: 0 matches
- isExporting in list files: 0 matches
- handleExportCsv in all three files: confirmed
- buildCsv uses `.join(';')`: confirmed
- downloadCsv prepends `\uFEFF`: confirmed

## Self-Check: PASSED
