---
phase: quick-76
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-invoices.php
  - src/api/client.js
  - src/hooks/useInvoices.js
  - src/pages/Finance/FactuurDetail.jsx
autonomous: true

must_haves:
  truths:
    - "A 'Reset betaalstatus' button is visible on invoice detail only when the active provider is in test/sandbox mode"
    - "Clicking the button clears payment_link, provider payment ID meta, qr_code_path, and resets status to 'sent' if currently 'paid'"
    - "Button is never shown when provider is in live/production mode"
  artifacts:
    - path: "includes/class-rest-invoices.php"
      provides: "POST /rondo/v1/invoices/{id}/reset-payment-state endpoint"
      contains: "reset_payment_state"
    - path: "src/api/client.js"
      provides: "resetPaymentState API method"
    - path: "src/hooks/useInvoices.js"
      provides: "useResetPaymentState mutation hook"
    - path: "src/pages/Finance/FactuurDetail.jsx"
      provides: "Conditional reset button with test-mode guard"
  key_links:
    - from: "src/pages/Finance/FactuurDetail.jsx"
      to: "/rondo/v1/invoices/{id}/reset-payment-state"
      via: "useResetPaymentState mutation"
      pattern: "useResetPaymentState"
    - from: "includes/class-rest-invoices.php"
      to: "FinanceConfig::get_all_settings()"
      via: "is_test_mode_active() check"
      pattern: "is_test_mode_active"
---

<objective>
Add a "Reset betaalstatus" button to the invoice detail page that clears all payment state
(payment link, provider payment ID, QR code) and resets a paid invoice back to sent status.
This button is ONLY shown when the active payment provider is in test/sandbox mode.

Purpose: Allow developers to re-test the full payment flow without having to create new
invoices. The test-mode guard prevents accidental use in production.
Output: REST endpoint + mutation hook + conditional button in FactuurDetail.jsx
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add reset-payment-state REST endpoint</name>
  <files>includes/class-rest-invoices.php</files>
  <action>
Register a new REST route in `register_routes()`:

```php
register_rest_route(
    'rondo/v1',
    '/invoices/(?P<id>\d+)/reset-payment-state',
    [[
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'reset_payment_state' ],
        'permission_callback' => [ $this, 'check_financieel_permission' ],
        'args'                => ['id' => ['validate_callback' => fn($p) => is_numeric($p)]],
    ]]
);
```

Add private helper method `is_test_mode_active(): bool` that:
- Reads `FinanceConfig::get_all_settings()`
- Returns true if `active_payment_provider === 'mollie'` AND `mollie_environment === 'test'`
- Returns true if `active_payment_provider === 'rabobank'` AND `rabobank_environment === 'sandbox'`
- Returns false otherwise

Add public method `reset_payment_state(\WP_REST_Request $request)`:
1. Validate invoice exists (same pattern as other endpoints — return 404 WP_Error if not found)
2. Call `$this->is_test_mode_active()` — if false, return WP_Error with code `test_mode_required`, message "Betaalstatus resetten is alleen beschikbaar in testmodus.", status 403
3. Get active provider via `$finance_config->get_active_payment_provider()`
4. Clear Mollie payment data: `delete_post_meta($invoice_id, '_mollie_payment_id')` and `update_field('payment_link', '', $invoice_id)`
5. Clear Rabobank payment data: `delete_post_meta($invoice_id, '_rabobank_payment_request_id')` and `update_field('payment_link', '', $invoice_id)`
6. Call `$this->clear_qr_code($invoice_id)` (private method already exists on the class)
7. If invoice is currently `rondo_paid`: `wp_update_post(['ID' => $invoice_id, 'post_status' => 'rondo_sent'])` and `update_field('status', 'sent', $invoice_id)`
8. Return `rest_ensure_response($this->format_invoice_detail(get_post($invoice_id)))`

Note: Clear BOTH Mollie and Rabobank meta regardless of active provider — keeps data clean when switching between providers.
  </action>
  <verify>
From terminal (requires auth cookie/nonce — logic check is sufficient):
`grep -n "reset_payment_state\|is_test_mode_active" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php`
Should show the route registration, both methods, and the test mode guard.
  </verify>
  <done>
`reset_payment_state` method exists, clears _mollie_payment_id, _rabobank_payment_request_id, payment_link, qr_code_path, resets paid→sent. Returns 403 when not in test mode.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add API method, mutation hook, and conditional UI button</name>
  <files>
    src/api/client.js
    src/hooks/useInvoices.js
    src/pages/Finance/FactuurDetail.jsx
  </files>
  <action>
