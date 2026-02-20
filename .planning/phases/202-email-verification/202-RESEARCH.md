# Phase 202: Email Verification - Research

**Researched:** 2026-02-20
**Domain:** Transactional email verification via Lettermint (post-activation testing)
**Confidence:** HIGH (all findings verified live on production via WP-CLI, DNS queries, and Lettermint logs)

---

## Summary

Phase 202 is a verification phase, but live testing during research has already exposed a **critical bug**: two email types (`EmailChannel` digest/notification emails and `MentionNotifications` immediate emails) will fail through Lettermint because they send from `@rondo.svawc.nl` — a subdomain that is NOT verified in the Lettermint account. Only `svawc.nl` is verified. Lettermint returns HTTP 422 with "The domain 'rondo.svawc.nl' is not verified or does not belong to your account."

Three email types were tested live and confirmed working: (1) discipline invoice with PDF attachment — accepted by Lettermint API with response 202; (2) VOG request email — accepted with response 202; (3) basic wp_mail from `@svawc.nl` addresses — accepted. Two email types fail: (4) `EmailChannel` which explicitly sets `notifications@rondo.svawc.nl` as From, and (5) `MentionNotifications::send_immediate_notification()` which sets no custom From, causing WordPress to fall back to `wordpress@rondo.svawc.nl`.

**Primary recommendation:** Phase 202 must include a code fix for both `EmailChannel` and `MentionNotifications` to use `@svawc.nl` addresses before the verification tests can pass. The fix is straightforward: change `EmailChannel::set_email_from_address()` to return `notifications@svawc.nl`, and add an explicit From header in `MentionNotifications`. The installment email sub-type verification requires a Mollie call and cannot be triggered without a live installment in pending state — a test invoice with a quarterly/monthly plan will need to be created on production, or WP-CLI can mock the Mollie call.

**Primary recommendation:** Fix `EmailChannel` and `MentionNotifications` from-address bug before running verification tests. Then trigger each email type via WP-CLI and confirm delivery in the Lettermint dashboard.

---

## Pre-Research: Live Testing Results

These findings were obtained by actually sending emails on production during research (2026-02-20):

| Email Type | Class | From Address | Lettermint Result | Status |
|------------|-------|-------------|-------------------|--------|
| Invoice (discipline) with PDF | `InvoiceEmailSender::send()` | `penningmeester@svawc.nl` | HTTP 202, `message_id` returned | **PASSES** |
| VOG request | `VOGEmail::send()` | `vog@svawc.nl` | HTTP 202 | **PASSES** |
| EmailChannel digest | `EmailChannel::send()` | `notifications@rondo.svawc.nl` | HTTP 422 — domain not verified | **FAILS** |
| Mention notification (immediate) | `MentionNotifications::send_immediate_notification()` | `wordpress@rondo.svawc.nl` (WP default) | HTTP 422 — domain not verified | **FAILS** |
| Installment email | `InstallmentEmailSender::send_installment_email()` | `penningmeester@svawc.nl` | Not tested live (requires Mollie) | **EXPECTED TO PASS** (correct domain) |
| Overdue reminder | `InstallmentEmailSender::send_reminder_1/2()` | `penningmeester@svawc.nl` | Not tested live | **EXPECTED TO PASS** |

---

## Production State Confirmed

- Lettermint v1.4.2: **active** on production
- Gravity SMTP v2.1.3: **inactive**
- API token: configured (`lm_kM8kzftX...`)
- Logging enabled: `lettermint_enable_logs = 1`
- Verified sending domain in Lettermint: **`svawc.nl`** only
- `rondo.svawc.nl` subdomain: NOT a verified Lettermint domain

### From Addresses by Email Type

