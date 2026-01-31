import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Query keys for fee data
 */
export const feeKeys = {
  all: ['fees'],
  list: (params) => [...feeKeys.all, 'list', params],
};

/**
 * Hook for fetching the membership fee list
 *
 * @param {Object} params - Optional params (season filter)
 * @returns {Object} Query result with data, isLoading, error
 */
export function useFeeList(params = {}) {
  return useQuery({
    queryKey: feeKeys.list(params),
    queryFn: async () => {
      const response = await prmApi.getFeeList(params);
      return response.data;
    },
  });
}
