# Phase 197: Frontend Updates - Research

**Researched:** 2026-02-19
**Domain:** React frontend (Facturen list, FactuurDetail), PHP REST API (invoice list filtering, installment data), WordPress capability system
**Confidence:** HIGH

## Summary

Phase 197 adds three new filter dropdowns to the Facturen list (by invoice type, payment plan, and overdue status), adds an installment timeline section to the FactuurDetail page, and ensures non-admin users with the `financieel` capability can access all finance pages without being blocked.

The frontend work is entirely in `src/pages/Finance/Facturen.jsx` and `src/pages/Finance/FactuurDetail.jsx`. The existing filter bar already uses URL-based state via `useSearchParams` (single `status` filter). The new filters extend this pattern. All backend installment data is stored as flat WordPress post meta (`_installment_N_*` keys) and must be read and appended to the `format_invoice_detail` response in `class-rest-invoices.php`. No new PHP classes are needed — only additions to existing files.

The AUTH-01 requirement is already mostly handled: `FinancieelRoute` guards all finance pages, `check_financieel_permission` guards all invoice API endpoints, and `can_access_financieel` is returned by `/rondo/v1/user/me`. The only blocker is the Finance Instellingen link in the sidebar, which is marked `adminOnly: true` — non-admin finance users cannot see it, which is correct. No auth work is needed for existing pages.

**Primary recommendation:** Backend first — extend `get_invoice_list` with `type` and `payment_plan` params, extend `format_invoice_detail` to include installments array. Then extend the frontend filter bar and add the timeline card to FactuurDetail.

## Standard Stack

### Core (all verified by reading actual code)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | UI components | Project standard |
| TanStack Query | ^5 | Server state, caching | Already used for useInvoices |
| React Router v6 | ^6 | URL-based filter state via useSearchParams | Already used in Facturen.jsx |
| Tailwind CSS v4 | ^4 | Styling | Project standard, CSS-first @theme |
| lucide-react | installed | Icons (Filter, ChevronUp/Down already imported) | Project standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| date-fns (via `@/utils/dateFormat`) | project util | Date formatting | `format`, `parseYmd` already in FactuurDetail |
| `@/utils/formatters` | project util | `formatCurrency` | Already used in FactuurDetail |

## Architecture Patterns

### Pattern 1: URL-based filter state (existing pattern in Facturen.jsx)

All filters live in `useSearchParams`. Already implemented for `status`. New filters follow identical pattern:

```jsx
const typeFilter = searchParams.get('type') || '';
const planFilter = searchParams.get('plan') || '';
const overdueFilter = searchParams.get('overdue') || '';

const updateFilter = useCallback((key, value) => {
  if (value === '') {
    searchParams.delete(key);
  } else {
    searchParams.set(key, value);
  }
  setSearchParams(searchParams, { replace: true });
}, [searchParams, setSearchParams]);
```

The `useInvoices` hook passes `params` as-is to `prmApi.getInvoices(params)`, which already accepts any query string params. The backend needs to handle the new keys.

### Pattern 2: Invoice list params → REST API

The existing `get_invoice_list` handler in `class-rest-invoices.php` accepts `status` and `person_id`. New params `type` and `payment_plan` map to post meta queries, `overdue` is a boolean shortcut for `status=overdue`.

The `_installment_plan` meta key stores `'full'`, `'quarterly_3'`, or `'monthly_8'`. The `invoice_type` ACF field stores `'membership'` or `'discipline'` (null = no type set, from legacy discipline invoices).

### Pattern 3: Installment timeline in format_invoice_detail

Installments are stored as flat post meta (not ACF). Read them with `get_post_meta`:

```php
$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
$plan  = get_post_meta( $invoice_id, '_installment_plan', true );

$installments = [];
for ( $n = 1; $n <= $count; $n++ ) {
    $installments[] = [
        'number'    => $n,
        'amount'    => (float) get_post_meta( $invoice_id, '_installment_' . $n . '_amount', true ),
        'admin_fee' => (float) get_post_meta( $invoice_id, '_installment_' . $n . '_admin_fee', true ),
        'status'    => (string) get_post_meta( $invoice_id, '_installment_' . $n . '_status', true ),
        'due_date'  => (string) get_post_meta( $invoice_id, '_installment_' . $n . '_due_date', true ) ?: null,
        'paid_at'   => (string) get_post_meta( $invoice_id, '_installment_' . $n . '_paid_at', true ) ?: null,
        'sent_at'   => (string) get_post_meta( $invoice_id, '_installment_' . $n . '_sent_at', true ) ?: null,
    ];
}
```

