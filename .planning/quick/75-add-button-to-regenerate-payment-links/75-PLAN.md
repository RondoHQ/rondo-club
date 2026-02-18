---
phase: 75-add-button-to-regenerate-payment-links
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-invoices.php
  - src/hooks/useInvoices.js
  - src/pages/Finance/FactuurDetail.jsx
autonomous: true

must_haves:
  truths:
    - "User can regenerate a payment link on any unpaid invoice that already has a payment link"
    - "Regenerated payment link replaces the old one and is immediately visible in the UI"
    - "Regeneration works for both Mollie and Rabobank providers"
  artifacts:
    - path: "includes/class-rest-invoices.php"
      provides: "POST /rondo/v1/invoices/{id}/regenerate-payment-link endpoint"
      contains: "regenerate_payment_link"
    - path: "src/hooks/useInvoices.js"
      provides: "useRegeneratePaymentLink mutation hook"
      exports: ["useRegeneratePaymentLink"]
    - path: "src/pages/Finance/FactuurDetail.jsx"
      provides: "Regenerate payment link button in action buttons section"
      contains: "handleRegeneratePaymentLink"
  key_links:
    - from: "src/pages/Finance/FactuurDetail.jsx"
      to: "/rondo/v1/invoices/{id}/regenerate-payment-link"
      via: "useRegeneratePaymentLink mutation"
      pattern: "regenerate-payment-link"
    - from: "includes/class-rest-invoices.php"
      to: "MolliePayment / RabobankPayment"
      via: "provider routing (same pattern as send_invoice)"
      pattern: "active_provider"
---

<objective>
Add a button to regenerate payment links on invoice detail pages.

Purpose: Users need to regenerate payment links when the old link has expired or a new link needs to be sent to the member. Currently a payment link can only be created once (when it does not exist). Once created, there is no way to regenerate it.
Output: A POST REST endpoint for regeneration + frontend button visible when a payment link already exists on an unpaid invoice.
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
  <name>Task 1: Add regenerate-payment-link REST endpoint</name>
  <files>includes/class-rest-invoices.php</files>
  <action>
    In `class-rest-invoices.php`, add two things:

    1. Register a new REST route in `register_routes()` (after the existing `/resend` route):
    ```php
    // Regenerate payment link for invoice
    register_rest_route(
        'rondo/v1',
        '/invoices/(?P<id>\d+)/regenerate-payment-link',
        [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'regenerate_payment_link' ],
                'permission_callback' => [ $this, 'check_financieel_permission' ],
                'args'                => [
                    'id' => [
                        'validate_callback' => function ( $param ) {
                            return is_numeric( $param );
                        },
                    ],
                ],
            ],
        ]
    );
    ```

    2. Add the `regenerate_payment_link()` method. Model it after `send_invoice()` — same provider routing pattern (FinanceConfig → get_active_payment_provider → mollie or rabobank branch). Key difference: before calling the provider, clear the Mollie payment ID so idempotency is bypassed:
    ```php
    public function regenerate_payment_link( \WP_REST_Request $request ) {
        $invoice_id = (int) $request->get_param( 'id' );

        // Only unpaid invoices can have payment links regenerated
        $status = get_field( 'status', $invoice_id );
        if ( $status === 'paid' ) {
            return new \WP_Error(
                'invoice_paid',
                __( 'Betaalde facturen kunnen geen nieuwe betaallink krijgen.', 'rondo' ),
                [ 'status' => 400 ]
            );
        }

        $finance_config  = new FinanceConfig();
        $active_provider = $finance_config->get_active_payment_provider();

        if ( 'mollie' === $active_provider ) {
            // Clear Mollie payment ID to bypass idempotency and force a new payment link
            delete_post_meta( $invoice_id, '_mollie_payment_id' );
            update_field( 'payment_link', '', $invoice_id );

            $mollie_payment = new MolliePayment();
            $result = $mollie_payment->create_payment_link( $invoice_id );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        } else {
            $oauth = new RabobankOAuth();
            if ( ! $oauth->is_connected() ) {
                return new \WP_Error(
                    'rabobank_not_connected',
                    __( 'Rabobank is niet gekoppeld.', 'rondo' ),
                    [ 'status' => 400 ]
                );
            }
            $payment = new RabobankPayment( $oauth );
            $result  = $payment->create_payment_request( $invoice_id );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        // Return updated invoice
        return rest_ensure_response( $this->format_invoice_response( get_post( $invoice_id ) ) );
    }
    ```

    Check the existing `format_invoice_response` method name — use whatever method `send_invoice()` uses to format its response. If it delegates to another method, match that pattern exactly.

    Also add the required `use` statements at the top of the class file if MolliePayment or RabobankOAuth/RabobankPayment are not already imported.
  </action>
  <verify>
    Run: `cd /Users/joostdevalk/Code/rondo/rondo-club && php -l includes/class-rest-invoices.php`
    Expected: "No syntax errors detected"
  </verify>
  <done>PHP lints clean. Endpoint registered at POST /rondo/v1/invoices/{id}/regenerate-payment-link with financieel permission check. Method clears Mollie payment ID before creating a new link for Mollie provider, calls create_payment_request directly for Rabobank.</done>
