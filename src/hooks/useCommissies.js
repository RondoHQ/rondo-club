import { useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';

/**
 * Create a new commissie/organization.
 * Handles payload building with all required ACF fields.
 *
 * @param {Object} options - Hook options
 * @param {Function} options.onSuccess - Called with created commissie data after successful creation
 * @returns {Object} TanStack Query mutation object
 */
export function useCreateCommissie({ onSuccess } = {}) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data) => {
      const payload = {
        title: data.title,
        status: 'publish',
        parent: data.parentId || 0,
        acf: {
          website: data.website,
          industry: data.industry,
        },
      };

      const response = await wpApi.createCommissie(payload);
      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['commissies'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      // Call custom onSuccess if provided
      onSuccess?.(result);
    },
  });
}

/**
 * Bulk update multiple commissies at once.
 * Updates visibility, workspace assignments, and/or labels for selected commissies.
 *
 * @returns {Object} TanStack Query mutation object with mutate({ ids, updates })
 */
export function useBulkUpdateCommissies() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ ids, updates }) => {
      const response = await prmApi.bulkUpdateCommissies(ids, updates);
      return response.data;
    },
    onSuccess: async () => {
      // Refetch commissies list to show updated data immediately
      await queryClient.refetchQueries({ queryKey: ['commissies'] });
    },
  });
}
