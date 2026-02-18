# Feature Research

**Domain:** Membership Fee Invoicing with Payment Plans — Dutch Sports Club
**Researched:** 2026-02-18
**Confidence:** HIGH (domain verified via Dutch club research + existing codebase analysis)

## Context

This research covers only NEW features needed for membership fee invoicing with payment plans. The following are already built and out of scope:
- Discipline case invoice CPT (rondo_invoice) with lifecycle statuses (rondo_draft/rondo_sent/rondo_paid/rondo_overdue)
- Invoice numbering (2026T001 format), PDF generation, email delivery
- Mollie payment link creation, webhook handler, idempotent reuse of payment links
- Finance Settings with Mollie API key, payment provider selector, email template
- Facturen list page with status filter and sortable columns
- FactuurDetail page with send/resend/mark-paid actions
- Membership fee calculation: categories, family discounts, pro-rata
- Contributie list page showing calculated fees

The existing invoice CPT has `invoice_type` not yet set — currently all invoices are discipline case invoices. The new milestone introduces membership fee invoices as a second type.

---

## Feature Landscape

### Table Stakes (Users Expect These)

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Per-season billing method toggle (Nikki vs Rondo) | Club has existing Nikki contract; transition must be controlled per-season, not a global switch that breaks ongoing billing | LOW | Store `billing_method` per season key in WordPress options. Values: `nikki` (default, existing behaviour) or `rondo`. The Contributie page already has a season selector; add a billing method display/toggle there. Only one season at a time can use Rondo billing. |
| Bulk concept invoice creation from fee calculations | Treasurer needs to move from "calculated fees" to "invoices ready to send" in one step — sending 500 individual invoices one-by-one is not a workflow | MEDIUM | New REST endpoint: `POST /rondo/v1/membership-fees/create-invoices`. Takes season, billing method check. Reads all fee calculations for season. Creates one `rondo_invoice` per member with `invoice_type = membership`. Idempotent: skips members who already have a membership invoice for that season. Returns count of created vs skipped. |
| Invoice type field on rondo_invoice CPT | Facturen list now shows both discipline and membership invoices; treasurer must be able to filter by type | LOW | Add `invoice_type` ACF field (or post meta) to `rondo_invoice`. Values: `discipline` (existing) or `membership` (new). Backfill existing invoices as `discipline` in migration. Add filter to Facturen page. |
| Public token-secured landing page for payment plan selection | Members are not tech-savvy; they receive an email with a link and must be able to choose their payment plan without logging in | MEDIUM | New public WordPress template (no auth) served at `/betaalplan/{token}`. Token is a signed URL-safe hash (stored as post meta on invoice). Page shows: member name, total amount, three plan options with prices broken down, submit button. No member account required. Token expires after season end (July 1). |
| Payment plan selection (full, 3-term, 8-term) | Dutch football clubs universally offer installment options (research confirmed: AMVJ: 3 terms, HZVV: 4 terms, SV Orion: 1-4 terms). Members paying €200-500 upfront expect spreading options | MEDIUM | Three fixed plans, not configurable per invoice: (1) Volledig: total in September, no admin fee; (2) 3 termijnen: Sep 25 (50%), Nov 25 (25%), Feb 25 (25%); (3) 8 termijnen: Sep 25 (first installment), then Oct-Apr on the 25th monthly. Each plan stores chosen plan type + installment schedule as post meta on the invoice. |
| Per-installment administration fee for multi-payment plans | Every Dutch club charges extra for installments. Be Quick '28 charges 10%, SV Orion charges €3/installment. Members expect it; absence creates confusion about true cost | LOW | Configurable in Finance Settings: admin fee per installment (e.g., €2.50). Added to each installment amount. For 3-term plan: 2 extra admin fees (Sep installment included in base). For 8-term plan: 7 extra admin fees. Show breakdown on landing page before member confirms choice. |
| Automatic installment emails on the 25th with Mollie payment links | Each installment needs its own payment link so the member pays exactly that amount. Manual email sending per installment at scale (500 members × 8 months) is not viable | HIGH | WordPress cron runs daily. On the 25th of each month, query all invoices where: billing_plan is 3-term or 8-term, next installment is due this month, installment not yet sent. For each: generate individual Mollie payment link for installment amount, send email to member. Store sent_date and payment_link_id per installment in post meta array. |
| Overdue installment follow-up reminders | Members miss payments. Treasurer needs automatic escalation without having to track 500+ installment due dates manually | MEDIUM | Two-stage reminder on existing cron: 14 days after installment due date if unpaid → send member reminder email with payment link; 21 days after due date if still unpaid → resend with BCC to treasurer. Treasurer gets visibility on persistent non-payers without daily manual checking. |
| Facturen page filter by invoice type | Treasurer must separate "Facturen van Nikki" from "Facturen van Rondo" from "Boetes" | LOW | Add `type` filter to Facturen list page (`membership` vs `discipline`). Use URL search params (existing pattern in codebase). REST API already filters by `person_id` and `status`; extend to support `invoice_type`. |
| Facturen page filter by payment plan | Treasurer needs to see "who has 8-term plans?" to understand cash flow timing and identify installment-tracking load | LOW | Add `payment_plan` filter to Facturen list page (`full`, `3term`, `8term`). Stored on invoice, queryable via meta_query. |
| Facturen page filter by overdue installments | Treasurer needs a single view of "who owes money right now?" — one action, not manual scanning | LOW | Add `overdue_installments` filter: returns membership invoices where at least one installment is past due and not paid. This is different from the invoice-level `overdue` status which applies to the whole invoice. |
| Finance capability for non-admin users | Treasurer is not the WordPress admin. They need access to Contributie and Facturen pages without full admin rights | LOW | The `financieel` capability and `rondo_bestuur` role already exist in `UserRoles::ROLES`. The gap is in the sidebar navigation and route guards: Finance menu items currently check `financieel` capability, which is correct. Verify ProtectedRoute in router.jsx enforces `financieel` and the Finance Settings page reads from the correct capability. This may be partially or fully done — verify in code before treating as new work. |

