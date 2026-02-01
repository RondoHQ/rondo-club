# Phase 128: Google Sheets Export - Context

**Gathered:** 2026-02-01
**Updated:** 2026-02-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Export fee data from the Contributie page to Google Sheets. Uses the existing Google Sheets OAuth connection infrastructure. Creates a new spreadsheet with formatted fee data for viewing/sharing.

</domain>

<decisions>
## Implementation Decisions

### Export trigger
- Button in header row, next to season indicator and totals (consistent with People page pattern)
- No confirmation dialog — direct export on click (creates new sheet, not destructive)
- When not connected to Google Sheets: show disabled button with tooltip explaining connection needed
- Export respects current sort order (what you see is what you get)

### Column selection
- Fixed column set (no picker):
  1. Name (Naam)
  2. KNVB ID (relatiecode)
  3. Category (Categorie)
  4. Leeftijdsgroep
  5. Base Fee (Basis)
  6. Family Discount (Gezinskorting)
  7. Pro-rata %
  8. Final Amount (Bedrag)
  9. Nikki Total
  10. Saldo
- Monetary values: raw numbers (not strings) with Google Sheets CURRENCY formatting
- Spreadsheet locale set to European (nl_NL) for proper Euro formatting
- Include totals row at bottom (sums for Base Fee and Final Amount only — not Nikki columns)
- Nikki columns: plain numbers, no conditional formatting (unlike UI which has red/green for saldo)

### Sheet naming
- Spreadsheet title: "Contributie [Season] - [Date]" (e.g., "Contributie 2025-2026 - 2026-02-01")
- Sheet tab name: "Contributie"

### Post-export behavior
- Success: Open spreadsheet in new browser tab automatically
- Loading: Button shows spinner, disabled during export
- Failure: Toast notification with error message, button returns to normal state

### Claude's Discretion
- Button icon choice (likely existing sheet/export icon)
- Exact tooltip wording for disabled state
- Column header translations (Dutch labels matching page)
- Exact formatting requests for Google Sheets API

</decisions>

<specifics>
## Specific Ideas

- Use existing `class-rest-google-sheets.php` pattern — create new endpoint for fee export or extend existing
- Follow same formatting pattern as People export: bold header, frozen row, auto-resize columns
- Locale setting reference: https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets
- Currency format reference: https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets/cells#numberformattype

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 128-google-sheets-export*
*Context gathered: 2026-02-01*
*Context updated: 2026-02-01 (added Nikki columns after Phase 127.1)*
