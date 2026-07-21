export const shiftCalendarKeys = {
  all: ['shift-calendar'],
  manage: (dienstType = '') => [...shiftCalendarKeys.all, 'manage', dienstType],
  signup: (dienstType = '') => [...shiftCalendarKeys.all, 'signup', dienstType],
};

/**
 * Refresh every cached calendar variant, including inactive routes and filters.
 *
 * The app disables refetch-on-mount globally, so invalidating an inactive
 * calendar is not enough to make it fresh when the user navigates back to it.
 */
export function refreshShiftCalendars(queryClient) {
  return queryClient.refetchQueries({
    queryKey: shiftCalendarKeys.all,
    type: 'all',
  });
}
