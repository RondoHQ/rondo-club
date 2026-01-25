import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

// Default dashboard card configuration
export const DEFAULT_DASHBOARD_CARDS = [
  'stats',
  'reminders',
  'todos',
  'awaiting',
  'meetings',
  'recent-contacted',
  'recent-edited',
  'favorites',
];

export function useDashboard() {
  return useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => {
      const response = await prmApi.getDashboard();
      return response.data;
    },
  });
}

export function useReminders(daysAhead = 30) {
  return useQuery({
    queryKey: ['reminders', daysAhead],
    queryFn: async () => {
      const response = await prmApi.getReminders(daysAhead);
      return response.data;
    },
  });
}

export function useTodos(status = 'open') {
  return useQuery({
    queryKey: ['todos', status],
    queryFn: async () => {
      const response = await prmApi.getAllTodos(status);
      return response.data;
    },
  });
}

export function useUpdateTodo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ todoId, data }) => prmApi.updateTodo(todoId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['todos'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      // Also invalidate timeline since todos now appear there
      queryClient.invalidateQueries({ queryKey: ['people', 'timeline'] });
    },
  });
}

export function useDeleteTodo() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (todoId) => prmApi.deleteTodo(todoId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['todos'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      // Also invalidate timeline since todos now appear there
      queryClient.invalidateQueries({ queryKey: ['people', 'timeline'] });
    },
  });
}

export function useSearch(query) {
  // Ensure query is always a string to maintain consistent hook calls
  const searchQuery = typeof query === 'string' ? query : '';
  const enabled = searchQuery.length >= 2;

  return useQuery({
    queryKey: ['search', searchQuery],
    queryFn: async () => {
      if (!enabled) {
        return { people: [], teams: [] };
      }
      const response = await prmApi.search(searchQuery);
      return response.data;
    },
    enabled,
  });
}

export function useDashboardSettings() {
  return useQuery({
    queryKey: ['dashboardSettings'],
    queryFn: async () => {
      const response = await prmApi.getDashboardSettings();
      return response.data;
    },
  });
}

export function useUpdateDashboardSettings() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (settings) => prmApi.updateDashboardSettings(settings),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboardSettings'] });
    },
  });
}
