# Architecture Research: Mollie Integration

**Domain:** Payment provider integration into existing WordPress invoice system
**Researched:** 2026-02-17
**Confidence:** HIGH (existing codebase is authoritative; Mollie from official SDK docs)

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    REST Layer (rondo/v1)                         │
├─────────────────────────────────────────────────────────────────┤
│  RestInvoices            RabobankOAuth        MollieWebhook     │
│  (MODIFIED)              (unchanged)           (NEW — public)   │
│       │                                             │           │
│       ↓                                    Mollie POST id=tr_   │
│  get_active_provider()                              │           │
│       │                                             ↓           │
│  ┌────┴──────────────────────────┐        fetch payment status  │
│  │  'rabobank'  │  'mollie'      │        update invoice status  │
│  └────┬──────────────────────────┘                             │
│       │               │                                        │
├───────↓───────────────↓────────────────────────────────────────┤
│                   Finance Layer                                  │
│                                                                 │
│   RabobankPayment        MolliePayment (NEW)                   │
│   (unchanged)             create_payment_link( $invoice_id )   │
│                               ↓                                │
│                           MollieClient (NEW)                   │
│                               ↓                                │
│                           mollie/mollie-api-php SDK            │
│                               ↓                                │
│                           Mollie API (api.mollie.com)          │
├─────────────────────────────────────────────────────────────────┤
│                   Config Layer                                   │
│                                                                 │
│   FinanceConfig (MODIFIED — Mollie key storage, active provider)│
│   CredentialEncryption (unchanged)                              │
├─────────────────────────────────────────────────────────────────┤
│                   Storage (WordPress Options)                    │
│                                                                 │
│   rondo_finance_mollie_api_key        (encrypted, existing enc) │
│   rondo_finance_active_payment_provider  ('rabobank'|'mollie')  │
│   rondo_rabobank_oauth_tokens         (unchanged)               │
│   rondo_finance_rabobank_credentials  (unchanged)               │
└─────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Status | Responsibility | Communicates With |
|-----------|--------|----------------|-------------------|
| `RestInvoices` | MODIFIED | Orchestrates invoice send: provider selection, PDF, email | `FinanceConfig`, `MolliePayment`, `RabobankPayment` |
| `FinanceConfig` | MODIFIED | Config storage/retrieval: adds Mollie key + active provider | `CredentialEncryption`, WordPress Options |
| `MollieClient` | NEW | SDK initialisation with API key; lazy wrapper | `FinanceConfig`, `mollie/mollie-api-php` |
| `MolliePayment` | NEW | Creates Mollie payment; stores checkout URL + payment ID on invoice | `MollieClient`, WordPress post meta, ACF |
| `MollieWebhook` | NEW | Public REST endpoint; fetches payment status; marks invoice paid | `MollieClient`, WordPress post meta |
| `RabobankOAuth` | Unchanged | OAuth token management | WordPress Options |
| `RabobankPayment` | Unchanged | Rabobank betaalverzoek creation | `RabobankOAuth` |
| `CredentialEncryption` | Unchanged | Sodium encrypt/decrypt for stored secrets | AUTH_KEY |

---

## Recommended Project Structure

```
includes/
├── class-finance-config.php          # MODIFIED: + Mollie methods + active_provider
├── class-mollie-client.php           # NEW: SDK wrapper
├── class-mollie-payment.php          # NEW: payment link creation service
├── class-mollie-webhook.php          # NEW: webhook REST endpoint
├── class-rabobank-oauth.php          # unchanged
├── class-rabobank-payment.php        # unchanged
├── class-rest-invoices.php           # MODIFIED: provider branching in send_invoice()
└── class-credential-encryption.php  # unchanged

composer.json                         # MODIFIED: + mollie/mollie-api-php ^3.0
functions.php                         # MODIFIED: instantiate MolliePayment + MollieWebhook
```

### Structure Rationale

- **class-mollie-client.php:** SDK setup is isolated. Both `MolliePayment` and `MollieWebhook` instantiate `MollieClient` directly — no singleton needed at this scale. Keeps Composer dependency encapsulated.
- **class-mollie-webhook.php:** Separate from `RestInvoices` because the webhook is public (`permission_callback: '__return_true'`). Mixing public and authenticated routes in one controller creates a confusion surface and breaks the existing pattern (Rabobank OAuth has its own class).
- **class-mollie-payment.php:** Mirrors `RabobankPayment` exactly in contract: one public method `create_payment_link( $invoice_id )` returning `string|WP_Error`. `RestInvoices` calls either without knowing internals.

