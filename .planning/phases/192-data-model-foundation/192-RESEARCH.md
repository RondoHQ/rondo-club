# Research: Phase 192 — Data Model Foundation

**Phase goal:** The invoice data model supports membership fees alongside discipline cases, with a defined installment storage schema and billing configuration that makes all downstream phases safe to build on.

**Requirements in scope:** INV-03, BILL-01, BILL-04, INST-01

---

## 1. Existing Invoice CPT — What Is Already There

### Post type: `rondo_invoice`

Registered in `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-post-types.php` at `register_invoice_post_type()`. The CPT itself does **not** need to change — it already exists with the right structure.

**Custom post statuses** (all already registered):
- `rondo_draft`
- `rondo_sent`
- `rondo_paid`
- `rondo_overdue`

### ACF field group: `group_invoice_fields`

File: `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_invoice_fields.json`

Current ACF fields on `rondo_invoice`:

| ACF field name | ACF key | Type | Purpose |
|----------------|---------|------|---------|
| `invoice_number` | `field_invoice_number` | text | Auto-generated number (2026T001) |
| `person` | `field_invoice_person` | post_object → person | Member this invoice belongs to |
| `status` | `field_invoice_status` | select | draft/sent/paid/overdue |
| `line_items` | `field_invoice_line_items` | repeater | Discipline case line items with `discipline_case`, `description`, `amount` |
| `total_amount` | `field_invoice_total_amount` | number | Sum of all line items |
| `payment_link` | `field_invoice_payment_link` | url | Mollie/Rabobank checkout URL |
| `pdf_path` | `field_invoice_pdf_path` | text | Relative path to generated PDF |
| `qr_code_path` | `field_invoice_qr_code_path` | text | Relative path to QR code PNG |
| `sent_date` | `field_invoice_sent_date` | date_picker | Date invoice was sent |
| `due_date` | `field_invoice_due_date` | date_picker | Payment due date |

**There is no `invoice_type` field yet.** This is the central gap for INV-03.

### Post meta (stored as flat meta, NOT via ACF):

- `_mollie_payment_id` — Mollie payment ID (`tr_xxx`) for the full invoice payment. Used by `MollieWebhook` for O(1) lookup via `WP_Query meta_query`.
- `_rabobank_payment_request_id` — Rabobank payment request ID.
- `invoice_number` — also stored as plain post meta (ACF stores both meta and serialized; invoice numbering service reads it via `get_post_meta`).
- `sent_date`, `due_date` — also read directly via `get_post_meta` in some places.

---

## 2. What INV-03 Requires: The `invoice_type` Field

**Requirement:** Invoices have a type field distinguishing discipline vs membership fee invoices.

### Implementation decision: ACF field in the existing field group

The `invoice_type` should be an ACF `select` field added to `group_invoice_fields.json`. This follows the established pattern (all invoice fields are ACF fields).

**Proposed ACF field:**

```json
{
    "key": "field_invoice_type",
    "label": "Factuurtype",
    "name": "invoice_type",
    "type": "select",
    "instructions": "Type factuur: tuchtzaak of lidmaatschapsbijdrage",
    "required": 0,
    "choices": {
        "discipline": "Tuchtzaak",
        "membership": "Lidmaatschapsbijdrage"
    },
    "default_value": "discipline",
    "allow_null": 1,
    "wrapper": {
        "width": "33",
        "class": "",
        "id": ""
    }
}
```

**Why allow_null?** During the backfill window (between deploy and WP-CLI run), existing invoices will have no value. Setting `allow_null: 1` and `default_value: discipline` means they display correctly in admin even before backfill. Code that reads `invoice_type` must treat `null` or empty as `discipline`.

### Backfill approach

The success criterion states: "Existing discipline invoices are backfilled with `invoice_type = discipline`."

Two implementation options:

**Option A: WP-CLI command** — Add a `wp prm invoices backfill_type` command to `class-wp-cli.php` that iterates all `rondo_invoice` posts and calls `update_field('invoice_type', 'discipline', $invoice_id)` if the field is empty. This is the precedent in the codebase (see `backfill_volunteer_status` around line 2010 of `class-wp-cli.php`).

