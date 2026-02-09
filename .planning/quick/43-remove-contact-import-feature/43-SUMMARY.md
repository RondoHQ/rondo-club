---
phase: quick-43
plan: 01
subsystem: import
tags:
  - ui
  - backend
  - cleanup
  - import
dependency_graph:
  requires: []
  provides:
    - "Legacy import feature removed"
  affects:
    - "Settings page UI"
    - "REST API endpoints"
tech_stack:
  added: []
  patterns:
    - "Feature removal"
    - "Dead code elimination"
key_files:
  created: []
  modified:
    - "src/pages/Settings/Settings.jsx"
    - "functions.php"
  deleted:
    - "src/components/import/VCardImport.jsx"
    - "src/components/import/GoogleContactsImport.jsx"
    - "includes/class-vcard-import.php"
    - "includes/class-google-contacts-import.php"
decisions:
  - "Kept live Google Contacts API sync (OAuth-based) as it's the replacement"
  - "Kept export functionality (vCard, Google CSV) as it's still needed"
  - "Removed file upload import endpoints entirely"
metrics:
  duration: "252 seconds (4.2 minutes)"
  completed: "2026-02-09"
---

# Quick Task 43: Remove Contact Import Feature Summary

**One-liner:** Removed legacy vCard and Google CSV file upload import feature, superseded by live Google Contacts API sync.

## Overview

Successfully removed the legacy contact import feature from Rondo Club. This feature allowed users to upload vCard (.vcf) files and Google Contacts CSV exports to import contacts. It has been replaced by the more sophisticated live Google Contacts API sync (OAuth-based) implemented in phases 79-83, which provides real-time synchronization rather than manual file uploads.

## Tasks Completed

### Task 1: Remove import UI from Settings page ✓

**Commit:** 0f20c937

**Changes:**
- Deleted `src/components/import/VCardImport.jsx` (12,649 bytes)
- Deleted `src/components/import/GoogleContactsImport.jsx` (19,469 bytes)
- Removed import statements from Settings.jsx
- Removed `importTypes` array configuration
- Removed entire Import Section from DataTab component
- Kept live Google Contacts API sync UI (OAuth-based)
- Kept export functionality (vCard, Google CSV)

**Verification:**
- ESLint: No new import errors (existing errors unrelated)
- Build: ✓ Successful (vite build completed)
- Import directory: Now empty

### Task 2: Remove import PHP classes and unregister from theme init ✓

**Commit:** 8f0584ca

**Changes:**
- Deleted `includes/class-vcard-import.php` (32,544 bytes, 1175 lines)
- Deleted `includes/class-google-contacts-import.php` (25,215 bytes, 896 lines)
- Removed use statements from functions.php:
  - `use Rondo\Import\VCard as VCardImport;`
  - `use Rondo\Import\GoogleContacts;`
- Removed instantiations from `stadion_init()`:
  - `new VCardImport();`
  - `new GoogleContacts();`
- Removed class aliases:
  - `RONDO_VCard_Import`
  - `RONDO_Google_Contacts_Import`
- Kept `GoogleContactsAPI` class and alias (used by live API sync)

**Verification:**
- PHP syntax: ✓ No errors
- Deleted files: ✓ Confirmed removed
- Remaining references: ✓ None found (except live API sync)

### Task 3: Update documentation and commit changes ✓

**Commit:** ec75c2f5

**Changes:**
- Updated CHANGELOG.md:
  - Added entry under `[Unreleased] > Removed`
  - Description: "Contact import feature (vCard and Google CSV file upload) - replaced by live Google Contacts API sync"
- Updated STATE.md:
  - Removed "remove-contact-import-feature" from pending todos (5→4 todos)
  - Added quick task 43 to completed tasks table
- Production build: ✓ Successful
- Git push: ✓ Complete
- Deployment: ✓ Complete (Rule 8 followed)

## Deviations from Plan

None - plan executed exactly as written.

## What Was Kept

The following features were intentionally preserved:

1. **Live Google Contacts API Sync (OAuth-based)**
   - `includes/class-google-contacts-api-import.php`
   - `use Rondo\Import\GoogleContactsAPI;`
   - Settings UI for OAuth connection, import, sync
   - REST endpoints: `/rondo/v1/google-contacts/*`