---

## Architectural Patterns

### Pattern 1: Provider Branching in RestInvoices::send_invoice()

**What:** Simple conditional on the stored provider setting. No PHP interface or polymorphism.

**When to use:** Two providers with fundamentally different auth setup (Rabobank = OAuth; Mollie = API key). A shared interface would impose artificial symmetry and complicate both implementations.

**Trade-offs:** Branching is explicit and readable. Adding a third provider later requires touching this one if-block — acceptable at this scale.

```php
// In RestInvoices::send_invoice()
$config   = new FinanceConfig();
$provider = $config->get_active_payment_provider(); // 'rabobank' | 'mollie'

if ( $provider === 'mollie' ) {
    $mollie         = new MolliePayment();
    $payment_result = $mollie->create_payment_link( $invoice_id );
} elseif ( $provider === 'rabobank' ) {
    $oauth = new RabobankOAuth();
    if ( $oauth->is_connected() ) {
        $payment        = new RabobankPayment( $oauth );
        $payment_result = $payment->create_payment_request( $invoice_id );
    }
}

// Existing pattern: log error but continue (payment link is non-blocking)
if ( isset( $payment_result ) && is_wp_error( $payment_result ) ) {
    error_log( 'Payment link failed: ' . $payment_result->get_error_message() );
}
```

### Pattern 2: MollieClient as Thin SDK Wrapper

**What:** One class owns `new MollieApiClient()` and `setApiKey()`. Both `MolliePayment` and `MollieWebhook` construct `new MollieClient()` and call `->get()`.

**When to use:** Always for external SDK clients. Keeps the Composer dependency contained and API key injection in one place.

**Trade-offs:** Not a singleton — each instantiation reads from `FinanceConfig` and creates a new SDK client. Acceptable: these are short-lived per-request objects.

```php
namespace Rondo\Finance;

use Mollie\Api\MollieApiClient;
use Rondo\Config\FinanceConfig;

class MollieClient {

    private MollieApiClient $client;

    public function __construct() {
        $config  = new FinanceConfig();
        $api_key = $config->get_mollie_api_key();

        $this->client = new MollieApiClient();
        $this->client->setApiKey( $api_key );
    }

    public function get(): MollieApiClient {
        return $this->client;
    }
}
```

### Pattern 3: MolliePayment Uses Payments API (Not Payment Links API)

**What:** Use `$mollie->payments->create()` (Payments API). Store `_links->checkout->href` as the payment URL. Store `$payment->id` as `_mollie_payment_id` post meta for webhook lookup.

**When to use:** For invoice-specific, one-time payments. The Payments API supports `webhookUrl` natively, enabling automatic status updates.

**Trade-offs:** Mollie also has a Payment Links API (`$mollie->paymentLinks->create()`), which produces reusable non-expiring links — designed for recurring/shared use cases, not per-invoice payments. The Payments API is the correct tool here.

```php
public function create_payment_link( int $invoice_id ): string|\WP_Error {
    $invoice = get_post( $invoice_id );
    if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
        return new \WP_Error( 'invalid_invoice', __( 'Ongeldige factuur.', 'rondo' ), [ 'status' => 404 ] );
    }

    try {
        $mollie_client = new MollieClient();
        $client        = $mollie_client->get();

        $total_amount   = (float) get_field( 'total_amount', $invoice_id );
        $invoice_number = get_field( 'invoice_number', $invoice_id );

        $payment = $client->payments->create( [
            'amount'      => [
                'currency' => 'EUR',
                'value'    => number_format( $total_amount, 2, '.', '' ),
            ],
            'description' => mb_substr( 'Factuur ' . $invoice_number, 0, 255 ),
            'redirectUrl' => home_url( '/financien/' ),
            'webhookUrl'  => rest_url( 'rondo/v1/mollie/webhook' ),
            'metadata'    => [ 'invoice_id' => $invoice_id ],
        ] );

        $payment_link = $payment->_links->checkout->href;

        // Store on invoice — same field as Rabobank (provider-agnostic field)
        update_field( 'payment_link', $payment_link, $invoice_id );
        // Store Mollie payment ID for webhook lookup
        update_post_meta( $invoice_id, '_mollie_payment_id', $payment->id );

        return $payment_link;

    } catch ( \Mollie\Api\Exceptions\ApiException $e ) {
        error_log( 'Mollie API error for invoice ' . $invoice_id . ': ' . $e->getMessage() );
        return new \WP_Error(
            'mollie_api_error',
            sprintf( __( 'Mollie API fout: %s', 'rondo' ), $e->getMessage() ),
            [ 'status' => 502 ]
        );
    }
}
```

