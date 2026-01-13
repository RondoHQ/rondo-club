---
phase: 21-phpunit-setup
plan: 01
subsystem: testing
tags: [phpunit, wp-browser, codeception, wploader]

# Dependency graph
requires:
  - phase: none
    provides: none (first testing phase)
provides:
  - PHPUnit testing infrastructure via wp-browser/Codeception
  - WPLoader module configuration for WordPress integration tests
  - CaelisTestCase base class with factory helpers
  - Test database (caelis_test) isolation
affects: [22-access-control-tests, 23-rest-api-tests]

# Tech tracking
tech-stack:
  added: [lucatume/wp-browser ^4.5, codeception 5.x, phpunit 11.x]
  patterns: [WPTestCase base class, factory methods for fixtures]

key-files:
  created:
    - codeception.yml
    - tests/.env.testing
    - tests/Support/CaelisTestCase.php
    - tests/Support/Helper/Wpunit.php
    - tests/Wpunit/SmokeTest.php
  modified:
    - composer.json
    - .gitignore

key-decisions:
  - "wp-browser 4.5.10 with Codeception 5.x and PHPUnit 11.x"
  - "WPLoader with loadOnly: false for transaction rollback isolation"
  - "MySQL via Homebrew for local test database"

patterns-established:
  - "CaelisTestCase as base class for all theme tests"
  - "Factory helpers: createPerson(), createOrganization(), createCaelisUser(), createImportantDate()"
  - "Smoke tests verify environment before writing feature tests"

issues-created: []

# Metrics
duration: 8min
completed: 2026-01-13
---

# Phase 21 Plan 01: PHPUnit Setup Summary

**wp-browser 4.5.10 with Codeception configured, 10 smoke tests passing verifying WordPress, theme, CPTs, ACF, and factory methods**

## Performance

- **Duration:** 8 min
- **Started:** 2026-01-13T21:39:32Z
- **Completed:** 2026-01-13T21:47:55Z
- **Tasks:** 3
- **Files modified:** 8

## Accomplishments

- Installed wp-browser 4.5.10 with Codeception 5.3.3 and PHPUnit 11.5.46
- Created test database `caelis_test` via Homebrew MySQL
- Configured WPLoader module with theme symlink and ACF Pro activation
- Built CaelisTestCase base class with factory helpers for all CPTs
- All 10 smoke tests passing in 1.4 seconds

## Task Commits

Each task was committed atomically:

1. **Task 1: Install wp-browser and create test database** - `7345178` (chore)
2. **Task 2: Configure Codeception with WPLoader** - `7d69baf` (feat)
3. **Task 3: Create base test case and smoke test** - `dc5592e` (feat)

**Plan metadata:** TBD (docs: complete plan)

## Files Created/Modified

- `composer.json` - Added wp-browser dev dependency and test scripts
- `composer.lock` - Locked 74 new packages
- `codeception.yml` - WPLoader configuration with env params
- `tests/.env.testing` - Template for test database config
- `tests/.env` - Local config (gitignored)
- `tests/Support/CaelisTestCase.php` - Base test case with factory helpers
- `tests/Support/Helper/Wpunit.php` - Custom helper module
- `tests/Wpunit/SmokeTest.php` - 10 smoke tests
- `.gitignore` - Added tests/.env and tests/_output/

## Decisions Made

- **MySQL via Homebrew:** Local MySQL CLI wasn't available, installed via `brew install mysql` for test database creation
- **Theme symlink:** Created symlink from WordPress themes directory to Caelis development folder for WPLoader to find theme
- **wp-browser 4.5.10:** Latest stable version with Codeception 5.x and PHPUnit 11.x

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Installed MySQL via Homebrew**
- **Found during:** Task 1 (test database creation)
- **Issue:** MySQL CLI not available on system, couldn't create caelis_test database
- **Fix:** Installed MySQL via `brew install mysql` and started service
- **Verification:** Database created successfully, tests can connect

**2. [Rule 3 - Blocking] Created theme symlink**
- **Found during:** Task 3 (smoke test execution)
- **Issue:** WPLoader couldn't find theme "caelis" in WordPress themes directory
- **Fix:** Created symlink `/Users/joostdevalk/Local Sites/personal-crm/app/public/wp-content/themes/caelis -> /Users/joostdevalk/Code/caelis`
- **Verification:** All 10 tests pass

### Deferred Enhancements

None - plan executed with blocking issues resolved.

---

**Total deviations:** 2 blocking issues auto-fixed
**Impact on plan:** Both fixes necessary to complete setup. No scope creep.

## Issues Encountered

None beyond the blocking issues resolved above.

## Next Phase Readiness

- PHPUnit infrastructure complete and working
- CaelisTestCase base class ready for Phase 22 access control tests
- Factory helpers available: createPerson(), createOrganization(), createCaelisUser(), createImportantDate()
- Test database `caelis_test` configured with transaction rollback isolation

---
*Phase: 21-phpunit-setup*
*Completed: 2026-01-13*
