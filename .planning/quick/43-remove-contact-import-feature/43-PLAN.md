---
phase: quick-43
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Settings/Settings.jsx
  - src/components/import/VCardImport.jsx
  - src/components/import/GoogleContactsImport.jsx
  - includes/class-vcard-import.php
  - includes/class-google-contacts-import.php
  - includes/class-google-contacts-api-import.php
  - functions.php
autonomous: true

must_haves:
  truths:
    - "Settings page no longer shows vCard or Google CSV import UI"
    - "Import API endpoints no longer exist"
    - "Import classes are no longer loaded"
  artifacts:
    - path: "src/pages/Settings/Settings.jsx"
      provides: "Settings page without import components"
      min_lines: 2000
    - path: "functions.php"
      provides: "Theme initialization without import classes"
      contains: "stadion_init"
  key_links:
    - from: "src/pages/Settings/Settings.jsx"
      to: "VCardImport component"
      via: "import statement"
      pattern: "import.*VCardImport"
      must_be: "removed"
    - from: "functions.php"
      to: "VCardImport class"
      via: "new instantiation"
      pattern: "new VCardImport"
      must_be: "removed"
---

<objective>
Remove the legacy contact import feature from Rondo Club.

Purpose: This feature is no longer needed as the Google Contacts API integration (phases 79-83) provides a better sync solution. The old file upload import (vCard and Google CSV) is being replaced by live API sync.

Output: Settings page without import UI, import PHP classes deleted, import API endpoints removed.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md

**Current state:** Settings page contains two import sections:
1. VCardImport component - for .vcf file uploads
2. GoogleContactsImport component - for Google CSV file uploads

**Backend classes:**
- `includes/class-vcard-import.php` - vCard parsing and import
- `includes/class-google-contacts-import.php` - Google CSV parsing
- `includes/class-google-contacts-api-import.php` - KEEP (used by live API sync)
- `includes/class-rest-import-export.php` - KEEP (handles CardDAV URLs and vCard/CSV export)

**API endpoints to remove:**
- `/rondo/v1/import/vcard` (POST)
- `/rondo/v1/import/vcard/validate` (POST)
- `/rondo/v1/import/vcard/parse` (POST)
- `/rondo/v1/import/google-contacts` (POST)
- `/rondo/v1/import/google-contacts/validate` (POST)

**Keep these (export functionality):**
- `/rondo/v1/export/vcard` (GET)
- `/rondo/v1/export/google-csv` (GET)
- `/rondo/v1/carddav/urls` (GET)
</context>

<tasks>

<task type="auto">
  <name>Remove import UI from Settings page</name>
  <files>
    src/pages/Settings/Settings.jsx
    src/components/import/VCardImport.jsx
    src/components/import/GoogleContactsImport.jsx
  </files>
  <action>
1. Remove VCardImport component file completely (`src/components/import/VCardImport.jsx`)
2. Remove GoogleContactsImport component file completely (`src/components/import/GoogleContactsImport.jsx`)
3. In `src/pages/Settings/Settings.jsx`:
   - Remove imports: `import VCardImport from '@/components/import/VCardImport';`
   - Remove imports: `import GoogleContactsImport from '@/components/import/GoogleContactsImport';`
   - Find and remove the settings configuration entries that reference these components (likely in a settings sections array around line 2871-2878 based on grep results)
   - Do NOT remove the live Google Contacts API integration UI (the OAuth-based sync that uses `handleConnectGoogleContacts`, `handleImportGoogleContacts`, etc.)
   - Keep all export functionality (vCard export, Google CSV export, CardDAV URLs)
  </action>
  <verify>
1. Run `npm run lint` to ensure no import errors
2. Run `npm run build` to verify the frontend compiles
3. Check that Settings.jsx no longer imports VCardImport or GoogleContactsImport
  </verify>
  <done>
- VCardImport.jsx and GoogleContactsImport.jsx files deleted
- Settings.jsx no longer references these components
- No linting or build errors
  </done>
</task>

<task type="auto">
  <name>Remove import PHP classes and unregister from theme init</name>
  <files>
    includes/class-vcard-import.php
    includes/class-google-contacts-import.php
    functions.php
  </files>
  <action>
1. Delete `includes/class-vcard-import.php` (1175 lines)
2. Delete `includes/class-google-contacts-import.php` (896 lines)
3. In `functions.php`:
   - Remove the `use` statement for VCardImport: `use Rondo\Import\VCard as VCardImport;` (around line 55)
   - Remove the `use` statement for GoogleContacts: `use Rondo\Import\GoogleContacts;` (around line 56)
   - In the `stadion_init()` function, remove these instantiations (around lines 371-372):
     ```php
     new VCardImport();
     new GoogleContacts();
     ```
   - DO NOT remove `use Rondo\Import\GoogleContactsAPI;` - this is used by the live API sync
   - DO NOT remove any instantiations of GoogleContactsAPI or GoogleContactsSync

