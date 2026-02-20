# Lettermint Integration Research

**Project:** Rondo Club — Lettermint transactional email integration
**Researched:** 2026-02-20
**Overall confidence:** HIGH (official docs, WordPress.org plugin page, Packagist, GitHub SDK)

---

## 1. What is Lettermint?

**Company:** Founded by Bjorn Antonissen and Bjarn Bronsveld. Based in the Netherlands.
**Founded:** Official start date January 1, 2025. First blog post May 2025.
**Tagline:** "Your European partner for transactional emails — Built in the Netherlands. Hosted in Europe."

**European data sovereignty:** HIGH confidence.
- Infrastructure hosted entirely within the EU
- No data stored outside EU borders
- Full GDPR compliance
- No dependency on US-based cloud infrastructure
- Listed on european-alternatives.eu

**What it does:** Lettermint is a transactional email delivery service with REST API, SMTP relay, PHP SDK, Laravel SDK, and a WordPress plugin. It is purpose-built for developer use. In late 2025, they added Lettermint Broadcast for marketing campaigns/newsletters (separate from transactional).

**Sources:**
- https://lettermint.co/
- https://lettermint.co/blog/welcome-future-transactional-mailing-europe
- https://european-alternatives.eu/product/lettermint

---

## 2. WordPress Plugin, PHP SDK, and REST API

Lettermint provides all three. Here is what each one does:

### WordPress Plugin

**Plugin slug:** `lettermint` on WordPress.org
**Current version:** 1.4.2
**WordPress requirement:** 5.0+ (tested up to 6.9.1)
**PHP requirement:** 7.4+

**Integration mechanism:** The plugin hooks into WordPress's `wp_mail()` function transparently. It intercepts all outgoing mail — from WordPress core, plugins, and themes — and reroutes delivery through the Lettermint REST API. **No code changes required in the theme.** Existing `wp_mail()` calls continue to work unchanged.

**Plugin configuration (Settings > Lettermint):**
| Setting | Required | Purpose |
|---------|----------|---------|
| API Token | Yes | Authenticates to Lettermint API |
| From Email | Optional | Overrides WordPress default sender email |
| From Name | Optional | Overrides WordPress default sender name |
| Force Email Address | Optional | Overrides sender set by plugins/themes |
| Force Name | Optional | Overrides sender name set by plugins/themes |
| Enable Logs | Optional | Logs email activity and API responses |
| Tag | Optional | Tags all emails from this site for tracking |
| Force HTML | Optional | Ensures HTML emails render correctly |
| Custom Route | Optional | Support for custom API routes |

**What data the plugin sends to Lettermint's API per email:**
- Recipients (To, CC, BCC from headers)
- Subject
- Email body (HTML and plain text)
- Sender email address
- Headers

**Source:** https://wordpress.org/plugins/lettermint/

### PHP SDK

