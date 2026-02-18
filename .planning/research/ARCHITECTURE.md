# Architecture Patterns: Membership Fee Invoicing with Payment Plans

**Domain:** Extending an existing WordPress invoice system for membership fees with payment plans, bulk creation, installment scheduling, overdue tracking, and a public landing page
**Researched:** 2026-02-18
**Confidence:** HIGH (based on direct codebase inspection — all existing components verified)

---

## System Overview

The existing invoice system handles discipline cases only. This milestone extends it for membership fees. The architectural challenge is that membership fee invoices introduce fundamentally new concepts — payment plans with installments, bulk creation across hundreds of members, a scheduled reminder system, and the first public-facing page — while the existing `rondo_invoice` CPT, `MolliePayment` service, `MollieWebhook` handler, and `RestInvoices` controller all remain in place and must not regress.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                PUBLIC LAYER (new — first unauthenticated page)           │
│                                                                         │
│  WordPress template: /betaling/{token}                                  │
│  PHP: PaymentLandingPage class                                          │
│  No React, no WP auth, reads token from post meta → renders HTML        │
└──────────────────────────────┬──────────────────────────────────────────┘
                               │ payment completed
                               ↓
┌─────────────────────────────────────────────────────────────────────────┐
│                MOLLIE WEBHOOK (unchanged public endpoint)                │
│                                                                         │
│  POST /rondo/v1/mollie/webhook                                          │
│  MODIFIED: after marking invoice paid, check for payment plan           │
│  If paid installment → mark installment paid, check if all paid         │
│  If all installments paid → transition parent invoice to rondo_paid     │
└──────────────────────────────┬──────────────────────────────────────────┘
                               │
┌─────────────────────────────────────────────────────────────────────────┐
│                REST LAYER (rondo/v1, authenticated)                      │
│                                                                         │
│  RestInvoices (MODIFIED)          RestMembershipInvoices (NEW)          │
│  - unchanged discipline flow      - bulk create from fee data           │
│  - payment plan fields in         - list/get/filter by invoice_type     │
│    format_invoice_detail()        - installment status summary          │
│  - on send: if has_payment_plan                                         │
│    → create installments via                                            │
│    PaymentPlanManager                                                   │
│                                                                         │
│  RestInvoiceInstallments (NEW)                                          │
│  - GET /invoices/{id}/installments                                      │
│  - POST /invoices/{id}/installments/{n}/send                            │
│  - POST /invoices/{id}/installments/{n}/mark-paid                       │
└─────────────────────────────┬───────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────────────┐
│                SERVICE LAYER                                             │
│                                                                         │
│  PaymentPlanManager (NEW)         InstallmentScheduler (NEW)            │
│  - create_installments()          - WP-Cron hook: monthly send          │
│  - get_installments()             - sends due installment emails        │
│  - mark_installment_paid()        - escalates overdue installments      │
│  - all_installments_paid()                                              │
│                                   MembershipInvoiceBulkCreator (NEW)   │
│  MolliePayment (MODIFIED)         - iterates fee list                   │
│  - create_payment_link() now      - calls RestInvoices::create()        │
│    accepts optional $installment_ - progress tracking via WP transient  │
│    number context                                                        │
│                                                                         │
│  InvoiceEmailSender (MODIFIED)    PaymentLandingPage (NEW)              │
│  - new template vars for          - generates/validates opaque tokens   │
│    installment emails             - renders public payment page HTML    │
│  - installment email flow         - updates redirect URL in FinanceConf │
└─────────────────────────────┬───────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────────────┐
│                DATA LAYER                                                │
│                                                                         │
│  rondo_invoice CPT (MODIFIED — new ACF fields via acf-json)             │
│  Post meta (new):                                                       │
│    _invoice_type          'discipline' | 'membership'                   │
│    _invoice_season        '2025-2026'                                   │
│    _has_payment_plan      '1' | ''                                      │
│    _payment_plan_count    '3' | '2' (number of installments)           │
│    _installment_{n}_amount  float                                       │
│    _installment_{n}_due_date  'Ymd'                                     │
│    _installment_{n}_status  'pending' | 'sent' | 'paid' | 'overdue'    │
│    _installment_{n}_mollie_payment_id  'tr_xxx'                         │
│    _installment_{n}_sent_date  'Ymd'                                    │
│    _public_payment_token  opaque random string (for landing page URL)  │
│                                                                         │
│  WordPress Options (new):                                               │
│    rondo_installment_scheduler_active   bool                            │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Component Boundaries

