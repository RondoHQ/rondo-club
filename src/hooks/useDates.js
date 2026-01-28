import { useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi } from '@/api/client';

/**
 * Create a new important date.
 * Handles payload building with all required ACF fields.
 *
 * @param {Object} options - Hook options
 * @param {Function} options.onSuccess - Called after successful creation
 * @returns {Object} TanStack Query mutation object
 */
export function useCreateDate({ onSuccess } = {}) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data) => {
      const payload = {
        title: data.title,
        status: 'publish',
        date_type: data.date_type,
        acf: {
          date_value: data.date_value,
          related_people: data.related_people,
          is_recurring: data.is_recurring,
          year_unknown: data.year_unknown,
        },
      };

      const response = await wpApi.createDate(payload);
      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      // Call custom onSuccess if provided
      onSuccess?.(result);
    },
  });
}