### Differentiators (Competitive Advantage)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Member self-selects payment plan via token link | Member chooses their plan independently — no back-and-forth with treasurer, no paper forms, no WhatsApp messages. Standard Dutch billing portals (ClubCollect) work this way. | MEDIUM | Token page renders three cards: Volledig / 3 Termijnen / 8 Termijnen. Each card shows total cost including admin fees. Member clicks their choice, plan is stored, confirmation email sent. Token is one-time-use (invalidated after plan selection). This replaces ClubCollect for this club. |
| Mollie webhook auto-marks installments paid | When a member pays their installment, the invoice updates automatically — treasurer has real-time visibility without manual reconciliation. Existing discipline case invoices already use this webhook pattern. | LOW | Extend existing Mollie webhook handler (`class-mollie-webhook.php`) to distinguish installment payments from single invoice payments. Each installment has its own `_mollie_payment_id`. On webhook: find invoice by payment_id, identify which installment, mark that installment as paid, update aggregate invoice status. |
| Treasurer BCC on second reminder | Treasurer stays informed about persistent non-payers without actively checking the system. This is how Dutch clubs handle it (HZVV research: "2 reminders within 2 weeks, then player registration blocked"). The BCC is the digital equivalent of "flagging for follow-up". | LOW | The second reminder email (21 days overdue) is CC'd to a configurable treasurer email address stored in Finance Settings. Treasurer sees context: member name, amount, which installment, how long overdue. |
| Invoice type visible on member's person page | When treasurer views a member's record in Rondo Club, they see outstanding invoices inline — both membership and discipline. No separate navigation needed to check payment status. | LOW | The Person page likely shows invoices via the existing `useInvoices({ person_id })` hook. With `invoice_type` added, show separate sections: "Contributie" vs "Boetes". This leverages existing data — minimal new code. |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Mollie Recurring Subscriptions (mandates/SEPA direct debit) | "Auto-charge members on the 25th without them doing anything" | Mollie Recurring requires: (1) first payment with `sequenceType: first` to create SEPA mandate, (2) subsequent charges via `sequenceType: recurring`, (3) member must complete iDEAL first payment for mandate creation — not a familiar flow for club members. SEPA direct debit can fail silently (bounced debits), requires SEPA creditor ID, and puts club in the position of initiating debits rather than members paying voluntarily. For 500 members, mandate collection is a project unto itself. | Use separate Mollie payment links per installment. Member gets email on 25th, clicks link, pays iDEAL. Simple, familiar, no mandate required. Slight friction per installment is acceptable for annual membership payments. |
| Configurable installment schedules (admin defines arbitrary dates) | "What if we want 4 terms in Jan/Mar/May/Jul?" | Every variation multiplies UI complexity: admin must configure dates, members must understand a non-standard schedule, reminder cron logic branches per schedule. Dutch club norms are already standardized (Sep/Nov/Feb or monthly from Sep). | Hard-code the 3 plans (full, 3-term, 8-term) with fixed Dutch season dates. If the club needs something different in the future, add a new plan type then. Don't build generic schedule configuration upfront. |
| Member portal / member account for payment history | "Members should log in and see all their invoices" | Members are not tech-savvy (stated project constraint). Building a portal requires: member auth flow, password management, session handling, a separate UI context. Over-engineering for annual membership payments. | Token-secured one-time landing page is sufficient. After plan selection, member receives email confirmation with summary. If they need to check payment status, they contact the treasurer. |
| Automatic player registration blocking on non-payment | "KNVB-style enforcement: block member if they don't pay" | Rondo Club does not integrate with KNVB's registration system (Sportlink). Any "blocking" would be symbolic only. Creating enforcement mechanisms in the app without real enforcement capability is misleading and adds complexity. | Treasurer gets BCC on second reminder. They handle enforcement through existing club processes (phone call, KNVB Sportlink admin). |
| Per-member custom payment arrangement | "Some members can't afford the standard plans, let us set custom dates" | Rare edge case. Custom dates require: special installment schedule storage, separate cron handling, UI for admin to define per-member schedules. Maintenance cost vs actual use is poor. | Treasurer handles genuine hardship cases outside the system (direct bank transfer, manual mark-paid). The existing "mark as paid" on FactuurDetail already covers this. |
| Automatic PDF invoice per installment | "Each installment should have its own PDF" | Members don't need a PDF for each installment — they just need to pay the amount in the email. Generating 8 PDFs per member × 500 members = 4,000 PDFs per season. Storage, generation time, and email attachment size create problems. | One PDF for the full-season invoice (generated at invoice creation). Each installment email shows the amount due inline in the email body with a Mollie payment link. |
| Bulk-send all membership invoices immediately | "Send everyone at once with one button" | Sending 500 emails simultaneously will hit server rate limits and WordPress mail queue. Members receive an email and immediately need to make a plan decision — simultaneous sends cause confusion (multiple emails arriving in same minute, members calling each other, loads of plan selections at same time straining the system). | Send in batches of 50 with 1-second delay between batches (standard wp_mail bulk pattern). Or: send per-category in multiple sessions. The "bulk create concepts" step is separate from the "bulk send" step, giving treasurer control over timing. |

