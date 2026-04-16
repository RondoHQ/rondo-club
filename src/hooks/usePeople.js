import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml, formatPersonName, isDutchMobilePhone } from '@/utils/formatters';
import { trackNoteAdded } from '@/hooks/useEngagementTracking';

// Query keys
export const peopleKeys = {
  all: ['people'],
  lists: () => [...peopleKeys.all, 'list'],
  list: (filters) => [...peopleKeys.lists(), filters],
  filtered: (filters) => [...peopleKeys.all, 'filtered', filters],
  filterOptions: () => [...peopleKeys.all, 'filter-options'],
  details: () => [...peopleKeys.all, 'detail'],
  detail: (id) => [...peopleKeys.details(), id],
  timeline: (id) => [...peopleKeys.detail(id), 'timeline'],
  todos: (id) => [...peopleKeys.detail(id), 'todos'],
};

// Transform person data to include thumbnail and other computed fields
function transformPerson(person) {
  // Extract thumbnail from embedded featured media
  const thumbnail = person._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
                    person._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url ||
                    null;

  // Decode HTML entities in the person's name
  const decodedName = decodeHtml(person.title?.rendered || '');

  return {
    ...person,
    id: person.id,
    name: decodedName,
    first_name: person.acf?.first_name || '',
    infix: person.acf?.infix || '',
    last_name: person.acf?.last_name || '',
    is_deceased: person.is_deceased || false,
    birth_year: person.birth_year || null,
    modified: person.modified || null,
    thumbnail,
  };
}

// Hooks
export function usePeople(params = {}, options = {}) {
  const { enabled = true } = options;

  return useQuery({
    queryKey: peopleKeys.list(params),
    queryFn: async () => {
      const allPeople = [];
      let page = 1;
      const perPage = 100;

      let hasMore = true;
      while (hasMore) {
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
          hasMore = false;
          break;
        }

        // Also check x-wp-totalpages header as a safety check
        const totalPages = parseInt(response.headers['x-wp-totalpages'] || response.headers['X-WP-TotalPages'] || '0', 10);
        if (totalPages > 0 && page >= totalPages) {
          hasMore = false;
          break;
        }

        page++;
      }

      return allPeople;
    },
    enabled,
  });
}

/**
 * Hook for fetching filtered and paginated people
 *
 * Uses the server-side filtering endpoint for efficient queries
 * on large datasets (1400+ contacts).
 *
 * @param {Object} filters - Filter and pagination options
 * @param {number} filters.page - Page number (default: 1)
 * @param {number} filters.perPage - Results per page (default: 100, max: 100)
 * @param {string} filters.ownership - 'mine', 'shared', or 'all' (default: 'all')
 * @param {number} filters.modifiedDays - Only people modified within N days
 * @param {number} filters.birthYearFrom - Filter by birth year (minimum, inclusive)
 * @param {number} filters.birthYearTo - Filter by birth year (maximum, inclusive)
 * @param {number} filters.birthMonth - Filter by birth month (1-12)
 * @param {string} filters.orderby - 'first_name', 'last_name', or 'modified' (default: 'first_name')
 * @param {string} filters.order - 'asc' or 'desc' (default: 'asc')
 * @param {string} filters.huidigeVrijwilliger - '1' for yes, '0' for no, '' for all
 * @param {string} filters.financieleBlokkade - '1' for yes, '0' for no, '' for all
 * @param {string} filters.typeLid - Filter by member type value
 * @param {string} filters.fotoMissing - '1' to show only people without photo date
 * @param {string} filters.vogMissing - '1' to show only people without VOG date
 * @param {number} filters.vogOlderThanYears - Filter for VOG older than N years
 * @param {string} filters.vogEmailStatus - 'sent' or 'not_sent' to filter by email status
 * @param {string} filters.vogType - 'nieuw', 'vernieuwing', or '' for all
 * @param {string} filters.vogJustisStatus - 'submitted' or 'not_submitted' to filter by Justis status
 * @param {string} filters.vogReminderStatus - 'sent' or 'not_sent' to filter by reminder status
 * @param {string} filters.lidTotFuture - '1' to show only people with lid-tot date in the future
 * @param {Object} options - TanStack Query options (staleTime, enabled, etc.)
 * @returns {Object} TanStack Query result with data, isLoading, error, etc.
 */
export function buildFilteredPeopleParams(filters = {}) {
  return {
    page: filters.page || 1,
    per_page: filters.perPage || 100,
    ownership: filters.ownership || 'all',
    modified_days: filters.modifiedDays || null,
    birth_year_from: filters.birthYearFrom || null,
    birth_year_to: filters.birthYearTo || null,
    birth_month: filters.birthMonth || null,
    orderby: filters.orderby || 'first_name',
    order: filters.order || 'asc',
    huidig_vrijwilliger: filters.huidigeVrijwilliger || null,
    financiele_blokkade: filters.financieleBlokkade || null,
    type_lid: filters.typeLid || null,
    foto_missing: filters.fotoMissing || null,
    vog_missing: filters.vogMissing || null,
    vog_older_than_years: filters.vogOlderThanYears || null,
    vog_expiring_within_days: filters.vogExpiringWithinDays || null,
    vog_email_status: filters.vogEmailStatus || null,
    vog_type: filters.vogType || null,
    leeftijdsgroep: filters.leeftijdsgroep || null,
    vog_justis_status: filters.vogJustisStatus || null,
    vog_reminder_status: filters.vogReminderStatus || null,
    include_former: filters.includeFormer || null,
    lid_tot_future: filters.lidTotFuture || null,
    spelactiviteit_no_team: filters.spelactiviteitNoTeam || null,
  };
}

