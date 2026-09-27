import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/api/client';
import { refreshShiftCalendars } from '@/utils/shiftQueryCache';

const endpoint = '/rondo/v1/sportpark/closures';
const key = ['sportpark-closures'];

export function useSportparkClosures(year) {
  return useQuery({
    queryKey: [...key, year],
    queryFn: async () => (await api.get(endpoint, { params: { year } })).data,
  });
}

export function useSaveSportparkClosure() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...data }) => (await (id ? api.put(`${endpoint}/${id}`, data) : api.post(endpoint, data))).data,
    onSuccess: () => Promise.all([
      client.invalidateQueries({ queryKey: key, refetchType: 'all' }),
      client.invalidateQueries({ queryKey: ['volunteer'], refetchType: 'all' }),
      refreshShiftCalendars(client),
    ]),
  });
}

export function useDeleteSportparkClosure() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: async (id) => (await api.delete(`${endpoint}/${id}`)).data,
    onSuccess: () => client.invalidateQueries({ queryKey: key, refetchType: 'all' }),
  });
}
