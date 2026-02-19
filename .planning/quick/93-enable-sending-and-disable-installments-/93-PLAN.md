---
phase: quick-93
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-invoices.php
  - includes/class-public-payment-page.php
  - includes/class-bulk-invoice-creator.php
  - src/hooks/useInvoices.js
  - src/pages/Finance/FactuurDetail.jsx
  - src/api/client.js
autonomous: true

must_haves:
  truths:
    - "Membership invoices created by BulkInvoiceCreator have _disable_installments=1 by default"
    - "The betaling page hides quarterly_3 and monthly_8 options when _disable_installments is set"
    - "The betaling page rejects plan selection for installments when _disable_installments is set"
    - "FactuurDetail shows a toggle for membership draft invoices to disable/enable installments"
    - "Toggling calls POST /rondo/v1/invoices/{id}/toggle-installments and refreshes the invoice"
  artifacts:
    - path: "includes/class-rest-invoices.php"
      provides: "toggle-installments REST endpoint + disable_installments in format_invoice_detail"
    - path: "includes/class-public-payment-page.php"
      provides: "render_page and handle_plan_selection check _disable_installments meta"
    - path: "includes/class-bulk-invoice-creator.php"
      provides: "sets _disable_installments=1 after creating membership invoice"
    - path: "src/hooks/useInvoices.js"
      provides: "useToggleInstallments mutation hook"
    - path: "src/pages/Finance/FactuurDetail.jsx"
      provides: "toggle UI for membership draft invoices"
  key_links:
    - from: "src/pages/Finance/FactuurDetail.jsx"
      to: "/rondo/v1/invoices/{id}/toggle-installments"
      via: "useToggleInstallments mutation"
    - from: "includes/class-public-payment-page.php"
      to: "_disable_installments post meta"
      via: "get_post_meta in render_page and handle_plan_selection"
---

<objective>
Add a per-invoice installments toggle for manually-created membership invoices.

Purpose: Nikki-year invoices are created manually via BulkInvoiceCreator but installments are irrelevant for that billing model. Admins need to be able to disable installments per-invoice, and newly created membership invoices should default to disabled.

Output: REST endpoint for toggling, public payment page enforcing the flag, bulk creator setting it by default, and a UI toggle on FactuurDetail.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: PHP — REST endpoint, public page enforcement, bulk creator default</name>
  <files>
    includes/class-rest-invoices.php
    includes/class-public-payment-page.php
    includes/class-bulk-invoice-creator.php
  </files>
  <action>
**includes/class-rest-invoices.php**

1. In `register_routes()` (after the reset-payment-state route, before the closing `}`), add a new route:
```php
// Toggle installments disabled flag
register_rest_route(
    'rondo/v1',
    '/invoices/(?P<id>\d+)/toggle-installments',
    [
        [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'toggle_installments' ],
            'permission_callback' => [ $this, 'check_financieel_permission' ],
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $p ) => is_numeric( $p ),
                ],
                'disabled' => [
                    'required'          => true,
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ],
    ]
);
```

2. Add the `toggle_installments()` method (near the other action methods, e.g. after `reset_payment_state()`):
```php
/**
 * Toggle installments disabled flag for a membership invoice.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error Response or error.
 */
public function toggle_installments( \WP_REST_Request $request ) {
    $invoice_id = (int) $request->get_param( 'id' );
    $invoice    = get_post( $invoice_id );

    if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
        return new \WP_Error( 'not_found', 'Factuur niet gevonden.', [ 'status' => 404 ] );
    }

    $disabled = (bool) $request->get_param( 'disabled' );

    if ( $disabled ) {
        update_post_meta( $invoice_id, '_disable_installments', '1' );
    } else {
        delete_post_meta( $invoice_id, '_disable_installments' );
    }

    return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
}
```

3. In `format_invoice_detail()`, after the `$invoice['installments'] = $installments;` line (around line 1335), add:
```php
$invoice['disable_installments'] = (bool) get_post_meta( $post->ID, '_disable_installments', true );
```

**includes/class-public-payment-page.php**

4. In `render_page()`, after line 186 (`$plan_8_enabled = $membership_fees->get_installment_plan_8_enabled( $season );`), add:
```php
// Per-invoice override: if installments disabled, hide both plans.
if ( get_post_meta( $invoice_id, '_disable_installments', true ) ) {
    $plan_3_enabled = false;
    $plan_8_enabled = false;
}
```

5. In `handle_plan_selection()`, after the existing plan-enabled checks (after line 470, the closing `}` of the installment plan checks block), add:
```php
// Per-invoice override: reject installment plan if disabled for this invoice.
if ( ( 'quarterly_3' === $plan || 'monthly_8' === $plan ) && get_post_meta( $invoice_id, '_disable_installments', true ) ) {
    $this->render_error( 'Termijnbetaling is niet beschikbaar voor deze factuur.' );
    exit;
}
```