**Option B: `after_setup_theme` hook with a one-time option flag** — Runs automatically on first deploy. Use the pattern from `rondo_migrate_options()`: check a flag option, run the migration, set the flag. Risk: could be slow on large datasets during a page load.

**Recommendation: Option A (WP-CLI)** for safety. Discipline invoice counts are manageable (not thousands). The command can be run after deploy with `wp prm invoices backfill_type`. Follows existing codebase convention.

### Reading `invoice_type` in downstream code

Callers must handle the null/empty case:

```php
$invoice_type = get_field('invoice_type', $invoice_id) ?: 'discipline';
```

This ensures all existing code paths treat existing invoices as `discipline` without any data change required immediately.

---

## 3. What BILL-01 Requires: Per-Season Billing Method Toggle

**Requirement:** Admin can toggle per-season billing method between Nikki (external) and Rondo invoicing.

### Where to store it

From the feature research (`FEATURES.md`): "Store `billing_method` per season key in WordPress options. Values: `nikki` (default) or `rondo`."

**Option key pattern** (consistent with existing `MembershipFees` season options):
```php
'rondo_billing_method_' . $season  // e.g., 'rondo_billing_method_2025-2026'
```

This follows the pattern established by `MembershipFees::get_option_key_for_season()`.

### Which class owns this?

Three candidates:

1. **`MembershipFees`** — Already handles all per-season membership fee settings. This is a per-season setting about how membership fees are billed. Natural home.

2. **`FinanceConfig`** — Handles finance-wide settings but is season-agnostic. Adding a season-keyed setting here would be inconsistent with its design.

3. **New `BillingConfig` class** — Clean separation but overkill for v1.

**Recommendation: Add to `MembershipFees`** — add `get_billing_method($season)` and `set_billing_method($season, $method)` methods. The billing method is fundamentally "how are we billing fees this season?" — it belongs with the fee configuration.

### Values

- `nikki` — external system (default, preserves existing behavior)
- `rondo` — Rondo invoicing is active for this season

Reading code should treat missing/null as `nikki`.

---

## 4. What BILL-04 Requires: Per-Installment Administration Fee

**Requirement:** Admin can configure per-installment administration fee amount.

### Existing `admin_fee` in `FinanceConfig`

`FinanceConfig` **already has** `OPTION_ADMIN_FEE = 'rondo_finance_admin_fee'` and `get_admin_fee(): float`. This is the administration fee injected as a line item on discipline case invoices when a discipline invoice is created.

**IMPORTANT: This is a different concept than the new per-installment fee.**

| Fee | Context | Storage | Existing? |
|-----|---------|---------|-----------|
| Invoice admin fee | Discipline invoices only — one fee per invoice, injected at invoice creation | `rondo_finance_admin_fee` | YES (Phase 191) |
| Installment admin fee | Membership invoices only — one fee per installment (not per invoice) | New option needed | NO |

### What needs to be added for BILL-04

A new option in `FinanceConfig` for the per-installment administration fee:

```php
const OPTION_INSTALLMENT_ADMIN_FEE = 'rondo_finance_installment_admin_fee';
```

Methods needed:
- `get_installment_admin_fee(): float` — returns the configured per-installment fee (default 0.00)
- `update_settings()` must handle `installment_admin_fee` in the data array

This value is a snapshot concern: when a member selects their payment plan (Phase 193), the fee amount at that point in time is stored in the installment schedule on the invoice so it doesn't change if admin later edits the setting.

---

## 5. What INST-01 Requires: Installment Storage Schema

**Requirement:** Each installment has tracked amount, status, due date, and Mollie payment ID.

### Prior decision: Flat numbered post meta

From the phase description: "Flat numbered post meta for installments (`_installment_N_*`) — not ACF repeater, not separate CPT."

This decision was made because:
- ACF repeaters serialize the whole array — replacing one item requires reading and writing the entire array, which is error-prone under concurrent writes from cron and webhooks
- Separate CPT adds overhead for what is a bounded set of records (max 8 per invoice)
- Flat meta allows O(1) update of a single installment by number without reading others

