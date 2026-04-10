---
phase: 220-extract-mollieconfig
plan: "02"
subsystem: payments
tags: [mollie, finance, refactoring, php, extraction]

# Dependency graph
requires:
  - phase: 220-extract-mollieconfig/220-01
    provides: MollieConfig class with all business logic, FinanceServices::mollie() locator, 10 Mollie public forwarders on FinanceConfig
  - phase: 219-finance-settings-snapshot-harness/219-01
    provides: bin/finance-settings-snapshot.sh + v34.0-baseline.json snapshot
provides:
  - All 7 Mollie consumer files (MolliePayment, MollieWebhook, InstallmentPaymentService, PublicPaymentPage, RestInvoices, InvoicePdfGenerator, BulkInvoiceCreator) rewired to FinanceServices::mollie()->X()
  - FinanceConfig with ZERO Mollie public methods (all 10 forwarders deleted)
  - FIN-02 fully satisfied: FinanceConfig is Mollie-free except for internal option constants and locator calls inside get_all_settings()/update_settings()
affects: [220-03, 221-extract-emailtemplates, 224-retire-financeconfig]

# Tech tracking
tech-stack:
  added: []
  patterns: [FinanceServices static locator pattern applied across all Mollie consumers, pure-motion refactor with snapshot harness regression guard]

key-files:
  created:
    - .planning/phases/220-extract-mollieconfig/220-02-post-plan02.json
    - .planning/phases/220-extract-mollieconfig/220-02-form-roundtrip.log
  modified:
    - includes/class-mollie-payment.php
    - includes/class-mollie-webhook.php
    - includes/class-installment-payment-service.php
    - includes/class-bulk-invoice-creator.php
    - includes/class-public-payment-page.php
    - includes/class-rest-invoices.php
    - includes/class-invoice-pdf-generator.php
    - includes/class-finance-config.php

key-decisions:
  - "resolve_payment_account_for_payload in RestInvoices: dropped FinanceConfig parameter from signature and updated the single caller — clean over backward-compat"
  - "Mollie option constants (OPTION_MOLLIE_ACCOUNTS etc.) kept in FinanceConfig — they are still referenced internally by update_settings(); external grep returned zero hits but internal use remains"
  - "get_iban() and get_setting('mollie_accounts') in FinanceConfig fixed before deleting forwarders — two $this-> residuals discovered and replaced with FinanceServices::mollie() calls inline"

patterns-established:
  - "Mollie-only vs mixed consumer split: Mollie-only files lose their FinanceConfig import entirely; mixed files keep FinanceConfig for non-Mollie methods only"
  - "Atomic commit bundles all caller rewires + forwarder deletion — never delete a forwarder before all callers are switched"

requirements-completed: [FIN-02]

# Metrics
duration: 25min
completed: 2026-04-10
---

# Phase 220 Plan 02: Extract MollieConfig (Caller Rewire) Summary

**7 Mollie consumer files rewired to FinanceServices::mollie() and all 10 FinanceConfig Mollie forwarders deleted; snapshot diff and form round-trip byte-identical, FIN-02 fully satisfied**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-04-10T08:00:00Z
- **Completed:** 2026-04-10T08:25:00Z
- **Tasks:** 3 (Task 1a, Task 1b, Task 2 — committed atomically as one)
- **Files modified:** 8 code files + 2 evidence artifacts

## Accomplishments

- Rewired 4 Mollie-only consumers (MolliePayment, MollieWebhook, InstallmentPaymentService, BulkInvoiceCreator) — `use Rondo\Config\FinanceConfig` and `new FinanceConfig()` fully deleted from these files
- Rewired 3 mixed consumers (PublicPaymentPage, RestInvoices, InvoicePdfGenerator) — only the Mollie call sites replaced; FinanceConfig preserved for email templates, org info, payment terms
- Deleted all 10 Mollie public forwarder methods from FinanceConfig; class is now Mollie-free except for internal option constants and locator calls inside aggregation methods
- All three verification gates passed: snapshot diff empty, zero Mollie forwarders remaining, zero raw consumer calls