### New Components

| Component | Namespace | Responsibility | Communicates With |
|-----------|-----------|----------------|-------------------|
| `RestMembershipInvoices` | `Rondo\REST` | Bulk invoice creation endpoint; fee-data-to-invoice conversion | `MembershipFees`, `RestInvoices` create flow, `PaymentPlanManager` |
| `RestInvoiceInstallments` | `Rondo\REST` | Read/send/mark-paid individual installments | `PaymentPlanManager`, `MolliePayment`, `InvoiceEmailSender` |
| `PaymentPlanManager` | `Rondo\Finance` | Create, read, update installments stored as post meta on invoice | WordPress post meta, `MolliePayment` |
| `InstallmentScheduler` | `Rondo\Finance` | WP-Cron job: find due installments, send emails, mark overdue | `PaymentPlanManager`, `InvoiceEmailSender`, `MolliePayment` |
| `MembershipInvoiceBulkCreator` | `Rondo\Finance` | Iterate members from fee data, create one invoice per member | `MembershipFees`, `RestInvoices` (reuses create logic), progress transient |
| `PaymentLandingPage` | `Rondo\Finance` | Token-gated public HTML page for members to pay; no WP login | WordPress template system, `FinanceConfig`, `MolliePayment` |

### Modified Components

| Component | What Changes | Why |
|-----------|-------------|-----|
| `rondo_invoice` ACF fields | Add `invoice_type`, `season`, `has_payment_plan`, `payment_plan_count` | Distinguish membership vs discipline invoices; store plan metadata |
| `RestInvoices::send_invoice()` | After send, if `has_payment_plan`: call `PaymentPlanManager::create_installments()` | Installment creation is triggered at send time, not at draft creation |
| `RestInvoices::format_invoice_detail()` | Include payment plan fields + installment summary in response | Frontend needs to render plan state |
| `MollieWebhook::handle_webhook()` | Check if matched invoice is an installment payment (`_installment_{n}_mollie_payment_id`); mark installment paid; check if all paid | Installments have their own Mollie payment IDs, not the parent invoice's |
| `MolliePayment::create_payment_link()` | Accept optional `$context` array (`installment_number`, `installment_amount`) to override amount and description | Installment payments are for a fraction of total; need separate Mollie payment per installment |
| `InvoiceEmailSender::send()` | Add installment-specific template variables: `{termijn}`, `{termijn_bedrag}`, `{termijn_vervaldatum}` | Installment reminder emails need different copy than the initial invoice email |
| `FinanceConfig` | Add `get_mollie_redirect_url()` setter; add `get_installment_email_template()` | Landing page needs its URL stored; installment emails need their own template |
| `functions.php` | Instantiate `MollieWebhook` class (MODIFIED), `RestMembershipInvoices`, `RestInvoiceInstallments`, `InstallmentScheduler`, `PaymentLandingPage` | Register new REST routes and cron hooks |
| `rondo_theme_template_redirect()` | Add `/betaling` to exempt routes (serve landing page template, not SPA index.php) | Public page is WordPress-rendered, not React |

---

## Data Flow

### Flow 1: Bulk Membership Invoice Creation

```
Admin clicks "Contributie factureren" (season, payment plan options)
    ↓
POST /rondo/v1/membership-invoices/bulk-create
    { season: '2025-2026', has_payment_plan: true, installment_count: 3 }
    ↓
RestMembershipInvoices::bulk_create()
    ↓
MembershipFees::get_fee_for_person_cached() — for each eligible person
    ↓ (for each person with calculable fee)
Create rondo_invoice CPT post, set ACF fields:
    invoice_type = 'membership'
    season = '2025-2026'
    has_payment_plan = true / false
    payment_plan_count = 3
    total_amount = person's final_fee
    line_items = [{ description: 'Contributie 2025-2026', amount: final_fee }]
    ↓
Store progress in WP transient (for ~500 members, this runs in a WP-Cron batch)
    ↓
Return { created: N, skipped: M, job_id: 'xyz' } immediately
Client polls GET /rondo/v1/membership-invoices/bulk-status/{job_id}
```

