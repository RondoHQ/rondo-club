# Phase 201: Lettermint Setup - Research

**Researched:** 2026-02-20
**Domain:** WordPress plugin installation, DNS configuration, transactional email delivery
**Confidence:** HIGH (plugin source code read directly from production server, DNS verified live, existing prior research from LETTERMINT.md)

---

## Summary

Phase 201 sets up Lettermint as the outgoing email transport for the production WordPress site (`rondo.svawc.nl`). The Lettermint WordPress plugin (v1.4.2) is already installed on production (inactive). Three of the four required DNS records for `svawc.nl` are already in place: the DKIM TXT record at `lettermint._domainkey.svawc.nl`, the bounce CNAME at `lm-bounces.svawc.nl → bounces.lmta.net`, and a DMARC TXT record at `p=reject`. The SPF record does not need updating (Lettermint uses Return-Path / bounce subdomain, not envelope-from on the primary domain). The critical blocker is Gravity SMTP, which is currently active and configured to send via Postmark using `stadion@svawc.nl` — it must be deactivated before Lettermint is activated.

The plugin source code was read directly from the production server. Its attachment handling is confirmed HIGH confidence: `process_attachments()` reads file paths via `file_get_contents()` and base64-encodes them before posting to the API. The hook mechanism uses `pre_wp_mail` (not `phpmailer_init` as the prior research speculated). Gravity SMTP replaces `wp_mail()` at the PHP function level and applies the `pre_wp_mail` filter inside its handler — so the two plugins can technically coexist with Lettermint intercepting first, but deactivating Gravity SMTP is the clean and correct approach.

**Primary recommendation:** Deactivate Gravity SMTP, activate Lettermint, enter the API token, and run the built-in test email. DNS is already fully configured — no DNS changes needed before proceeding.

---

## Current State on Production

### Plugin inventory (before this phase)

| Plugin | Status | Version | Notes |
|--------|--------|---------|-------|
| `lettermint` | **inactive** | 1.4.2 | Just installed by WP-CLI during research; not yet activated |
| `gravitysmtp` | **active** | 2.1.3 | Configured with Postmark; uses `stadion@svawc.nl`; must be deactivated |
| `advanced-custom-fields-pro` | active | 6.7.0.2 | Unrelated |
| `sg-cachepress` / `sg-security` | active | — | SiteGround infrastructure |
| `user-switching` | active | — | Dev tool |

### Gravity SMTP configuration (found in `gravitysmtp_postmark` option)

```json
{
  "server_api_token": "598d05ed-...",
  "from_email": "stadion@svawc.nl",
  "force_from_email": false,
  "from_name": "AWC Stadion",
  "is_primary": true
}
```

This plugin has sent 45 emails since activation (44 sent, 1 failed). Deactivating it is a breaking change to the current email transport — confirm with the user before deactivating, or ensure the API token is entered and Lettermint is activated in the same WP-CLI session to minimize the window where no transport is active.

### DNS state for `svawc.nl` (verified 2026-02-20)

| Record | Status | Detail |
|--------|--------|--------|
| DKIM TXT `lettermint._domainkey.svawc.nl` | PRESENT | Full RSA public key present |
| Bounce CNAME `lm-bounces.svawc.nl` | PRESENT | Points to `bounces.lmta.net` |
| DMARC `_dmarc.svawc.nl` | PRESENT | `v=DMARC1; p=reject; rua=mailto:...@dmarc-reports.cloudflare.net` |
| SPF `svawc.nl` | NO CHANGE NEEDED | Lettermint uses Return-Path (bounce subdomain); no SPF modification required |

**Conclusion:** No DNS changes are required. The Lettermint dashboard should already show `svawc.nl` as verified (or will show verified immediately after clicking "Verify all records" if a prior setup was partially completed).

### Sending domain in use

All finance emails (`InvoiceEmailSender`, `InstallmentEmailSender`) send from `penningmeester@svawc.nl` via `FinanceConfig::get_contact_email()`. VOG emails use `vog@svawc.nl`. The domain for all is `svawc.nl`. DNS is already configured for that domain.

---

## Standard Stack

### Core

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| Lettermint WordPress plugin | 1.4.2 | Intercepts `wp_mail()` and delivers via Lettermint API | Zero code changes; transparent transport replacement |
| Lettermint API | v1 | `https://api.lettermint.co/v1/send` | European GDPR-compliant endpoint |

