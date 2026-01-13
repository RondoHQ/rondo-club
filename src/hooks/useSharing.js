import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Get shares for a post
 *
 * @param {string} postType - 'people' or 'companies'
 * @param {number} postId - The post ID
 */
export function useShares(postType, postId) {
  return useQuery({
    queryKey: ['shares', postType, postId],
    queryFn: async () => {
      const response = await prmApi.getPostShares(postId, postType);
      return response.data;
    },
    enabled: !!postId,
  });
}

/**
 * Add share mutation
 */
export function useAddShare() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ postType, postId, userId, permission }) => {
      const response = await prmApi.sharePost(postId, postType, {
        user_id: userId,
        permission,
      });
      return response.data;
    },
    onSuccess: (_, { postType, postId }) => {
      queryClient.invalidateQueries({ queryKey: ['shares', postType, postId] });
    },
  });
}

/**
 * Remove share mutation
 */
export function useRemoveShare() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ postType, postId, userId }) => {
      await prmApi.unsharePost(postId, postType, userId);
    },
    onSuccess: (_, { postType, postId }) => {
      queryClient.invalidateQueries({ queryKey: ['shares', postType, postId] });
    },
  });
}

/**
 * Search users for sharing
 *
 * @param {string} query - Search query (min 2 chars)
 */
export function useUserSearch(query) {
  return useQuery({
    queryKey: ['users', 'search', query],
    queryFn: async () => {
      const response = await prmApi.searchUsers(query);
      return response.data;
    },
    enabled: query?.length >= 2,
  });
}
