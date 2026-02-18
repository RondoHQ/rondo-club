# Phase 193: Public Payment Landing Page - Research

**Researched:** 2026-02-18
**Domain:** WordPress public page (unauthenticated), PHP-rendered HTML, Mollie payment creation, token security
**Confidence:** HIGH

---

## Summary

Phase 193 is a server-side PHP feature: a public, token-secured HTML page at `/betaling/{token}` that lets members view their invoice and choose a payment plan without logging in. The prior decisions locked all the major architectural choices — PHP-rendered (not React), `template_redirect` at priority 0, and no WP nonce. The research validates those decisions are straightforward to implement against the existing codebase patterns.

The page must do three things: (1) resolve the token to an invoice, (2) render invoice details and payment plan options as a standalone HTML page, and (3) handle a plan selection form POST that creates a Mollie payment and redirects the member. All three have clear precedents in the codebase — token-based public pages from iCal/CardDAV, PHP-rendered HTML from `InvoiceEmailSender`, and Mollie payment creation from `MolliePayment`.

The key complexity is the plan selection state machine: a form POST must write installment meta to the invoice *and* create the right Mollie payment (full amount vs first installment amount) *and* redirect — all in a single synchronous request. This is safe because Mollie payment creation is fast (< 1s) and the page is only invoked once per member action.

**Primary recommendation:** One new class `Rondo\Finance\PublicPaymentPage` wired via `template_redirect` priority 0, handling both GET (render) and POST (plan selection + Mollie redirect). Token stored as post meta `_payment_token` on the invoice.

---

## Standard Stack

### Core (all already in the project)

| Component | Version/Source | Purpose | Already Exists |
|-----------|---------------|---------|----------------|
| `Rondo\Finance\MolliePayment` | existing class | Create Mollie payment link | YES |
| `Rondo\Config\FinanceConfig` | existing class | Read installment admin fee, enabled plans | YES |
| `Rondo\Fees\MembershipFees` | existing class | Read billing method per season | YES |
| WordPress `template_redirect` | core hook | Intercept request before SPA catch-all | YES |
| WordPress `add_rewrite_rule` | core | Register `/betaling/{token}` URL | YES |
| WordPress post meta | core | Store `_payment_token` on invoice | YES |

### Supporting

| Component | Purpose | When to Use |
|-----------|---------|-------------|
| `bin2hex(random_bytes(32))` | Token generation | Same pattern as iCal (`ICalFeed::generate_token`) |
| `get_posts` with meta_query | Token lookup | Same as `ICalFeed::get_user_by_token` |
| `number_format($n, 2, ',', '.')` | Dutch currency formatting | Same as `InvoiceEmailSender` |
| `wp_safe_redirect` | Redirect to Mollie | Safer than `header('Location:')` |

---

## Architecture Patterns

### Recommended Structure

One new file:
```
includes/class-public-payment-page.php   # PublicPaymentPage class
```

No new template files needed. The page renders itself from a method (same approach as iCal feed and CardDAV server — they output directly and `exit`).

Token generation and storage happens in `InvoiceEmailSender::send()` or a new `generate_payment_token($invoice_id)` helper called when a membership invoice is sent.

### Pattern 1: Token-Secured Public Page via template_redirect

This is the same pattern as `ICalFeed` and `CardDAVServer`:

```php
// In constructor:
add_action( 'init', [ $this, 'register_rewrite_rules' ] );
add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
// Priority 0 fires BEFORE the SPA catch-all at priority 1 in functions.php

public function register_rewrite_rules() {
    add_rewrite_rule(
        '^betaling/([a-f0-9]+)$',
        'index.php?rondo_payment_token=$matches[1]',
        'top'
    );
}

public function add_query_vars( $vars ) {
    $vars[] = 'rondo_payment_token';
    return $vars;
}

public function handle_request() {
    $token = get_query_var( 'rondo_payment_token' );
    if ( empty( $token ) ) {
        return; // Not our request
    }
    // Handle GET or POST and exit
    $this->render_or_redirect( $token );
    exit;
}
```

**Why priority 0:** The SPA catch-all in `functions.php` runs at `template_redirect` priority 1. Our public page at priority 0 fires first, handles the request, and exits — so the SPA never sees it. This matches the stated prior decision.

**Source:** Verified against `functions.php` line 829: `add_action('template_redirect', 'rondo_theme_template_redirect', 1)`. CardDAV uses priority 0 confirmed at `class-carddav-server.php` line 30.

