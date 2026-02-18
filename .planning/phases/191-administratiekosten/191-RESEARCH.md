# Phase 191: Administratiekosten - Research

**Researched:** 2026-02-18
**Domain:** Invoice system (PHP backend + React frontend)
**Confidence:** HIGH

## Summary

The administration fee (administratiekosten) is a fixed, club-configurable charge added as a single extra line item on every discipline-based invoice. It must be stored in `FinanceConfig` via the WordPress Options API (one setting: `admin_fee`), surfaced in the Finance Settings UI on the "Betaling" tab, and injected into the `line_items` repeater during invoice creation in `create_invoice()` on the backend.

The line items repeater on the `rondo_invoice` CPT already supports non-discipline line items through the `discipline_case` field being nullable (the ACF field has `required: 1` but the REST create endpoint stores `null` via `$item['discipline_case_id'] ?? null`). The email sender already handles non-discipline rows with a `colspan=3` fallback; the PDF generator has an `else` branch that renders the plain `description`. The admin fee row slots cleanly into both output paths with no structural changes required.

One important nuance: the frontend `handleCreateInvoice()` in `PersonDetail.jsx` sums amounts purely from `dc.acf.administrative_fee` (per discipline case). It must also add the admin fee as an additional line item in the `line_items` array it sends to the API, OR the backend can inject it server-side during `create_invoice()`. Either approach works; server-side injection is strongly preferred for consistency (the amount is always current, can't be tampered, and the frontend doesn't need to know about it separately).

**Primary recommendation:** Add `admin_fee` to `FinanceConfig`, expose it in the Finance Settings UI, and inject the admin fee line item server-side in `create_invoice()` when the fee is > 0. No changes to the PDF generator or email sender HTML structure are needed — both already have the correct fallback rendering for non-discipline line items.

## Standard Stack

### Core
| Component | Location | Purpose |
|-----------|----------|---------|
| FinanceConfig | `includes/class-finance-config.php` | WordPress Options API wrapper for all finance settings |
| REST API (finance settings) | `includes/class-rest-api.php` L727-767 | `/rondo/v1/finance/settings` GET/POST |
| REST API (invoices) | `includes/class-rest-invoices.php` | `create_invoice()` builds line_items and totals |
| InvoicePdfGenerator | `includes/class-invoice-pdf-generator.php` | Renders `line_items` to PDF HTML |
| InvoiceEmailSender | `includes/class-invoice-email-sender.php` | Renders `line_items` to `{tuchtzaken_lijst}` HTML |
| FinanceSettings.jsx | `src/pages/Finance/FinanceSettings.jsx` | Admin UI with tabbed form (Organization, Payment, Email, Rabobank, Mollie) |

### Supporting
| Component | Location | Purpose |
|-----------|----------|---------|
| useFinanceSettings | `src/hooks/useFinanceSettings.js` | TanStack Query hook for finance settings |
| DisciplineCaseTable | `src/components/DisciplineCaseTable.jsx` | Shows discipline cases + invoice creation toolbar |
| useCreateInvoice | `src/hooks/useInvoices.js` | Mutation hook; POSTs to `/rondo/v1/invoices` |

## Architecture Patterns

### Pattern 1: Adding a new FinanceConfig option

All finance settings follow this exact pattern:

**PHP — `class-finance-config.php`:**
1. Add a `const OPTION_ADMIN_FEE = 'rondo_finance_admin_fee';` constant
2. Add `'admin_fee' => 0.00` to `DEFAULTS`
3. Add getter `get_admin_fee(): float { return (float) get_option(self::OPTION_ADMIN_FEE, self::DEFAULTS['admin_fee']); }`
4. Add to `get_all_settings()` return array: `'admin_fee' => $this->get_admin_fee()`
5. Add to `update_settings()`: `if (isset($data['admin_fee'])) { update_option(self::OPTION_ADMIN_FEE, (float)$data['admin_fee']); }`
6. Add to `get_setting()` switch-case

**REST API — `class-rest-api.php`:**
- Add `'admin_fee' => ['required' => false, 'type' => 'number']` to the args array at L741

**Frontend — `FinanceSettings.jsx`:**
- Add `admin_fee: 0` to `formData` initial state
- Add `admin_fee: settings.admin_fee || 0` in the `useEffect` loader
- Add `admin_fee: parseFloat(formData.admin_fee)` to the save payload
- Add a number input field in the "Betaling" tab (after betalingstermijn)

**Frontend — `useFinanceSettings.js`:**
No changes needed — the hook passes all settings through transparently.

### Pattern 2: Injecting admin fee server-side in create_invoice()

The ACF `line_items` repeater already supports rows without a `discipline_case` (the field stores `null` when `discipline_case_id` is not provided in the REST payload). The email and PDF renderers already have correct fallback paths:

