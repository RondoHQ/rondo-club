import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { peopleKeys } from '@/hooks/usePeople';

export const sponsorKeys = {
  all: ['sponsors'],
  lists: () => ['sponsors', 'list'],
  list: (filters) => ['sponsors', 'list', filters],
  detail: (id) => ['sponsors', 'detail', String(id)],
};

export function useSponsors(filters = {}, options = {}) {
  return useQuery({
    queryKey: sponsorKeys.list(filters),
    queryFn: async () => (await prmApi.getSponsors(filters)).data,
    placeholderData: (previous) => previous,
    ...options,
  });
}

export function useSponsor(id, options = {}) {
  return useQuery({
    queryKey: sponsorKeys.detail(id),
    queryFn: async () => (await prmApi.getSponsor(id)).data,
    enabled: Boolean(id),
    ...options,
  });
}

function invalidateSponsorData(queryClient, sponsorId) {
  queryClient.invalidateQueries({ queryKey: sponsorKeys.all });
  if (sponsorId) queryClient.invalidateQueries({ queryKey: sponsorKeys.detail(sponsorId) });
  queryClient.invalidateQueries({ queryKey: peopleKeys.all });
  queryClient.invalidateQueries({ queryKey: ['narrowcasting', 'sponsors'] });
}

export function useCreateSponsor() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data) => prmApi.createSponsor(data),
    onSuccess: (response) => invalidateSponsorData(queryClient, response.data?.id),
  });
}

export function useUpdateSponsor() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }) => prmApi.updateSponsor(id, data),
    onSuccess: (_, { id }) => invalidateSponsorData(queryClient, id),
  });
}

export function useUploadSponsorLogo() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, file }) => prmApi.uploadSponsorLogo(id, file),
    onSuccess: (_, { id }) => {
      invalidateSponsorData(queryClient, id);
      queryClient.invalidateQueries({ queryKey: ['household'] });
    },
  });
}

export function useArchiveSponsor() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id) => prmApi.archiveSponsor(id),
    onSuccess: (_, id) => invalidateSponsorData(queryClient, id),
  });
}

export function useCreateSponsorContact() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ sponsorId, data }) => prmApi.createSponsorContact(sponsorId, data),
    onSuccess: (_, { sponsorId }) => invalidateSponsorData(queryClient, sponsorId),
  });
}

export function useSponsorPersonOptions(search, options = {}) {
  return useQuery({
    queryKey: ['sponsors', 'person-options', search],
    queryFn: async () => (await prmApi.searchSponsorPeople(search)).data,
    enabled: search.trim().length >= 2,
    staleTime: 30_000,
    ...options,
  });
}
