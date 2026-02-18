---
phase: quick-83
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
    - "Draft invoices can be permanently deleted via the detail page"
    - "Non-draft invoices cannot be deleted (backend guard returns 400)"
    - "Deleting a draft frees the invoice number for reuse by generate_next()"
    - "Linked discipline cases have is_charged reset to empty on deletion"
    - "PDF and QR code files are cleaned up from disk on deletion"
    - "User sees a confirmation dialog before deletion occurs"
    - "After deletion, user is navigated back to invoices list"
  artifacts:
    - path: "includes/class-rest-invoices.php"
      provides: "DELETE /rondo/v1/invoices/{id} endpoint"
      contains: "delete_invoice"
    - path: "src/api/client.js"
      provides: "deleteInvoice API method"
      contains: "deleteInvoice"
    - path: "src/hooks/useInvoices.js"
      provides: "useDeleteInvoice mutation hook"
      contains: "useDeleteInvoice"
    - path: "src/pages/Finance/FactuurDetail.jsx"
      provides: "Red delete button for draft invoices"
      contains: "handleDelete"
  key_links:
    - from: "src/pages/Finance/FactuurDetail.jsx"
      to: "src/hooks/useInvoices.js"
      via: "useDeleteInvoice hook"
      pattern: "useDeleteInvoice"
    - from: "src/hooks/useInvoices.js"
      to: "src/api/client.js"
      via: "prmApi.deleteInvoice"
      pattern: "prmApi\\.deleteInvoice"
    - from: "src/api/client.js"
      to: "includes/class-rest-invoices.php"
      via: "DELETE /rondo/v1/invoices/${id}"
      pattern: "api\\.delete.*invoices"
---

<objective>
Add the ability to delete draft invoices, cleaning up associated files (PDF, QR code), resetting linked discipline cases, and freeing the invoice number for reuse.

Purpose: Users need to discard incorrectly created draft invoices and reclaim the invoice number.
Output: Working delete flow from UI button through REST API to permanent post deletion.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-rest-invoices.php (REST controller with existing patterns: clear_pdf, clear_qr_code, reset_payment_state)
@src/api/client.js (API client with existing invoice methods at lines 302-312)
@src/hooks/useInvoices.js (TanStack Query hooks for invoice mutations)
@src/pages/Finance/FactuurDetail.jsx (Invoice detail page with status-driven action buttons)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Backend DELETE endpoint and API client method</name>
  <files>includes/class-rest-invoices.php, src/api/client.js</files>
  <action>
In `includes/class-rest-invoices.php`:

1. Register a new DELETE route in `register_routes()` on the existing `/invoices/(?P<id>\d+)` route group. Add a second array entry alongside the existing GET handler:
   ```php
   [
       'methods'             => \WP_REST_Server::DELETABLE,
       'callback'            => [ $this, 'delete_invoice' ],
       'permission_callback' => [ $this, 'check_financieel_permission' ],
       'args'                => [
           'id' => [
               'validate_callback' => function ( $param ) {
                   return is_numeric( $param );
               },
           ],
       ],
   ],
   ```

2. Add `delete_invoice()` public method after `create_invoice()`:
   - Get invoice_id from request param, validate post exists and is `rondo_invoice` type (404 if not)
   - Guard: check `$invoice->post_status === 'rondo_draft'`, return WP_Error with status 400 and message "Alleen conceptfacturen kunnen worden verwijderd." if not draft
   - Call `$this->clear_pdf( $invoice_id )` to delete PDF file from disk
   - Call `$this->clear_qr_code( $invoice_id )` to delete QR code file from disk
   - Clear Mollie/Rabobank payment data: `delete_post_meta( $invoice_id, '_mollie_payment_id' )` and `delete_post_meta( $invoice_id, '_rabobank_payment_request_id' )`
   - Reset discipline cases: get `line_items` via `get_field( 'line_items', $invoice_id )`, loop through items, for each with non-empty `discipline_case`, call `update_field( 'is_charged', '', (int) $item['discipline_case'] )`
   - Force delete: `wp_delete_post( $invoice_id, true )` (skip trash so the invoice number is freed for `generate_next()`)
   - Return `rest_ensure_response( [ 'deleted' => true, 'id' => $invoice_id ] )`

