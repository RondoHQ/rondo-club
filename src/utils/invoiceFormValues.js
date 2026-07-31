/**
 * Map an invoice API object onto the InvoiceDraftForm `initialValues` shape.
 *
 * Shared by the draft edit screen and the "copy invoice" flow so both stay in
 * sync. When `copy` is true the due date is reset (a copied invoice gets a fresh
 * payment term rather than inheriting the original's, which is likely in the past).
 *
 * @param {object} invoice Invoice as returned by the REST API.
 * @param {{ copy?: boolean }} [options]
 */
export function mapInvoiceToInitialValues(invoice, { copy = false } = {}) {
  return {
    invoiceKind: invoice.invoice_kind || 'normal',
    invoiceTarget: invoice.person?.id ? 'member' : 'external',
    customerName: invoice.customer_name || '',
    customerAttention: invoice.customer_attention || '',
    customerEmail: invoice.customer_email || '',
    customerCcEmail: invoice.customer_cc_email || '',
    customerAddress: invoice.customer_address || '',
    personId: invoice.person?.id || null,
    personLabel: invoice.person?.name || '',
    dueDate: copy ? '' : (invoice.due_date || ''),
    // A copy starts unscheduled — the schedule belongs to the original invoice.
    scheduledSendDate: copy ? '' : (invoice.scheduled_send_date || ''),
    paymentAccountId: invoice.payment_account?.id || '',
    emailSubject: invoice.email_subject || '',
    emailBody: invoice.email_body_override || '',
    customFields: invoice.custom_fields || [],
    lineItems: (invoice.line_items || []).map((item) => ({
      description: item.description || '',
      amount: item.amount ?? '',
      discipline_case_id: item.discipline_case?.id || null,
    })),
  };
}