### Proposed meta key schema

For an invoice with `$n` installments (N = 1-based integer):

| Meta key | Value | Notes |
|----------|-------|-------|
| `_installment_count` | `8` | Total number of installments for this invoice |
| `_installment_plan` | `monthly_8` | Plan type: `full`, `quarterly_3`, or `monthly_8` |
| `_installment_1_amount` | `53.50` | Float: total amount due for installment 1 |
| `_installment_1_admin_fee` | `2.50` | Float: admin fee portion of amount |
| `_installment_1_status` | `pending` | One of: `pending`, `sent`, `paid`, `overdue` |
| `_installment_1_due_date` | `2025-09-25` | ISO date string (Y-m-d) |
| `_installment_1_sent_at` | `2025-09-25 09:03:22` | DateTime when installment email was sent (nullable) |
| `_installment_1_paid_at` | `2025-09-26 14:22:11` | DateTime when payment confirmed (nullable) |
| `_installment_1_mollie_payment_id` | `tr_abc123` | Mollie payment ID for this installment (nullable until link created) |
| `_installment_1_payment_link` | `https://...` | Mollie checkout URL for this installment (nullable) |

**Reading a single installment:**
```php
$amount   = (float) get_post_meta($invoice_id, '_installment_1_amount', true);
$status   = get_post_meta($invoice_id, '_installment_1_status', true) ?: 'pending';
```

**Updating a single installment field:**
```php
update_post_meta($invoice_id, '_installment_1_status', 'paid');
update_post_meta($invoice_id, '_installment_1_paid_at', current_time('Y-m-d H:i:s'));
```

**Reading all installments for an invoice:**
```php
$count = (int) get_post_meta($invoice_id, '_installment_count', true);
$installments = [];
for ($n = 1; $n <= $count; $n++) {
    $installments[$n] = [
        'amount'             => (float) get_post_meta($invoice_id, "_installment_{$n}_amount", true),
        'admin_fee'          => (float) get_post_meta($invoice_id, "_installment_{$n}_admin_fee", true),
        'status'             => get_post_meta($invoice_id, "_installment_{$n}_status", true) ?: 'pending',
        'due_date'           => get_post_meta($invoice_id, "_installment_{$n}_due_date", true),
        'sent_at'            => get_post_meta($invoice_id, "_installment_{$n}_sent_at", true) ?: null,
        'paid_at'            => get_post_meta($invoice_id, "_installment_{$n}_paid_at", true) ?: null,
        'mollie_payment_id'  => get_post_meta($invoice_id, "_installment_{$n}_mollie_payment_id", true) ?: null,
        'payment_link'       => get_post_meta($invoice_id, "_installment_{$n}_payment_link", true) ?: null,
    ];
}
```

### Phase 192's scope for INST-01

Phase 192 does **not** need to store actual installment data — no invoices exist yet. The task is to:
1. Define and document the meta key schema (the table above)
2. Possibly create a helper class or static method for reading/writing installments consistently — so Phases 193-195 all use the same code, not hand-written meta keys scattered across files

A `Rondo\Finance\InstallmentStorage` class (or static methods on a new class) would be the DRY approach. Alternatively, if the schema is well-documented here and in comments, the helper can be deferred to Phase 194 (Payment Plan Manager) when it's actually first used.

**Recommendation: Document the schema in Phase 192, defer the helper class to Phase 194.** Phase 192's deliverable is the schema definition, not the implementation of all installment helpers.

---

## 6. The Reverse-Lookup Key Pattern (INST-02 preview, must be defined in Phase 192)

**From success criteria SC4:** "The reverse-lookup key pattern (`_mollie_pid_{payment_id} = installment_number`) is defined and documented before any payment IDs are stored."

### Why this matters

When Mollie calls the webhook with a payment ID like `tr_abc123`, the webhook handler must find:
1. Which invoice this payment belongs to
2. Which installment number within that invoice

