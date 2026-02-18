# Project Research Summary

**Project:** Membership Fee Invoicing with Payment Plans
**Domain:** WordPress invoice system extension — installment billing, public payment landing pages, scheduled reminders
**Researched:** 2026-02-18
**Confidence:** HIGH

## Executive Summary

This milestone extends Rondo Club's existing discipline case invoice system to support membership fee invoicing with installment payment plans. The existing stack (WordPress, PHP 8.0+, React 18, Mollie SDK v3.9, mPDF, WP-Cron) requires no new dependencies — all needed capabilities are already installed and verified in vendor. The core challenge is a set of fundamentally new concepts layered onto the existing `rondo_invoice` CPT: payment plans with per-installment tracking, bulk creation across 500 members, the first unauthenticated public-facing page in the app, and a scheduled monthly reminder system. Research across 5 Dutch football clubs confirms that three fixed payment plans (full, 3-term, 8-term) cover the full range of Dutch club billing norms, and that a token-secured member self-service page replaces the ClubCollect workflow clubs currently use.

The recommended approach follows established patterns from the existing codebase throughout: rewrite rules plus `template_redirect` for the public page (same pattern as the iCal feed), `bin2hex(random_bytes(32))` 64-char hex tokens (same as iCal), a single daily WP-Cron sweeper (same as Reminders class), and flat numbered post meta for installment storage (avoids ACF repeater query limitations). The key architectural decision is discriminating invoice types via `_invoice_type` post meta on the existing `rondo_invoice` CPT rather than creating a separate CPT, which avoids duplicating the entire invoice, PDF, email, and Mollie infrastructure. Installments are stored as flat post meta keys (`_installment_1_status`, `_installment_2_due_date`, etc.) with a reverse-lookup key (`_mollie_pid_{payment_id} = installment_number`) that enables O(1) webhook payment matching without WP_Query wildcard limitations.

The primary risk is a cluster of interconnected pitfalls around the Mollie webhook: the existing 1:1 invoice-to-payment assumption breaks the moment installment payments arrive, and a naive implementation silently marks invoices paid after the first installment. This must be resolved at the data model phase using the reverse-lookup meta pattern before any installment or webhook code is written. A secondary risk cluster involves bulk operations: WP-Cron unreliability for scheduled emails, HTTP timeout on 500-invoice synchronous requests, and SMTP rate limits on SiteGround shared hosting. All are mitigated by batching (50 invoices per cron execution, 50 emails per batch) and a single daily cron sweeper rather than per-invoice scheduled events.

## Key Findings

### Recommended Stack

No new dependencies are required. The Mollie SDK v3.9 already installed includes `CreateCustomerRequest`, `CreateCustomerPaymentRequest`, and `CreateSubscriptionRequest` (all verified by reading source files in `vendor/mollie/mollie-api-php/src/Http/Requests/`). WordPress core functions cover all other needs: rewrite rules for public pages, `wp_schedule_single_event` for cron, and post meta for installment storage.

Important: Mollie Subscriptions (fully automatic recurring charges via SEPA Direct Debit) are available in the SDK but require SEPA Direct Debit activated on the Mollie account — a business prerequisite. The recommended primary flow is manual payment links per installment (separate Mollie payment per installment, sent by email on the 25th), which requires no mandate setup and keeps members in control of each payment. This matches what Dutch clubs using ClubCollect actually do.

**Core technologies (all existing):**
- `mollie/mollie-api-php ^3.9` — per-installment Mollie payment link creation — verified in vendor
- WordPress Rewrite API + `template_redirect` — public landing page — identical pattern to existing iCal feed
- `bin2hex(random_bytes(32))` — 64-char secure token generation — identical pattern to existing iCal tokens
- `wp_schedule_single_event()` — installment reminder cron — identical pattern to existing Reminders and FeeCacheInvalidator
- Flat numbered post meta — installment storage — directly queryable, cascade-deletes with parent invoice, no ACF overhead

### Expected Features

