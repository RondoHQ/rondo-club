# Phase 131: Forecast Export - Research

**Researched:** 2026-02-02
**Domain:** Google Sheets export for forecast fee data
**Confidence:** HIGH

## Summary

This phase extends the existing Google Sheets export functionality to support forecast mode. The research focused on understanding the existing implementation patterns in this codebase, as this is a feature extension rather than new technology adoption.

The existing `export_fees` endpoint in `class-rest-google-sheets.php` already has a well-established pattern for exporting fee data to Google Sheets. The forecast mode was recently added to the fee list endpoint (`/stadion/v1/fees`) in Phase 130, which returns data with 100% pro-rata and excludes Nikki billing columns. The frontend in `ContributieList.jsx` already toggles between current and forecast modes, hiding Nikki columns when `isForecast` is true.

The implementation requires: (1) adding a `forecast` parameter to the export endpoint, (2) adjusting the data fetching to use forecast mode, (3) modifying the spreadsheet structure to exclude Nikki columns, and (4) updating the sheet title to include "(Prognose)" indicator.

**Primary recommendation:** Extend the existing `export_fees` method with a `forecast` boolean parameter and conditionally adjust column count, formatting, and title based on mode.

## Standard Stack

No new libraries needed. This feature uses the existing stack already in place.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Google Sheets API | v4 | Spreadsheet creation/formatting | Already used in `export_fees` |
| google/apiclient | (installed) | PHP client for Google APIs | Already integrated |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| TanStack Query | 5.x | Frontend data fetching | Already used for `useFeeList` hook |
| Axios | (installed) | HTTP client | Already configured in `api/client.js` |

## Architecture Patterns

### Pattern 1: Parameter-Based Export Mode

The existing `export_fees` endpoint accepts `sort_field` and `sort_order` parameters. The same pattern should be used for the `forecast` flag.

**What:** Add boolean `forecast` parameter to `export-fees` endpoint
**When to use:** When frontend calls export with forecast mode active
**Example:**
```php
// Source: Existing pattern in class-rest-google-sheets.php
'args' => [
    'forecast' => [
        'required' => false,
        'type'     => 'boolean',
        'default'  => false,
    ],
    // ... existing sort_field, sort_order
],
```

### Pattern 2: Conditional Column Structure

The current `build_fee_spreadsheet_data` method builds a fixed 10-column structure. Forecast mode should build an 8-column structure (excluding Nikki Total and Saldo).

**What:** Conditionally build spreadsheet data based on mode
**When to use:** In `export_fees` and `build_fee_spreadsheet_data` methods
**Example:**
```php
// Source: Codebase pattern - ContributieList.jsx line 150-173
// Frontend already conditionally renders Nikki columns:
{!isForecast && (
    <>
        <td>Nikki Total</td>
        <td>Saldo</td>
    </>
)}

// Backend equivalent in build_fee_spreadsheet_data:
if (!$forecast) {
    $data['nikki_total'] = $member['nikki_total'];
    $data['nikki_saldo'] = $member['nikki_saldo'];
}
```

### Pattern 3: Forecast Fee Calculation

The existing `/stadion/v1/fees` endpoint already handles forecast mode by:
1. Using next season key
2. Setting 100% pro-rata for all members
3. Omitting Nikki data

**What:** Reuse existing `fetch_fee_data` method with forecast parameter
**When to use:** When fetching data for export
**Example:**
```php
// Source: class-rest-api.php line 2645-2657
if ($forecast) {
    $season = $fees->get_next_season_key();
} else {
    $season = $fees->get_season_key();
}
```

### Pattern 4: Dynamic Sheet Title

The current title format is "Contributie {season} - {date}". Forecast should include "(Prognose)" indicator.

**What:** Modify title based on export mode
**When to use:** When creating spreadsheet
**Example:**
```php
// Current: "Contributie 2025-2026 - 2026-02-02"
// Forecast: "Contributie 2026-2027 (Prognose) - 2026-02-02"
$title = 'Contributie ' . $season;
if ($forecast) {
    $title .= ' (Prognose)';
}
$title .= ' - ' . gmdate('Y-m-d');
```

### Anti-Patterns to Avoid

- **Duplicating the entire export method:** Don't create a separate `export_fees_forecast` method. Extend the existing method with a parameter.
- **Hardcoding column indices:** Use dynamic column count calculation based on `$forecast` flag.
- **Forgetting to update totals row:** The totals row formatting should also exclude Nikki columns in forecast mode.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Fee calculation | Custom forecast logic | Existing `MembershipFees` class methods | Already handles forecast via `calculate_fee_with_family_discount` |
| Column formatting | Manual index math | `$num_columns` variable based on mode | Already used in current implementation |
| OAuth/token handling | Custom token refresh | Existing `GoogleOAuth::get_access_token` | Handles token refresh automatically |

**Key insight:** The infrastructure is already in place. This is a matter of passing the `forecast` flag through the stack and adjusting column structure conditionally.

## Common Pitfalls

### Pitfall 1: Hardcoded Column Count
**What goes wrong:** The current implementation uses `$num_columns = 10` hardcoded. Forecast mode needs 8 columns.
**Why it happens:** Easy to overlook when adding conditional logic.
**How to avoid:** Calculate `$num_columns` dynamically: `$forecast ? 8 : 10`
**Warning signs:** Export produces malformed spreadsheet with empty columns or misaligned formatting.