### Flow 2: Send Membership Invoice with Payment Plan

```
Admin sends invoice (POST /rondo/v1/invoices/{id}/send)
    ↓
RestInvoices::send_invoice() — existing flow runs:
    - MolliePayment::create_payment_link() for full invoice total
    - InvoicePdfGenerator::generate()
    - InvoiceEmailSender::send() (initial invoice email)
    - invoice status → rondo_sent
    ↓
if get_post_meta($invoice_id, '_has_payment_plan') === '1':
    ↓
PaymentPlanManager::create_installments($invoice_id)
    total = get_field('total_amount', $invoice_id)
    count = get_post_meta($invoice_id, '_payment_plan_count')
    installment_amount = round(total / count, 2)

    For n = 1 to count:
        due_date = calculate_due_date(n)  // n months from sent_date
        update_post_meta($invoice_id, '_installment_{n}_amount', $installment_amount)
        update_post_meta($invoice_id, '_installment_{n}_due_date', $due_date)
        update_post_meta($invoice_id, '_installment_{n}_status', 'pending')

    // Installment 1 is sent immediately:
    MolliePayment::create_payment_link($invoice_id, ['installment_number' => 1, 'amount' => installment_amount])
    → stores _installment_1_mollie_payment_id
    → stores _installment_1_payment_link (separate from parent invoice payment_link)
    InvoiceEmailSender::send_installment($invoice_id, installment_number: 1)
    update_post_meta($invoice_id, '_installment_1_status', 'sent')
```

### Flow 3: Mollie Webhook — Installment Payment Received

```
Member pays installment 1 on Mollie checkout page
    ↓
POST /rondo/v1/mollie/webhook  { id: 'tr_installment_abc' }
    ↓
MollieWebhook::handle_webhook()
    ↓
$payment = MollieClient::get()->payments->get('tr_installment_abc')
    ↓
$payment->isPaid() → true
    ↓
// Try invoice-level payment ID first (existing discipline path):
$invoice = find_invoice_by_meta('_mollie_payment_id', 'tr_installment_abc')

// If not found, try installment-level (new path):
if (!$invoice):
    find_invoice_by_any_meta('_installment_%_mollie_payment_id', 'tr_installment_abc')
    → returns ($invoice_id, $installment_number)
    ↓
    PaymentPlanManager::mark_installment_paid($invoice_id, $installment_number)
    update_post_meta($invoice_id, '_installment_{n}_status', 'paid')
    ↓
    PaymentPlanManager::all_installments_paid($invoice_id) ?
        YES → wp_update_post(post_status: 'rondo_paid'), update_field('status', 'paid')
        NO  → no invoice status change; next installment scheduled by cron
    ↓
HTTP 200 (always)
```

### Flow 4: Scheduled Installment Reminders (WP-Cron)

```
WP-Cron fires 'rondo_send_due_installments' (daily)
    ↓
InstallmentScheduler::process_due_installments()
    ↓
WP_Query: all rondo_invoice posts where _has_payment_plan = '1'
    and post_status IN ('rondo_sent', 'rondo_overdue')
    ↓
For each invoice, for each installment n (1 to _payment_plan_count):
    status = get_post_meta($invoice_id, '_installment_{n}_status')
    due_date = get_post_meta($invoice_id, '_installment_{n}_due_date')

    if status === 'pending' AND due_date <= today + advance_days:
        // Send installment email + create Mollie payment link
        MolliePayment::create_payment_link($invoice_id, ['installment_number' => n])
        InvoiceEmailSender::send_installment($invoice_id, n)
        update_post_meta → status = 'sent', sent_date = today

    elif status === 'sent' AND due_date < today:
        // Overdue: escalate
        update_post_meta → status = 'overdue'
        // Optional: send overdue reminder email
```

### Flow 5: Public Payment Landing Page

```
Member receives email with link: https://rondo.svawc.nl/betaling/{token}
    ↓
WordPress template_redirect:
    Path starts with 'betaling/' → do NOT serve React SPA index.php
    Load PaymentLandingPage template (PHP-rendered, no WP auth)
    ↓
PaymentLandingPage::render($token)
    ↓
Find invoice by _public_payment_token meta
    → Not found: render "Ongeldig token" page
    → Found but invoice is 'paid': render "Al betaald" confirmation page
    ↓
Get payment_link (or installment payment_link if payment plan)
    ↓
Render minimal HTML page (Tailwind CSS via CDN or inline):
    - Club logo from FinanceConfig
    - Invoice number, amount, due date
    - "Betaal nu" button → redirect to Mollie checkout URL
    - OR: installment breakdown if payment plan (shows which installment is due)
```