### Pattern 2: Token Storage on Invoice Post Meta

```php
const TOKEN_META_KEY = '_payment_token';
const TOKEN_LENGTH   = 32; // bytes → 64 hex chars

public static function generate_token( int $invoice_id ): string {
    $token = bin2hex( random_bytes( self::TOKEN_LENGTH ) );
    update_post_meta( $invoice_id, self::TOKEN_META_KEY, $token );
    return $token;
}

public static function get_invoice_by_token( string $token ): ?int {
    $posts = get_posts([
        'post_type'      => 'rondo_invoice',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [[
            'key'   => '_payment_token',
            'value' => $token,
        ]],
    ]);
    return $posts[0] ?? null;
}
```

**Lookup cost:** `get_posts` with meta_query on `_payment_token` is O(1) with MySQL index on `meta_key`. Same approach used by `ICalFeed::get_user_by_token` (user meta) and implicitly by `MollieWebhook` (post meta `_mollie_payment_id`).

**Security:** 32 bytes = 256 bits of entropy. Collision-resistant. Token is stored in post meta (server-side), never in URL params beyond the public URL itself. Acceptable for member-facing payment links (not a session credential).

**Token lifetime:** Token is permanent until the invoice is deleted or paid. No expiry needed for MVP (Phase 193). Expiry is a Phase 195+ concern if at all.

### Pattern 3: Plan Selection POST Handler

The page handles two HTTP methods:

**GET:** Render the invoice details + plan selection UI.

**POST:** Receive the selected plan (`full`, `quarterly_3`, `monthly_8`), write installment meta to the invoice, create a Mollie payment for the correct amount (first installment if multi-plan, full amount if single), and redirect the member to Mollie's checkout URL.

```php
public function handle_request() {
    $token = get_query_var( 'rondo_payment_token' );
    if ( empty( $token ) ) return;

    $invoice_id = self::get_invoice_by_token( $token );

    if ( ! $invoice_id ) {
        $this->render_error( 'Betaallink niet gevonden of verlopen.' );
        exit;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $this->handle_plan_selection( $invoice_id );
    } else {
        $this->render_page( $invoice_id );
    }
    exit;
}

private function handle_plan_selection( int $invoice_id ) {
    $plan = sanitize_key( $_POST['plan'] ?? '' );
    $allowed = [ 'full', 'quarterly_3', 'monthly_8' ];

    if ( ! in_array( $plan, $allowed, true ) ) {
        $this->render_error( 'Ongeldige keuze.' );
        return;
    }

    // 1. Store plan on invoice
    update_post_meta( $invoice_id, '_installment_plan', $plan );

    // 2. Calculate payment amount for first installment
    $total    = (float) get_field( 'total_amount', $invoice_id );
    $config   = new FinanceConfig();
    $admin_fee = $config->get_installment_admin_fee();

    if ( $plan === 'full' ) {
        $first_amount = $total;
        update_post_meta( $invoice_id, '_installment_count', 1 );
    } elseif ( $plan === 'quarterly_3' ) {
        update_post_meta( $invoice_id, '_installment_count', 3 );
        $first_amount = round( $total / 3, 2 ) + $admin_fee;
        // Store installment meta for installments 1-3
        $this->write_installment_meta( $invoice_id, 3, $total, $admin_fee );
    } elseif ( $plan === 'monthly_8' ) {
        update_post_meta( $invoice_id, '_installment_count', 8 );
        $first_amount = round( $total / 8, 2 ) + $admin_fee;
        $this->write_installment_meta( $invoice_id, 8, $total, $admin_fee );
    }

    // 3. Create Mollie payment for first installment amount
    // NOTE: Can't reuse MolliePayment::create_payment_link() — that
    // reads total_amount ACF field. Need direct Mollie call here
    // or a new method that accepts an amount parameter.
    $checkout_url = $this->create_mollie_payment( $invoice_id, $first_amount, 1 );

    if ( is_wp_error( $checkout_url ) ) {
        $this->render_error( 'Betaling aanmaken mislukt. Probeer opnieuw.' );
        return;
    }

    // 4. Redirect to Mollie
    wp_safe_redirect( $checkout_url );
    exit;
}
```

**Important constraint:** `MolliePayment::create_payment_link()` reads `get_field('total_amount')` and is idempotent (checks `_mollie_payment_id`). It is NOT suitable for installment payments because the amount must be the installment amount (not the total), and we need to store the payment ID against the installment, not the invoice root. Phase 193 must create Mollie payments directly via `MollieClient` or via a new method on `MolliePayment` that accepts an explicit amount and a installment number.