**Package:** `lettermint/lettermint-php`
**Composer:** `composer require lettermint/lettermint-php`
**Current version:** 1.5.1 (released 2025-12-23)
**PHP requirement:** 8.2+ (note: higher than plugin's 7.4+ requirement)
**Dependency:** `guzzlehttp/guzzle ^7.9`
**License:** MIT
**Installs:** ~20,000 on Packagist

**The project uses PHP 8.0+, which is below the SDK's PHP 8.2 requirement.** The WordPress plugin (which requires PHP 7.4+) is the correct integration path — not the SDK directly.

**Source:** https://packagist.org/packages/lettermint/lettermint-php

### REST API

**Base URL:** `https://api.lettermint.co`
**Authentication:** Bearer token in Authorization header
**Format:** JSON request body

---

## 3. HTML Email, Attachments, Template Variables, Delivery Tracking

### HTML Email

**Status:** SUPPORTED. HIGH confidence.

The WordPress plugin has a "Force HTML" setting to ensure HTML emails render correctly. The plugin passes HTML body content through the Lettermint API unchanged. Existing `Content-Type: text/html; charset=UTF-8` headers in the theme's `wp_mail()` calls are preserved.

### PDF Attachments

**Status:** SUPPORTED. HIGH confidence.

The PHP SDK accepts attachments as base64-encoded file content:

```php
$fileContent = file_get_contents('/path/to/document.pdf');
$lettermint->email
    ->from('invoices@yourdomain.com')
    ->to('customer@example.com')
    ->subject('Your Invoice')
    ->html('<p>Please find your invoice attached.</p>')
    ->attach('invoice.pdf', base64_encode($fileContent))
    ->send();
```

The WordPress plugin passes through WordPress's native attachment mechanism (the `$attachments` parameter of `wp_mail()`). Attachments in `InvoiceEmailSender::send()` — which uses `wp_mail($recipient, $subject, $body, $headers, $attachments)` with a file path — will be handled by the plugin's interception layer.

**Important:** The WordPress plugin documentation does not explicitly describe how it handles the `$attachments` array (file paths) from `wp_mail()`. This needs to be verified during implementation. The likely behavior is that the plugin reads the file, base64-encodes it, and sends it to the Lettermint API — matching the SDK's pattern — but this should be tested with a real PDF attachment before production deployment.

**Confidence on attachment passthrough via plugin:** MEDIUM (official docs confirm attachments work via SDK; plugin behavior with `wp_mail()` attachments should be tested)

### Template Variables

Lettermint does **not** have its own server-side template variable system for transactional email. The theme's existing approach of doing `str_replace('{voornaam}', $first_name, $template)` in PHP before calling `wp_mail()` is correct and compatible. Variable substitution happens in PHP, and the final rendered HTML is sent to Lettermint.

**Impact:** No changes needed to the theme's variable substitution logic in `InvoiceEmailSender`, `InstallmentEmailSender`, or `VOGEmail`.

### Delivery Tracking

**Status:** SUPPORTED. HIGH confidence.

The Lettermint dashboard shows:
- Delivery statuses
- Bounce tracking
- Open tracking (Starter plan and above)
- Click tracking (Starter plan and above)
- Activity logs with subject and recipient columns

**Source:** https://lettermint.co/features/transactional-emails

---

## 4. Integration Pattern: wp_mail Transport Override vs Direct API

**Recommended pattern: Install the Lettermint WordPress plugin.**

This is the right approach for this project because:

1. **Zero code changes in theme email classes.** All five sending locations (`EmailChannel`, `InvoiceEmailSender`, `InstallmentEmailSender`, `VOGEmail`, `MentionNotifications`) continue using `wp_mail()` unchanged.

2. **Handles all edge cases automatically.** The plugin intercepts `wp_mail_from` and `wp_mail_from_name` filters, BCC headers, Reply-To headers, Content-Type headers, and attachment file paths — all of which are used across the theme's email classes.

3. **Conflict detection built in.** The plugin warns if other email delivery plugins are active.

4. **No Composer dependency needed in the theme.** The PHP SDK requires PHP 8.2+, which is above the project's PHP 8.0+ baseline. Using the plugin avoids this constraint entirely.

**How the plugin works technically:**
- Hooks into `wp_mail()` at the filter/action level
- Intercepts the normalized email parameters
- Converts them to a Lettermint API request
- Returns success/failure back to the caller

**Alternative (not recommended for this project):** Using the PHP SDK directly would require adding Guzzle as a Composer dependency and rewriting all `wp_mail()` calls in the theme to use the SDK's fluent interface. This is more work with no benefit given the existing architecture.

---

## 5. Pricing Tiers and Limitations

| Plan | Price | Emails/month | Domains | Notable Features |
|------|-------|-------------|---------|-----------------|
| Developer | Free | 300 | 1 | SMTP, REST API, SDKs, webhooks |
| Starter | €10/mo | 10,000 included | 5 | + Inbound email, open/click tracking, activity logs, unlimited projects, unlimited users |
| Growth | €13/mo | 10,000 included | 30 | Same as Starter |
| Pro | €15/mo | 10,000 included | Unlimited | + Custom tracking domains, spam insights, dedicated IPs (€50/mo add-on) |

**Overage pricing:**
- Starter: €1.50 per 1,000 additional emails
- Growth: €1.15 per 1,000 additional emails
- Pro: €1.10 per 1,000 additional emails

**Data retention:** 28 days on all plans.

**For SVAWC (sports club):** Volume is low — likely fewer than 500 transactional emails per month (invoices, installment reminders, VOG requests, mention notifications). The free Developer plan covers 300/month. The Starter plan at €10/month covers 10,000/month and adds open/click tracking.

**Recommendation:** Start with Developer plan to test. Move to Starter (€10/mo) for production — the tracking and logging features are worth it for a finance-critical email system.

**Note on pricing page data:** The fetched pricing page showed Growth at €13/mo and Pro at €15/mo which may reflect a recent pricing update. Earlier search results mentioned €45/mo and €110/mo. The lower prices are from the official pricing page (HIGH confidence), but verify on lettermint.co/pricing before signing up.

**Source:** https://lettermint.co/pricing

---

## 6. DNS Setup (SPF, DKIM, DMARC)

Lettermint requires three DNS records. These are configured in the Lettermint dashboard under Domains, then verified with a "Verify all records" button.

### Records Required

| Record Type | Host/Name | Value | Notes |
|-------------|-----------|-------|-------|
| TXT | `lettermint._domainkey.yourdomain.nl` | `v=DKIM1;k=rsa;p=<key>` | DKIM key from Lettermint dashboard |
| CNAME | `<bounce-subdomain>.yourdomain.nl` | `bounces.lmta.net` | Bounce handling — proxy off in Cloudflare |
| TXT | `_dmarc.yourdomain.nl` | `v=DMARC1; p=none; rua=mailto:<email>` | Start with p=none, harden later |

### SPF

**No SPF record changes needed on the primary domain.** Lettermint uses a subdomain (`lm-bounces.yourdomain.nl`) as the Return-Path / Envelope-From address. The CNAME bounce record points this subdomain to Lettermint's infrastructure, which manages SPF for that subdomain. The domain's existing SPF record is untouched.

### DKIM

A TXT record is added at `lettermint._domainkey.yourdomain.nl` containing the public key from the Lettermint dashboard. This enables strict DKIM alignment.

### DMARC

**Constraint:** Strict SPF alignment (`aspf=s`) cannot be used because the Envelope-From uses a subdomain. DKIM strict alignment (`adkim=s`) is possible and recommended.

Recommended DMARC progression:
1. Start: `v=DMARC1; p=none; rua=mailto:<monitoring-address>` — monitoring only
2. After confirming delivery: `v=DMARC1; p=quarantine; adkim=s; rua=mailto:<monitoring-address>`
3. Final: `v=DMARC1; p=reject; adkim=s; rua=mailto:<monitoring-address>`

### Domain Verification Process

1. Log into Lettermint dashboard
2. Navigate to Domains > Add domain
3. Enter the sending domain (e.g., `svawc.nl` or `rondo.svawc.nl`)
4. Lettermint displays the three records to create
5. Add records in DNS provider
6. Click "Verify all records" — DNS propagation takes minutes to an hour

Lettermint has step-by-step guides for Cloudflare, TransIP, Neostrada, and Domain Chief.

**Sources:**
- https://dmarc.wiki/lettermint
- https://lettermint.co/knowledge-base/guides/add-dns-records-cloudflare

---

## 7. PHP/WordPress-Specific Docs and Examples

### Official WordPress Plugin

https://wordpress.org/plugins/lettermint/

### Official PHP Guide

https://docs.lettermint.co/guides/send-email-with-php

### GitHub PHP SDK

https://github.com/lettermint/lettermint-php

### Changelog

https://lettermint.co/changelog

---

## Integration Impact Analysis for Rondo Club

### Existing email sending locations

| Class | Method | Uses Attachments | Uses BCC | Uses Custom From |
|-------|--------|-----------------|----------|-----------------|
| `InvoiceEmailSender` | `send()` | YES (PDF via file path) | YES (treasurer BCC) | YES (From header) |
| `InstallmentEmailSender` | `resolve_and_send()` | No | YES (reminder 2 only) | YES (From header) |
| `VOGEmail` | `send()` | No | No | YES (wp_mail_from filters) |
| `VOGEmail` | `send_reminder()` | No | No | YES (wp_mail_from filters) |
| `MentionNotifications` | `send_immediate_notification()` | No | No | No (uses WP default) |
| `EmailChannel` | `send()` | No | No | YES (wp_mail_from filters) |

### Plugin compatibility assessment

| Feature | Plugin Handles? | Notes |
|---------|---------------|-------|
| HTML body | YES | Via `Content-Type: text/html` header passthrough |
| Attachments (file path) | LIKELY — verify | Plugin should read file and base64-encode |
| BCC header | YES | Plugin extracts BCC from headers array |
| Custom From header | YES | Plugin reads From from headers OR its own config |
| `wp_mail_from` filter | YES | Plugin reads the filtered value |
| `wp_mail_from_name` filter | YES | Plugin reads the filtered value |
| Reply-To header | YES | Passed through in headers |
| HTML entities (`&euro;`, `&nbsp;`) | YES | Body sent as-is |

### Key risk: attachment handling via plugin

`InvoiceEmailSender::send()` passes a file path array as the 5th argument to `wp_mail()`:

```php
$attachments[] = '/path/to/invoice.pdf'; // absolute server path
$result = wp_mail($recipient, $subject, $body, $headers, $attachments);
```

The Lettermint WordPress plugin intercepts this. Based on the PHP SDK pattern (which requires base64-encoded content), the plugin must convert the file path to base64 before sending to the API. This is the expected behavior, but it should be **tested explicitly** with a real PDF attachment before deploying to production.

**Test plan for attachments:**
1. Install Lettermint plugin on staging
2. Enable logging in Settings > Lettermint
3. Trigger `InvoiceEmailSender::send()` for a test invoice
4. Verify email arrives at recipient with PDF attached
5. Check Lettermint dashboard for delivery confirmation

### No code changes needed in theme (if plugin is used)

All five email-sending classes use `wp_mail()` with standard WordPress patterns. The plugin intercepts at the WordPress level. The only action required is:

1. Install and activate the Lettermint WordPress plugin
2. Enter the API token in Settings > Lettermint
3. Configure the From email/name if desired
4. Add DNS records for the sending domain
5. Test each email type (especially invoice with PDF)

---

## Summary Recommendation

**Use the Lettermint WordPress plugin** (not the PHP SDK directly). This is the correct integration approach because:

- Zero code changes required in the theme
- Compatible with all existing `wp_mail()` patterns in the theme
- PHP version constraint (plugin: PHP 7.4+; SDK: PHP 8.2+) makes the plugin the safe choice
- European, GDPR-compliant, Netherlands-based
- Transparent interception of all WordPress email (core, plugins, theme)
- Built-in logging for debugging
- Conflict detection

**Only investigate the PHP SDK if** attachment delivery via the plugin turns out to be broken — in that case, `InvoiceEmailSender::send()` would need to be rewritten to call the SDK directly (while keeping other classes on `wp_mail()`).

---

## Confidence Assessment

| Area | Confidence | Source |
|------|------------|--------|
| Company identity and European hosting | HIGH | Official blog, european-alternatives.eu |
| WordPress plugin existence and mechanism | HIGH | WordPress.org plugin page |
| HTML email support | HIGH | Plugin docs, SDK docs |
| PDF attachment support via plugin | MEDIUM | SDK confirmed; plugin behavior with wp_mail() file paths needs testing |
| Template variable approach | HIGH | Theme does PHP-side substitution, sends rendered HTML |
| Delivery tracking | HIGH | Official features page |
| Pricing | MEDIUM | Official pricing page, but numbers should be verified at signup |
| DNS setup (SPF/DKIM/DMARC) | HIGH | dmarc.wiki, official Cloudflare guide |
| PHP version constraint (SDK 8.2+) | HIGH | Packagist page |
