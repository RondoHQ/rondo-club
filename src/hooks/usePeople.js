import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml } from '@/utils/formatters';

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

// Transform person data to include thumbnail and other computed fields
function transformPerson(person) {
  // Extract thumbnail from embedded featured media
  const thumbnail = person._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
                    person._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url ||
                    null;

  // Extract labels from embedded terms
  const labels = person._embedded?.['wp:term']?.flat()
    ?.filter(term => term?.taxonomy === 'person_label')
    ?.map(term => term.name) || [];

  // Decode HTML entities in the person's name
  const decodedName = decodeHtml(person.title?.rendered || '');

  return {
    ...person,
    id: person.id,
    name: decodedName,
    first_name: person.acf?.first_name || '',
    last_name: person.acf?.last_name || '',
    is_favorite: person.acf?.is_favorite || false,
    thumbnail,
    labels,
  };
}

// Hooks
export function usePeople(params = {}) {
  return useQuery({
    queryKey: peopleKeys.list(params),
    queryFn: async () => {
      const allPeople = [];
      let page = 1;
      const perPage = 100;
      
      while (true) {
        const response = await wpApi.getPeople({
          per_page: perPage,
          page,
          _embed: true,
          ...params,
        });
        
        const people = response.data.map(transformPerson);
        allPeople.push(...people);
        
        // If we got fewer results than per_page, we're on the last page
        if (people.length < perPage) {
          break;
        }
        
        // Also check x-wp-totalpages header as a safety check
        const totalPages = parseInt(response.headers['x-wp-totalpages'] || response.headers['X-WP-TotalPages'] || '0', 10);
        if (totalPages > 0 && page >= totalPages) {
          break;
        }
        
        page++;
      }
      
      return allPeople;
    },
  });
}

export function usePerson(id) {
  return useQuery({
    queryKey: peopleKeys.detail(id),
    queryFn: async () => {
      const response = await wpApi.getPerson(id, { _embed: true });
      return transformPerson(response.data);
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
    onSuccess: (_, { id, data }) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
      
      // If relationships were updated, invalidate cache for related people
      if (data?.acf?.relationships) {
        const relationships = Array.isArray(data.acf.relationships) ? data.acf.relationships : [];
        relationships.forEach(rel => {
          const relatedPersonId = rel.related_person;
          if (relatedPersonId) {
            queryClient.invalidateQueries({ queryKey: peopleKeys.detail(relatedPersonId) });
          }
        });
      }
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

// Date mutations
export function useDeleteDate() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ dateId, personId }) => wpApi.deleteDate(dateId),
    onSuccess: (_, { personId }) => {
      if (personId) {
        queryClient.invalidateQueries({ queryKey: peopleKeys.dates(personId) });
        queryClient.invalidateQueries({ queryKey: peopleKeys.detail(personId) });
      }
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
    },
  });
}
