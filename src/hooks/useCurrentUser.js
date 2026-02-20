import { useQuery, useMutation } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Hook for fetching the current authenticated user.
 * Centralized query to ensure deduplication across all components.
 *
 * Used by: ApprovalCheck, FairplayRoute, Sidebar, UserMenu, FinancesCard, PersonDetail, Profile
 *
 * @returns {Object} TanStack Query result with user data
 */
export function useCurrentUser() {
  return useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - user data rarely changes
    retry: false,
  });
}

/**
 * Hook for changing the current user's password.
 * On success the backend destroys all sessions; the page redirects to login.
 *
 * @returns {Object} TanStack Mutation result
 */
export function useChangePassword() {
  return useMutation({
    mutationFn: ({ currentPassword, newPassword }) =>
      prmApi.changePassword({ current_password: currentPassword, new_password: newPassword }),
  });
}
