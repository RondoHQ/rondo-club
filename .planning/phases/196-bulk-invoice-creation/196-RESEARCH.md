# Phase 196: Bulk Invoice Creation - Research

**Researched:** 2026-02-19
**Domain:** WordPress async cron, membership fee invoicing, React progress UI
**Confidence:** HIGH

## Summary

Phase 196 adds bulk membership fee invoice creation and the settings toggle that controls whether billing is done via Nikki or Rondo's own invoicing. The phase builds directly on the infrastructure established in phases 192-195: the `rondo_invoice` CPT with `invoice_type` ACF field (phase 192), the `PublicPaymentPage` token + landing page (phase 193), the `_installment_N_*` flat post meta payment plan schema (phase 194), and the `InstallmentScheduler` daily cron sweeper plus `InstallmentEmailSender` (phase 195).

The primary challenge is avoiding PHP timeouts when creating invoices for 300+ members. The decision from the prior-decisions section is already made: 50 invoices per cron batch. The implementation pattern must therefore be: REST endpoint enqueues a job, WP-Cron processes it in batches, and the React UI polls a progress endpoint to show live status. This is the same pattern used for Google Contacts sync (`CalendarSync`, `GoogleContactsSync`) but applied to a one-shot bulk job rather than a recurring scheduled event.

The second major concern is the billing method toggle (BILL-01 / success criteria 1): when billing method is `rondo`, the Nikki-specific columns in `ContributieList` must be hidden. The `billing_method` is already stored in WordPress Options as `rondo_billing_method_{season}` via `MembershipFees::get_billing_method()` / `set_billing_method()`, but there is no REST endpoint to get or set it, and the frontend currently does not read it. The installment plan enable/disable (BILL-02/BILL-03) is also not yet implemented in any layer — no option key exists yet, no REST arg, no UI.

**Primary recommendation:** Implement a `BulkInvoiceCreator` PHP class with a WP-Cron hook for batched processing, three new REST endpoints (start job, check progress, create single invoice), extend `MembershipFees` / Finance Settings with billing method and installment plan toggles, hide Nikki columns when billing method is `rondo`, and add a "Maak facturen" button plus progress display in `ContributieOverzicht`.

## Standard Stack

### Core (all already in use, no new dependencies)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | — | Store billing method, installment plan toggles, job progress | Established pattern throughout codebase |
| WP-Cron (`wp_schedule_single_event`) | — | Batch invoice creation without HTTP timeout | Used by CalendarSync, GoogleContactsSync, InstallmentScheduler |
| WordPress Transients API | — | Mutex lock (prevent double-firing), job state | Used by InstallmentScheduler for lock |
| `rondo_invoice` CPT | — | Invoice storage | Established in phase 192 |
| ACF `invoice_type` field | — | Distinguishes discipline vs membership invoices | Established in phase 192, allow_null=1 |
| `wp_insert_post` | — | Create invoice posts | Used by `Invoices::create_invoice()` |
| TanStack Query | — | React data fetching + polling | Used throughout; `refetchInterval` for polling |
| `useQuery` with `refetchInterval` | — | Progress polling without page refresh | Standard TanStack pattern |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `PublicPaymentPage::generate_token()` | — | Generate payment token for membership invoices | Already implemented; call it during single-invoice creation |
| `MembershipFees::get_fee_for_person_cached()` | — | Fetch calculated fee (with cache, pro-rata, family discount) | The correct method for bulk; uses cached meta |
| `InvoiceNumbering::generate_next()` | — | Auto-number invoice posts | Already used by `Invoices::create_invoice()` |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP-Cron batch hook | Action Scheduler (WooCommerce) | Not installed; WP-Cron is sufficient for this use case |
| WP-Cron batch hook | PHP background process (wp_remote_post async) | More complex; WP-Cron is the WordPress-native approach |
| Transient for job progress | Custom DB table | Violates Rule 0; transient is appropriate for temporary state |
| REST polling for progress | WebSockets / SSE | Overkill; polling every 2-3 seconds is fine |

**Installation:** No new npm packages or Composer packages needed.

## Architecture Patterns