**src/api/client.js** — add to `prmApi` after `regeneratePaymentLink`:
```js
resetPaymentState: (invoiceId) => api.post(`/rondo/v1/invoices/${invoiceId}/reset-payment-state`),
```

**src/hooks/useInvoices.js** — add export after `useRegeneratePaymentLink`:
```js
/**
 * Reset payment state for a test-mode invoice
 * @returns {object} Mutation object
 */
export function useResetPaymentState() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id) => {
      const response = await prmApi.resetPaymentState(id);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
    },
  });
}
```

**src/pages/Finance/FactuurDetail.jsx**:

1. Add import: `useResetPaymentState` to the imports from `@/hooks/useInvoices` and `useFinanceSettings` from `@/hooks/useFinanceSettings`. The `useFinanceSettings` import is already present — keep it.

2. Add hook call near top of component (after existing hooks):
```js
const resetPaymentState = useResetPaymentState();
const { data: financeSettings } = useFinanceSettings();
```
Note: `useFinanceSettings` is already imported but not called in the component — add this call.

3. Add test mode detection derived variable:
```js
const isTestMode = (() => {
  if (!financeSettings) return false;
  const provider = financeSettings.active_payment_provider;
  if (provider === 'mollie') return financeSettings.mollie_environment === 'test';
  if (provider === 'rabobank') return financeSettings.rabobank_environment === 'sandbox';
  return false;
})();
```

4. Add handler:
```js
const handleResetPaymentState = async () => {
  if (!window.confirm('Weet je zeker dat je de betaalstatus wilt resetten? Dit wist de betaallink en betaalstatus (alleen in testmodus).')) {
    return;
  }
  setErrorMessage('');
  try {
    await resetPaymentState.mutateAsync(id);
    setSuccessMessage('Betaalstatus gereset!');
  } catch (err) {
    setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het resetten van de betaalstatus.');
  }
};
```

5. Update `isPending` to include `resetPaymentState.isPending`.

6. Add the reset button inside the existing action buttons card. Place it after the existing paid-status actions block — show it for ALL invoice statuses when `isTestMode` is true AND the invoice has payment state to clear (has `payment_link` OR has `qr_code_path` OR status is `paid`):
```jsx
{isTestMode && (invoice.payment_link || invoice.qr_code_path || invoice.status === 'paid') && (
  <button
    onClick={handleResetPaymentState}
    disabled={isPending}
    className="btn-secondary flex items-center gap-2 border-orange-300 dark:border-orange-700 text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20"
  >
    {resetPaymentState.isPending ? (
      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-orange-600 dark:border-orange-400"></div>
    ) : (
      <RefreshCw className="w-4 h-4" />
    )}
    Reset betaalstatus (test)
  </button>
)}
```

Note: `RefreshCw` is already imported in FactuurDetail.jsx.
  </action>
  <verify>
Run `npm run build` from `/Users/joostdevalk/Code/rondo/rondo-club/` — must complete with no errors.
Then grep: `grep -n "resetPaymentState\|useResetPaymentState\|isTestMode\|reset-payment-state" /Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js /Users/joostdevalk/Code/rondo/rondo-club/src/hooks/useInvoices.js /Users/joostdevalk/Code/rondo/rondo-club/src/pages/Finance/FactuurDetail.jsx`
  </verify>
  <done>
Build succeeds. All three files contain reset payment state code. The button renders conditionally when isTestMode is true and the invoice has payment state to clear.
  </done>
</task>

</tasks>

<verification>
1. PHP: `grep -n "reset_payment_state\|is_test_mode_active" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php` — shows both methods
2. JS build: `npm run build` exits 0 with no errors
3. Frontend: Button import and conditional render present in FactuurDetail.jsx
4. Deploy to production: `bin/deploy.sh` from `/Users/joostdevalk/Code/rondo/rondo-club/`
</verification>

<success_criteria>
- REST endpoint POST /rondo/v1/invoices/{id}/reset-payment-state exists and returns 403 when provider is in live/production mode
- Endpoint clears _mollie_payment_id, _rabobank_payment_request_id, payment_link, qr_code_path when in test mode
- Endpoint resets paid invoice back to sent status when in test mode
- "Reset betaalstatus (test)" button appears on invoice detail page only when active provider is in test/sandbox mode AND invoice has payment state
- Button is never visible in production/live mode
- npm run build succeeds
</success_criteria>

<output>
After completion, create `.planning/quick/76-reset-payment-state-interface-for-test-m/76-SUMMARY.md`
</output>
