import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { peopleKeys } from './usePeople';

// Query keys
export const meetingsKeys = {
  all: ['meetings'],
  person: (personId) => ['person-meetings', personId],
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
