# Phase 199: OAuth + Frontend Cleanup - Research

**Researched:** 2026-02-20
**Domain:** PHP class cleanup / React frontend state & UI removal
**Confidence:** HIGH

## Summary

Phase 199 is a code-removal phase that follows Phase 198's backend sync class deletion. The goal is to trim down `class-google-oauth.php` to Sheets-only, remove the entire Contacts/Calendar sync UI sections from `Settings.jsx`, remove dead API client methods, and remove the Gravatar REST endpoint and its frontend call. No new libraries or patterns are needed — this is pure deletion with targeted refactoring.

The phase is straightforward because Phase 198 already removed the backend sync classes. What remains are: the OAuth class with Contacts/Calendar scope methods that are now dead code; the Settings page `CalendarsTab` and `ConnectionsContactsSubtab` components that call non-existent REST endpoints; API client methods pointing to deleted backend routes; and the Gravatar endpoint + its one frontend call site.

The critical risk is identifying what MUST be preserved. `class-google-oauth.php` must keep the Sheets-only methods (`get_sheets_client`, `get_sheets_auth_url`, `handle_sheets_callback`, `has_sheets_scope`) and the shared token utility (`get_access_token`, `refresh_token`, `is_configured`). The `CalendarsTab` (CalDAV + iCal) must also be preserved — only the Google Calendar OAuth sub-flow and the Google Contacts subtab are removed. The `CONNECTION_SUBTABS` configuration must be updated to remove 'calendars' and 'contacts' entries.

**Primary recommendation:** Two sub-plans: (1) simplify `class-google-oauth.php` to Sheets only; (2) remove the Contacts/Calendar subtabs, dead API methods, and the Gravatar endpoint+hook in a single frontend/backend sweep. Start with backend PHP (plan 199-01), then frontend (plan 199-02).

---

## Standard Stack

No new libraries required. This phase uses the existing stack:

### Core
| Component | Version | Purpose | Notes |
|-----------|---------|---------|-------|
| PHP 8.0+ | existing | Backend class cleanup | Edit in-place |
| React 18 | existing | Frontend component removal | Edit in-place |
| Vite 5 | existing | Build verification | Run `npm run build` after changes |

---

## Architecture Patterns

### Pattern 1: Incremental PHP Class Simplification

The `class-google-oauth.php` file contains three logical groups of methods:

1. **Shared utility** (KEEP): `is_configured()`, `get_access_token()`, `refresh_token()`
2. **Calendar-specific** (REMOVE): `SCOPES` constant, `get_client()`, `get_redirect_uri()`, `get_auth_url()`, `handle_callback()`
3. **Contacts-specific** (REMOVE): `CONTACTS_SCOPE_READONLY`, `CONTACTS_SCOPE_READWRITE`, `get_contacts_client()`, `get_contacts_auth_url()`, `handle_contacts_callback()`, `has_contacts_scope()`, `get_contacts_access_mode()`
4. **Sheets-specific** (KEEP): `SHEETS_SCOPE`, `get_sheets_client()`, `get_sheets_auth_url()`, `handle_sheets_callback()`, `has_sheets_scope()`

After the removal, the file class doc comment should be updated to reflect "Handles Google OAuth2 for Google Sheets integration only."

Also: The class namespace is `Rondo\Calendar` — this should be moved to `Rondo\Sheets` to match its sole remaining purpose. However, `functions.php` has a `use Rondo\Calendar\GoogleOAuth;` and a backward-compat alias `RONDO_Google_OAuth`. Namespace change would require updating both. **Check if any other class references `Rondo\Calendar\GoogleOAuth`:**

- `includes/class-rest-google-sheets.php` line 15: `use Rondo\Calendar\GoogleOAuth;`
- `functions.php` line 46: `use Rondo\Calendar\GoogleOAuth;`
- `functions.php` line 177: `class_alias( GoogleOAuth::class, 'RONDO_Google_OAuth' );`

Namespace change is optional DRY cleanup — not required for functionality but is the correct thing to do per the removal of Calendar responsibility. Include in plan 199-01.

### Pattern 2: Settings.jsx Component Removal Strategy

