---
phase: quick
plan: 017
subsystem: export
tags: [google-sheets, oauth, export, rest-api]

requires:
  - Google OAuth credentials configured
  - Existing Google OAuth infrastructure
  - Google API client library (composer)

provides:
  - Google Sheets OAuth connection
  - People list export to Google Sheets
  - REST endpoints for Sheets connection management

affects:
  - People list UI (new export button)
  - Settings (potential Sheets connection management)

tech-stack:
  added:
    - Google Sheets API
  patterns:
    - OAuth incremental authorization
    - REST API for export operations
    - User meta storage for connection credentials

key-files:
  created:
    - includes/class-google-sheets-connection.php
    - includes/class-rest-google-sheets.php
  modified:
    - includes/class-google-oauth.php
    - functions.php
    - src/api/client.js
    - src/pages/People/PeopleList.jsx

decisions:
  - decision: Use incremental OAuth to preserve existing Calendar/Contacts scopes
    rationale: Users may already have Google Calendar or Contacts connected; we don't want to require re-authorization
    alternatives: [Separate OAuth flow per service, Combined scope request]
    chosen: Incremental authorization via setIncludeGrantedScopes(true)
    impact: Seamless addition of Sheets without disrupting existing connections

  - decision: Export creates new spreadsheet rather than updating existing
    rationale: Simpler UX, avoids conflicts, gives user full control over export retention
    alternatives: [Update existing sheet, Ask user which sheet, Template-based]
    chosen: Always create new spreadsheet with timestamp in title
    impact: User accumulates export history; can manually manage old exports

  - decision: Export button only visible when connected
    rationale: Reduces clutter when feature not available; clear path to enable
    alternatives: [Always show with disabled state, Show in settings only]
    chosen: Show connect button when configured, export button when connected
    impact: Progressive disclosure; cleaner UI for users without Sheets

  - decision: Use server-side export rather than client-side
    rationale: Avoids CORS issues, handles large datasets, can use refresh tokens
    alternatives: [Client-side Google API, Download CSV then upload, Hybrid approach]
    chosen: Server-side endpoint that returns spreadsheet URL
    impact: Reliable export for any data size; backend handles token refresh

metrics:
  duration: 379s
  completed: 2026-01-30
  tasks: 3
  commits: 3
---

# Quick Task 017: Google Sheets Export for People List

**One-liner:** Export filtered People list to Google Sheets with current columns and sorting using OAuth incremental authorization

## What Was Built

Added Google Sheets export functionality to the People list:

1. **OAuth Infrastructure** (Task 1):
   - Extended GoogleOAuth class with Sheets scope support
   - Added `SHEETS_SCOPE`, `get_sheets_client()`, `get_sheets_auth_url()`, `handle_sheets_callback()`, `has_sheets_scope()`
   - Created GoogleSheetsConnection class for connection storage (simpler than Contacts - no sync tokens or frequency)
   - Registered class in functions.php with backward-compatible alias

2. **REST Endpoints** (Task 2):
   - Created GoogleSheets REST class with 5 endpoints:
     - `GET /status` - Check connection status
     - `GET /auth` - Initiate OAuth flow
     - `GET /callback` - Handle OAuth callback (public for redirect)
     - `DELETE /disconnect` - Remove connection
     - `POST /export-people` - Export people list to Google Sheets
   - Export endpoint:
     - Accepts columns array, filters object, and optional title
     - Fetches people matching current filters using WP_Query
     - Creates spreadsheet with header row + data rows
     - Auto-resizes columns for readability
     - Returns spreadsheet URL

3. **Frontend UI** (Task 3):
   - Added Google Sheets API methods to prmApi client
   - Added export button next to Settings gear in People list header
   - Button visibility:
     - Not connected + OAuth configured → Shows connect button
     - Connected → Shows export button with spinner while exporting
     - Not configured → No button
   - Export flow:
     - Collects visible columns (name + dynamic columns)
     - Collects current filters from URL params
     - Calls export endpoint
     - Opens new spreadsheet in new tab
     - Shows success alert with row count

## Technical Implementation

### OAuth Flow

```
User clicks connect → Frontend requests /auth → Backend generates auth URL with state token
→ User authorizes at Google → Google redirects to /callback → Backend exchanges code for tokens
→ Stores encrypted credentials in user meta → Redirects to /people with success flag
```

### Export Flow

```
User clicks export → Frontend collects columns + filters → POST /export-people
→ Backend fetches people with WP_Query → Builds 2D array (header + rows)
→ Creates spreadsheet via Google Sheets API → Auto-resizes columns
→ Returns spreadsheet URL → Frontend opens in new tab
```

### Data Structure

**GoogleSheetsConnection (user meta: `_stadion_google_sheets_connection`):**
```php
[
  'credentials' => string (encrypted),  // OAuth tokens
  'email' => string,                    // Connected Google account
  'connected_at' => string,             // ISO 8601 timestamp
  'last_error' => string|null,          // Last error message
]
```

**Export request payload:**
```json
{
  "columns": ["name", "email", "phone", "team", "labels"],
  "filters": {
    "search": "John",
    "label": "23",
    "orderby": "first_name",
    "order": "asc"
  },
  "title": "Leden Export - 2026-01-30 08:44"
}
```

## Deviations from Plan

None - plan executed exactly as written.

## Testing Recommendations

1. **OAuth Flow:**
   - Test connect when not connected (should redirect to Google)
   - Test connect when already connected (should show error)
   - Test callback with invalid state token (should redirect with error)
   - Test callback with denied permission (should redirect with error)

2. **Export Functionality:**
   - Test export with various column configurations
   - Test export with different filters (labels, team, search)
   - Test export with sorting (first name, last name, modified)
   - Test export with large datasets (1000+ people)
   - Test export when token needs refresh

3. **Error Handling:**
   - Test export when not connected (should show error)
   - Test export when token expired/invalid (should refresh)
   - Test export when Google API fails (should store error)

4. **UI Behavior:**
   - Verify button only shows when OAuth configured
   - Verify connect button shows when not connected
   - Verify export button shows when connected
   - Verify spinner shows while exporting
   - Verify spreadsheet opens in new tab
   - Verify success message shows row count

## Next Phase Readiness

**Ready for:**
- Settings page connection management UI (disconnect, status display)
- Export templates (predefined column sets)
- Scheduled exports (via cron)
- Export other entity types (teams, commissies)

**Blockers:** None

**Concerns:** None

## Files Modified

### Backend (PHP)
- `includes/class-google-oauth.php` - Added Sheets OAuth methods
- `includes/class-google-sheets-connection.php` - Created connection storage class
- `includes/class-rest-google-sheets.php` - Created REST endpoints
- `functions.php` - Registered new classes

### Frontend (React)
- `src/api/client.js` - Added Sheets API methods to prmApi
- `src/pages/People/PeopleList.jsx` - Added export button and handlers

### Build
- `dist/` - Updated production assets (not committed, gitignored)

## Commits

| Hash    | Message                                                   |
|---------|-----------------------------------------------------------|
| 7fdd45e | feat(quick-017): add Google Sheets OAuth scope and connection storage |
| 73bb07e | feat(quick-017): create Google Sheets REST endpoints      |
| 0f82bd7 | feat(quick-017): add export button to People list UI      |

**Total changes:**
- 2 files created (class-google-sheets-connection.php, class-rest-google-sheets.php)
- 4 files modified (class-google-oauth.php, functions.php, client.js, PeopleList.jsx)
- 3 atomic commits
- 379 seconds execution time (~6 minutes)
