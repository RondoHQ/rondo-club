# Roadmap: Rondo Club

## Milestones

- ✅ **v33.0 Fee Service Decomposition** — Phases 214-218 (shipped 2026-04-09) — [archive](milestones/v33.0-ROADMAP.md) · [audit](milestones/v33.0-MILESTONE-AUDIT.md)
- 📋 **v34.0 Finance Service Decomposition** — Phases 219-224 (in progress, started 2026-04-09)

## Phases

<details>
<summary>✅ v33.0 Fee Service Decomposition (Phases 214–218) — SHIPPED 2026-04-09</summary>

- [x] Phase 214: FeeCategoryResolver + Snapshot Infrastructure (2/2 plans) — completed 2026-04-09
- [x] Phase 215: FamilyGroupingService (1/1 plan) — completed 2026-04-09
- [x] Phase 216: FeeCalculator (1/1 plan) — completed 2026-04-09
- [x] Phase 217: MembershipFeeSettings (2/2 plans) — completed 2026-04-09
- [x] Phase 218: Retire MembershipFees (1/1 plan) — completed 2026-04-09

`MembershipFees` god class deleted (2,137 lines → 0). Fee system refactored into 8 focused classes totalling 2,692 lines with no class over 600 lines. Triple-clean snapshot diff validation at every phase. See archive + audit for full details.

</details>

### 📋 v34.0 Finance Service Decomposition (Phases 219–224)

**Goal:** Retire the 1,308-line `Rondo\Finance\FinanceConfig` god class (now the top god node post-v33.0, 54 edges, cohesion 0.1) by decomposing it into focused services using the v33.0 hard-replacement pattern. Pure internal refactor — zero user-visible behaviour changes.

**Strategy:** Hard replacement (Option C). Delete `FinanceConfig` entirely by the end of the milestone. No thin facade. All 18 callers get rewired phase-by-phase as each subsystem comes out. Mirrors v33.0 Phase 218's retirement of `MembershipFees`.

**Validation discipline (standing):** Every phase from 219 onward ships with:
1. A clean `bin/finance-settings-snapshot.sh` pre/post diff recorded in its `SUMMARY.md` — zero drift across all 48 `rondo_finance_*` option keys + `/rondo/v1/finance-settings` REST response. (FIN-12)
2. `class-rest-finance-settings.php` continues to serve the same flat settings object to `FinanceSettings.jsx` — no changes to the REST response shape. (FIN-11)

- [x] **Phase 219: Finance Settings Snapshot Harness** — Build the regression harness that gates the entire milestone (completed 2026-04-09)
- [ ] **Phase 220: Extract MollieConfig** — Extract the biggest and scariest subsystem first (encrypted API keys, webhook plumbing)
- [ ] **Phase 221: Extract EmailTemplates** — Extract email template/heading/subject getters + central dispatch
- [ ] **Phase 222: Extract MembershipPassConfig** — Extract Apple + Google wallet pass configuration
- [ ] **Phase 223: Extract OrgInfo + PaymentTerms + RabobankConfig** — Bundle the three smallest remaining fragments
- [ ] **Phase 224: Retire FinanceConfig** — Delete the class file, introduce `FinanceServices` static locator, final triple-clean diff

## Phase Details

### Phase 219: Finance Settings Snapshot Harness
**Goal**: A runnable `bin/finance-settings-snapshot.sh` + `bin/finance-settings-snapshot.php` WP-CLI-backed harness captures the full finance settings surface as byte-for-byte JSON, ready to gate every subsequent extraction phase.
**Depends on**: Nothing (first phase of milestone, must precede all extraction work)
**Requirements**: FIN-01 (harness exists and is runnable)
**Standing**: FIN-11 and FIN-12 become active obligations from this phase onward and apply to every subsequent phase.
**Success Criteria** (what must be TRUE):
  1. `bin/finance-settings-snapshot.sh` runs end-to-end without error against a local WP install and writes a timestamped JSON artifact to a predictable location
  2. The snapshot captures all 48 `rondo_finance_*` option values (verified by counting keys in the output) and the full `/rondo/v1/finance-settings` REST response as pretty-printed JSON
  3. Running the harness twice against an unchanged database produces a `jq -S` byte-for-byte identical diff (zero drift — the harness is deterministic and order-stable)
  4. A baseline snapshot is recorded in the phase's `SUMMARY.md` and committed alongside the harness, establishing the golden-state reference all subsequent phases will diff against
**Plans:** 1/1 plans complete
Plans:
- [ ] 219-01-PLAN.md — Build finance-settings-snapshot.{sh,php} harness (48-key allowlist + get_all_settings() REST capture), prove run-twice-diff-empty determinism, commit v34.0 baseline

