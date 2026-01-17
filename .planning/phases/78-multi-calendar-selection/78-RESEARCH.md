# Phase 78: Multi-Calendar Selection - Research

**Researched:** 2026-01-17
**Domain:** Google Calendar multi-select, WordPress user meta, React checkbox UI
**Confidence:** HIGH

## Summary

This phase converts the existing single-calendar selection to multi-calendar selection for Google Calendar connections. The current implementation stores `calendar_id` as a single string; this needs to change to `calendar_ids` as an array. The sync logic needs to iterate through all selected calendars, and the UI needs to change from a dropdown to checkboxes.

The implementation is straightforward because the existing infrastructure is solid:
- Calendar list API already exists (`GET /calendar/connections/{id}/calendars`)
- Connection storage structure supports arbitrary fields
- Sync already tracks which calendar_id events came from via post meta
- UI already has the EditConnectionModal for changing calendar selection

**Primary recommendation:** Change `calendar_id` (string) to `calendar_ids` (array), add backward-compatible migration on read, iterate sync over array, replace dropdown with checkboxes.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | UI framework | Already in use |
| WordPress user_meta | Native | Array storage | Already stores connections as JSON |
| TanStack Query | 5.x | Data fetching | Already in use for calendar APIs |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Lucide React | Current | Icons | Already used throughout Settings |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Checkboxes | Multi-select dropdown | Checkboxes better UX for 5-10 items |
| Array field | Multiple connections | Array simpler, preserves single OAuth |

**Installation:**
No new packages needed - all dependencies already present.

## Architecture Patterns

### Current Data Structure (Connection)
```php
// Current single calendar storage
$connection = [
    'id'             => 'conn_abc123',
    'provider'       => 'google',
    'name'           => 'Google Calendar',
    'calendar_id'    => 'primary',  // Single string
    'calendar_name'  => 'Primary',  // Single string
    'credentials'    => '...encrypted...',
    'sync_enabled'   => true,
    // ... other fields
];
```

### Target Data Structure
```php
// Multi-calendar storage (backward compatible)
$connection = [
    'id'             => 'conn_abc123',
    'provider'       => 'google',
    'name'           => 'Google Calendar',
    'calendar_ids'   => ['primary', 'work@example.com', 'family@group.calendar.google.com'],
    'calendar_names' => [
        'primary' => 'Primary',
        'work@example.com' => 'Work Calendar',
        'family@group.calendar.google.com' => 'Family'
    ],
    // 'calendar_id' may still exist for backward compat reads
    'credentials'    => '...encrypted...',
    'sync_enabled'   => true,
    // ... other fields
];
```

### Pattern 1: Backward-Compatible Read
**What:** When reading connection, normalize to array format
**When to use:** All reads of connection data
**Example:**
```php
// In class-calendar-connections.php or sync code
function get_calendar_ids( array $connection ): array {
    // New format: calendar_ids array
    if ( ! empty( $connection['calendar_ids'] ) && is_array( $connection['calendar_ids'] ) ) {
        return $connection['calendar_ids'];
    }
    // Old format: single calendar_id
    if ( ! empty( $connection['calendar_id'] ) ) {
        return [ $connection['calendar_id'] ];
    }
    // Default: primary calendar
    return [ 'primary' ];
}
```

### Pattern 2: Multi-Calendar Sync Loop
**What:** Sync iterates through all selected calendars
**When to use:** In GoogleProvider::do_sync()
**Example:**
```php
// In class-google-calendar-provider.php
private static function do_sync( int $user_id, array $connection ): array {
    $calendar_ids = self::get_calendar_ids( $connection );

    $created = 0;
    $updated = 0;
    $total   = 0;

    foreach ( $calendar_ids as $calendar_id ) {
        // Existing sync logic, but with $calendar_id from loop
        $result = self::sync_single_calendar( $user_id, $connection, $calendar_id );
        $created += $result['created'];
        $updated += $result['updated'];
        $total   += $result['total'];
    }

    return compact( 'created', 'updated', 'total' );
}
```

### Pattern 3: Checkbox Selection UI
**What:** React checkbox list instead of dropdown
**When to use:** EditConnectionModal calendar selection
**Example:**
```jsx
// In EditConnectionModal
const [selectedCalendarIds, setSelectedCalendarIds] = useState(
    connection.calendar_ids || (connection.calendar_id ? [connection.calendar_id] : [])
);

const toggleCalendar = (calId) => {
    setSelectedCalendarIds(prev =>
        prev.includes(calId)
            ? prev.filter(id => id !== calId)
            : [...prev, calId]
    );
};

// Render
{calendars.map((cal) => (
    <label key={cal.id} className="flex items-center gap-2">
        <input
            type="checkbox"
            checked={selectedCalendarIds.includes(cal.id)}
            onChange={() => toggleCalendar(cal.id)}
        />
        {cal.name}{cal.primary ? ' (Primary)' : ''}
    </label>
))}
```

### Anti-Patterns to Avoid
- **Don't migrate data on save only:** Always normalize on read for backward compatibility
- **Don't require re-authentication:** Existing OAuth tokens work for all calendars in account
- **Don't break existing sync:** Events already stored with `_calendar_id` meta continue to work

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Checkbox styling | Custom checkbox | Tailwind checkbox classes | Consistent with existing UI |
| Array validation | Manual check | is_array() + filter | PHP native sufficient |
| Calendar count display | Complex logic | Simple array count | `count($calendar_ids)` |

**Key insight:** This is primarily a data structure change, not a complex feature. The infrastructure already exists.

## Common Pitfalls