The Settings.jsx file (3160 lines) is a monolith. The surgical removals needed are:

**A. `CONNECTION_SUBTABS` constant** (line 26–31): Remove the `calendars` and `contacts` entries, keeping only `carddav` and `api-access`.

```js
// BEFORE (line 26-31):
const CONNECTION_SUBTABS = [
  { id: 'calendars', label: 'Google Agenda', icon: Calendar },
  { id: 'contacts', label: 'Google Contacten', icon: Users },
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'api-access', label: 'API-toegang', icon: Key },
];

// AFTER:
const CONNECTION_SUBTABS = [
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'api-access', label: 'API-toegang', icon: Key },
];
```

**B. `Settings()` function state variables** (lines 97–112): Remove all Google Contacts state:
- `googleContactsStatus`, `googleContactsLoading`, `connectingGoogleContacts`, `disconnectingGoogleContacts`, `googleContactsMessage`, `googleContactsImporting`, `googleContactsImportResult`, `unlinkedCount`, `isBulkExporting`, `bulkExportResult`, `isSyncing`, `syncError`, `syncSuccess`

**C. `useEffect` hooks in `Settings()`** (lines 160–222):
- Remove "Fetch Google Contacts status on mount" effect (lines 160-173)
- Remove "Fetch unlinked count when connected with readwrite" effect (lines 175-186)
- Remove Google Contacts handling from "Handle OAuth callback messages" effect (lines 188-213) — only keep the non-contacts `googleConnected === 'google'` branch if needed, but since CalendarsTab is also removed, remove the entire `useEffect` or simplify it
- Remove "Auto-import when pending flag is set" effect (lines 216-222)

**D. Handler functions in `Settings()`** (lines 269-395 approx):
- Remove: `handleConnectGoogleContacts`, `handleDisconnectGoogleContacts`, `handleImportGoogleContacts`, `handleBulkExportGoogleContacts`, `handleContactsSync`, `handleFrequencyChange`

**E. `ConnectionsTab` component** (line 2018-2115):
- Remove all Google Contacts props from the component signature
- Remove `{activeSubtab === 'calendars' && <ConnectionsCalendarsSubtab />}` line (2063)
- Remove `{activeSubtab === 'contacts' && <ConnectionsContactsSubtab ... />}` block (lines 2064-2087)

**F. Sub-components to delete entirely**:
- `CalendarsTab` function (line 953 through ~line 1358 — very large component)
- `ConnectionsCalendarsSubtab` function (lines 2117-2121)
- `ConnectionsContactsSubtab` function (lines 2131 to ~line 2475)
- `SYNC_FREQUENCY_OPTIONS` constant (lines 2123-2130) - only used by deleted component

**G. Imports to clean up** (line 3):
- Remove `Calendar` from lucide-react import (only used by deleted CalendarsTab and CONNECTION_SUBTABS)
- Remove `Users` from lucide-react import if not used elsewhere (check: used in `ADMIN_SUBTABS` line 35: `{ id: 'users', label: 'Gebruikers', icon: Users }`)
- Keep `Users` — it is still used in admin subtabs

Also check the default `activeSubtab` behavior: line 50 sets `const activeSubtab = urlSubtab || 'calendars'`. This needs to change to `|| 'carddav'` since `calendars` subtab is being removed.

### Pattern 3: API Client Removal

`src/api/client.js` lines 223-231 (Google Contacts methods) and lines 250-257 (Calendar connections + Google Auth):