For full-payment invoices (existing behavior), the current `MollieWebhook` does:
```php
WP_Query with meta_query: ['key' => '_mollie_payment_id', 'value' => $payment_id]
```
This works because there's one payment per invoice.

For installment invoices, the payment is `_installment_N_mollie_payment_id` — so querying by `_mollie_payment_id` won't find it. The webhook needs a reverse-lookup.

### The reverse-lookup meta pattern

When storing a Mollie payment ID for an installment, also store a reverse-lookup key:

```php
// When creating Mollie payment for installment N:
update_post_meta($invoice_id, "_installment_{$n}_mollie_payment_id", $payment_id);
update_post_meta($invoice_id, "_mollie_pid_{$payment_id}", $n);  // reverse lookup
```

**Lookup in webhook:**
```php
// 1. Try existing full-invoice lookup
$query = new WP_Query([
    'post_type'  => 'rondo_invoice',
    'post_status' => 'any',
    'meta_query' => [['key' => '_mollie_payment_id', 'value' => $payment_id]],
]);

// 2. If not found, try reverse-lookup for installment
if (!$query->have_posts()) {
    $query = new WP_Query([
        'post_type'  => 'rondo_invoice',
        'post_status' => 'any',
        'meta_query' => [['key' => "_mollie_pid_{$payment_id}", 'compare' => 'EXISTS']],
    ]);
    if ($query->have_posts()) {
        $invoice = $query->posts[0];
        $installment_number = (int) get_post_meta($invoice->ID, "_mollie_pid_{$payment_id}", true);
        // → mark installment $installment_number as paid
    }
}
```

**O(1) lookup:** WordPress meta queries with `EXISTS` on an indexed meta key are efficient. No need to scan all installments.

**Phase 192 task:** Define this pattern. Implement it when creating Mollie payment links for installments in Phase 193/194 — not in Phase 192 itself (no installments exist yet).

---

## 7. `FinanceConfig` — Current State and What Changes

Current `FinanceConfig` (`/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php`):

**Existing options (no changes needed):**
- `OPTION_ADMIN_FEE` / `get_admin_fee()` — per-invoice fee for discipline invoices (Phase 191)
- `get_all_settings()` — returns all settings for the Finance Settings UI REST endpoint
- `update_settings()` — handles all settings updates in one call

**What Phase 192 must add to `FinanceConfig`:**

```php
const OPTION_INSTALLMENT_ADMIN_FEE = 'rondo_finance_installment_admin_fee';
```

New methods:
```php
public function get_installment_admin_fee(): float {
    return (float) get_option(self::OPTION_INSTALLMENT_ADMIN_FEE, 0.00);
}
```

`get_all_settings()` must be extended to include `installment_admin_fee`.

`update_settings()` must handle `installment_admin_fee` in the data array.

