import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export const clothingKeys = {
  all: ['clothing'],
  items: () => [...clothingKeys.all, 'items'],
  assignments: (filters = {}) => [...clothingKeys.all, 'assignments', filters],
  personProfile: (personId) => [...clothingKeys.all, 'person-profile', personId],
  overview: () => [...clothingKeys.all, 'overview'],
  settings: () => [...clothingKeys.all, 'settings'],
};

export function useClothingItems() {
  return useQuery({
    queryKey: clothingKeys.items(),
    queryFn: async () => {
      const response = await prmApi.getClothingItems();
      return response.data;
    },
  });
}

export function useCreateClothingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data) => prmApi.createClothingItem(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clothingKeys.items() });
    },
  });
}

export function useDeleteClothingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => prmApi.deleteClothingItem(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clothingKeys.items() });
      queryClient.invalidateQueries({ queryKey: clothingKeys.overview() });
    },
  });
}

export function useClothingAssignments(filters = {}) {
  return useQuery({
    queryKey: clothingKeys.assignments(filters),
    queryFn: async () => {
      const response = await prmApi.getClothingAssignments(filters);
      return response.data;
    },
  });
}

export function useCreateClothingAssignment() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data) => prmApi.createClothingAssignment(data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: clothingKeys.assignments({}) });
      queryClient.invalidateQueries({ queryKey: clothingKeys.overview() });
      if (variables?.person_id) {
        queryClient.invalidateQueries({ queryKey: clothingKeys.personProfile(variables.person_id) });
      }
    },
  });
}

export function useClothingPersonProfile(personId, options = {}) {
  return useQuery({
    queryKey: clothingKeys.personProfile(personId),
    queryFn: async () => {
      const response = await prmApi.getClothingPersonProfile(personId);
      return response.data;
    },
    enabled: !!personId && (options.enabled ?? true),
  });
}

export function useClothingOverview() {
  return useQuery({
    queryKey: clothingKeys.overview(),
    queryFn: async () => {
      const response = await prmApi.getClothingOverview();
      return response.data;
    },
  });
}

export function useClothingSettings() {
  return useQuery({
    queryKey: clothingKeys.settings(),
    queryFn: async () => {
      const response = await prmApi.getClothingSettings();
      return response.data;
    },
  });
}

export function useUpdateClothingSettings() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data) => prmApi.updateClothingSettings(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clothingKeys.settings() });
    },
  });
}