---

## Recommended Project Structure

```
includes/
├── class-rest-invoices.php                # MODIFIED: payment plan fields in create/format; send triggers installments
├── class-rest-membership-invoices.php     # NEW: bulk create endpoint + status polling
├── class-rest-invoice-installments.php   # NEW: installment CRUD + send/mark-paid
├── class-payment-plan-manager.php        # NEW: installment storage/retrieval/state transitions
├── class-installment-scheduler.php       # NEW: WP-Cron for due installments
├── class-membership-invoice-bulk-creator.php  # NEW: iterates MembershipFees to create invoices
├── class-invoice-email-sender.php        # MODIFIED: installment email template vars + send_installment()
├── class-mollie-payment.php              # MODIFIED: $context parameter for installment amount
├── class-mollie-webhook.php              # MODIFIED: installment payment ID lookup
├── class-payment-landing-page.php        # NEW: public HTML page + token management
└── class-finance-config.php              # MODIFIED: installment email template option

acf-json/
└── group_invoice_fields.json             # MODIFIED: add invoice_type, season, has_payment_plan, payment_plan_count fields

src/pages/Finance/
├── Facturen.jsx                          # MODIFIED: filter by invoice_type; show membership invoices
├── FactuurDetail.jsx                     # MODIFIED: installment timeline section
└── MembershipInvoices.jsx                # NEW: bulk create UI + progress display (or tab within existing Facturen)

src/pages/Contributie/
└── ContributieList.jsx                   # MODIFIED: "Factureer geselecteerde leden" bulk action

src/hooks/
├── useInvoices.js                        # MODIFIED: useInvoiceInstallments() hook
└── useMembershipInvoices.js              # NEW: useBulkCreate(), useBulkStatus()

functions.php                             # MODIFIED: instantiate new classes
```

---

## Architectural Patterns

### Pattern 1: Installments as Post Meta (Not a Separate CPT)

**What:** Each installment is stored as a set of numbered post meta keys on the parent `rondo_invoice` post. No new CPT, no separate ACF field group.

**Why:** The number of installments per invoice is small (2-3). Querying installment state is always done in the context of the parent invoice. A separate CPT would complicate every query, add an extra WP_Query per invoice, and create orphan records when invoices are deleted. Post meta with a numbered key prefix (`_installment_1_*`, `_installment_2_*`) is simpler, transactional (deletion of the invoice post cascades to all its meta), and consistent with how Mollie payment IDs are already stored.

**Trade-off:** Post meta is not directly queryable in a JOIN sense. For the scheduler to find "all invoices with overdue installment 2", it must query posts with `_has_payment_plan = '1'` and then loop. At 500 members this is fast. If the club scales to 5,000 members, the query strategy would need revisiting — but that is not this club's scope.

**Implementation:**
```php
// PaymentPlanManager::create_installments()
public function create_installments( int $invoice_id, int $count ): void {
    $total    = (float) get_field( 'total_amount', $invoice_id );
    $base     = round( $total / $count, 2 );
    $sent_date = get_post_meta( $invoice_id, 'sent_date', true );
    $sent_ts   = $sent_date ? strtotime( $sent_date ) : time();

    for ( $n = 1; $n <= $count; $n++ ) {
        $due = date( 'Ymd', strtotime( "+{$n} months", $sent_ts ) );
        update_post_meta( $invoice_id, "_installment_{$n}_amount",   $base );
        update_post_meta( $invoice_id, "_installment_{$n}_due_date", $due );
        update_post_meta( $invoice_id, "_installment_{$n}_status",   'pending' );
    }
    update_post_meta( $invoice_id, '_payment_plan_count', $count );
    update_post_meta( $invoice_id, '_has_payment_plan',   '1' );
}
```

### Pattern 2: MollieWebhook Extended with Fallback Lookup

**What:** The existing webhook first searches for the payment ID in `_mollie_payment_id` (invoice-level). If not found, it searches across `_installment_1_mollie_payment_id` through `_installment_N_mollie_payment_id` using a meta_query with LIKE.