### Pitfall 2: Formatting Request Mismatch
**What goes wrong:** Currency/percentage formatting requests reference wrong column indices in forecast mode.
**Why it happens:** Nikki columns are indices 8-9, which shift in forecast mode.
**How to avoid:** Only add Nikki column formatting requests when `!$forecast`
**Warning signs:** Formatting applied to wrong columns (e.g., currency format on text).

### Pitfall 3: Frontend Not Passing Forecast Parameter
**What goes wrong:** Export always uses current season even when viewing forecast.
**Why it happens:** Forgetting to include `forecast` in the API call.
**How to avoid:** Pass `forecast: isForecast` in `handleExportToSheets`
**Warning signs:** Exported sheet title shows current season when forecast is displayed.

### Pitfall 4: Totals Row Column Span
**What goes wrong:** Totals row formatting references wrong column indices.
**Why it happens:** `colSpan` and formatting hardcoded for 10 columns.
**How to avoid:** Use `$num_columns` for totals row range as well.
**Warning signs:** Totals row has wrong formatting or spans wrong cells.

## Code Examples

### Backend: Modified fetch_fee_data Signature

```php
// Source: Extend existing method in class-rest-google-sheets.php
private function fetch_fee_data(string $sort_field, string $sort_order, bool $forecast = false): array {
    $fees = new \Stadion\Fees\MembershipFees();

    // Get appropriate season
    $season = $forecast
        ? $fees->get_next_season_key()
        : $fees->get_season_key();

    $nikki_year = substr($season, 0, 4);

    // ... existing query code ...

    foreach ($query->posts as $person) {
        if ($forecast) {
            // Calculate with 100% pro-rata
            $fee_data = $fees->calculate_fee_with_family_discount($person->ID, $season);
            if ($fee_data === null) continue;
            $fee_data['prorata_percentage'] = 1.0;
            $fee_data['final_fee'] = $fee_data['fee_after_discount'] ?? $fee_data['final_fee'];
        } else {
            // Use cached calculation
            $fee_data = $fees->get_fee_for_person_cached($person->ID, $season);
            if ($fee_data === null) continue;
        }

        $result = [
            'id' => $person->ID,
            'name' => $name,
            // ... other fields ...
        ];

        // Only include Nikki data for current season
        if (!$forecast) {
            $result['nikki_total'] = ...;
            $result['nikki_saldo'] = ...;
        }

        $results[] = $result;
    }

    return [
        'season' => $season,
        'forecast' => $forecast,
        'members' => $results,
    ];
}
```

### Backend: Modified build_fee_spreadsheet_data

```php
// Source: Extend existing method in class-rest-google-sheets.php
private function build_fee_spreadsheet_data(array $fee_data, bool $forecast = false): array {
    $data = [];

    // Header row - conditional columns
    $headers = [
        'Naam', 'Relatiecode', 'Categorie', 'Leeftijdsgroep',
        'Basis', 'Gezinskorting', 'Pro-rata %', 'Bedrag',
    ];

    if (!$forecast) {
        $headers[] = 'Nikki Total';
        $headers[] = 'Saldo';
    }

    $data[] = $headers;

    // Data rows
    foreach ($fee_data['members'] as $member) {
        $row = [
            $member['name'],
            $member['relatiecode'] ?: '',
            $category_labels[$member['category']] ?? $member['category'],
            $member['leeftijdsgroep'] ?: '',
            $member['base_fee'],
            $member['family_discount_rate'],
            $member['prorata_percentage'],
            $member['final_fee'],
        ];

        if (!$forecast) {
            $row[] = $member['nikki_total'];
            $row[] = $member['nikki_saldo'];
        }

        $data[] = $row;
    }

    // Totals row
    $totals_row = ['Totaal', '', '', '', $total_base_fee, '', '', $total_final_fee];
    if (!$forecast) {
        $totals_row[] = '';  // Nikki Total - no sum
        $totals_row[] = '';  // Saldo - no sum
    }
    $data[] = $totals_row;

    return $data;
}
```

### Frontend: Export Call with Forecast Parameter

```javascript
// Source: Extend existing handler in ContributieList.jsx
const handleExportToSheets = async () => {
    if (isExporting) return;
    setIsExporting(true);

    const newWindow = window.open('about:blank', '_blank');

    try {
        const response = await prmApi.exportFeesToSheets({
            sort_field: sortField,
            sort_order: sortOrder,
            forecast: isForecast,  // Add this parameter
        });

        if (response.data.spreadsheet_url && newWindow) {
            newWindow.location.href = response.data.spreadsheet_url;
        }
    } catch (error) {
        // ... existing error handling ...
    } finally {
        setIsExporting(false);
    }
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| N/A (new feature) | Extend existing endpoint | Phase 131 | Minimal code change |

**Deprecated/outdated:** None - this is extending existing functionality.

## Open Questions

None - the implementation path is clear based on existing patterns in the codebase.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-rest-google-sheets.php` - Existing export implementation (lines 108-131, 497-828)
- `/Users/joostdevalk/Code/stadion/includes/class-rest-api.php` - Forecast fee calculation (lines 2636-2758)
- `/Users/joostdevalk/Code/stadion/src/pages/Contributie/ContributieList.jsx` - Frontend forecast handling (lines 77-176, 239-263)
- `/Users/joostdevalk/Code/stadion/src/api/client.js` - API client methods (line 303)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No new libraries, all existing
- Architecture: HIGH - Clear patterns from existing implementation
- Pitfalls: HIGH - Directly derived from code analysis

**Research date:** 2026-02-02
**Valid until:** Indefinite - implementation patterns are stable
