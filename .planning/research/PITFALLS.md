# Pitfalls Research: Membership Fee Invoicing with Payment Plans

**Domain:** Payment plan invoicing + public landing pages + multi-payment webhook extension added to existing WordPress invoice system
**Researched:** 2026-02-18
**Confidence:** HIGH (Mollie webhook behavior, WP-Cron reliability, token security) / MEDIUM (bulk creation at 500-member scale, invoice CPT extension patterns)
**Context:** Rondo Club — extending existing `rondo_invoice` CPT (currently discipline invoices with single Mollie payment per invoice) to support membership fee invoices with installment tracking, public token-secured landing pages, and WP-Cron-driven monthly reminder scheduling.

---

## Critical Pitfalls

### Pitfall 1: Webhook Looks Up `_mollie_payment_id` — Breaks When Invoice Has Multiple Payments

**What goes wrong:**
The current `MollieWebhook::handle_webhook()` finds the invoice by querying `post_meta` where `_mollie_payment_id = $payment_id`. This works for the existing 1:1 model (one invoice, one payment). For a payment plan, one invoice has N installment payments, each with its own Mollie payment ID. The current code cannot find the invoice for installment #2 onwards because only one `_mollie_payment_id` is stored per invoice.

The new webhook call arrives with installment payment ID `tr_SECOND_PAYMENT`, but no post has `_mollie_payment_id = tr_SECOND_PAYMENT`. The handler logs "no invoice found" and returns 200 — the installment is silently ignored and never marked as paid.

**Why it happens:**
The webhook's invoice lookup is designed around the 1:1 assumption hardcoded in the meta key name (`_mollie_payment_id`, singular). Developers adding installment tracking add more payments without touching the webhook lookup strategy.

**How to avoid:**
Store installment payment IDs in a way the webhook can traverse. Two approaches — choose one before implementation:

**Option A (recommended — serialized list in dedicated meta):**
Store all Mollie payment IDs for an invoice in a single meta key as a JSON array:
```php
// When creating installment payment:
$existing = json_decode( get_post_meta( $invoice_id, '_mollie_payment_ids', true ) ?: '[]', true );
$existing[] = $new_payment_id;
update_post_meta( $invoice_id, '_mollie_payment_ids', json_encode( $existing ) );

// In webhook handler — query against serialized field (requires LIKE):
$meta_query = [
    [
        'key'     => '_mollie_payment_ids',
        'value'   => $payment_id,
        'compare' => 'LIKE', // Searches inside JSON string
    ],
];
```

**Option B (separate installment posts):**
Create a `rondo_installment` CPT where each installment is a post with `_mollie_payment_id` set, linked to the parent invoice. Webhook finds the installment post, then marks the parent invoice. More normalized, higher complexity.

Do NOT keep using the singular `_mollie_payment_id` and add `_mollie_payment_id_2`, `_mollie_payment_id_3` — this does not scale and makes the webhook lookup code unbounded.

**Warning signs:**
- Webhook handler logs "no invoice found for payment `tr_xxx`" for installments after the first
- Installment statuses never advance automatically after first payment
- Mollie Dashboard shows 200 responses on webhook but installment status stays "open" in the app

**Phase to address:** Data model phase — define installment payment ID storage strategy before writing any installment or webhook code.

---

### Pitfall 2: Webhook Marks Entire Invoice "Paid" When One Installment Pays

**What goes wrong:**
The current webhook handler calls `wp_update_post(['post_status' => 'rondo_paid'])` on the invoice when any payment is confirmed paid. For a 4-installment plan, the webhook fires after installment 1, and the invoice immediately becomes `rondo_paid` — even though 3 installments are still outstanding.

The treasurer sees all membership invoices as "Paid" after month 1. Subsequent installments are never charged because the invoice is already marked complete.

**Why it happens:**
The existing webhook is designed to make a single binary transition: sent → paid. The concept of "partially paid" does not exist in the current invoice state machine. Developers wiring up the new payment ID lookup forget to update the transition logic.

**How to avoid:**
The webhook must check the installment context before transitioning:
1. When webhook fires: identify which installment was paid, mark that installment as paid
2. After marking the installment: check if ALL installments for this invoice are now paid
3. Only if all installments are paid: transition invoice to `rondo_paid`
4. If some installments remain: keep invoice in `rondo_sent` (or a new `rondo_partial` status) — do not change invoice status

