# Phase 195: Installment Scheduler + Email System — Research

**Researched:** 2026-02-19
**Domain:** WordPress WP-Cron + wp_mail, PHP installment date math, email template system
**Confidence:** HIGH

---

## Summary

Phase 195 is the automation layer for the membership installment payment system. The codebase from Phases 193–194 established the data model (flat numbered post meta `_installment_N_*`), the public payment landing page, and the Mollie webhook handler. Phase 195 adds the daily cron sweeper that sends scheduled installment emails on the 25th of each month and escalates unpaid installments into overdue reminders.

The production server verified: WP-Cron fires correctly (`wp cron test` returns Success), Postmark is configured via GravitySmtp (so `wp_mail()` goes through a reliable transactional email provider), and the existing cron infrastructure (calendar sync, Google Contacts sync) demonstrates the pattern for registering daily sweepers. There is no access to real server `crontab` for the SSH user, so WP-Cron is the only option — but it is confirmed working.

There is one critical data gap: the existing `write_installment_meta()` in `PublicPaymentPage` writes `_installment_N_amount`, `_installment_N_admin_fee`, and `_installment_N_status`, but does NOT write `_installment_N_due_date`. The due date field is defined in the schema docblock in `FinanceConfig` but never populated. Phase 195 must either backfill due dates at cron sweep time or extend `write_installment_meta()` to write them at plan selection time. Writing due dates at plan selection time is strongly preferred because the sweeper can then do a simple `<= today` meta query instead of computing dates dynamically.

**Primary recommendation:** Create a new `InstallmentScheduler` class with a single `daily` WP-Cron hook. On each run, query all invoices with `_installment_plan != 'full'` in any active status (rondo_sent), iterate installments to: (1) send scheduled emails on the 25th for pending installments whose due_date is today, (2) send overdue reminder 1 at due_date + 14 days, (3) send overdue reminder 2 (with treasurer BCC) at due_date + 21 days. Add three email template option keys to `FinanceConfig`. Extend `write_installment_meta()` to persist `_installment_N_due_date`.

---

## Standard Stack

### Core (all already in project — no new dependencies needed)

| Component | Version/Pattern | Purpose | Why Standard |
|-----------|----------------|---------|--------------|
| WP-Cron (`wp_schedule_event`) | WordPress built-in | Daily sweeper registration | Confirmed working on production (wp cron test passes) |
| `wp_mail()` | WordPress built-in via Postmark/GravitySmtp | Email delivery | Postmark confirmed active; existing `InvoiceEmailSender` and `VogEmail` already use this pattern |
| `get_posts()` / `WP_Query` with `meta_query` | WordPress built-in | Query invoices with active payment plans | Pattern used in `MollieWebhook` for reverse-lookup |
| `get_post_meta()` / `update_post_meta()` | WordPress built-in | Read/write installment meta | Established by Phases 192–194 |
| `FinanceConfig` | `includes/class-finance-config.php` | Store email templates in WordPress Options | Pattern already established for `email_template`, `bcc_email`, `org_name` |

### Supporting

| Component | Version/Pattern | Purpose | When to Use |
|-----------|----------------|---------|-------------|
| `InstallmentPaymentService::create_payment()` | Phase 194 | Create fresh Mollie payment link for installment email | When sending installment email on the 25th — a fresh payment link must be created for each installment, not a stale URL |
| `RichTextEditor` React component | Project component | UI for editing email templates in Finance Settings | Already used for `email_template` in FinanceSettings.jsx email tab |
| `current_time('Y-m-d')` | WordPress built-in | Date comparison in sweeper | Use `current_time()` not `date()` — timezone-aware |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Single daily WP-Cron sweeper | Per-invoice `wp_schedule_single_event()` | Single sweeper explicitly required by STATE.md (prevents unbounded wp_options growth with hundreds of individual events) — locked decision |
| Real server crontab | WP-Cron | SSH user has no `crontab` access; WP-Cron is confirmed working and adequate |
| Generating payment links at plan selection time | Generate at email-send time | Generate at email-send time (in the sweeper). This ensures a fresh, non-expired Mollie link is included in the email. Mollie payment links for iDEAL expire after 15 minutes, for SEPA bank transfer after 12 days. Generating early means the link in the email may already be expired. |