### Pattern 4: PHP-Rendered Page (No React, No WP nonce)

The page outputs a complete, standalone HTML document with inline CSS. Same pattern used by `ICalFeed` (outputs iCal text) and the PDF generator (outputs binary). No `wp_head()` or SPA assets needed.

```php
private function render_page( int $invoice_id ) {
    // Read invoice data
    $invoice_number = get_field( 'invoice_number', $invoice_id );
    $total_amount   = (float) get_field( 'total_amount', $invoice_id );
    $person_id      = get_field( 'person', $invoice_id );
    // ... build name, season ...

    $config    = new FinanceConfig();
    $admin_fee = $config->get_installment_admin_fee();

    // Read enabled plans from config (Phase 196 adds the toggles)
    // For Phase 193, show all three plans always (toggles come later)

    status_header( 200 );
    header( 'Content-Type: text/html; charset=UTF-8' );

    // Output standalone HTML page with inline Tailwind-free CSS
    echo '<!DOCTYPE html><html lang="nl">...';
}
```

**No WordPress login redirect:** Because `template_redirect` fires and `exit`s before WordPress's auth checks for the SPA, and because no `is_user_logged_in()` check or capability check is applied, the page is fully public. Confirmed: WordPress does not automatically redirect unauthenticated requests to wp-login.php for custom page handlers — that only applies to password-protected posts.

**Mobile-first HTML:** Use a `<meta name="viewport" content="width=device-width, initial-scale=1">` in the output. Use simple block-level buttons (no hover-only interactions). Use `font-size: 16px` on inputs to prevent iOS auto-zoom.

### Pattern 5: Installment Due Date Schedule

The prior decisions specify:
- 3-installment plan: Sep, Nov 25, Feb 25
- 8-installment plan: Sep + Oct–Apr 25th monthly

Phase 193 only needs to set installment 1 due dates (the member pays installment 1 immediately). The due dates for installments 2–8 are set by Phase 195's scheduler. For Phase 193, only `_installment_1_due_date` is meaningful (it could be "now" or "the 25th of the next month").

**Simplification for Phase 193:** Just write `_installment_count` and `_installment_plan`. The full `_installment_N_*` meta population is Phase 194's job. Phase 193 only needs to create the Mollie payment for the first installment amount and redirect.

### Anti-Patterns to Avoid

- **Do NOT use `wp_create_nonce` for the POST form.** There is no logged-in user to verify against. Use a hidden `token` field (the same payment token from the URL) as CSRF protection — it's a secret that only the member with the link has.
- **Do NOT reuse `MolliePayment::create_payment_link()` for installments.** It reads the invoice total and checks idempotency via `_mollie_payment_id`. For installment payments, the amount is different and the payment ID must be stored on the installment, not the invoice root.
- **Do NOT use React/Vite assets on this page.** The SPA loads via WP's `wp_enqueue_scripts` which only fires for normal WordPress page loads. `exit` in `template_redirect` prevents enqueue. Even if assets were enqueued, they'd require a nonce that unauthenticated users don't have.
- **Do NOT call `flush_rewrite_rules()` on every request.** Only call it on theme activation (`after_switch_theme`) or via WP-CLI after registering the new rewrite rule.
- **Do NOT store the plan selection and Mollie redirect as two separate requests.** The form POST must atomically write the plan meta AND create the Mollie payment AND redirect in one go, to avoid a state where the plan is stored but no payment exists.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Token entropy | Custom token generation | `bin2hex(random_bytes(32))` | Cryptographically secure, same as ICalFeed |
| Mollie API call | Raw cURL to Mollie | Existing `MollieClient` + `mollie/mollie-api-php` SDK | SDK handles auth, error wrapping, response parsing |
| Amount formatting | Custom locale-aware formatting | `number_format($n, 2, ',', '.')` | Same pattern as InvoiceEmailSender |
| Redirect | `header('Location:')` | `wp_safe_redirect()` | WordPress validates URL safety before redirecting |

---

## Common Pitfalls

### Pitfall 1: Rewrite Rule Not Taking Effect