Define a `rondo_partial` post status only if the treasurer needs to distinguish "partially paid" from "fully sent" in the UI. For MVP, keeping `rondo_sent` with installment meta updated may be sufficient.

**Warning signs:**
- Invoice transitions to `rondo_paid` before due date of final installment
- Treasurer reports "all memberships show as paid" in month 1 of the plan
- No installment emails sent after month 1 (because invoice is no longer in `rondo_sent`)

**Phase to address:** Webhook extension phase — update transition logic alongside installment payment ID lookup.

---

### Pitfall 3: Public Landing Page Intercepted by SPA's Catch-All template_redirect

**What goes wrong:**
The existing `rondo_theme_template_redirect()` function in `functions.php` (line 769) intercepts ALL frontend requests, including unrecognized paths, and serves `index.php` (the React SPA). A public membership payment landing page at `/betaling/abc123token` will be caught by this handler, served as the React SPA, which then routes to a 404 page because `/betaling` is not in the React Router routes.

The member clicks the link in their email and gets the Rondo Club login screen, not their payment page.

**Why it happens:**
The catch-all handler was written for a 100% authenticated SPA where all public-facing URLs are managed by React Router. The assumption `if it's a 404 and not admin, serve index.php` becomes wrong when there is a legitimate public page at a new URL.

The handler at line 818:
```php
if ( $is_app_route || ( is_404() && ! is_admin() ) ) {
    include get_template_directory() . '/index.php';
    exit;
}
```
The `/betaling/TOKEN` path is not in `$app_routes`, will 404 in WordPress, and gets served as the SPA.

**How to avoid:**
Two implementation options — choose one:

**Option A (recommended — intercept before SPA catch-all):**
Register a custom rewrite rule at `init` for the betaling URL pattern. In the `template_redirect` handler, add an explicit exemption before the SPA serve:
```php
// In rondo_theme_template_redirect(), before the is_app_route check:
if ( 0 === strpos( $path, 'betaling/' ) ) {
    return; // Let WordPress handle this — it has a dedicated template
}
```
Then register a custom rewrite rule and use a template file (`betaling.php`) to serve the public page.

**Option B (REST endpoint as landing page):**
Serve the public page from a REST endpoint at `/wp-json/rondo/v1/betaling/{token}` that renders HTML directly. Bypasses all SPA/template routing. Simpler but unusual (REST endpoints normally return JSON).

Do NOT add `/betaling` to `$app_routes` — this would make the React SPA try to render it, requiring authentication.

**Warning signs:**
- Visiting the public betaling URL in an incognito browser shows the Rondo login page
- Browser network tab shows the SPA's `index.php` loading, not a standalone page
- No HTTP 401 — just React Router 404 within the authenticated SPA

**Phase to address:** Public landing page phase — test in incognito browser immediately after creating the URL, before building any React component.

---

### Pitfall 4: Token Brute Force on Public Landing Page

**What goes wrong:**
A token-secured public landing page at `/betaling/{token}` with a short or predictable token is vulnerable to brute force. If the token is 8 characters of hex (`wp_generate_password(8, false, false)`), an attacker who knows the URL pattern can enumerate tokens. With ~500 live tokens and no rate limiting on a public WordPress REST endpoint, exposure is feasible.

An attacker who guesses a valid token sees the member's name, invoice amount, and a Mollie payment link — or can mark the invoice as paid in the app by completing the payment.

**Why it happens:**
- Developers use `wp_hash()` or `substr(md5(...), 0, 8)` for brevity — short tokens are guessable
- WordPress REST endpoints have no built-in rate limiting
- Public landing pages by definition cannot use WordPress authentication as a defense layer

