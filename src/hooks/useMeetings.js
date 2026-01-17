import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import { prmApi } from '@/api/client';
import { peopleKeys } from './usePeople';

// Query keys
export const meetingsKeys = {
  all: ['meetings'],
  today: ['meetings', 'today'],
  forDate: (dateStr) => ['meetings', 'forDate', dateStr],
  person: (personId) => ['person-meetings', personId],
  notes: (eventId) => ['meeting-notes', eventId],
};

/**
 * Hook to fetch meetings for a person
 * Returns upcoming and past meetings where the person was matched as an attendee
 *
 * @param {number} personId - The person ID to fetch meetings for
 * @returns {Object} TanStack Query result with { upcoming, past, total_upcoming, total_past }
 */
export function usePersonMeetings(personId) {
  return useQuery({
    queryKey: meetingsKeys.person(personId),
    queryFn: async () => {
      const response = await prmApi.getPersonMeetings(personId);
      return response.data;
    },
    enabled: !!personId,
  });
}

/**
 * Hook to fetch meetings for a specific date
 * Returns meetings for the given date with matched attendees and their details
 *
 * @param {Date} date - The date to fetch meetings for
 * @returns {Object} TanStack Query result with { meetings, total, has_connections }
 */
export function useDateMeetings(date) {
  const dateStr = format(date, 'yyyy-MM-dd');
  return useQuery({
    queryKey: meetingsKeys.forDate(dateStr),
    queryFn: async () => {
      const response = await prmApi.getMeetingsForDate(dateStr);
      return response.data;
    },
    enabled: !!date,
    staleTime: 5 * 60 * 1000, // 5 minutes - meetings don't change often
    refetchInterval: 15 * 60 * 1000, // Refetch every 15 minutes
  });
}

/**
 * Hook to fetch today's meetings for the dashboard widget (legacy alias)
 * @returns {Object} TanStack Query result with { meetings, total, has_connections }
 */
export function useTodayMeetings() {
  return useDateMeetings(new Date());
}

/**
 * Hook to log a calendar event as an activity
 * Creates activity records for all matched people on the event
 *
 * @returns {Object} TanStack Query mutation object
 */
export function useLogMeetingAsActivity() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (eventId) => prmApi.logMeetingAsActivity(eventId),
    onSuccess: (data, eventId) => {
      // Invalidate all person-meetings queries since we don't know which people were affected
      queryClient.invalidateQueries({ queryKey: ['person-meetings'] });
      // Invalidate timeline queries - activities were created
      queryClient.invalidateQueries({ queryKey: peopleKeys.all });
    },
  });
}

/**
 * Hook to fetch meeting notes for a specific event
 *
 * @param {number} eventId - The calendar event ID
 * @returns {Object} TanStack Query result with { notes }
 */
export function useMeetingNotes(eventId) {
  return useQuery({
    queryKey: meetingsKeys.notes(eventId),
    queryFn: async () => {
      const response = await prmApi.getMeetingNotes(eventId);
      return response.data;
    },
    enabled: !!eventId,
  });
}

/**
 * Hook to update meeting notes for a specific event
 *
 * @returns {Object} TanStack Query mutation object
 */
export function useUpdateMeetingNotes() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ eventId, notes }) => prmApi.updateMeetingNotes(eventId, notes),
    onSuccess: (data, variables) => {
      // Invalidate the notes query for this event
      queryClient.invalidateQueries({ queryKey: meetingsKeys.notes(variables.eventId) });
    },
  });
}