---

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── class-installment-scheduler.php   # New: daily cron sweeper
├── class-installment-email-sender.php  # New: installment-specific email renderer
├── class-finance-config.php          # Extend: add 3 new email template options
├── class-public-payment-page.php     # Extend: write_installment_meta adds due dates
functions.php                         # Extend: instantiate InstallmentScheduler; register on cron
src/pages/Finance/FinanceSettings.jsx # Extend: add 2 more template editors to email tab
```

### Pattern 1: Daily Cron Sweeper (follow calendar sync pattern)

**What:** Register a `daily` cron event on `after_switch_theme`, process in batches per hook execution.

**When to use:** Always — WP-Cron daily is the right interval for "check what needs to happen today."

```php
// Source: existing includes/class-calendar-sync.php pattern
class InstallmentScheduler {

    const CRON_HOOK     = 'rondo_installment_sweeper';
    const LOCK_TRANSIENT = 'rondo_installment_sweeper_lock';

    public function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
        add_action( 'after_switch_theme', [ $this, 'schedule_sweeper' ] );
        add_action( 'switch_theme', [ $this, 'unschedule_sweeper' ] );
    }

    public function schedule_sweeper() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // Schedule for midnight local time daily
            wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', self::CRON_HOOK );
        }
    }

    public function unschedule_sweeper() {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
    }

    public function run_sweep() {
        // Idempotency lock: skip if already running (max 5 min run time)
        if ( get_transient( self::LOCK_TRANSIENT ) ) {
            return;
        }
        set_transient( self::LOCK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );

        try {
            $this->process_due_installments();
            $this->process_overdue_installments();
        } finally {
            delete_transient( self::LOCK_TRANSIENT );
        }
    }
}
```

### Pattern 2: Installment Due Date Calculation

**What:** Dates for `quarterly_3` (3 installments) and `monthly_8` (8 installments) are fixed Dutch standard dates.

**When to use:** Called from `write_installment_meta()` at plan selection time; stored as `_installment_N_due_date` in Y-m-d format.

```php
// Plan: quarterly_3 → installment dates per Dutch football season norms
// From SUMMARY.md research: Sep 25 (50%), Nov 25 (25%), Feb 25 (25%)
// Plan: monthly_8 → Sep 25, then Oct–Apr 25th monthly
//
// Key insight: the season year must be derived from the invoice.
// The invoice has an ACF 'season' field or the year can be derived from
// when the invoice was created. Use the membership season the invoice belongs to.

private static function calculate_due_dates( string $plan, string $season ): array {
    // $season format: "2025-2026"
    $start_year = (int) substr( $season, 0, 4 ); // e.g. 2025
    $end_year   = $start_year + 1;               // e.g. 2026

    if ( 'quarterly_3' === $plan ) {
        return [
            1 => $start_year . '-09-25',
            2 => $start_year . '-11-25',
            3 => $end_year   . '-02-25',
        ];
    }

    if ( 'monthly_8' === $plan ) {
        return [
            1 => $start_year . '-09-25',
            2 => $start_year . '-10-25',
            3 => $start_year . '-11-25',
            4 => $start_year . '-12-25',
            5 => $end_year   . '-01-25',
            6 => $end_year   . '-02-25',
            7 => $end_year   . '-03-25',
            8 => $end_year   . '-04-25',
        ];
    }

    return []; // 'full' has no installment schedule
}
```

**Important:** The `season` field must be available on the invoice. Confirm ACF field `season` exists on `rondo_invoice` (check `group_invoice_fields.json`). If not, the sweeper can derive season from the invoice's `post_date` year using `MembershipFees::get_season_key()`.

### Pattern 3: Sweeper Query Strategy

**What:** The sweeper must find invoices with active payment plans efficiently.

**Query approach:**
```php
// Query all rondo_invoice posts in rondo_sent status with a payment plan != 'full'
// Then iterate installments per invoice in PHP.
// This is more reliable than a complex meta_query on installment-N fields.