---

## Feature Dependencies

```
Per-Season Billing Method Toggle
    └──required by──> Bulk Concept Invoice Creation (must know if Rondo billing is active for season)
    └──required by──> Contributie Page (shows billing method indicator)

invoice_type field on rondo_invoice
    └──required by──> Facturen Type Filter
    └──required by──> Installment Tracking (only membership invoices have installments)
    └──required by──> Bulk Concept Invoice Creation (marks new invoices as membership type)

Bulk Concept Invoice Creation
    └──required by──> Public Token Landing Page (tokens attach to invoice records)
    └──required by──> Payment Plan Selection (plan stored on invoice)
    └──required by──> All installment emails (installments derived from invoice + plan)

Public Token Landing Page
    └──required by──> Payment Plan Selection (member picks plan on this page)

Payment Plan Selection (member chooses plan)
    └──stores──> payment_plan + installment_schedule on invoice
    └──required by──> Automatic Installment Emails (cron reads schedule to know when to send)
    └──required by──> Overdue Reminder Cron (compares schedule to today's date)

Per-Installment Admin Fee Configuration (Finance Settings)
    └──required by──> Payment Plan Selection Landing Page (must show correct totals)
    └──required by──> Automatic Installment Emails (installment amounts include fee)

Automatic Installment Emails (cron, 25th of month)
    └──requires──> Mollie Payment Link per installment (existing MolliePayment class, extended)
    └──enables──> Overdue Reminder Cron (no point reminding if emails never sent)

Mollie Webhook (existing)
    └──extended by──> Installment-level paid status update (new logic in existing handler)

Finance Capability for Non-Admin Users
    └──independent──> All Finance features (prerequisite but separate concern)
```

