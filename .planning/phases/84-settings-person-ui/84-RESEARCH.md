# Phase 84: Settings & Person UI - Research

**Researched:** 2026-01-18
**Domain:** React UI, Settings Page, Person Detail Page, Google Contacts Integration
**Confidence:** HIGH

## Summary

This research investigates what exists vs what needs to be built for Phase 84. The key finding is that **most Settings UI already exists** from Phase 82-03. The remaining work is completing a few requirements (SETTINGS-01, SETTINGS-03, SETTINGS-07) and adding the Person page integration (PERSON-01).

**Primary recommendation:** Focus on gap-filling rather than new architecture. Add `google_contact_id` to REST response, display error count, build sync history log viewer, and add "View in Google" link to PersonDetail page.

## Current State Analysis

### What Already Exists (from Phase 79-82)

| Requirement | Current State | Location |
|-------------|--------------|----------|
| SETTINGS-01 | PARTIAL - Card exists but missing error count | `Settings.jsx:2207-2480` |
| SETTINGS-02 | COMPLETE - Connect/Disconnect buttons work | `handleConnectGoogleContacts`, `handleDisconnectGoogleContacts` |
| SETTINGS-03 | PARTIAL - Shows last_sync and contact_count, missing error count | `Settings.jsx:2233-2242` |
| SETTINGS-04 | COMPLETE - "Sync Now" button exists and works | `handleContactsSync`, line 2406-2422 |
| SETTINGS-05 | COMPLETE - Frequency dropdown exists | `SYNC_FREQUENCY_OPTIONS`, line 2425-2441 |
| SETTINGS-06 | NOT BUILT - Conflict resolution dropdown | Backend defaults to "Stadion wins", no UI toggle |
| SETTINGS-07 | NOT BUILT - Sync history log viewer | No backend storage for history |
| PERSON-01 | NOT BUILT - "View in Google Contacts" link | `_google_contact_id` exists in post meta, not exposed to frontend |

### Backend Data Available

From `/rondo/v1/google-contacts/status` (class-rest-google-contacts.php:183-200):

```php
$response = [
    'connected'          => $connected,
    'google_configured'  => GoogleOAuth::is_configured(),
    'access_mode'        => $connection['access_mode'] ?? 'none',
    'email'              => $connection['email'] ?? '',
    'last_sync'          => $connection['last_sync'] ?? null,
    'contact_count'      => $connection['contact_count'] ?? 0,
    'last_error'         => $connection['last_error'] ?? null,
    'has_pending_import' => GoogleContactsConnection::has_pending_import($user_id),
    'connected_at'       => $connection['connected_at'] ?? null,
    'sync_frequency'     => $connection['sync_frequency'] ?? GoogleContactsConnection::get_default_frequency(),
    'sync_in_progress'   => false,
];
```

**Not available:** error_count, sync_history

### Person Data Available

From `wpApi.getPerson()` via WP REST API:
- ACF fields exposed via `person.acf`
- `_google_contact_id` stored in post meta but NOT exposed to REST

## Standard Stack

Already in use in the codebase (no new libraries needed):

| Library | Version | Purpose | Already Used |
|---------|---------|---------|--------------|
| date-fns | Current | formatDistanceToNow for relative times | Yes - Settings.jsx:5 |
| TanStack Query | Current | Server state, cache invalidation | Yes - throughout |
| Lucide React | Current | Icons | Yes - ExternalLink icon available |

## Architecture Patterns

### Existing Pattern: Connection Status Display

From `ConnectionsContactsSubtab` (Settings.jsx:2224-2242):

```jsx
<div className="p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
  <div className="flex items-center justify-between">
    <div>
      <p className="font-medium text-green-900 dark:text-green-300">Connected to Google Contacts</p>
      {googleContactsStatus.email && (
        <p className="text-sm text-green-700 dark:text-green-400">{googleContactsStatus.email}</p>
      )}
      {googleContactsStatus.last_sync && (
        <p className="text-xs text-green-600 dark:text-green-500 mt-1">
          Last synced: {formatDistanceToNow(new Date(googleContactsStatus.last_sync), { addSuffix: true })}
        </p>
      )}
      {googleContactsStatus.contact_count > 0 && (
        <p className="text-xs text-green-600 dark:text-green-500">
          {googleContactsStatus.contact_count} contacts synced
        </p>
      )}
    </div>
  </div>
</div>
```

**Pattern:** Information displayed as stacked `<p>` elements with conditional rendering.

### Existing Pattern: Error Display

From `ConnectionsContactsSubtab` (Settings.jsx:2459-2465):

