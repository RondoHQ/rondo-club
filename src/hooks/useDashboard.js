import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

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

export function useSearch(query) {
  const enabled = Boolean(query && typeof query === 'string' && query.length >= 2);
  
  return useQuery({
    queryKey: ['search', query || ''],
    queryFn: async () => {
      if (!enabled) {
        return { people: [], companies: [], dates: [] };
      }
      const response = await prmApi.search(query);
      return response.data;
    },
    enabled,
  });
}
