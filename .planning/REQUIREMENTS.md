# Requirements: Rondo Club v34.0 Finance Service Decomposition

**Defined:** 2026-04-09
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system.
**Milestone goal:** Retire the 1,308-line `Rondo\Finance\FinanceConfig` god class (the top god node in graphify post-v33.0, 54 edges, cohesion 0.1) by decomposing it into focused services. Pure internal refactor — zero user-visible behaviour changes. Strategy: **hard replacement (Option C)** — delete `FinanceConfig` entirely by the end of the milestone, mirroring v33.0 Phase 218's retirement of `MembershipFees`.

## v34.0 Requirements

Every requirement below is validated by: (a) a clean `bin/finance-settings-snapshot.sh` diff (zero drift across all 48 `rondo_finance_*` option keys + `/rondo/v1/finance-settings` REST response), and (b) successful `FinanceSettings.jsx` form round-trip (load → submit unchanged → DB bytes identical).

### Regression Harness

- [ ] **FIN-01**: `bin/finance-settings-snapshot.sh` + `bin/finance-settings-snapshot.php` WP-CLI-backed harness exists and captures (a) all 48 `rondo_finance_*` option values and (b) the full `/rondo/v1/finance-settings` REST response as pretty-printed JSON, suitable for `jq -S` byte-for-byte diff. Analog to `bin/fee-snapshot.sh` from v33.0. Must be buildable before Phase 219 Plan 02 extraction work begins.

### Mollie Configuration Service

- [ ] **FIN-02**: `Rondo\Finance\MollieConfig` class owns the complete Mollie configuration surface: `get_mollie_accounts()`, `get_mollie_account_by_id()`, `get_mollie_api_key_for_account()`, `get_usable_mollie_accounts()`, `get_default_mollie_account_id( $invoice_type )`, `get_default_mollie_account()`, `get_payment_account_snapshot_for_invoice_type()`, `get_mollie_redirect_url()`, `get_active_payment_provider()`, `update_active_payment_provider()`, plus the private helpers (`normalize_mollie_accounts_for_storage`, `build_safe_mollie_accounts_from_storage`, `decrypt_mollie_account_api_key`, `derive_mollie_environment`, `get_mollie_account_record_by_id`). Zero Mollie code remains in `FinanceConfig` after this requirement is satisfied.

- [ ] **FIN-03**: Mollie webhook integration verified end-to-end with a real test-mode roundtrip (test-mode API key, test payment link, test webhook fires, invoice transitions to `rondo_paid`, reverse-lookup `_mollie_pid_{pl_xxx}` meta found, status email sent) **before** the Mollie phase merges to main. Mollie is the scariest subsystem in the refactor because of encrypted API key plumbing and webhook routing — snapshot diff alone is not sufficient evidence.

### Email Templates Service

- [ ] **FIN-04**: `Rondo\Finance\EmailTemplates` class owns all email template/heading/subject getters: `get_email_template()`, `get_installment_email_template()`, `get_reminder_1/2_email_template()`, `get_membership_email_template()`, `get_membership_payment_clause()`, `get_invoice_reminder_1/2_email_template()`, `get_credit_email_template()`, `get_credit_email_subject()`, `get_regular_invoice_email_subject()`, `get_regular_invoice_email_body()`, and the central `get_email_heading( string $type )` dispatch covering all 9 heading types (regular_invoice, discipline, membership, installment, reminder_1, reminder_2, invoice_reminder_1, invoice_reminder_2, credit). `InvoiceEmailSender::send()` and `InstallmentEmailSender::send()` both call through `FinanceServices::email_templates()->X()`. Zero email-template code remains in `FinanceConfig`.

### Membership Pass Configuration Service

- [ ] **FIN-05**: `Rondo\Finance\MembershipPassConfig` class owns all wallet pass settings: `get_membership_pass_apple_cert_attachment_id()`, `get_membership_pass_apple_cert_password()`, `get_membership_pass_apple_pass_type_identifier()`, `get_membership_pass_apple_team_identifier()`, `get_membership_pass_apple_organization_name()`, `get_membership_pass_google_service_account_attachment_id()`, `get_membership_pass_google_issuer_id()`, `get_membership_pass_google_class_suffix()`. Both `MembershipPassApple` and `MembershipPassGoogle` classes call through `FinanceServices::membership_pass()->X()`. Zero wallet-pass code remains in `FinanceConfig`.

### Remaining Fragments Services

- [ ] **FIN-06**: `Rondo\Finance\OrgInfo` class owns organizational identity settings: `get_org_name()`, `get_display_name()`, `get_org_address()`, `get_contact_email()`, `get_iban()`, `get_bcc_email()`, `get_club_logo_id()`, `get_accent_color()`, `get_accent_background_color()`. Shared across email senders, PDF generators, and public pages.

- [ ] **FIN-07**: `Rondo\Finance\PaymentTerms` class owns payment terms and fee settings: `get_payment_term_days()`, `get_payment_clause()`, `get_admin_fee()`, `get_installment_admin_fee()`. Used by invoice creation, installment scheduling, and PDF generation.

- [ ] **FIN-08**: `Rondo\Finance\RabobankConfig` class owns Rabobank OAuth credential management: `get_rabobank_credentials()`, `update_rabobank_credentials( string $client_id, string $client_secret, string $environment )`. Called by `RabobankOAuth` and `RabobankPayment`.