```jsx
{googleContactsStatus.last_error && !googleContactsImportResult && (
  <div className="p-3 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
    <p className="text-sm text-red-700 dark:text-red-300">
      Last sync error: {googleContactsStatus.last_error}
    </p>
  </div>
)}
```

**Pattern:** Error shown in red banner below status card.

### Existing Pattern: External Link

From `MeetingCard` in PersonDetail.jsx (line 139-149):

```jsx
<a
  href={meeting.meeting_url}
  target="_blank"
  rel="noopener noreferrer"
  className="text-sm text-accent-600 dark:text-accent-400 hover:underline flex items-center gap-1"
>
  <Video className="w-3.5 h-3.5" />
  <span className="truncate">{meeting.location || 'Video meeting'}</span>
  <ExternalLink className="w-3 h-3 flex-shrink-0" />
</a>
```

**Pattern:** External links use accent color, small icon, target="_blank" with noopener noreferrer.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Relative time display | Custom date logic | `formatDistanceToNow` from date-fns | Already used throughout codebase |
| REST field exposure | Custom endpoint | `register_rest_field` WordPress function | Standard WP pattern for adding fields |
| Collapsible sections | Custom accordion | Existing expandable pattern in codebase | Consistent UX |

## Implementation Approach

### SETTINGS-03: Error Count Display

**Current gap:** Backend tracks `last_error` but not `error_count`.

**Options:**
1. Add `error_count` to connection storage (requires backend change)
2. Display "1 error" when `last_error` exists, "0 errors" otherwise (no backend change)

**Recommendation:** Option 2 - Display binary error indicator. The CONTEXT.md says "Error display: collapse to 'X errors' with expandable details if user wants them" - but we only have one error (last_error). Show "1 error" if last_error exists, make it expandable.

### SETTINGS-06: Conflict Resolution Strategy

**CONTEXT.md decision:** "Conflict resolution: default 'Stadion wins' strategy is automatic - no user-facing toggle needed"

**Recommendation:** Skip this requirement per user decision. Backend already defaults to Stadion wins.

### SETTINGS-07: Sync History Log

**Current gap:** No sync history storage. Backend only stores last_sync timestamp.

**Options:**
1. Add sync history array to connection meta (unlimited entries, memory concern)
2. Add sync history with capped entries (e.g., last 10)
3. Log to post comments on a system post (WordPress pattern)
4. Skip sync history UI (simplest)

**Recommendation:** Option 2 - Store last 10 sync operations in connection meta. Structure:
```php
'sync_history' => [
    [
        'timestamp' => '2026-01-18T10:00:00Z',
        'pulled' => 5,
        'pushed' => 3,
        'errors' => 0,
        'duration_ms' => 1250,
    ],
    // ... up to 10 entries
]
```

**UI Pattern:** Collapsible "Sync History" section showing recent syncs in a simple list.

### PERSON-01: View in Google Contacts Link

**Data needed:** `_google_contact_id` post meta (stores `people/c12345` format)

**Google Contacts URL format:**
```
https://contacts.google.com/person/{resourceName}
```

Where resourceName is the full `people/c12345` string stored in `_google_contact_id`.

**Example:**
- Meta value: `people/c8947123456789012345`
- URL: `https://contacts.google.com/person/people/c8947123456789012345`

Note: The URL includes the full resourceName including the "people/" prefix.

**Implementation approach:**
1. Add `register_rest_field` to expose `_google_contact_id` on person REST response
2. Add conditional link in PersonDetail.jsx when `person.google_contact_id` exists

**Placement (per CONTEXT.md):** "Display as small link icon near contact info (not prominent - utility feature)"

Recommended placement: In the contact info section header, as a small external link icon.

## Common Pitfalls

### Pitfall 1: Google Contacts URL Format

**What goes wrong:** Incorrect URL construction, contact not found.

**Why it happens:** Assuming resourceName needs transformation.

**How to avoid:** Use resourceName as-is. The format `people/c12345` is already the path segment Google expects.

**Verification:** Test URL with real contact ID in browser.

### Pitfall 2: REST Field Security

**What goes wrong:** Exposing google_contact_id to all users.

**Why it happens:** Not considering that different users might view different contacts (in workspaces).

**How to avoid:** The `_google_contact_id` is user-specific (each user has their own Google connection). Only expose to the contact owner via `get_callback` permission check.

**Actually:** Since Stadion already has per-user access control at the query level (RONDO_Access_Control), users can only see their own contacts. The google_contact_id on a contact they can see is inherently theirs. No additional permission check needed.

### Pitfall 3: Sync History Memory

**What goes wrong:** Connection meta grows unbounded, slows queries.

