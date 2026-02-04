import { useFilteredPeople } from '@/hooks/usePeople';

/**
 * Hook for getting the count of volunteers needing VOG action.
 * Used in navigation badge to show how many volunteers need attention.
 *
 * Filters:
 * - huidig-vrijwilliger = true (current volunteers only)
 * - No VOG date OR VOG date older than 3 years
 *
 * @returns {Object} { count: number, isLoading: boolean }
 */
export function useVOGCount() {
  const { data, isLoading } = useFilteredPeople(
    {
      page: 1,
      perPage: 1, // Only need count, not full data
      huidigeVrijwilliger: '1',
      vogMissing: '1',
      vogOlderThanYears: 3,
    },
    {
      staleTime: 5 * 60 * 1000, // 5 minutes - badge doesn't need real-time updates
    }
  );

  return {
    count: data?.total || 0,
    isLoading,
  };
}
