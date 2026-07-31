import { useState, useMemo, useEffect, useCallback } from 'react';
import { Download, Gavel } from 'lucide-react';
import { useQueryClient, useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
  useDisciplineCases,
  useSeasons,
  useCurrentSeason,
} from '@/hooks/useDisciplineCases';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useAllInvoicedCaseIds, useBulkCreateInvoices } from '@/hooks/useInvoices';
import { wpApi } from '@/api/client';
import DisciplineCaseTable from '@/components/DisciplineCaseTable';
import { getDoorbelastLabel, isDoorbelastException, isDoorbelastNVT } from '@/utils/disciplineCases';
import { buildCsv, downloadCsv } from '@/utils/csvExport';
import { format, parseYmd } from '@/utils/dateFormat';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { DataTableToolbar, ColumnSettingsPanel, useColumnVisibility, createColumn, FILTER_TYPES } from '@/components/DataTable';

function getSpeeldag(activiteit) {
  if (!activiteit) return '';
  const parts = activiteit.split(/veld\s*-\s*/i);
  return parts.length > 1 ? parts[parts.length - 1].trim() : activiteit;
}

export default function DisciplineCasesList() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  const { data: currentUser } = useCurrentUser();
  const canEditFinancieel = currentUser?.can_edit_financieel ?? false;
  const canAccessFairplay = currentUser?.can_access_fairplay ?? false;
  const canCreateInvoice = canAccessFairplay && canEditFinancieel;

  const [selectedCaseIds, setSelectedCaseIds] = useState(new Set());
  const { data: invoicedCaseIds = [] } = useAllInvoicedCaseIds({ enabled: canCreateInvoice });
  const bulkCreate = useBulkCreateInvoices();

  const { data: currentSeason, isLoading: isCurrentSeasonLoading } =
    useCurrentSeason();

  const [selectedSeasonId, setSelectedSeasonId] = useState(null);
  const [hasInitialized, setHasInitialized] = useState(false);

  const [doorbelastFilter, setDoorbelastFilter] = useState('');
  const [sanctieFilter, setSanctieFilter] = useState('');
  const [persoonFilter, setPersoonFilter] = useState('');
  const [kaartFilter, setKaartFilter] = useState('');
  const [boeteFilter, setBoeteFilter] = useState('');
  const [teamFilter, setTeamFilter] = useState('');
  const [excludeTeamFilter, setExcludeTeamFilter] = useState('');
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);

  const { isVisible, toggle } = useColumnVisibility('tuchtzaken');

  const colVisColumns = [
    { id: 'team', label: 'Team', isVisible: isVisible('team') },
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
    const ids = cases.map((dc) => dc.fields?.person).filter(Boolean);
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
        const firstName = person.fields?.first_name || '';
        const infix = person.fields?.infix || '';
        const lastName = person.fields?.last_name || '';
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

  // Fetch all teams for speeldag lookup
  const { data: teamsData } = useQuery({
    queryKey: ['teams', 'all'],
    queryFn: async () => {
      const response = await wpApi.getTeams({ per_page: 100 });
      return response.data;
    },
  });

  // Build team ID → speeldag map for resolving numeric team names
  const teamSpeeldagMap = useMemo(() => {
    const map = new Map();
    if (teamsData) {
      teamsData.forEach(team => {
        const speeldag = getSpeeldag(team.fields?.activiteit);
        if (speeldag) map.set(team.id, speeldag);
      });
    }
    return map;
  }, [teamsData]);

  // Format team name: use home_team/away_team ID to resolve speeldag for numeric names
  const formatTeamName = useCallback((acf) => {
    const name = acf?.team_name;
    if (!name) return '-';
    if (/^\d+$/.test(name.trim())) {
      const teamId = parseInt(acf.home_team || acf.away_team, 10);
      if (teamId && teamSpeeldagMap.has(teamId)) {
        return `${teamSpeeldagMap.get(teamId)} ${name.trim()}`;
      }
    }
    return name;
  }, [teamSpeeldagMap]);

  // Derive dynamic team filter options from loaded cases (with formatted display names)
  const teamFilterOptions = useMemo(() => {
    if (!cases) return [];
    // Build unique display names per raw team_name
    const displayMap = new Map();
    cases.forEach(dc => {
      const raw = dc.fields?.team_name;
      if (raw && !displayMap.has(raw)) {
        displayMap.set(raw, formatTeamName(dc.fields));
      }
    });
    return [...displayMap.entries()]
      .sort((a, b) => a[1].localeCompare(b[1]))
      .map(([value, label]) => ({ value, label }));
  }, [cases, formatTeamName]);

  // Dynamic filter column definitions (depends on teamFilterOptions)
  const filterColumns = useMemo(() => [
    createColumn({
      id: 'persoon',
      header: 'Persoon',
      filterType: FILTER_TYPES.TEXT,
    }),
    createColumn({
      id: 'team',
      header: 'Team',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: teamFilterOptions,
      getFilterLabel: (val) => {
        const opt = teamFilterOptions.find(o => o.value === val);
        return opt ? opt.label : val;
      },
    }),
    createColumn({
      id: 'excludeTeam',
      header: 'Uitsluiten team',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: teamFilterOptions,
      getFilterLabel: (val) => {
        const opt = teamFilterOptions.find(o => o.value === val);
        return opt ? opt.label : val;
      },
    }),
    createColumn({
      id: 'doorbelast',
      header: 'Doorbelast',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'exception', label: 'Uitzondering' },
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
  ], [teamFilterOptions]);

  const filteredCases = useMemo(() => {
    if (!cases) return [];
    return cases.filter(dc => {
      const acf = dc.fields || {};

      if (persoonFilter.trim() !== '') {
        const person = personMap.get(acf.person);
        const personName = (person ? (person.name || '') : '').toLowerCase();
        if (!personName.includes(persoonFilter.toLowerCase().trim())) return false;
      }

      if (teamFilter !== '') {
        if ((acf.team_name || '') !== teamFilter) return false;
      }

      if (excludeTeamFilter !== '') {
        if ((acf.team_name || '') === excludeTeamFilter) return false;
      }

      if (doorbelastFilter !== '') {
        const isNVT = isDoorbelastNVT(acf);
        const isException = isDoorbelastException(acf);
        if (doorbelastFilter === 'exception' && !isException) return false;
        if (doorbelastFilter === 'nvt' && !isNVT) return false;
        if (doorbelastFilter === 'none' && (acf.is_charged || isNVT || isException)) return false;
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
  }, [cases, persoonFilter, teamFilter, excludeTeamFilter, doorbelastFilter, sanctieFilter, kaartFilter, boeteFilter, personMap]);

  const hasActiveFilters = !!(persoonFilter || teamFilter || excludeTeamFilter || doorbelastFilter || sanctieFilter || kaartFilter || boeteFilter);
  const activeFilterCount = [persoonFilter, teamFilter, excludeTeamFilter, doorbelastFilter, sanctieFilter, kaartFilter, boeteFilter].filter(Boolean).length;

  const clearFilters = () => {
    setPersoonFilter('');
    setTeamFilter('');
    setExcludeTeamFilter('');
    setDoorbelastFilter('');
    setSanctieFilter('');
    setKaartFilter('');
    setBoeteFilter('');
  };

  const setFilter = (colId, value) => {
    if (colId === 'persoon') setPersoonFilter(value || '');
    else if (colId === 'team') setTeamFilter(value || '');
    else if (colId === 'excludeTeam') setExcludeTeamFilter(value || '');
    else if (colId === 'doorbelast') setDoorbelastFilter(value || '');
    else if (colId === 'sanctie') setSanctieFilter(value || '');
    else if (colId === 'kaart') setKaartFilter(value || '');
    else if (colId === 'boete') setBoeteFilter(value || '');
  };

  const handleBulkCreateInvoice = async () => {
    if (selectedCaseIds.size === 0) return;
    try {
      await bulkCreate.mutateAsync([...selectedCaseIds]);
      setSelectedCaseIds(new Set());
      navigate('/financien/facturen');
    } catch {
      alert('Facturen konden niet worden aangemaakt. Probeer het opnieuw.');
    }
  };

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['discipline-cases'] });
    await queryClient.invalidateQueries({ queryKey: ['seasons'] });
    await queryClient.invalidateQueries({ queryKey: ['invoiced-case-ids'] });
  };

  const handleSeasonChange = (e) => {
    const value = e.target.value;
    setSelectedCaseIds(new Set());
    setSelectedSeasonId(value === '' ? null : parseInt(value, 10));
  };

  const handleExportCsv = () => {
    const headers = [
      'Dossier',
      'Persoon',
      'Wedstrijd',
      'Wedstrijddatum',
      'Team',
      'Kaart',
      'Code',
      'Tenlastelegging',
      'Sanctie',
      'Doorbelast',
      'Boete',
    ];
    const rows = filteredCases.map((disciplineCase) => {
      const acf = disciplineCase.fields || {};
      const chargeCodes = (acf.charge_codes || '').trim();
      const person = personMap.get(acf.person);
      return [
        acf.dossier_id || '',
        person?.name || '',
        acf.match_description || '',
        acf.match_date ? format(parseYmd(acf.match_date), 'dd-MM-yyyy') : '',
        formatTeamName(acf),
        chargeCodes ? (chargeCodes.endsWith('-1') ? 'Geel' : 'Rood') : '',
        chargeCodes,
        acf.charge_description || '',
        acf.sanction_description || '',
        getDoorbelastLabel(acf),
        parseFloat(acf.administrative_fee) || 0,
      ];
    });
    const csv = buildCsv([headers, ...rows]);
    downloadCsv(csv, `tuchtzaken-${format(new Date(), 'yyyy-MM-dd')}.csv`);
  };

  const isLoading =
    isCurrentSeasonLoading || (hasInitialized && isCasesLoading);

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Client-side filter toolbar */}
        <DataTableToolbar
          columns={filterColumns}
          filters={{ persoon: persoonFilter, team: teamFilter, excludeTeam: excludeTeamFilter, doorbelast: doorbelastFilter, sanctie: sanctieFilter, kaart: kaartFilter, boete: boeteFilter }}
          onFilterChange={setFilter}
          onClearFilters={clearFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
          toolbarEnd={(
            <div className="flex items-center gap-2">
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
              <button
                onClick={handleExportCsv}
                className="btn-tertiary"
                title="Gefilterde tuchtzaken downloaden als CSV voor Excel"
                aria-label="Gefilterde tuchtzaken exporteren"
                disabled={filteredCases.length === 0}
              >
                <Download className="w-4 h-4" />
              </button>
            </div>
          )}
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
        {(isLoading || filteredCases?.length > 0) && (
          <div className="card">
            <DisciplineCaseTable
              cases={filteredCases}
              showPersonColumn={true}
              personMap={personMap}
              isLoading={isLoading}
              isColVisible={isVisible}
              formatTeamName={formatTeamName}
              canCreateInvoice={canCreateInvoice}
              selectedCaseIds={selectedCaseIds}
              onSelectionChange={setSelectedCaseIds}
              onCreateInvoice={handleBulkCreateInvoice}
              isCreatingInvoice={bulkCreate.isPending}
              invoicedCaseIds={new Set(invoicedCaseIds)}
            />
          </div>
        )}

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
              {selectedSeasonId || persoonFilter || teamFilter || excludeTeamFilter || doorbelastFilter || sanctieFilter || kaartFilter || boeteFilter
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
