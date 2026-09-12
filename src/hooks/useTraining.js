import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { canAccessFeature } from '@/utils/featureToggles';

export function useTrainingAccess() {
  const { data: user } = useCurrentUser();
  return { available: canAccessFeature('training', user?.is_admin), isAdmin: Boolean(user?.is_admin) };
}

export function useTrainingSchedules() {
  const { available } = useTrainingAccess();
  return useQuery({ queryKey: ['training', 'schedules'], queryFn: async () => (await api.get('/rondo/v1/training/schedules')).data, enabled: available, refetchInterval: 30000 });
}

export function useActiveTraining() {
  const { available } = useTrainingAccess();
  return useQuery({ queryKey: ['training', 'active'], queryFn: async () => (await api.get('/rondo/v1/training/active')).data, enabled: available, refetchInterval: 30000, staleTime: 0 });
}

export function useTrainingSettings() {
  const { available, isAdmin } = useTrainingAccess();
  return useQuery({ queryKey: ['training', 'settings'], queryFn: async () => (await api.get('/rondo/v1/training/settings')).data, enabled: available && isAdmin });
}

export function useTrainingMutation() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: async ({ path, method = 'post', data }) => (await api.request({ url: `/rondo/v1/training/${path}`, method, data })).data,
    onSuccess: () => client.invalidateQueries({ queryKey: ['training'] }),
  });
}