## Task Commits

1. **Tasks 1a + 1b + 2: Rewire consumers + delete forwarders (atomic)** - `76c75fb4` (refactor)

## Files Created/Modified

- `includes/class-mollie-payment.php` — removed FinanceConfig import, rewired 2 call sites to FinanceServices::mollie()
- `includes/class-mollie-webhook.php` — removed FQCN instantiation, rewired 4 call sites + private helper signature updated to MollieConfig
- `includes/class-installment-payment-service.php` — removed FinanceConfig import, rewired 2 call sites
- `includes/class-bulk-invoice-creator.php` — removed FinanceConfig import, rewired 1 call site
- `includes/class-public-payment-page.php` — rewired 2 Mollie call sites in local scope (FinanceConfig kept for non-Mollie methods)
- `includes/class-rest-invoices.php` — added FinanceServices use statement, rewired 7 call sites, dropped FinanceConfig param from resolve_payment_account_for_payload helper
- `includes/class-invoice-pdf-generator.php` — rewired 1 call site in get_invoice_payment_account helper
- `includes/class-finance-config.php` — deleted 10 Mollie public forwarder methods; fixed 2 $this->get_mollie_* residuals in get_iban() and get_setting() before deletion

## Snapshot Diff (FIN-12 — Standing Requirement)

```
diff v34.0-baseline.json 220-02-post-plan02.json: EMPTY (byte-for-byte clean)
```

Baseline: `.planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json`
Post-plan: `.planning/phases/220-extract-mollieconfig/220-02-post-plan02.json`

## Form Round-trip (FIN-11 — Standing Requirement)

```
inserted_fake:NO
update_result:false
accounts_identical:YES
```

`update_result:false` is correct — `update_option` returns false when value is identical (no change needed). `accounts_identical:YES` confirms the round-trip is byte-for-byte clean.

## Decisions Made

- `resolve_payment_account_for_payload` helper in RestInvoices: dropped the `FinanceConfig $finance_config` parameter entirely and updated the single caller. Cleanest approach — the body now calls `FinanceServices::mollie()` directly, no unused parameter.
- Mollie option constants (6 constants like `OPTION_MOLLIE_ACCOUNTS`) kept in FinanceConfig because they are still used internally by `update_settings()`. External grep returned zero hits but internal use is present — deletion deferred to Phase 224 when the class is retired.
- Two `$this->get_mollie_*` residuals found in `get_iban()` (calls `get_active_payment_provider` + `get_default_mollie_account`) and in `get_setting('mollie_accounts')` — fixed to call `FinanceServices::mollie()->X()` inline before deleting the forwarders.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Two $this->get_mollie_X() residuals in FinanceConfig would crash after forwarder deletion**
- **Found during:** Task 2 (delete forwarders)
- **Issue:** `get_iban()` called `$this->get_active_payment_provider()` and `$this->get_default_mollie_account()` internally; `get_setting()` called `$this->get_mollie_accounts()`. Deleting forwarders without fixing these first would cause fatal call-to-undefined-method errors.
- **Fix:** Replaced with `FinanceServices::mollie()->get_active_payment_provider()`, `FinanceServices::mollie()->get_default_mollie_account('manual')`, and `FinanceServices::mollie()->get_mollie_accounts()` before deleting the methods.
- **Files modified:** `includes/class-finance-config.php`
- **Verification:** `php -l` clean, `composer lint` clean
- **Committed in:** `76c75fb4` (part of atomic commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - Bug)
**Impact on plan:** Essential correctness fix caught proactively before deletion. No scope creep.

## Issues Encountered

None — three verification gates all passed on first attempt.

## Next Phase Readiness

- FIN-02 complete: FinanceConfig has zero Mollie public methods
- Plan 03 (live test-mode webhook roundtrip gate) can now proceed
- After Plan 03, the consolidated phase SUMMARY.md will be written
- Phase 221 (Extract EmailTemplates) is unblocked

---
*Phase: 220-extract-mollieconfig*
*Completed: 2026-04-10*