Add `'installment_plan' => $plan`, `'installment_count' => $count`, `'installments' => $installments` to the detail response. For invoices without installment data (discipline invoices, full-plan invoices before payment selection), `_installment_count` returns empty string → cast to int = 0 → return empty array. The full plan invoices store `_installment_plan = 'full'` and `_installment_count = 1` but no per-installment meta (the amount is just `total_amount`). Edge case: invoices with `_installment_plan = 'full'` should still show the installment section — with one row showing the full amount and its status.

### Pattern 4: Status labels for installments

From `class-installment-scheduler.php`, installment statuses are: `'pending'`, `'sent'`, `'betaald'`, `'overdue'` (note: **betaald** not **paid** — this is Dutch). From `class-installment-email-sender.php`:

```php
update_post_meta( $invoice_id, '_installment_' . $n . '_status', 'sent' );   // after email sent
update_post_meta( $invoice_id, '_installment_' . $n . '_status', 'betaald' ); // after payment confirmed
```

Dutch label mapping for frontend display:
- `pending` → "Openstaand"
- `sent` → "Verstuurd"
- `betaald` → "Betaald"
- (no explicit overdue status stored per-installment — the scheduler checks days overdue at runtime)

### Pattern 5: AUTH-01 — Finance Instellingen is adminOnly, rest is already guarded

From the codebase:

```jsx
// router.jsx — all finance pages are inside FinancieelRoute
{ path: 'financien/contributie', element: <FinancieelRoute><Contributie /></FinancieelRoute> }
{ path: 'financien/facturen', element: <FinancieelRoute><Facturen /></FinancieelRoute> }
{ path: 'financien/instellingen', element: <FinancieelRoute><FinanceSettings /></FinancieelRoute> }
```

```jsx
// Layout.jsx sidebar — Instellingen is adminOnly: true
{ name: 'Instellingen', href: '/financien/instellingen', adminOnly: true, requiresFinancieel: true }
```

The `FinancieelRoute` checks `user?.can_access_financieel`. The `rondo_bestuur` role has `financieel` capability. So a `rondo_bestuur` user who is NOT a WP admin can:
- See the Financien section in the sidebar (requiresFinancieel passes)
- Access Contributie and Facturen (FinancieelRoute passes)
- NOT see Finance Instellingen link (adminOnly blocks it)
- NOT access Finance Instellingen URL directly (FinancieelRoute allows it, but the route is guarded by `check_financieel_permission` not `check_admin_permission`)

**Finding:** AUTH-01 says "can manage membership invoicing without being WordPress administrators." The FinancieelRoute already covers Contributie, Facturen, and FactuurDetail. The Finance Instellingen route is also `FinancieelRoute` (not `AdminRoute`), so a bestuur user CAN navigate directly to `/financien/instellingen` — the sidebar link just doesn't show. This may be intentional or an oversight. The requirements say "access the Facturen list, invoice detail, and Contributie page" — which are all already accessible. No auth changes needed for those three pages.

**The one open question:** The FinanceSettings page at `/financien/instellingen` is accessible to any financieel user (not admin-only at the route level), but the sidebar hides it for non-admins. This seems intentional. AUTH-01 does not require admin-only protection for FinanceSettings, so this is fine as-is.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Filter state in URL | Custom useState + URL sync | `useSearchParams` (already in use) | Built-in, handles browser back/forward |
| Date formatting | Custom formatter | `format(parseYmd(date), 'd MMM yyyy')` (already in FactuurDetail) | Consistent with existing code |
| Status badges | Custom component | Extend existing `StatusBadge` pattern | DRY — already defined in both files |

## Common Pitfalls

### Pitfall 1: installment_count = 0 for invoices without installment data

**What goes wrong:** For discipline invoices (no payment plan selected) and membership invoices in `draft` state (before member visits payment page), `_installment_count` is empty/not set. `get_post_meta` returns `''`, cast to int = 0.

**How to avoid:** Guard: `if ($count < 1) return empty array`. Do not render the installment section on the frontend when `installments` array is empty and `installment_plan` is null/empty.

### Pitfall 2: full-plan installments have no per-installment meta

**What goes wrong:** Invoices with `_installment_plan = 'full'` store `_installment_count = 1` but `write_installment_meta` is NOT called for `'full'` plan (see `class-public-payment-page.php` line 458-459). So `_installment_1_amount`, `_installment_1_status`, etc. are empty for full-plan invoices.

**How to avoid:** For `full` plan, synthesize a single installment row from the invoice's `total_amount` and `status` fields rather than reading per-installment meta. Or simply: show "Volledig" badge for full-plan and omit the timeline table.

### Pitfall 3: Overdue filter conflicts with status filter