**Email sender** (`class-invoice-email-sender.php` L142-149):
```php
} elseif ( ! empty( $item['description'] ) ) {
    // Fallback row for non-discipline items: description spans first 3 columns
    $table_rows[] = '<tr ...>'
        . '<td colspan="3" ...>' . esc_html( $item['description'] ) . '</td>'
        . '<td ...>' . $formatted_amount . '</td>'
        . '</tr>';
}
```

**PDF generator** (`class-invoice-pdf-generator.php` L288-293):
```php
} else {
    $description = $item['description'] ?? '';
}
```
The PDF already renders description in the first column, empty card/suspension columns, and amount in the last column — exactly correct for an admin fee row.

**Server-side injection pattern in `create_invoice()`:**
```php
// After building $rows from $line_items...
$finance_config = new FinanceConfig();
$admin_fee = $finance_config->get_admin_fee();
if ( $admin_fee > 0 ) {
    $rows[] = [
        'discipline_case' => null,
        'description'     => 'Administratiekosten',
        'amount'          => $admin_fee,
    ];
    $total_amount += $admin_fee;
}
```

Note: the `discipline_case` sub_field in the ACF JSON has `"required": 1` and `"allow_null": 0`. This is for the WP Admin UI only; the REST API bypasses ACF field validation when calling `update_field()` programmatically with `null`. Confirmed by the existing pattern where `discipline_case_id` is optional in the REST payload and already stored as `null`.

### Pattern 3: Total amount recalculation

`total_amount` is stored as a separate ACF field and calculated from line items during creation. Adding the admin fee amount to `$total_amount` before `update_field('total_amount', ...)` is all that's needed. No changes to `format_invoice()` or `format_invoice_detail()` — they read `total_amount` from the stored field.

### Anti-Patterns to Avoid

- **Modifying the ACF JSON to make `discipline_case` nullable:** Not needed. The existing `allow_null: 0` in the ACF JSON only affects the WP Admin editor, not programmatic `update_field()` calls.
- **Frontend-side admin fee injection:** Don't add admin fee logic to `handleCreateInvoice()` in `PersonDetail.jsx`. The backend is the single source of truth for the fee amount, and frontend-side injection could be bypassed or drift out of sync.
- **Adding a separate admin fee ACF field to the invoice:** The existing `line_items` repeater already handles this as a regular row. Do not create a parallel data structure.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Settings storage | Custom DB table or serialized meta | WordPress Options API via `get_option`/`update_option` (already used by FinanceConfig) |
| Settings UI form | Custom form state management | Existing FinanceSettings.jsx pattern (controlled inputs + payload in handleSubmit) |
| Number formatting | Custom currency formatter | `formatCurrency()` from `src/utils/formatters.js` already used throughout |

## Common Pitfalls

### Pitfall 1: ACF `discipline_case` required field
**What goes wrong:** The `field_invoice_line_discipline_case` sub-field in `acf-json/group_invoice_fields.json` has `"required": 1` and `"allow_null": 0`. A developer might assume `update_field('line_items', [...null...])` will fail validation.
**Why it happens:** ACF required validation runs in the WP admin UI, not programmatically.
**How to avoid:** Call `update_field('line_items', $rows, $post_id)` directly. Null discipline_case values are already stored this way in the codebase (the REST endpoint always does `$item['discipline_case_id'] ?? null`).
**Warning signs:** Don't pre-validate the rows array server-side.

### Pitfall 2: Total amount drift
**What goes wrong:** Admin fee is injected into `$rows` but forgotten in `$total_amount` accumulation.
**Why it happens:** Total is calculated in a separate loop before rows are built.
**How to avoid:** Add admin fee to `$total_amount` in the same block where the fee row is pushed to `$rows`.

### Pitfall 3: Admin fee shown for 0-value configs
**What goes wrong:** When `admin_fee` is 0 (default/not configured), an empty "Administratiekosten" line item with €0.00 appears on invoices.
**How to avoid:** Gate injection on `if ($admin_fee > 0)`.

### Pitfall 4: Finance Settings form state
**What goes wrong:** New `admin_fee` field in formData not initialized in the `useEffect` that loads settings, causing the field to remain stale on page load.
**How to avoid:** Always add the new field to all three places in FinanceSettings.jsx: initial state, `useEffect` loader, and save payload.

### Pitfall 5: REST API args missing the new field
**What goes wrong:** `update_settings()` in FinanceConfig correctly handles `admin_fee`, but the REST route args in `class-rest-api.php` don't declare it, so WordPress strips it before the callback runs.
**How to avoid:** Add `'admin_fee' => ['required' => false, 'type' => 'number']` to the args array in the `update_finance_settings` route registration.

## Code Examples

