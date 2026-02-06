---
phase: quick
plan: 017
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-google-oauth.php
  - includes/class-rest-google-sheets.php
  - src/pages/People/PeopleList.jsx
  - src/api/client.js
autonomous: true

must_haves:
  truths:
    - "User can export current People list result set to Google Sheets"
    - "Export includes all visible columns in current column order"
    - "Export respects current filters/sorting"
    - "Export button only appears when Google is connected with Sheets scope"
  artifacts:
    - path: "includes/class-rest-google-sheets.php"
      provides: "REST endpoint for Google Sheets export"
      exports: ["POST /rondo/v1/google-sheets/export-people"]
    - path: "includes/class-google-oauth.php"
      provides: "Google Sheets scope constant"
      contains: "SHEETS_SCOPE"
  key_links:
    - from: "src/pages/People/PeopleList.jsx"
      to: "/rondo/v1/google-sheets/export-people"
      via: "fetch on export button click"
      pattern: "google-sheets/export-people"
---

<objective>
Export People list to Google Sheets with current columns, filters, and sorting.

Purpose: Allow users to quickly create a Google Sheet from the current People list view for sharing, reporting, or further analysis. Leverages existing Google OAuth infrastructure.

Output: Export button in People list that creates a new Google Sheet and opens it.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@includes/class-google-oauth.php (existing Google OAuth with contacts scope)
@includes/class-rest-google-contacts.php (REST pattern reference)
@includes/class-google-contacts-connection.php (connection storage pattern)
@src/pages/People/PeopleList.jsx (People list with visible columns, filters)
@composer.json (google/apiclient includes Sheets service)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add Google Sheets OAuth scope and connection storage</name>
  <files>
    - includes/class-google-oauth.php
    - includes/class-google-sheets-connection.php
  </files>
  <action>
1. In `class-google-oauth.php`:
   - Add constant `SHEETS_SCOPE = 'https://www.googleapis.com/auth/spreadsheets'`
   - Add `get_sheets_client()` method following `get_contacts_client()` pattern
   - Add `get_sheets_auth_url()` method following `get_contacts_auth_url()` pattern
   - Add `handle_sheets_callback()` method following `handle_contacts_callback()` pattern
   - Add `has_sheets_scope()` check method

2. Create `class-google-sheets-connection.php` in `includes/`:
   - Follow `class-google-contacts-connection.php` pattern
   - Store in user meta `_stadion_google_sheets_connection`
   - Methods: `get_connection()`, `save_connection()`, `delete_connection()`, `is_connected()`, `get_decrypted_credentials()`, `update_connection()`
   - Simpler than contacts: no sync_token, no sync_frequency - just credentials and connection state

3. Register the new class in `functions.php` in the `rondo_init()` function.
  </action>
  <verify>
    - `class-google-sheets-connection.php` exists with proper structure
    - `SHEETS_SCOPE` constant defined in GoogleOAuth class
    - No PHP syntax errors: `php -l includes/class-google-oauth.php && php -l includes/class-google-sheets-connection.php`
  </verify>
  <done>
    - Google Sheets scope constant available
    - Connection storage class created
    - OAuth methods for Sheets scope implemented
  </done>
</task>

<task type="auto">
  <name>Task 2: Create Google Sheets REST endpoints</name>
  <files>
    - includes/class-rest-google-sheets.php
    - functions.php
  </files>
  <action>
1. Create `class-rest-google-sheets.php` following `class-rest-google-contacts.php` pattern:
   - Namespace: `Stadion\REST`
   - Extend Base class (or implement permission callbacks directly)
   - Register routes:
     - `GET /rondo/v1/google-sheets/status` - Check if connected with sheets scope
     - `GET /rondo/v1/google-sheets/auth` - Initiate OAuth for sheets scope
     - `GET /rondo/v1/google-sheets/callback` - Handle OAuth callback (public)
     - `DELETE /rondo/v1/google-sheets/disconnect` - Remove sheets connection
     - `POST /rondo/v1/google-sheets/export-people` - Create sheet from people data

2. For `export-people` endpoint:
   - Accept JSON body with: `columns` (array of column IDs), `filters` (current filter params), `title` (optional sheet title)
   - Fetch people matching filters using same query logic as `useFilteredPeople` hook
   - Build column headers from column definitions
   - Build data rows from people records
   - Use `Google\Service\Sheets` to:
     - Create new spreadsheet with title "Leden Export - {date}"
     - Write header row + data rows
     - Auto-resize columns
   - Return `{ success: true, spreadsheet_url: "https://docs.google.com/spreadsheets/d/..." }`

3. Add class instantiation in `functions.php` `rondo_init()`.
  </action>
  <verify>
    - `php -l includes/class-rest-google-sheets.php` passes
    - Route registration can be verified by checking `wp rest-api` output on production after deploy
  </verify>
  <done>
    - REST endpoints for Sheets connection management
    - Export endpoint that creates spreadsheet and returns URL
  </done>
</task>

<task type="auto">
  <name>Task 3: Add export button to People list UI</name>
  <files>
    - src/api/client.js
    - src/pages/People/PeopleList.jsx
  </files>
  <action>
1. In `src/api/client.js`:
   - Add to `stadionApi` object:
     - `getSheetsStatus: () => api.get('/rondo/v1/google-sheets/status')`
     - `getSheetsAuthUrl: () => api.get('/rondo/v1/google-sheets/auth')`
     - `disconnectSheets: () => api.delete('/rondo/v1/google-sheets/disconnect')`
     - `exportPeopleToSheets: (data) => api.post('/rondo/v1/google-sheets/export-people', data)`

2. In `PeopleList.jsx`:
   - Import `FileSpreadsheet` from lucide-react for the export icon
   - Add query for sheets connection status: `useQuery({ queryKey: ['google-sheets-status'], queryFn: () => stadionApi.getSheetsStatus() })`
   - Add state for export loading: `const [isExporting, setIsExporting] = useState(false)`
   - Add export handler that:
     - Collects current visible columns from `visibleColumns` + 'name'
     - Collects current filter params from `searchParams`
     - Calls `exportPeopleToSheets` with columns, filters, and sorting
     - Opens returned `spreadsheet_url` in new tab
     - Shows toast on success/error
   - Add export button next to the Settings gear icon:
     - Only show if `sheetsStatus?.connected` is true
     - If not connected, show connect button that redirects to auth URL
     - Show spinner while exporting
     - Dutch label: "Exporteren naar Google Sheets" (tooltip), icon only on button
  </action>
  <verify>
    - `npm run lint` passes
    - `npm run build` succeeds
    - Export button appears in UI (manual verification after deploy)
  </verify>
  <done>
    - API client methods for Sheets endpoints
    - Export button in People list header
    - Export flow: click -> loading -> new tab with spreadsheet
  </done>
</task>

</tasks>

<verification>
1. PHP syntax check: `php -l includes/class-google-oauth.php && php -l includes/class-google-sheets-connection.php && php -l includes/class-rest-google-sheets.php`
2. Frontend build: `npm run build`
3. Lint check: `npm run lint`
4. Deploy and test OAuth flow for Sheets scope
5. Test export with various filters and column configurations
</verification>

<success_criteria>
- Google Sheets OAuth scope added to existing infrastructure
- REST endpoints for connection management and export
- Export button visible in People list when connected
- Clicking export creates new Google Sheet with current data and opens it
- Sheet contains header row with column names and data rows matching current view
</success_criteria>

<output>
After completion, create `.planning/quick/017-google-sheets-export-people-list/017-SUMMARY.md`
</output>