**What goes wrong:** If user sets `status=sent` AND `overdue=1`, the results may conflict (overdue invoices have `rondo_overdue` status, not `rondo_sent`).

**How to avoid:** Treat `overdue` filter as its own independent filter that sets `status=overdue` in the backend query, or display it as a separate binary toggle (has_overdue_installment). Per requirements, "Admin can filter the Facturen list to show only invoices with at least one overdue installment" — this means filter by invoice status `overdue`. The simplest approach: `overdue=1` simply forces `status=overdue` in the backend query and the status filter dropdown is reset/disabled.

**Actually**: re-reading FACT-03: "show only invoices with at least one overdue installment". This refers to the invoice-level overdue status (not per-installment overdue). The backend already has `rondo_overdue` post status. So `overdue=1` maps directly to `status=overdue` in the API call. The frontend can implement this as a checkbox or separate button rather than adding complexity.

### Pitfall 4: DRY violation — StatusBadge is duplicated

**What goes wrong:** `StatusBadge` component is copy-pasted in both `Facturen.jsx` and `FactuurDetail.jsx` with slightly different padding (`px-2 py-0.5` vs `px-2 py-1`).

**How to avoid:** This is pre-existing technical debt. Do not introduce a third copy for installment statuses. Consider extracting to `@/components/StatusBadge.jsx` in this phase to DRY up all three usages — but only if the phase plan calls for it (it is a nice-to-have, not required).

### Pitfall 5: payment_plan filter maps display labels to meta values

**What goes wrong:** The frontend shows "Volledig / 3 termijnen / 8 termijnen" but the meta values are `'full'` / `'quarterly_3'` / `'monthly_8'`.

**How to avoid:** The frontend sends the raw meta value as the `payment_plan` query param. The backend reads `_installment_plan` meta with `'!=', ''` and `'=', $plan` comparison.

## Code Examples

### Backend: extend get_invoice_list with type and payment_plan filters

```php
// In get_invoice_list(), after existing status and person_id filters:

// Filter by invoice type
$type = $request->get_param( 'type' );
if ( ! empty( $type ) && in_array( $type, [ 'membership', 'discipline' ], true ) ) {
    $args['meta_query'][] = [
        'key'   => 'invoice_type',
        'value' => $type,
    ];
}

// Filter by payment plan (stored in post meta _installment_plan)
$payment_plan = $request->get_param( 'payment_plan' );
if ( ! empty( $payment_plan ) && in_array( $payment_plan, [ 'full', 'quarterly_3', 'monthly_8' ], true ) ) {
    $args['meta_query'][] = [
        'key'   => '_installment_plan',
        'value' => $payment_plan,
    ];
}
```

Note: `meta_query` needs to be initialised as an array before conditionally appending.

### Backend: add installments to format_invoice_detail

```php
// In format_invoice_detail(), after existing fields:

$plan  = get_post_meta( $post->ID, '_installment_plan', true ) ?: null;
$count = (int) get_post_meta( $post->ID, '_installment_count', true );

$installments = [];
if ( $count >= 1 && $plan !== 'full' ) {
    for ( $n = 1; $n <= $count; $n++ ) {
        $amount    = (float) get_post_meta( $post->ID, '_installment_' . $n . '_amount', true );
        $admin_fee = (float) get_post_meta( $post->ID, '_installment_' . $n . '_admin_fee', true );
        $installments[] = [
            'number'   => $n,
            'amount'   => $amount + $admin_fee,
            'status'   => (string) get_post_meta( $post->ID, '_installment_' . $n . '_status', true ) ?: 'pending',
            'due_date' => (string) get_post_meta( $post->ID, '_installment_' . $n . '_due_date', true ) ?: null,
            'paid_at'  => (string) get_post_meta( $post->ID, '_installment_' . $n . '_paid_at', true ) ?: null,
            'sent_at'  => (string) get_post_meta( $post->ID, '_installment_' . $n . '_sent_at', true ) ?: null,
        ];
    }
}

$invoice['installment_plan']  = $plan;
$invoice['installment_count'] = $count;
$invoice['installments']      = $installments;
```

### Frontend: extend useInvoices to accept new filter params

The `useInvoices` hook already passes `params` object to `prmApi.getInvoices(params)`. No hook changes needed — just pass `{ status, type, payment_plan }` from Facturen.jsx.

### Frontend: installment status badges

```jsx
const installmentStatusColors = {
  pending: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  betaald: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
};

const installmentStatusLabels = {
  pending: 'Openstaand',
  sent: 'Verstuurd',
  betaald: 'Betaald',
};
```

### Frontend: installment timeline table (in FactuurDetail)

