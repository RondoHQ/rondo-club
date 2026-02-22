import { useState, useMemo, useEffect } from 'react';
import { Gavel } from 'lucide-react';
import { useQueryClient, useQuery } from '@tanstack/react-query';
import {
  useDisciplineCases,
  useSeasons,
  useCurrentSeason,
} from '@/hooks/useDisciplineCases';
import { wpApi } from '@/api/client';
import DisciplineCaseTable from '@/components/DisciplineCaseTable';
import { isDoorbelastNVT } from '@/utils/disciplineCases';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { DataTableToolbar, ColumnSettingsPanel, useColumnVisibility, createColumn, FILTER_TYPES } from '@/components/DataTable';

// Static column definitions for the filter toolbar
const FILTER_COLUMNS = [
  createColumn({
    id: 'persoon',
    header: 'Persoon',
    filterType: FILTER_TYPES.TEXT,
  }),
  createColumn({
    id: 'doorbelast',
    header: 'Doorbelast',
    filterType: FILTER_TYPES.SELECT,
    filterOptions: [
      { value: 'nvt', label: 'N.v.t.' },
      { value: 'none', label: 'Nee' },
      { value: 'sportlink', label: 'Ja, Sportlink' },
      { value: 'rondo', label: 'Ja, Rondo' },
    ],
  }),
  createColumn({
    id: 'sanctie',
    header: 'Sanctie',
    filterType: FILTER_TYPES.TEXT,
  }),
  createColumn({
    id: 'kaart',
    header: 'Kaart',
    filterType: FILTER_TYPES.SELECT,
    filterOptions: [
      { value: 'geel', label: 'Geel' },
      { value: 'rood', label: 'Rood' },
      { value: 'geen', label: 'Geen kaart' },
    ],
    getFilterLabel: (val) => ({ geel: 'Geel', rood: 'Rood', geen: 'Geen kaart' }[val] || val),
  }),
  createColumn({
    id: 'boete',
    header: 'Boete',
    filterType: FILTER_TYPES.SELECT,
    filterOptions: [
      { value: 'heeft', label: 'Heeft boete' },
      { value: 'geen', label: 'Geen boete' },
    ],
    getFilterLabel: (val) => val === 'heeft' ? 'Heeft boete' : 'Geen boete',
  }),
];

