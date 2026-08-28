import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Query keys for fee data
 */
export const feeKeys = {
  all: ['fees'],
  list: (params) => [...feeKeys.all, 'list', params],
  summary: (params) => [...feeKeys.all, 'summary', params],
  person: (personId, params) => [...feeKeys.all, 'person', personId, params],
  bulkJob: ['fees', 'bulk-job'],
  billingSettings: (season) => ['fees', 'billing-settings', season],
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
 * Hook for fetching the fee summary (aggregated by category)
 * Lightweight alternative to useFeeList for the overview tab.
 *
 * @param {Object} params - Optional params (season, forecast)
 * @returns {Object} Query result with data, isLoading, error
 */
export function useFeeSummary(params = {}) {
  return useQuery({
    queryKey: feeKeys.summary(params),
    queryFn: async () => {
      const response = await prmApi.getFeeSummary(params);
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

/**
 * Hook for polling bulk invoice job status.
 * Automatically refetches every 2 seconds while a job is running.
 *
 * @param {boolean} processBatches - Whether polling may process the next batch.
 * @returns {Object} Query result with data (job status), isLoading, error
 */
export function useBulkInvoiceJob(processBatches = false) {
  return useQuery({
    queryKey: feeKeys.bulkJob,
    queryFn: async () => {
      const response = processBatches
        ? await prmApi.processBulkInvoiceJob()
        : await prmApi.getBulkInvoiceJobStatus();
      return response.data;
    },
    refetchInterval: (query) => {
      return query.state.data?.status === 'running' ? 2000 : false;
    },
  });
}

/**
 * Hook for fetching billing settings for a season.
 *
 * @param {string} season - Season key (e.g. '2025-2026')
 * @returns {Object} Query result with data, isLoading, error
 */
export function useBillingSettings(season) {
  return useQuery({
    queryKey: feeKeys.billingSettings(season),
    queryFn: async () => {
      const response = await prmApi.getBillingSettings({ season });
      return response.data;
    },
    enabled: !!season,
  });
}
