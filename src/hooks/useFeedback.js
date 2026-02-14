import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

// Query keys
export const feedbackKeys = {
  all: ['feedback'],
  lists: () => [...feedbackKeys.all, 'list'],
  list: (filters) => [...feedbackKeys.lists(), filters],
  details: () => [...feedbackKeys.all, 'detail'],
  detail: (id) => [...feedbackKeys.details(), id],
  comments: (id) => [...feedbackKeys.all, 'comments', id],
};

/**
 * Fetch list of feedback items with optional filters.
 * @param {Object} filters - Optional filters (type, status, etc.)
 * @returns {Object} TanStack Query result
 */
export function useFeedbackList(filters = {}) {
  return useQuery({
    queryKey: feedbackKeys.list(filters),
    queryFn: async () => {
      const response = await prmApi.getFeedbackList(filters);
      return response.data;
    },
  });
}

/**
 * Fetch single feedback item by ID.
 * @param {number|string} id - Feedback post ID
 * @returns {Object} TanStack Query result
 */
export function useFeedback(id) {
  return useQuery({
    queryKey: feedbackKeys.detail(id),
    queryFn: async () => {
      const response = await prmApi.getFeedback(id);
      return response.data;
    },
    enabled: !!id,
  });
}

/**
 * Create new feedback item.
 * @returns {Object} TanStack Query mutation object
 */
export function useCreateFeedback() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data) => prmApi.createFeedback(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: feedbackKeys.lists() });
    },
  });
}

/**
 * Update existing feedback item.
 * @returns {Object} TanStack Query mutation object with mutate({ id, data })
 */
export function useUpdateFeedback() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }) => prmApi.updateFeedback(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: feedbackKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: feedbackKeys.lists() });
    },
  });
}

/**
 * Delete feedback item.
 * @returns {Object} TanStack Query mutation object
 */
export function useDeleteFeedback() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => prmApi.deleteFeedback(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: feedbackKeys.lists() });
      queryClient.invalidateQueries({ queryKey: feedbackKeys.details() });
    },
  });
}

/**
 * Fetch comments for a feedback item.
 * @param {number|string} id - Feedback post ID
 * @returns {Object} TanStack Query result
 */
export function useFeedbackComments(id) {
  return useQuery({
    queryKey: feedbackKeys.comments(id),
    queryFn: async () => {
      const response = await prmApi.getFeedbackComments(id);
      return response.data;
    },
    enabled: !!id,
  });
}

/**
 * Create a comment on a feedback item.
 * @returns {Object} TanStack Query mutation object with mutate({ id, content })
 */
export function useCreateFeedbackComment() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, content }) => prmApi.createFeedbackComment(id, { content, author_type: 'user' }),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: feedbackKeys.comments(id) });
      queryClient.invalidateQueries({ queryKey: feedbackKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: feedbackKeys.lists() });
    },
  });
}
