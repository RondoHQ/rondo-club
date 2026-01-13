import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

// List all workspaces for current user
export function useWorkspaces() {
  return useQuery({
    queryKey: ['workspaces'],
    queryFn: async () => {
      const response = await prmApi.getWorkspaces();
      return response.data;
    },
  });
}

// Get single workspace with members
export function useWorkspace(id) {
  return useQuery({
    queryKey: ['workspaces', id],
    queryFn: async () => {
      const response = await prmApi.getWorkspace(id);
      return response.data;
    },
    enabled: !!id,
  });
}

// Create workspace mutation
export function useCreateWorkspace() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data) => {
      const response = await prmApi.createWorkspace(data);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workspaces'] });
    },
  });
}

// Update workspace mutation
export function useUpdateWorkspace() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }) => {
      const response = await prmApi.updateWorkspace(id, data);
      return response.data;
    },
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['workspaces'] });
      queryClient.invalidateQueries({ queryKey: ['workspaces', id] });
    },
  });
}

// Delete workspace mutation
export function useDeleteWorkspace() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id) => {
      await prmApi.deleteWorkspace(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workspaces'] });
    },
  });
}

// Member management
export function useAddWorkspaceMember() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ workspaceId, userId, role }) => {
      const response = await prmApi.addWorkspaceMember(workspaceId, { user_id: userId, role });
      return response.data;
    },
    onSuccess: (_, { workspaceId }) => {
      queryClient.invalidateQueries({ queryKey: ['workspaces', workspaceId] });
    },
  });
}

export function useRemoveWorkspaceMember() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ workspaceId, userId }) => {
      await prmApi.removeWorkspaceMember(workspaceId, userId);
    },
    onSuccess: (_, { workspaceId }) => {
      queryClient.invalidateQueries({ queryKey: ['workspaces', workspaceId] });
    },
  });
}

export function useUpdateWorkspaceMember() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ workspaceId, userId, role }) => {
      const response = await prmApi.updateWorkspaceMember(workspaceId, userId, { role });
      return response.data;
    },
    onSuccess: (_, { workspaceId }) => {
      queryClient.invalidateQueries({ queryKey: ['workspaces', workspaceId] });
    },
  });
}

// Invite management
export function useWorkspaceInvites(workspaceId) {
  return useQuery({
    queryKey: ['workspaces', workspaceId, 'invites'],
    queryFn: async () => {
      const response = await prmApi.getWorkspaceInvites(workspaceId);
      return response.data;
    },
    enabled: !!workspaceId,
  });
}

export function useCreateWorkspaceInvite() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ workspaceId, email, role }) => {
      const response = await prmApi.createWorkspaceInvite(workspaceId, { email, role });
      return response.data;
    },
    onSuccess: (_, { workspaceId }) => {
      queryClient.invalidateQueries({ queryKey: ['workspaces', workspaceId, 'invites'] });
    },
  });
}

export function useRevokeWorkspaceInvite() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ workspaceId, inviteId }) => {
      await prmApi.revokeWorkspaceInvite(workspaceId, inviteId);
    },
    onSuccess: (_, { workspaceId }) => {
      queryClient.invalidateQueries({ queryKey: ['workspaces', workspaceId, 'invites'] });
    },
  });
}

// Public invite validation (for accept page)
export function useValidateInvite(token) {
  return useQuery({
    queryKey: ['invite', token],
    queryFn: async () => {
      const response = await prmApi.validateInvite(token);
      return response.data;
    },
    enabled: !!token,
    retry: false, // Don't retry on invalid token
  });
}

export function useAcceptInvite() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (token) => {
      const response = await prmApi.acceptInvite(token);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workspaces'] });
    },
  });
}