**Why:** Installment payments have separate Mollie payment IDs from the parent invoice. The webhook receives only the payment ID and must determine whether it belongs to a full-invoice payment or an installment payment.

**Implementation:**
```php
// In MollieWebhook::handle_webhook() — after failing to find invoice by _mollie_payment_id:
$query = new \WP_Query([
    'post_type'      => 'rondo_invoice',
    'post_status'    => 'any',
    'posts_per_page' => 1,
    'meta_query'     => [[
        'key'     => '_installment_%_mollie_payment_id',
        'value'   => $payment_id,
        'compare' => '=',
    ]],
]);

// NOTE: WordPress meta_query with wildcard % in key does NOT work with MySQL LIKE.
// Instead, search with a direct $wpdb->get_row() for efficiency, or:
// Store a reverse-lookup key: update_post_meta($invoice_id, '_mollie_pid_' . $payment_id, $installment_number)
// Then look up: get_posts where meta_key = '_mollie_pid_' . $payment_id
```

**Recommended: Reverse Lookup Key Pattern**

When creating an installment payment link, store a reverse-lookup meta:
```php
update_post_meta( $invoice_id, '_mollie_pid_' . $payment->id, $installment_number );
```

Then in the webhook:
```php
$reverse = get_posts([
    'post_type'   => 'rondo_invoice',
    'post_status' => 'any',
    'meta_key'    => '_mollie_pid_' . $payment_id,
    'fields'      => 'ids',
    'numberposts' => 1,
]);
if ( $reverse ) {
    $invoice_id        = $reverse[0];
    $installment_number = (int) get_post_meta( $invoice_id, '_mollie_pid_' . $payment_id, true );
    // mark installment paid
}
```

This avoids the WP meta_query wildcard limitation entirely. HIGH confidence this is the correct pattern.

### Pattern 3: Public Landing Page via WordPress Template Redirect

**What:** A dedicated PHP class `PaymentLandingPage` registers on `template_redirect` (priority 5, before the SPA redirect at priority 1... actually: the SPA redirect runs at priority 1 with an early return, so the landing page must intercept before it). The class checks if the URL path starts with `betaling/`, validates the token, and renders a standalone HTML page.

**Implementation approach:**

The existing `rondo_theme_template_redirect()` runs at priority 1. It serves `index.php` for any path it recognises and calls `exit`. To allow `/betaling/*` to be handled by the new class, one of two approaches works:

**Option A (modify existing function):** Add `betaling` as an excluded prefix before the SPA match. Then `PaymentLandingPage` registers at priority 1 as well (same priority, but added after, so runs second — or use priority 0).

**Option B (check in PaymentLandingPage at priority 0):** Register `PaymentLandingPage` at priority 0. If path starts with `betaling/`, render and exit. The SPA redirect at priority 1 never fires.

**Recommended: Option B — PaymentLandingPage at priority 0**

```php
// In PaymentLandingPage::__construct():
add_action( 'template_redirect', [ $this, 'maybe_render' ], 0 );

public function maybe_render(): void {
    $path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( ! str_starts_with( $path, 'betaling/' ) ) {
        return; // Let SPA handle it
    }

    $token = substr( $path, strlen( 'betaling/' ) );
    $this->render( sanitize_text_field( $token ) );
    exit;
}
```

**Token generation:** When a membership invoice is sent, generate a cryptographically random token:
```php
$token = bin2hex( random_bytes( 32 ) ); // 64-char hex string
update_post_meta( $invoice_id, '_public_payment_token', $token );
```

The landing page URL is: `home_url( '/betaling/' . $token )`

This URL is included in the membership invoice email as the primary call-to-action link instead of (or in addition to) the direct Mollie checkout URL.

**Landing page renders standalone HTML** (no React, no WP admin bar, no theme files). Use inline Tailwind via CDN for styling, or a minimal embedded `<style>` block that matches the brand from `FinanceConfig::get_accent_color()`.

### Pattern 4: Bulk Creation via WP-Cron Batching

**What:** Creating 500 invoices in a single HTTP request will time out. The bulk create endpoint starts a WP-Cron batch job and returns a job ID immediately. The React frontend polls a status endpoint.

