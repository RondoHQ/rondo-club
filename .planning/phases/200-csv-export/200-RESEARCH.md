# Phase 200: CSV Export - Research

**Researched:** 2026-02-20
**Domain:** Frontend CSV generation, client-side file download
**Confidence:** HIGH

## Summary

Phase 200 adds CSV download buttons to three existing list pages: People (PeopleList.jsx), VOG (VOGList.jsx), and Contributie (ContributieList.jsx). The goal is a simple, dependency-free alternative to the Google Sheets export that users removed in Phase 199. The data for all three pages is already fetched client-side by TanStack Query hooks — the CSV can be generated entirely in the browser from already-loaded data, with no new backend endpoints required.

The existing Google Sheets export pattern (found in PeopleList.jsx, VOGList.jsx, ContributieList.jsx) establishes the UI pattern: a button in the top-right area of each list page. The new CSV button will mirror this placement and styling. The existing `prmApi.exportPeopleToSheets` / `prmApi.exportFeesToSheets` API calls and the entire `class-rest-google-sheets.php` server-side data assembly are NOT needed for CSV — all data is already in the React component's state via TanStack Query.

The correct approach is a pure client-side CSV utility function: iterate over the already-loaded data, serialize to CSV string, trigger a `<a download>` click. No library needed — the Browser's native `Blob` + `URL.createObjectURL` API handles this perfectly. Dutch locale number formatting for currency/percentages should use plain string formatting, not `Intl.NumberFormat` (which produces `€` symbols that confuse Excel's number parser). Comma (`,`) as decimal separator and semicolon (`;`) as column delimiter is the Dutch CSV convention that Excel recognizes natively.

**Primary recommendation:** Implement pure client-side CSV generation using a shared `src/utils/csvExport.js` utility module, triggered by a download button on each of the three list pages. No new API endpoints required.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Browser Blob API | Native | Create downloadable file from string | No dependency, universally supported |
| Browser URL.createObjectURL | Native | Generate temporary download URL | Standard browser download pattern |
| date-fns (already installed) | ^3.2.0 | Format dates in exported data | Already in package.json |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `lucide-react` (already installed) | ^0.309.0 | `Download` icon for button | Already used throughout the codebase |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Native Blob | `papaparse` library | No reason to add a dependency for a task this simple; hand-rolling CSV for flat data is 10 lines |
| Native Blob | Server-side PHP CSV endpoint | Requires new API route, authentication, server round-trip; all data is already in the browser |
| Semicolon delimiter | Comma delimiter | Excel in Dutch locale uses semicolon as CSV delimiter; comma would split Dutch numbers like "1.234,56" incorrectly |

**Installation:** No new packages required.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── utils/
│   └── csvExport.js         # NEW: shared CSV utility (downloadCsv, buildRow, escapeCell)
├── pages/
│   ├── People/
│   │   └── PeopleList.jsx   # MODIFIED: add CSV button + handleExportCsv()
│   ├── VOG/
│   │   └── VOGList.jsx      # MODIFIED: add CSV button + handleExportCsv()
│   └── Contributie/
│       └── ContributieList.jsx  # MODIFIED: add CSV button + handleExportCsv()
```

### Pattern 1: Shared CSV Utility
**What:** A single utility module with functions for CSV serialization and download triggering.
**When to use:** Any time data from the page needs to be downloaded as CSV.
**Example:**
```javascript
// src/utils/csvExport.js

/**
 * Escape a CSV cell value.
 * Wraps in double-quotes if the value contains semicolons, double-quotes, or newlines.
 * Escapes existing double-quotes by doubling them (RFC 4180).
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function escapeCell(value) {
  if (value === null || value === undefined) return '';
  const str = String(value);
  if (str.includes(';') || str.includes('"') || str.includes('\n')) {
    return '"' + str.replace(/"/g, '""') + '"';
  }
  return str;
}

/**
 * Convert a 2D array of values into a CSV string using semicolon delimiter.
 * Uses semicolons for Dutch Excel compatibility.
 *
 * @param {Array<Array<string|number|null>>} rows - Array of rows, each row is an array of cells
 * @returns {string} CSV string
 */
export function buildCsv(rows) {
  return rows.map(row => row.map(escapeCell).join(';')).join('\r\n');
}

/**
 * Trigger a browser file download for a CSV string.
 * Uses BOM (Byte Order Mark) prefix for correct UTF-8 detection in Excel.
 *
 * @param {string} csvString - The CSV content
 * @param {string} filename - The filename for the download (include .csv extension)
 */