**includes/class-bulk-invoice-creator.php**

6. In `create_membership_invoice()`, after the `PublicPaymentPage::generate_token( $post_id );` call (around line 270) and before `return 'created';`, add:
```php
// Default: disable installments for bulk-created membership invoices.
update_post_meta( $post_id, '_disable_installments', '1' );
```
  </action>
  <verify>
Run `npm run build` — should compile without errors.
Check PHP syntax: `php -l includes/class-rest-invoices.php && php -l includes/class-public-payment-page.php && php -l includes/class-bulk-invoice-creator.php`
  </verify>
  <done>All three PHP files parse clean. Build compiles. REST endpoint registered at POST /rondo/v1/invoices/{id}/toggle-installments. format_invoice_detail returns disable_installments boolean. BulkInvoiceCreator sets _disable_installments=1 after creation. Public page respects the meta in both render and handle.</done>
</task>

<task type="auto">
  <name>Task 2: Frontend — useToggleInstallments hook + FactuurDetail toggle UI</name>
  <files>
    src/api/client.js
    src/hooks/useInvoices.js
    src/pages/Finance/FactuurDetail.jsx
  </files>
  <action>
**src/api/client.js**

After the `resetPaymentState` line (line 299), add:
```js
toggleInstallments: (invoiceId, disabled) => api.post(`/rondo/v1/invoices/${invoiceId}/toggle-installments`, { disabled }),
```

**src/hooks/useInvoices.js**

At the end of the file (after `useResetPaymentState`), add:
```js
/**
 * Toggle installments disabled flag for a membership invoice
 * @returns {object} Mutation object
 */
export function useToggleInstallments() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, disabled }) => {
      const response = await prmApi.toggleInstallments(id, disabled);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
    },
  });
}
```

**src/pages/Finance/FactuurDetail.jsx**

1. Update the import line at the top to add `useToggleInstallments`:
```js
import { useInvoice, useSendInvoice, useUpdateInvoiceStatus, useResendInvoice, useGenerateInvoicePdf, useRegeneratePaymentLink, useResetPaymentState, useDeleteInvoice, useToggleInstallments } from '@/hooks/useInvoices';
```

2. Inside the `FactuurDetail` component (near the other mutation hooks), add:
```js
const toggleInstallments = useToggleInstallments();
```

3. In the draft-status actions section (inside `{invoice.status === 'draft' && (...)}`, before the Send button or just before the closing `</>` of the draft block), add a toggle for membership invoices:
```jsx
{invoice.invoice_type === 'membership' && (
  <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer select-none">
    <input
      type="checkbox"
      checked={!!invoice.disable_installments}
      disabled={toggleInstallments.isPending}
      onChange={(e) => toggleInstallments.mutate({ id: invoice.id, disabled: e.target.checked })}
      className="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500"
    />
    Termijnbetaling uitschakelen
  </label>
)}
```

Place this toggle just before the Send button inside the draft block, so it appears above the action buttons. Exact placement: as the first element inside the `<>` of the `{invoice.status === 'draft' && (...)}` block, but wrap it in a `<div className="w-full mb-2">` so it appears on its own line before the buttons.
  </action>
  <verify>
Run `npm run build` — should compile without errors. No ESLint warnings (run `npm run lint`).
  </verify>
  <done>Build and lint pass. FactuurDetail shows "Termijnbetaling uitschakelen" checkbox for membership draft invoices. Checkbox reflects invoice.disable_installments. Toggling calls POST /rondo/v1/invoices/{id}/toggle-installments and invalidates invoice queries.</done>
</task>

</tasks>

<verification>
After deploying:
1. Navigate to a draft membership invoice in FactuurDetail
2. Verify "Termijnbetaling uitschakelen" checkbox is visible and checked (for bulk-created ones)
3. Uncheck it — page should reload showing unchecked state
4. Re-check it — page should reload showing checked state
5. Visit the betaling page link for an invoice with _disable_installments=1 — only "Volledig betalen" option should be visible (no quarterly_3 / monthly_8)
6. Discipline invoices should NOT show the checkbox
</verification>

<success_criteria>
- Membership draft invoices show installments toggle in FactuurDetail
- Bulk-created membership invoices default to disable_installments=true
- The betaling public page hides installment options when flag is set
- The betaling public page rejects POST plan selection for installments when flag is set
- Sending already works via existing "Verstuur factuur" button (no changes needed)
</success_criteria>

<output>
After completion, create `.planning/quick/93-enable-sending-and-disable-installments-/93-SUMMARY.md`
</output>
