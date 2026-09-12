import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';

export function useTrainingAccess() {
  const { data: user } = useCurrentUser();
  return { canManage: Boolean(user?.can_manage_training) };
}

export function useTrainingSchedules() {
  return useQuery({ queryKey: ['training', 'schedules'], queryFn: async () => (await api.get('/rondo/v1/training/schedules')).data, refetchInterval: 30000 });
}

export function useActiveTraining() {
  return useQuery({ queryKey: ['training', 'active'], queryFn: async () => (await api.get('/rondo/v1/training/active')).data, refetchInterval: 30000, staleTime: 0 });
}

export function useTrainingSettings() {
  const { canManage } = useTrainingAccess();
  return useQuery({ queryKey: ['training', 'settings'], queryFn: async () => (await api.get('/rondo/v1/training/settings')).data, enabled: canManage });
}

export function useTrainingMutation() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: async ({ path, method = 'post', data }) => (await api.request({ url: `/rondo/v1/training/${path}`, method, data })).data,
    onSuccess: () => client.invalidateQueries({ queryKey: ['training'] }),
  });
}
