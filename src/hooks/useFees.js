import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Query keys for fee data
 */
export const feeKeys = {
  all: ['fees'],
  list: (params) => [...feeKeys.all, 'list', params],
  person: (personId, params) => [...feeKeys.all, 'person', personId, params],
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

/**
 * Hook for fetching fee data for a single person
 *
 * @param {number} personId - The person ID
 * @param {Object} params - Optional params (season filter)
 * @returns {Object} Query result with data, isLoading, error
 */
export function usePersonFee(personId, params = {}) {
  return useQuery({
    queryKey: feeKeys.person(personId, params),
    queryFn: async () => {
      const response = await prmApi.getPersonFee(personId, params);
      return response.data;
    },
    enabled: !!personId,
  });
}