**What goes wrong:** New rewrite rule is registered in `add_rewrite_rule` but the URL returns 404.
**Why it happens:** WordPress caches rewrite rules in the `rewrite_rules` option. New rules only take effect after `flush_rewrite_rules()`.
**How to avoid:** Call `flush_rewrite_rules()` in `after_switch_theme` (already done for other rules in `rondo_theme_activation()`). Add the new rule to that hook too.
**Warning signs:** URL gives 404 immediately after deploy; fix by navigating to Settings > Permalinks in WP admin (which flushes rules), or running `wp rewrite flush` on the server.

### Pitfall 2: MollieClient Throws on Missing API Key

**What goes wrong:** `new MollieClient()` in `MolliePayment` throws `\Mollie\Api\Exceptions\ApiException` if the API key is not configured. For the public page, this must be caught gracefully.
**Why it happens:** `MollieClient::__construct()` calls `$this->client->setApiKey($api_key)` with an empty string if no key is stored, which Mollie rejects.
**How to avoid:** Guard with `$config->get_mollie_api_key()` check before instantiating `MollieClient`, same as `MolliePayment::create_payment_link()` does. Return a user-friendly Dutch error page, not a PHP fatal.
**Warning signs:** White screen / 500 error on the payment page when Mollie is not configured.

### Pitfall 3: SPA Catch-All Intercepts /betaling/ URL

**What goes wrong:** Visiting `/betaling/abc123` loads the React SPA instead of the payment page.
**Why it happens:** `rondo_theme_template_redirect()` in `functions.php` runs at priority 1 and catches all 404-like routes (the `is_404()` branch). If our priority-0 handler returns early (instead of calling `exit`), the priority-1 handler fires next.
**How to avoid:** Always call `exit` immediately after outputting the payment page or doing the redirect. Do NOT `return` from the handler after rendering — always `exit`.
**Warning signs:** Payment page URL renders the React app shell (shows "Loading...").

### Pitfall 4: Double Mollie Payment on Back Button

**What goes wrong:** Member selects a plan, gets redirected to Mollie, presses Back, selects again — a second Mollie payment is created for the same installment.
**Why it happens:** No idempotency check on the plan selection POST.
**How to avoid:** After writing `_installment_plan` and `_installment_1_mollie_payment_id`, check if a payment ID already exists for installment 1 before calling Mollie. If it exists, redirect directly to the stored checkout URL.
**Warning signs:** Two `tr_*` Mollie payment IDs for the same installment on the same invoice.

### Pitfall 5: Token in URL is Logged by Servers

**What goes wrong:** The payment token appears in server access logs, proxy logs, or browser history.
**Why it happens:** Tokens in URL paths are always logged.
**How to avoid (for Phase 193):** This is an accepted limitation — the prior decisions chose token-in-URL for simplicity. Document it. Do not escalate to HMAC-signed URLs or short-lived tokens in Phase 193.
**Warning signs:** N/A for Phase 193 (accepted tradeoff).

### Pitfall 6: flush_rewrite_rules Needed After Deploy

**What goes wrong:** Deployed production server has old rewrite rule cache and `/betaling/` 404s.
**Why it happens:** WordPress stores rewrite rules in wp_options and doesn't auto-flush on file changes.
**How to avoid:** After deploy, run `wp rewrite flush` on production (add to deploy checklist or deploy script).
**Warning signs:** 404 on `/betaling/{token}` immediately after deploy.

---

## Code Examples

### Verified Pattern: Token Lookup by Post Meta

```php
// Source: Analogous to MollieWebhook::handle_webhook() post meta lookup
$posts = get_posts([
    'post_type'      => 'rondo_invoice',
    'post_status'    => 'any',
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'meta_query'     => [[
        'key'   => '_payment_token',
        'value' => sanitize_text_field( $raw_token ),
    ]],
]);
$invoice_id = $posts[0] ?? null;
```

### Verified Pattern: Direct Mollie Payment Creation for Custom Amount

```php
// Source: MolliePayment::create_payment_link() + Mollie SDK docs
// Use when amount differs from invoice total_amount (installment case)
$mollie_client = new MollieClient();
$mollie        = $mollie_client->get();
$payment = $mollie->payments->create([
    'amount'      => [
        'currency' => 'EUR',
        'value'    => number_format( $first_installment_amount, 2, '.', '' ),
    ],
    'description' => 'Termijn 1 - Contributie ' . $season . ' - ' . $invoice_number,
    'redirectUrl' => home_url( '/betaling/' . $token ),  // Return to landing page
    'webhookUrl'  => rest_url( 'rondo/v1/mollie/webhook' ),
    'metadata'    => [
        'invoice_id'         => $invoice_id,
        'installment_number' => 1,
    ],
]);
$checkout_url = $payment->getCheckoutUrl();
// Store: _installment_1_mollie_payment_id = $payment->id
// Store: _installment_1_payment_link = $checkout_url
// Store: _mollie_pid_{$payment->id} = 1  (reverse-lookup for webhook)
update_post_meta( $invoice_id, '_installment_1_mollie_payment_id', $payment->id );
update_post_meta( $invoice_id, '_installment_1_payment_link', $checkout_url );
update_post_meta( $invoice_id, '_mollie_pid_' . $payment->id, 1 );
```