### Recommended Project Structure

New files:

```
includes/
├── class-bulk-invoice-creator.php     # WP-Cron job processor
src/pages/Contributie/
├── ContributieOverzicht.jsx           # Add "Maak facturen" button + progress
src/hooks/
├── useBulkInvoice.js                  # Mutation + polling hook (new or inline)
```

Modified files:

```
includes/
├── class-membership-fees.php          # Add get/set installment plan toggles
├── class-rest-api.php                 # New routes: bulk start, progress, single-invoice, billing settings
├── class-finance-config.php           # Possibly: add installment_plan_3_enabled / installment_plan_8_enabled
src/pages/Contributie/
├── ContributieList.jsx                # Hide Nikki columns when billing_method = 'rondo'
├── ContributieOverzicht.jsx           # Add bulk creation UI
src/pages/Finance/FinanceSettings.jsx  # Add billing method + installment plan toggles in Finance Settings
functions.php                          # Load BulkInvoiceCreator class
```

### Pattern 1: One-Shot Batched WP-Cron Job

**What:** A REST endpoint stores a job payload in a transient, schedules a single WP-Cron event, and returns immediately. Each cron run processes 50 invoices, then schedules the next batch if work remains.

**When to use:** Any bulk operation >30 seconds on 500+ records.

**Example (adapting from InstallmentScheduler pattern):**

```php
// BulkInvoiceCreator — cron hook
const CRON_HOOK = 'rondo_bulk_invoice_batch';
const JOB_OPTION = 'rondo_bulk_invoice_job';
const BATCH_SIZE = 50;

// REST: POST /rondo/v1/fees/bulk-create-invoices
// Stores job state: { season, person_ids, offset, total, created, skipped, errors }
// Schedules: wp_schedule_single_event( time() + 5, self::CRON_HOOK )

// Cron callback: run_batch()
// Reads job state, processes BATCH_SIZE items, updates counts, reschedules if more remain
// Uses update_option() to write progress so REST polling can read it
```

**Why `wp_schedule_single_event` not `wp_schedule_event`:** This is a one-shot job, not a recurring schedule. Use `wp_schedule_single_event` for each batch, re-scheduling itself until done.

**Source:** Established pattern — `InstallmentScheduler` (recurring), adapted to one-shot chained batches.

### Pattern 2: Job State via WordPress Options

**What:** Job state stored as a serialized array in a WordPress option. Not a transient (transients have TTL issues if a batch takes a long time); use `update_option()` with `autoload=no`.

**Fields:**
```php
[
  'season'    => '2025-2026',
  'status'    => 'running',    // 'idle' | 'running' | 'done' | 'error'
  'total'     => 312,
  'offset'    => 50,
  'created'   => 47,
  'skipped'   => 3,
  'errors'    => 0,
  'started_at' => '2026-02-19 10:00:00',
  'finished_at' => null,
]
```

**Why option not transient:** Transients are garbage-collected and don't survive long-running batches well. Options persist until explicitly deleted.

### Pattern 3: React Polling with TanStack Query

**What:** `useQuery` with `refetchInterval` to auto-poll a progress endpoint every 2-3 seconds while job is running; stop polling when status becomes `done` or `error`.

**Example:**
```js
const { data: jobStatus } = useQuery({
  queryKey: ['bulk-invoice-job'],
  queryFn: () => prmApi.getBulkInvoiceJobStatus(),
  refetchInterval: (data) => {
    if (data?.status === 'running') return 2000;
    return false; // Stop polling
  },
});
```

**Source:** TanStack Query v5 `refetchInterval` accepts a function that receives the current data. This is the correct pattern for conditional polling.

### Pattern 4: Idempotency Check (Success Criteria 5)

**What:** Before creating an invoice for a person, query existing `rondo_invoice` posts with `invoice_type = 'membership'` and matching `person` meta for the same season.

**How:** Query by person_id + invoice_type + season meta key. If an invoice already exists for this person in this season, increment `skipped` counter and continue.

**Season on invoice:** Store season as post meta `_invoice_season` on each membership invoice at creation time. This avoids deriving season from post_date in the future.