In `src/api/client.js`:

3. Add `deleteInvoice` method to the `prmApi` object (after `resendInvoice`):
   ```js
   deleteInvoice: (id) => api.delete(`/rondo/v1/invoices/${id}`),
   ```
  </action>
  <verify>Run `npm run build` to confirm frontend compiles. Grep for `delete_invoice` in class-rest-invoices.php and `deleteInvoice` in client.js to confirm both exist.</verify>
  <done>DELETE /rondo/v1/invoices/{id} endpoint registered with draft-only guard, file cleanup, discipline case reset, and force delete. API client has deleteInvoice method.</done>
</task>

<task type="auto">
  <name>Task 2: React hook and delete button in FactuurDetail</name>
  <files>src/hooks/useInvoices.js, src/pages/Finance/FactuurDetail.jsx</files>
  <action>
In `src/hooks/useInvoices.js`:

1. Add `useDeleteInvoice()` export after `useResetPaymentState`:
   ```js
   export function useDeleteInvoice() {
     const queryClient = useQueryClient();
     return useMutation({
       mutationFn: async (id) => {
         const response = await prmApi.deleteInvoice(id);
         return response.data;
       },
       onSuccess: () => {
         queryClient.invalidateQueries({ queryKey: ['invoices'] });
         queryClient.invalidateQueries({ queryKey: ['invoiced-case-ids'] });
         queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
       },
     });
   }
   ```

In `src/pages/Finance/FactuurDetail.jsx`:

2. Import `useDeleteInvoice` alongside existing hook imports from `@/hooks/useInvoices`. Also import `Trash2` from `lucide-react` alongside existing icon imports. Import `useNavigate` from `react-router-dom` alongside existing imports.

3. Inside the component, add:
   - `const navigate = useNavigate();`
   - `const deleteInvoice = useDeleteInvoice();`
   - Add `deleteInvoice.isPending` to the `isPending` combined check

4. Add `handleDelete` async handler (follow the same pattern as `handleSend`):
   - Show confirm dialog: `'Weet je zeker dat je deze conceptfactuur wilt verwijderen? Dit kan niet ongedaan worden gemaakt.'`
   - On confirm: `await deleteInvoice.mutateAsync(id)`, then `navigate('/financien/facturen')`
   - Catch errors: set errorMessage with `err.response?.data?.message` fallback

5. Add a red/destructive delete button in the draft status actions block, AFTER the existing draft buttons (after the PDF regenerate button, before the closing `</>`). Use this markup:
   ```jsx
   <button
     onClick={handleDelete}
     disabled={isPending}
     className="btn-secondary flex items-center gap-2 border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
   >
     {deleteInvoice.isPending ? (
       <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-red-600 dark:border-red-400"></div>
     ) : (
       <Trash2 className="w-4 h-4" />
     )}
     Verwijder factuur
   </button>
   ```
   This follows the exact same styling pattern as the existing "Reset factuur (test)" orange button.
  </action>
  <verify>Run `npm run build` to confirm frontend compiles without errors. Run `npm run lint` and note any new warnings (0 new expected).</verify>
  <done>useDeleteInvoice hook invalidates correct query keys. FactuurDetail shows red "Verwijder factuur" button for draft invoices only, with confirmation dialog, error handling, and post-delete navigation to invoices list.</done>
</task>

</tasks>

<verification>
1. `npm run build` succeeds with no new errors
2. Backend: DELETE route registered on `/rondo/v1/invoices/(?P<id>\d+)` with `check_financieel_permission`
3. Backend: `delete_invoice()` guards against non-draft status, cleans up PDF/QR files, resets discipline cases, force-deletes post
4. Frontend: Delete button appears only for draft invoices, shows confirmation, navigates to list on success
5. Invoice number reuse: automatic via `wp_delete_post` + `generate_next()` querying existing posts
</verification>

<success_criteria>
- Draft invoice can be deleted from the detail page
- Non-draft invoices show no delete button
- After deletion, user is back on the invoices list
- Deleted invoice's number becomes available for the next created invoice
- Linked discipline cases have is_charged reset
- PDF and QR files removed from disk
</success_criteria>

<output>
After completion, create `.planning/quick/83-delete-draft-invoices-with-number-reuse/83-SUMMARY.md`
</output>
