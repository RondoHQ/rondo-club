import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export function useAccessEventMatches() {
  return useQuery({
    queryKey: ['access-event-matches'],
    queryFn: async () => {
      const response = await prmApi.getAccessEventMatches();
      return response.data;
    },
    refetchInterval: 60_000,
  });
}

export function useSelectAccessEvent() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (sourceId) => {
      const response = await prmApi.selectAccessEvent(sourceId);
      return response.data;
    },
    onSuccess: (data) => {
      if (data?.event?.id && data?.stats) {
        queryClient.setQueryData(['access-event-stats', data.event.id], data.stats);
      }
    },
  });
}

export function useAccessEventStats(eventId) {
  return useQuery({
    queryKey: ['access-event-stats', eventId],
    queryFn: async () => {
      const response = await prmApi.getAccessEventStats(eventId);
      return response.data;
    },
    enabled: Boolean(eventId),
    refetchInterval: 5_000,
  });
}

export function useScanAccessEvent() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ eventId, token }) => {
      const response = await prmApi.scanAccessEvent(eventId, token);
      return response.data;
    },
    onSuccess: (data, variables) => {
      if (data?.stats) {
        queryClient.setQueryData(['access-event-stats', variables.eventId], data.stats);
      }
    },
  });
}