**Implementation:**
```php
// POST /rondo/v1/membership-invoices/bulk-create
public function start_bulk_create( \WP_REST_Request $request ): \WP_REST_Response {
    $season     = $request->get_param( 'season' );
    $has_plan   = (bool) $request->get_param( 'has_payment_plan' );
    $plan_count = (int) $request->get_param( 'installment_count' );

    $job_id = 'bulk_' . uniqid();
    set_transient( $job_id, [ 'status' => 'pending', 'created' => 0, 'skipped' => 0 ], HOUR_IN_SECONDS );

    wp_schedule_single_event( time(), 'rondo_bulk_create_invoices', [ $job_id, $season, $has_plan, $plan_count ] );

    return rest_ensure_response( [ 'job_id' => $job_id ] );
}
```

The cron handler runs `MembershipInvoiceBulkCreator::run()`, updating the transient as it processes. The React frontend polls `GET /rondo/v1/membership-invoices/bulk-status/{job_id}` every 2 seconds until `status === 'complete'`.

**Batch size:** Process 50 members per cron execution to stay within the 30-second PHP execution limit. If more remain, schedule a follow-up cron event immediately.

### Pattern 5: Invoice Type Discrimination via Post Meta

**What:** A `_invoice_type` post meta field (`'discipline'` or `'membership'`) distinguishes the two invoice flows. The `line_items` ACF repeater remains shared — membership invoices use it with `discipline_case = null` and a description like "Contributie 2025-2026". The ACF field group gets three new optional top-level fields: `invoice_type`, `season`, `has_payment_plan`.

**Why:** The existing `rondo_invoice` CPT handles the common invoice shape (number, person, line items, total, status, payment link). A separate CPT for membership invoices would duplicate the entire payment, PDF, and email infrastructure. Discrimination by post meta keeps the CPT unified and the `RestInvoices` controller largely unchanged.

**ACF additions** (in `group_invoice_fields.json`):
```json
{
    "key": "field_invoice_type",
    "name": "invoice_type",
    "type": "select",
    "choices": { "discipline": "Tuchtzaak", "membership": "Contributie" },
    "default_value": "discipline"
},
{
    "key": "field_invoice_season",
    "name": "season",
    "type": "text"
},
{
    "key": "field_invoice_has_payment_plan",
    "name": "has_payment_plan",
    "type": "true_false",
    "default_value": 0
},
{
    "key": "field_invoice_payment_plan_count",
    "name": "payment_plan_count",
    "type": "number",
    "min": 2,
    "max": 12
}
```

---

## Build Order

Build order follows strict dependency direction. Each phase is deployable and testable independently.

### Phase 1: Data Model Extension

**Depends on:** Nothing.

- Add new ACF fields to `group_invoice_fields.json`: `invoice_type`, `season`, `has_payment_plan`, `payment_plan_count`
- No PHP changes needed yet — ACF auto-sync on deploy
- After deploy: verify fields appear on invoice posts in WP admin

### Phase 2: PaymentLandingPage (Public Page)

**Depends on:** Phase 1 (token stored on invoice posts).

- Create `includes/class-payment-landing-page.php`
- Register at `template_redirect` priority 0
- Token generation: add to `RestInvoices::send_invoice()` when `invoice_type === 'membership'`
- Add `betaling` path to `rondo_theme_template_redirect()` exclusion list as a safeguard
- Test: manually create a token, visit `/betaling/{token}`, verify page renders without WP auth

**Why Phase 2 before payment plans:** The landing page is needed by membership invoice emails regardless of whether payment plans are involved. It's also the lowest-risk new feature — pure read-only PHP rendering, no data mutations.

### Phase 3: MolliePayment Installment Context

**Depends on:** Phase 1 (invoice type discrimination).

- Modify `MolliePayment::create_payment_link()` to accept optional `$context` parameter
- If `$context['installment_number']` is set, use `$context['amount']` and append installment number to description
- Store reverse-lookup meta `_mollie_pid_{payment_id}` = `installment_number` on the invoice
- Test: manually call with installment context, verify Mollie receives correct amount

### Phase 4: PaymentPlanManager + MollieWebhook Extension

**Depends on:** Phase 3 (installment Mollie payment IDs in reverse-lookup format).

