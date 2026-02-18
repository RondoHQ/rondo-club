# Phase 194: Payment Plan Manager + Webhook Extension - Research

**Researched:** 2026-02-18
**Domain:** WordPress post meta, Mollie webhook handling, PHP payment state machines
**Confidence:** HIGH

## Summary

Phase 194 extends the existing Mollie webhook handler (`MollieWebhook`) to correctly route
payment confirmations to individual installments instead of always marking the entire invoice
as paid. The phase also adds the logic that transitions an invoice to `rondo_paid` only once
ALL installments in a plan have been confirmed by Mollie.

The code infrastructure established in Phase 192-193 is already largely in place in
`class-public-payment-page.php`. That class already:

- Writes flat-numbered installment meta (`_installment_N_amount`, `_installment_N_admin_fee`,
  `_installment_N_status`) for each installment when a plan is selected.
- Creates Mollie payments for the first installment and stores the reverse-lookup meta
  `_mollie_pid_{payment_id} = installment_number` on the invoice.
- Stores `_installment_N_mollie_payment_id` and `_installment_N_payment_link` for installment 1.

What is **NOT yet implemented**:

1. The webhook handler (`MollieWebhook::handle_webhook`) still uses the old
   `_mollie_payment_id` lookup that only works for full-payment invoices created by the
   old `MolliePayment::create_payment_link()` path. It has no awareness of installment meta
   or the reverse-lookup pattern.
2. There is no mechanism to create Mollie payments for installments 2-N; the member
   pays termijn 1 at plan-selection time, but subsequent installments have no payment
   links yet.