### Not Used

| Component | Why Excluded |
|-----------|-------------|
| Lettermint PHP SDK | Requires PHP 8.2+; project baseline is PHP 8.0+. Production actually runs PHP 8.2.30 but SDK adds a Composer dependency unnecessarily |
| Direct REST API calls | Plugin handles this; no code changes needed |

---

## Architecture Patterns

### Plugin Hook Mechanism (verified from source)

The plugin uses `pre_wp_mail` hook at priority 10 (not `phpmailer_init` as the earlier research speculated):

```php
// class-lettermint-core.php
if ( $this->is_configured() ) {
    add_filter( 'pre_wp_mail', array( $this->mailer, 'intercept_wp_mail' ), 10, 2 );
}
```

When Lettermint is configured (API token set), it short-circuits WordPress's built-in mailer entirely by returning a non-null value from `pre_wp_mail`. This is the correct WordPress extension point for replacing email transport.

### Attachment Handling (confirmed HIGH confidence)

`process_attachments()` in `class-lettermint-mailer.php` handles file path arrays from `wp_mail()` correctly:

```php
// class-lettermint-mailer.php (lines verified from production server)
private function process_attachments( $attachments ) {
    $processed = array();
    foreach ( $attachments as $attachment ) {
        if ( is_string( $attachment ) && file_exists( $attachment ) ) {
            $content = file_get_contents( $attachment );
            if ( false !== $content ) {
                $processed[] = array(
                    'filename' => basename( $attachment ),
                    'content'  => base64_encode( $content ),
                );
            }
        }
    }
    return $processed;
}
```

This is an **exact match** with how `InvoiceEmailSender::send()` passes attachments — as absolute file path strings in an array. The prior research MEDIUM confidence concern is now resolved: **attachment passthrough is confirmed HIGH confidence**.

### Configuration is done via WordPress options

| WP Option | Purpose | Set Via |
|-----------|---------|---------|
| `lettermint_api_token` | API authentication token | Settings > Lettermint or WP-CLI |
| `lettermint_from_email` | Optional from email override | Settings > Lettermint |
| `lettermint_from_name` | Optional from name override | Settings > Lettermint |
| `lettermint_force_email` | Override from address set by plugins | `0` by default (leave off) |
| `lettermint_force_from_name` | Override from name set by plugins | `0` by default (leave off) |
| `lettermint_enable_logs` | Enable activity logging | `1` by default (enabled) |
| `lettermint_force_html` | Force HTML content type | `0` (theme already sets Content-Type header) |
| `lettermint_tag` | Tag all emails for tracking | Optional |
| `lettermint_route_slug` | Custom API route | Not needed |

**Important:** Do NOT enable `lettermint_force_email` or `lettermint_force_from_name`. The theme's email classes set their own From headers (e.g., `penningmeester@svawc.nl`, `vog@svawc.nl`) that must be preserved for proper domain alignment. Overriding these would break DKIM signing alignment if a different domain is forced.

### How Gravity SMTP conflicts

Gravity SMTP defines its own `wp_mail()` function via `functions_include.php` using `if (!function_exists('wp_mail'))`. Its replacement calls `Mail_Handler::mail()` which applies `pre_wp_mail`. So:

- If Gravity SMTP defines `wp_mail()` AND Lettermint registers `pre_wp_mail`: both are active, and Lettermint fires first (priority 10) inside Gravity SMTP's handler — short-circuiting Gravity SMTP's Postmark connector.
- This technically works but is fragile and will confuse logging (Gravity SMTP logs show emails; Lettermint logs show emails).
- **Correct action: deactivate Gravity SMTP before activating Lettermint.**

### WP-CLI commands for this phase

```bash
# Deactivate Gravity SMTP
wp plugin deactivate gravitysmtp

# Activate Lettermint
wp plugin activate lettermint

# Set API token (replace with actual token from Lettermint dashboard)
wp option update lettermint_api_token "YOUR_API_TOKEN_HERE"

# Verify configuration
wp option get lettermint_api_token
wp option get lettermint_enable_logs

# Send test email via WP-CLI
wp eval 'wp_mail("test@example.com", "Test from Lettermint", "This is a test email", ["Content-Type: text/html; charset=UTF-8"]);'
```

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Email transport replacement | Custom `phpmailer_init` hook in theme | Lettermint plugin | Plugin handles From/BCC/headers/attachments/logging |
| DKIM signing | Custom PHP signing code | Lettermint backend handles signing | Infrastructure concern, not theme concern |
| Bounce processing | Custom webhook | Lettermint handles bounces via bounce subdomain CNAME | Complex infrastructure |