$invoices = get_posts( [
    'post_type'      => 'rondo_invoice',
    'post_status'    => 'rondo_sent',
    'posts_per_page' => -1,  // All at once — member count is ~500, not 500k
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'meta_query'     => [
        [
            'key'     => '_installment_plan',
            'value'   => 'full',
            'compare' => '!=',
        ],
    ],
] );
```

**Why not query per-installment due_dates directly:** Meta_query on `_installment_1_due_date`, `_installment_2_due_date`, etc. across N keys is not supported cleanly. The PHP iteration over all `rondo_sent` installment invoices is simpler and adequate at ~500 members.

### Pattern 4: Email Template Substitution (extend existing pattern)

**What:** The existing `InvoiceEmailSender` uses `str_replace()` with `{variable}` tokens. Installment emails need a similar mechanism.

New template variables for installment/reminder emails:
- `{naam}` — Full name (existing)
- `{voornaam}` — First name (existing)
- `{factuur_nummer}` — Invoice number (existing)
- `{termijn_nummer}` — Installment number (e.g. "2")
- `{totaal_termijnen}` — Total installments (e.g. "3" or "8")
- `{termijn_bedrag}` — Amount for this installment (e.g. "€ 45,00")
- `{betaallink}` — Fresh Mollie checkout URL for this installment
- `{vervaldatum}` — Due date formatted as "25 november 2025"
- `{organisatie_naam}` — Organization name (existing)

These are new compared to the discipline invoice template, which has `{tuchtzaken_lijst}`, `{qr_code}`, `{totaal_bedrag}`. A **separate** PHP class `InstallmentEmailSender` (not extending `InvoiceEmailSender`) should own this to avoid tangling discipline and membership email logic.

### Pattern 5: Three Email Templates in FinanceConfig

Three new WordPress options (following the pattern of `OPTION_EMAIL_TEMPLATE`):

```php
const OPTION_INSTALLMENT_EMAIL_TEMPLATE  = 'rondo_finance_installment_email_template';
const OPTION_REMINDER_1_EMAIL_TEMPLATE   = 'rondo_finance_reminder_1_email_template';
const OPTION_REMINDER_2_EMAIL_TEMPLATE   = 'rondo_finance_reminder_2_email_template';
```

Default values must be sensible Dutch-language HTML (the admin shouldn't need to write them from scratch):
- `installment_email_template` — Invoice for installment N of M, with payment link
- `reminder_1_email_template` — "Herinnering: u heeft termijn N nog niet voldaan"
- `reminder_2_email_template` — "Tweede herinnering: uw termijn is overdue" (treasurer gets BCC)

### Anti-Patterns to Avoid

- **Scheduling per-invoice events:** Never use `wp_schedule_single_event()` for individual installments. This bloats `wp_options` with hundreds of events and slows page loads. Single daily sweeper only (already locked in STATE.md).
- **Querying installment fields directly in meta_query:** Avoid `meta_query` on `_installment_1_due_date`, `_installment_2_due_date`... The WP_Query meta_query cannot do "any _installment_N_ field" matching. Query by plan != full, then iterate in PHP.
- **Reusing `InvoiceEmailSender` for installment emails:** That class is built for discipline invoices (has Datum/Wedstrijd/Kaart table, QR code, PDF attachment). Installment emails are different in structure. Create `InstallmentEmailSender` separately.
- **Generating Mollie payment links in advance at plan selection:** Links expire (iDEAL: 15 min, bank transfer: 12 days). Generate a fresh payment link when the email is sent, not before.
- **Using `date()` instead of `current_time()`:** WordPress `current_time('Y-m-d')` respects the site timezone setting. PHP `date()` uses server timezone which may differ.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Email delivery reliability | Custom SMTP adapter | `wp_mail()` via GravitySmtp/Postmark | Already configured on production; handles DKIM, SPF, bounce tracking |
| Transactional email formatting | Custom HTML builder | String-replace token template (established pattern) | Simple and sufficient; no Twig/Blade dependency needed |
| Cron scheduling reliability | Real server cron via SSH | WP-Cron (`wp_schedule_event`) | SSH user has no crontab access; WP-Cron confirmed working |
| Date arithmetic | Custom PHP date math | `strtotime()` or `DateTimeImmutable` | Simpler and well-tested |
| Concurrency protection | Custom mutex | `set_transient()` as advisory lock | Adequate for WP-Cron which rarely runs concurrently on shared hosting |

---

## Common Pitfalls

### Pitfall 1: `_installment_N_due_date` is in the schema but never written

**What goes wrong:** The `write_installment_meta()` method in `PublicPaymentPage` (line 469–486) only writes `_amount`, `_admin_fee`, and `_status`. It does NOT write `_due_date`. The sweeper cannot determine when to send emails without this field.

**Why it happens:** Phase 194 focused on the webhook state machine. Due dates were planned but not implemented.

**How to avoid:** Phase 195 MUST extend `write_installment_meta()` to also write `_installment_N_due_date` using the calculated schedule above. This is a prerequisite for everything else. Existing invoices created in Phase 193–194 testing will NOT have due dates — they will need a WP-CLI backfill command or will simply not receive automated emails (acceptable for test invoices).

**Warning signs:** Sweeper runs but sends no emails — check if `_installment_1_due_date` exists on any invoice.

### Pitfall 2: WP-Cron trigger timing on SiteGround cached hosting

**What goes wrong:** SiteGround's SuperCacher serves cached HTML without loading PHP/WordPress. WP-Cron is visitor-triggered (appended to page loads via `wp-cron.php` spawn). If all visitors are hitting cached pages, WP-Cron never fires.

**Why it happens:** SiteGround caches the homepage and most pages. Only logged-in users and REST API calls bypass cache.

**How to avoid / verified status:** `wp cron test` returned "WP-Cron spawning is working as expected." This was tested via SSH/WP-CLI which bypasses caching. The admin dashboard, REST API calls, and WP-CLI all trigger cron. Since the app has regular REST activity (every React page load calls the API), cron fires adequately. However, this is a known risk for low-traffic periods (summer). The MEMORY.md blocker note says "Verify SiteGround real server cron access before finalizing" — real server cron via crontab is NOT available (no crontab command for the SSH user), so WP-Cron is the only option. **Accept this risk** for the MVP and document it.

**Warning signs:** Sweeper shows in `wp cron event list` with a past `next_run` time — means it's scheduled but not firing.

### Pitfall 3: Mollie payment link creation fails silently during sweep

**What goes wrong:** `InstallmentPaymentService::create_payment()` returns a `WP_Error` if the Mollie API key is missing or Mollie API is down. If the sweeper silently swallows this error, no email is sent and no `_sent_at` timestamp is written, so the sweeper will try again the next day. This can be correct behavior or a loop depending on implementation.

**Why it happens:** The webhook already wraps `create_payment()` in `try/catch` and logs errors. The sweeper should do the same.

**How to avoid:** If `create_payment()` returns `WP_Error`, log with `error_log()` and skip that installment (do not mark as sent). The sweeper will retry the next day. This is acceptable because the 25th sweeper can retry on the 26th, 27th, etc. without business harm — the date check should be `<= today` not `=== today` for the initial send, or use a "sent" flag so retries only happen if not yet sent.

**Warning signs:** Same invoice appears in error log daily for 30+ days without resolution.

### Pitfall 4: Sending duplicate emails (idempotency)

**What goes wrong:** If the daily sweeper runs twice in one day (unlikely but possible if manually triggered via WP-CLI or if cron fires twice), or if the `_sent_at` timestamp is not written before `wp_mail()`, duplicate emails are sent to members.

**Why it happens:** Non-atomic read-then-write for the sent flag.

**How to avoid:** Write `_installment_N_status = 'sent'` and `_installment_N_sent_at` immediately BEFORE calling `wp_mail()`, not after. Use the transient lock to prevent concurrent sweeper runs. Check `_installment_N_status !== 'sent'` before sending.

**Warning signs:** Member reports receiving the same installment email twice.

### Pitfall 5: Reminder 2 BCC goes to wrong address

**What goes wrong:** The treasurer BCC uses the `bcc_email` from `FinanceConfig`. If this is the general BCC email (used for all invoice sends), the treasurer also receives ALL reminder 1 and initial installment emails, not just reminder 2.

**Why it happens:** The existing `bcc_email` in `FinanceConfig` is already used for discipline invoice sends. Requirements state only reminder 2 gets BCC.

**How to avoid:** In `InstallmentEmailSender`, only add `Bcc:` header when sending the second reminder. For initial installment emails and reminder 1, skip BCC. Reuse the same `bcc_email` config option (no separate option needed — the requirement is just about when BCC fires, not a different address).

### Pitfall 6: Season derivation for installment due dates

**What goes wrong:** `write_installment_meta()` needs to know which season (e.g. "2025-2026") to calculate the correct due dates. If the invoice doesn't have a season ACF field, the code must derive it from the invoice creation date.

**Why it happens:** The ACF JSON for `rondo_invoice` shows `invoice_type` and `due_date` fields — but there is no confirmed `season` field for membership invoices.

**How to avoid:** Check `group_invoice_fields.json` before implementing. If no season field exists on the invoice, derive from `get_post_field('post_date', $invoice_id)` using `MembershipFees::get_season_key()`. This is safe because membership invoices are created during the season billing window (August–September). Alternative: the invoice's `post_date` always maps to the correct season.

---

## Code Examples

### Daily Sweeper Registration (follow calendar sync exactly)

```php
// Source: includes/class-calendar-sync.php, adapted for daily
const CRON_HOOK = 'rondo_installment_sweeper';