### Pattern 4: MollieWebhook — Public Endpoint in Dedicated Class

**What:** Separate class `MollieWebhook` in `Rondo\Finance` namespace. Registers `POST /rondo/v1/mollie/webhook` with `permission_callback: '__return_true'`. Fetches payment via API (the API call is the security verification), updates invoice on `isPaid()`.

**When to use:** Always for public provider callbacks. Keeps permission model clean and separate from authenticated invoice endpoints.

**Trade-offs:** Mollie retries webhooks up to 10 times over 26 hours. Webhook must be idempotent (updating an already-paid invoice to paid is a no-op). Must always return HTTP 200 to prevent infinite retries on unresolvable errors.

```php
namespace Rondo\Finance;

class MollieWebhook {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            'rondo/v1',
            '/mollie/webhook',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_webhook' ],
                'permission_callback' => '__return_true', // Mollie servers post here — no WP auth
            ]
        );
    }

    public function handle_webhook( \WP_REST_Request $request ) {
        $payment_id = $request->get_param( 'id' );

        if ( empty( $payment_id ) ) {
            return new \WP_REST_Response( null, 200 );
        }

        try {
            $mollie_client = new MollieClient();
            $payment       = $mollie_client->get()->payments->get( $payment_id );

            if ( $payment->isPaid() ) {
                $invoice_id = $this->get_invoice_id_by_payment_id( $payment_id );
                if ( $invoice_id ) {
                    $invoice = get_post( $invoice_id );
                    // Idempotent: only update if not already paid
                    if ( $invoice && $invoice->post_status !== 'rondo_paid' ) {
                        wp_update_post( [ 'ID' => $invoice_id, 'post_status' => 'rondo_paid' ] );
                        update_field( 'status', 'paid', $invoice_id );
                    }
                }
            }
        } catch ( \Exception $e ) {
            error_log( 'Mollie webhook error for payment ' . $payment_id . ': ' . $e->getMessage() );
            // Still return 200 — code errors should not trigger Mollie retries
        }

        return new \WP_REST_Response( null, 200 );
    }

    private function get_invoice_id_by_payment_id( string $payment_id ): int {
        $posts = get_posts( [
            'post_type'      => 'rondo_invoice',
            'meta_key'       => '_mollie_payment_id',
            'meta_value'     => $payment_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
        ] );
        return (int) ( $posts[0] ?? 0 );
    }
}
```

---

## Data Flow

### Invoice Send Flow (Mollie Path)

```
POST /rondo/v1/invoices/{id}/send
    ↓
RestInvoices::send_invoice()
    ↓
FinanceConfig::get_active_payment_provider() → 'mollie'
    ↓
MolliePayment::create_payment_link( $invoice_id )
    ↓
MollieClient → MollieApiClient::setApiKey( $key )
    ↓
POST https://api.mollie.com/v2/payments (JSON)
    ↓
$payment->_links->checkout->href  → update_field('payment_link', ..., $invoice_id)
$payment->id                       → update_post_meta($invoice_id, '_mollie_payment_id', ...)
    ↓
InvoicePdfGenerator::generate()   (embeds payment_link in PDF — unchanged)
    ↓
InvoiceEmailSender::send()        (includes payment_link in email — unchanged)
    ↓
invoice status → rondo_sent
```

### Webhook Flow (Payment Confirmed by Payer)

```
Payer completes payment on Mollie checkout page
    ↓
Mollie servers POST id=tr_xxx
    ↓
POST /wp-json/rondo/v1/mollie/webhook  (public, no WP auth)
    ↓
MollieWebhook::handle_webhook()
    ↓
MollieClient::get()->payments->get('tr_xxx')
    (authenticated API call — this IS the security check; Mollie doesn't sign webhooks)
    ↓
$payment->isPaid() → true
    ↓
get_invoice_id_by_payment_id('tr_xxx') via WP_Query on _mollie_payment_id meta
    ↓
invoice already rondo_paid? → no-op (idempotency)
invoice is rondo_sent/rondo_overdue? → wp_update_post + update_field → rondo_paid
    ↓
HTTP 200 → Mollie stops retrying
```