**Note:** The UI for editing this setting is NOT in Phase 192 (that's Phase 196 or 195). Phase 192 just adds the backend storage and getters. The Finance Settings page REST endpoint will return the value, but the field won't appear in the UI yet.

---

## 8. `MembershipFees` — What Changes for BILL-01

Current `MembershipFees` (`/Users/joostdevalk/Code/rondo/rondo-club/includes/class-membership-fees.php`):

Already has `get_season_key()`, `get_option_key_for_season()`, `get_categories_for_season()`, etc.

**What Phase 192 must add:**

```php
/**
 * Get the billing method for a season.
 *
 * @param string|null $season Season key (e.g., "2025-2026"). Defaults to current season.
 * @return string 'nikki' or 'rondo'. Defaults to 'nikki' if not set.
 */
public function get_billing_method(?string $season = null): string {
    $season = $season ?? $this->get_season_key();
    return get_option('rondo_billing_method_' . $season, 'nikki');
}

/**
 * Set the billing method for a season.
 *
 * @param string      $method 'nikki' or 'rondo'.
 * @param string|null $season Season key. Defaults to current season.
 * @return bool True on success.
 */
public function set_billing_method(string $method, ?string $season = null): bool {
    $season = $season ?? $this->get_season_key();
    if (!in_array($method, ['nikki', 'rondo'], true)) {
        return false;
    }
    return update_option('rondo_billing_method_' . $season, $method);
}
```

**Option key:** `rondo_billing_method_2025-2026` (season-keyed, consistent with `rondo_membership_fees_2025-2026`).

---

## 9. REST API — What Changes in Phase 192

Phase 192 is backend-only with no new visible UI. However, the Finance Settings REST endpoint must return the new `installment_admin_fee` value.

**`/rondo/v1/settings` GET response** — need to confirm where this is handled. The Finance Settings page reads from a settings endpoint. Based on `FinanceConfig::get_all_settings()` being called in the REST controller, extending that method is the right approach.

The billing method toggle (`BILL-01`) is per-season, so it belongs in a membership fees settings endpoint, not the general finance settings. The REST endpoint for membership fee settings is at `/rondo/v1/membership-fees` (or similar — needs verification of the exact endpoint). Phase 192 does not need to add the REST endpoint for billing method; that's Phase 196 (Bulk Invoice Creation) when the Contributie page uses it.

**Phase 192 REST scope:**
- Extend `FinanceConfig::get_all_settings()` to include `installment_admin_fee`
- Extend `FinanceConfig::update_settings()` to accept `installment_admin_fee`
- No new routes needed

---

## 10. WP-CLI Backfill Command

The `class-wp-cli.php` file contains the WP-CLI command structure. The existing `backfill_volunteer_status` command (around line 2010) is the pattern to follow.

**New command:** `wp prm invoices backfill_type`

This command:
1. Queries all `rondo_invoice` posts (any status)
2. For each, checks if `invoice_type` ACF field is empty
3. If empty, sets `update_field('invoice_type', 'discipline', $invoice_id)`
4. Reports count of updated vs already-set vs skipped

**Command registration** follows the same pattern as other WP-CLI commands in that file.

---

## 11. functions.php — Class Loading

Phase 192 does not add new classes. If a `InstallmentStorage` helper is added, it would need:
- A `use` statement at the top
- Loading in `rondo_init()` under the appropriate condition (REST-only or always)

But since Phase 192 defers the helper class to Phase 194, no `functions.php` changes are expected in this phase.

---

## 12. ACF JSON File — Update Procedure

The ACF field group JSON is at:
`/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_invoice_fields.json`

Phase 192 must **add** the `invoice_type` field to the `fields` array in this JSON file. The `modified` timestamp and `active` flag should be updated.

**Important:** ACF reads the JSON file and stores the group in the database on first use. Editing the JSON file directly is the correct approach (as stated in `CLAUDE.md`: "ACF field groups are stored as JSON in `acf-json/` for version control"). After deployment, ACF will detect the JSON is newer than the DB version and prompt to sync (or auto-sync if `WP_DEBUG` is true).

---

## 13. Existing Code That Touches `invoice_type`

**Result: none found.** A search for `invoice_type` across `includes/` returned no matches in PHP files. The field does not exist in any current class.

A search for `invoice_type` across the planning files shows it mentioned in:
- `REQUIREMENTS.md` (requirement INV-03)
- `FEATURES.md` (implementation notes)
- `ROADMAP.md` (phase description)

This confirms the field is entirely new — no existing code paths will break when it is added, and no code currently writes `invoice_type` to the database.

---

## 14. Downstream Code Safety Analysis

### Existing `RestInvoices::create_invoice()` — will it break?

Current code creates a discipline invoice without setting `invoice_type`. After Phase 192 ships:
- The ACF field has `default_value: discipline`
- Existing creation code does NOT need to be updated to set the field (ACF will use the default)
- However, the plan is to explicitly set the field for clarity. But this is a Phase 196 task (membership invoice creation) — discipline invoice creation can stay as-is since the default covers it.

Actually: when an ACF select field with a `default_value` is registered but not explicitly set during `wp_insert_post`, ACF does NOT automatically write the default to the database — the field is just absent from post meta. The `default_value` only applies when reading via `get_field()`. So existing discipline invoices will have no meta for `invoice_type` but `get_field('invoice_type', $id)` will return `discipline` (the default).

This means the backfill is optional for correctness (reading always returns the right default) but required for querying (a `meta_query` for `invoice_type = 'discipline'` will NOT match invoices where the meta key doesn't exist). The Facturen list filter (Phase 197) will use `meta_query`, so backfill is necessary for filters to work correctly.

### Existing `MollieWebhook::handle_webhook()` — will it break?

The existing webhook finds invoices by `_mollie_payment_id`. Adding `invoice_type` changes nothing about this lookup. No changes needed in Phase 192.

---

## 15. Key Decisions for the Plan

1. **`invoice_type` as ACF select field** (not raw post meta) — consistent with all other invoice fields, enables admin-visible display in WP admin.

2. **Backfill via WP-CLI command** — safe, explicit, follows codebase precedent.

3. **`billing_method` stored in `MembershipFees`** — per-season, consistent with existing fee option pattern. NOT in `FinanceConfig` (which is season-agnostic).

4. **`installment_admin_fee` in `FinanceConfig`** — season-agnostic setting (same fee applies to all seasons unless admin changes it), consistent with `admin_fee` already there.

5. **Flat numbered post meta for installments** — `_installment_N_*` keys. Not ACF repeater, not CPT. Allows O(1) updates per installment.

6. **Reverse-lookup meta** — `_mollie_pid_{payment_id} = installment_number` stored on invoice when Mollie payment is created. Written in Phase 193 (payment link creation), but defined here.

7. **No `InstallmentStorage` helper class in Phase 192** — schema is defined in this phase, implementation deferred to Phase 194 when first actually used.

8. **No REST endpoint changes for billing method in Phase 192** — that's Phase 196.

9. **`allow_null: 1` on `invoice_type` ACF field** — code that reads must treat null/empty as `discipline`.

---

## 16. Files That Will Change in Phase 192

| File | Change |
|------|--------|
| `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_invoice_fields.json` | Add `invoice_type` select field to `fields` array |
| `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php` | Add `OPTION_INSTALLMENT_ADMIN_FEE`, `get_installment_admin_fee()`, extend `get_all_settings()` and `update_settings()` |
| `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-membership-fees.php` | Add `get_billing_method()`, `set_billing_method()` |
| `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-wp-cli.php` | Add `wp prm invoices backfill_type` command |

**No changes expected to:**
- `functions.php`
- `class-rest-invoices.php`
- `class-mollie-webhook.php`
- `class-mollie-payment.php`
- `class-post-types.php`
- Any React frontend files

---

## 17. Testing the Success Criteria

**SC1 — Existing discipline invoices backfilled:**
- Run `wp prm invoices backfill_type` on production after deploy
- Verify via `wp post meta get <invoice_id> invoice_type` returns `discipline`
- Check that `RestInvoices::get_invoice()` still returns correct invoice data

**SC2 — Membership invoice can be created:**
- Create a `rondo_invoice` post manually (via WP-CLI or API) with `invoice_type = membership`
- Verify `get_field('invoice_type', $id)` returns `membership`
- Verify no existing code paths are affected

**SC3 — Installment data can be stored and read:**
- Use `update_post_meta($id, '_installment_1_amount', 53.50)` etc.
- Read back via `get_post_meta($id, '_installment_1_amount', true)`
- Confirm schema works as designed (no class needed for this test)

**SC4 — Reverse-lookup key defined:**
- Document verified in this file and in code comments in `class-mollie-webhook.php`
- No actual meta keys need to be written in Phase 192 (no installments exist yet)

**SC5 — Installment admin fee in FinanceConfig:**
- Call `$config->get_installment_admin_fee()` → returns `0.00` (default)
- Call `$config->update_settings(['installment_admin_fee' => 2.50])` → returns true
- Call `get_installment_admin_fee()` → returns `2.50`
- Verify Finance Settings REST endpoint includes `installment_admin_fee` in response

---

*Research by: Claude Code*
*Phase: 192 — Data Model Foundation*
*Date: 2026-02-18*
