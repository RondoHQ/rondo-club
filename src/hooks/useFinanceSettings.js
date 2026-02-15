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
