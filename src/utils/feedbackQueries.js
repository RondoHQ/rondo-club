// Query keys
export const feedbackKeys = {
  all: ['feedback'],
  lists: () => [...feedbackKeys.all, 'list'],
  list: (filters) => [...feedbackKeys.lists(), filters],
  details: () => [...feedbackKeys.all, 'detail'],
  detail: (id) => [...feedbackKeys.details(), String(id)],
  comments: (id) => [...feedbackKeys.all, 'comments', String(id)],
};

/** Load all pages because feedback tables filter and sort in the browser. */
export function feedbackListOptions(filters, fetchPage) {
  return {
    queryKey: feedbackKeys.list(filters),
    refetchOnMount: 'always',
    queryFn: async () => {
      const response = await fetchPage({ ...filters, page: 1 });
      const pages = Number(response.headers['x-wp-totalpages'] || 1);
      const remaining = await Promise.all(
        Array.from({ length: Math.max(0, pages - 1) }, (_, index) => (
          fetchPage({ ...filters, page: index + 2 })
        ))
      );
      return [...response.data, ...remaining.flatMap((page) => page.data)];
    },
  };
}

/** Finish refreshing inactive overviews before the edit dialog closes. */
export async function refreshUpdatedFeedback(queryClient, id, feedback) {
  await queryClient.cancelQueries({ queryKey: feedbackKeys.detail(id) });
  queryClient.setQueryData(feedbackKeys.detail(id), feedback);
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: feedbackKeys.lists(), refetchType: 'all' }),
    queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
  ]);
}