public function __construct() {
    add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );  // if custom schedule needed
    add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
    add_action( 'after_switch_theme', [ $this, 'schedule_sweeper' ] );
    add_action( 'switch_theme', [ $this, 'unschedule_sweeper' ] );
}
```

### Installment Status Check (per-installment loop)

```php
$today = current_time( 'Y-m-d' );
$count = (int) get_post_meta( $invoice_id, '_installment_count', true );

for ( $n = 1; $n <= $count; $n++ ) {
    $status   = get_post_meta( $invoice_id, '_installment_' . $n . '_status', true );
    $due_date = get_post_meta( $invoice_id, '_installment_' . $n . '_due_date', true );

    if ( empty( $due_date ) || 'betaald' === $status || 'sent' === $status ) {
        continue; // Skip paid or already-sent
    }

    $days_overdue = ( strtotime( $today ) - strtotime( $due_date ) ) / DAY_IN_SECONDS;

    if ( 'pending' === $status && $today >= $due_date ) {
        // Initial send — due today or past due and not yet sent
        $this->send_installment_email( $invoice_id, $n );
    } elseif ( 'sent' === $status && $days_overdue >= 14 && $days_overdue < 21 ) {
        // Reminder 1 — 14 days overdue
        $sent_reminder_1 = get_post_meta( $invoice_id, '_installment_' . $n . '_reminder_1_sent_at', true );
        if ( empty( $sent_reminder_1 ) ) {
            $this->send_reminder_1( $invoice_id, $n );
        }
    } elseif ( $days_overdue >= 21 ) {
        // Reminder 2 — 21 days overdue, BCC treasurer
        $sent_reminder_2 = get_post_meta( $invoice_id, '_installment_' . $n . '_reminder_2_sent_at', true );
        if ( empty( $sent_reminder_2 ) ) {
            $this->send_reminder_2( $invoice_id, $n );
        }
    }
}
```

**Note on status values:** The schema in `FinanceConfig` docblock uses `'sent'` as a status string. However, the webhook uses `'betaald'` for paid (Dutch). Clarify: should the installment status be `'sent'` (English) after the initial email is sent? Yes — `'sent'` means "email sent, awaiting payment". `'betaald'` means "paid" (set by webhook). Keep `'pending'` → `'sent'` → `'betaald'` as the status progression. Add `'overdue'` as an optional label (but do NOT use it to gate reminder logic — use `sent_at` + day arithmetic instead, because an installment can be overdue and still have reminders sent).

### Writing Due Dates in write_installment_meta (extend Phase 194 code)

```php
// Extend PublicPaymentPage::write_installment_meta()
private function write_installment_meta( int $invoice_id, int $count, float $total, float $admin_fee ) {
    $base_amount = round( $total / $count, 2 );
    $accumulated = 0.0;

    // Calculate due dates from plan and season
    $plan     = 'quarterly_3' === $count ? 'quarterly_3' : 'monthly_8';  // caller knows plan
    $due_dates = $this->calculate_installment_due_dates( $plan, $invoice_id );

    for ( $n = 1; $n <= $count; $n++ ) {
        // ... existing amount logic ...
        update_post_meta( $invoice_id, '_installment_' . $n . '_amount', $amount );
        update_post_meta( $invoice_id, '_installment_' . $n . '_admin_fee', $admin_fee );
        update_post_meta( $invoice_id, '_installment_' . $n . '_status', 'pending' );
        // NEW: write due date
        if ( isset( $due_dates[ $n ] ) ) {
            update_post_meta( $invoice_id, '_installment_' . $n . '_due_date', $due_dates[ $n ] );
        }
    }
}
```

### Email Header with Conditional BCC

```php
// Source: existing InvoiceEmailSender::send() pattern (lines 222-234)
$headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $org_name . ' <' . $contact_email . '>',
];