### Credential Storage Flow

```
Finance Settings UI: user enters Mollie API key
    ↓
PUT /rondo/v1/settings (existing endpoint)
    body: { mollie_api_key: 'test_...' }
    ↓
FinanceConfig::update_settings( $data )
    ↓
FinanceConfig::update_mollie_api_key( $api_key )
    ↓
CredentialEncryption::encrypt( ['api_key' => $api_key] )
    ↓
update_option( 'rondo_finance_mollie_api_key', $encrypted )
```

---

## Build Order

The dependencies flow strictly top-to-bottom: config before client, client before payment service, payment service before webhook, both before RestInvoices branching, everything before UI.

### Phase 1: SDK + FinanceConfig + MollieClient

**Depends on:** Nothing. No REST routes registered. Safe to deploy.

- Add `composer require mollie/mollie-api-php ^3.0`
- `FinanceConfig`: add `OPTION_MOLLIE_API_KEY`, `OPTION_ACTIVE_PAYMENT_PROVIDER` constants
- `FinanceConfig`: add `get_mollie_api_key()`, `update_mollie_api_key()`, `get_active_payment_provider()`, `update_active_payment_provider()`
- `FinanceConfig::update_settings()`: handle `mollie_api_key` key in bulk update
- `FinanceConfig::get_all_settings()`: expose `mollie_has_api_key` (bool), `mollie_environment` (derived from key prefix: `test_` vs `live_`)
- Create `includes/class-mollie-client.php`

### Phase 2: MolliePayment — Payment Link Creation

**Depends on:** Phase 1 (MollieClient, FinanceConfig Mollie methods).

- Create `includes/class-mollie-payment.php` with `create_payment_link( $invoice_id ): string|WP_Error`
- Register instantiation in `functions.php` (REST-only block, alongside Rabobank classes)
- Manually testable: call the method directly, check ACF `payment_link` field and `_mollie_payment_id` meta

### Phase 3: MollieWebhook — Automatic Status Update

**Depends on:** Phase 2 (`_mollie_payment_id` meta stored by `MolliePayment`).

- Create `includes/class-mollie-webhook.php`
- Register instantiation in `functions.php` (REST-only block)
- Test with: `curl -X POST https://rondo.svawc.nl/wp-json/rondo/v1/mollie/webhook -d "id=tr_REAL_TEST_ID"`

### Phase 4: RestInvoices — Provider Branching

**Depends on:** Phases 1+2 (FinanceConfig `get_active_payment_provider()`, `MolliePayment` class).

- Modify `RestInvoices::send_invoice()`: read provider, branch to `MolliePayment` or `RabobankPayment`
- Default provider is `'rabobank'` — existing behaviour is unchanged until Mollie is explicitly configured
- This is the only modification to existing working code; do it last to minimise risk window

### Phase 5: Finance Settings UI — Mollie Configuration

**Depends on:** Phase 1+4 (settings endpoint, provider selection stored).

- Add Mollie API key input to Finance Settings React component
- Add payment provider selector (Rabobank / Mollie radio or select)
- Show Mollie status: key set / not set, environment (test / live, derived from prefix)
- Calls existing settings REST endpoint — no new backend endpoints needed

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Registering Webhook Inside RestInvoices

**What people do:** Add `POST /rondo/v1/mollie/webhook` route inside `RestInvoices::register_routes()`.

**Why wrong:** `RestInvoices` applies `check_financieel_permission()` to routes. The Mollie webhook is called by Mollie's servers — no WordPress authentication. Mixing public and authenticated routes in one controller makes the permission model fragile and breaks the existing Rabobank pattern (where `RabobankOAuth` owns its own routes).

**Do instead:** Dedicated `MollieWebhook` class. Instantiated in `functions.php` alongside `RabobankOAuth` and `RabobankPayment`.

### Anti-Pattern 2: Using Payment Links API Instead of Payments API

**What people do:** `$mollie->paymentLinks->create()` because the name sounds like "payment link".

**Why wrong:** Mollie's Payment Links API creates reusable, non-expiring links for generic payments (e.g., "donate to our club"). It has different webhook semantics than the Payments API. For invoice-specific one-time payments, the Payments API (`$mollie->payments->create()`) is the correct tool: it returns `_links->checkout->href`, supports `webhookUrl` natively, and generates a unique `tr_xxx` ID that the webhook uses to look up the invoice.

