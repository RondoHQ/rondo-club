import { useQuery } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';

// Query keys for cache management
export const disciplineCaseKeys = {
  all: ['discipline-cases'],
  lists: () => [...disciplineCaseKeys.all, 'list'],
  list: (filters) => [...disciplineCaseKeys.lists(), filters],
  byPerson: (personId) => [...disciplineCaseKeys.all, 'person', personId],
  seasons: ['seasons'],
  currentSeason: ['current-season'],
};

/**
 * Hook to fetch discipline cases with optional season filter
 * @param {Object} options
 * @param {number|null} options.seizoen - Season term ID to filter by (null for all seasons)
 * @param {boolean} options.enabled - Whether to enable the query
 */
export function useDisciplineCases({ seizoen = null, enabled = true } = {}) {
  return useQuery({
    queryKey: disciplineCaseKeys.list({ seizoen }),
    queryFn: async () => {
      const params = {
        per_page: 100,
        _embed: true, // Include person data
        orderby: 'date',
        order: 'desc',
      };

      // Filter by season if specified
      if (seizoen) {
        params.seizoen = seizoen;
      }

      const response = await wpApi.getDisciplineCases(params);
      return response.data;
    },
    enabled,
  });
}

/**
 * Hook to fetch discipline cases for a specific person
 * @param {number} personId - Person post ID
 * @param {Object} options
 * @param {boolean} options.enabled - Whether to enable the query
 */
export function usePersonDisciplineCases(personId, { enabled = true } = {}) {
  return useQuery({
    queryKey: disciplineCaseKeys.byPerson(personId),
    queryFn: async () => {
      const params = {
        per_page: 100,
        _embed: true,
        orderby: 'date',
        order: 'desc',
      };

      // Fetch all and filter by person (ACF meta queries can be unreliable)
      const response = await wpApi.getDisciplineCases(params);

      // Client-side filter (ACF post_object returns integer)
      return response.data.filter(dc => dc.acf?.person === parseInt(personId, 10));
    },
    enabled: !!personId && enabled,
  });
}

/**
 * Hook to fetch all seasons (seizoen taxonomy terms)
 */
export function useSeasons() {
  return useQuery({
    queryKey: disciplineCaseKeys.seasons,
    queryFn: async () => {
      const response = await wpApi.getSeasons();
      return response.data;
    },
  });
}

/**
 * Hook to get current season term
 */
export function useCurrentSeason() {
  return useQuery({
    queryKey: disciplineCaseKeys.currentSeason,
    queryFn: async () => {
      const response = await prmApi.getCurrentSeason();
      return response.data;
    },
  });
}

/**
 * Hook to get count of discipline cases for the current season
 * Used in navigation badge
 */
export function useDisciplineCasesCount() {
  const { data: currentSeason, isLoading: isLoadingSeason } = useCurrentSeason();
  const { data: cases, isLoading: isLoadingCases } = useDisciplineCases({
    seizoen: currentSeason?.id || null,
    enabled: !!currentSeason?.id,
  });

  return {
    count: cases?.length || 0,
    isLoading: isLoadingSeason || isLoadingCases,
  };
}