---

## Common Pitfalls

### Pitfall 1: Activating Lettermint without deactivating Gravity SMTP

**What goes wrong:** Both plugins claim to handle email delivery. Gravity SMTP defines `wp_mail()` at the PHP function level. When Lettermint's `pre_wp_mail` fires inside Gravity SMTP's `wp_mail()`, email is delivered via Lettermint — but both plugins log the event, creating confusion. Gravity SMTP may also show the email as "not sent via Postmark" in its logs.

**How to avoid:** Always deactivate Gravity SMTP before activating Lettermint. Do this in a single WP-CLI command sequence to minimize the window with no transport.

**Warning signs:** Both the Gravity SMTP logs and the Lettermint logs show activity for the same email. Gravity SMTP's event log shows 0 emails sent after Lettermint is activated.

### Pitfall 2: Setting Force Email Address incorrectly

**What goes wrong:** If `lettermint_force_email` is set to `1` and `lettermint_from_email` is set to, e.g., `info@svawc.nl`, all emails from `penningmeester@svawc.nl` and `vog@svawc.nl` are overridden. The Lettermint dashboard will show the wrong sender; recipients may be confused.

**How to avoid:** Leave `lettermint_force_email` at `0` (the default). The theme's email classes already set correct From headers via WordPress headers array and `wp_mail_from` filters. Lettermint reads these correctly — confirmed in `get_from_email()` which applies the `wp_mail_from` filter.

**Warning signs:** All emails show the same From address regardless of which email type was sent.

### Pitfall 3: Wrong API token (project vs. global)

**What goes wrong:** Lettermint uses per-project API tokens. Entering a token from the wrong project will cause all emails to appear in the wrong project's activity log, or fail with 401 errors.

**How to avoid:** Get the API token from the correct Lettermint project for `svawc.nl` / the production domain. Verify by checking the domain association in the Lettermint dashboard.

**Warning signs:** Test email fails with 401 response code in the Lettermint logs.

### Pitfall 4: DMARC at p=reject with SPF/DKIM misalignment

**What goes wrong:** `svawc.nl` already has `DMARC p=reject`. If Lettermint's DKIM key is not verified in the dashboard, emails will fail DMARC and be rejected.

**How to avoid:** Verify the domain in the Lettermint dashboard before sending any real emails. The DKIM record (`lettermint._domainkey.svawc.nl`) is already in DNS — it just needs to be marked verified in Lettermint.

**Warning signs:** Emails go missing entirely (rejected before delivery due to DMARC failure). Check DMARC reports at the `rua` address.

### Pitfall 5: From email address not on the verified sending domain

**What goes wrong:** The Lettermint account's verified domain is `svawc.nl`. All From addresses (`penningmeester@svawc.nl`, `vog@svawc.nl`) are on that domain. If any future From address uses a different domain (e.g., a Gmail address), Lettermint will reject or deliver without DKIM signing.

**How to avoid:** Ensure all From addresses in the theme are on `svawc.nl`.

---

## Code Examples

### Verified: How Lettermint reads From headers set by theme

```php
// class-lettermint-mailer.php
private function get_from_email( $parsed_headers ) {
    if ( ! empty( $parsed_headers['from'] ) && preg_match( '/<([^>]+)>/', $parsed_headers['from'], $matches ) ) {
        $from_email = $matches[1]; // Extracts email from "Name <email>" format
    } elseif ( ! empty( $parsed_headers['from'] ) ) {
        $from_email = $parsed_headers['from'];
    } else {
        // Falls back to WordPress default
        $from_email = apply_filters( 'wp_mail_from', 'wordpress@...' );
    }
    return apply_filters( 'wp_mail_from', $from_email );
}
```

The theme's `InvoiceEmailSender` and `InstallmentEmailSender` set `From: AWC Rondo <penningmeester@svawc.nl>` in the headers array. Lettermint's `parse_headers()` extracts this and passes it to the API correctly.

### Verified: How Lettermint intercepts wp_mail