Research across 5 Dutch football clubs (HZVV, AMVJ, Be Quick '28, SV Orion, DFS) confirms these as standard expectations. The feature dependency chain is strict: invoice type field must exist before bulk creation; bulk creation must exist before the token landing page; the token landing page must exist before plan selection; plan selection must exist before installment scheduling.

**Must have (table stakes):**
- Per-season billing method toggle (Nikki vs Rondo) — prevents retroactive billing conflicts when transitioning mid-contract
- `invoice_type` field on `rondo_invoice` CPT + backfill of existing invoices as `discipline`
- Bulk concept invoice creation from fee calculations — idempotent, async via WP-Cron batch, returns job ID
- Public token-secured landing page at `/betaling/{token}` — mobile-first, no WP login, serves PHP template
- Three fixed payment plans: Volledig (1x in Sep), 3 Termijnen (Sep 50% / Nov 25% / Feb 25%), 8 Termijnen (Sep + monthly Oct–Apr on 25th)
- Per-installment administration fee — configurable in Finance Settings, shown on landing page before member confirms
- Automatic installment emails on the 25th via daily cron sweeper + individual Mollie payment link per installment
- Overdue reminders: 14-day resend, 21-day resend with BCC to treasurer
- Facturen page filters: invoice type, payment plan type, overdue installments

**Should have (differentiators):**
- Member self-selects payment plan on token page — no back-and-forth with treasurer; replaces ClubCollect
- Mollie webhook auto-marks individual installments paid — real-time treasurer visibility without manual reconciliation
- Treasurer BCC on second overdue reminder — passive visibility without active monitoring

**Defer (v2+):**
- Treasurer cash flow projection dashboard — useful once plan selection data exists in production
- Batch-send rate limiting queue system — manual resend is acceptable for MVP
- Nikki reconciliation import — Rondo billing replaces Nikki; import is low priority

**Anti-features (explicitly excluded from this milestone):**
- Mollie Recurring Subscriptions as primary flow — SEPA activation required; unfamiliar member UX for mandate; keep as opt-in variant only
- Configurable installment schedules — Dutch club norms are standardized; generic configuration multiplies complexity for no benefit
- Member portal / login for payment history — token page is sufficient; members are not tech-savvy (stated project constraint)
- Automatic KNVB player registration blocking — Rondo has no Sportlink integration; enforcement is symbolic

### Architecture Approach

The system extends five existing components (RestInvoices, MollieWebhook, MolliePayment, InvoiceEmailSender, FinanceConfig) and adds six new components (PaymentPlanManager, InstallmentScheduler, MembershipInvoiceBulkCreator, RestMembershipInvoices, RestInvoiceInstallments, PaymentLandingPage). Installments are stored as flat numbered post meta on the parent invoice — not a separate CPT and not an ACF repeater. This enables direct meta queries by the scheduler while preserving cascade deletion behavior. The public landing page is PHP-rendered, not React, because the React SPA requires `wpApiSettings` (WP nonce) injected by WordPress, which is not present for unauthenticated users.

**Major components:**
1. `PaymentLandingPage` — Public PHP template served at `/betaling/{token}` via `template_redirect` priority 0, intercepting before the SPA catch-all at priority 1
2. `PaymentPlanManager` — Creates, reads, and transitions installment post meta; provides `all_installments_paid()` for webhook use
3. `InstallmentScheduler` — Daily WP-Cron sweeper that queries invoices with `_has_payment_plan = '1'`, sends due installments, escalates overdue, BCCs treasurer on second reminder
4. `MembershipInvoiceBulkCreator` — Processes 50 members per cron execution from WP transient queue, tracks progress, enables frontend polling
5. `MollieWebhook` (modified) — Extended with reverse-lookup pattern: when creating each installment payment, stores `_mollie_pid_{payment_id} = installment_number` on invoice; webhook looks up this key directly; only transitions invoice to `rondo_paid` when all installments are paid
6. `RestMembershipInvoices` — Bulk create endpoint (returns job ID immediately) + status polling endpoint

### Critical Pitfalls

1. **Webhook lookup breaks on multi-payment invoices** — The existing webhook finds invoices by `_mollie_payment_id` (singular). This fails for installments 2+. Use the reverse-lookup pattern: when creating each installment payment, store `_mollie_pid_{payment_id} = installment_number` on the invoice. Webhook looks up this key directly. Address in data model phase before writing any installment or webhook code.

2. **Webhook marks entire invoice paid after first installment** — The existing binary transition (sent → paid) fires on any confirmed payment. For installment plans, only transition to `rondo_paid` when `PaymentPlanManager::all_installments_paid()` returns true. If some installments remain, keep the invoice in `rondo_sent`. Address in the same webhook extension phase as pitfall 1.

3. **Public landing page intercepted by SPA catch-all** — `rondo_theme_template_redirect()` serves `index.php` for all 404s, including `/betaling/TOKEN`. The member clicks the email link and sees the Rondo login screen. Register `PaymentLandingPage` at `template_redirect` priority 0, before the SPA at priority 1. Test in incognito browser immediately after creating the route.

4. **Bulk creation HTTP timeout at 500 members** — Each `wp_insert_post()` triggers multiple hooks (AutoTitle, FeeCacheInvalidator, Google Contacts export). 500 in a single request exhausts the 30-60s PHP limit. Return job ID immediately; process in batches of 50 via `wp_schedule_single_event`.

5. **WP-Cron installment reminders never fire** — WP-Cron is visitor-triggered and SiteGround caching can serve pages without loading WordPress. Set up a real server cron via SiteGround's cron panel before the first installment reminder is due. Use a single daily sweeper hook, not per-invoice scheduled events (which bloat wp_options and slow page loads).

## Implications for Roadmap

The dependency chain is strict. Each phase must be deployable and testable independently. Phase order follows the dependency graph from FEATURES.md and the build order defined in ARCHITECTURE.md.

### Phase 1: Data Model Foundation

**Rationale:** Everything else depends on the ability to distinguish membership from discipline invoices and on having a defined installment payment ID storage strategy. The webhook lookup pattern (reverse-lookup meta) must be decided before any installment payment IDs are stored. Invoice number race condition must be fixed before bulk creation runs.
**Delivers:** `invoice_type` ACF field added to `rondo_invoice` (select: discipline/membership, default: discipline), backfill migration for existing discipline invoices, defined installment post meta schema, option-based atomic invoice number counter replacing scan-and-increment
**Addresses:** invoice type filter (FEATURES.md), invoice email template type-awareness
**Avoids:** Invoice number race condition under bulk creation (PITFALL #7), wrong email template for membership invoices (PITFALL #11), webhook lookup strategy not decided before code written (PITFALL #1)

### Phase 2: Public Payment Landing Page

**Rationale:** Lowest-risk new feature (read-only, no mutations). Must be proven in production before any invoice email includes the link. If the landing page fails, all downstream member UX breaks. Builds the public page infrastructure that installment emails will link to.
**Delivers:** `PaymentLandingPage` class, token generation (`bin2hex(random_bytes(32))`) on invoice send for membership invoices, `/betaling/{token}` rewrite rule, PHP template (mobile-first, no React, no WP auth), 404 and "already paid" states, template_redirect priority 0 registration
**Addresses:** Public token-secured landing page (FEATURES.md table stakes)
**Avoids:** SPA catch-all intercept (PITFALL #3), token brute force (PITFALL #4 — 64-char hex from day 1), public page performance (PITFALL #9 — use `get_post_meta()` not `get_field()`)

### Phase 3: Payment Plan Manager + Webhook Extension

**Rationale:** Core business logic of installment tracking and fixed plan schedules. Must be built before the scheduler (which reads installment state) and before the bulk creation UI (which creates invoices with payment plan flags). Webhook extension must happen in the same phase as plan manager — both depend on the same reverse-lookup meta pattern.
**Delivers:** `PaymentPlanManager` class, three fixed plan schedules with Dutch-standard dates (Sep/Nov/Feb and Sep–Apr 25th), installment state machine (pending → sent → paid | overdue | cancelled), extended `MollieWebhook` with reverse-lookup pattern, correct all-installments-paid invoice transition, "cancel remaining installments" action
**Addresses:** Payment plan selection + installment schedule storage (FEATURES.md), Mollie webhook auto-marks installments paid (FEATURES.md differentiator)
**Avoids:** Webhook marks invoice paid on first installment (PITFALL #2), multi-payment webhook lookup failure (PITFALL #1), missing cancelled/defaulted state (PITFALL #12), Mollie payment expiry unhandled (PITFALL #8)

### Phase 4: Installment Scheduler + Email System

**Rationale:** Automation layer, only buildable after the payment plan data model exists. Real server cron must be verified and configured before this phase ships — it is a deployment prerequisite, not a code change.
**Delivers:** `InstallmentScheduler` daily cron sweeper, monthly installment emails with per-installment Mollie payment links, 14-day and 21-day overdue reminders with treasurer BCC, type-aware `InvoiceEmailSender` (no Datum/Wedstrijd/Kaart columns for membership invoices), per-installment admin fee configuration in Finance Settings
**Addresses:** Automatic installment emails, overdue reminders with BCC, per-installment admin fee (FEATURES.md table stakes)
**Avoids:** WP-Cron unreliability (PITFALL #6 — real server cron must be set up in SiteGround panel), SMTP rate limits (PITFALL #10 — batch emails in groups of 50), Mollie payment expiry unhandled (PITFALL #8 — generate payment link at send time, not at invoice creation), wrong email template (PITFALL #11 — invoice type awareness in email sender)

### Phase 5: Bulk Invoice Creation

**Rationale:** Most operationally complex phase. Depends on all previous phases so newly created invoices immediately have correct type, plan metadata, and token. Async architecture must be designed before implementation — synchronous creation is not viable at 500-member scale.
**Delivers:** `MembershipInvoiceBulkCreator` (batches of 50 per cron execution), `RestMembershipInvoices` bulk create endpoint (returns job ID immediately) + status polling endpoint, WP-Cron batch processing with transient progress tracking, React progress UI with polling, per-season billing method toggle in Finance Settings, idempotency (skip existing membership invoices for same person and season)
**Addresses:** Per-season billing method toggle, bulk concept invoice creation (FEATURES.md table stakes)
**Avoids:** Bulk creation HTTP timeout (PITFALL #5), invoice number race condition (PITFALL #7), Google Contacts export hook explosion during batch (PITFALL #5), SMTP rate limit on bulk send (PITFALL #10)

### Phase 6: Frontend Updates (Facturen + Contributie)

**Rationale:** UI layer on top of completed API. Only buildable once REST endpoints return invoice type and installment data. Brings all treasurer-facing features to completion and verifies Finance capability gating.
**Delivers:** Facturen page filters (invoice type, payment plan, overdue installments), FactuurDetail installment timeline section with per-installment status, Contributie list "Factureer" bulk action button triggering bulk create flow, Finance capability verification for `rondo_bestuur` role and `financieel` capability
**Addresses:** All Facturen filters (FEATURES.md table stakes), Finance capability for non-admin users, invoice type visible on member's person page (FEATURES.md differentiator)
**Avoids:** Treasurer unable to filter membership from discipline invoices, role/capability gaps in route guards

### Phase Ordering Rationale

- **Data model first** — `_invoice_type` and installment post meta schema (including reverse-lookup strategy) are prerequisites for every other phase. The webhook lookup pattern must be decided before any installment payment IDs are stored.
- **Public landing page second** — Must be tested in incognito before any invoice email includes the link. A broken landing page on invoice send day cannot be patched mid-send.
- **Webhook extension with plan manager** — Both depend on the reverse-lookup meta pattern and must be built together to enable end-to-end testing.
- **Scheduler after plan manager** — The scheduler reads installment state created by `PaymentPlanManager`; building it first would require mocking that state.
- **Bulk creation after all invoice logic is settled** — It creates invoices using the existing create flow; any change to invoice creation after bulk creation is built requires retesting the entire batch path.
- **Frontend last** — The React components are thin layers on stable API responses; building them before the API is complete causes churn.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 4 (Installment Scheduler):** Real server cron setup on SiteGround requires verification of actual cron panel access and PHP execution time limits for cron contexts. PITFALLS.md rates this MEDIUM confidence. Verify before finalizing batch size and reminder timing logic.
- **Phase 5 (Bulk Creation):** SiteGround PHP memory limits for WP-Cron execution contexts are LOW confidence in research. Batch size of 50 per cron run is conservative — verify actual limits on the account before committing to the number.

Phases with standard patterns (skip research-phase):
- **Phase 1 (Data Model):** ACF JSON field additions are established and well-documented in this codebase; option-based invoice counter is standard WordPress.
- **Phase 2 (Public Page):** Identical pattern to existing iCal feed (`class-ical-feed.php`) — no unknowns.
- **Phase 3 (Plan Manager + Webhook):** Mollie API calls and post meta patterns are verified in vendor source. Reverse-lookup meta pattern is a known WordPress pattern.
- **Phase 6 (Frontend):** React + TanStack Query patterns are established; Facturen filter pattern already exists in the codebase.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All Mollie SDK classes verified by reading vendor source; all codebase patterns verified by reading existing class files; no new dependencies required |
| Features | HIGH | Verified against 5 Dutch football club websites; Mollie payment link docs confirmed; codebase feature audit complete; fixed plan schedules match Dutch club norms |
| Architecture | HIGH | All existing classes read directly from codebase; component boundaries and data flows verified against real implementations; reverse-lookup meta pattern resolves WP_Query wildcard limitation |
| Pitfalls | HIGH (Mollie webhook, SPA routing, token security) / MEDIUM (bulk creation batch sizes, SiteGround-specific memory limits) | Webhook behavior from official Mollie docs and codebase; SPA routing from direct functions.php reading; batch sizing from SiteGround community docs |

**Overall confidence:** HIGH

### Gaps to Address

- **Mollie SEPA activation status:** The automatic subscription flow requires SEPA Direct Debit enabled on the Mollie account. Verify with the treasurer before planning the automatic subscription variant. The manual payment link flow works without it and is the recommended primary approach.
- **SiteGround system cron access:** PITFALLS.md recommends disabling WP-Cron's visitor trigger and using real server cron. Verify SiteGround cron panel access and PHP execution limits for cron jobs before finalizing batch sizes in Phase 5. This is a deployment concern, not a code change.
- **Existing `rondo_daily_cron` hook:** FEATURES.md references an existing `rondo_daily_cron` hook in `class-reminders.php`. Verify whether this hook already exists before creating a new daily hook in `InstallmentScheduler`. If it exists, register `InstallmentScheduler` on it rather than creating a duplicate.
- **Installment plan dates for non-2025-2026 seasons:** The three fixed plan schedules use hardcoded Dutch season dates (Sep 25, Nov 25, Feb 25 for Plan B; Sep–Apr 25th for Plan C). Verify how dates should be calculated for other seasons before implementing `PaymentPlanManager::calculate_due_date()`.

## Sources

### Primary (HIGH confidence)
- Codebase direct inspection: `vendor/mollie/mollie-api-php/src/Http/Requests/CreateSubscriptionRequest.php`, `CreateCustomerRequest.php`, `CreateCustomerPaymentRequest.php`
- Codebase direct inspection: `includes/class-ical-feed.php`, `includes/class-fee-cache-invalidator.php`, `includes/class-mollie-webhook.php`, `includes/class-mollie-payment.php`, `includes/class-rest-invoices.php`, `includes/class-reminders.php`, `includes/class-finance-config.php`, `includes/class-membership-fees.php`
- Codebase direct inspection: `functions.php` (template_redirect catch-all confirmed), `acf-json/group_invoice_fields.json`
- [Mollie Recurring Payments docs](https://docs.mollie.com/docs/recurring-payments) — iDEAL creates SEPA Direct Debit mandate; SEPA activation required as account prerequisite
- [Mollie Create Subscription](https://docs.mollie.com/reference/create-subscription) — `times` parameter limits total charges; `startDate` format verified
- [Mollie Webhooks Reference](https://docs.mollie.com/reference/webhooks) — 10 retries over 26h, re-fetch required, 15s timeout, always return 200
- [Mollie Handling Payment Status](https://docs.mollie.com/docs/handling-payment-status) — `expired` status handling
- [WordPress Developer Docs: add_rewrite_rule](https://developer.wordpress.org/reference/functions/add_rewrite_rule/)
- [WordPress WP_Cron documentation](https://developer.wordpress.org/plugins/cron/) — visitor-triggered, unreliable on cached sites
- Dutch club research: HZVV (4 terms, 2 reminders within 2 weeks), AMVJ (3 terms via ClubCollect, admin fee for incasso), Be Quick '28 (10 monthly terms, 10% fee max €19), SV Orion (1-4 terms, €3/installment admin fee)

### Secondary (MEDIUM confidence)
- [WP-Cron Missed Events — WP Crontrol](https://wp-crontrol.com/help/missed-cron-events/) — visitor-trigger reliability issues; solution: system cron
- [WP Mail SMTP Rate Limiting](https://wpmailsmtp.com/introducing-wp-mail-smtp-4-0-optimized-email-sending-rate-limiting/) — email rate limiting per host; batching required for bulk sends
- [Packagist: woocommerce/action-scheduler](https://packagist.org/packages/woocommerce/action-scheduler) — `wordpress-plugin` type, not suitable for theme; confirms WP-Cron is correct approach
- [Mollie Payment Expiry Times](https://wordpress.org/support/topic/mollie-payments-expire-too-soon/) — 15-min iDEAL, 12-day bank transfer confirmed
- [Duplicate Invoice Numbers — WordPress.org](https://wordpress.org/support/topic/duplicate-invoice-numbers-1/) — race conditions in scan-and-increment patterns

### Tertiary (LOW confidence — validate during implementation)
- SiteGround PHP memory limits for WP-Cron execution contexts — verify on actual account before finalizing batch sizes
- Token hashing recommendations for stored payment tokens — `hash('sha256', $token)` is standard; PHP docs are authoritative

---
*Research completed: 2026-02-18*
*Ready for roadmap: yes*