**Why it happens:** Appending to history without limit.

**How to avoid:** Cap history at 10 entries, use array_slice to trim.

## Code Examples

### Adding REST Field for google_contact_id

```php
// In class-rest-api.php or class-rest-google-contacts.php
add_action('rest_api_init', function() {
    register_rest_field('person', 'google_contact_id', [
        'get_callback' => function($post) {
            return get_post_meta($post['id'], '_google_contact_id', true) ?: null;
        },
        'schema' => [
            'description' => 'Google Contacts resource name for this person',
            'type' => 'string',
            'context' => ['view', 'edit'],
        ],
    ]);
});
```

### View in Google Link Component

```jsx
// In PersonDetail.jsx, near contact info section
{person.google_contact_id && (
  <a
    href={`https://contacts.google.com/person/${person.google_contact_id}`}
    target="_blank"
    rel="noopener noreferrer"
    className="text-xs text-gray-500 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 flex items-center gap-1"
    title="View in Google Contacts"
  >
    <ExternalLink className="w-3 h-3" />
    <span>Google</span>
  </a>
)}
```

### Sync History Storage Update

```php
// In class-google-contacts-sync.php after successful sync
$history_entry = [
    'timestamp' => current_time('c'),
    'pulled' => $results['pull_stats']['contacts_imported'] ?? 0,
    'pushed' => $results['push_stats']['pushed'] ?? 0,
    'errors' => count($results['errors'] ?? []),
    'duration_ms' => $duration_ms,
];

$history = $connection['sync_history'] ?? [];
array_unshift($history, $history_entry);
$history = array_slice($history, 0, 10); // Keep last 10

GoogleContactsConnection::update_connection($user_id, [
    'sync_history' => $history,
]);
```

### Error Count Display

```jsx
// In ConnectionsContactsSubtab
{googleContactsStatus.last_error ? (
  <details className="text-xs text-amber-600 dark:text-amber-400 mt-1">
    <summary className="cursor-pointer hover:underline">1 error</summary>
    <p className="mt-1 text-amber-700 dark:text-amber-300">
      {googleContactsStatus.last_error}
    </p>
  </details>
) : (
  <p className="text-xs text-green-600 dark:text-green-500">No errors</p>
)}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Show error as banner | Collapse error details | Phase 84 | Cleaner UI when no errors |
| No sync history | Last 10 syncs visible | Phase 84 | Better debugging for users |

## Open Questions

### Question 1: Sync History Granularity

- **What we know:** We can track pulled/pushed/errors/duration per sync
- **What's unclear:** Should we track per-contact detail (which contacts had errors)?
- **Recommendation:** Start simple (aggregate counts only). Enhance if users request detail.

### Question 2: Error Count vs Last Error

- **What we know:** Backend only stores `last_error` (string, singular)
- **What's unclear:** Should we add `error_count` field or derive from history?
- **Recommendation:** Derive from history. If sync_history[0].errors > 0, show count. Otherwise binary "has error / no error".

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/pages/Settings/Settings.jsx` - Existing ConnectionsContactsSubtab implementation
- `/Users/joostdevalk/Code/stadion/includes/class-rest-google-contacts.php` - Status endpoint response structure
- `/Users/joostdevalk/Code/stadion/includes/class-google-contacts-connection.php` - Connection storage model

### Secondary (MEDIUM confidence)
- Google Contacts URL format verified via browser testing pattern `https://contacts.google.com/person/people/c*`

## Metadata

**Confidence breakdown:**
- Existing UI patterns: HIGH - Direct code analysis
- REST field exposure: HIGH - Standard WordPress pattern
- Google Contacts URL: MEDIUM - Verified pattern but may change
- Sync history structure: HIGH - Simple extension of existing model

**Research date:** 2026-01-18
**Valid until:** 30 days (stable domain, no fast-moving dependencies)

---

## Summary for Planner

**What to build:**

1. **Backend: REST field for google_contact_id** - `register_rest_field` on `person` post type
2. **Backend: Sync history storage** - Add `sync_history` array to connection, update after sync
3. **Backend: Return sync_history in status** - Extend `/rondo/v1/google-contacts/status` response
4. **Frontend: Error count display** - Add collapsible error details to existing status card
5. **Frontend: Sync history viewer** - Collapsible section with list of recent syncs
6. **Frontend: View in Google link** - Small external link on PersonDetail page

**What NOT to build:**
- SETTINGS-06 (conflict resolution dropdown) - User decided "Stadion wins" is automatic, no toggle needed
- New sync frequency options - Already complete
- Connect/disconnect buttons - Already complete
