# Phase 128: Google Sheets Export - Research

**Researched:** 2026-02-01
**Domain:** Google Sheets API integration for fee data export
**Confidence:** HIGH

## Summary

This phase implements exporting fee data from the Contributie page to Google Sheets. The research confirms that Stadion already has a complete Google Sheets integration infrastructure including OAuth connection management, credential encryption, and an existing export endpoint for the People list. The implementation will follow the established pattern.

The existing `class-rest-google-sheets.php` provides a complete template for the export functionality with:
- Connection status checking
- Credential decryption and token refresh
- Spreadsheet creation with formatting (bold headers, frozen rows, auto-resize)
- Error handling and user feedback patterns

The Contributie page already has all the data needed via the existing `/rondo/v1/fees` REST endpoint which returns fee calculations including Nikki integration data.

**Primary recommendation:** Create a new `export-fees` endpoint following the `export-people` pattern, reusing all existing infrastructure. The frontend pattern from PeopleList.jsx can be directly adapted for ContributieList.jsx.

## Standard Stack

The established libraries/tools for this domain:

### Core (Already in Project)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Google\Client | vendor/google/apiclient | Google API authentication | Already integrated for Sheets/Calendar/Contacts |
| Google\Service\Sheets | vendor/google/apiclient-services | Sheets API operations | Already in use for People export |
| Stadion\Calendar\GoogleOAuth | includes/class-google-oauth.php | OAuth flow management | Handles token refresh, scope checking |
| Stadion\Sheets\GoogleSheetsConnection | includes/class-google-sheets-connection.php | Connection storage | Stores encrypted credentials per user |

### Supporting (Already Available)
| Library | Purpose | When Used |
|---------|---------|-----------|
| Stadion\Data\CredentialEncryption | Secure credential storage | Token storage/retrieval |
| Stadion\Fees\MembershipFees | Fee calculation service | Source data for export |
| lucide-react (FileSpreadsheet) | Export button icon | UI consistency |
| @tanstack/react-query | State management | Sheets connection status |

### No Additional Dependencies Required

All required libraries are already in the project and working in production.

## Architecture Patterns

### Backend: Follow Existing Export Pattern

The `export_people` method in `class-rest-google-sheets.php` provides the exact pattern:

```php
// 1. Check connection
if ( ! GoogleSheetsConnection::is_connected( $user_id ) ) {
    return new \WP_Error( 'not_connected', '...', [ 'status' => 400 ] );
}

// 2. Get credentials and refresh token if needed
$connection = GoogleSheetsConnection::get_connection( $user_id );
$access_token = GoogleOAuth::get_access_token( array_merge( $connection, [ 'user_id' => $user_id ] ) );

// 3. Create Sheets client
$client = new \Google\Client();
$client->setClientId( GOOGLE_OAUTH_CLIENT_ID );
$client->setClientSecret( GOOGLE_OAUTH_CLIENT_SECRET );
$client->setAccessToken( [ 'access_token' => $access_token ] );
$sheets_service = new \Google\Service\Sheets( $client );

// 4. Fetch data (use existing fee list logic)
// 5. Build spreadsheet data
// 6. Create spreadsheet with formatting
// 7. Return URL
```

### Frontend: Follow PeopleList Export Pattern

From `PeopleList.jsx` lines 1043-1102:

```javascript
// 1. Query Sheets connection status
const { data: sheetsStatus } = useQuery({
  queryKey: ['google-sheets-status'],
  queryFn: async () => {
    const response = await prmApi.getSheetsStatus();
    return response.data;
  },
});

// 2. Handle export (pre-open window for popup blocker)
const handleExportToSheets = async () => {
  setIsExporting(true);
  const newWindow = window.open('about:blank', '_blank');

  try {
    const response = await prmApi.exportFeesToSheets({ sortField, sortOrder });
    if (response.data.spreadsheet_url && newWindow) {
      newWindow.location.href = response.data.spreadsheet_url;
    }
  } catch (error) {
    if (newWindow) newWindow.close();
    // Show error toast
  } finally {
    setIsExporting(false);
  }
};

// 3. Button rendering (connected vs not connected)
{sheetsStatus?.connected ? (
  <button onClick={handleExportToSheets} disabled={isExporting}>
    {isExporting ? <Spinner /> : <FileSpreadsheet />}
  </button>
) : sheetsStatus?.google_configured ? (
  <button onClick={handleConnectSheets} title="Connect to export">
    <FileSpreadsheet />
  </button>
) : null}
```

### Spreadsheet Structure

Per CONTEXT.md decisions:

```
Spreadsheet Title: "Contributie [Season] - [Date]"
Sheet Tab Name: "Contributie"
Locale: nl_NL (for Euro formatting)

Columns (fixed, in order):
1. Naam           - Text
2. Relatiecode    - Text (KNVB ID from ACF)
3. Categorie      - Text (mini/pupil/junior/senior/recreant/donateur)
4. Leeftijdsgroep - Text
5. Basis          - Currency (EUR)
6. Gezinskorting  - Percentage (as decimal like 0.25)
7. Pro-rata %     - Percentage
8. Bedrag         - Currency (EUR) - final_fee
9. Nikki Total    - Currency (EUR with decimals)
10. Saldo         - Currency (EUR with decimals)

Totals Row (footer):
- Column 1: "Totaal"
- Column 5: Sum of Basis
- Column 8: Sum of Bedrag
- Columns 9-10: Not summed (per CONTEXT.md decision)
```

### Google Sheets API Formatting

```php
// 1. Set spreadsheet locale for Euro formatting
$spreadsheet = new \Google\Service\Sheets\Spreadsheet([
    'properties' => [
        'title'  => $title,
        'locale' => 'nl_NL', // Dutch locale for Euro currency
    ],
    'sheets' => [[
        'properties' => ['title' => 'Contributie'],
    ]],
]);

// 2. Currency format for columns 5, 8, 9, 10
$currency_format = [
    'repeatCell' => [
        'range' => [
            'sheetId' => $sheet_id,
            'startRowIndex' => 1, // Skip header
            'startColumnIndex' => 4, // Column E (Basis)
            'endColumnIndex' => 5,
        ],
        'cell' => [
            'userEnteredFormat' => [
                'numberFormat' => [
                    'type' => 'CURRENCY',
                    'pattern' => '[$EUR]#,##0',
                ],
            ],
        ],
        'fields' => 'userEnteredFormat.numberFormat',
    ],
];

// 3. Percentage format for columns 6, 7
$percentage_format = [
    'repeatCell' => [
        'range' => [
            'sheetId' => $sheet_id,
            'startRowIndex' => 1,
            'startColumnIndex' => 5, // Column F (Gezinskorting)
            'endColumnIndex' => 7,   // Through column G
        ],
        'cell' => [
            'userEnteredFormat' => [
                'numberFormat' => [
                    'type' => 'PERCENT',
                    'pattern' => '0%',
                ],
            ],
        ],
        'fields' => 'userEnteredFormat.numberFormat',
    ],
];
```

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth token management | Custom token refresh logic | `GoogleOAuth::get_access_token()` | Handles refresh, error states, credential updates |
| Credential encryption | Custom encryption | `CredentialEncryption` class | Already handles encryption/decryption securely |
| Connection status checking | Manual meta queries | `GoogleSheetsConnection::is_connected()` | Centralized, consistent checks |
| Fee data fetching | New data query | Existing `MembershipFees` service | All calculation logic already implemented |
| Window popup blocking | Custom solutions | Pre-open blank window pattern | Proven pattern from People export |
| Export button state | Custom loading state | Existing pattern with `isExporting` state | Consistent UX |

**Key insight:** The entire export infrastructure exists. This phase is about adding a new endpoint that follows the established pattern, not building new infrastructure.

## Common Pitfalls

### Pitfall 1: Popup Blocker
**What goes wrong:** Browser blocks new window opening after async call
**Why it happens:** Window.open() after await is blocked by browsers
**How to avoid:** Open blank window BEFORE async call, then navigate it
**Warning signs:** Export succeeds but no window opens

### Pitfall 2: Token Expiration During Export
**What goes wrong:** Token expires mid-export, causing API error
**Why it happens:** Access tokens have 1-hour lifetime
**How to avoid:** Use `GoogleOAuth::get_access_token()` which handles refresh automatically
**Warning signs:** Intermittent "invalid_token" errors

### Pitfall 3: Missing Nikki Data Display
**What goes wrong:** Nikki columns show empty when data exists
**Why it happens:** Null vs empty string confusion in meta values
**How to avoid:** Check for `!== ''` not just truthiness, preserve null for "no data"
**Warning signs:** All Nikki columns show "-" even for members with data

### Pitfall 4: Locale Not Applied
**What goes wrong:** Currency shows as USD or wrong format
**Why it happens:** Locale property not set during spreadsheet creation
**How to avoid:** Set `locale` in SpreadsheetProperties during create
**Warning signs:** Numbers display with $ instead of Euro

### Pitfall 5: Sort Order Not Respected
**What goes wrong:** Export doesn't match current UI sort order
**Why it happens:** Backend fetches fresh data without considering client sort
**How to avoid:** Pass current sortField and sortOrder to export endpoint
**Warning signs:** User expects category sort but gets alphabetical

## Code Examples

### Backend: Export Endpoint Registration

```php
// Source: Follows pattern from class-rest-google-sheets.php line 81-106
register_rest_route(
    'rondo/v1',
    '/google-sheets/export-fees',
    [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'export_fees' ],
        'permission_callback' => [ $this, 'check_user_approved' ],
        'args'                => [
            'sort_field' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'category',
            ],
            'sort_order' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'asc',
                'enum'     => [ 'asc', 'desc' ],
            ],
        ],
    ]
);
```