export default function DisciplineCasesList() {
  const queryClient = useQueryClient();

  const { data: currentSeason, isLoading: isCurrentSeasonLoading } =
    useCurrentSeason();

  const [selectedSeasonId, setSelectedSeasonId] = useState(null);
  const [hasInitialized, setHasInitialized] = useState(false);

  const [doorbelastFilter, setDoorbelastFilter] = useState('');
  const [sanctieFilter, setSanctieFilter] = useState('');
  const [persoonFilter, setPersoonFilter] = useState('');
  const [kaartFilter, setKaartFilter] = useState('');
  const [boeteFilter, setBoeteFilter] = useState('');
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);

  const { isVisible, toggle } = useColumnVisibility('tuchtzaken');

  const colVisColumns = [
    { id: 'wedstrijd', label: 'Wedstrijd', isVisible: isVisible('wedstrijd') },
    { id: 'sanctie', label: 'Sanctie', isVisible: isVisible('sanctie') },
    { id: 'kaart', label: 'Kaart', isVisible: isVisible('kaart') },
    { id: 'doorbelast', label: 'Doorbelast', isVisible: isVisible('doorbelast') },
    { id: 'boete', label: 'Boete', isVisible: isVisible('boete') },
  ];

  useEffect(() => {
    if (currentSeason && !hasInitialized) {
      setSelectedSeasonId(currentSeason.id);
      setHasInitialized(true);
    } else if (!currentSeason && !isCurrentSeasonLoading && !hasInitialized) {
      setHasInitialized(true);
    }
  }, [currentSeason, isCurrentSeasonLoading, hasInitialized]);

  const { data: seasons = [] } = useSeasons();

  const {
    data: cases,
    isLoading: isCasesLoading,
    error: casesError,
  } = useDisciplineCases({
    seizoen: selectedSeasonId,
    enabled: hasInitialized,
  });

  const personIds = useMemo(() => {
    if (!cases) return [];
    const ids = cases.map((dc) => dc.acf?.person).filter(Boolean);
    return [...new Set(ids)];
  }, [cases]);

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

  const personMap = useMemo(() => {
    const map = new Map();
    if (personsData) {
      personsData.forEach((person) => {
        const firstName = person.acf?.first_name || '';
        const infix = person.acf?.infix || '';
        const lastName = person.acf?.last_name || '';
        const fullName = person.title?.rendered || [firstName, infix, lastName].filter(Boolean).join(' ');

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

  const filteredCases = useMemo(() => {
    if (!cases) return [];
    return cases.filter(dc => {
      const acf = dc.acf || {};

      if (persoonFilter.trim() !== '') {
        const person = personMap.get(acf.person);
        const personName = (person ? (person.name || '') : '').toLowerCase();
        if (!personName.includes(persoonFilter.toLowerCase().trim())) return false;
      }

      if (doorbelastFilter !== '') {
        const isNVT = isDoorbelastNVT(acf);
        if (doorbelastFilter === 'nvt' && !isNVT) return false;
        if (doorbelastFilter === 'none' && (acf.is_charged || isNVT)) return false;
        if (doorbelastFilter === 'sportlink' && acf.is_charged !== 'sportlink') return false;
        if (doorbelastFilter === 'rondo' && acf.is_charged !== 'rondo') return false;
      }

      if (sanctieFilter.trim() !== '') {
        const sanctionText = (acf.sanction_description || '').toLowerCase();
        if (!sanctionText.includes(sanctieFilter.toLowerCase().trim())) return false;
      }

      if (kaartFilter !== '') {
        const codes = (acf.charge_codes || '').trim();
        const isGeel = codes ? codes.endsWith('-1') : false;
        const isRood = codes ? !codes.endsWith('-1') : false;
        if (kaartFilter === 'geel' && !isGeel) return false;
        if (kaartFilter === 'rood' && !isRood) return false;
        if (kaartFilter === 'geen' && codes) return false;
      }

      if (boeteFilter !== '') {
        const fee = parseFloat(acf.administrative_fee) || 0;
        if (boeteFilter === 'heeft' && !(fee > 0)) return false;
        if (boeteFilter === 'geen' && fee > 0) return false;
      }

      return true;
    });
  }, [cases, persoonFilter, doorbelastFilter, sanctieFilter, kaartFilter, boeteFilter, personMap]);

  const hasActiveFilters = !!(persoonFilter || doorbelastFilter || sanctieFilter || kaartFilter || boeteFilter);
  const activeFilterCount = [persoonFilter, doorbelastFilter, sanctieFilter, kaartFilter, boeteFilter].filter(Boolean).length;

  const clearFilters = () => {
    setPersoonFilter('');
    setDoorbelastFilter('');
    setSanctieFilter('');
    setKaartFilter('');
    setBoeteFilter('');
  };

  const setFilter = (colId, value) => {
    if (colId === 'persoon') setPersoonFilter(value || '');
    else if (colId === 'doorbelast') setDoorbelastFilter(value || '');
    else if (colId === 'sanctie') setSanctieFilter(value || '');
    else if (colId === 'kaart') setKaartFilter(value || '');
    else if (colId === 'boete') setBoeteFilter(value || '');
  };

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
            <h1 className="text-2xl font-bold text-brand-gradient">
              Tuchtzaken
            </h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Overzicht van tuchtrechtelijke procedures en dossiers
            </p>
          </div>

          {/* Season filter (server-side — kept separate from client-side toolbar) */}
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

        {/* Client-side filter toolbar */}
        <DataTableToolbar
          columns={FILTER_COLUMNS}
          filters={{ persoon: persoonFilter, doorbelast: doorbelastFilter, sanctie: sanctieFilter, kaart: kaartFilter, boete: boeteFilter }}
          onFilterChange={setFilter}
          onClearFilters={clearFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
        />

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
            cases={filteredCases}
            showPersonColumn={true}
            personMap={personMap}
            isLoading={isLoading}
            isColVisible={isVisible}
          />
        </div>

        {/* Empty state for filtered view */}
        {!isLoading && !casesError && filteredCases?.length === 0 && (
          <div className="card p-12 text-center">
            <div className="flex justify-center mb-4">
              <div className="p-4 bg-gray-100 rounded-full dark:bg-gray-700">
                <Gavel className="w-12 h-12 text-gray-400 dark:text-gray-500" />
              </div>
            </div>
            <h2 className="text-xl font-semibold text-gray-900 mb-2 dark:text-gray-100">
              Geen tuchtzaken gevonden
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              {selectedSeasonId || persoonFilter || doorbelastFilter || sanctieFilter || kaartFilter || boeteFilter
                ? 'Pas de filters aan om meer resultaten te zien.'
                : 'Er zijn momenteel geen tuchtzaken.'}
            </p>
          </div>
        )}
      </div>

      <ColumnSettingsPanel
        isOpen={isColumnSettingsOpen}
        onClose={() => setIsColumnSettingsOpen(false)}
        columns={colVisColumns}
        onToggleColumn={toggle}
      />
    </PullToRefreshWrapper>
  );
}