**Remove:**
```js
// Google Contacts OAuth (lines 223-231)
getGoogleContactsStatus: () => api.get('/rondo/v1/google-contacts/status'),
initiateGoogleContactsAuth: (readonly = true) => api.get('/rondo/v1/google-contacts/auth', { params: { readonly } }),
disconnectGoogleContacts: () => api.delete('/rondo/v1/google-contacts'),
triggerGoogleContactsImport: () => api.post('/rondo/v1/google-contacts/import'),
getGoogleContactsUnlinkedCount: () => api.get('/rondo/v1/google-contacts/unlinked-count'),
bulkExportGoogleContacts: () => api.post('/rondo/v1/google-contacts/bulk-export'),
triggerContactsSync: () => api.post('/rondo/v1/google-contacts/sync'),
updateContactsSyncFrequency: (frequency) => api.post('/rondo/v1/google-contacts/sync-frequency', { frequency }),

// Calendar connections (lines 250-257)
getCalendarConnections: () => api.get('/rondo/v1/calendar/connections'),
createCalendarConnection: (data) => api.post('/rondo/v1/calendar/connections', data),
updateCalendarConnection: (id, data) => api.put(`/rondo/v1/calendar/connections/${id}`, data),
deleteCalendarConnection: (id) => api.delete(`/rondo/v1/calendar/connections/${id}`),
triggerCalendarSync: (id) => api.post(`/rondo/v1/calendar/connections/${id}/sync`),
getConnectionCalendars: (id) => api.get(`/rondo/v1/calendar/connections/${id}/calendars`),
getGoogleAuthUrl: () => api.get('/rondo/v1/calendar/auth/google'),
testCalDAVConnection: (credentials) => api.post('/rondo/v1/calendar/auth/caldav/test', credentials),
```

**Keep:**
```js
// Google Sheets OAuth (lines 233-237) — KEEP
getSheetsStatus: () => api.get('/rondo/v1/google-sheets/status'),
getSheetsAuthUrl: () => api.get('/rondo/v1/google-sheets/auth'),
disconnectSheets: () => api.delete('/rondo/v1/google-sheets/disconnect'),
exportPeopleToSheets: (data) => api.post('/rondo/v1/google-sheets/export-people', data),

// Also keep (lines 213-221) — used by Dashboard for meetings display
getPersonMeetings: ..., logMeetingAsActivity: ..., getTodayMeetings: ...,
getMeetingsForDate: ..., getMeetingNotes: ..., updateMeetingNotes: ...
```

**Note on calendar/today-meetings**: `getTodayMeetings` and `getMeetingsForDate` call `/rondo/v1/calendar/today-meetings` which is a different endpoint (in `class-rest-api.php`) and is used by the Dashboard. This endpoint is NOT being removed. Only the Calendar *connections* management and CalDAV sync endpoints are removed.

**Also remove:**
```js
// Gravatar (line 102-103)
sideloadGravatar: (personId, email) => api.post(`/rondo/v1/people/${personId}/gravatar`, { email }),
```

### Pattern 4: Gravatar Backend Endpoint Removal

In `includes/class-rest-people.php`:
- Remove `register_rest_route` block for `/people/(?P<person_id>\d+)/gravatar` (lines 55-77)
- Remove `sideload_gravatar()` method (lines 375-447)

### Pattern 5: Gravatar Frontend Removal

**`src/hooks/usePeople.js`** (lines 264-270):
```js
// REMOVE this block:
if (data.email) {
  try {
    await prmApi.sideloadGravatar(personId, data.email);
  } catch {
    // Gravatar sideload failed silently - not critical
  }
}
```

**`src/components/PersonEditModal.jsx`** (line 373):
```jsx
// REMOVE this helper text line:
Gravatar wordt automatisch opgehaald indien beschikbaar
```

**`src/api/client.js`** (line 102-103):
Already noted above — remove `sideloadGravatar` method.

### Anti-Patterns to Avoid

- **Don't remove the `CalDAVProvider` class**: It is still used by the CardDAV subtab in settings (test connection, discover calendars).
- **Don't remove `CalendarMatcher` class**: Still used for meeting/people matching (Dashboard meetings feature).
- **Don't remove `getPersonMeetings`, `getTodayMeetings`, etc.**: These API methods call `/rondo/v1/calendar/today-meetings` which is in `class-rest-api.php` (not a deleted class). The Dashboard still uses meetings.
- **Don't remove the `calendar_event` post type**: Still needed for meeting data.
- **Don't remove `ICalFeed` class or iCal URL endpoints**: The iCal subscription section in CalendarsTab is read-only personal calendar feed; it's unrelated to CalendarSync removal.

