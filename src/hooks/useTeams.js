import { useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';

/**
 * Create a new team/organization.
 * Handles payload building with all required ACF fields.
 *
 * @param {Object} options - Hook options
 * @param {Function} options.onSuccess - Called with created team data after successful creation
 * @returns {Object} TanStack Query mutation object
 */
export function useCreateTeam({ onSuccess } = {}) {
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

      const response = await wpApi.createTeam(payload);
      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['teams'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      // Call custom onSuccess if provided
      onSuccess?.(result);
    },
  });
}

/**
 * Bulk update multiple teams at once.
 * Updates visibility, workspace assignments, and/or labels for selected teams.
 *
 * @returns {Object} TanStack Query mutation object with mutate({ ids, updates })
 */
export function useBulkUpdateTeams() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ ids, updates }) => {
      const response = await prmApi.bulkUpdateTeams(ids, updates);
      return response.data;
    },
    onSuccess: async () => {
      // Refetch teams list to show updated data immediately
      await queryClient.refetchQueries({ queryKey: ['teams'] });
    },
  });
}