**Note:** The class-rest-import-export.php should remain as it provides:
- vCard export endpoint (used)
- Google CSV export endpoint (used)
- CardDAV URLs endpoint (used)
- These export features are separate from import and still needed
  </action>
  <verify>
1. Run `php -l functions.php` to check for syntax errors
2. Verify the deleted classes no longer exist: `ls includes/class-vcard-import.php includes/class-google-contacts-import.php` should return "No such file"
3. Grep for any remaining references: `grep -r "VCardImport\|class-vcard-import\|GoogleContacts.*Import" includes/ src/ --exclude-dir=node_modules`
  </verify>
  <done>
- class-vcard-import.php deleted
- class-google-contacts-import.php deleted
- functions.php no longer references these classes
- No PHP syntax errors
- No remaining import references in codebase
  </done>
</task>

<task type="auto">
  <name>Update documentation and commit changes</name>
  <files>
    CHANGELOG.md
    .planning/STATE.md
  </files>
  <action>
1. Add entry to CHANGELOG.md under `## [Unreleased]` > `### Removed`:
   ```
   - Contact import feature (vCard and Google CSV file upload) - replaced by live Google Contacts API sync
   ```

2. Update `.planning/STATE.md`:
   - Remove the "remove-contact-import-feature" todo from pending list (it's now complete)
   - Add entry to Quick Tasks Completed table:
     ```
     | 43 | Remove contact import feature | 2026-02-09 | [commit] | [43-remove-contact-import-feature](./quick/43-remove-contact-import-feature/) |
     ```

3. Run `npm run build` to ensure production assets are updated

4. Commit all changes:
   ```bash
   git add -A
   git commit -m "$(cat <<'EOF'
   feat(quick-43): remove legacy contact import feature

   Removed vCard and Google CSV file upload import functionality.
   This has been superseded by the live Google Contacts API sync
   implemented in phases 79-83.

   Removed:
   - VCardImport React component
   - GoogleContactsImport React component
   - class-vcard-import.php (vCard parsing)
   - class-google-contacts-import.php (CSV parsing)
   - Import API endpoints

   Kept:
   - Export functionality (vCard, Google CSV)
   - CardDAV URLs endpoint
   - Live Google Contacts API sync (OAuth-based)

   Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>
   EOF
   )"
   ```

**Note:** Per Rule 8, deploy to production BEFORE presenting for verification. Export feature and live sync remain functional.
  </action>
  <verify>
1. Check CHANGELOG.md contains the removal entry
2. Check STATE.md no longer lists the todo as pending
3. Verify git commit was created: `git log -1 --oneline`
4. Verify build output exists: `ls dist/assets/index-*.js`
  </verify>
  <done>
- CHANGELOG.md updated
- STATE.md updated with completed quick task
- Production build created
- Git commit created with proper message
  </done>
</task>

</tasks>

<verification>
**Manual checks after deployment:**

1. Visit Settings page - confirm no vCard or Google CSV import sections visible
2. Confirm export functionality still works:
   - vCard export button present and functional
   - Google CSV export button present and functional
3. Confirm live Google Contacts sync UI remains intact:
   - "Google Contacten koppelen" button visible
   - OAuth flow still works
   - Import/sync buttons functional
4. Check browser console for any JavaScript errors
5. Attempt to access old import endpoints - should return 404:
   - `curl https://[site]/wp-json/rondo/v1/import/vcard` (should be 404)
   - `curl https://[site]/wp-json/rondo/v1/import/google-contacts` (should be 404)
</verification>

<success_criteria>
- [ ] VCardImport and GoogleContactsImport React components deleted
- [ ] Import UI removed from Settings page
- [ ] class-vcard-import.php deleted
- [ ] class-google-contacts-import.php deleted
- [ ] functions.php no longer loads import classes
- [ ] No linting or build errors
- [ ] CHANGELOG.md updated
- [ ] STATE.md todo removed and quick task recorded
- [ ] Changes committed to git
- [ ] Export functionality still works (vCard, Google CSV, CardDAV)
- [ ] Live Google Contacts API sync UI remains functional
</success_criteria>

<output>
After completion, create `.planning/quick/43-remove-contact-import-feature/43-SUMMARY.md`
</output>