// Only Reminder 2 gets BCC (REM-02 requirement)
if ( $is_reminder_2 ) {
    $bcc_email = $config->get_bcc_email();
    if ( ! empty( $bcc_email ) ) {
        $headers[] = 'Bcc: ' . $bcc_email;
    }
}
```

### FinanceConfig Extension (three new template options)

```php
// Add to FinanceConfig constants
const OPTION_INSTALLMENT_EMAIL_TEMPLATE = 'rondo_finance_installment_email_template';
const OPTION_REMINDER_1_EMAIL_TEMPLATE  = 'rondo_finance_reminder_1_email_template';
const OPTION_REMINDER_2_EMAIL_TEMPLATE  = 'rondo_finance_reminder_2_email_template';

// Defaults (sensible Dutch HTML)
const DEFAULTS = [
    // ... existing defaults ...
    'installment_email_template' => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {naam},</p><p>Hierbij ontvangt u de betalingsherinnering voor termijn {termijn_nummer} van {totaal_termijnen} van uw lidmaatschapsbijdrage (factuur {factuur_nummer}).</p><p>Bedrag: <strong>{termijn_bedrag}</strong><br/>Vervaldatum: <strong>{vervaldatum}</strong></p><p>U kunt betalen via: {betaallink}</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
    'reminder_1_email_template'  => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {naam},</p><p>Wij constateren dat termijn {termijn_nummer} van {totaal_termijnen} (factuur {factuur_nummer}, bedrag {termijn_bedrag}) nog niet is voldaan. De vervaldatum was {vervaldatum}.</p><p>Betaal zo snel mogelijk via: {betaallink}</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
    'reminder_2_email_template'  => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {naam},</p><p>Dit is onze tweede herinnering. Termijn {termijn_nummer} van {totaal_termijnen} (factuur {factuur_nummer}, bedrag {termijn_bedrag}) is al {dagen_te_laat} dagen te laat.</p><p>Neem contact met ons op als u vragen heeft, of betaal direct via: {betaallink}</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
];
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| WP-Cron visitor-triggered | WP-Cron confirmed working on SiteGround via REST/admin traffic | Verified 2026-02-19 | No real-server cron needed for MVP |
| `InvoiceEmailSender` (discipline-only) | New `InstallmentEmailSender` (membership installment-specific) | Phase 195 | Clean separation of email types |
| No installment due dates written | `write_installment_meta()` writes `_installment_N_due_date` | Phase 195 | Enables simple sweeper meta queries |

