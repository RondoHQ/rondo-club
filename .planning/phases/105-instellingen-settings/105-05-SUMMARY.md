---
phase: 105-instellingen-settings
plan: 05
subsystem: ui
tags: [react, i18n, dutch, localization, import, vcard, monica, google-contacts]

# Dependency graph
requires:
  - phase: 105-04
    provides: "Settings subpage translations"
provides:
  - "Import components fully translated to Dutch (vCard, Monica, Google Contacts)"
  - "Settings document titles in Dutch"
  - "Proper JSX escaping for Dutch apostrophes"
affects: [105-06]

# Tech tracking
tech-stack:
  added: []
  patterns: ["JSX entity escaping for Dutch apostrophes (&apos;)"]

key-files:
  created: []
  modified:
    - "src/components/import/VCardImport.jsx"
    - "src/components/import/MonicaImport.jsx"
    - "src/components/import/GoogleContactsImport.jsx"
    - "src/hooks/useDocumentTitle.js"
    - "src/pages/Settings/Labels.jsx"

key-decisions:
  - "Used &apos; entity for apostrophes in 'foto's' to satisfy linter"

patterns-established:
  - "Escape Dutch apostrophes with &apos; in JSX text"

# Metrics
duration: 7min
completed: 2026-01-25
---

# Phase 105 Plan 05: Import Components & Titles Summary

**Import workflows (vCard, Monica, Google Contacts) and Settings document titles fully translated to Dutch**

## Performance

- **Duration:** 7 min
- **Started:** 2026-01-25T19:34:06Z
- **Completed:** 2026-01-25T19:41:19Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- VCardImport component: All labels, messages, and validation text in Dutch
- MonicaImport component: All labels, file upload prompts, and URL input in Dutch
- GoogleContactsImport component: All labels, duplicate resolution UI, and export instructions in Dutch
- Settings route and page document titles updated from "Settings" to "Instellingen"
- Fixed JSX escaping for Dutch apostrophes in "foto's"

## Task Commits

Each task was committed atomically:

1. **Task 1: Translate Import Components** - `b45f6eb` (feat)
2. **Task 2: Translate Document Titles** - `50a8ee5` (feat)

## Files Created/Modified
- `src/components/import/VCardImport.jsx` - All UI text translated including success messages, validation, and file upload prompts
- `src/components/import/MonicaImport.jsx` - All UI text translated including Monica URL input and SQL file handling
- `src/components/import/GoogleContactsImport.jsx` - All UI text translated including duplicate resolution interface with "Bestaande bijwerken", "Nieuwe aanmaken", "Overslaan" buttons
- `src/hooks/useDocumentTitle.js` - Settings route title changed from "Settings" to "Instellingen"
- `src/pages/Settings/Labels.jsx` - Document title updated to "Labels - Instellingen"

## Decisions Made
- Used HTML entity `&apos;` for apostrophes in Dutch text (e.g., "foto's" → "foto&apos;s") to satisfy React/ESLint rules about unescaped entities in JSX

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed unescaped apostrophes in JSX**
- **Found during:** Task 1 (Import components translation)
- **Issue:** ESLint error `react/no-unescaped-entities` for apostrophes in "foto's"
- **Fix:** Replaced all instances of "foto's" with "foto&apos;s" using HTML entity
- **Files modified:** VCardImport.jsx, MonicaImport.jsx, GoogleContactsImport.jsx
- **Verification:** Lint passes, build succeeds
- **Committed in:** 50a8ee5 (Task 2 commit)

**2. [Rule 1 - Bug] Fixed unescaped quotes in JSX**
- **Found during:** Task 1 (GoogleContactsImport translation)
- **Issue:** ESLint error `react/no-unescaped-entities` for straight quotes in instructions
- **Fix:** Replaced quotes with `&ldquo;` and `&rdquo;` entities
- **Files modified:** GoogleContactsImport.jsx
- **Verification:** Lint passes, build succeeds
- **Committed in:** 50a8ee5 (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (2 JSX escaping bugs)
**Impact on plan:** Both auto-fixes necessary for code quality and linter compliance. No scope creep.

## Issues Encountered
None

## Next Phase Readiness
- All import workflows fully localized and ready for Dutch users
- Settings page document titles complete
- Ready to move to final Settings items (Phase 105-06)

---
*Phase: 105-instellingen-settings*
*Completed: 2026-01-25*
