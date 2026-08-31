import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export function useMyGuestPasses() {
  return useQuery({
    queryKey: ['guest-passes', 'me'],
    queryFn: async () => {
      const response = await prmApi.getMyGuestPasses();
      return response.data;
    },
  });
}

export function useCreateGuestPassSlot() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (slot) => {
      const response = await prmApi.createGuestPassSlot(slot);
      return response.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['guest-passes', 'me'] }),
  });
}

export function useReplaceGuestPassSlot() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (slot) => {
      const response = await prmApi.replaceGuestPassSlot(slot);
      return response.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['guest-passes', 'me'] }),
  });
}