### Verified Pattern: Rewrite Rule + Query Var Registration

```php
// Source: class-ical-feed.php lines 37-60 (same pattern)
public function register_rewrite_rules() {
    add_rewrite_rule(
        '^betaling/([a-f0-9]{64})$',
        'index.php?rondo_payment_token=$matches[1]',
        'top'
    );
}

public function add_query_vars( $vars ) {
    $vars[] = 'rondo_payment_token';
    return $vars;
}
```

### Verified Pattern: Standalone PHP HTML Page Output

```php
// Source: ICalFeed::output_feed() pattern — output and exit
private function render_page( int $invoice_id ) {
    status_header( 200 );
    nocache_headers();
    header( 'Content-Type: text/html; charset=UTF-8' );
    ?>
    <!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Betaling - <?php echo esc_html( get_bloginfo('name') ); ?></title>
        <style>
            /* Self-contained mobile-first CSS — no external dependencies */
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                   background: #f8fafc; color: #1e293b; min-height: 100vh; }
            /* ... */
        </style>
    </head>
    <body>
        <!-- Invoice details + plan selection form -->
    </body>
    </html>
    <?php
}
```

### Verified Pattern: CSRF Protection for Unauthenticated POST

```php
// Source: project pattern — payment token IS the CSRF token
// No wp_nonce for unauthenticated users. The payment token in the hidden field
// serves as CSRF protection (attacker cannot forge a form without the token).
?>
<form method="POST" action="">
    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
    <input type="hidden" name="plan" value="full">
    <button type="submit">Volledig betalen</button>
</form>
<?php
// In POST handler:
$submitted_token = sanitize_key( $_POST['token'] ?? '' );
if ( $submitted_token !== $token ) {
    $this->render_error( 'Ongeldige aanvraag.' );
    return;
}
```

---

## Open Questions

1. **When is the payment token generated?**
   - What we know: The token needs to exist before the email is sent (so the link can be in the email). `InvoiceEmailSender::send()` is the send point.
   - What's unclear: Should token generation happen in `InvoiceEmailSender`, in `RestInvoices::send_invoice()` (the orchestrator), or in the new `PublicPaymentPage` class as a static method called by the sender?
   - Recommendation: Add `PublicPaymentPage::generate_token($invoice_id)` as a static method. Call it from `InvoiceEmailSender::send()` right before building the email, store the result, and include it in the `{betaallink}` template variable. This keeps token logic colocated with the page that uses it.

2. **What does the `{betaallink}` template variable in membership emails point to?**
   - What we know: The current email template variable `{betaallink}` in `InvoiceEmailSender` renders as a Mollie direct link (`get_field('payment_link')`). For membership invoices, the link should point to `/betaling/{token}` instead of directly to Mollie — so the member can choose their plan.
   - What's unclear: Does Phase 193 need to modify `InvoiceEmailSender` to conditionally use the landing page URL for membership invoices, or is email sending for membership invoices a separate code path (Phase 195)?
   - Recommendation: Phase 193 should NOT modify `InvoiceEmailSender` — membership invoice emails are Phase 195's job. Phase 193 only needs the page to exist and be reachable. The token is generated when the page class initializes for a token that doesn't exist yet, OR generated at invoice creation. The planner should decide whether token generation is in Phase 193 or Phase 195.

3. **Which billing plans should Phase 193 show?**
   - What we know: The prior decisions say "based on what the admin has enabled." Phase 196 adds the plan enable/disable toggles in Finance Settings. Phase 193 ships before Phase 196.
   - What's unclear: Should Phase 193 always show all three plans (full, 3-installments, 8-installments), or should it read a flag that doesn't exist yet?
   - Recommendation: Phase 193 shows all three plans unconditionally. The plan-enable flags from Phase 196 can filter the display later. This avoids a blocking dependency.