### Facade Retirement

- [ ] **FIN-09**: `includes/class-finance-config.php` deleted from the codebase. Zero references to `FinanceConfig` remain in any of the 18 original dependents (`InvoiceEmailSender`, `PublicPaymentPage`, `BulkInvoiceCreator`, `PublicMembershipPassPage`, `MembershipPassGoogle`, `MembershipPassApple`, `InvoiceReminderSender`, `RestLettermint`, `RestInvoices`, `RestFinanceSettings`, `RabobankPayment`, `RabobankOAuth`, `QrCodeGenerator`, `MollieWebhook`, `MolliePayment`, `InvoicePdfGenerator`, `InstallmentPaymentService`, `InstallmentEmailSender`). All callers use `FinanceServices::X()->method()`.

- [ ] **FIN-10**: `Rondo\Finance\FinanceServices` static locator class provides lazy accessors for every extracted service: `mollie()`, `email_templates()`, `membership_pass()`, `org_info()`, `payment_terms()`, `rabobank()`. Mirrors the `FeeServices` pattern from v33.0 Phase 218: static class, zero business logic of its own, each accessor constructs and caches a single instance per request.

### REST API Compatibility

- [ ] **FIN-11**: `class-rest-finance-settings.php` continues to serve the same flat settings object to `FinanceSettings.jsx` — no changes to the REST response shape or the request body shape accepted by the settings save endpoint. The REST class becomes a composition point that pulls from all the extracted services but presents one unified `get_all_settings()` response. Verified at every phase with `FinanceSettings.jsx` form round-trip: load settings → submit unchanged → verify every `rondo_finance_*` option in the database is byte-for-byte identical.

### Validation Discipline

- [ ] **FIN-12**: Every phase merged to `main` ships with a clean `bin/finance-settings-snapshot.sh` diff recorded in its `SUMMARY.md` (zero drift across all 48 `rondo_finance_*` option keys + the full REST response JSON). Mirrors v33.0 Phase 214-218 fee-snapshot discipline. Any non-empty diff must be resolved before the commit is pushed.

## v35.0 Requirements

Deferred to a later milestone. Tracked but not in current roadmap.

### Finance REST API Decomposition

- **FINRST-01**: Consider splitting `class-rest-finance-settings.php` into per-service REST endpoints once the backend services are stabilized (e.g. `/rondo/v1/mollie-config`, `/rondo/v1/email-templates`). Would require a parallel `FinanceSettings.jsx` rewrite.

### FinanceSettings.jsx Component Split

- **FINUI-01**: `FinanceSettings.jsx` is a large single-file React component mirroring `FinanceConfig`'s flat shape. Once the backend is split, consider splitting the React component into per-service tabs/sub-components (`MollieSettings`, `EmailTemplateSettings`, `MembershipPassSettings`, `OrgInfoSettings`, etc.).

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Exclusion | Reason |
|-----------|--------|
| Changing the REST API response shape | Frontend form is built against the current flat shape; preserving compatibility is a hard requirement. Any restructuring goes in v35.0. |
| Splitting `FinanceSettings.jsx` | Out of scope for v34.0. Backend-only refactor. Deferred to v35.0 as **FINUI-01**. |
| Deleting `rondo_finance_*` WordPress options | Option keys MUST stay identical — the v33.0 pattern was byte-for-byte option-key preservation. Any renaming is a separate data migration with its own phase outside this milestone. |
| Adding new Mollie features | Not a feature milestone. Mollie code is extracted, not enhanced. |
| Thin `FinanceConfig` facade | User chose Option C (hard replacement) over Option A (facade). Keeping any `FinanceConfig` shim would defeat the purpose of the retirement. |
| Introducing a DI container | `FinanceServices` is a static locator (not a container), matching v33.0's `FeeServices` precedent. A real container is out of scope. |
| Refactoring email template storage format | Templates stay as `wp_option` strings with `{placeholder}` variables. No move to files, no new rendering pipeline — just relocating the getters. |
| Unit tests as validation net | Snapshot harness + form round-trip + Mollie webhook test are the validation strategy. Unit test backfill is deferred and consistent with the v33.0 direct-style pattern. |
| Formal Nyquist VALIDATION.md per phase | v33.0 shipped without Nyquist VALIDATION.md and snapshot-diff was effective. Same approach for v34.0. Direct-style execution. |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FIN-01 | Phase 219 | Pending |
| FIN-02 | Phase 220 | Pending |
| FIN-03 | Phase 220 | Pending |
| FIN-04 | Phase 221 | Pending |
| FIN-05 | Phase 222 | Pending |
| FIN-06 | Phase 223 | Pending |
| FIN-07 | Phase 223 | Pending |
| FIN-08 | Phase 223 | Pending |
| FIN-09 | Phase 224 | Pending |
| FIN-10 | Phase 224 | Pending |
| FIN-11 | Phase 219 + all subsequent | Pending |
| FIN-12 | Phase 219 + all subsequent | Pending |

**Coverage:**
- v34.0 requirements: 12 total
- Mapped to phases: 12 (pending roadmapper confirmation)
- Unmapped: 0 ✓

---
*Requirements defined: 2026-04-09*
*Last updated: 2026-04-09 after v34.0 milestone kickoff*
