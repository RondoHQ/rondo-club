---
phase: 189-restinvoices-provider-routing
verified: 2026-02-18T06:39:15Z
status: passed
score: 4/4 must-haves verified
re_verification: false
---

# Phase 189: RestInvoices Provider Routing — Verification Report

**Phase Goal:** RestInvoices::send_invoice() routes to Mollie or Rabobank based on the configured active provider — existing Rabobank path is completely unchanged.
**Verified:** 2026-02-18T06:39:15Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #  | Truth                                                                                                     | Status     | Evidence                                                                                                      |
|----|-----------------------------------------------------------------------------------------------------------|------------|---------------------------------------------------------------------------------------------------------------|
| 1  | When Mollie is active, send_invoice() creates a Mollie payment link via MolliePayment::create_payment_link() | VERIFIED | Lines 673-678 of class-rest-invoices.php: `if ('mollie' === $active_provider) { $mollie_payment->create_payment_link($invoice_id) }` |
| 2  | When Rabobank is active (or no provider configured), send_invoice() executes the original Rabobank path unchanged | VERIFIED | Lines 679-689: `else { $oauth = new RabobankOAuth(); if ($oauth->is_connected()) { ... } }` — byte-for-byte original code with original error message text |
| 3  | Payment link failures for either provider are non-blocking — errors logged, invoice sending continues       | VERIFIED | Both branches log via error_log() and execution falls through to PDF generation at line 692                   |
| 4  | Existing RabobankPayment and RabobankOAuth classes are not modified                                        | VERIFIED | git log shows zero commits to class-rabobank-payment.php and class-rabobank-oauth.php since phase start; git diff of commits 038c2c5a..205eb5e0 shows 0 lines changed in those files |

**Score:** 4/4 truths verified

### Required Artifacts

| Artifact                                  | Expected                              | Status   | Details                                                                                   |
|-------------------------------------------|---------------------------------------|----------|-------------------------------------------------------------------------------------------|
| `includes/class-rest-invoices.php`        | Provider routing in send_invoice()    | VERIFIED | Contains `get_active_payment_provider`, `create_payment_link`, `create_payment_request`, `'mollie' === $active_provider`, `use Rondo\Finance\MolliePayment;` |

### Key Link Verification

| From                           | To                              | Via                                     | Status   | Details                                                                                       |
|--------------------------------|---------------------------------|-----------------------------------------|----------|-----------------------------------------------------------------------------------------------|
| `class-rest-invoices.php`      | `class-finance-config.php`      | `FinanceConfig::get_active_payment_provider()` | WIRED | Line 670-671: `$finance_config = new FinanceConfig(); $active_provider = $finance_config->get_active_payment_provider();` |
| `class-rest-invoices.php`      | `class-mollie-payment.php`      | `MolliePayment::create_payment_link()`  | WIRED    | Line 674-675: `$mollie_payment = new MolliePayment(); $payment_result = $mollie_payment->create_payment_link($invoice_id);` |

### Requirements Coverage

| Requirement                                                                                      | Status    | Blocking Issue |
|--------------------------------------------------------------------------------------------------|-----------|----------------|
| send_invoice() reads FinanceConfig::get_active_payment_provider() and branches to MolliePayment::create_payment_link() when Mollie selected | SATISFIED | None |
| When Rabobank is active provider, invoice sending behavior is byte-for-byte identical to v26.0   | SATISFIED | None           |
| Default provider is rabobank — if option not set, Rabobank path executes                         | SATISFIED | FinanceConfig::get_active_payment_provider() uses `get_option(..., 'rabobank')` as default; routing uses `else` (not `elseif 'rabobank'`) so all non-Mollie values go to Rabobank |
| Existing RabobankPayment and RabobankOAuth classes are not modified                               | SATISFIED | None           |

### Anti-Patterns Found

No anti-patterns found in the routing section. Two `return null` occurrences at lines 898 and 904 are in `get_invoice_person_summary()` — correct early-returns for missing/invalid data, not stubs.

### Human Verification Required

None — all verification is programmatically deterministic for this phase (PHP logic branching, not visual or real-time).

### Gaps Summary

No gaps. All four must-have truths are verified with direct code evidence:

1. The `use Rondo\Finance\MolliePayment;` import is present at line 16 of the file, grouped with other Finance imports.
2. The routing block at lines 669-689 of `send_invoice()` correctly reads `FinanceConfig::get_active_payment_provider()`, branches on `'mollie' === $active_provider`, and places the original Rabobank code verbatim in the `else` branch.
3. Both commits are confirmed in git history (038c2c5a, 205eb5e0), each modifying only `includes/class-rest-invoices.php`.
4. `php -l` reports no syntax errors.
5. Rabobank classes have zero changes since before phase 189.

---

_Verified: 2026-02-18T06:39:15Z_
_Verifier: Claude (gsd-verifier)_