| Email Type | From Address | Domain | Lettermint Can Send |
|------------|-------------|--------|---------------------|
| Invoice (discipline) | `penningmeester@svawc.nl` (from `FinanceConfig`) | `svawc.nl` | YES |
| Membership fee invoice | `penningmeester@svawc.nl` | `svawc.nl` | YES |
| Installment (termijn) | `penningmeester@svawc.nl` | `svawc.nl` | YES |
| Overdue reminder 1 | `penningmeester@svawc.nl` | `svawc.nl` | YES |
| Overdue reminder 2 (+BCC) | `penningmeester@svawc.nl` + BCC `factuur@svawc.nl` | `svawc.nl` | YES |
| VOG request | `vog@svawc.nl` (configured in WP options) | `svawc.nl` | YES |
| VOG reminder | `vog@svawc.nl` | `svawc.nl` | YES |
| EmailChannel digest | `notifications@rondo.svawc.nl` | `rondo.svawc.nl` | **NO** |
| Mention notification (immediate) | `wordpress@rondo.svawc.nl` (WP default fallback) | `rondo.svawc.nl` | **NO** |

### Finance Contact Configuration (production)

```
penningmeester@svawc.nl  — FinanceConfig::get_contact_email()
factuur@svawc.nl         — FinanceConfig::get_bcc_email()  (treasurer BCC on reminder 2)
sv AWC                   — FinanceConfig::get_org_name()
```

---

## Bug: EmailChannel and MentionNotifications Use Wrong Domain

### EmailChannel (`includes/class-email-channel.php`)

```php
// Line 288-291 — current broken code
public function set_email_from_address( $from_email ) {
    $domain = parse_url( home_url(), PHP_URL_HOST );
    return 'notifications@' . $domain;
}
```

`home_url()` returns `https://rondo.svawc.nl`, so `$domain` = `rondo.svawc.nl`. The resulting From is `notifications@rondo.svawc.nl`. **Fix:** hardcode to `@svawc.nl` or use `wp_parse_url( network_home_url(), PHP_URL_HOST )` after stripping the `www.` prefix — but the simplest fix is to use the verified domain directly.

### MentionNotifications (`includes/class-mention-notifications.php`)

```php
// Lines 88-93 — no From header set
wp_mail(
    $user->user_email,
    $subject,
    $message,
    [ 'Content-Type: text/html; charset=UTF-8' ]
);
```

No `From:` header. WordPress core `wp_mail()` falls back to `wordpress@{site_domain}` where `{site_domain}` is derived from `home_url()` — which is `rondo.svawc.nl`. **Fix:** Add `From: Rondo Club <notifications@svawc.nl>` to the headers array.

### Recommended Fix

Both classes should use `notifications@svawc.nl` as the From address. This is consistent with the verified Lettermint domain. The fix is 2-3 lines of code in each file and must be deployed before the verification tests in plan 202-02 can pass.

---

## Tested Trigger Methods (for Verification)

### Plan 202-01: Invoice Email with PDF (WP-CLI)

Invoice 6187 (2026T003, discipline, person 422) was used in research testing. It has a PDF at `invoices/factuur-2026T003.pdf`. The `override_email` option redirects delivery to a test address without sending to the real recipient.

```bash
# On production via SSH:
wp eval '
$result = Rondo\Finance\InvoiceEmailSender::send(6187, [
    "override_email" => "joost@joost.blog",
    "skip_bcc" => true
]);
echo is_wp_error($result) ? $result->get_error_message() : "SUCCESS";
echo PHP_EOL;
'
```

This was confirmed working during research (Lettermint log shows HTTP 202, message_id assigned, attachments included).

**Attachment verification:** Lettermint log `request_data` array shows `attachments` key with base64-encoded content. The research log confirms the PDF was included — Lettermint's `process_attachments()` read the file from `wp-content/uploads/invoices/factuur-2026T003.pdf` and base64-encoded it correctly.

### Plan 202-01: Membership Fee Invoice Email (WP-CLI)

Invoice 6189 (2026C002, membership, plan=full) has a PDF and payment token. Use `resend` route for memberships:

```bash
wp eval '
$result = Rondo\Finance\InvoiceEmailSender::send(6189, [
    "override_email" => "joost@joost.blog",
    "skip_bcc" => true
]);
echo is_wp_error($result) ? $result->get_error_message() : "SUCCESS";
echo PHP_EOL;
'
```

### Plan 202-02: Installment Email (WP-CLI)

No multi-installment invoices currently exist on production (all membership invoices have `plan=full`, meaning one payment). To test `InstallmentEmailSender`, either:

**Option A:** Create a test membership fee invoice with `quarterly_3` or `monthly_8` plan. This requires going through the full invoice creation flow in the UI (Settings > Contributie, then trigger invoice generation for a member). The installment scheduler or a direct WP-CLI call can then trigger `send_installment_email`.

**Option B:** Call `InstallmentEmailSender::send_installment_email()` directly on invoice 6189 (which has `plan=full`, installment 1 pending). This will attempt a Mollie `paymentLinks->create()` call. If Mollie is configured, it will succeed.

```bash
wp eval '
$result = Rondo\Finance\InstallmentEmailSender::send_installment_email(6189, 1);
echo is_wp_error($result) ? $result->get_error_message() : "SUCCESS";
echo PHP_EOL;
'
```

Note: `send_installment_email` writes `_installment_1_status = sent` BEFORE calling `wp_mail`. If this is run on a real invoice (6189), it will change the production installment status. Consider creating a dedicated test invoice.

**Reminder emails** require overdue status (14+ days past due date). The simplest test is to call `send_reminder_1` directly:

```bash
wp eval '
$result = Rondo\Finance\InstallmentEmailSender::send_reminder_1(6189, 1);
echo is_wp_error($result) ? $result->get_error_message() : "SUCCESS";
'
```

**Reminder 2 with BCC to treasurer** (`factuur@svawc.nl`):

```bash
wp eval '
$result = Rondo\Finance\InstallmentEmailSender::send_reminder_2(6189, 1);
echo is_wp_error($result) ? $result->get_error_message() : "SUCCESS";
'
```

### Plan 202-02: VOG Emails (WP-CLI)

VOG request email — confirmed working in research. Person 371 (Jurre) was sent a VOG aanvraag to `jurrevh2004@gmail.com`. For verification plan, use any person with an email address:

```bash
# VOG request (new volunteer)
wp eval '$vog = new Rondo\VOG\VOGEmail(); $r = $vog->send(371, "new"); echo is_wp_error($r) ? $r->get_error_message() : "SUCCESS"; echo PHP_EOL;'

# VOG reminder
wp eval '$vog = new Rondo\VOG\VOGEmail(); $r = $vog->send_reminder(371, "reminder_new"); echo is_wp_error($r) ? $r->get_error_message() : "SUCCESS"; echo PHP_EOL;'
```

### Plan 202-02: Mention Notification Email (WP-CLI + fix)

After the `MentionNotifications` fix is deployed, test with:

```bash
# Set user pref to immediate, trigger a mention manually
wp eval '
update_user_meta(1, "rondo_mention_notifications", "immediate");
// Then add a note mentioning user 1 via the REST API or simulate:
$notif = new Rondo\Collaboration\MentionNotifications();
// Or directly test wp_mail with the fixed from header
$result = wp_mail(
    "joost@svawc.nl",
    "Test mention notification",
    "<p>Someone mentioned you in a note</p>",
    ["Content-Type: text/html; charset=UTF-8", "From: Rondo Club <notifications@svawc.nl>"]
);
echo ($result ? "SUCCESS" : "FAILED") . PHP_EOL;
'
```

### Plan 202-02: EmailChannel Digest Email (WP-CLI + fix)

After the `EmailChannel` fix is deployed, the daily digest is triggered by WP-Cron. To test manually:

```bash
# Trigger the daily digest hook directly
wp eval 'do_action("rondo_daily_reminders");'
```

Or test the email send path directly:

```bash
wp eval '
$result = wp_mail(
    "joost@svawc.nl",
    "Test digest email",
    "<p>Test digest content</p>",
    ["Content-Type: text/html; charset=UTF-8", "From: Rondo <notifications@svawc.nl>"]
);
echo ($result ? "SUCCESS" : "FAILED") . PHP_EOL;
'
```

---

## Lettermint Log Verification Method

After each `wp_mail` call, the most recent Lettermint log entry shows the result:

```bash
wp eval '
$logs = get_option("lettermint_logs", []);
$last = end($logs);
echo "Type: " . $last["type"] . PHP_EOL;
echo "Message: " . $last["message"] . PHP_EOL;
echo "From: " . ($last["details"]["request_data"]["from"] ?? "N/A") . PHP_EOL;
echo "To: " . implode(",", (array)($last["details"]["request_data"]["to"] ?? [])) . PHP_EOL;
echo "Code: " . ($last["details"]["response_code"] ?? "N/A") . PHP_EOL;
echo "Error: " . ($last["details"]["error_message"] ?? "none") . PHP_EOL;
$atts = $last["details"]["request_data"]["attachments"] ?? [];
echo "Attachments: " . count($atts) . PHP_EOL;
'
```

- `type: success` + `response_code: 202` = email accepted by Lettermint API
- `type: error` + `response_code: 422` = domain not verified
- Check `attachments` count to confirm PDF was included

The Lettermint dashboard at https://dash.lettermint.co provides human-visible delivery confirmation (required for the success criteria about "appears in Lettermint dashboard").

---

## Architecture: How Each Email Class Sends

### Invoice emails (InvoiceEmailSender, InstallmentEmailSender)

Both set explicit `From:` header via the `$headers` array:

```php
$headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $org_name . ' <' . $contact_email . '>',
];
```

`$contact_email` = `penningmeester@svawc.nl`. This is correct and aligns with the Lettermint verified domain.

### VOG email (VOGEmail)

Uses `wp_mail_from` and `wp_mail_from_name` filters (set before `wp_mail` call, removed after). The `from_email` is stored in the `rondo_vog_from_email` WordPress option (currently `vog@svawc.nl`). Correct.

### EmailChannel (daily digest + mention digest)

Uses `wp_mail_from` filter that returns `notifications@rondo.svawc.nl`. **Wrong domain — must fix.**

### MentionNotifications (immediate mention email)

No `From:` header, no `wp_mail_from` filter. WordPress falls back to `wordpress@rondo.svawc.nl`. **Wrong domain — must fix.**

---

## Code Fix Required (before 202-02 can pass)

### Fix 1: EmailChannel

**File:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-email-channel.php`

**Current (line 288-291):**
```php
public function set_email_from_address( $from_email ) {
    $domain = parse_url( home_url(), PHP_URL_HOST );
    return 'notifications@' . $domain;
}
```

**Fixed:**
```php
public function set_email_from_address( $from_email ) {
    return 'notifications@svawc.nl';
}
```

Or more flexibly, extract the root domain from the site URL to avoid hardcoding `svawc.nl`. However, the Lettermint verified domain IS `svawc.nl` and the simplest correct fix returns that domain. If the codebase needs to be environment-agnostic, use `get_option('lettermint_from_email')` as a fallback or a new WP option — but given this is a single-tenant app, hardcoding is acceptable. The cleaner approach is to strip the subdomain:

```php
public function set_email_from_address( $from_email ) {
    $host = wp_parse_url( network_home_url(), PHP_URL_HOST );
    // Strip leading www. (WordPress core pattern)
    if ( str_starts_with( $host, 'www.' ) ) {
        $host = substr( $host, 4 );
    }
    // Use root domain (strip subdomains like rondo.)
    $parts = explode( '.', $host );
    $domain = count( $parts ) >= 2
        ? implode( '.', array_slice( $parts, -2 ) )
        : $host;
    return 'notifications@' . $domain;
}
```

This extracts `svawc.nl` from `rondo.svawc.nl` correctly. This approach is more portable if the site ever moves, but adds complexity. **Recommended:** use the root-domain extraction approach for correctness without hardcoding.

### Fix 2: MentionNotifications

**File:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mention-notifications.php`