4. **What is the `redirectUrl` when creating the Mollie payment from the landing page?**
   - What we know: `MolliePayment::create_payment_link()` uses `FinanceConfig::get_mollie_redirect_url()`, which is admin-configured and may point to a generic "thank you" page.
   - What's unclear: For the installment flow, should the redirectUrl be the same admin-configured URL, or should it redirect back to `/betaling/{token}` so the member can see a "betaling gelukt" confirmation?
   - Recommendation: Redirect back to `/betaling/{token}?status=paid` so the page can show a success message. This gives the member feedback without requiring a separate thank-you page configuration. The page reads the `?status` query param and shows "Uw betaling is verwerkt."

---

## Key Decisions Needed Before Planning

The planner needs answers to these before writing plan tasks:

**D1: Token generation timing**
Options:
- A) Token generated in Phase 193 by `PublicPaymentPage::generate_token()`, called from `InvoiceEmailSender` (requires modifying InvoiceEmailSender in Phase 193)
- B) Token generated at invoice creation time (stored immediately), `InvoiceEmailSender` just reads it
- C) Token generated by Phase 195's membership email system (Phase 193 page works but email linking deferred)

Recommended: **B** — generate at invoice creation time (in `RestInvoices::create_invoice` for membership type), so the token always exists before anything tries to use it.

**D2: Plan availability**
Options:
- A) Phase 193 hardcodes all three plans (full, 3x, 8x) always visible
- B) Phase 193 reads flags that don't exist yet (blocks on Phase 196)

Recommended: **A** — hardcode all three plans for now. Phase 196 adds the flags and Phase 197 uses them.

**D3: Redirect URL after Mollie payment**
Options:
- A) Use admin-configured `get_mollie_redirect_url()`
- B) Redirect back to `/betaling/{token}?betaald=1` for inline confirmation

Recommended: **B** — richer UX, no extra config, the page is already public.

---

## Scope Clarification for Phase 193

Based on the requirements and prior decisions, Phase 193 scope is:

**IN SCOPE:**
- New rewrite rule: `^betaling/([a-f0-9]{64})$` → `rondo_payment_token` query var
- New class `PublicPaymentPage` with GET renderer and POST handler
- Token generation method (static, on the class)
- Token generation triggered at membership invoice creation (hook into `RestInvoices`)
- Invoice detail display: member name, invoice number, season, total amount
- Three plan buttons: Volledig, 3 termijnen, 8 termijnen (all always shown)
- POST handler: write `_installment_plan` + `_installment_count` meta, create Mollie payment for correct first-payment amount, store installment payment ID using reverse-lookup pattern
- Redirect to Mollie checkout URL
- Error page for invalid/unknown token (Dutch message)
- Success state for return from Mollie (`?betaald=1` param)
- Mobile-first CSS (no horizontal scroll, touch-friendly 48px+ buttons)
- Register class in `functions.php` on all non-REST requests (not just `$is_rest`)
- `flush_rewrite_rules` on theme activation

**OUT OF SCOPE (later phases):**
- Plan enable/disable toggles (Phase 196)
- Membership invoice email with landing page link (Phase 195)
- Installments 2–N scheduling and emails (Phase 195)
- Webhook handling for installment payment confirmation (Phase 194)
- All-paid detection (Phase 194)

---

## Sources

### Primary (HIGH confidence)

- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-ical-feed.php` — rewrite rule + query var + template_redirect pattern
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-carddav-server.php` — template_redirect priority 0 confirmed
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/functions.php` line 829 — SPA catch-all at priority 1
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-payment.php` — Mollie payment creation pattern
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-webhook.php` — post meta lookup via WP_Query
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php` — installment meta schema (class docblock), FinanceConfig options
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-invoice-email-sender.php` — PHP-rendered HTML email pattern
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_invoice_fields.json` — invoice ACF fields (invoice_type, total_amount, person, etc.)
- Context7 `/websites/mollie` — Mollie PHP payment creation with redirectUrl, webhookUrl, metadata

### Secondary (MEDIUM confidence)

- Prior decisions in phase context: `template_redirect priority 0`, `PHP-rendered public page`, `flat numbered post meta`, `reverse-lookup pattern`

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components already exist in codebase
- Architecture: HIGH — directly verified against iCal and CardDAV class patterns
- Pitfalls: HIGH — flush_rewrite_rules and SPA catch-all verified from live code
- Open questions: MEDIUM — token timing and plan display policy need planner decision

**Research date:** 2026-02-18
**Valid until:** 2026-04-18 (stable — WordPress hook system and Mollie SDK do not change rapidly)