export function useFilteredPeople(filters = {}, options = {}) {
  const params = buildFilteredPeopleParams(filters);

  return useQuery({
    // Include all filter params in query key for proper cache separation
    queryKey: peopleKeys.filtered(params),
    queryFn: async () => {
      const response = await prmApi.getFilteredPeople(params);

      // Response is already in correct shape from backend:
      // { people: [...], total: N, page: N, total_pages: N }
      return response.data;
    },
    // Keep previous data while fetching new page for smoother UX
    placeholderData: (previousData) => previousData,
    // Allow staleTime, enabled, etc. to be passed
    ...options,
  });
}

// Fetch every matching person across all pages. Backend caps per_page at 100,
// so we walk pages sequentially. Page 1 tells us total_pages; the rest fan out.
export async function fetchAllFilteredPeople(filters = {}) {
  const firstParams = { ...buildFilteredPeopleParams(filters), page: 1, per_page: 100 };
  const first = (await prmApi.getFilteredPeople(firstParams)).data;
  const totalPages = first.total_pages || 1;
  if (totalPages <= 1) return first.people || [];

  const rest = await Promise.all(
    Array.from({ length: totalPages - 1 }, (_, i) => i + 2).map(async (page) => {
      const response = await prmApi.getFilteredPeople({ ...firstParams, page });
      return response.data.people || [];
    })
  );

  return [...(first.people || []), ...rest.flat()];
}

/**
 * Hook for fetching dynamic filter options with counts.
 * Returns available age groups and member types from the database.
 *
 * @param {Object} options - TanStack Query options
 * @returns {Object} TanStack Query result with { total, age_groups, member_types }
 */
export function useFilterOptions(options = {}) {
  return useQuery({
    queryKey: peopleKeys.filterOptions(),
    queryFn: async () => {
      const response = await prmApi.getFilterOptions();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - options change only on sync
    ...options,
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
 * Handles: payload building, birthday creation.
 *
 * @param {Object} options - Hook options
 * @param {Function} options.onSuccess - Called with created person data after successful creation
 * @returns {Object} TanStack Query mutation object
 */
export function useCreatePerson({ onSuccess } = {}) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data) => {
      // Determine phone field based on type
      const isMobile = isDutchMobilePhone(data.phone) || (data.phone_type || 'mobile') === 'mobile';

      // Build the full payload with fixed contact fields
      const payload = {
        title: formatPersonName(data.first_name, data.infix, data.last_name),
        status: 'publish',
        acf: {
          first_name: data.first_name,
          infix: data.infix || '',
          last_name: data.last_name,
          nickname: data.nickname,
          gender: data.gender || null,
          pronouns: data.pronouns || null,
          email_1: data.email || '',
          mobile_1: isMobile && data.phone ? data.phone : '',
          telephone_1: !isMobile && data.phone ? data.phone : '',
        },
      };

      // Create the person
      const response = await wpApi.createPerson(payload);

      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
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
 * Add email address to existing person's fixed email fields.
 * Fetches fresh person data, checks for duplicate, writes to email_1 or email_2.
 *
 * @returns {Object} TanStack Query mutation object with mutate({ personId, email })
 */
export function useAddEmailToPerson() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ personId, email }) => {
      // Fetch fresh person data to get current emails
      const response = await wpApi.getPerson(personId, { _embed: true });
      const person = response.data;

      const email1 = (person.acf?.email_1 || '').toLowerCase();
      const email2 = (person.acf?.email_2 || '').toLowerCase();
      const normalizedEmail = email.toLowerCase();

      // Check if email already exists (case-insensitive)
      if (email1 === normalizedEmail || email2 === normalizedEmail) {
        return { alreadyExists: true, person: transformPerson(person) };
      }

      // Add to first available slot
      const updateField = !email1 ? 'email_1' : !email2 ? 'email_2' : null;
      if (!updateField) {
        // Both slots full, overwrite email_2
        await wpApi.updatePerson(personId, {
          acf: { email_2: normalizedEmail },
        });
      } else {
        await wpApi.updatePerson(personId, {
          acf: { [updateField]: normalizedEmail },
        });
      }

      // Return updated person
      const updated = await wpApi.getPerson(personId, { _embed: true });
      return { alreadyExists: false, person: transformPerson(updated.data) };
    },
    onSuccess: (_, { personId }) => {
      // Invalidate person detail and list caches
      queryClient.invalidateQueries({ queryKey: peopleKeys.detail(personId) });
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
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
      trackNoteAdded();
    },
  });
}

export function useDeleteNote() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ noteId }) => prmApi.deleteNote(noteId),
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
      trackNoteAdded();
    },
  });
}

export function useUpdateActivity() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ activityId, data }) => prmApi.updateActivity(activityId, data),
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
    mutationFn: ({ activityId }) => prmApi.deleteActivity(activityId),
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
    mutationFn: ({ todoId, data }) => prmApi.updateTodo(todoId, data),
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
    mutationFn: ({ todoId }) => prmApi.deleteTodo(todoId),
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
