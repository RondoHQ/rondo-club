import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const keys = { all: ['communications'], detail: (id) => ['communications', id], comments: (id) => ['communications', id, 'comments'] };

export function useCommunications() {
  return useQuery({ queryKey: keys.all, queryFn: async () => (await prmApi.getCommunications()).data });
}

export function useCommunication(id) {
  return useQuery({ queryKey: keys.detail(id), queryFn: async () => (await prmApi.getCommunication(id)).data, enabled: Boolean(id) });
}

function useRefreshMutation(mutationFn) {
  const client = useQueryClient();
  return useMutation({ mutationFn, onSuccess: (data) => {
    client.invalidateQueries({ queryKey: keys.all });
    if (data?.data?.id) client.setQueryData(keys.detail(data.data.id), data.data);
  } });
}

export function useCreateCommunication() { return useRefreshMutation((data) => prmApi.createCommunication(data)); }
export function useUpdateCommunication() { return useRefreshMutation(({ id, data }) => prmApi.updateCommunication(id, data)); }
export function useCommunicationAction() { return useRefreshMutation(({ id, data }) => prmApi.actionCommunication(id, data)); }
export function useSeriesAction() { return useRefreshMutation(({ id, data }) => prmApi.actionCommunicationSeries(id, data)); }
export function useUploadCommunication() { return useRefreshMutation(({ id, file }) => prmApi.uploadCommunicationAttachment(id, file)); }

export function useCommunicationComments(id) {
  return useQuery({ queryKey: keys.comments(id), queryFn: async () => (await prmApi.getCommunicationComments(id)).data, enabled: Boolean(id) });
}

export function useAddCommunicationComment() {
  const client = useQueryClient();
  return useMutation({ mutationFn: ({ id, content }) => prmApi.addCommunicationComment(id, content), onSuccess: (_, { id }) => {
    client.invalidateQueries({ queryKey: keys.comments(id) });
    client.invalidateQueries({ queryKey: keys.detail(id) });
  } });
}