- Create `includes/class-payment-plan-manager.php`
- Modify `MollieWebhook::handle_webhook()` to check reverse-lookup meta after invoice-level lookup fails
- Mark installment paid; check `all_installments_paid()` to transition parent invoice
- Test: use Mollie test mode; confirm installment status transitions

### Phase 5: InstallmentScheduler (WP-Cron)

**Depends on:** Phase 4 (PaymentPlanManager).

- Create `includes/class-installment-scheduler.php`
- Register `rondo_send_due_installments` WP-Cron hook (daily, runs at midnight)
- Logic: query invoices with `_has_payment_plan = '1'`; for each, check installment statuses
- Send due installments via `MolliePayment` + `InvoiceEmailSender`
- Modify `InvoiceEmailSender::send()` to handle installment template variables
- Test: manually trigger hook, verify installment 2 email sent on schedule

### Phase 6: RestInvoices Extension for Payment Plans

**Depends on:** Phases 4+5 (PaymentPlanManager, InstallmentScheduler).

- Modify `RestInvoices::send_invoice()`: after status → rondo_sent, check `_has_payment_plan`; call `PaymentPlanManager::create_installments()` and send installment 1
- Modify `RestInvoices::format_invoice_detail()`: include installment array in response
- Add `RestInvoiceInstallments` controller (GET installments, POST mark-paid manually)
- Test: send a membership invoice with payment plan; verify installments created and installment 1 sent

### Phase 7: Bulk Invoice Creation

**Depends on:** Phase 6 (RestInvoices create logic handles membership type).

- Create `includes/class-membership-invoice-bulk-creator.php`
- Create `includes/class-rest-membership-invoices.php` (bulk create + status endpoints)
- WP-Cron batch logic: 50 members per execution, transient progress tracking
- Frontend: `MembershipInvoices.jsx` or new tab in Contributie page with progress display
- Test: trigger bulk create for current season, verify invoices created, no timeouts

### Phase 8: Frontend Updates (Facturen + Contributie)

**Depends on:** Phase 6+7 (REST API returns invoice_type, installment data).

- `Facturen.jsx`: add invoice_type filter (show all / discipline / membership)
- `FactuurDetail.jsx`: render installment timeline when `has_payment_plan: true`
- `ContributieList.jsx`: add "Factureer" bulk action button to send to bulk create flow

---

## Integration Points

### What Remains Unchanged

| Component | Status | Notes |
|-----------|--------|-------|
| `rondo_invoice` CPT registration | Unchanged | New ACF fields are additive |
| `RestInvoices` discipline flow | Unchanged | `invoice_type: 'discipline'` path is untouched |
| `MollieWebhook` existing path | Unchanged | Falls through to existing discipline lookup if not installment |
| `InvoicePdfGenerator` | Unchanged | PDF generation is provider-agnostic |
| `RabobankPayment` | Unchanged | Only Mollie supports installments; Rabobank users skip payment plan feature |
| `FinanceConfig` credential encryption | Unchanged | |
| `MembershipFees` fee calculation | Unchanged | `get_fee_for_person_cached()` is consumed by bulk creator |

### New REST Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/rondo/v1/membership-invoices/bulk-create` | POST | `financieel` | Start batch invoice creation |
| `/rondo/v1/membership-invoices/bulk-status/{job_id}` | GET | `financieel` | Poll batch progress |
| `/rondo/v1/invoices/{id}/installments` | GET | `financieel` | List installments for invoice |
| `/rondo/v1/invoices/{id}/installments/{n}/send` | POST | `financieel` | Manually send installment n |
| `/rondo/v1/invoices/{id}/installments/{n}/mark-paid` | POST | `financieel` | Manually mark installment paid |

### External Service Changes

| Service | Change |
|---------|--------|
| Mollie API | New payment calls per installment (each installment = separate Mollie payment with its own `tr_xxx` ID) |
| Mollie Webhook | Receives webhook for installment payments — webhook URL unchanged, logic extended |

### Frontend Route Changes

| Route | Change |
|-------|--------|
| `/betaling/{token}` | New — PHP-rendered public page, not React |
| `/financien/facturen` | New filter by `invoice_type` |
| `/financien/contributie/per-lid` | New "Factureer" action |

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Creating a Separate CPT for Membership Invoices

**What goes wrong:** Developer creates `rondo_membership_invoice` CPT alongside `rondo_invoice`.