---

## Open Questions

1. **Season field on rondo_invoice post type**
   - What we know: ACF JSON for invoice shows `invoice_type` (discipline/membership) and `due_date` fields.
   - What's unclear: Is there an ACF `season` field on the invoice? Or must the sweeper derive season from post_date?
   - Recommendation: Before writing the due date calculator, check `group_invoice_fields.json` for a season field. If absent, derive from `get_post_field('post_date', $invoice_id)` using `MembershipFees::get_season_key()`. This is safe for the 2025–2026 season.

2. **Status string for "email sent, awaiting payment" installment state**
   - What we know: Schema docblock says `'sent'` (English). Webhook writes `'betaald'` (Dutch). Initial status is `'pending'`.
   - What's unclear: Is `'sent'` already written anywhere, or is this a new value being introduced in Phase 195?
   - Recommendation: Use `'sent'` consistently. The `_installment_N_status` field transitions: `pending` → `sent` (sweeper writes this after email) → `betaald` (webhook writes this after payment). No code currently writes `'sent'` — Phase 195 introduces it.

3. **What happens to invoices created during Phase 193–194 testing that lack `_installment_N_due_date`?**
   - What we know: Real production billing hasn't started yet (Phase 192–194 are infrastructure).
   - What's unclear: Are there real test invoices in production that need backfilling?
   - Recommendation: Add a WP-CLI command to backfill due dates for existing invoices with plans but no due dates. Low priority — the sweeper should simply skip invoices with missing due dates (log a warning).

4. **Whether to add `{dagen_te_laat}` (days overdue) as a template variable for Reminder 2**
   - What we know: It's referenced in the default template example above.
   - What's unclear: Is this computed value worth the complexity?
   - Recommendation: Yes — the sweeper knows `$days_overdue` when sending. Add it as a variable. Simple integer, no extra computation.

---

## Production Environment Facts (Verified 2026-02-19)

