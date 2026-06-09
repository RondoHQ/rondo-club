import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const LIST_KEY = ['taakuitleg', 'list'];

/**
 * List all taakuitleg entries (published + drafts the user may edit).
 */
export function useTaakuitlegList() {
  return useQuery({
    queryKey: LIST_KEY,
    queryFn: async () => (await prmApi.getTaakuitleg({ per_page: 100, orderby: 'title', order: 'asc', status: 'publish,draft' })).data || [],
    staleTime: 60 * 1000,
  });
}

/**
 * Fetch a single taakuitleg in edit context (so post_content + acf are present).
 */
export function useTaakuitlegItem(id) {
  return useQuery({
    queryKey: ['taakuitleg', 'item', id],
    queryFn: async () => (await prmApi.getTaakuitlegItem(id)).data,
    enabled: !!id,
  });
}

/**
 * The dienst_types catalogue, used to populate the relationship multiselect.
 */
export function useDienstTypes() {
  return useQuery({
    queryKey: ['volunteer', 'dienst-types'],
    queryFn: async () => (await prmApi.getDienstTypes()).data || [],
    staleTime: 5 * 60 * 1000,
  });
}

/**
 * Create or update a taakuitleg.
 */
export function useSaveTaakuitleg(id) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload) => (id ? prmApi.updateTaakuitleg(id, payload) : prmApi.createTaakuitleg(payload)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LIST_KEY });
      if (id) {
        queryClient.invalidateQueries({ queryKey: ['taakuitleg', 'item', id] });
      }
    },
  });
}

/**
 * Delete a taakuitleg.
 */
export function useDeleteTaakuitleg() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id) => prmApi.deleteTaakuitleg(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LIST_KEY });
    },
  });
}
