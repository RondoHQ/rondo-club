---
phase: 186-sdk-financeconfig-mollieclient
plan: 01
subsystem: payments

tags: [mollie, composer, php, encryption, sodium]

# Dependency graph
requires: []
provides:
  - Mollie PHP SDK v3.9.0 installed via Composer
  - FinanceConfig.get_mollie_api_key() / update_mollie_api_key() with sodium encryption
  - FinanceConfig.get_active_payment_provider() / update_active_payment_provider() with allowlist
  - FinanceConfig.derive_mollie_environment() from API key prefix
  - get_all_settings() exposes mollie_has_api_key (bool), mollie_environment, active_payment_provider
  - MollieClient wrapper class at includes/class-mollie-client.php
affects:
  - 187-mollie-payment
  - 188-mollie-webhook
  - 189-mollie-ui
  - 190-mollie-integration

# Tech tracking
tech-stack:
  added:
    - mollie/mollie-api-php v3.9.0
    - nyholm/psr7 (Mollie SDK dependency)
    - composer/ca-bundle (Mollie SDK dependency)
  patterns:
    - Sodium encryption for Mollie API key (same pattern as Rabobank credentials)
    - Environment derivation from API key prefix (live_ = live, else = test)
    - Boolean exposure in settings API instead of raw credentials (mollie_has_api_key)
    - Provider allowlist validation ['rabobank', 'mollie'] in update_active_payment_provider()
    - Non-singleton MollieClient: reads fresh key on each instantiation

key-files:
  created:
    - includes/class-mollie-client.php
  modified:
    - composer.json
    - composer.lock
    - vendor/composer/autoload_classmap.php
    - vendor/composer/autoload_files.php
    - vendor/composer/autoload_psr4.php
    - vendor/composer/autoload_static.php
    - vendor/composer/installed.json
    - vendor/composer/installed.php
    - includes/class-finance-config.php

key-decisions:
  - "Sodium encryption for Mollie API key (same pattern as Rabobank credentials)"
  - "MollieClient is not a singleton — each instantiation reads a fresh key from FinanceConfig"
  - "Active payment provider defaults to 'rabobank' so existing behavior is unchanged"
  - "Boolean mollie_has_api_key in get_all_settings() — raw key never exposed via REST"
  - "derive_mollie_environment() derives test/live from key prefix (live_ = live)"

patterns-established:
  - "MollieClient instantiation pattern: new MollieClient()->get() for API calls in Phase 187+"
  - "Payment provider allowlist: validate against ['rabobank', 'mollie'] before storing"

# Metrics
duration: 3min
completed: 2026-02-17
---

# Phase 186 Plan 01: SDK + FinanceConfig + MollieClient Summary

**Mollie PHP SDK v3.9.0 installed via Composer, FinanceConfig extended with encrypted API key storage and provider settings, MollieClient wrapper class created — foundation for Phases 187-190.**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-02-17T07:47:05Z
- **Completed:** 2026-02-17T07:50:05Z
- **Tasks:** 2
- **Files modified:** 9

## Accomplishments

- Installed `mollie/mollie-api-php:^3.9` (v3.9.0) via Composer — adds Mollie API client to the project
- Extended `FinanceConfig` with 2 new constants, 4 public methods + 1 private helper for encrypted Mollie API key storage, provider selection, and environment derivation
- Created `MollieClient` wrapper class that reads the key from FinanceConfig and returns a configured `MollieApiClient`

## Task Commits

Each task was committed atomically:

1. **Task 1: Install Mollie SDK + Extend FinanceConfig** - `e15a1cda` (feat)
2. **Task 2: Create MollieClient Wrapper Class** - `5e1388ad` (feat)

**Plan metadata:** (see final metadata commit below)

## Files Created/Modified

- `composer.json` - Added `mollie/mollie-api-php: ^3.9` dependency
- `composer.lock` - Updated lock file with Mollie SDK + dependencies
- `vendor/composer/autoload_*.php` - Regenerated autoloader files
- `vendor/composer/installed.json` / `installed.php` - Package registry updated
- `includes/class-finance-config.php` - Added 2 constants, 5 methods, 3 new settings keys
- `includes/class-mollie-client.php` (new) - MollieClient wrapper class

## Decisions Made

- Sodium encryption for Mollie API key — same pattern as existing Rabobank credentials, consistent with project's credential storage approach
- `MollieClient` is not a singleton — each instantiation reads a fresh API key from `FinanceConfig`, keeping coupling low
- `get_active_payment_provider()` defaults to `'rabobank'` — ensures no behavioral change for existing sites without Mollie configured
- `get_all_settings()` exposes only `mollie_has_api_key` (bool) — raw key never exposed via the settings REST endpoint
- `derive_mollie_environment()` is private, derives `live`/`test` from the `live_` key prefix

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

- `vendor/` directory is gitignored, but `vendor/composer/` autoloader files are tracked. Used `git add -f` to stage the tracked autoloader files specifically.

## User Setup Required

None — no external service configuration required in this phase. Mollie API key will be configured via the Finance Settings UI (Phase 189).

## Next Phase Readiness

- Phase 187 (MolliePayment): `MollieClient` and all `FinanceConfig` Mollie methods are ready. `functions.php` class loading will be added in Phase 187.
- Phase 188 (MollieWebhook): Same dependency satisfied
- No blockers

## Self-Check: PASSED

- includes/class-mollie-client.php: FOUND
- includes/class-finance-config.php: FOUND
- .planning/phases/186-sdk-financeconfig-mollieclient/186-01-SUMMARY.md: FOUND
- commit e15a1cda (Task 1): FOUND
- commit 5e1388ad (Task 2): FOUND

---
*Phase: 186-sdk-financeconfig-mollieclient*
*Completed: 2026-02-17*
