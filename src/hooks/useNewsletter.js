import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export const newsletterKeys = { all: ['newsletter'], detail: (id) => ['newsletter', 'draft', id] };
export function useNewsletterMetadata() {
  return useQuery({ queryKey: ['newsletter', 'metadata'], queryFn: async () => (await prmApi.getNewsletterMetadata()).data });
}
export function useNewsletter(id) {
  return useQuery({ queryKey: newsletterKeys.detail(id), queryFn: async () => (await prmApi.getNewsletter(id)).data, refetchOnWindowFocus: false });
}
export function useNewsletterLists(enabled = true) {
  return useQuery({ queryKey: ['newsletter', 'lists'], queryFn: async () => (await prmApi.getNewsletterLists()).data, enabled, staleTime: 60000, retry: false });
}
export function useNewsletterSegments(id, enabled) {
  return useQuery({ queryKey: ['newsletter', 'segments', id], queryFn: async () => (await prmApi.getNewsletterSegments(id)).data, enabled: Boolean(id) && enabled, staleTime: 60000, retry: false });
}
