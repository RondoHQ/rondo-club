import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml } from '@/utils/formatters';
import { meetingsKeys } from './useMeetings';

// Query keys
export const peopleKeys = {
  all: ['people'],
  lists: () => [...peopleKeys.all, 'list'],
  list: (filters) => [...peopleKeys.lists(), filters],
  details: () => [...peopleKeys.all, 'detail'],
  detail: (id) => [...peopleKeys.details(), id],
  timeline: (id) => [...peopleKeys.detail(id), 'timeline'],
  dates: (id) => [...peopleKeys.detail(id), 'dates'],
  todos: (id) => [...peopleKeys.detail(id), 'todos'],
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
    is_deceased: person.is_deceased || false,
    birth_year: person.birth_year || null,
    modified: person.modified || null,
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

export function usePersonTodos(personId) {
  return useQuery({
    queryKey: peopleKeys.todos(personId),
    queryFn: async () => {
      const response = await prmApi.getPersonTodos(personId);
      return response.data;
    },
    enabled: !!personId,
  });
}

/**
 * Create a new person with all associated data.
 * Handles: payload building, Gravatar sideload, birthday creation.
 *
 * @param {Object} options - Hook options
 * @param {Function} options.onSuccess - Called with created person data after successful creation
 * @returns {Object} TanStack Query mutation object
 */
export function useCreatePerson({ onSuccess } = {}) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data) => {
      // Build contact_info array from individual fields
      const contactInfo = [];
      if (data.email) {
        contactInfo.push({
          contact_type: 'email',
          contact_value: data.email,
          contact_label: 'Email',
        });
      }
      if (data.phone) {
        contactInfo.push({
          contact_type: data.phone_type || 'mobile',
          contact_value: data.phone,
          contact_label: data.phone_type === 'mobile' ? 'Mobile' : 'Phone',
        });
      }

      // Build the full payload
      const payload = {
        title: `${data.first_name} ${data.last_name}`.trim(),
        status: 'publish',
        acf: {
          first_name: data.first_name,
          last_name: data.last_name,
          nickname: data.nickname,
          gender: data.gender || null,
          pronouns: data.pronouns || null,
          how_we_met: data.how_we_met,
          is_favorite: data.is_favorite,
          contact_info: contactInfo,
          _visibility: data.visibility || 'private',
          _assigned_workspaces: data.assigned_workspaces || [],
        },
      };

      // Create the person
      const response = await wpApi.createPerson(payload);
      const personId = response.data.id;

      // Try to sideload Gravatar if email is provided
      if (data.email) {
        try {
          await prmApi.sideloadGravatar(personId, data.email);
        } catch {
          // Gravatar sideload failed silently - not critical
        }
      }

      // Create birthday if provided
      if (data.birthday && data.birthdayType) {
        try {
          await wpApi.createDate({
            title: `${data.first_name}'s Birthday`,
            status: 'publish',
            date_type: [data.birthdayType.id],
            acf: {
              date_value: data.birthday,
              is_recurring: true,
              related_people: [personId],
              _visibility: 'private',
            },
          });
        } catch {
          // Birthday creation failed silently - not critical
        }
      }

      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      // Invalidate meetings to trigger re-matching of attendees
      queryClient.invalidateQueries({ queryKey: meetingsKeys.today });
      queryClient.invalidateQueries({ queryKey: ['person-meetings'] });
      // Call custom onSuccess if provided
      onSuccess?.(result);
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
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });

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

/**
 * Add email address to existing person's contact_info.
 * Fetches fresh person data, checks for duplicate, adds email, triggers calendar re-matching.
 *
 * @returns {Object} TanStack Query mutation object with mutate({ personId, email })
 */
export function useAddEmailToPerson() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ personId, email }) => {
      // Fetch fresh person data to get current contact_info
      const response = await wpApi.getPerson(personId, { _embed: true });
      const person = response.data;

      const currentContacts = person.acf?.contact_info || [];

      // Check if email already exists (case-insensitive)
      const emailExists = currentContacts.some(
        c => c.contact_type === 'email' &&
             c.contact_value.toLowerCase() === email.toLowerCase()
      );

      if (emailExists) {
        return { alreadyExists: true, person: transformPerson(person) };
      }

      // Add new email
      const newContact = {
        contact_type: 'email',
        contact_value: email.toLowerCase(),
        contact_label: 'Email',
      };

      await wpApi.updatePerson(personId, {
        acf: {
          first_name: person.acf?.first_name || '',
          last_name: person.acf?.last_name || '',
          contact_info: [...currentContacts, newContact],
        },
      });

      // Return updated person
      const updated = await wpApi.getPerson(personId, { _embed: true });
      return { alreadyExists: false, person: transformPerson(updated.data) };
    },
    onSuccess: (_, { personId }) => {
      // Invalidate person detail and list caches
      queryClient.invalidateQueries({ queryKey: peopleKeys.detail(personId) });
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
      // Invalidate meetings to trigger re-matching (email was added)
      queryClient.invalidateQueries({ queryKey: meetingsKeys.today });
      queryClient.invalidateQueries({ queryKey: meetingsKeys.date });
      queryClient.invalidateQueries({ queryKey: ['person-meetings'] });
    },
  });
}

export function useDeletePerson() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => wpApi.deletePerson(id, { force: true }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
      queryClient.invalidateQueries({ queryKey: peopleKeys.details() });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

/**
 * Bulk update multiple people at once.
 * Updates visibility and/or workspace assignments for selected people.
 *
 * @returns {Object} TanStack Query mutation object with mutate({ ids, updates })
 */
export function useBulkUpdatePeople() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ ids, updates }) => prmApi.bulkUpdatePeople(ids, updates),
    onSuccess: async () => {
      // Refetch people list to show updated data immediately
      await queryClient.refetchQueries({ queryKey: peopleKeys.lists() });
      // Invalidate details for individual person views
      queryClient.invalidateQueries({ queryKey: peopleKeys.details() });
    },
  });
}

// Notes mutations
export function useCreateNote() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ personId, content, visibility = 'private' }) =>
      prmApi.createNote(personId, content, visibility),
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

export function useUpdateActivity() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ activityId, data, personId }) => prmApi.updateActivity(activityId, data),
    onSuccess: (_, { personId }) => {
      if (personId) {
        queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
      }
    },
  });
}

export function useDeleteActivity() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ activityId, personId }) => prmApi.deleteActivity(activityId),
    onSuccess: (_, { personId }) => {
      if (personId) {
        queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
      }
    },
  });
}

// Todo mutations
export function useCreateTodo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ personId, data }) => prmApi.createTodo(personId, data),
    onSuccess: (_, { personId }) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
      queryClient.invalidateQueries({ queryKey: peopleKeys.todos(personId) });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['todos'] });
    },
  });
}

export function useUpdateTodo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ todoId, data, personId }) => prmApi.updateTodo(todoId, data),
    onSuccess: (_, { personId }) => {
      if (personId) {
        queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
        queryClient.invalidateQueries({ queryKey: peopleKeys.todos(personId) });
      }
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['todos'] });
    },
  });
}

export function useDeleteTodo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ todoId, personId }) => prmApi.deleteTodo(todoId),
    onSuccess: (_, { personId }) => {
      if (personId) {
        queryClient.invalidateQueries({ queryKey: peopleKeys.timeline(personId) });
        queryClient.invalidateQueries({ queryKey: peopleKeys.todos(personId) });
      }
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['todos'] });
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