### Dependency Notes

- **Billing method toggle must be per-season:** If it were global, switching to Rondo billing would retroactively affect past seasons where Nikki invoiced. Season-keyed option prevents this.
- **invoice_type is a prerequisite for everything:** Without distinguishing membership from discipline invoices, the Facturen page becomes unmanageable and installment logic cannot target the right invoices.
- **Bulk creation is separate from bulk send:** Treasurer creates all concepts first, reviews them (correct amounts, correct members), then sends. This prevents mass-sending wrong amounts. This is how the existing discipline invoice flow works — concepts exist before sending.
- **Token landing page is the linchpin of the member UX:** If this is hard to use, everything downstream breaks (members phone the treasurer, plans don't get selected, cron emails go to unplanned members). Simplicity here is non-negotiable.
- **Mollie payment links per installment, not one link for the whole invoice:** A single payment link for the full amount cannot support partial payments in Mollie's model. Each installment needs its own link for its specific amount.

---

## MVP Definition

### Launch With (v1 — the milestone)

- [ ] **Per-season billing method toggle** — Required to activate Rondo invoicing without breaking Nikki's ongoing billing. Store in Finance Settings or Contributie Settings, season-keyed.
- [ ] **invoice_type field on rondo_invoice** — Prerequisite for all membership invoice features. Backfill existing invoices as `discipline`.
- [ ] **Bulk concept invoice creation endpoint** — `POST /rondo/v1/membership-fees/create-invoices`. Idempotent. Creates membership invoices from fee calculations for a season.
- [ ] **Public token-secured landing page** — `/betaalplan/{token}`. Shows member name, total, three plan options with admin fees included. Mobile-optimized for non-tech-savvy members.
- [ ] **Payment plan selection + installment schedule storage** — Member picks plan, schedule stored as structured post meta on invoice. Confirmation email sent to member.
- [ ] **Per-installment administration fee in Finance Settings** — Single configurable amount (e.g., €2.50/installment). Shown on landing page. Added to installment emails.
- [ ] **Automatic installment email cron (25th monthly)** — Runs on `rondo_daily_cron`. Checks installments due this month, generates Mollie payment link per installment, sends email. Marks installment as "sent" in meta.
- [ ] **Overdue reminders (14 days and 21 days)** — On same daily cron. 14-day reminder re-sends payment link. 21-day reminder re-sends with BCC to treasurer email (configurable in Finance Settings).
- [ ] **Facturen filters: type + payment plan + overdue installments** — Three additional filter dropdowns on Facturen page. URL search param pattern (existing codebase convention).
- [ ] **Finance capability verification** — Confirm `rondo_bestuur` role and `financieel` capability correctly gate all Finance pages and endpoints. Fix gaps if any.

### Add After Validation (v1.x)

- [ ] **Mollie webhook: installment-level paid marking** — Extends existing webhook. When member pays an installment, that specific installment is marked paid. Invoice aggregate status updates. Add once the basic email-and-pay flow is validated.
- [ ] **Treasurer dashboard: cash flow projection** — Show expected income per month based on selected payment plans. Useful for treasurer planning. Add once plan selection data exists in production.

### Future Consideration (v2+)

- [ ] **Batch sending with rate limiting** — Currently a concern for 500 members. Add a queue system if email failures occur in production.
- [ ] **Nikki reconciliation import** — If club continues using Nikki for some seasons, an import to mark invoices paid from Nikki's export. Low priority — Rondo billing replaces Nikki.

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Per-season billing method toggle | HIGH | LOW | P1 |
| invoice_type field + backfill | HIGH | LOW | P1 |
| Bulk concept invoice creation | HIGH | MEDIUM | P1 |
| Public token landing page | HIGH | MEDIUM | P1 |
| Payment plan selection (3 fixed plans) | HIGH | MEDIUM | P1 |
| Per-installment admin fee (Finance Settings) | HIGH | LOW | P1 |
| Automatic installment emails (cron) | HIGH | HIGH | P1 |
| Overdue reminders (14d + 21d + BCC) | HIGH | MEDIUM | P1 |
| Facturen filter: invoice type | MEDIUM | LOW | P1 |
| Facturen filter: payment plan | MEDIUM | LOW | P1 |
| Facturen filter: overdue installments | MEDIUM | LOW | P1 |
| Finance capability verification | HIGH | LOW | P1 |
| Mollie webhook: installment-level update | MEDIUM | MEDIUM | P2 |
| Treasurer cash flow projection | MEDIUM | MEDIUM | P2 |
| Batch send rate limiting | LOW | MEDIUM | P3 |

**Priority key:**
- P1: Must have for this milestone — required for the feature to work end-to-end
- P2: Add once core flow validated in production
- P3: Future, only if explicitly needed

---

## Implementation Notes for Roadmap

### Data Model: Installment Schedule on Invoice

The installment schedule should be stored as a structured array in post meta on `rondo_invoice`, not as separate post type. For 500 members × 8 installments = 4,000 records — this is post meta territory, not a new CPT.

Suggested post meta structure:

```php
// Stored as: get_post_meta($invoice_id, '_installment_plan', true)
[
    'plan_type'   => 'monthly_8',  // 'full', 'quarterly_3', 'monthly_8'
    'installments' => [
        [
            'due_date'       => '2025-09-25',
            'amount'         => 53.50,           // includes admin fee
            'admin_fee'      => 2.50,
            'status'         => 'paid',           // 'pending', 'sent', 'paid', 'overdue'
            'sent_at'        => '2025-09-25 09:03:22',
            'paid_at'        => '2025-09-26 14:22:11',
            'mollie_payment_id' => 'tr_abc123',
            'payment_link'   => 'https://paymentlink.mollie.com/...',
        ],
        // ... remaining installments
    ],
    'admin_fee_per_installment' => 2.50,  // Snapshot of setting at time of plan selection
    'selected_at' => '2025-08-20 10:15:00',
    'selection_token' => 'abc123...',     // Invalidated after selection
    'selection_token_expires' => '2026-07-01',  // Season end date
]
```

### Token Security Pattern

Token must be unforgeable and tied to a specific invoice. Use `wp_hash()` with a per-invoice salt:

```php
$token = wp_hash( $invoice_id . '_' . get_post_meta($invoice_id, '_token_salt', true) );
```

Store token on invoice post meta. Verify on landing page: compute expected token, compare with URL token. If mismatch → show error. If invoice already has a plan selected → show confirmation (idempotent). Tokens do not need to be single-use for the landing page display (member can reload the page), but plan selection should be idempotent (selecting same plan twice is fine, selecting different plan after already selected should warn or be locked).

### Cron Pattern: Daily check, not monthly schedule

`wp_schedule_event` with a custom monthly interval is unreliable (WP cron requires a page visit to trigger). Use existing `rondo_daily_cron` (or create one if not already present). On each run, check: is today the 25th? If yes, process installments due this month. Is today 14 or 21 days past any installment's due date? If yes, process reminders.

This is the same pattern used by `class-reminders.php` in this codebase.

### Bulk Invoice Creation: Idempotency

On re-run, the bulk creation endpoint must not create duplicate invoices. Check for existing `rondo_invoice` posts where:
- `invoice_type = membership`
- `person = {person_id}`
- `season = {season_key}`

If exists: skip (return as "skipped" count). This allows safe re-run if the process was interrupted.

### Payment Plan Fixed Schedules (2025-2026 Season)

**Plan A: Volledig (1 payment)**
- Sep 25, 2025: 100% of fee

**Plan B: 3 Termijnen (3 payments)**
- Sep 25, 2025: 50% + admin fee
- Nov 25, 2025: 25% + admin fee
- Feb 25, 2026: 25%

**Plan C: 8 Termijnen (8 payments)**
- Sep 25, 2025: base amount divided by 8
- Oct 25, 2025 through Apr 25, 2026: remaining 7 equal amounts + admin fee each

The percentages for Plan B (50/25/25 split) match the most common Dutch club pattern seen in research (AMVJ uses 3 terms; proportion reflects that September is the full season start, so first installment is largest).

For Plan C, equal amounts per installment is simpler than front-loading — members can budget the same amount each month.

### Landing Page: Mobile-First

Dutch football parents (the primary payer for youth members) will open the email on their phone. The landing page must work on mobile without any scrolling to find the submit button. Three plan cards should stack vertically, each showing: plan name, dates, amounts per installment, total cost. The "Kies dit plan" button must be prominent. No form fields. No login. One tap to select.

---

## Ecosystem Context: Dutch Club Billing Patterns

Research across 5 Dutch football clubs (AMVJ, HZVV, Be Quick '28, SV Orion, DFS) reveals:

| Pattern | Clubs Using It | Rondo Approach |
|---------|---------------|----------------|
| 1 lump sum payment | All clubs | Plan A: Volledig |
| 3 term payments | AMVJ, several others | Plan B: 3 Termijnen |
| 4 term payments | HZVV | Close enough to Plan B — not adding a 4th plan |
| Monthly (up to 8-10 payments) | Be Quick '28 (10), SV Orion (4) | Plan C: 8 Termijnen (Sep + 7 months) |
| Via ClubCollect | Most clubs | Rondo builds equivalent token-based flow |
| Admin fee per installment | All clubs (€1-€3/installment or 10% cap) | Configurable in Finance Settings |
| Payment reminders: 2 rounds | HZVV: 2 within 2 weeks | Rondo: 14 days, 21 days with BCC |
| Member portal | ClubCollect provides one | Not building one — token page is sufficient |

---

## Sources

- HZVV Contributie page (contributie 4 terms: Sep/Nov/Feb/May, 2 reminders within 2 weeks): https://hzvv.nl/vereniging/contributie/
- AMVJ Voetbal contributie (3 monthly installments via ClubCollect, admin fee for incasso): https://amvjvoetbal.nl/club/contributie
- Be Quick '28 (10 monthly installments, 10% admin fee max €19): https://www.bequick28.nl/club/lidmaatschap/contributie/
- SV Orion (1-4 installments, €3/installment admin fee, €5 failed payment fee): https://www.sv-orion.nl/info/lidmaatschap/contributie
- SportMember: Treasurer role and automated reminders: https://www.sportmember.com/en/clubmanagement/treasurer-role-in-sports-club-2025
- Mollie Payment Links API (no expiry by default, webhook optional but recommended): https://docs.mollie.com/reference/create-payment-link
- Mollie Recurring Payments (mandate required, SEPA direct debit, first payment creates mandate): https://docs.mollie.com/docs/recurring-payments

---
*Feature research for: Membership fee invoicing with payment plans — Rondo Club Dutch sports club*
*Researched: 2026-02-18*
