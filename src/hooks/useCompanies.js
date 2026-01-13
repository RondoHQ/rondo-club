import { useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';

/**
 * Create a new company/organization.
 * Handles payload building with all required ACF fields.
 *
 * @param {Object} options - Hook options
 * @param {Function} options.onSuccess - Called with created company data after successful creation
 * @returns {Object} TanStack Query mutation object
 */
export function useCreateCompany({ onSuccess } = {}) {
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
          investors: data.investors || [],
          _visibility: data.visibility || 'private',
          _assigned_workspaces: data.assigned_workspaces || [],
        },
      };

      const response = await wpApi.createCompany(payload);
      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      // Call custom onSuccess if provided
      onSuccess?.(result);
    },
  });
}

/**
 * Bulk update multiple companies at once.
 * Updates visibility, workspace assignments, and/or labels for selected companies.
 *
 * @returns {Object} TanStack Query mutation object with mutate({ ids, updates })
 */
export function useBulkUpdateCompanies() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ ids, updates }) => {
      const response = await prmApi.bulkUpdateCompanies(ids, updates);
      return response.data;
    },
    onSuccess: async () => {
      // Refetch companies list to show updated data immediately
      await queryClient.refetchQueries({ queryKey: ['companies'] });
    },
  });
}
