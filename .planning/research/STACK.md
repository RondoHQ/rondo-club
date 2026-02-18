# Stack Research: Membership Fee Invoicing with Payment Plans

**Domain:** Payment plan selection, public token-secured landing pages, installment scheduling (WordPress PHP 8.0+ theme)
**Researched:** 2026-02-18
**Confidence:** HIGH

---

## What This Research Covers

This is a **subsequent milestone** on an existing codebase. The existing stack (WordPress, PHP 8.0+, React 18, Mollie SDK v3.9, mPDF, wp_mail, WP-Cron) is already in place and working. This document covers only what is **new or changed** for membership fee invoicing with payment plans.

---

## New Capabilities Required

| Capability | New? | Notes |
|-----------|------|-------|
| Public token-secured landing page | YES | App is 100% behind WP login today |
| Payment plan selection UI (1x / 3x / 8x) | YES | No payment plan concept exists yet |
| Mollie Subscriptions API (recurring) | YES | Only one-time payments used so far |
| Customer creation in Mollie | YES | No Mollie customer objects exist yet |
| Installment tracking in WordPress | YES | New post type or post_meta structure needed |
| Automatic follow-up emails via WP-Cron | YES | Pattern exists (Reminders class), needs installment variant |
| Overdue escalation reminders | YES | New cron hook and logic |

---

## Recommended Stack Additions

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Mollie Subscriptions API (via existing SDK) | `^3.9` (already installed) | Automatic recurring installment charges after mandate established | `CreateSubscriptionRequest` with `times` parameter limits total charges — no new dependency needed. Verified: class exists in `vendor/mollie/mollie-api-php/src/Http/Requests/CreateSubscriptionRequest.php` |
| Mollie Customers API (via existing SDK) | `^3.9` (already installed) | Create Mollie customer records required for subscriptions | `CreateCustomerRequest` verified in vendor. Customer ID must be stored on the WP person post (post_meta `_mollie_customer_id`). Required prerequisite for mandate flow |
| WordPress Rewrite API + `template_redirect` | Core WordPress | Public landing page URL (`/contributie/{token}/`) without WP login | Established pattern in codebase — iCal feed and CardDAV use identical approach. No new libraries. |
| WordPress `query_vars` + PHP template | Core WordPress | Serve payment plan landing page HTML from PHP template file in theme | `template_include` or `template_redirect` + `include` — same pattern as `rondo_theme_template_redirect()` in functions.php |
| `bin2hex(random_bytes(32))` | PHP 8.0+ built-in | Secure token generation for payment landing page links | Already used in `ICalFeed::generate_token()`. 64-character hex token, stored as post_meta on the invoice/fee record |
| WP-Cron (`wp_schedule_single_event`) | Core WordPress | Send installment due reminders and overdue escalation emails | Already used for fee recalculation and async exports. Single-event scheduling is appropriate for per-invoice due-date reminders |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None new required | — | — | All needed libraries are already installed |

---

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `woocommerce/action-scheduler` | Packagist package is typed `wordpress-plugin`, not `wordpress-theme`; adds significant complexity and DB tables; overkill for dozens of scheduled installment emails | `wp_schedule_single_event()` with named hooks, already established in `FeeCacheInvalidator` and `GoogleContactsExport` |
| Mollie OAuth / Connect | Only needed for multi-merchant SaaS; this is a single-club deployment | Existing `FinanceConfig::get_mollie_api_key()` API key flow |
| Custom payment gateway libraries | The Mollie SDK already handles all payment operations | `mollie/mollie-api-php` already installed |
| React SPA for landing page | The landing page must be publicly accessible without WP login, nonce, or JS app bootstrap; a React SPA requires auth context and nonce injection via `wpApiSettings` | PHP template file served via `template_redirect` — simpler, faster, no auth dependency |
| JWT tokens for landing page | Stateless tokens make revocation impossible; club needs to be able to invalidate a payment link | Random token stored as post_meta (same as iCal) — can be invalidated by deleting/overwriting meta |

---

## Architecture: Mollie Payment Plan Flow

The recurring installment flow requires a **two-step Mollie interaction** that does not exist in the current codebase:

### Step 1: First Payment (establishes mandate)

The member visits the public landing page, selects "3 installments" or "8 installments", and completes the **first payment** through Mollie. This payment:

1. Creates (or reuses) a Mollie customer for the person (`CreateCustomerRequest`)
2. Creates a customer payment with `sequenceType: 'first'` (`CreateCustomerPaymentRequest`)
3. The member completes the payment — Mollie creates a **mandate** automatically
4. Mollie webhook fires → system records installment 1 as paid