Wait — but we ARE removing CalendarsTab entirely from Settings. The iCal section lives INSIDE CalendarsTab. This means the iCal URL management UI will be gone too. Is this intentional?

**Important clarification needed**: The requirements say "No Contacts or Calendar sync pages accessible" and "Settings Connections tab shows no Contacts or Calendar connection UI." The CalendarsTab contains both Google Calendar OAuth connections AND CalDAV connections AND the iCal feed URL section. The requirements say to remove Calendar connection UI, but the iCal feed is a read-only subscription URL, not a "sync connection."

However, the iCal feed URL is also accessible elsewhere: there is a `/rondo/v1/user/ical-url` endpoint and `user/ical-url` and `user/regenerate-ical-token` calls in CalendarsTab. If the CalendarsTab is entirely removed, the iCal URL UI is gone too. Since the plan says "remove the CalendarsTab" per the additional context, this is accepted scope. The iCal feed still works (backend preserved), just no UI to see/regenerate the URL.

Actually, looking more carefully: The plan description says "Settings Connections tab shows no Contacts or Calendar connection UI" — this implies removing the 'calendars' and 'contacts' subtabs. The question is whether the iCal URL section gets its own place or just disappears. Per the requirements, just removing the full subtab is correct — the iCal feed still works without the UI.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Verifying no dead imports remain | Manual check | `npm run lint` — ESLint will catch unused imports after removal |
| Verifying build still compiles | Manual check | `npm run build` at end of phase 199-02 |
| Verifying no PHP syntax errors | Manual check | SSH to production and check error log after deploy |

---

## Common Pitfalls

### Pitfall 1: Removing Calendar Auth Without Checking Dashboard Dependencies

**What goes wrong:** Removing `getGoogleAuthUrl`, `getCalendarConnections` etc. from client.js but missing that `Dashboard.jsx` references calendar connections via `prmApi`.

**Why it happens:** The Dashboard file imports `prmApi` and calls `getTodayMeetings` / `getPersonMeetings` / `getCalendarConnections` separately.

**How to avoid:** Search for `getCalendarConnections\|getGoogleAuthUrl\|triggerCalendarSync` across all `src/` files before deleting. Only Settings.jsx uses these.

**Warning signs:** ESLint "X is not a function" or undefined errors in console.

### Pitfall 2: Leaving `activeSubtab` Default Pointing to Removed Tab

**What goes wrong:** Line 50 sets `const activeSubtab = urlSubtab || 'calendars'`. If `calendars` is removed from `CONNECTION_SUBTABS`, the default will try to render `activeSubtab === 'calendars'` which renders nothing — the Connections tab appears blank.

**How to avoid:** Update the default to `'carddav'`: `const activeSubtab = urlSubtab || 'carddav'`.

### Pitfall 3: Unused Lucide Icons After Removal

**What goes wrong:** `Calendar` icon from lucide-react is imported on line 3 and used only in `CONNECTION_SUBTABS` and `CalendarsTab`. After removal, ESLint fails with unused import.

**How to avoid:** Remove `Calendar` from the lucide import. Verify `Users` is still needed (yes — used in `ADMIN_SUBTABS`).

### Pitfall 4: OAuth Namespace Mismatch After Class Cleanup

**What goes wrong:** Renaming `class-google-oauth.php` namespace from `Rondo\Calendar` to `Rondo\Sheets` but not updating `use` statements in `functions.php` and `class-rest-google-sheets.php`.

**How to avoid:** Update all three locations atomically:
1. `class-google-oauth.php`: change `namespace Rondo\Calendar;`
2. `functions.php`: change `use Rondo\Calendar\GoogleOAuth;` (line 46)
3. `class-rest-google-sheets.php`: change `use Rondo\Calendar\GoogleOAuth;` (line 15)

The backward compat alias `RONDO_Google_OAuth` in functions.php (line 177) still works regardless of namespace since it uses the imported class.

### Pitfall 5: `get_access_token` Docblock Still Says "Calendar connection"

**What goes wrong:** The docblock comment for `get_access_token` (line 139 of class-google-oauth.php) says "Calendar connection with encrypted credentials". This is misleading after the cleanup.

