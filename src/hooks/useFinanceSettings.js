import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Hook for fetching finance settings
 *
 * @returns {Object} Query result with data, isLoading, error
 */
export function useFinanceSettings() {
  return useQuery({
    queryKey: ['finance-settings'],
    queryFn: async () => {
      const response = await prmApi.getFinanceSettings();
      return response.data;
    },
  });
}

/**
 * Hook for updating finance settings
 *
 * @returns {Object} Mutation object with mutate function
 */
export function useUpdateFinanceSettings() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data) => {
      const response = await prmApi.updateFinanceSettings(data);
      return response.data;
    },
    onSuccess: (data) => {
      // Update cached data immediately
      queryClient.setQueryData(['finance-settings'], data);
    },
  });
}

/**
 * Hook for fetching Rabobank connection status
 *
 * @returns {Object} Query result with data, isLoading, error
 */
export function useRabobankStatus() {
  return useQuery({
    queryKey: ['rabobank-status'],
    queryFn: async () => {
      const response = await prmApi.getRabobankStatus();
      return response.data;
    },
  });
}

/**
 * Hook for disconnecting Rabobank integration
 *
 * @returns {Object} Mutation object with mutate function
 */
export function useDisconnectRabobank() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      const response = await prmApi.disconnectRabobank();
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rabobank-status'] });
    },
  });
}

/**
 * Hook for creating payment links
 *
 * @returns {Object} Mutation object with mutate function
 */
export function useCreatePaymentLink() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (invoiceId) => {
      const response = await prmApi.createPaymentLink(invoiceId);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
    },
  });
}
