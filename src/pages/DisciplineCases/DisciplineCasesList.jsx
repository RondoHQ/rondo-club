import { useState, useMemo, useEffect } from 'react';
import { Gavel, Filter } from 'lucide-react';
import { useQueryClient, useQuery } from '@tanstack/react-query';
import {
  useDisciplineCases,
  useSeasons,
  useCurrentSeason,
} from '@/hooks/useDisciplineCases';
import { wpApi } from '@/api/client';
import DisciplineCaseTable from '@/components/DisciplineCaseTable';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';

export default function DisciplineCasesList() {
  const queryClient = useQueryClient();

  // Fetch current season to set as default
  const { data: currentSeason, isLoading: isCurrentSeasonLoading } =
    useCurrentSeason();

  // Season filter state - initialize to null (will be set after currentSeason loads)
  const [selectedSeasonId, setSelectedSeasonId] = useState(null);
  const [hasInitialized, setHasInitialized] = useState(false);

  // Set default season when currentSeason loads
  useEffect(() => {
    if (currentSeason && !hasInitialized) {
      setSelectedSeasonId(currentSeason.id);
      setHasInitialized(true);
    } else if (!currentSeason && !isCurrentSeasonLoading && !hasInitialized) {
      // No current season set, use null (all seasons)
      setHasInitialized(true);
    }
  }, [currentSeason, isCurrentSeasonLoading, hasInitialized]);

  // Fetch seasons for dropdown
  const { data: seasons = [] } = useSeasons();

  // Fetch discipline cases (filtered by season if selected)
  const {
    data: cases,
    isLoading: isCasesLoading,
    error: casesError,
  } = useDisciplineCases({
    seizoen: selectedSeasonId,
    enabled: hasInitialized, // Wait until default season is determined
  });

  // Collect person IDs from cases
  const personIds = useMemo(() => {
    if (!cases) return [];
    const ids = cases.map((dc) => dc.acf?.person).filter(Boolean);
    return [...new Set(ids)];
  }, [cases]);

  // Batch fetch persons for table display
  const { data: personsData } = useQuery({
    queryKey: ['people', 'batch', personIds.sort().join(',')],
    queryFn: async () => {
      if (personIds.length === 0) return [];
      const response = await wpApi.getPeople({
        per_page: 100,
        include: personIds.join(','),
        _embed: true,
      });
      return response.data;
    },
    enabled: personIds.length > 0,
  });

  // Create person map for table
  const personMap = useMemo(() => {
    const map = new Map();
    if (personsData) {
      personsData.forEach((person) => {
        // Person name fields are in the acf object, not at root level
        const firstName = person.acf?.first_name || '';
        const infix = person.acf?.infix || '';
        const lastName = person.acf?.last_name || '';
        const fullName = person.title?.rendered || [firstName, infix, lastName].filter(Boolean).join(' ');

        // Extract thumbnail from embedded featured media (same logic as transformPerson in usePeople.js)
        const thumbnail = person._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
                         person._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url ||
                         null;

        map.set(person.id, {
          id: person.id,
          first_name: firstName,
          name: fullName,
          thumbnail,
        });
      });
    }
    return map;
  }, [personsData]);

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['discipline-cases'] });
    await queryClient.invalidateQueries({ queryKey: ['seasons'] });
  };

  const handleSeasonChange = (e) => {
    const value = e.target.value;
    setSelectedSeasonId(value === '' ? null : parseInt(value, 10));
  };

  const isLoading =
    isCurrentSeasonLoading || (hasInitialized && isCasesLoading);

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
              Tuchtzaken
            </h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Overzicht van tuchtrechtelijke procedures en dossiers
            </p>
          </div>

          {/* Season Filter */}
          <div className="flex items-center gap-2">
            <Filter className="w-4 h-4 text-gray-400" />
            <select
              value={selectedSeasonId ?? ''}
              onChange={handleSeasonChange}
              className="text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
            >
              <option value="">Alle seizoenen</option>
              {seasons.map((season) => (
                <option key={season.id} value={season.id}>
                  {season.name}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Error state */}
        {casesError && (
          <div className="card p-6 text-center">
            <p className="text-red-600 dark:text-red-400">
              Tuchtzaken konden niet worden geladen.
            </p>
          </div>
        )}

        {/* Table */}
        <div className="card">
          <DisciplineCaseTable
            cases={cases}
            showPersonColumn={true}
            personMap={personMap}
            isLoading={isLoading}
          />
        </div>

        {/* Empty state for filtered view */}
        {!isLoading && !casesError && cases?.length === 0 && selectedSeasonId && (
          <div className="card p-12 text-center">
            <div className="flex justify-center mb-4">
              <div className="p-4 bg-gray-100 rounded-full dark:bg-gray-700">
                <Gavel className="w-12 h-12 text-gray-400 dark:text-gray-500" />
              </div>
            </div>
            <h2 className="text-xl font-semibold text-gray-900 mb-2 dark:text-gray-100">
              Geen tuchtzaken in dit seizoen
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              Selecteer een ander seizoen of bekijk alle seizoenen.
            </p>
          </div>
        )}
      </div>
    </PullToRefreshWrapper>
  );
}
