import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Query keys for volunteer role settings
 */
export const volunteerRoleKeys = {
  all: ['volunteer-roles'],
  settings: () => [...volunteerRoleKeys.all, 'settings'],
};

/**
 * Hook for fetching volunteer role classification settings
 *
 * Returns current configuration for player roles and excluded roles.
 * These settings control volunteer status calculation and team member display.
 *
 * @returns {Object} Query result with:
 *   - data.player_roles: Array of role names that identify players
 *   - data.excluded_roles: Array of honorary/membership roles
 *   - data.default_player_roles: Backend defaults for player roles
 *   - data.default_excluded_roles: Backend defaults for excluded roles
 */
export function useVolunteerRoleSettings() {
  return useQuery({
    queryKey: volunteerRoleKeys.settings(),
    queryFn: async () => {
      const response = await prmApi.getVolunteerRoleSettings();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - settings rarely change
  });
}