**SDK class (verified in vendor):**
```php
use Mollie\Api\Http\Requests\CreateCustomerRequest;
use Mollie\Api\Http\Requests\CreateCustomerPaymentRequest;
use Mollie\Api\Http\Data\Money;

// Create customer (once per person, store cst_xxx as post_meta)
$customer = $mollie->send(new CreateCustomerRequest(
    name: $person_name,
    email: $person_email,
    metadata: ['person_id' => $person_id]
));
update_post_meta($person_id, '_mollie_customer_id', $customer->id);

// Create first payment to establish mandate
$payment = $mollie->send(new CreateCustomerPaymentRequest(
    customerId: $customer->id,
    description: 'Contributie 2025-2026 — termijn 1',
    amount: new Money('EUR', '45.00'),
    redirectUrl: home_url('/contributie/' . $token . '/bedankt/'),
    webhookUrl: rest_url('rondo/v1/mollie/webhook'),
    sequenceType: 'first',
    metadata: ['invoice_id' => $invoice_id, 'installment' => 1]
));
```

### Step 2: Subscription (charges remaining installments automatically)

After the mandate is confirmed (via webhook), create a Mollie subscription for the remaining installments:

**SDK class (verified in vendor, all params confirmed):**
```php
use Mollie\Api\Http\Requests\CreateSubscriptionRequest;
use Mollie\Api\Http\Data\Money;

// times: remaining installments (2 for "3x", 7 for "8x")
// interval: '1 month' (monthly installments)
// startDate: first day of next month
$subscription = $mollie->send(new CreateSubscriptionRequest(
    customerId: $customer_id,
    amount: new Money('EUR', '45.00'),
    interval: '1 month',
    description: 'Contributie 2025-2026',
    times: 2,           // null = endless; set to remaining count
    startDate: new \DateTime('first day of next month'),
    webhookUrl: rest_url('rondo/v1/mollie/webhook'),
    metadata: ['invoice_id' => $invoice_id]
));
update_post_meta($invoice_id, '_mollie_subscription_id', $subscription->id);
```

### Full-Payment Flow (1x — no subscription)

The existing `MolliePayment::create_payment_link()` flow (one-time payment link) is reused as-is. No `sequenceType` is set, no customer required.

---

## Architecture: Public Landing Page

The public landing page at `/contributie/{token}/` follows the **identical pattern** to `ICalFeed`:

```php
// In a new PublicPaymentLanding class:

public function register_rewrite_rules(): void {
    add_rewrite_rule(
        '^contributie/([a-f0-9]{64})/?$',
        'index.php?rondo_payment_token=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^contributie/([a-f0-9]{64})/bedankt/?$',
        'index.php?rondo_payment_token=$matches[1]&rondo_payment_step=bedankt',
        'top'
    );
}

public function add_query_vars(array $vars): array {
    $vars[] = 'rondo_payment_token';
    $vars[] = 'rondo_payment_step';
    return $vars;
}

public function handle_request(): void {
    $token = get_query_var('rondo_payment_token');
    if (empty($token)) {
        return;
    }

    // Look up invoice by token stored in post_meta
    $invoice = $this->find_invoice_by_token($token);
    if (!$invoice) {
        status_header(404);
        include get_template_directory() . '/templates/404.php';
        exit;
    }

    // Serve standalone PHP template (no WP login, no React app)
    include get_template_directory() . '/templates/payment-landing.php';
    exit;
}
```

**Token generation** (follow existing `ICalFeed` pattern exactly):
```php
$token = bin2hex(random_bytes(32)); // 64-char hex
update_post_meta($invoice_id, '_payment_landing_token', $token);
```

**Important:** `rondo_theme_template_redirect()` in `functions.php` currently intercepts 404s and serves the React SPA. The public landing page handler must run at **priority 0** (before priority 1) to return before the SPA handler takes over.

---

## Architecture: Installment Scheduling with WP-Cron

For the **full-payment** and **3-installment** manual reminder flows (not Mollie subscription), use WP-Cron single events:

```php
// Schedule reminder 7 days before installment due date
wp_schedule_single_event(
    strtotime($due_date . ' -7 days'),
    'rondo_installment_reminder',
    [$invoice_id, $installment_number]
);

// Schedule overdue escalation 7 days after due date
wp_schedule_single_event(
    strtotime($due_date . ' +7 days'),
    'rondo_installment_overdue',
    [$invoice_id, $installment_number]
);
```

**Hook naming follows existing pattern** (`rondo_user_reminder`, `rondo_async_calendar_rematch`, etc.).

**Important caveat:** WP-Cron fires only on page visits. The production server should have a real server cron triggering `wp-cron.php` every 5 minutes for reliability. This is a deployment concern, not a code change.

---

## Mollie Subscription Limitations & Design Decision

### The Mollie Subscriptions approach requires SEPA Direct Debit

When a Dutch member pays the "first" payment via **iDEAL**, Mollie creates a SEPA Direct Debit mandate (not a new iDEAL mandate). Subsequent subscription charges go via SEPA Direct Debit automatically.

**This requires SEPA Direct Debit to be activated on the Mollie account.** This is an account-level business requirement, not a code issue.

### Alternative: Manual installment tracking

If SEPA Direct Debit is not available or desired, installments can be **fully manual**:
- Each installment generates a separate Mollie payment link (same as existing flow)
- Scheduled via WP-Cron — send email with payment link at each installment due date
- Member clicks link, pays via iDEAL each time (no mandate required)
- More member friction but simpler technically and no SEPA activation needed