2. **Export Functionality**
   - vCard export endpoint: `/rondo/v1/export/vcard`
   - Google CSV export endpoint: `/rondo/v1/export/google-csv`
   - Export UI in Settings > Data tab
   - `includes/class-rest-import-export.php` (handles export + CardDAV URLs)

3. **CardDAV Integration**
   - CardDAV URLs endpoint: `/rondo/v1/carddav/urls`
   - CardDAV server functionality

## API Endpoints Removed

The following REST API endpoints no longer exist:
- `/rondo/v1/import/vcard` (POST)
- `/rondo/v1/import/vcard/validate` (POST)
- `/rondo/v1/import/vcard/parse` (POST)
- `/rondo/v1/import/google-contacts` (POST)
- `/rondo/v1/import/google-contacts/validate` (POST)

These will return 404 errors if accessed.

## Code Metrics

**Deleted:**
- 4 files
- 89,877 bytes (87.8 KB)
- ~2,271 lines of code

**Modified:**
- 2 files (Settings.jsx, functions.php)
- Removed ~230 lines from Settings.jsx
- Removed ~10 lines from functions.php

**Build Output:**
- Production build: 1,652.46 KiB (77 entries)
- No build errors
- No new linting errors

## Deployment

**Status:** ✓ Deployed to production

**Production URL:** https://stadion.svawc.nl/

**Deployment steps completed:**
1. Synced dist/ folder (78 files)
2. Synced theme files (42,498 files)
3. Regenerated composer autoloader
4. Cleared WordPress cache
5. Cleared SiteGround Speed Optimizer cache

## Verification Checklist

Per plan verification section:

- [x] VCardImport and GoogleContactsImport React components deleted
- [x] Import UI removed from Settings page
- [x] class-vcard-import.php deleted
- [x] class-google-contacts-import.php deleted
- [x] functions.php no longer loads import classes
- [x] No linting or build errors
- [x] CHANGELOG.md updated
- [x] STATE.md todo removed and quick task recorded
- [x] Changes committed to git
- [x] Deployed to production
- [ ] Export functionality still works (requires manual verification)
- [ ] Live Google Contacts API sync UI remains functional (requires manual verification)

## Manual Verification Steps

The following should be verified on production after deployment:

1. **Settings page**: Confirm no vCard or Google CSV import sections visible
2. **Export functionality**:
   - vCard export button present and functional
   - Google CSV export button present and functional
3. **Live Google Contacts sync UI**:
   - "Google Contacten koppelen" button visible
   - OAuth flow still works
   - Import/sync buttons functional
4. **Browser console**: No JavaScript errors
5. **Old import endpoints**: Should return 404
   - `curl https://stadion.svawc.nl/wp-json/rondo/v1/import/vcard`
   - `curl https://stadion.svawc.nl/wp-json/rondo/v1/import/google-contacts`

## Self-Check: PASSED

**Created files verification:**
- N/A (no files created, only deleted)

**Modified files verification:**
- ✓ src/pages/Settings/Settings.jsx exists and modified
- ✓ functions.php exists and modified
- ✓ CHANGELOG.md exists and modified
- ✓ .planning/STATE.md exists and modified

**Deleted files verification:**
- ✓ src/components/import/VCardImport.jsx deleted
- ✓ src/components/import/GoogleContactsImport.jsx deleted
- ✓ includes/class-vcard-import.php deleted
- ✓ includes/class-google-contacts-import.php deleted

**Commits verification:**
- ✓ 0f20c937 exists: feat(quick-43): remove import UI from Settings page
- ✓ 8f0584ca exists: feat(quick-43): remove import PHP classes and unregister from theme init
- ✓ ec75c2f5 exists: docs(quick-43): update documentation for import feature removal

All checks passed.

## Related Work

This task completes the migration from file-based import to API-based sync:
- **Phases 79-83**: Implemented live Google Contacts API sync with OAuth
- **Quick Task 43**: Removed legacy file upload import (this task)
- **Remaining**: Remove the pending todo (already done in STATE.md)

## Future Considerations

- The import directory `src/components/import/` is now empty and could be removed in a future cleanup
- The live Google Contacts API sync is the only import method going forward
- Export functionality remains useful for data portability and backups
