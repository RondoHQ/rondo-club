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