3. There is no "all installments paid?" check to gate the invoice transition to `rondo_paid`.
4. The public landing page (`PublicPaymentPage`) still renders "Betaling geregistreerd" on
   any `?betaald=1` redirect regardless of whether the payment has actually been confirmed
   (Mollie's redirect fires before webhook confirmation).

**Primary recommendation:** Extend `MollieWebhook::handle_webhook` with a dual-path lookup
(reverse-lookup meta first, then legacy `_mollie_payment_id` fallback), update installment
status, and gate the invoice `rondo_paid` transition on all installments being complete.
The subsequent-installment payment creation logic (for termijnen 2-N) can be a thin service
method called when an installment is marked paid.

## Standard Stack

### Core (no new libraries needed)

| Component | Existing | Purpose |
|-----------|----------|---------|
| `MollieWebhook` | `includes/class-mollie-webhook.php` | Receives Mollie POSTs, transitions invoice status |
| `PublicPaymentPage` | `includes/class-public-payment-page.php` | Token-gated payment page; creates Mollie payments for installment 1 |
| `MollieClient` | `includes/class-mollie-client.php` | Thin wrapper over Mollie PHP SDK |
| `MolliePayment` | `includes/class-mollie-payment.php` | Legacy full-payment service |
| `FinanceConfig` | (existing) | Reads API key, admin fee, redirect URL |
| WordPress post meta | — | All installment state lives here |

No new Composer packages are required. The Mollie PHP SDK (`mollie/mollie-api-client`) is
already installed and the same `MollieClient` factory works for this phase.

### Meta key schema (established Phase 192-193)

```
_installment_plan              → 'full' | 'quarterly_3' | 'monthly_8'
_installment_count             → integer (1, 3, or 8)
_installment_N_amount          → float  (base amount without admin fee)
_installment_N_admin_fee       → float
_installment_N_status          → 'pending' | 'betaald'
_installment_N_mollie_payment_id → string (Mollie payment ID, set when payment created)
_installment_N_payment_link    → string (checkout URL)
_mollie_pid_{payment_id}       → int    (installment number, reverse-lookup)
```

**Important nuance found in existing code:** `_installment_N_amount` stores the BASE
amount (without admin fee). The actual charge to Mollie is `base + admin_fee`. This is
critical for subsequent-installment payment creation: read both meta keys, not just amount.

## Architecture Patterns

### Pattern 1: Dual-Path Webhook Lookup

The webhook handler must support two types of invoices simultaneously:

1. **Installment invoices** — use reverse-lookup meta `_mollie_pid_{payment_id}` to get
   the installment number; then update `_installment_N_status` to `betaald`; then check
   if all installments are paid before marking invoice `rondo_paid`.

2. **Full-payment invoices (legacy)** — use `_mollie_payment_id` on the invoice directly;
   mark invoice `rondo_paid` immediately (existing behavior preserved).

Determining which path to take: query for `_mollie_pid_{payment_id}` first. If a post
with that meta exists, it is an installment payment. If not, fall back to the legacy
`_mollie_payment_id` query.

**However:** both paths use `WP_Query` which is expensive. A better approach since we
already know the reverse-lookup pattern stores the installment number AS THE META VALUE
on the INVOICE post: query for posts where meta_key = `_mollie_pid_{payment_id}` — this
returns the invoice directly without needing to know the invoice ID first. O(1) because
WordPress post meta is indexed.

```php
// Reverse-lookup: find invoice and installment number in one query
$posts = get_posts([
    'post_type'      => 'rondo_invoice',
    'post_status'    => 'any',
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'meta_query'     => [
        ['key' => '_mollie_pid_' . $payment_id, 'compare' => 'EXISTS'],
    ],
]);

if ( ! empty( $posts ) ) {
    $invoice_id        = $posts[0];
    $installment_n     = (int) get_post_meta( $invoice_id, '_mollie_pid_' . $payment_id, true );
    // → installment path
} else {
    // → legacy full-payment path (existing code)
}
```

### Pattern 2: "All Paid?" Check for Invoice Completion

After marking an installment as `betaald`, count how many installments are still `pending`:

```php
$count  = (int) get_post_meta( $invoice_id, '_installment_count', true );
$all_paid = true;
for ( $n = 1; $n <= $count; $n++ ) {
    $status = get_post_meta( $invoice_id, '_installment_' . $n . '_status', true );
    if ( 'betaald' !== $status ) {
        $all_paid = false;
        break;
    }
}
if ( $all_paid ) {
    wp_update_post(['ID' => $invoice_id, 'post_status' => 'rondo_paid']);
    update_field('status', 'paid', $invoice_id);
}
```

This loop runs at most 8 times (monthly_8 plan), so performance is irrelevant.

### Pattern 3: Creating Subsequent Installment Payments

When installment N is confirmed paid, create the Mollie payment for installment N+1
immediately (so the member has a link ready for the next payment).

**Where does the redirect URL for N+1 point?** The member used the `/betaling/{token}` URL
for installment 1. For subsequent installments, the webhook has no browser context, so
we cannot redirect. We should:

- Create the Mollie payment for N+1 via the API (stores payment ID + link in meta)
- Store `_installment_{N+1}_mollie_payment_id` and `_installment_{N+1}_payment_link` and
  the reverse-lookup meta `_mollie_pid_{payment_id}` for N+1
- The existing `PublicPaymentPage` already has `create_installment_payment()` as a private
  method — this logic should be extracted to a callable static or service method OR
  the webhook can replicate the 6-line Mollie call directly

**Token for redirect URL:** The webhook needs the invoice's `_payment_token` to build the
redirect URL for subsequent payment creation (the Mollie payment needs a `redirectUrl`).
Read `get_post_meta($invoice_id, PublicPaymentPage::TOKEN_META_KEY, true)`.

### Pattern 4: Idempotency for the Installment Path

The webhook can fire multiple times for the same payment (Mollie retries). Before
updating installment status, check: is `_installment_N_status` already `betaald`? If yes,
skip all state mutations (but still return HTTP 200).

```php
$current_status = get_post_meta( $invoice_id, '_installment_' . $n . '_status', true );
if ( 'betaald' === $current_status ) {
    return rest_ensure_response( [ 'ok' => true ] ); // idempotent no-op
}
```

### Anti-Patterns to Avoid

- **Trusting the POST body:** The webhook already re-fetches from Mollie API — keep this pattern.
- **Marking invoice paid on first installment:** The current webhook does `wp_update_post` to
  `rondo_paid` immediately — this MUST be guarded by the all-paid check for installment invoices.
- **Using ACF `get_field` for installment meta:** Installment meta is stored as raw WordPress
  post meta (not ACF fields). Use `get_post_meta` / `update_post_meta` consistently.
  Only `status` (the overall invoice status) uses `update_field`.
- **Creating N+1 payment inside WP_Query loop:** Never. Create it after the query ends.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Mollie API calls | Custom HTTP client | Existing `MollieClient` wrapper |
| Invoice status transitions | Custom meta-only state | `wp_update_post` + `update_field('status')` combo (matches existing pattern) |

## Common Pitfalls

### Pitfall 1: Legacy Invoice Path Breakage

**What goes wrong:** If the reverse-lookup meta query finds nothing, the code falls into the
installment path by mistake, or vice versa — breaking existing full-payment invoices.

**Why it happens:** Full-payment invoices created before Phase 192-193 use `_mollie_payment_id`
on the invoice. They do NOT have `_mollie_pid_{payment_id}` meta. Installment invoices have
both.

**How to avoid:** Query for `_mollie_pid_{payment_id}` first. Only if that returns nothing,
fall back to `_mollie_payment_id` legacy query. The legacy query is unchanged from current code.

**Warning sign:** Integration test — create a full-payment invoice via old path, trigger
webhook; verify it still reaches `rondo_paid`.

### Pitfall 2: Missing Installment Count Meta

**What goes wrong:** If `_installment_count` is empty (e.g., 'full' plan), the all-paid loop
runs 0 times and `$all_paid` stays `true`, wrongly triggering a `rondo_paid` transition.

**Why it happens:** For `full` plan, `_installment_count` is set to `1` in
`handle_plan_selection()`. But if the meta read returns `''` or `0`, the loop doesn't run.

**How to avoid:** Guard with `if ($count < 1) { $count = 1; }` or cast carefully.
Actually looking at the code: for `full` plan, only `_installment_count` = 1 is set;
`_installment_1_status` is NOT set. So the loop would find no `betaald` status and wrongly
conclude not all paid. Need to handle `full` plan differently:

- Option A: For `full` plan invoices that arrive via legacy `_mollie_payment_id` path,
  use existing behavior (mark paid immediately).
- Option B: For `full` plan installment path — the code sets `_installment_count = 1` but
  does NOT call `write_installment_meta()` for `full`, so `_installment_1_status` is never
  written. The webhook receiving termijn 1 needs to mark invoice paid immediately for `full`.

**Recommended approach:** Only use the reverse-lookup/all-paid path for multi-installment
plans. Check: `if ( $count > 1 ) { /* check all */ } else { /* mark paid immediately */ }`.

### Pitfall 3: Mollie Payment for N+1 Failing Mid-Webhook

**What goes wrong:** Webhook marks installment N as paid, then the API call to create N+1
payment throws `ApiException`. Invoice is in an inconsistent state (N paid, N+1 has no link).

**Why it happens:** Network errors, Mollie API downtime.

**How to avoid:** Wrap the N+1 payment creation in a try/catch. If it fails, log the error
but do NOT rollback the N paid status. The webhook always returns HTTP 200. The member
can retry by visiting the `/betaling/{token}` page, which could detect the missing N+1 link
and create it lazily (or this can be handled in a future phase). Log loudly so admins see it.

**Warning sign:** Check that the `create_installment_payment` call in the webhook is
wrapped in try/catch and does not propagate exceptions.

### Pitfall 4: `_installment_1_status` Not Set for 'full' Plan

**What goes wrong:** For `full` plan, `write_installment_meta()` is NOT called in
`handle_plan_selection()`. So `_installment_1_status` is never written as `pending`.
The webhook reverse-lookup will find `_mollie_pid_{payment_id} = 1` and try to mark
`_installment_1_status = betaald`, but then the all-paid check reads `_installment_count = 1`
and loops — finding the just-written `betaald` and triggering invoice completion. This
actually WORKS, but only if the webhook writes the status before checking completeness.
Order of operations matters.

**How to avoid:** Ensure the code sequence is: (1) update installment status, (2) check all paid.

### Pitfall 5: Idempotency for Subsequent Payment Creation

**What goes wrong:** Webhook fires twice for the same paid installment; N+1 Mollie payment
is created twice; two orphaned payments in Mollie.

**How to avoid:** Before creating N+1 payment, check if `_installment_{N+1}_mollie_payment_id`
already exists. If it does, skip creation.

### Pitfall 6: `get_post_meta` vs `get_field` Confusion

**What goes wrong:** Installment meta is raw WP post meta, not ACF-registered fields.
Calling `get_field('_installment_1_status', $invoice_id)` would return `false` (not found
in ACF field group).

**How to avoid:** Always use `get_post_meta` / `update_post_meta` for `_installment_N_*`
keys. Only `status` (the high-level invoice status string) uses `update_field`.

## Code Examples

### Revised `handle_webhook` Structure

```php
public function handle_webhook( \WP_REST_Request $request ) {
    $payment_id = sanitize_text_field( $request->get_param( 'id' ) );

    if ( empty( $payment_id ) ) {
        error_log( 'Mollie webhook: missing payment ID' );
        return rest_ensure_response( [ 'ok' => true ] );
    }

    // Re-fetch from Mollie API.
    try {
        $mollie_client = new MollieClient();
        $payment = $mollie_client->get()->payments->get( $payment_id );
    } catch ( \Mollie\Api\Exceptions\ApiException $e ) {
        error_log( 'Mollie webhook: API exception: ' . $e->getMessage() );
        return rest_ensure_response( [ 'ok' => true ] );
    }

    if ( ! $payment->isPaid() ) {
        return rest_ensure_response( [ 'ok' => true ] );
    }

    // --- Path 1: installment reverse-lookup ---
    $installment_posts = get_posts([
        'post_type'      => 'rondo_invoice',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [['key' => '_mollie_pid_' . $payment_id, 'compare' => 'EXISTS']],
    ]);

    if ( ! empty( $installment_posts ) ) {
        $invoice_id = (int) $installment_posts[0];
        $n          = (int) get_post_meta( $invoice_id, '_mollie_pid_' . $payment_id, true );
        return $this->handle_installment_paid( $invoice_id, $n, $payment_id, $payment );
    }

    // --- Path 2: legacy full-payment lookup ---
    // [existing WP_Query + status transition code, unchanged]
}

private function handle_installment_paid( int $invoice_id, int $n, string $payment_id, $payment ) {
    // Idempotency check.
    if ( 'betaald' === get_post_meta( $invoice_id, '_installment_' . $n . '_status', true ) ) {
        return rest_ensure_response( [ 'ok' => true ] );
    }

    // Mark installment paid.
    update_post_meta( $invoice_id, '_installment_' . $n . '_status', 'betaald' );

    // Check if all installments paid.
    $count    = max( 1, (int) get_post_meta( $invoice_id, '_installment_count', true ) );
    $all_paid = true;
    for ( $i = 1; $i <= $count; $i++ ) {
        if ( 'betaald' !== get_post_meta( $invoice_id, '_installment_' . $i . '_status', true ) ) {
            $all_paid = false;
            break;
        }
    }

    if ( $all_paid ) {
        wp_update_post([ 'ID' => $invoice_id, 'post_status' => 'rondo_paid' ]);
        update_field( 'status', 'paid', $invoice_id );
    } else {
        // Create payment for next installment.
        $next = $n + 1;
        $existing_next_pid = get_post_meta( $invoice_id, '_installment_' . $next . '_mollie_payment_id', true );
        if ( empty( $existing_next_pid ) ) {
            try {
                $this->create_next_installment_payment( $invoice_id, $next );
            } catch ( \Exception $e ) {
                error_log( 'Mollie webhook: failed to create next installment payment: ' . $e->getMessage() );
                // Non-fatal — return 200 regardless.
            }
        }
    }

    return rest_ensure_response( [ 'ok' => true ] );
}
```

### Next Installment Payment Creation in Webhook

The `MollieWebhook` class needs a method to create the next installment payment. This
replicates the logic in `PublicPaymentPage::create_installment_payment()`. Options:

**Option A (preferred — DRY):** Move `create_installment_payment` from `PublicPaymentPage`
to a shared service class (e.g., `InstallmentPaymentService`) that both `PublicPaymentPage`
and `MollieWebhook` call. The method needs `$invoice_id`, `$amount`, `$installment_number`,
`$token`.

**Option B:** Duplicate the method into `MollieWebhook`. Simpler but violates DRY.

Given the project's DRY rule (Rule 3), Option A is required.

```php
// InstallmentPaymentService::create_payment( int $invoice_id, int $n ): string|\WP_Error
// Reads _installment_N_amount, _installment_N_admin_fee, _payment_token from meta.
// Returns checkout URL or WP_Error.
```

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|-----------------|--------|
| Webhook marks invoice paid on `_mollie_payment_id` match | Phase 194: dual-path — reverse-lookup for installments, legacy for full-payment | Backward-compatible |
| No installment tracking | Phase 192: flat `_installment_N_*` meta | Schema already exists |
| No reverse-lookup | Phase 193: `_mollie_pid_{id}` meta written at payment creation | Already in place |

## Open Questions

1. **Where does `create_installment_payment` live after DRY extraction?**
   - What we know: It currently lives in `PublicPaymentPage` as a private method.
   - What's unclear: Should it be a new class, a static method on an existing class, or
     promoted to public on `PublicPaymentPage`?
   - Recommendation: Create a new `InstallmentPaymentService` class in
     `includes/class-installment-payment-service.php` with a single public method
     `create_payment( int $invoice_id, int $n ): string|\WP_Error`. Load it in
     `functions.php` alongside the other Finance classes.

2. **What happens to the public landing page after a member pays installment N?**
   - What we know: The page currently always shows the plan selection (all three options).
     After plan selection, there is no UI for the member to see remaining installments.
   - What's unclear: Phase 194 requirements do not mention updating the landing page UI.
     The success criterion only covers webhook behavior.
   - Recommendation: Do NOT change the landing page UI in this phase. Out of scope per
     the success criteria. The page can show a "betaling ontvangen" state for any `?betaald=1`
     regardless.

3. **Should `_installment_1_status` be written for `full` plans?**
   - What we know: `write_installment_meta()` is NOT called for `full` plan in Phase 193.
     So `_installment_1_status` does not exist for full-payment invoices.
   - What's unclear: The webhook will find `_mollie_pid_{id} = 1` for full-plan invoices
     (since `create_installment_payment` is called for installment 1 regardless of plan).
     The all-paid check would then need `_installment_1_status = betaald` to be set.
   - Recommendation: In `handle_installment_paid`, always write `_installment_N_status =
     betaald` before the all-paid check, regardless of whether it was previously set to
     `pending`. This is safe and idempotent. For full-plan invoices with count=1, the loop
     finds `_installment_1_status = betaald` (just written) and marks all paid.

## Sources

### Primary (HIGH confidence)

- Direct code inspection of `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-webhook.php`
- Direct code inspection of `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-public-payment-page.php`
- Direct code inspection of `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-payment.php`
- Direct code inspection of `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-client.php`
- Phase description and prior decisions in the task briefing

### Secondary (MEDIUM confidence)

- WordPress post meta behavior and WP_Query `meta_query` with `EXISTS` compare — standard
  WordPress patterns, HIGH confidence from prior usage in this same codebase.
- Mollie PHP SDK `payments->get()` and `isPaid()` — verified in existing webhook code.

## Metadata

**Confidence breakdown:**
- Current code state: HIGH — read directly from source files
- Architecture pattern: HIGH — based on existing code patterns in this codebase
- Pitfalls: HIGH — derived from careful reading of existing implementation
- DRY extraction need: HIGH — Rule 3 in CLAUDE.md is explicit

**Research date:** 2026-02-18
**Valid until:** Until code changes in class-public-payment-page.php or class-mollie-webhook.php