**Why:** Duplicates PDF generation, email sending, Mollie payment integration, ACF field groups, and REST infrastructure. The two CPTs diverge over time. Existing invoice list page can't show unified view.

**Do instead:** Discriminate with `_invoice_type` post meta on the existing `rondo_invoice` CPT. Conditional logic is limited to the `RestInvoices` and `MollieWebhook` — both already have provider branching as a precedent.

### Anti-Pattern 2: Creating Installments in a Separate ACF Repeater

**What goes wrong:** Developer adds an ACF repeater field `payment_plan_installments` to the invoice field group.

**Why:** ACF repeater rows generate `field_N` suffixed keys in wp_postmeta. Querying "which invoices have an overdue installment" via WP_Query meta_query is impossible without knowing which row index corresponds to which installment. The cron scheduler would need to load every invoice and iterate ACF repeater data — slow and fragile.

**Do instead:** Flat numbered post meta (`_installment_1_status`, `_installment_2_status`). These are directly queryable, easily deleted with the parent post, and have no ACF SDK dependency at query time.

### Anti-Pattern 3: Generating All 500 Invoices in One HTTP Request

**What goes wrong:** `RestMembershipInvoices::bulk_create()` loops over all members and creates invoices inline.

**Why:** PHP execution timeout (30s) kills the request after ~50-100 invoices. The client never gets a response. Partial creation with no way to resume is worse than no creation.

**Do instead:** Return immediately with a `job_id`. Use `wp_schedule_single_event()` to run the batch in WP-Cron. Process 50 members per cron execution. Frontend polls status.

### Anti-Pattern 4: Serving the Public Landing Page via React

**What goes wrong:** Developer adds a `/betaling/:token` route to the React SPA and marks it public with `ProtectedRoute` skipped.

**Why:** The React SPA requires `wpApiSettings` injected via `wp_localize_script`, which requires WordPress to bootstrap. Members accessing the landing page from an email link are not WP-authenticated — the nonce won't be present. WordPress will still partially bootstrap (for script injection), revealing internal WP URLs and schema. Public pages must be pure PHP renders.

**Do instead:** Intercept the path in `template_redirect` (priority 0, before the SPA redirect), render a standalone PHP HTML page with no WP admin scripts, exit.

### Anti-Pattern 5: Using the Parent Invoice's Mollie Payment ID for Installment Lookups

**What goes wrong:** Developer reuses `_mollie_payment_id` for installment payments, overwriting it on each installment.

**Why:** The first installment overwrites the full-invoice payment ID. Mollie webhook for the first installment can't be matched. Idempotency in `MolliePayment::create_payment_link()` (which checks `_mollie_payment_id`) creates the wrong payment on re-run.

**Do instead:** Use separate meta keys per installment (`_installment_1_mollie_payment_id`). Use the reverse-lookup pattern (`_mollie_pid_{payment_id}`) for webhook matching. Leave `_mollie_payment_id` as the full-invoice payment reference.

---

## Scalability Considerations

Single club, ~500 members. Scale is not a primary driver. The only genuine scaling concern is the bulk creation flow: 500 WP_Query calls + 500 `wp_insert_post()` calls + 500 `update_field()` calls in sequence. The WP-Cron batch approach with 50-per-run limits handles this.

The installment scheduler (daily cron) queries all `rondo_invoice` posts with `_has_payment_plan = '1'`. At 500 members, this is one WP_Query returning at most 500 posts, each with a small fixed number of meta reads. Well within acceptable limits.

---

## Sources

- Existing codebase (direct inspection, HIGH confidence):
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php`
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-webhook.php`
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-payment.php`
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-membership-fees.php`
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php`
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-reminders.php`
  - `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_invoice_fields.json`
  - `/Users/joostdevalk/Code/rondo/rondo-club/functions.php`
  - `/Users/joostdevalk/Code/rondo/rondo-club/src/router.jsx`
- WordPress meta_query documentation — `LIKE` wildcard in meta_key is not supported by WP_Query; workaround is direct `$wpdb` query or reverse-lookup meta pattern. HIGH confidence (established limitation).
- WordPress `wp_schedule_single_event()` for one-time cron batching — HIGH confidence (core API).

---

*Architecture research for: Membership fee invoicing with payment plans — Rondo Club*
*Researched: 2026-02-18*