**Important:** The existing `create_invoice()` endpoint in `class-rest-invoices.php` creates discipline invoices. The bulk creator must bypass this endpoint and call `wp_insert_post` / `update_field` directly (same as `create_invoice` does internally), setting `invoice_type = 'membership'` and calling `PublicPaymentPage::generate_token()` to set the payment link.

### Pattern 5: Billing Method + Installment Plan Settings

**What:** Extend `MembershipFees` with installment plan enabled flags stored as WordPress options. Extend the membership-fees settings REST endpoint to include these new fields. In the React Finance Settings, add toggles to a new "Contributie" tab (or extend the existing membership fee settings area).

**Option keys to add:**
- `rondo_installment_plan_3_enabled_{season}` — boolean, default true
- `rondo_installment_plan_8_enabled_{season}` — boolean, default true

**Why per-season:** The billing method is already per-season (`rondo_billing_method_{season}`). Installment plan availability should match that granularity.

**PublicPaymentPage impact:** When `plan_3` or `plan_8` is disabled, the `/betaling/{token}` page must not show that option. The `render_page()` method must read these settings and conditionally render the plan buttons.

### Anti-Patterns to Avoid

- **Creating each invoice via HTTP to the REST API:** Makes 300+ HTTP round-trips from within the same process. Call `wp_insert_post` and `update_field` directly in the batch processor.
- **Storing job state in a transient:** TTL expiry can wipe state mid-job. Use `update_option()` with `autoload=no`.
- **Using `wp_schedule_event` (recurring) for a one-shot job:** Schedule single events with `wp_schedule_single_event`, chaining them inside the cron callback.
- **Running bulk invoice creation synchronously in the REST endpoint:** PHP timeout at 30s kills the request. Always async via cron.
- **Forgetting to set `invoice_type = 'membership'`:** Without this, the bulk invoices are indistinguishable from discipline invoices.
- **Forgetting idempotency:** If the "Maak facturen" button is clicked twice, you get duplicate membership invoices per person. Always query for existing membership invoices before creating.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Invoice numbering | Custom counter | `InvoiceNumbering::generate_next()` | Already handles sequential numbering correctly |
| Fee calculation | Re-implement | `MembershipFees::get_fee_for_person_cached()` | Handles pro-rata, family discount, cache correctly |
| Payment token | Custom random ID | `PublicPaymentPage::generate_token()` | Handles 64-char hex, `_payment_token` meta, and `payment_link` ACF field |
| React data fetching | Custom useEffect loop | TanStack Query `refetchInterval` | Handles deduplication, cache, error states |

## Common Pitfalls

### Pitfall 1: WP-Cron Timing on SiteGround

**What goes wrong:** SiteGround may run WP-Cron via the built-in WordPress cron trigger (on page load) OR via a real system cron. If WP-Cron is triggered by page loads, batches only run when someone visits the site.

**Why it happens:** WordPress cron is not a real system daemon by default.

**How to avoid:** After the REST endpoint returns, the frontend should make one additional request to `/?doing_wp_cron` (or hit a dedicated endpoint) to trigger the cron immediately. Alternatively, rely on the system cron if one is configured. Check the current setup: the `InstallmentScheduler` already works, so cron is functional. For the bulk job, simply schedule and let it run — if the admin is active in the UI, subsequent page requests will trigger cron.

**Warning signs:** Progress counter stuck at 0 for more than 60 seconds after starting.

### Pitfall 2: Race Condition on Double-Start

**What goes wrong:** Admin clicks "Maak facturen" twice. Two jobs start simultaneously, creating duplicate invoices.

**Why it happens:** No mutex on the REST start endpoint.

**How to avoid:** The REST endpoint must check if job status is `running` before starting a new job. If running, return 409 Conflict. Use the job option status field for this check.

### Pitfall 3: Forgetting `suppress_filters` in Bulk WP_Query

**What goes wrong:** Access control filters (`class-access-control.php`) are applied to WP_Query, causing person records to be filtered out depending on the requesting user context. In a cron context there is no logged-in user, so all person records may be filtered out.