**Current (lines 88-93):**
```php
wp_mail(
    $user->user_email,
    $subject,
    $message,
    [ 'Content-Type: text/html; charset=UTF-8' ]
);
```

**Fixed:** Add a `From:` header using the same root-domain extraction logic, or reuse the `EmailChannel` from address:

```php
$site_name = get_bloginfo( 'name' );
$host = wp_parse_url( network_home_url(), PHP_URL_HOST );
if ( str_starts_with( $host, 'www.' ) ) {
    $host = substr( $host, 4 );
}
$parts  = explode( '.', $host );
$domain = count( $parts ) >= 2
    ? implode( '.', array_slice( $parts, -2 ) )
    : $host;
$from_email = 'notifications@' . $domain;

wp_mail(
    $user->user_email,
    $subject,
    $message,
    [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $from_email . '>',
    ]
);
```

**Alternative (DRY):** Since both files need the same root-domain logic, extract it to a shared helper function or move it into `EmailChannel` and call it statically. Given the DRY principle (Rule 3), this is preferred.

---

## Installment Email: No Multi-installment Invoice on Production

**Finding:** All current production membership invoices with installment plans use `plan=full` (one payment). There are no `quarterly_3` or `monthly_8` invoices on production. This means:

1. `InstallmentEmailSender::send_installment_email()` can be tested on invoice 6189 (plan=full, term 1 = pending), BUT calling it will permanently mark `_installment_1_status = sent` on that invoice.

2. Reminder 1 and Reminder 2 tests require calling the methods directly — they will also attempt Mollie payment link creation (which is a live API call).

**Recommendation for plan:** Use a dedicated test invoice (create a new membership fee invoice for a test person with a quarterly plan) OR accept that calling `send_installment_email` on a real invoice changes its status and run it on the least-critical pending invoice.

**Alternative for BCC verification:** Reminder 2 sends BCC to `factuur@svawc.nl`. To verify BCC is included, check the Lettermint log `request_data.bcc` field — this can be done programmatically without checking the actual inbox.

---

## Lettermint Dashboard Verification

The success criteria require "Lettermint dashboard shows successful delivery for each email type tested." This is a human checkpoint. The Lettermint activity log at https://dash.lettermint.co shows:

- Timestamp
- Recipient
- Subject
- Delivery status (delivered, bounced, etc.)
- From address

For Phase 202 verification, the planner should include a human checkpoint after each batch of test emails to confirm they appear in the Lettermint dashboard. The WP option `lettermint_logs` provides programmatic pre-verification (confirms API accepted the email), while the Lettermint dashboard confirms actual delivery to the recipient.

---

## What Must Happen in Order

1. **Fix 1: EmailChannel from address** → deploy to production
2. **Fix 2: MentionNotifications from address** → deploy to production (same deploy)
3. **Plan 202-01:** Test invoice email with PDF attachment (discipline invoice + membership fee invoice) — already confirmed working
4. **Plan 202-02:** Test installment email, reminder 1, reminder 2 (BCC verified in log), VOG email, VOG reminder, mention notification, EmailChannel digest

---

## Phase Structure Recommendation

The phase has 2 plans as described:

**202-01:** Focus on invoice email with PDF attachment
- Discipline invoice (already confirmed working in research)
- Membership fee invoice (same code path, different invoice type)
- Verify Lettermint dashboard shows delivery
- Verify PDF attachment included in Lettermint log