### Pitfall 1: Event Duplication on Re-sync
**What goes wrong:** Same event synced multiple times if calendar overlap
**Why it happens:** Event might appear in multiple calendars (shared events)
**How to avoid:** Continue using `_event_uid` + `_connection_id` for deduplication - already works correctly. The `_calendar_id` stored per event tracks which calendar it came from.
**Warning signs:** If seeing duplicate events in UI after sync

### Pitfall 2: Breaking Existing Connections
**What goes wrong:** Old connections with `calendar_id` stop working
**Why it happens:** Code only checks `calendar_ids` array
**How to avoid:** Implement `get_calendar_ids()` helper that normalizes both formats
**Warning signs:** Errors during sync for existing connections

### Pitfall 3: Empty Selection State
**What goes wrong:** User unchecks all calendars, sync fails silently
**Why it happens:** Empty array passed to sync
**How to avoid:** Validate at least one calendar selected before save, or default to primary
**Warning signs:** Connection shows 0 calendars, no events synced

### Pitfall 4: REST API Parameter Type
**What goes wrong:** `calendar_ids` not saved correctly
**Why it happens:** REST API not configured for array parameter
**How to avoid:** Update REST endpoint args to handle array type for `calendar_ids`
**Warning signs:** Only first calendar saved, or data corruption

## Code Examples

Verified patterns from official sources:

### Backend: Update Connection REST Endpoint
```php
// In class-rest-calendar.php register_routes()
'calendar_ids' => [
    'required'          => false,
    'type'              => 'array',
    'items'             => [ 'type' => 'string' ],
    'sanitize_callback' => function( $value ) {
        if ( ! is_array( $value ) ) {
            return [];
        }
        return array_map( 'sanitize_text_field', $value );
    },
],
```

### Backend: Update Connection Handler
```php
// In update_connection() method
if ( isset( $data['calendar_ids'] ) && is_array( $data['calendar_ids'] ) ) {
    $updates['calendar_ids'] = array_map( 'sanitize_text_field', $data['calendar_ids'] );
    // Build names lookup
    if ( isset( $data['calendar_names'] ) && is_array( $data['calendar_names'] ) ) {
        $updates['calendar_names'] = array_map( 'sanitize_text_field', $data['calendar_names'] );
    }
    // Clear old single field
    $updates['calendar_id'] = '';
    $updates['calendar_name'] = '';
}
```

### Frontend: Connection Card Calendar Count
```jsx
// Display in connection card
{connection.calendar_ids?.length > 0 ? (
    <p className="text-xs text-gray-400 dark:text-gray-500">
        {connection.calendar_ids.length} calendar{connection.calendar_ids.length !== 1 ? 's' : ''} selected
    </p>
) : connection.calendar_id && connection.calendar_id !== 'primary' ? (
    <p className="text-xs text-gray-400 dark:text-gray-500 truncate max-w-xs">
        {connection.calendar_name || connection.calendar_id}
    </p>
) : null}
```

### API Client Update
```javascript
// In src/api/client.js - updateCalendarConnection already handles objects
// Just ensure calendar_ids array is passed through correctly
await prmApi.updateCalendarConnection(connection.id, {
    calendar_ids: selectedCalendarIds,
    calendar_names: selectedCalendarNames,
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Single calendar_id | Multiple calendar_ids array | This phase | Multi-calendar sync |

**Deprecated/outdated:**
- `calendar_id` (string): Will be read for backward compat but new saves use `calendar_ids`
- `calendar_name` (string): Replaced by `calendar_names` (object keyed by id)

## Open Questions

Things that couldn't be fully resolved:

1. **Calendar removal handling**
   - What we know: Events have `_calendar_id` meta tracking source
   - What's unclear: Should events from unselected calendars be deleted?
   - Recommendation: No auto-deletion - events persist until next sync; calendar is simply no longer synced

2. **Google Calendar sharing permissions**
   - What we know: `calendarList` returns all calendars user has access to
   - What's unclear: Are there rate limits per-calendar for API calls?
   - Recommendation: Proceed with sync loop; Google API handles rate limiting via 429 responses

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/caelis/includes/class-calendar-connections.php` - Current storage structure
- `/Users/joostdevalk/Code/caelis/includes/class-google-calendar-provider.php` - Current sync implementation
- `/Users/joostdevalk/Code/caelis/includes/class-rest-calendar.php` - Current REST endpoints
- `/Users/joostdevalk/Code/caelis/src/pages/Settings/Settings.jsx` - Current UI implementation

### Secondary (MEDIUM confidence)
- Phase 68-01-SUMMARY.md - Prior calendar selection implementation

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No new dependencies, existing patterns
- Architecture: HIGH - Clear transformation of existing structure
- Pitfalls: HIGH - Based on actual codebase analysis

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (30 days - stable domain)

## Implementation Checklist

Based on requirements mapping:

| Requirement | Implementation |
|-------------|----------------|
| CAL-01: Multi-calendar selection UI | Checkbox list in EditConnectionModal |
| CAL-02: Array storage | `calendar_ids` array in connection data |
| CAL-03: Sync all calendars | Loop in GoogleProvider::do_sync() |
| CAL-04: Display calendar count | Connection card shows "N calendars selected" |
| CAL-05: Auto-migrate | get_calendar_ids() helper normalizes old format |

## Files to Modify

| File | Changes |
|------|---------|
| `includes/class-calendar-connections.php` | Add `get_calendar_ids()` helper method |
| `includes/class-google-calendar-provider.php` | Refactor sync to iterate calendar_ids |
| `includes/class-rest-calendar.php` | Add `calendar_ids` array parameter handling |
| `src/pages/Settings/Settings.jsx` | EditConnectionModal: checkbox UI, connection card: count display |
| `src/api/client.js` | No changes needed - already handles object params |