**Why it happens:** The access control hooks run on `pre_get_posts` and filter by user capabilities.

**How to avoid:** Use `'suppress_filters' => true` in the WP_Query within the cron batch processor. The existing `build_family_groups()` and `recalculate_all_family_positions()` methods in `MembershipFees` already do this correctly.

### Pitfall 4: invoice_type Field Null Check

**What goes wrong:** Existing discipline invoices have `invoice_type = null` (before the WP-CLI backfill), so idempotency queries using `invoice_type = 'membership'` will correctly not match them. But if someone runs bulk creation before running the backfill, they may see odd results.

**Why it happens:** Phase 192 allowed `invoice_type` with `allow_null=1` and `required=0` for backward compatibility.

**How to avoid:** The idempotency query for "does this person already have a membership invoice for this season?" must query on both `invoice_type = 'membership'` AND `_invoice_season = {season}` meta. This is unambiguous regardless of backfill status.

### Pitfall 5: `update_option` autoload Performance

**What goes wrong:** Using `update_option()` without setting `autoload=no` on the job state option means it gets loaded on every WordPress boot, adding overhead.

**How to avoid:** Pass `false` as the 4th argument to `update_option()` to disable autoload. Or use `add_option()` with `false` autoload on first creation.

```php
update_option( 'rondo_bulk_invoice_job', $state, false ); // autoload=no
```

### Pitfall 6: Nikki Column Visibility — Needs Billing Method in Fee API Response

**What goes wrong:** The billing method is stored per-season in WordPress Options, but the `get_fee_list` REST endpoint does not currently return it. The React `ContributieList` needs to know the billing method to conditionally show/hide Nikki columns.

**How to avoid:** Include `billing_method` in the `get_fee_list` and `get_fee_summary` response payloads. The frontend already receives `season` from these endpoints — add `billing_method` alongside it.

## Code Examples

### Starting a Bulk Job (REST handler)

```php
// POST /rondo/v1/fees/bulk-create-invoices
public function start_bulk_invoice_creation( $request ) {
    $fees   = new \Rondo\Fees\MembershipFees();
    $season = $request->get_param( 'season' ) ?? $fees->get_season_key();

    // Check not already running
    $existing = get_option( 'rondo_bulk_invoice_job', [] );
    if ( ! empty( $existing ) && $existing['status'] === 'running' ) {
        return new \WP_Error( 'job_running', 'Bulk aanmaken al bezig.', [ 'status' => 409 ] );
    }

    // Collect eligible person IDs
    $query = new \WP_Query( [
        'post_type'        => 'person',
        'post_status'      => 'publish',
        'posts_per_page'   => -1,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
    ] );
    $person_ids = array_map( 'intval', $query->posts );

    // Initialize job state
    $state = [
        'season'      => $season,
        'status'      => 'running',
        'total'       => count( $person_ids ),
        'offset'      => 0,
        'created'     => 0,
        'skipped'     => 0,
        'errors'      => 0,
        'started_at'  => current_time( 'Y-m-d H:i:s' ),
        'finished_at' => null,
        'person_ids'  => $person_ids,
    ];
    update_option( 'rondo_bulk_invoice_job', $state, false );

    // Schedule first batch
    wp_schedule_single_event( time() + 2, BulkInvoiceCreator::CRON_HOOK );

    return rest_ensure_response( $this->format_job_status( $state ) );
}
```

### Processing a Batch (BulkInvoiceCreator cron callback)

```php
public function run_batch(): void {
    $state = get_option( 'rondo_bulk_invoice_job', [] );
    if ( empty( $state ) || $state['status'] !== 'running' ) {
        return;
    }

    $batch = array_slice( $state['person_ids'], $state['offset'], self::BATCH_SIZE );
    $fees  = new \Rondo\Fees\MembershipFees();

    foreach ( $batch as $person_id ) {
        $result = $this->create_membership_invoice( $person_id, $state['season'] );
        if ( $result === 'created' ) $state['created']++;
        elseif ( $result === 'skipped' ) $state['skipped']++;
        else $state['errors']++;
    }

    $state['offset'] += count( $batch );

    if ( $state['offset'] >= $state['total'] ) {
        $state['status']      = 'done';
        $state['finished_at'] = current_time( 'Y-m-d H:i:s' );
    } else {
        // Schedule next batch
        wp_schedule_single_event( time() + 2, self::CRON_HOOK );
    }

    update_option( 'rondo_bulk_invoice_job', $state, false );
}
```

