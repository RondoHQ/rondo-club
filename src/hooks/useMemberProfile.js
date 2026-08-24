import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const pendingEmailKey = ['member-profile', 'pending-email'];

function invalidateProfile(queryClient) {
  queryClient.invalidateQueries({ queryKey: ['household'] });
  queryClient.invalidateQueries({ queryKey: ['current-user'] });
}

export function usePendingProfileEmail(personId) {
  return useQuery({
    queryKey: [...pendingEmailKey, personId],
    queryFn: async () => (await prmApi.getPendingProfileEmail(personId)).data.pending,
  });
}

export function useRequestProfileEmailChange() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data) => prmApi.requestProfileEmailChange(data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: pendingEmailKey }),
  });
}

export function useCancelProfileEmailChange() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (personId) => prmApi.cancelProfileEmailChange(personId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: pendingEmailKey }),
  });
}

export function useRemoveSecondaryProfileEmail() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (personId) => prmApi.removeSecondaryProfileEmail(personId),
    onSuccess: () => invalidateProfile(queryClient),
  });
}

export function useUpdateProfilePhones() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data) => prmApi.updateProfilePhones(data),
    onSuccess: () => invalidateProfile(queryClient),
  });
}

export function useUpdateHouseholdAddress() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data) => prmApi.updateHouseholdAddress(data),
    onSuccess: () => invalidateProfile(queryClient),
  });
}

export function useAddHouseholdParent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ childId, data }) => prmApi.addHouseholdParent(childId, data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['household'] }),
  });
}

export function useProfileChangeLog(page = 1) {
  return useQuery({
    queryKey: ['profile-change-log', page],
    queryFn: async () => (await prmApi.getProfileChangeLog({ page, per_page: 50 })).data,
  });
}