**Do instead:** Always `$mollie->payments->create()`. The checkout URL is in `_links->checkout->href`.

### Anti-Pattern 3: Storing Mollie API Key in Plain Text

**What people do:** `update_option( 'rondo_mollie_api_key', $api_key )`.

**Why wrong:** Inconsistent with the established credential storage pattern. The Rabobank credentials are sodium-encrypted. The Mollie API key is equally sensitive.

**Do instead:** `CredentialEncryption::encrypt( ['api_key' => $api_key] )` then `update_option(...)`. Same flow as `FinanceConfig::update_rabobank_credentials()`.

### Anti-Pattern 4: Trusting Webhook Payload for Payment Status

**What people do:** Read a `status` field from the webhook POST body and act on it.

**Why wrong:** Mollie webhooks POST only the payment `id` — there is no status in the payload. More importantly, Mollie's security model requires a subsequent authenticated API call to retrieve the actual status. This fetch-after-webhook pattern is the verification step — without it, any attacker could POST a fake `id` and mark invoices paid.

**Do instead:** Always `$mollie->payments->get( $payment_id )` inside the webhook. The SDK call uses your API key, making the response authoritative.

### Anti-Pattern 5: Returning Non-200 for Unresolvable Errors

**What people do:** Return 400 when the invoice can't be found, or 500 on a code error.

**Why wrong:** Mollie retries any non-200 response up to 10 times over ~26 hours. For unresolvable errors (invoice deleted, data mismatch), retries will never succeed and waste Mollie's retry budget.

**Do instead:** Catch all exceptions, log them, return 200 regardless. Only genuine transient errors (temporary DB unavailability) justify retries — and those are handled by PHP exceptions bubbling up, which you should catch and still return 200 after logging.

---

## Integration Points

### External Services

| Service | Integration Pattern | Auth | Notes |
|---------|---------------------|------|-------|
| Mollie API (api.mollie.com) | SDK `payments->create()` | API key in `Authorization: Bearer` header | Key prefix reveals env: `test_` vs `live_` |
| Mollie webhook inbound | `POST /rondo/v1/mollie/webhook` | None (public) — API fetch is the verification | Must return 200 always, idempotent |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| `RestInvoices` → `MolliePayment` | Direct `new MolliePayment()` | Same pattern as Rabobank |
| `RestInvoices` → `RabobankPayment` | Direct `new RabobankPayment($oauth)` | Unchanged |
| `MolliePayment` → `MollieClient` | Direct `new MollieClient()` | Client reads FinanceConfig internally |
| `MollieWebhook` → `MollieClient` | Direct `new MollieClient()` | Same client class, separate instantiation |
| `MollieClient` → SDK | `new MollieApiClient()` from Composer autoload | No global state |
| `FinanceConfig` → `CredentialEncryption` | `CredentialEncryption::encrypt/decrypt()` | Unchanged static calls |

---

## Scaling Considerations

Not applicable. This is a single-club management tool; scale is irrelevant to the architecture decisions here.

**One practical concern:** Mollie retries webhooks up to 10 times over 26 hours. The webhook handler must be idempotent. Updating an invoice from `rondo_paid` to `rondo_paid` must be a no-op (checking `post_status !== 'rondo_paid'` before updating achieves this).

---

## Sources

- Existing codebase: `class-rabobank-oauth.php`, `class-rabobank-payment.php`, `class-rest-invoices.php`, `class-finance-config.php`, `class-credential-encryption.php` — HIGH confidence, authoritative
- [Mollie Webhooks documentation](https://docs.mollie.com/reference/webhooks) — HIGH confidence, official docs, accessed 2026-02-17
- [Mollie Create Payment API reference](https://docs.mollie.com/reference/create-payment) — HIGH confidence, official docs
- [Mollie PHP SDK webhook recipe](https://github.com/mollie/mollie-api-php/blob/master/docs/recipes/payments/handle-webhook.md) — HIGH confidence, official SDK
- [mollie/mollie-api-php on Packagist](https://packagist.org/packages/mollie/mollie-api-php) — v3.9.0 (released 2026-02-09), PHP >=7.4 — HIGH confidence
- [Mollie Payment Links API](https://docs.mollie.com/reference/payment-links-api) — MEDIUM confidence, used to confirm Payments API is the correct choice for per-invoice use

---
*Architecture research for: Mollie payment provider integration — Rondo Club invoice system*
*Researched: 2026-02-17*