### Backend: Data Building

```php
// Source: Adapted from get_fee_list in class-rest-api.php
private function fetch_fee_data( string $sort_field, string $sort_order ): array {
    $fees = new \Stadion\Fees\MembershipFees();
    $season = $fees->get_season_key();
    $nikki_year = substr( $season, 0, 4 );

    // Use same query logic as REST endpoint
    $query = new \WP_Query([
        'post_type'      => 'person',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    $results = [];
    foreach ( $query->posts as $person ) {
        $fee_data = $fees->get_fee_for_person_cached( $person->ID, $season );
        if ( $fee_data === null ) continue;

        $results[] = [
            'name'             => trim( get_field( 'first_name', $person->ID ) . ' ' . get_field( 'last_name', $person->ID ) ),
            'relatiecode'      => get_field( 'relatiecode', $person->ID ) ?: '',
            'category'         => $fee_data['category'],
            'leeftijdsgroep'   => $fee_data['leeftijdsgroep'] ?: '',
            'base_fee'         => $fee_data['base_fee'],
            'family_discount'  => $fee_data['family_discount_rate'],
            'prorata'          => $fee_data['prorata_percentage'],
            'final_fee'        => $fee_data['final_fee'],
            'nikki_total'      => get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_total', true ),
            'nikki_saldo'      => get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_saldo', true ),
        ];
    }

    // Apply sorting
    $this->sort_fee_data( $results, $sort_field, $sort_order );

    return [ 'season' => $season, 'members' => $results ];
}
```

### Frontend: Button in Header Row

```jsx
// Source: Follows PeopleList.jsx pattern
// Location: ContributieList.jsx header section

// In component:
const [isExporting, setIsExporting] = useState(false);
const { data: sheetsStatus } = useQuery({
  queryKey: ['google-sheets-status'],
  queryFn: async () => {
    const response = await prmApi.getSheetsStatus();
    return response.data;
  },
});

// In JSX (header row with season indicator):
<div className="flex items-center justify-between">
  <div className="text-sm text-gray-500 dark:text-gray-400">
    Seizoen: <span className="font-medium">{data?.season}</span>
    <span className="ml-4">{sortedMembers.length} leden</span>
  </div>
  <div className="flex items-center gap-3">
    <div className="text-sm text-gray-500 dark:text-gray-400">
      Totaal: <span className="font-medium">{formatCurrency(totals.finalFee)}</span>
    </div>
    {/* Export Button */}
    {sheetsStatus?.connected ? (
      <button
        onClick={handleExportToSheets}
        disabled={isExporting}
        className="btn-secondary"
        title="Exporteren naar Google Sheets"
      >
        {isExporting ? (
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-current" />
        ) : (
          <FileSpreadsheet className="w-4 h-4" />
        )}
      </button>
    ) : sheetsStatus?.google_configured ? (
      <button
        onClick={handleConnectSheets}
        className="btn-secondary text-gray-400"
        title="Verbinden met Google Sheets om te exporteren"
      >
        <FileSpreadsheet className="w-4 h-4" />
      </button>
    ) : null}
  </div>
</div>
```

### API Client Method

```javascript
// Source: Add to src/api/client.js prmApi object
exportFeesToSheets: (data) => api.post('/rondo/v1/google-sheets/export-fees', data),
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Google Sheets API v3 | Google Sheets API v4 | 2017 | v4 required, v3 deprecated |
| Direct token storage | Encrypted credential storage | Already implemented | Security compliant |
| Manual OAuth refresh | `GoogleOAuth::get_access_token()` | Already implemented | Automatic handling |

**Already up to date:**
- Google API PHP client (vendor/google/apiclient) - Already in project
- OAuth 2.0 flow with PKCE - Already implemented
- Encrypted credential storage - Already implemented

## Open Questions

None identified. The implementation path is clear:
1. All infrastructure exists
2. CONTEXT.md provides all design decisions
3. Patterns are established and working in production

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-google-sheets.php` - Complete export pattern (lines 287-464)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-google-sheets-connection.php` - Connection management
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-google-oauth.php` - OAuth and token refresh
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PeopleList.jsx` - Frontend export pattern (lines 1043-1102)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Contributie/ContributieList.jsx` - Target component
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` - Fee list endpoint (lines 2570-2655)

### Secondary (MEDIUM confidence)
- [Google Sheets API - SpreadsheetProperties](https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets) - Locale settings
- [Google Sheets API - Formats](https://developers.google.com/sheets/api/guides/formats) - Number/currency formatting

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in project and working
- Architecture: HIGH - Following existing, proven patterns
- Pitfalls: HIGH - Based on existing implementation and documented issues

**Research date:** 2026-02-01
**Valid until:** 2026-03-01 (30 days - stable infrastructure)
