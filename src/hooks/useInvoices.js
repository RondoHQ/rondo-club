import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Get discipline case IDs that already have invoices for a person
 * @param {number} personId - Person ID
 * @param {object} options - TanStack Query options
 * @returns {object} Query result with array of invoiced case IDs
 */
export function useInvoicedCaseIds(personId, options = {}) {
  return useQuery({
    queryKey: ['invoiced-case-ids', personId],
    queryFn: async () => {
      const response = await prmApi.getInvoicedCaseIds(personId);
      return response.data.case_ids;
    },
    enabled: !!personId && (options.enabled ?? true),
    staleTime: 30000, // 30 seconds - invoiced status changes infrequently during a session
    ...options,
  });
}

/**
 * Get invoices for a specific person
 * @param {number} personId - Person ID
 * @param {object} options - TanStack Query options
 * @returns {object} Query result with array of invoice objects
 */
export function usePersonInvoices(personId, options = {}) {
  return useQuery({
    queryKey: ['invoices', 'person', personId],
    queryFn: async () => {
      const response = await prmApi.getInvoices({ person_id: personId });
      return response.data;
    },
    enabled: !!personId && (options.enabled ?? true),
    staleTime: 30000, // 30 seconds
    ...options,
  });
}

/**
 * Create a new invoice
 * @returns {object} Mutation object for creating invoices
 */
export function useCreateInvoice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data) => {
      const response = await prmApi.createInvoice(data);
      return response.data;
    },
    onSuccess: () => {
      // Invalidate invoiced case IDs, all invoices, and person-specific invoices
      queryClient.invalidateQueries({ queryKey: ['invoiced-case-ids'] });
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
    },
  });
}

export function useUpdateDraftInvoice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, data }) => {
      const response = await prmApi.updateDraftInvoice(id, data);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
      queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
      queryClient.invalidateQueries({ queryKey: ['invoiced-case-ids'] });
    },
  });
}


/**
 * Get preview of the next invoice number
 * @returns {object} Query result
 */
export function useNextInvoiceNumber(options = {}) {
  return useQuery({
    queryKey: ['invoices', 'next-number'],
    queryFn: async () => {
      const response = await prmApi.getNextInvoiceNumber();
      return response.data;
    },
    staleTime: 30000,
    ...options,
  });
}

/**
 * Get all invoices (optionally filtered)
 * @param {object} params - Query parameters (status, person_id)
 * @param {object} options - TanStack Query options
 * @returns {object} Query result with array of invoice objects
 */
export function useInvoices(params = {}, options = {}) {
  return useQuery({
    queryKey: ['invoices', params],
    queryFn: async () => {
      const response = await prmApi.getInvoices(params);
      return response.data;
    },
    staleTime: 30000, // 30 seconds
    enabled: options.enabled ?? true,
    ...options,
  });
}

/**
 * Get a single invoice by ID
 * @param {number} id - Invoice ID
 * @param {object} options - TanStack Query options
 * @returns {object} Query result with invoice detail object
 */
export function useInvoice(id, options = {}) {
  return useQuery({
    queryKey: ['invoice', id],
    queryFn: async () => {
      const response = await prmApi.getInvoice(id);
      return response.data;
    },
    enabled: !!id && (options.enabled ?? true),
    staleTime: 30000, // 30 seconds
    ...options,
  });
}

/**
 * Send a draft invoice
 * @returns {object} Mutation object for sending invoices
 */
export function useSendInvoice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) => {
      const payload = typeof input === 'object' && input !== null ? input : { id: input };
      const response = await prmApi.sendInvoice(payload.id, payload.recipient ? { recipient: payload.recipient } : {});
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
      queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
    },
  });
}

/**
 * Update invoice status
 * @returns {object} Mutation object for updating invoice status
 */
export function useUpdateInvoiceStatus() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, status }) => {
      const response = await prmApi.updateInvoiceStatus(id, status);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
      queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
    },
  });
}

/**
 * Add a manual correction line to a draft invoice
 * @returns {object} Mutation object
 */
export function useAddDraftInvoiceLineItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, description, amount }) => {
      const response = await prmApi.addDraftInvoiceLineItem(id, description, amount);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
      queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
    },
  });
}

/**
 * Generate PDF for an invoice
 * @returns {object} Mutation object for generating invoice PDFs
 */
export function useGenerateInvoicePdf() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (id) => {
      const response = await prmApi.generateInvoicePdf(id);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
    },
  });
}

/**
 * Resend invoice email
 * @returns {object} Mutation object for resending invoices
 */
export function useResendInvoice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) => {
      const payload = typeof input === 'object' && input !== null ? input : { id: input };
      const response = await prmApi.resendInvoice(payload.id, payload.recipient ? { recipient: payload.recipient } : {});
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
    },
  });
}

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

/**
 * Delete a draft invoice
 * @returns {object} Mutation object for deleting invoices
 */
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

/**
 * Get all discipline case IDs that already have invoices (across all persons)
 * @param {object} options - TanStack Query options
 * @returns {object} Query result with array of invoiced case IDs
 */
export function useAllInvoicedCaseIds(options = {}) {
  return useQuery({
    queryKey: ['invoiced-case-ids', 'all'],
    queryFn: async () => {
      const response = await prmApi.getAllInvoicedCaseIds();
      return response.data.case_ids;
    },
    staleTime: 30000, // 30 seconds
    ...options,
  });
}

/**
 * Bulk create invoices from an array of discipline case IDs
 * Groups by person — one invoice per person.
 * @returns {object} Mutation object for bulk invoice creation
 */
export function useBulkCreateInvoices() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (caseIds) => {
      const response = await prmApi.bulkCreateInvoices(caseIds);
      return response.data;
    },
    onSuccess: () => {
      // Invalidate invoiced case IDs, all invoices, and person-specific invoices
      queryClient.invalidateQueries({ queryKey: ['invoiced-case-ids'] });
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
    },
  });
}

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

/**
 * Update family discount percentage on a sent/unpaid membership invoice
 * @returns {object} Mutation object
 */
export function useUpdateMembershipInvoiceDiscount() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, familyDiscountPercent, entryDiscountPercent }) => {
      const response = await prmApi.updateMembershipInvoiceDiscount(id, familyDiscountPercent, entryDiscountPercent);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['invoice'] });
      queryClient.invalidateQueries({ queryKey: ['invoices', 'person'] });
    },
  });
}