```php
// class-lettermint-core.php
if ( $this->is_configured() ) {
    add_filter( 'pre_wp_mail', array( $this->mailer, 'intercept_wp_mail' ), 10, 2 );
}

// is_configured() checks:
public function is_configured() {
    return ! empty( get_option( 'lettermint_api_token' ) );
}
```

The plugin only intercepts when an API token is set. Before the token is entered, email falls through to WordPress's default PHPMailer.

### WP-CLI: Set API token and verify

```bash
# On production via SSH:
wp option update lettermint_api_token "lm_token_here"
wp option get lettermint_api_token
# Should return the token
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| `phpmailer_init` hook (replaces PHPMailer transport) | `pre_wp_mail` hook (short-circuits before PHPMailer is initialized) | Lettermint plugin uses the cleaner, newer hook — no PHPMailer config needed |
| Gravity SMTP (Postmark) was sending from `stadion@svawc.nl` | Lettermint will send from theme-configured addresses (`penningmeester@svawc.nl`, `vog@svawc.nl`) | Correct domain alignment for all outgoing email |

---

## Open Questions

1. **Does the Lettermint account/project already exist for `svawc.nl`?**
   - What we know: DNS records are already configured for `svawc.nl` (DKIM + bounce CNAME), which implies a Lettermint account was created and the domain was added at some point.
   - What's unclear: Whether the Lettermint account is accessible, which email it's registered to, and what the API token is.
   - Recommendation: Check the Lettermint dashboard at https://dash.lettermint.co — the domain `svawc.nl` should already be listed. If the account credentials are unknown, a new account can be created and the DNS records re-verified (existing records are already correct).

2. **What happens to the Gravity SMTP email log history?**
   - What we know: Gravity SMTP has logged 45 sent/failed emails (stored in `gravitysmtp_*` options).
   - What's unclear: Whether deactivating the plugin preserves or deletes those logs.
   - Recommendation: Check the Gravity SMTP email log before deactivating to preserve any important records. Deactivation (not deletion) keeps options in the DB.

3. **Which Lettermint plan is being used?**
   - What we know: The free Developer plan covers 300 emails/month. The club likely sends fewer than 500/month total.
   - What's unclear: Whether a paid plan was already set up when the DNS was configured.
   - Recommendation: Check dashboard; if on free Developer plan, evaluate upgrading to Starter (€10/mo) for activity logs with 28-day retention and open tracking.

---

## Sources

### Primary (HIGH confidence)

- Plugin source: `/wp-content/plugins/lettermint/includes/class-lettermint-mailer.php` — read directly from production server; verified attachment handling, hook mechanism, API endpoint, header parsing
- Plugin source: `/wp-content/plugins/lettermint/includes/class-lettermint-core.php` — verified `pre_wp_mail` hook usage and configuration check
- DNS queries (live): `dig TXT lettermint._domainkey.svawc.nl`, `dig CNAME lm-bounces.svawc.nl`, `dig TXT _dmarc.svawc.nl`, `dig TXT svawc.nl` — all run 2026-02-20
- Production WP-CLI: `wp plugin list`, `wp option list --search='*gravitysmtp*'` — verified plugin states and Gravity SMTP Postmark config

### Secondary (MEDIUM confidence)

- Prior research: `/Users/joostdevalk/Code/rondo/rondo-club/.planning/research/LETTERMINT.md` — comprehensive milestone-level research from prior session; most findings now confirmed HIGH
- WordPress.org plugin page: https://wordpress.org/plugins/lettermint/ — version 1.4.2, PHP 7.4+ requirement
- Lettermint DNS guide: https://lettermint.co/knowledge-base/guides/add-dns-records-cloudflare — confirms SPF modification not needed

### Tertiary (LOW confidence)

- None for this phase; all critical findings are verified from primary sources.

---

## Metadata

**Confidence breakdown:**

- Plugin mechanism (hooks, attachment handling): HIGH — verified from production plugin source
- DNS records already in place: HIGH — verified with live dig queries
- Gravity SMTP conflict and resolution: HIGH — verified from Gravity SMTP source on production
- Lettermint API token / account existence: LOW — DNS implies prior setup, but credentials unknown; must be confirmed
- Attachment delivery end-to-end (email actually arrives with PDF): MEDIUM — code path confirmed HIGH but actual delivery not yet tested

**Research date:** 2026-02-20
**Valid until:** 2026-03-20 (DNS stable; plugin source stable for this version; account credential question remains until verified)
