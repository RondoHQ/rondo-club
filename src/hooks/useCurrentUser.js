import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Hook for fetching the current authenticated user.
 * Centralized query to ensure deduplication across all components.
 *
 * Used by: ApprovalCheck, FairplayRoute, Sidebar, UserMenu, FinancesCard, PersonDetail
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