**How to avoid:** Update the docblock to reference "Sheets connection" or "OAuth connection".

### Pitfall 6: `SCOPES` Constant Used Indirectly

**What goes wrong:** The private `SCOPES` constant (`calendar.readonly`) is used in `get_client()` and in `handle_callback()` (the `scope` field fallback in the returned array). Deleting these methods removes all references to `SCOPES`. No issue — just verify grep shows zero remaining references.

---

## Code Examples

### PHP: Class After Cleanup (skeleton)

```php
namespace Rondo\Sheets;  // Changed from Rondo\Calendar

class GoogleOAuth {

    /**
     * Google Sheets scope
     */
    public const SHEETS_SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    public static function is_configured(): bool { ... }

    public static function get_access_token( array $connection ): ?string { ... }

    private static function refresh_token( string $refresh_token ): array { ... }

    public static function get_sheets_client( bool $include_granted_scopes = true ): ?\Google\Client { ... }

    public static function get_sheets_auth_url( int $user_id ): string { ... }

    public static function handle_sheets_callback( string $code, int $user_id ): array { ... }

    public static function has_sheets_scope( array $credentials ): bool { ... }
}
```

Methods removed: `SCOPES`, `CONTACTS_SCOPE_READONLY`, `CONTACTS_SCOPE_READWRITE`, `get_client()`, `get_redirect_uri()`, `get_auth_url()`, `handle_callback()`, `get_contacts_client()`, `get_contacts_auth_url()`, `handle_contacts_callback()`, `has_contacts_scope()`, `get_contacts_access_mode()`.

### React: CONNECTION_SUBTABS After Cleanup

```js
const CONNECTION_SUBTABS = [
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'api-access', label: 'API-toegang', icon: Key },
];
```

Default activeSubtab changes:
```js
const activeSubtab = urlSubtab || 'carddav';
```

### React: usePeople.js After Gravatar Removal

```js
// Create the person
const response = await wpApi.createPerson(payload);
const personId = response.data.id;

// (Gravatar sideload removed)

return response.data;
```

---

## Open Questions

1. **iCal URL section**: The iCal URL display/regenerate section lives inside `CalendarsTab`. When `CalendarsTab` is deleted from Settings, users lose the ability to see/regenerate their iCal URL through the UI. The backend iCal feed still works. Per the requirements this is accepted scope (the tab removal is explicit), but worth flagging for the plan so the planner documents this as a known UX change.

2. **Namespace change**: Moving `GoogleOAuth` from `Rondo\Calendar` to `Rondo\Sheets` is correct semantically but is a refactor that touches 3 files. Confidence it's safe: HIGH (no other files reference `Rondo\Calendar\GoogleOAuth` besides the two `use` statements and the alias). Confirm with a grep before implementing.

3. **`functions.php` backward compat alias**: The `RONDO_Google_OAuth` alias (line 177-179) can stay — it does no harm and maintains backward compatibility. Or it can be removed since there are no external consumers. Recommend keeping it to be safe.

---

## Sources

### Primary (HIGH confidence)
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-google-oauth.php` — full file read
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-google-sheets.php` — full file read
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-people.php` — relevant section read
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js` — relevant sections read
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/Settings.jsx` — multiple sections read
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/src/hooks/usePeople.js` — relevant section read
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/src/components/PersonEditModal.jsx` — grep
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/functions.php` — full file read
- Codebase inspection: `/Users/joostdevalk/Code/rondo/rondo-club/src/router.jsx` — full file read

### Secondary (MEDIUM confidence)
- Phase 198 additional context (provided) — confirms what was deleted in Phase 198

---

## Metadata

**Confidence breakdown:**
- What to remove: HIGH — direct code inspection confirms all removal targets
- What to keep: HIGH — verified by cross-referencing API client, hooks, and Dashboard
- PHP namespace change risk: HIGH — only 2 `use` statements need updating, confirmed by grep
- No hidden consumers: HIGH — grep for `Rondo\Calendar\GoogleOAuth` and `google-contacts` confirms only the expected files

**Research date:** 2026-02-20
**Valid until:** Until Phase 200 planning (this research is about specific code that won't change)