### Phase 220: Extract MollieConfig
**Goal**: The entire Mollie configuration surface lives in a dedicated `Rondo\Finance\MollieConfig` class with encrypted API key plumbing verified end-to-end via a real test-mode webhook roundtrip, and every Mollie-touching caller is rewired to the new service.
**Depends on**: Phase 219 (snapshot harness must exist before extraction begins)
**Requirements**: FIN-02 (class owns complete Mollie surface), FIN-03 (test-mode webhook roundtrip verified)
**Success Criteria** (what must be TRUE):
  1. `Rondo\Finance\MollieConfig` owns every Mollie method enumerated in FIN-02 (accounts, API key retrieval, defaults, snapshots, redirect URL, active provider, environment derivation) plus all private helpers — zero Mollie code remains in `FinanceConfig`
  2. Every Mollie-touching caller is rewired through `FinanceServices::mollie()->X()` — verified by grepping the codebase: `MolliePayment`, `MollieWebhook`, `InstallmentPaymentService`, `PublicPaymentPage`, `RestInvoices`, and any other consumer show zero remaining references to `FinanceConfig` for Mollie-related calls
  3. `bin/finance-settings-snapshot.sh` pre/post diff is byte-for-byte clean across all 48 option keys + REST response, recorded in phase `SUMMARY.md`
  4. End-to-end Mollie test-mode webhook roundtrip verified: test-mode API key → test payment link created → test payment completed → webhook fires → invoice transitions to `rondo_paid` → reverse-lookup `_mollie_pid_{pl_xxx}` meta is found → status email sent. Evidence captured in phase `SUMMARY.md`.
**Plans:** 2/3 plans executed
Plans:
- [ ] 220-01-PLAN.md — Scaffold MollieConfig class + FinanceServices locator + rewire FinanceConfig internals to delegate (Wave 1)
- [ ] 220-02-PLAN.md — Rewire 7 Mollie consumer files to FinanceServices::mollie() and delete FinanceConfig Mollie forwarders (Wave 2)
- [ ] 220-03-PLAN.md — Live test-mode Mollie webhook roundtrip + phase SUMMARY (Wave 3, checkpointed)

### Phase 221: Extract EmailTemplates
**Goal**: All email template/heading/subject getters and the central `get_email_heading($type)` dispatch (9 heading types) live in a dedicated `Rondo\Finance\EmailTemplates` class, and all three email senders are rewired to the new service.
**Depends on**: Phase 219 (snapshot harness)
**Requirements**: FIN-04 (class owns complete email template surface)
**Success Criteria** (what must be TRUE):
  1. `Rondo\Finance\EmailTemplates` owns every email template, heading, and subject getter enumerated in FIN-04, and the central `get_email_heading(string $type)` dispatch covers all 9 heading types (regular_invoice, discipline, membership, installment, reminder_1, reminder_2, invoice_reminder_1, invoice_reminder_2, credit) — zero email-template code remains in `FinanceConfig`
  2. `InvoiceEmailSender`, `InstallmentEmailSender`, and `InvoiceReminderSender` all call through `FinanceServices::email_templates()->X()` — verified by grep, zero remaining `FinanceConfig` references from the email senders
  3. `bin/finance-settings-snapshot.sh` pre/post diff is byte-for-byte clean across all 48 option keys + REST response, recorded in phase `SUMMARY.md`
**Plans**: TBD

### Phase 222: Extract MembershipPassConfig
**Goal**: All wallet pass configuration (Apple cert/password/identifiers + Google service account/issuer/class suffix) lives in a dedicated `Rondo\Finance\MembershipPassConfig` class, and both wallet pass classes plus the public pass page are rewired.
**Depends on**: Phase 219 (snapshot harness)
**Requirements**: FIN-05 (class owns complete wallet pass surface)
**Success Criteria** (what must be TRUE):
  1. `Rondo\Finance\MembershipPassConfig` owns every wallet-pass getter enumerated in FIN-05 (Apple cert attachment ID, cert password, pass type identifier, team identifier, organization name; Google service account attachment ID, issuer ID, class suffix) — zero wallet-pass code remains in `FinanceConfig`
  2. `MembershipPassApple`, `MembershipPassGoogle`, and `PublicMembershipPassPage` all call through `FinanceServices::membership_pass()->X()` — verified by grep
  3. `bin/finance-settings-snapshot.sh` pre/post diff is byte-for-byte clean across all 48 option keys + REST response, recorded in phase `SUMMARY.md`
**Plans**: TBD