**Recommendation:** Build both. Use Mollie Subscriptions for the automatic flow (member opts in), manual payment links as fallback or for members who prefer to pay each installment manually. The system should store `payment_plan_type` (`automatic` vs `manual`) on the installment plan record.

---

## Data Storage Pattern

No new database tables. Follow Rule 0:

| Data | Storage |
|------|---------|
| Installment plan (plan type, total, installments, season) | New CPT `rondo_payment_plan` OR post_meta on `rondo_invoice` |
| Individual installment records | Post_meta array on the plan, or separate CPT `rondo_installment` |
| Mollie customer ID per person | Post_meta `_mollie_customer_id` on `person` post |
| Mollie subscription ID per plan | Post_meta `_mollie_subscription_id` on payment plan |
| Public landing page token | Post_meta `_payment_landing_token` on `rondo_invoice` |
| Installment payment status | Post_meta `_installment_{n}_status` and `_installment_{n}_paid_at` |

**Recommendation:** Use a new `rondo_payment_plan` CPT (not sub-posts) so WP_Query can find all plans for a member across seasons.

---

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Mollie Subscriptions API for automatic installments | Manual payment links per installment | When SEPA Direct Debit is not activated on Mollie account; or when member prefers to pay each installment manually |
| WP-Cron for reminder emails | Action Scheduler | Only worth it if scheduling thousands of concurrent jobs; overkill for a sports club with ~200 members |
| PHP template for public landing page | React SPA with public route | React SPA requires WP nonce and `wpApiSettings` bootstrap; not suitable for unauthenticated pages |
| Rewrite rules + `query_vars` + `template_redirect` | WordPress Page with custom template | Custom page approach requires creating a DB record; rewrite rules are code-only, version-controlled |
| Token stored in post_meta | Signed JWT URL | JWT cannot be revoked without a blocklist; post_meta token can be deleted to invalidate link immediately |

---

## Installation

No new PHP packages required. All capabilities use:
- `mollie/mollie-api-php:^3.9` — already installed
- WordPress core functions — built-in
- PHP 8.0+ built-ins — already required

```bash
# Nothing to install — existing dependencies cover all new capabilities
# After adding new classes, run composer dump-autoload if not using classmap
composer dump-autoload --optimize
```

---

## Version Compatibility

| Component | Version | Status | Notes |
|-----------|---------|--------|-------|
| `CreateSubscriptionRequest` | mollie-api-php ^3.9 | Verified in vendor | `times` parameter exists; `startDate` accepts `DateTimeInterface` |
| `CreateCustomerRequest` | mollie-api-php ^3.9 | Verified in vendor | `name`, `email`, `locale`, `metadata` params |
| `CreateCustomerPaymentRequest` | mollie-api-php ^3.9 | Verified in vendor | `sequenceType` is a nullable string parameter |
| WordPress Rewrite API | WP 6.0+ | Verified (existing usage) | Must flush rewrite rules on theme activation |
| `bin2hex(random_bytes(32))` | PHP 8.0+ | Verified (existing usage in ICalFeed) | 64-char hex token |

---

## Sources

- Codebase: `vendor/mollie/mollie-api-php/src/Http/Requests/CreateSubscriptionRequest.php` — `times` param, `startDate` type, `customerId` route param (HIGH confidence — read directly)
- Codebase: `vendor/mollie/mollie-api-php/src/Http/Requests/CreateCustomerPaymentRequest.php` — `sequenceType` nullable string param (HIGH confidence — read directly)
- Codebase: `vendor/mollie/mollie-api-php/src/Http/Requests/CreateCustomerRequest.php` — constructor params (HIGH confidence — read directly)
- Codebase: `includes/class-ical-feed.php` — token generation and rewrite rules pattern to replicate (HIGH confidence — read directly)
- Codebase: `includes/class-fee-cache-invalidator.php` — `wp_schedule_single_event` pattern (HIGH confidence — read directly)
- [Mollie Docs: Recurring Payments](https://docs.mollie.com/docs/recurring-payments) — iDEAL creates SEPA Direct Debit mandate; SEPA activation required; flow diagram (HIGH confidence — official docs)
- [Mollie Docs: Create Subscription](https://docs.mollie.com/reference/create-subscription) — `times` parameter limits total charges; `startDate` format YYYY-MM-DD; interval format (HIGH confidence — official docs)
- [Packagist: woocommerce/action-scheduler 3.9.3](https://packagist.org/packages/woocommerce/action-scheduler) — `wordpress-plugin` type, not suitable for theme distribution (HIGH confidence — official package metadata)
- [WordPress Developer Docs: add_rewrite_rule](https://developer.wordpress.org/reference/functions/add_rewrite_rule/) — rewrite rule registration pattern (HIGH confidence — official docs)

---

*Stack research for: Membership fee invoicing with payment plans, public landing pages, installment scheduling*
*Researched: 2026-02-18*