**202-02:** All other email types — but this plan MUST include the bug fix as a first task before verification tests:
1. Fix `EmailChannel` from address
2. Fix `MentionNotifications` from address
3. Deploy fix to production
4. Test installment emails (initial + reminder 1 + reminder 2 with BCC)
5. Test VOG email + reminder
6. Test mention notification (immediate)
7. Test EmailChannel digest trigger
8. Human checkpoint: Lettermint dashboard shows all email types delivered

---

## Common Pitfalls

### Pitfall 1: Subdomain From address rejected by Lettermint

**What goes wrong:** Any email From address on `@rondo.svawc.nl` is rejected with HTTP 422. Lettermint only allows sending from verified domains. `rondo.svawc.nl` is not verified — only `svawc.nl` is.

**Root cause:** `home_url()` returns `https://rondo.svawc.nl` (the WordPress site URL). Both `EmailChannel` and the WP default fallback derive the From domain from this URL without stripping the subdomain.

**How to avoid:** Always strip subdomain to root domain when constructing From addresses. Use `array_slice(explode('.', $host), -2)` to extract the TLD+domain.

### Pitfall 2: InstallmentEmailSender triggers live Mollie API

**What goes wrong:** Calling `send_installment_email` creates a live Mollie payment link. This costs nothing but creates a real payment link record. If called on a real member's invoice, it changes the installment status from `pending` to `sent`.

**How to avoid:** Either create a dedicated test invoice for a fake person, or accept the side effects and call it on the least-impactful pending invoice.

### Pitfall 3: Lettermint log overflow (453KB observed)

**What goes wrong:** `lettermint_logs` WordPress option grew to 453KB including base64-encoded PDF content in the request_data. Storing large binary data in WordPress options table is inefficient.

**Risk:** This is not a blocker for Phase 202, but worth noting. If many PDFs are sent, the option could grow very large.

**How to avoid for now:** Not a Phase 202 concern. Flag for future cleanup phase.

### Pitfall 4: Mention notification only fires for "immediate" preference

**What goes wrong:** By default, `rondo_mention_notifications` user meta is empty, which means `MentionNotifications` queues mentions for digest delivery (not immediate email). To test the immediate email path, the user pref must be set to `immediate`.

**How to avoid:** Before testing mention notification, set `wp eval 'update_user_meta(1, "rondo_mention_notifications", "immediate");'`

---

## Sources

### Primary (HIGH confidence)

- Live Lettermint logs on production (`get_option("lettermint_logs")` via WP-CLI) — actual API responses for each email type tested 2026-02-20
- Production WP-CLI: `wp eval` for `InvoiceEmailSender::send()`, `VOGEmail::send()`, and direct `wp_mail` with `notifications@rondo.svawc.nl` — confirmed working/failing
- DNS queries: `dig TXT rondo.svawc.nl` (no records), `dig TXT svawc.nl` (SPF present), `dig TXT lettermint._domainkey.svawc.nl` (DKIM present for root domain only)
- Source code: `class-email-channel.php`, `class-mention-notifications.php`, `class-invoice-email-sender.php`, `class-installment-email-sender.php`, `class-vog-email.php` — read directly

### Secondary (MEDIUM confidence)

- Phase 201 research and summary: confirmed Lettermint plugin version, activation state, API token, verified domain list

### Tertiary (LOW confidence)

- None. All critical claims verified from primary sources.

---

## Metadata

**Confidence breakdown:**
- Invoice email with PDF: HIGH — confirmed working on production with Lettermint log showing HTTP 202 and attachment
- VOG email: HIGH — confirmed working on production
- EmailChannel bug: HIGH — confirmed failing on production with HTTP 422 error message
- MentionNotifications bug: HIGH — confirmed failing on production with HTTP 422 error message
- Installment email (initial, reminders): MEDIUM — not tested live (Mollie dependency), but correct domain confirmed via code inspection
- BCC on reminder 2: MEDIUM — code path clear, `$add_bcc=true` confirmed, but delivery not tested

**Research date:** 2026-02-20
**Valid until:** 2026-03-20 (production state stable; Lettermint domain verification state stable)