export function downloadCsv(csvString, filename) {
  // BOM ensures Excel opens UTF-8 correctly without asking about encoding
  const BOM = '\uFEFF';
  const blob = new Blob([BOM + csvString], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
```

### Pattern 2: Page-Level Export Handler
**What:** Each list page defines a `handleExportCsv()` function that maps its already-loaded data to rows and calls `downloadCsv`.
**When to use:** Inside each list page component, using data already fetched by TanStack Query.
**Example (People page):**
```javascript
// In PeopleList.jsx
import { buildCsv, downloadCsv } from '@/utils/csvExport';
import { format } from '@/utils/dateFormat';

const handleExportCsv = () => {
  const headers = ['Naam', 'Voornaam', 'Achternaam', 'Email', 'Telefoon', 'Team'];
  const rows = people.map(person => {
    const email = getFirstContactByType(person, 'email') || '';
    const phone = getFirstPhone(person) || '';
    const teamName = personTeamMap[person.id] || '';
    const fullName = [person.first_name, person.infix, person.last_name].filter(Boolean).join(' ');
    return [fullName, person.first_name || '', person.last_name || '', email, phone, teamName];
  });
  const csv = buildCsv([headers, ...rows]);
  const date = format(new Date(), 'yyyy-MM-dd');
  downloadCsv(csv, `leden-${date}.csv`);
};
```

### Pattern 3: Button Placement
**What:** The CSV download button goes in the same top-right area where the Google Sheets button was. It uses `btn-secondary` class and the `Download` icon from lucide-react.
**When to use:** Top-right action area of each list page.
**Example:**
```jsx
import { Download } from 'lucide-react';

<button
  onClick={handleExportCsv}
  className="btn-secondary"
  title="Downloaden als CSV"
>
  <Download className="w-4 h-4" />
</button>
```

### Anti-Patterns to Avoid
- **Fetching data from the API again for export:** All three pages already have their data in memory via TanStack Query. There is no reason to make a new API call.
- **Using `Intl.NumberFormat` for CSV currency values:** This produces `€ 1.234,56` which Excel cannot parse as a number. Use raw numbers (e.g., `123.45`) or a plain string format (`"123,45"`) in CSV.
- **Using comma as delimiter:** Dutch Windows Excel uses semicolon as CSV delimiter. A comma-delimited file will not open correctly in Excel without a manual import dialog.
- **Fetching all pages of data:** The People list is paginated (100 per page). The CSV should export the CURRENT PAGE only (what the user sees), not all pages. This is consistent with how the Google Sheets export worked — it sent the current filters but the UI showed page 100 at a time. (See Open Question #1.)
- **Library dependencies for simple CSV:** papaparse, xlsx, etc. are overkill. The data is already flat (no nested structures that need special handling).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| UTF-8 BOM for Excel | Custom encoding logic | BOM prefix `\uFEFF` in Blob | One character solves Excel encoding detection |
| File download | Form POST, server redirect | `URL.createObjectURL` + `<a download>` | Standard browser pattern, works offline |

**Key insight:** The hardest part of CSV export in this codebase is not the CSV generation — it is identifying which data to include and mapping it from the existing component data structures. The utility functions are trivial; the page-level mapping is where judgment is required.

## Common Pitfalls

### Pitfall 1: Dutch Excel Semicolon Delimiter
**What goes wrong:** Exporting with comma delimiter — `Name,Email,Amount` — causes Dutch Excel to treat the whole line as one cell because Excel in Dutch locale uses `,` as the decimal separator for numbers.
**Why it happens:** RFC 4180 uses comma, but Excel respects the system locale's list separator.
**How to avoid:** Use semicolons as the column delimiter: `Name;Email;Amount`.
**Warning signs:** If testing in Excel produces a single column with all data in column A.

### Pitfall 2: Missing UTF-8 BOM
**What goes wrong:** Dutch characters (ë, ij, é, etc.) appear as garbled text when opening in Excel.
**Why it happens:** Excel defaults to the system code page (Windows-1252) when no BOM is present.
**How to avoid:** Prepend `\uFEFF` (UTF-8 BOM) to the Blob content.
**Warning signs:** Characters like "ë" appear as "Ã«".

### Pitfall 3: Pagination Scope
**What goes wrong:** Exporting only the 100 visible items when the user expects all filtered results; or conversely, silently exporting 1000+ records including unfetched pages.
**Why it happens:** People list uses pagination (100 per page). TanStack Query only holds the current page in `people`.
**How to avoid:** The export should clearly export only the current view (what is visible). The button title/tooltip should say "Exporteer huidige pagina" or similar if pagination is active, to set expectations.
**Warning signs:** User reports missing data in exports.

### Pitfall 4: Infix in Name Column
**What goes wrong:** Names like "van den Berg" get split: voornaam "Jan", infix "van den", achternaam "Berg" but full name export misses the infix.
**Why it happens:** People list uses `[person.first_name, person.infix, person.last_name].filter(Boolean).join(' ')` — this pattern must be replicated in the export.
**How to avoid:** Copy the exact pattern from `PersonListRow` rendering: `[person.first_name, person.infix, person.last_name].filter(Boolean).join(' ')`.
**Warning signs:** Names with tussenvoegsels appear incomplete in export.

### Pitfall 5: Contributie Data Shape Mismatch
**What goes wrong:** The `ContributieList` sorts data client-side and uses `sortedMembers`, not `data.members`. Exporting `data.members` would ignore the current sort and mismatch-only filter.
**Why it happens:** Contributie does all filtering and sorting client-side from the API response.
**How to avoid:** Export from `sortedMembers` (the already-filtered-and-sorted array), not from `data?.members`.
**Warning signs:** Export order does not match the displayed order.

### Pitfall 6: VOG Columns Include Dates as ISO Strings
**What goes wrong:** Date fields like `vog_email_sent_date`, `vog_justis_submitted_date` are stored as ISO datetime strings. Exporting the raw value gives `2025-09-01T14:23:00` instead of `2025-09-01`.
**Why it happens:** ACF stores datetime fields as full ISO strings.
**How to avoid:** Apply `format(new Date(value), 'yyyy-MM-dd')` before writing to CSV, matching what is displayed in the table.
**Warning signs:** Date columns in export look different from what the user sees on screen.

## Code Examples

### escapeCell — handles Dutch characters and quotes correctly
```javascript
// Source: RFC 4180 + in-codebase pattern
export function escapeCell(value) {
  if (value === null || value === undefined) return '';
  const str = String(value);
  // Need quoting: contains delimiter, quote, or newline
  if (str.includes(';') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
    return '"' + str.replace(/"/g, '""') + '"';
  }
  return str;
}
```

### Currency formatting for CSV (plain number, not formatted string)
```javascript
// For CSV: export raw number, not Intl.NumberFormat output
// Excel will format it as currency in the spreadsheet if needed
// e.g., member.final_fee = 145.5 => "145,5" in Dutch decimal, or just 145.5 as raw
// Safest: export raw float, Dutch Excel parses "145.5" fine as number with period decimal
row.push(member.final_fee);  // NOT formatCurrency(member.final_fee)
```

### VOG page export pattern
```javascript
const handleExportCsv = () => {
  const headers = ['Naam', 'KNVB ID', 'Email', 'Telefoon', 'Datum VOG', '1e email', 'Justis', 'Herinnering'];
  const rows = people.map(person => {
    const email = getFirstContactByType(person, 'email') || '';
    const phone = getFirstPhone(person) || '';
    const fullName = [person.first_name, person.infix, person.last_name].filter(Boolean).join(' ');
    const formatDate = (d) => d ? format(new Date(d), 'yyyy-MM-dd') : '';
    return [
      fullName,
      person.acf?.['knvb-id'] || '',
      email,
      phone,
      formatDate(person.acf?.['datum-vog']),
      formatDate(person.acf?.['vog_email_sent_date']),
      formatDate(person.acf?.['vog_justis_submitted_date']),
      formatDate(person.acf?.['vog_reminder_sent_date']),
    ];
  });
  const csv = buildCsv([headers, ...rows]);
  downloadCsv(csv, `vog-${format(new Date(), 'yyyy-MM-dd')}.csv`);
};
```

### Contributie export pattern
```javascript
const handleExportCsv = () => {
  const baseHeaders = ['Voornaam', 'Achternaam', 'Categorie', 'Leeftijdsgroep', 'Basis', 'Gezinskorting', 'Pro-rata', 'Bedrag'];
  const headers = showNikkiColumns ? [...baseHeaders, 'Nikki', 'Saldo'] : baseHeaders;
  const rows = sortedMembers.map(member => {
    const categoryLabel = data?.categories?.[member.category]?.label ?? member.category;
    const baseRow = [
      member.first_name,
      member.last_name,
      categoryLabel,
      member.leeftijdsgroep || '',
      member.base_fee,
      member.family_discount_rate,
      member.prorata_percentage,
      member.final_fee,
    ];
    if (showNikkiColumns) {
      baseRow.push(member.nikki_total ?? '');
      baseRow.push(member.nikki_saldo ?? '');
    }
    return baseRow;
  });
  const csv = buildCsv([headers, ...rows]);
  const season = data?.season || 'contributie';
  downloadCsv(csv, `contributie-${season}.csv`);
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Google Sheets export (server-side, OAuth) | CSV download (client-side, no auth) | Phase 199 removed Google Sheets UI | No Google account required, works immediately |
| `exportPeopleToSheets` API call | No API call (use already-loaded data) | Phase 200 | Faster, simpler, offline-capable |

**Deprecated/outdated:**
- Google Sheets export buttons: removed in Phase 199, replaced by this CSV download feature.
- `prmApi.getSheetsStatus` query in all three list pages: was used to conditionally show the Sheets export button. This query can also be removed from the three pages as part of this phase (the Sheets-connect button is also gone). However, the `getSheetsStatus` API and the Settings connections page may still use it, so remove only the query calls in the three list pages.

## Open Questions

1. **People list: export current page or all filtered results?**
   - What we know: `useFilteredPeople` fetches 100 items per page. The `people` array in state has at most 100 items. The Google Sheets export sent filters to the backend and fetched ALL matching records server-side.
   - What's unclear: Should the CSV button export only the current 100 visible results, or fetch all filtered results for export?
   - Recommendation: Export the current page only (what is visible). This is simpler, avoids a new API endpoint, and is consistent with the description "exports visible/filtered people." If all-pages export is needed, it can be added later. Add a note in the button tooltip if pagination is active.

2. **People list: which columns to include in CSV?**
   - What we know: PeopleList has configurable visible columns (user preference stored via `useListPreferences`). The display respects `visibleColumns` ordering.
   - What's unclear: Should the CSV export respect the user's visible column configuration, or export a fixed set?
   - Recommendation: Export a sensible fixed set (name, email, phone, team, and all currently visible custom columns) rather than making CSV depend on the complex column preferences system. The success criterion says "exports visible/filtered people" which refers to the rows, not necessarily the columns.

3. **Remove `getSheetsStatus` query from the three list pages?**
   - What we know: All three pages currently query `['google-sheets-status']` to conditionally show the Sheets export button. After Phase 199 removed the button UI, these queries are now dead code in the list pages.
   - What's unclear: The CONTEXT.md does not exist, so there is no locked decision on this.
   - Recommendation: Remove the `getSheetsStatus` query from PeopleList, VOGList, and ContributieList as part of this phase. The Settings connections page may still use it — verify before removing.

## Sources

### Primary (HIGH confidence)
- Codebase analysis — `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PeopleList.jsx` — UI patterns, data shape, existing export handler structure
- Codebase analysis — `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/VOG/VOGList.jsx` — VOG column definitions, data fields
- Codebase analysis — `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Contributie/ContributieList.jsx` — sortedMembers pattern, showNikkiColumns logic
- Codebase analysis — `/Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js` — API method inventory
- Codebase analysis — `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-google-sheets.php` — Prior export column definitions, fee data shape
- Codebase analysis — `/Users/joostdevalk/Code/rondo/rondo-club/src/utils/formatters.js` — Existing format utilities
- Codebase analysis — `/Users/joostdevalk/Code/rondo/rondo-club/package.json` — Installed dependencies (no CSV library present)
- MDN Browser APIs — Blob, URL.createObjectURL, `<a download>` — Standard browser download pattern

### Secondary (MEDIUM confidence)
- RFC 4180 — CSV format specification (semicolon delimiter is a de-facto Microsoft Excel extension to RFC 4180 for European locales)

### Tertiary (LOW confidence)
- Dutch Excel CSV convention (semicolon delimiter): common knowledge, verified by experience but no single authoritative source. Well-established community practice.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — No new library needed; existing code + browser APIs
- Architecture: HIGH — Direct mapping from existing data structures is fully visible in source
- Pitfalls: HIGH — Dutch locale CSV quirks are well-documented; data structure pitfalls confirmed by reading source code

**Research date:** 2026-02-20
**Valid until:** 2026-04-20 (stable domain, no fast-moving dependencies)