### Phase 223: Extract OrgInfo + PaymentTerms + RabobankConfig
**Goal**: The three smallest remaining fragments of `FinanceConfig` (~200 lines total) are extracted into three dedicated service classes, and all dependents — the widest spread of callers in the milestone — are rewired. After this phase, `FinanceConfig` is effectively empty except for bookkeeping like `get_all_settings()` aggregation.
**Depends on**: Phase 219 (snapshot harness). Independent of Phases 220-222.
**Requirements**: FIN-06 (OrgInfo service), FIN-07 (PaymentTerms service), FIN-08 (RabobankConfig service)
**Success Criteria** (what must be TRUE):
  1. `Rondo\Finance\OrgInfo` owns all organizational identity getters enumerated in FIN-06 (org name, display name, address, contact email, IBAN, BCC email, club logo ID, accent color, accent background color)
  2. `Rondo\Finance\PaymentTerms` owns all payment term and fee getters enumerated in FIN-07 (payment term days, payment clause, admin fee, installment admin fee)
  3. `Rondo\Finance\RabobankConfig` owns Rabobank OAuth credential management (get + update) enumerated in FIN-08, and both `RabobankOAuth` and `RabobankPayment` are rewired
  4. All dependents of all three services (the widest spread of callers in the milestone — email senders, PDF generators, public pages, invoice creators, QR code generator, Rabobank integration) are rewired through `FinanceServices::org_info()` / `::payment_terms()` / `::rabobank()` — verified by grep
  5. `bin/finance-settings-snapshot.sh` pre/post diff is byte-for-byte clean across all 48 option keys + REST response, recorded in phase `SUMMARY.md`. After this phase, `FinanceConfig` contains no concrete business logic — only aggregation stubs that will be retired in Phase 224.
**Plans**: TBD

### Phase 224: Retire FinanceConfig
**Goal**: `includes/class-finance-config.php` is deleted from the codebase, `Rondo\Finance\FinanceServices` static locator is operational as the sole ergonomic entry point to every extracted service, and the REST settings composition layer is rewired to compose from the services while preserving the existing `FinanceSettings.jsx` form contract byte-for-byte.
**Depends on**: Phases 220, 221, 222, 223 (all extraction phases must be complete before the facade can be retired)
**Requirements**: FIN-09 (class file deleted, zero callers remain), FIN-10 (FinanceServices locator operational)
**Success Criteria** (what must be TRUE):
  1. `includes/class-finance-config.php` is deleted. Zero references to `FinanceConfig` remain in any of the 18 original dependents (`InvoiceEmailSender`, `PublicPaymentPage`, `BulkInvoiceCreator`, `PublicMembershipPassPage`, `MembershipPassGoogle`, `MembershipPassApple`, `InvoiceReminderSender`, `RestLettermint`, `RestInvoices`, `RestFinanceSettings`, `RabobankPayment`, `RabobankOAuth`, `QrCodeGenerator`, `MollieWebhook`, `MolliePayment`, `InvoicePdfGenerator`, `InstallmentPaymentService`, `InstallmentEmailSender`) — verified by a final grep for `FinanceConfig` returning zero hits outside the deleted file's history
  2. `Rondo\Finance\FinanceServices` static locator exposes lazy accessors for every extracted service (`mollie()`, `email_templates()`, `membership_pass()`, `org_info()`, `payment_terms()`, `rabobank()`), each constructing and caching one instance per request, with zero business logic of its own — mirrors `FeeServices` from v33.0 Phase 218
  3. `class-rest-finance-settings.php` composes the `/rondo/v1/finance-settings` response from all services while preserving the flat shape `FinanceSettings.jsx` expects — verified by `FinanceSettings.jsx` form round-trip: load settings → submit unchanged → every `rondo_finance_*` option in the database is byte-for-byte identical (FIN-11 final verification)
  4. Final triple-clean diff recorded in phase `SUMMARY.md`: (a) `bin/finance-settings-snapshot.sh` pre/post diff clean, (b) option-list diff clean (all 48 keys present and unchanged), (c) `FinanceSettings.jsx` form round-trip clean
  5. Production deployment verified with a live smoke test: live Mollie webhook on production processes a real payment successfully, and a live email send (using `override_email` to project owner) renders with correct heading, template, and org info — proving the extraction holds under real traffic
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
| --- | --- | --- | --- | --- |
| 214. FeeCategoryResolver | v33.0 | 2/2 | Complete | 2026-04-09 |
| 215. FamilyGroupingService | v33.0 | 1/1 | Complete | 2026-04-09 |
| 216. FeeCalculator | v33.0 | 1/1 | Complete | 2026-04-09 |
| 217. MembershipFeeSettings | v33.0 | 2/2 | Complete | 2026-04-09 |
| 218. Retire MembershipFees | v33.0 | 1/1 | Complete | 2026-04-09 |
| 219. Finance Settings Snapshot Harness | 1/1 | Complete    | 2026-04-09 | — |
| 220. Extract MollieConfig | 2/3 | In Progress|  | — |
| 221. Extract EmailTemplates | v34.0 | 0/0 | Not started | — |
| 222. Extract MembershipPassConfig | v34.0 | 0/0 | Not started | — |
| 223. Extract OrgInfo + PaymentTerms + RabobankConfig | v34.0 | 0/0 | Not started | — |
| 224. Retire FinanceConfig | v34.0 | 0/0 | Not started | — |
