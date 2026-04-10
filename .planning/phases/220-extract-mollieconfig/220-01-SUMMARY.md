---
phase: 220-extract-mollieconfig
plan: "01"
subsystem: finance-config
tags: [refactor, extraction, mollie, service-decomposition]
dependency_graph:
  requires: [219-01]
  provides: [Rondo\Finance\MollieConfig, Rondo\Finance\FinanceServices]
  affects: [includes/class-finance-config.php, includes/class-mollie-config.php, includes/class-finance-services.php]
tech_stack:
  added: [Rondo\Finance\MollieConfig, Rondo\Finance\FinanceServices]
  patterns: [static-service-locator, delegation-forwarder, pure-motion-refactor]
key_files:
  created:
    - includes/class-mollie-config.php
    - includes/class-finance-services.php
  modified:
    - includes/class-finance-config.php
decisions:
  - "Mollie extracted as pure motion refactor: FinanceConfig keeps one-line forwarders, callers rewired in Plan 02"
  - "normalize_accounts_for_storage and build_safe_accounts_from_storage made public on MollieConfig (dropped _mollie_ infix)"
  - "get_all_settings() inlines all 5 Mollie reads directly to FinanceServices::mollie()->X() — not via $this->get_mollie_X() forwarders"
  - "update_result:false on round-trip is expected — WordPress update_option returns false when value is unchanged (no-op)"
metrics:
  duration_seconds: 1210
  completed_date: "2026-04-10"
  tasks_completed: 3
  files_changed: 5
---

# Phase 220 Plan 01: Extract MollieConfig + Scaffold FinanceServices Summary

**One-liner:** Moved 10 public + 5 private Mollie methods from FinanceConfig into a new `Rondo\Finance\MollieConfig` class; introduced `Rondo\Finance\FinanceServices` static locator; FinanceConfig Mollie methods are now one-line forwarders, snapshot diff is byte-clean.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Create MollieConfig class with every Mollie method moved byte-for-byte | 07abd88c | includes/class-mollie-config.php (created, 453 lines) |
| 2 | Create FinanceServices locator + rewire FinanceConfig internals | 07abd88c | includes/class-finance-services.php (created), includes/class-finance-config.php (modified) |
| 3 | Deploy + snapshot diff + form round-trip + atomic commit | 07abd88c | .planning/phases/220-extract-mollieconfig/220-01-post-plan01.json, 220-01-form-roundtrip.log |

## What Was Built

### `includes/class-mollie-config.php` (new, 453 lines)
- `Rondo\Finance\MollieConfig` class with constructor accepting `FinanceConfig $finance_config`
- 6 option constants (OPTION_MOLLIE_ACCOUNTS, OPTION_MOLLIE_REDIRECT_URL, OPTION_MOLLIE_DEFAULT_MEMBERSHIP_ACCOUNT_ID, OPTION_MOLLIE_DEFAULT_DISCIPLINE_ACCOUNT_ID, OPTION_MOLLIE_DEFAULT_MANUAL_ACCOUNT_ID, OPTION_ACTIVE_PAYMENT_PROVIDER)
- 10 public methods: `get_mollie_accounts`, `get_mollie_account_by_id`, `get_mollie_api_key_for_account`, `get_usable_mollie_accounts`, `get_default_mollie_account_id`, `get_default_mollie_account`, `get_payment_account_snapshot_for_invoice_type`, `get_mollie_redirect_url`, `get_active_payment_provider`, `update_active_payment_provider`
- 2 public save-path helpers (renamed from `normalize_mollie_accounts_for_storage` / `build_safe_mollie_accounts_from_storage` to `normalize_accounts_for_storage` / `build_safe_accounts_from_storage`)
- 3 private helpers: `get_mollie_account_record_by_id`, `decrypt_mollie_account_api_key`, `derive_mollie_environment`
- `get_payment_account_snapshot_for_invoice_type` uses `$this->finance_config->get_org_name()` and `$this->finance_config->get_iban()` (cross-service dependency preserved until Phase 223)

### `includes/class-finance-services.php` (new, 77 lines)
- `Rondo\Finance\FinanceServices` static locator mirroring `Rondo\Fees\FeeServices` from v33.0
- `mollie()` lazy accessor: constructs `new MollieConfig( new FinanceConfig() )` on first call, caches per request
- `reset()` method for tests and long-running CLI processes

### `includes/class-finance-config.php` (modified)
- Added `use Rondo\Finance\FinanceServices;`
- All 10 Mollie public methods replaced with one-line forwarders to `FinanceServices::mollie()->X()`
- All 5 private Mollie helpers deleted (bodies now live only in MollieConfig)
- `get_all_settings()`: every Mollie read inlined directly to `FinanceServices::mollie()->X()` (no `$this->get_mollie_*` residuals)
- `update_settings()`: `normalize_mollie_accounts_for_storage` → `FinanceServices::mollie()->normalize_accounts_for_storage()`, `build_safe_mollie_accounts_from_storage` → `FinanceServices::mollie()->build_safe_accounts_from_storage()`, `update_active_payment_provider` → `FinanceServices::mollie()->update_active_payment_provider()`

## Validation

### Snapshot Diff (FIN-12)
```
bin/finance-settings-snapshot.sh → .planning/phases/220-extract-mollieconfig/220-01-post-plan01.json
diff against v34.0-baseline.json: CLEAN (zero output, exit 0)
```

### Form Round-trip (FIN-11)
```
Production has 4 real Mollie accounts (no fake needed).
update_settings() round-trip: accounts_identical:YES, before_len=after_len=1359
update_result:false (expected — WordPress returns false when value is unchanged)
Post-roundtrip snapshot diff: CLEAN
```

### Code Quality
- `php -l` passes on all three files
- `composer lint` passes on all three files

## Deviations from Plan

None — plan executed exactly as written.

## Key Decisions

1. **`update_result:false` is correct** — WordPress `update_option()` returns `false` when the new value is identical to the stored value. The round-trip payload was reconstructed from existing DB values so no actual write occurred — exactly as intended for a no-op round-trip.
2. **Private helpers deleted via single Edit calls** — the two helper blocks (`normalize_mollie_accounts_for_storage` + `build_safe_mollie_accounts_from_storage`, and then `decrypt_mollie_account_api_key` + `derive_mollie_environment`) were removed in two Edit operations. `get_mollie_account_record_by_id` was removed together with the `get_mollie_account_by_id` refactor (it was no longer needed as a private helper once the public method became a forwarder).

## Self-Check: PASSED

- includes/class-mollie-config.php: FOUND
- includes/class-finance-services.php: FOUND
- .planning/phases/220-extract-mollieconfig/220-01-post-plan01.json: FOUND
- .planning/phases/220-extract-mollieconfig/220-01-form-roundtrip.log: FOUND
- commit 07abd88c: FOUND