</task>

<task type="auto">
  <name>Task 2: Add frontend hook and button</name>
  <files>
    src/hooks/useInvoices.js
    src/pages/Finance/FactuurDetail.jsx
  </files>
  <action>
    **useInvoices.js** — Add `useRegeneratePaymentLink` hook at the bottom of the file, following the same pattern as `useResendInvoice`:
    ```js
    /**
     * Regenerate payment link for an invoice
     * @returns {object} Mutation object for regenerating payment links
     */
    export function useRegeneratePaymentLink() {
      const queryClient = useQueryClient();

      return useMutation({
        mutationFn: async (id) => {
          const response = await prmApi.regeneratePaymentLink(id);
          return response.data;
        },
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: ['invoices'] });
          queryClient.invalidateQueries({ queryKey: ['invoice'] });
        },
      });
    }
    ```

    **src/api/client.js** — Add the API method alongside `createPaymentLink`:
    ```js
    regeneratePaymentLink: (invoiceId) => api.post(`/rondo/v1/invoices/${invoiceId}/regenerate-payment-link`),
    ```

    **FactuurDetail.jsx** — Three changes:

    1. Add `useRegeneratePaymentLink` to the import from `@/hooks/useInvoices`:
    ```js
    import { useInvoice, useSendInvoice, useUpdateInvoiceStatus, useResendInvoice, useGenerateInvoicePdf, useRegeneratePaymentLink } from '@/hooks/useInvoices';
    ```

    2. Instantiate the hook alongside the others:
    ```js
    const regeneratePaymentLink = useRegeneratePaymentLink();
    ```

    3. Add handler function (after `handleCreatePaymentLink`):
    ```js
    const handleRegeneratePaymentLink = async () => {
      if (!window.confirm('Weet je zeker dat je een nieuwe betaallink wilt aanmaken? De bestaande link wordt vervangen.')) {
        return;
      }
      setErrorMessage('');
      try {
        await regeneratePaymentLink.mutateAsync(id);
        setSuccessMessage('Betaallink opnieuw aangemaakt!');
      } catch (err) {
        setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het opnieuw aanmaken van de betaallink.');
      }
    };
    ```

    4. Include `regeneratePaymentLink.isPending` in the `isPending` check:
    ```js
    const isPending = sendInvoice.isPending || updateInvoiceStatus.isPending || resendInvoice.isPending || generatePdf.isPending || createPaymentLink.isPending || regeneratePaymentLink.isPending;
    ```

    5. Add the "Betaallink opnieuw aanmaken" button in the action buttons section. Place it alongside the existing "Betaallink aanmaken" block. Show it when the invoice is unpaid AND already has a payment link (opposite condition from the existing button):
    ```jsx
    {/* Regenerate payment link button (for any unpaid invoice WITH an existing link) */}
    {invoice.status !== 'paid' && invoice.payment_link && (
      <button
        onClick={handleRegeneratePaymentLink}
        disabled={isPending}
        className="btn-secondary flex items-center gap-2"
      >
        {regeneratePaymentLink.isPending ? (
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
        ) : (
          <RefreshCw className="w-4 h-4" />
        )}
        Betaallink opnieuw aanmaken
      </button>
    )}
    ```

    Place this block directly after the existing `{invoice.status !== 'paid' && !invoice.payment_link && (...)}` block so both are visually grouped.

    Note: `RefreshCw` is already imported in the file.
  </action>
  <verify>
    Run: `cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build 2>&1 | tail -20`
    Expected: Build completes without errors.
  </verify>
  <done>Build succeeds. Invoice detail page shows "Betaallink opnieuw aanmaken" button when invoice is unpaid and already has a payment link. Clicking calls POST /rondo/v1/invoices/{id}/regenerate-payment-link and shows success/error message.</done>
</task>

</tasks>

<verification>
1. PHP syntax check: `php -l includes/class-rest-invoices.php` — no errors
2. Frontend build: `npm run build` — no errors
3. Deploy and verify on production:
   - Navigate to an invoice that already has a payment_link value
   - Confirm "Betaallink opnieuw aanmaken" button is visible (alongside the existing link)
   - Confirm invoices without a payment link still show "Betaallink aanmaken" (existing button unchanged)
   - Click regenerate on an invoice with Mollie active — confirm a new link is returned and displayed
</verification>

<success_criteria>
- "Betaallink opnieuw aanmaken" button visible on unpaid invoices that already have a payment link
- "Betaallink aanmaken" button still visible on unpaid invoices with no payment link (unchanged)
- Regeneration clears old Mollie payment ID before creating new link (avoiding idempotency return)
- Both Mollie and Rabobank provider paths handled in the new endpoint
- Build and PHP lint pass cleanly
</success_criteria>

<output>
After completion, create `.planning/quick/75-add-button-to-regenerate-payment-links/75-SUMMARY.md`
</output>
