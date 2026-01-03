import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';

// Query keys
export const peopleKeys = {
  all: ['people'],
  lists: () => [...peopleKeys.all, 'list'],
  list: (filters) => [...peopleKeys.lists(), filters],
  details: () => [...peopleKeys.all, 'detail'],
  detail: (id) => [...peopleKeys.details(), id],
  timeline: (id) => [...peopleKeys.detail(id), 'timeline'],
  dates: (id) => [...peopleKeys.detail(id), 'dates'],
};

// Hooks
export function usePeople(params = {}) {
  return useQuery({
    queryKey: peopleKeys.list(params),
    queryFn: async () => {
      const response = await wpApi.getPeople({
        per_page: 100,
        _embed: true,
        ...params,
      });
      return response.data;
    },
  });
}

export function usePerson(id) {
  return useQuery({
    queryKey: peopleKeys.detail(id),
    queryFn: async () => {
      const response = await wpApi.getPerson(id);
      return response.data;
    },
    enabled: !!id,
  });
}

export function usePersonTimeline(id) {
  return useQuery({
    queryKey: peopleKeys.timeline(id),
    queryFn: async () => {
      const response = await prmApi.getPersonTimeline(id);
      return response.data;
    },
    enabled: !!id,
  });
}

export function usePersonDates(id) {
  return useQuery({
    queryKey: peopleKeys.dates(id),
    queryFn: async () => {
      const response = await prmApi.getPersonDates(id);
      return response.data;
    },
    enabled: !!id,
  });
}

export function useCreatePerson() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data) => wpApi.createPerson(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
    },
  });
}

export function useUpdatePerson() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }) => wpApi.updatePerson(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
    },
  });
}

export function useDeletePerson() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id) => wpApi.deletePerson(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
    },
  });
}

// Notes mutations
export function useCreateNote() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ personId, content }) => prmApi.createNote(personId, content),
    onSuccess: (_, { personId }) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
    },
  });
}

export function useDeleteNote() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ noteId, personId }) => prmApi.deleteNote(noteId),
    onSuccess: (_, { personId }) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
    },
  });
}

// Activity mutations
export function useCreateActivity() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ personId, data }) => prmApi.createActivity(personId, data),
    onSuccess: (_, { personId }) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
    },
  });
}