### Adding the option to FinanceConfig
```php
// In class-finance-config.php

const OPTION_ADMIN_FEE = 'rondo_finance_admin_fee';

const DEFAULTS = [
    // ... existing defaults ...
    'admin_fee' => 0.00,
];

public function get_admin_fee(): float {
    return (float) get_option( self::OPTION_ADMIN_FEE, self::DEFAULTS['admin_fee'] );
}

// In get_all_settings():
'admin_fee' => $this->get_admin_fee(),

// In update_settings():
if ( isset( $data['admin_fee'] ) ) {
    $fee = max( 0.0, (float) $data['admin_fee'] );
    update_option( self::OPTION_ADMIN_FEE, $fee );
}
```

### Injecting admin fee in create_invoice()
```php
// In class-rest-invoices.php, create_invoice()

// After $rows[] = [...] loop:
$finance_config = new FinanceConfig();
$admin_fee = $finance_config->get_admin_fee();
if ( $admin_fee > 0 ) {
    $rows[] = [
        'discipline_case' => null,
        'description'     => 'Administratiekosten',
        'amount'          => $admin_fee,
    ];
    $total_amount += $admin_fee;
}
```

### Finance Settings UI input (in the Betaling tab)
```jsx
<div>
  <label htmlFor="admin_fee" className="block text-sm font-medium ...">
    Administratiekosten
  </label>
  <div className="relative">
    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">€</span>
    <input
      type="number"
      id="admin_fee"
      value={formData.admin_fee}
      onChange={(e) => setFormData(prev => ({ ...prev, admin_fee: e.target.value }))}
      min="0"
      step="0.01"
      className="w-full pl-7 pr-3 py-2 border ..."
    />
  </div>
  <p className="mt-1 text-xs text-gray-500">
    Vaste administratiekosten per factuur. Wordt automatisch als aparte regelpost toegevoegd. Gebruik 0 om uit te schakelen.
  </p>
</div>
```

## Scope Boundaries

The following are NOT in scope for this phase (no changes needed):

- **PDF generator HTML structure:** The existing 4-column table (Omschrijving, Kaart, Schorsing, Bedrag) already renders admin-fee rows correctly via the `else` branch. The kaart and schorsing columns render empty for non-discipline rows.
- **Email sender HTML structure:** The `colspan=3` fallback row already handles non-discipline line items correctly.
- **`format_invoice_detail()` response format:** Already includes all line items with `discipline_case: null` for non-discipline rows — matches the admin fee row shape.
- **ACF JSON files:** No changes required. The `discipline_case` required/allow_null constraint is UI-only.
- **Invoice numbering:** Unchanged.
- **Delete invoice / discipline case reset logic:** The admin fee row has `discipline_case: null`, so the existing `if (!empty($item['discipline_case']))` reset loop in `delete_invoice()` and `reset_payment_state()` already skips it correctly.

## Open Questions

1. **Label for admin fee row**
   - What we know: "Administratiekosten" is the natural Dutch label
   - What's unclear: Should this be configurable (a text field alongside the amount) or always hardcoded as "Administratiekosten"?
   - Recommendation: Hardcode "Administratiekosten" as the description. A configurable label adds complexity without clear benefit. If the user wants a different label in the future, it's a simple text change.

2. **Per-invoice vs global admin fee**
   - What we know: Phase description says "configurable administration fee" — one global value
   - What's unclear: Whether different invoice amounts could need different fees
   - Recommendation: One global fee in FinanceConfig. If per-invoice override is ever needed, it can be added later as a UI field on the invoice creation flow.

3. **Admin fee on resend/regenerate-pdf**
   - What we know: The admin fee is a line item stored in the `line_items` repeater on creation. Subsequent PDF regenerations read from the stored repeater.
   - What's unclear: Nothing — the stored line item persists through PDF regeneration, email resend, and status changes.
   - Recommendation: No action needed. The admin fee is stored at creation time and persists with the invoice.

## Sources

### Primary (HIGH confidence)
- Direct code reading: `includes/class-finance-config.php` — complete FinanceConfig pattern
- Direct code reading: `includes/class-rest-invoices.php` — `create_invoice()` at L411-489; `delete_invoice()` at L501-547; `reset_payment_state()` at L1029-1092
- Direct code reading: `includes/class-invoice-pdf-generator.php` — `build_html()` line items rendering L258-302
- Direct code reading: `includes/class-invoice-email-sender.php` — `{tuchtzaken_lijst}` building L99-165
- Direct code reading: `acf-json/group_invoice_fields.json` — `line_items` repeater field definitions
- Direct code reading: `src/pages/Finance/FinanceSettings.jsx` — "Betaling" tab structure L464-546
- Direct code reading: `src/pages/People/PersonDetail.jsx` — `handleCreateInvoice()` L484-505
- Direct code reading: `includes/class-rest-api.php` L727-767 — finance settings route args

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all files read directly
- Architecture: HIGH — injection pattern is a straight extension of existing code
- Pitfalls: HIGH — derived from reading all affected code paths (PDF, email, delete, reset)

**Research date:** 2026-02-18
**Valid until:** 60 days (stable codebase, no fast-moving dependencies)
