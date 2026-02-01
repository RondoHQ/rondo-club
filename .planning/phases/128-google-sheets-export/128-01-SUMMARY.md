# Phase 128 Plan 01: Google Sheets Export Summary

**One-liner:** Export Contributie data to Google Sheets with 10 Dutch-labeled columns, Euro formatting, and totals row.

## Execution Details

- **Duration:** 3 minutes
- **Completed:** 2026-02-01
- **Tasks:** 2/2
- **Commits:** 4

## What Was Built

### Backend: Export Fees Endpoint
- Added `POST /stadion/v1/google-sheets/export-fees` REST endpoint
- Endpoint creates new Google Sheets spreadsheet with:
  - Title: "Contributie {season} - {date}"
  - Sheet tab: "Contributie"
  - Locale: nl_NL for proper Euro formatting
- 10 columns with Dutch headers:
  1. Naam (text)
  2. Relatiecode (text - KNVB ID)
  3. Categorie (text)
  4. Leeftijdsgroep (text)
  5. Basis (currency EUR, whole numbers)
  6. Gezinskorting (percentage)
  7. Pro-rata % (percentage)
  8. Bedrag (currency EUR, whole numbers)
  9. Nikki Total (currency EUR, 2 decimals)
  10. Saldo (currency EUR, 2 decimals)
- Formatting applied:
  - Bold header row with gray background
  - Frozen header row
  - Currency and percentage number formats
  - Bold totals row
  - Auto-resized columns
- Totals row sums Basis and Bedrag only (not Nikki columns per CONTEXT.md)
- Supports sort_field and sort_order parameters to match UI view

### Frontend: Export Button
- Added `exportFeesToSheets` method to API client
- Added export button to Contributie page header (next to totals display)
- Button behavior:
  - Shows FileSpreadsheet icon when connected
  - Shows spinner during export
  - Disabled during export
  - Shows connect button when Google configured but not connected
  - Hidden when Google not configured
- Export opens spreadsheet in new tab automatically
- Uses popup blocker workaround (opens blank tab before API call)

## Files Changed

| File | Change |
|------|--------|
| `includes/class-rest-google-sheets.php` | Added export_fees endpoint, fetch_fee_data, build_fee_spreadsheet_data methods |
| `src/api/client.js` | Added exportFeesToSheets API method |
| `src/pages/Contributie/ContributieList.jsx` | Added export button, handlers, sheetsStatus query |
| `style.css` | Version bump to 8.4.0 |
| `package.json` | Version bump to 8.4.0 |
| `CHANGELOG.md` | Added 8.4.0 entry |

## Commits

| Hash | Message |
|------|---------|
| 2a9ee417 | feat(128-01): add Google Sheets export endpoint for fees |
| 602444e2 | feat(128-01): add export button to Contributie page |
| a6c73337 | chore: bump version to 8.4.0 |

## Verification

- [x] PHP syntax valid (`php -l` passes)
- [x] ESLint passes (no new errors)
- [x] npm run build succeeds
- [x] Deployed to production

## Success Criteria Met

- [x] Export button on Contributie page (EXP-01 requirement)
- [x] Creates Google Sheets with all fee data
- [x] Uses existing Google OAuth connection (no new auth flow)
- [x] Spreadsheet auto-opens in new tab
- [x] Euro currency formatting via nl_NL locale
- [x] Totals row includes Base Fee and Final Amount sums

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

Phase 128 (Google Sheets Export) is complete. No blockers or concerns for future phases.
