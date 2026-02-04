import { useFilteredPeople } from '@/hooks/usePeople';

/**
 * Hook for getting VOG-related counts for the navigation badge.
 *
 * Returns three counts:
 * - needsVog: Volunteers who need a new VOG (missing or expired)
 * - emailSent: Volunteers who have received a VOG email (but don't have valid VOG yet)
 * - justisSubmitted: Volunteers who have been submitted to Justis (but don't have valid VOG yet)
 *
 * @returns {Object} { needsVog, emailSent, justisSubmitted, isLoading }
 */
export function useVOGCount() {
  // Count: needs VOG (no date or expired, no email sent yet)
  const { data: needsVogData, isLoading: isLoadingNeedsVog } = useFilteredPeople(
    {
      page: 1,
      perPage: 1,
      huidigeVrijwilliger: '1',
      vogMissing: '1',
      vogOlderThanYears: 3,
      vogEmailStatus: 'not_sent',
    },
    {
      staleTime: 5 * 60 * 1000,
    }
  );

  // Count: email sent (but VOG still missing/expired)
  const { data: emailSentData, isLoading: isLoadingEmailSent } = useFilteredPeople(
    {
      page: 1,
      perPage: 1,
      huidigeVrijwilliger: '1',
      vogMissing: '1',
      vogOlderThanYears: 3,
      vogEmailStatus: 'sent',
      vogJustisStatus: 'not_submitted',
    },
    {
      staleTime: 5 * 60 * 1000,
    }
  );

  // Count: submitted to Justis (but VOG still missing/expired)
  const { data: justisSubmittedData, isLoading: isLoadingJustis } = useFilteredPeople(
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
    needsVog: needsVogData?.total || 0,
    emailSent: emailSentData?.total || 0,
    justisSubmitted: justisSubmittedData?.total || 0,
    isLoading: isLoadingNeedsVog || isLoadingEmailSent || isLoadingJustis,
  };
}