### Idempotency Check Inside create_membership_invoice

```php
private function create_membership_invoice( int $person_id, string $season ): string {
    // Skip if no calculable fee
    $fees     = new \Rondo\Fees\MembershipFees();
    $fee_data = $fees->get_fee_for_person_cached( $person_id, $season );
    if ( $fee_data === null ) {
        return 'skipped'; // Not calculable
    }

    // Skip former members not eligible for this season
    $is_former = (bool) get_post_meta( $person_id, 'former_member', true );
    if ( $is_former && ! $fees->is_former_member_in_season( $person_id, $season ) ) {
        return 'skipped';
    }

    // Idempotency: skip if membership invoice for this season already exists
    $existing = get_posts( [
        'post_type'        => 'rondo_invoice',
        'post_status'      => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
        'posts_per_page'   => 1,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
        'meta_query'       => [
            [ 'key' => 'person', 'value' => $person_id ],
            [ 'key' => '_invoice_season', 'value' => $season ],
            [ 'key' => 'invoice_type', 'value' => 'membership' ],
        ],
    ] );
    if ( ! empty( $existing ) ) {
        return 'skipped'; // Already has membership invoice for this season
    }

    // Create invoice post
    $invoice_number = \Rondo\Finance\InvoiceNumbering::generate_next();
    $post_id = wp_insert_post( [
        'post_type'   => 'rondo_invoice',
        'post_title'  => $invoice_number,
        'post_status' => 'rondo_draft',
        'post_author' => 0, // System-created
    ] );
    if ( is_wp_error( $post_id ) ) {
        return 'error';
    }

    // Set ACF fields
    update_field( 'invoice_number', $invoice_number, $post_id );
    update_field( 'person', $person_id, $post_id );
    update_field( 'status', 'draft', $post_id );
    update_field( 'invoice_type', 'membership', $post_id );
    update_field( 'total_amount', $fee_data['final_fee'], $post_id );
    update_field( 'line_items', [ [
        'discipline_case' => null,
        'description'     => 'Contributie ' . $season,
        'amount'          => $fee_data['final_fee'],
    ] ], $post_id );

    // Store season for idempotency lookup
    update_post_meta( $post_id, '_invoice_season', $season );

    // Generate payment token (payment_link ACF field set by generate_token)
    \Rondo\Finance\PublicPaymentPage::generate_token( $post_id );

    return 'created';
}
```

### React Progress Polling

```js
// In ContributieOverzicht.jsx
const { data: jobStatus } = useQuery({
  queryKey: ['bulk-invoice-job'],
  queryFn: () => prmApi.getBulkInvoiceJobStatus(),
  refetchInterval: (data) => {
    return data?.status === 'running' ? 2000 : false;
  },
  enabled: isJobActive,
});
```

### Billing Method in Fee List Response

```php
// In get_fee_list() REST callback:
return rest_ensure_response( [
  'season'         => $season,
  'billing_method' => $fees->get_billing_method( $season ),
  // ...existing fields
] );
```

### ContributieList — Conditional Nikki Columns

