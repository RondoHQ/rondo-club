import { useFilteredPeople } from '@/hooks/usePeople';

/**
 * Hook for getting VOG-related counts for the navigation badge.
 *
 * Returns two counts based on Justis submission status:
 * - notSubmittedToJustis: Volunteers who need VOG and have NOT been submitted to Justis yet
 * - submittedToJustis: Volunteers who need VOG and HAVE been submitted to Justis (waiting for VOG)
 *
 * @returns {Object} { notSubmittedToJustis, submittedToJustis, isLoading }
 */
export function useVOGCount() {
  // Count: needs VOG and NOT yet submitted to Justis
  const { data: notSubmittedData, isLoading: isLoadingNotSubmitted } = useFilteredPeople(
    {
      page: 1,
      perPage: 1,
      huidigeVrijwilliger: '1',
      vogMissing: '1',
      vogOlderThanYears: 3,
      vogJustisStatus: 'not_submitted',
    },
    {
      staleTime: 5 * 60 * 1000,
    }
  );

  // Count: needs VOG and submitted to Justis (waiting for VOG to arrive)
  const { data: submittedData, isLoading: isLoadingSubmitted } = useFilteredPeople(
    {
      page: 1,
      perPage: 1,
      huidigeVrijwilliger: '1',
      vogMissing: '1',
      vogOlderThanYears: 3,
      vogJustisStatus: 'submitted',
    },
    {
      staleTime: 5 * 60 * 1000,
    }
  );

  return {
    notSubmittedToJustis: notSubmittedData?.total || 0,
    submittedToJustis: submittedData?.total || 0,
    isLoading: isLoadingNotSubmitted || isLoadingSubmitted,
  };
}
