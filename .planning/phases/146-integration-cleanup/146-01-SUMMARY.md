---
phase: 146-integration-cleanup
plan: 01
subsystem: ui
tags: [freescout, club-config, dynamic-integration]

# Dependency graph
requires:
  - phase: 144-backend-configuration-system
    provides: Club config API with FreeScout URL storage
  - phase: 145-frontend-color-refactor
    provides: window.stadionConfig club configuration object
provides:
  - Dynamic FreeScout URL integration reading from club config
  - Zero club-specific hardcoded references in source code
  - Fully generic, installable-by-any-club codebase
affects: [deployment, documentation, multi-tenant-future]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "External service URLs read from window.stadionConfig for club customization"
    - "Conditional link rendering based on both data presence AND config availability"

key-files:
  created: []
  modified:
    - src/pages/People/PersonDetail.jsx
    - src/hooks/useTheme.js
    - AGENTS.md
    - PERFORMANCE-FINDINGS.md

key-decisions:
  - "lighthouse-full.json excluded from AWC cleanup (historical test artifact)"
  - "Legacy awc→club migration removed (users migrated in Phase 145)"

patterns-established:
  - "Integration URLs externalized: check window.stadionConfig first, hide feature if not configured"

# Metrics
duration: 1min
completed: 2026-02-05
---

# Phase 146 Plan 01: Integration Cleanup Summary

**FreeScout URL externalized to club config API; zero AWC/svawc club-specific references remain in source code**

## Performance

- **Duration:** 1 minute
- **Started:** 2026-02-05T21:11:02Z
- **Completed:** 2026-02-05T21:12:37Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- FreeScout link in PersonDetail reads base URL from `window.stadionConfig.freescoutUrl`
- FreeScout link hidden when URL not configured (dual-condition check)
- Zero awc/svawc/AWC references in source code (verified by comprehensive grep scan)
- Documentation uses generic placeholders (DEPLOY_REMOTE_THEME_PATH, generic titles)

## Task Commits

Each task was committed atomically:

1. **Task 1: Externalize FreeScout URL in PersonDetail** - `f3b0de18` (feat)
2. **Task 2: Remove AWC references and update documentation** - `fc474115` (refactor)
3. **Task 3: Verify internal keys and final build** - (verification only, no commit)

**Plan metadata:** `e7179d73` (docs: complete plan)

## Files Created/Modified
- `src/pages/People/PersonDetail.jsx` - FreeScout URL reads from `window.stadionConfig.freescoutUrl`; link hidden when URL not configured
- `src/hooks/useTheme.js` - Removed legacy awc→club migration code (users migrated in Phase 145)
- `AGENTS.md` - Replaced hardcoded svawc.nl path with `$DEPLOY_REMOTE_THEME_PATH` env var
- `PERFORMANCE-FINDINGS.md` - Changed title from club-specific to generic "Stadion Dashboard"

## Decisions Made

**Lighthouse artifact exclusion:**
- `lighthouse-full.json` contains svawc.nl references but is historical test data, not source code
- Excluded from cleanup; not part of distributable theme
- Justification: Performance testing artifacts document actual test runs against specific URLs

**Migration code removal:**
- Removed awc→club migration from useTheme.js
- Safe to remove because Phase 145 already migrated all users
- Users who skipped Phase 145 will get default 'club' color (safe fallback)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. Build and verification completed successfully on first attempt.

## User Setup Required

None - no external service configuration required.

FreeScout URL is configured via Settings page (Phase 144), already documented in user setup for that phase.

## Verification Results

**ACF JSON scan:** Zero matches (clean)
**Source code scan:** Zero matches in source files (clean)
**Lighthouse artifact:** 7 matches (historical test data - excluded as non-source)
**Build:** Successful (2.86s)
**Lint:** 155 pre-existing errors, zero new errors introduced

## Next Phase Readiness

**Theme is now club-agnostic:**
- Zero hardcoded club-specific references in source code
- All external integrations read from club config API
- Documentation uses generic placeholders
- Installable by any sports club without code changes

**Ready for:**
- Multi-club deployment scenarios
- Open-source distribution
- Whitelabel/resale opportunities

**Blockers:** None

**Concerns:** None

---
*Phase: 146-integration-cleanup*
*Completed: 2026-02-05*