```jsx
{invoice.installments && invoice.installments.length > 0 && (
  <div className="card p-6">
    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
      Termijnen ({invoice.installment_plan === 'quarterly_3' ? '3 termijnen' : '8 termijnen'})
    </h2>
    <div className="overflow-x-auto">
      <table className="w-full">
        <thead className="border-b border-gray-200 dark:border-gray-700">
          <tr>
            <th className="...">Termijn</th>
            <th className="...">Vervaldatum</th>
            <th className="...">Bedrag</th>
            <th className="...">Status</th>
            <th className="...">Betaald op</th>
          </tr>
        </thead>
        <tbody>
          {invoice.installments.map((inst) => (
            <tr key={inst.number}>
              <td>{inst.number}</td>
              <td>{inst.due_date ? format(parseYmd(inst.due_date), 'd MMM yyyy') : '-'}</td>
              <td>{formatCurrency(inst.amount, 2)}</td>
              <td><InstallmentStatusBadge status={inst.status} /></td>
              <td>{inst.paid_at ? format(new Date(inst.paid_at), 'd MMM yyyy') : '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  </div>
)}
```

## State of the Art

| Area | Current State | What Phase 197 Changes |
|------|--------------|------------------------|
| Facturen filter bar | Single status dropdown | Adds type + payment_plan dropdowns + overdue toggle |
| FactuurDetail | Shows line items, no installment info | Adds installment timeline card |
| Finance route auth | `FinancieelRoute` already guards all pages | No changes needed — auth already correct |
| Invoice list API | Accepts `status`, `person_id` | Add `type`, `payment_plan` params |
| Invoice detail API | Returns line_items, no installments | Add `installment_plan`, `installment_count`, `installments[]` |

## Open Questions

1. **Should the overdue filter be a checkbox or a dropdown option?**
   - What we know: Status dropdown already has "Verlopen" as an option. Adding a separate overdue checkbox/button would be redundant.
   - Recommendation: Implement as a separate `<select>` styled like the status filter with "Alle" / "Verlopen termijn" options — OR simply make it a button/checkbox. Since the status dropdown already handles invoice-level overdue, FACT-03 ("at least one overdue installment") appears redundant with the existing `status=overdue` filter. Planner should decide if this needs a separate filter or if the existing status filter already satisfies FACT-03.

2. **Full-plan timeline: show or hide?**
   - For `_installment_plan = 'full'`, there is no per-installment meta.
   - Recommendation: Hide the installment timeline section entirely for full-plan or no-plan invoices. Show it only when `installments.length > 0`. This matches the requirement "per-installment timeline" which only makes sense for multi-installment plans.

3. **`invoice_type` field for legacy discipline invoices**
   - Per Phase 192 notes: `invoice_type` ACF field has `allow_null=1` and `required=0`. Legacy discipline invoices created before Phase 192 may have null/empty `invoice_type`.
   - Recommendation: Backend filter for `type=discipline` should include invoices where `invoice_type = 'discipline'` OR where `invoice_type` is empty and the invoice has discipline case line items. Alternatively, treat null invoice_type invoices as discipline invoices (they were all discipline before membership was added). Planner should verify what Phase 192 actually shipped.

## Sources

### Primary (HIGH confidence — code read directly)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Finance/Facturen.jsx` — existing filter pattern, useSearchParams, filter bar
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Finance/FactuurDetail.jsx` — existing detail structure, line items table
- `/Users/joostdevalk/Code/rondo/rondo-club/src/hooks/useInvoices.js` — useInvoices, useInvoice hooks
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php` — get_invoice_list, format_invoice, format_invoice_detail
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php` — installment meta schema (lines 22-35)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-public-payment-page.php` — write_installment_meta, plan storage
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-installment-scheduler.php` — installment status values
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-user-roles.php` — rondo_bestuur role has financieel cap
- `/Users/joostdevalk/Code/rondo/rondo-club/src/router.jsx` — FinancieelRoute, all finance routes already guarded
- `/Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx` — adminOnly flag on Instellingen, canAccessFinancieel check
- `/Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js` — getInvoices already passes params as query string
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-bulk-invoice-creator.php` — invoice_type = 'membership' confirmed

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries verified in package.json and actual code
- Architecture (filter pattern): HIGH — read existing Facturen.jsx implementation
- Architecture (installment data): HIGH — read installment meta schema from class-finance-config.php comments and actual write_installment_meta code
- Auth analysis: HIGH — read router.jsx, Layout.jsx, class-user-roles.php in full
- Pitfalls: HIGH — derived from actual code behavior, not assumptions

**Research date:** 2026-02-19
**Valid until:** 2026-03-19 (stable codebase, no external dependencies)