```jsx
// ContributieList.jsx — read billing_method from fee data
const billingMethod = data?.billing_method ?? 'nikki';
const showNikkiColumns = billingMethod === 'nikki' && !isForecast;

// Replace existing !isForecast with showNikkiColumns for the Nikki column conditionals
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Sync bulk operations in REST handler | Async WP-Cron + polling | Phase 196 (new) | Avoids timeout on 500+ members |
| Nikki columns always visible | Hidden when billing_method=rondo | Phase 196 (new) | Cleaner UI when not using Nikki |

## Open Questions

1. **Where do billing method + installment plan settings live in the Finance Settings UI?**
   - What we know: Finance Settings has 5 tabs: Organisation, Payment, Email, Rabobank, Mollie. Billing method and installment plan toggles are membership-fee-specific settings, not finance configuration.
   - What's unclear: Should they go in the Contributie page's existing "Instellingen" tab (inside `FeeCategorySettings.jsx`) or in Finance Settings under a new "Contributie" section?
   - Recommendation: Add them to the existing Contributie "Instellingen" tab as a new section at the top (billing method per season, then installment plan toggles). This is where fee categories are already managed. Keep Finance Settings for invoice/payment provider config. The billing method is a per-season decision (like fee categories), so the Contributie settings tab is the right home.

2. **Should the bulk job include non-former members only, or also former members eligible for the season?**
   - What we know: `MembershipFees::get_fee_for_person_cached()` calls `calculate_full_fee()` which calls `calculate_fee()` — this does not filter out former members. The former-member check is done separately via `is_former_member_in_season()`.
   - What's unclear: The requirements say "eligible members." Former members who participated in the season should get an invoice.
   - Recommendation: Apply the same former-member inclusion logic as `build_family_groups()` uses (skip former members who are NOT in season). Include former members who ARE in season.

3. **Single-member invoice creation from person record (INV-01) — where in the UI?**
   - What we know: `FinancesCard.jsx` shows the fee data on the person page. The `Invoices::create_invoice()` endpoint exists.
   - What's unclear: Should the "create single membership invoice" button be in `FinancesCard.jsx`, in the `Facturen` page filtered by person, or both?
   - Recommendation: Add a "Maak factuur" button to `FinancesCard.jsx` when billing method is `rondo` and no membership invoice yet exists for this season. Clicking it calls the new single-invoice REST endpoint for this specific person. This mirrors the discipline invoice creation flow.

4. **Job state persistence — what happens if the cron fires but WordPress crashes mid-batch?**
   - What we know: The job state option is updated at the end of each batch. Invoices created before the crash are permanent.
   - What's unclear: On recovery, will the idempotency check correctly skip already-created invoices?
   - Recommendation: Yes — the idempotency query checks for existing membership invoices per person per season. Re-running a batch after a crash will skip already-created invoices. The offset stored in the job option will be stale (pointing to a batch that partially completed), but the idempotency check in `create_membership_invoice()` makes each item safe to retry.

## Sources

### Primary (HIGH confidence)

- Codebase: `includes/class-installment-scheduler.php` — WP-Cron hook registration, transient lock, batch processing pattern
- Codebase: `includes/class-membership-fees.php` — fee calculation, billing method storage, former member handling, `suppress_filters` pattern
- Codebase: `includes/class-rest-invoices.php` — invoice creation flow (wp_insert_post + update_field pattern)
- Codebase: `includes/class-public-payment-page.php` — `generate_token()` static method already implemented and ready to call
- Codebase: `includes/class-finance-config.php` — options pattern, installment meta schema documented at top of file
- Codebase: `src/pages/Contributie/ContributieList.jsx` — Nikki column conditional rendering (currently on `!isForecast`, needs `billingMethod`)
- Codebase: `src/pages/Contributie/ContributieOverzicht.jsx` — current overview UI structure; location for "Maak facturen" button
- Codebase: `includes/class-rest-api.php` lines 530-690 — existing membership-fees REST routes
- WordPress Docs (training knowledge, HIGH): `wp_schedule_single_event`, `update_option` with autoload=false, `WP_Query suppress_filters`

### Secondary (MEDIUM confidence)

- TanStack Query `refetchInterval` function signature — training knowledge; the `refetchInterval: (data) => number | false` pattern is standard TanStack Query v5 behavior.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all dependencies already in codebase, verified by reading source
- Architecture: HIGH — WP-Cron batch pattern verified against InstallmentScheduler and CalendarSync
- Pitfalls: HIGH — all verified against actual code (suppress_filters, autoload, idempotency, billing_method not in fee response)
- React patterns: HIGH — TanStack Query refetchInterval is standard usage

**Research date:** 2026-02-19
**Valid until:** 2026-03-21 (30 days — stable internal codebase)