**How to avoid:**
1. Use 32-byte cryptographic tokens: `bin2hex(random_bytes(32))` = 64 character hex token. This is effectively unguessable.
2. Store the token hashed in the database (`hash('sha256', $token)`) — display value is never stored plaintext
3. Tokens must expire (30-day reasonable maximum for payment plans)
4. Invalidate token once invoice is fully paid — paid invoices have no payment action possible
5. Add server-level rate limiting on the landing page URL pattern (`.htaccess` or SiteGround's rate limiting tools)
6. Log failed token lookups to error_log — patterns of failed lookups indicate brute force attempts

```php
// Token generation (secure):
$token = bin2hex( random_bytes( 32 ) ); // 64 hex chars
$token_hash = hash( 'sha256', $token );
update_post_meta( $invoice_id, '_public_token_hash', $token_hash );
update_post_meta( $invoice_id, '_public_token_expires', time() + ( 30 * DAY_IN_SECONDS ) );

// Token verification (timing-safe):
$stored_hash = get_post_meta( $invoice_id, '_public_token_hash', true );
if ( ! hash_equals( $stored_hash, hash( 'sha256', $token ) ) ) {
    // Invalid token — return 404, not 403 (don't confirm invoice exists)
}
```

**Warning signs:**
- Token shorter than 32 characters in generated URLs
- Token stored unhashed in post meta (visible in wp-admin)
- No expiry on token meta
- Error logs show repeated requests to `/betaling/` with different tokens

**Phase to address:** Public landing page phase — token generation must be cryptographically secure from day 1.

---

### Pitfall 5: Bulk Invoice Creation (500 Members) Times Out or Exhausts Memory

**What goes wrong:**
Creating 500 invoices in a single HTTP request — iterating through all members, calculating fees, calling `wp_insert_post()`, `update_field()` (6+ ACF writes per invoice), and generating a Mollie payment link (HTTP API call) per member — will exhaust PHP's execution time limit (typically 30-60s on SiteGround) and memory limit well before completion.

The request fails mid-way through. The treasurer sees "internal server error" after waiting 2 minutes. 200 invoices are created, 300 are not. There is no way to tell which members got invoices without manually checking.

`wp_insert_post()` also triggers all post save hooks (AutoTitle, FeeCacheInvalidator, Google Contacts export scheduling) for each insert — compounding the performance issue.

**Why it happens:**
- Single HTTP request cannot run indefinitely
- Each `wp_insert_post()` fires multiple WordPress action hooks
- ACF `update_field()` is slower than `update_post_meta()` for repeater-free fields
- Mollie API calls add ~200-500ms network latency per invoice (makes 500 invoices = 100-250 seconds in API calls alone)

**How to avoid:**
1. Batch creation via WP-Cron, not a single HTTP request:
   - Initial request creates a transient with the member list and returns immediately
   - WP-Cron processes batches of 20-30 invoices per scheduled event
   - Progress tracked in an option (`rondo_invoice_batch_progress`)
   - Treasurer sees a progress indicator in the UI, polling for completion
2. Defer Mollie payment link creation to a separate step after invoice creation
3. Suppress non-essential hooks during batch creation: wrap with `remove_action` guards for Google Contacts export and fee cache invalidation
4. Use `wp_insert_post()` with `wp_slash()` for title only — delay ACF writes via bulk `update_post_meta()` where possible
5. Decouple email sending entirely — send emails as a separate scheduled batch

**Warning signs:**
- Browser request times out after 30-60 seconds
- Partial set of invoices created (visible in invoice list)
- `error_log` shows `maximum execution time exceeded` or `allowed memory size exhausted`
- Google Contacts export cron queue explodes with 500 pending jobs

**Phase to address:** Bulk creation phase — design the batching architecture first. Do not attempt 500-invoice creation as a synchronous REST endpoint.

---

### Pitfall 6: WP-Cron Installment Reminders Fire Late or Not at All

**What goes wrong:**
Monthly installment reminder emails are scheduled via `wp_schedule_event()` or `wp_schedule_single_event()`. WP-Cron only fires when WordPress receives an HTTP request. On a low-traffic site or when SiteGround serves pages from full-page cache (bypassing WordPress entirely), WP-Cron does not fire at all. Installment reminders scheduled for the 1st of the month fire on the 5th (when the treasurer next logs in) — or never.

**Why it happens:**
WP-Cron is not a true system cron. It piggybacks on visitor requests. SiteGround's caching layer and object cache can serve pages without loading WordPress, meaning no WP-Cron trigger. The WordPress documentation acknowledges this: "It's quite possible that scheduled events could be delayed if your site doesn't receive much traffic."

**How to avoid:**
1. Disable WP-Cron's visitor-triggered mechanism: add `define('DISABLE_WP_CRON', true)` to `wp-config.php`
2. Set up a real server-level cron job via SiteGround's Cron Jobs panel to hit `wp-cron.php` every 15 minutes:
   ```
   */15 * * * * curl -s https://rondo.svawc.nl/wp-cron.php?doing_wp_cron > /dev/null 2>&1
   ```
3. For installment scheduling, store the next due date as post meta on each invoice and use a single daily cron hook that queries for invoices with `next_installment_date <= today` rather than scheduling 500 individual per-invoice events

Using per-invoice scheduled events (500 `wp_schedule_event()` calls) bloats the `wp_options` cron option, making every WordPress page load slower.

**Warning signs:**
- `wp_get_scheduled_event('rondo_installment_reminder')` returns future dates that have already passed without firing
- Treasurer reports members not receiving monthly installment emails
- WP Crontrol plugin shows overdue events ("missed schedule")
- `wp_options` table `cron` option is abnormally large

**Phase to address:** Installment scheduling phase — design the cron architecture (single daily sweeper vs per-invoice events) before writing any scheduling code.

---

### Pitfall 7: Invoice Numbering Race Condition Under Bulk Creation

**What goes wrong:**
`InvoiceNumbering::generate_next()` queries all existing invoices matching the year prefix, finds the max, and increments. This is not atomic. Under bulk creation with 2-3 concurrent PHP processes (triggered by multiple browser tabs or a batch processor spawning workers), two processes read the same max simultaneously, both produce the same next number, and two invoices share a number (e.g., two `2026T047`).

Duplicate invoice numbers violate Dutch invoice numbering requirements (sequential, no gaps, no duplicates per BTW-wet / fiscal regulations).

**Why it happens:**
The current sequential scan + increment pattern works for single-user, single-request invoice creation (discipline invoices). Bulk creation parallelism exposes the race window.

**How to avoid:**
Use MySQL's `GET_LOCK()` to serialize invoice number generation, or use WordPress's `update_option()` with autoload as an atomic counter (WordPress uses `REPLACE INTO` which is atomic for options):

```php
// Atomic counter approach using WordPress options:
public static function generate_next(): string {
    $year = gmdate( 'Y' );
    $option_key = 'rondo_invoice_counter_' . $year;

    // get_option returns false if not set; start at 0
    $current = (int) get_option( $option_key, 0 );
    $next = $current + 1;

    // update_option uses REPLACE INTO — effectively atomic for single-value options
    update_option( $option_key, $next, false ); // false = no autoload

    return $year . 'T' . str_pad( (string) $next, 3, '0', STR_PAD_LEFT );
}
```

Note: This still has a rare TOCTOU window under heavy concurrency. For absolute safety, wrap in `wp_cache_add()` with a transient lock, or use `$wpdb->query('SELECT GET_LOCK...')`. At 500-member bulk creation with batching (see Pitfall 5), true concurrency is low and the option-based approach is sufficient.

**Warning signs:**
- Two invoices with identical invoice numbers in the invoice list
- `InvoiceNumbering::generate_next()` called from inside a batching loop without serialization
- Bulk creation running concurrent WP-Cron worker processes

**Phase to address:** Data model / bulk creation phase — switch to the option-based counter before implementing bulk creation.

---

## Moderate Pitfalls

### Pitfall 8: Mollie Payment Expires Before Member Pays Installment — No Graceful Recovery

**What goes wrong:**
Mollie Payments API payments expire after 15 minutes (iDEAL, Bancontact) or 12 days (bank transfer). For installment plans, the second month's payment link is generated 30 days after the first. If the member doesn't pay within the method's expiry window, Mollie sends a webhook with status `expired`. The system has no handler for expired installment payments and does not generate a replacement link.

The member's installment page shows a broken/expired payment link. They cannot pay. The treasurer has no automatic way to know without manually checking Mollie.

**How to avoid:**
1. Handle `expired` status in the webhook: when an installment payment expires, generate a new Mollie payment for the same installment amount and update the landing page token with the new link
2. Use bank transfer (`banktransfer`) as the primary method for installment plans — 12-day expiry is much more forgiving than 15 minutes
3. Send a "your payment link has expired, here is a new one" reminder email automatically when webhook signals expiry
4. Alternatively: generate the monthly installment payment link only when the monthly reminder email is sent (not in advance), reducing the window during which expiry can occur

**Warning signs:**
- Mollie webhook delivers `expired` status but code only handles `paid`
- Member landing page shows "Betaling verlopen" with no option to retry
- Webhook handler returns 200 for expired payments without creating a replacement

**Phase to address:** Webhook extension phase + public landing page phase.

---

### Pitfall 9: Public Landing Page Loads WordPress to Serve Static Token — Expensive for Members' ISPs

**What goes wrong:**
The public payment landing page is served by WordPress (PHP), loading ACF, all theme classes, WP-Cron checks, and all active plugins on every page view. For a 500-member club, the peak (invoice send day) could be 400+ simultaneous page loads. SiteGround's shared hosting may throttle or time out under this load.

**How to avoid:**
1. Serve the landing page HTML from a transient or pre-rendered static string (cache the rendered page per token)
2. The page only needs to display: member name, invoice number, amount, remaining installments, and a payment button. This is a simple PHP template with minimal database reads — 2-3 `get_post_meta()` calls
3. Do NOT load ACF `get_field()` on the public page if you can read the data via `get_post_meta()` directly (faster, no ACF overhead)
4. After the token is validated once, cache a `{token_hash}_page_data` transient for 5 minutes so rapid refreshes by the member do not re-query

**Warning signs:**
- Public page takes >2 seconds to load
- SiteGround CPU throttling alerts during invoice send day
- `get_field()` calls on the public page include ACF's full field group loading overhead

**Phase to address:** Public landing page implementation phase.

---

### Pitfall 10: Bulk Email (500 Members) Hits SiteGround SMTP Rate Limit

**What goes wrong:**
Sending 500 invoice emails via `wp_mail()` in rapid succession hits SiteGround's SMTP hourly sending limit (typically 500 emails/hour for shared hosting). Emails sent after the limit are silently dropped, queued, or result in errors that do not bubble up to the treasurer. Members don't receive their invoices and the treasurer has no failure report.

**Why it happens:**
`wp_mail()` uses PHP's mail function or a configured SMTP server. SiteGround imposes per-account hourly email limits that are not exposed in WordPress error handling.

**How to avoid:**
1. Send emails in time-spaced batches: 50 emails per WP-Cron run, scheduled every 15 minutes = 500 emails over ~2.5 hours
2. Log each email send result — `wp_mail()` returns `false` on failure; log which member ID and invoice failed
3. Provide a "resend failed" action in the treasurer UI that re-queues members who didn't receive emails
4. Consider Transactional Email services (Postmark, SendGrid via WP Mail SMTP plugin) which have much higher rate limits and bounce tracking

**Warning signs:**
- Fewer than 500 members received emails despite 500 invoices showing `rondo_sent`
- `wp_mail()` returns `false` silently for some members
- SiteGround email logs show rate limiting or deferred sending

**Phase to address:** Bulk email sending phase.

---

### Pitfall 11: `line_items` ACF Repeater Doesn't Accommodate Membership Invoice Structure

**What goes wrong:**
The existing `line_items` repeater has fields: `discipline_case` (relationship), `description` (text), `amount` (number). Membership fee invoices do not have discipline cases — the `discipline_case` field would be empty for every row. The existing `InvoiceEmailSender` uses `if ( ! empty( $item['discipline_case'] ) )` to render the table, and falls back to a generic description row. The membership invoice email would render identically to the "other" line item format — which uses a `<td colspan="3">` table without the Datum/Wedstrijd/Kaart columns.

The email template for discipline invoices and membership invoices are structurally incompatible unless the email sender is made invoice-type-aware.

**How to avoid:**
1. Store the invoice type as post meta or ACF field: `_invoice_type` = `'discipline' | 'membership'`
2. `InvoiceEmailSender` reads `_invoice_type` and renders different HTML templates for each type
3. Alternatively, use a more generic line items structure (description + amount only) and move the discipline-specific table rendering to a separate template — making `line_items` invoice-type-agnostic
4. PDF generator (`InvoicePdfGenerator`) has the same problem — needs to be type-aware

**Warning signs:**
- Membership invoice email shows empty table columns (Datum, Wedstrijd, Kaart) with dashes
- `get_field('line_items', $invoice_id)` returns rows where `discipline_case` is null for every row in a membership invoice
- PDF renders discipline-specific headers even for membership invoices

**Phase to address:** Data model phase — define `_invoice_type` before writing any invoice creation code.

---

### Pitfall 12: Payment Plan State Machine Has No "Cancelled" or "Defaulted" Path

**What goes wrong:**
A member starts a 4-installment payment plan and stops paying after installment 2. The invoice stays `rondo_sent`, the remaining installments keep generating monthly reminder emails, and the treasurer has no way to mark the plan as defaulted or cancelled without manually deleting installment data.

In a receivables context, unresolved installment plans accumulate and the treasurer cannot distinguish "plan in progress" from "plan abandoned."

**How to avoid:**
1. Define the installment state machine before implementation: `scheduled → pending → paid | expired | cancelled`
2. Add a "Cancel remaining installments" action on the invoice detail view (treasurer-only)
3. Cancellation should: stop the cron reminder, mark remaining installments `cancelled`, move invoice to `rondo_overdue` or a new `rondo_defaulted` status
4. After N reminders with no payment (e.g., 2 consecutive missed installments), optionally auto-cancel and notify the treasurer

**Warning signs:**
- No "cancel plan" button in invoice UI
- Cron jobs continue sending reminders for invoices where member has stopped paying
- Invoice list has no way to filter "active plans" vs "abandoned plans"

**Phase to address:** Data model / UI phase — define full state machine before writing installment scheduling code.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Keep singular `_mollie_payment_id` meta, add `_mollie_payment_id_2` etc. | No webhook refactor needed immediately | Webhook lookup code unbounded; adding installment 5 requires code change | Never |
| Synchronous 500-invoice creation in single REST request | No batch infrastructure needed | Times out at 30+ invoices; partial state with no recovery | Never for bulk creation |
| Use `wp_schedule_event()` per invoice (500 cron jobs) | Simple scheduling concept | Bloats cron option; slow page loads; misses fire on low-traffic | Never at 500-member scale |
| Short token (8-12 chars) for public landing page | Shorter URLs | Brute-forceable; member financial data exposed | Never for financial data |
| Trust `template_redirect` catch-all to handle betaling page | No routing code needed | Public page served as SPA login screen; member cannot pay | Never |
| Skip email logging for bulk send | Simpler implementation | No visibility into send failures; no retry path | Only for MVP if resend is manual |
| Reuse `InvoiceEmailSender::send()` unchanged for membership invoices | No new code | Wrong email template (discipline table columns) for membership invoices | Never — type-awareness required |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Mollie webhook (multi-payment) | Look up invoice by `_mollie_payment_id` (singular) | Store all payment IDs per invoice; use LIKE query or separate installment CPT |
| Mollie webhook (status transition) | Mark invoice paid on first installment confirmed | Check if all installments paid before transitioning invoice to `rondo_paid` |
| Mollie payment expiry | Ignore `expired` webhook status | Handle expiry: generate replacement payment link, send new email |
| WP-Cron installment scheduling | 500 `wp_schedule_event()` calls (one per invoice) | Single daily sweeper hook queries invoices with `next_installment_date <= today` |
| WordPress template routing | Add betaling URL to `$app_routes` array | Add explicit exemption in `template_redirect` before SPA catch-all |
| Bulk `wp_insert_post()` | Call in loop inside single REST request | Queue member IDs in transient; process in batched WP-Cron events |
| SiteGround SMTP | Call `wp_mail()` 500 times in rapid succession | Batch emails with delays; log failures; provide resend mechanism |
| `InvoiceEmailSender` | Use unchanged for membership invoices | Add `_invoice_type` context; render type-specific HTML template |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Synchronous bulk invoice creation | Browser timeout, partial invoice set | Batch via WP-Cron, return job ID immediately | > 20 invoices in one request |
| Per-invoice WP-Cron events at 500 scale | Slow WordPress page loads (cron option bloat) | Single daily sweeper queries meta, not individual events | > 50 scheduled invoice events |
| `get_field()` on public landing page | Public page slow (ACF overhead) | Direct `get_post_meta()` for known keys | Every page load |
| Overdue check on every invoice list request | Invoice list slow as invoice count grows | Move overdue check to WP-Cron daily hook | > 200 invoices in system |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Short or MD5-based landing page token | Brute force attack reveals member financial data | 64-char hex token from `bin2hex(random_bytes(32))` |
| Token stored plaintext in post meta | DB breach exposes all active payment links | Store `hash('sha256', $token)` only; token in URL is one-time secret |
| Landing page at `404 → SPA catch-all` path | Member cannot access; error reveals app structure | Explicit route exemption before SPA handler |
| No token expiry | Old paid invoices still have valid payment links | Set 30-day expiry; invalidate on full payment |
| Public webhook endpoint with no secondary verification | (Existing pitfall — documented in v27.0 PITFALLS.md) | Always re-fetch from Mollie API |
| Installment email contains direct payment link without token refresh | Expired link in email; member confused | Regenerate Mollie payment link at send time, not at invoice creation |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Landing page shows total invoice amount (not installment amount) | Member thinks they owe full amount immediately | Show "Termijn X van N" with installment amount prominently |
| No confirmation after payment redirect | Member unsure if payment worked | Poll Mollie status on redirect URL; show "betaling verwerkt" or "betaling in afwachting" |
| Bulk create with no progress feedback | Treasurer stares at spinner for 5 minutes then sees error | Return immediately, show async progress bar polling a status endpoint |
| Reminder email looks identical to original invoice email | Member ignores reminder thinking it's a duplicate | Subject line: "Herinnering termijn 2: [invoice number]" + different body copy |
| Overdue installment not clearly flagged | Treasurer cannot identify defaulting members | Distinct `rondo_overdue` status per installment + filter in invoice list |

---

## "Looks Done But Isn't" Checklist

- [ ] **Multi-payment webhook:** Webhook returns 200 for installment 2 — verify the invoice was actually found and installment marked paid (not just the 200 from the "no invoice found" guard path)
- [ ] **Invoice status:** First installment pays — verify invoice does NOT transition to `rondo_paid` unless all installments are complete
- [ ] **Public page routing:** Visit `/betaling/TOKEN` in incognito — verify it shows the payment page, not the Rondo login screen or SPA 404
- [ ] **Token security:** Inspect the landing page URL in the email — verify token is ≥ 64 characters and appears random, not sequential
- [ ] **Bulk creation:** Create 500 invoices — verify no timeout, verify all 500 created, verify no duplicate invoice numbers
- [ ] **Cron reminders:** Schedule a reminder for 1 minute in the future — verify it fires without requiring a page visit (i.e., real server cron is set up)
- [ ] **Email template:** Open membership invoice email — verify it does not show empty Datum/Wedstrijd/Kaart discipline table columns
- [ ] **Payment expiry:** Let a Mollie test payment expire — verify the installment gets a replacement link and the member receives a new email
- [ ] **Token expiry:** Advance the server clock past expiry date — verify the token no longer works
- [ ] **Partial payment state:** Pay 2 of 4 installments — verify invoice list distinguishes this from fully unpaid and fully paid

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Webhook marks invoice paid after first installment | MEDIUM | Write migration script to reset `rondo_paid` invoices that still have unpaid installments back to `rondo_sent`; re-activate cron reminders |
| Bulk creation times out at 200/500 | LOW | Identify gap in invoice numbers; re-run batch creation for remaining members using a "skip existing" check |
| Cron reminders never fire (no system cron) | LOW | Set up real system cron immediately; manually trigger missed reminders via WP-CLI: `wp eval 'do_action("rondo_installment_reminder_sweep");'` |
| Duplicate invoice numbers from race condition | HIGH | Identify duplicates via SQL; renumber one of each pair; notify relevant member; update invoice numbering to option-based counter |
| Public page intercepted by SPA | LOW | Add URL exemption to `template_redirect`; flush rewrite rules; fix is immediate |
| Token brute forced (if short token used) | HIGH | Invalidate all active tokens; regenerate with 64-char tokens; send new emails to all members; audit access logs for suspicious patterns |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Multi-payment webhook lookup | Data model: define installment payment ID storage | Test: POST installment 2 payment ID to webhook; verify invoice found |
| Webhook marks invoice paid on first installment | Webhook extension: add installment-count check | Test: pay 1 of 4 installments; verify invoice stays `rondo_sent` |
| Public page intercepted by SPA catch-all | Public landing page: add URL exemption first | Test: incognito browser visit to `/betaling/token` shows payment page |
| Token brute force | Public landing page: use `random_bytes(32)` from day 1 | Verify: token in generated URLs is 64 hex chars |
| Bulk creation timeout | Bulk creation architecture: design batching first | Test: trigger 500-invoice creation; verify no timeout, no partial state |
| WP-Cron unreliability | Installment scheduling: set up real server cron | Verify: real cron configured in SiteGround panel before first scheduled reminder |
| Invoice number race condition | Data model: switch to option-based counter | Test: concurrent bulk creation; verify no duplicate numbers |
| Payment expiry unhandled | Webhook extension: handle expired status | Test: let Mollie test payment expire; verify replacement generated |
| Wrong email template for membership invoice | Data model: add `_invoice_type` meta | Verify: membership invoice email shows no Datum/Wedstrijd/Kaart columns |
| Bulk SMTP rate limit | Bulk email: implement batching with delay | Test: send 500 emails; verify all delivered via email log |
| No cancelled/defaulted state | Data model: define full installment state machine | Verify: "cancel plan" action exists in UI; cron stops after cancellation |
| Public page performance | Landing page implementation: use `get_post_meta()` not `get_field()` | Test: public page load time < 500ms under load |

---

## Sources

### HIGH Confidence (Official Documentation + Codebase Analysis)

- [Mollie Webhooks Reference](https://docs.mollie.com/reference/webhooks) — 10 retries over 26h; re-fetch required; always return 200; 15s timeout
- [Mollie Recurring Payments](https://docs.mollie.com/payments/recurring) — subscription payment IDs not known in advance; use subscriptionId or metadata for lookup
- [Mollie Handling Payment Status](https://docs.mollie.com/docs/handling-payment-status) — expiry behavior; do not predict expiry; handle `expired` webhook status
- [WordPress WP_Cron Reliability](https://developer.wordpress.org/plugins/cron/) — visitor-triggered, unreliable on low-traffic or cached sites
- [`functions.php` lines 769-829](https://github.com/local) — actual catch-all `template_redirect` implementation confirmed from codebase
- [`class-mollie-webhook.php`](https://github.com/local) — confirmed 1:1 lookup via `_mollie_payment_id` singular meta
- [`class-invoice-numbering.php`](https://github.com/local) — confirmed scan-and-increment pattern without locking
- [`class-rest-invoices.php` line 346-382](https://github.com/local) — confirmed overdue check on every list request

### MEDIUM Confidence (Official Docs + Community Verification)

- [WP-Cron Missed Events — WP Crontrol](https://wp-crontrol.com/help/missed-cron-events/) — documents visitor-trigger reliability issues; solution: system cron
- [WP Mail SMTP Rate Limiting](https://wpmailsmtp.com/introducing-wp-mail-smtp-4-0-optimized-email-sending-rate-limiting/) — email rate limiting per host; need batching for bulk sends
- [Duplicate Invoice Numbers — WordPress.org](https://wordpress.org/support/topic/duplicate-invoice-numbers-1/) — community reports of race conditions in invoice numbering
- [Mollie Payment Expiry Times](https://wordpress.org/support/topic/mollie-payments-expire-too-soon/) — 15-min expiry for iDEAL; 12-day for bank transfer confirmed

### LOW Confidence (WebSearch Only — Validate During Implementation)

- SiteGround PHP memory limits for WP-Cron contexts — verify actual limits on account before designing batch sizes
- Token length recommendations for public financial pages — 64-char hex from `random_bytes(32)` is conservative; cryptography guidance from PHP docs is authoritative

---

*Pitfalls research for: Membership fee invoicing with payment plans, public landing pages, and installment tracking added to Rondo Club*
*Researched: 2026-02-18*