| Fact | Value | Verified By |
|------|-------|-------------|
| WP-Cron working | Yes — "WP-Cron spawning is working as expected" | `wp cron test` via SSH |
| Real server crontab | Not available — `crontab` not in PATH for SSH user | SSH command test |
| Email delivery | Postmark via GravitySmtp (active, configured) | `get_option('gravitysmtp_postmark')` |
| From email in Postmark | `stadion@svawc.nl` | GravitySmtp config |
| From name in Postmark | `AWC Stadion` | GravitySmtp config |
| `wp_mail()` test | Returned `sent` | `wp eval` via SSH |
| Existing rondo cron events | `rondo_calendar_sync` (15min), `rondo_google_contacts_sync` (15min), `rondo_rotate_debug_log` (daily) | `wp cron event list` |
| Current version | 27.1.2 | Package.json/style.css |

---

## Implementation Sequence for Planner

The planner should structure phases in this order (each independently deployable and testable):

1. **Phase 195-01: Data model extension** — Extend `write_installment_meta()` to write `_installment_N_due_date`. Verify existing Phase 194 webhook still works. WP-CLI command to backfill existing test invoices.

2. **Phase 195-02: FinanceConfig email templates** — Add three new option keys and getters/setters to `FinanceConfig`. Add corresponding REST endpoint support in the Finance Settings REST handler. Add two new `RichTextEditor` template editors to the Finance Settings "E-mail" tab in React.

3. **Phase 195-03: InstallmentEmailSender** — New PHP class that resolves person email, builds installment-specific HTML body from the three templates, sends via `wp_mail()`. No PDF, no QR code, no discipline table. Tests: send test email for a specific installment via WP-CLI.

4. **Phase 195-04: InstallmentScheduler daily cron sweeper** — New PHP class with `daily` hook. Queries `rondo_sent` invoices with non-full plans. For each: iterates installments, checks due dates and overdue thresholds, calls `InstallmentPaymentService::create_payment()` for fresh link, calls `InstallmentEmailSender`, writes `_sent_at` and status. Register in `functions.php` under the `$is_cron` block.

5. **Phase 195-05: Testing and verification** — Manual trigger of sweeper via WP-CLI (`wp eval`), verify email received, verify `_installment_1_sent_at` written, verify Mollie payment link is fresh and valid, verify 14-day and 21-day reminder logic.

---

## Sources

### Primary (HIGH confidence)
- Code inspection of `includes/class-installment-payment-service.php` — Phase 194 payment service; confirmed `_installment_N_*` meta pattern
- Code inspection of `includes/class-public-payment-page.php` (lines 469–486) — `write_installment_meta()` confirmed does NOT write `_installment_N_due_date`
- Code inspection of `includes/class-invoice-email-sender.php` — existing email sender; confirmed str_replace template pattern, `wp_mail()`, BCC pattern
- Code inspection of `includes/class-calendar-sync.php` — established WP-Cron sweeper pattern (CRON_HOOK, schedule/unschedule, run method)
- Code inspection of `includes/class-finance-config.php` — confirmed option keys pattern for template storage
- SSH `wp cron test` on production — WP-Cron confirmed working
- SSH `wp plugin status gravitysmtp` — Postmark confirmed active
- SSH `get_option('gravitysmtp_postmark')` — from address and configuration verified
- SSH `wp eval 'echo wp_mail(...)'` — email delivery confirmed working

### Secondary (MEDIUM confidence)
- `.planning/research/SUMMARY.md` — Dutch standard installment dates (Sep 25, Nov 25, Feb 25 for quarterly; Sep–Apr 25th monthly for 8-term) from prior Dutch football club research
- `.planning/research/PITFALLS.md` — Mollie link expiry timings (iDEAL: 15min, bank transfer: 12 days)

### Tertiary (LOW confidence)
- Prior research notes on SiteGround WP-Cron reliability — stated as "visitor-triggered" risk, but production test shows WP-Cron working. Accept as adequate for MVP.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components already in production and verified working
- Architecture: HIGH — patterns follow existing codebase conventions directly
- Pitfalls: HIGH — most confirmed by code inspection; WP-Cron reliability confirmed by production test
- Email delivery: HIGH — Postmark via GravitySmtp confirmed working

**Research date:** 2026-02-19
**Valid until:** 2026-03-19 (stable infrastructure; Mollie API is stable)
