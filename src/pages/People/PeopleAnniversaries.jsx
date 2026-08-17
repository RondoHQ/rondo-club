import { useCallback, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Users } from 'lucide-react';
import { prmApi } from '@/api/client';
import PersonAvatar from '@/components/PersonAvatar';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';
import { comparePersonNames, formatPersonSurname } from '@/utils/formatters';

const PERIOD_OPTIONS = [
  { value: 'future-3', label: 'Komende 3 maanden', daysAhead: 90, daysBack: 0 },
  { value: 'future-6', label: 'Komende 6 maanden', daysAhead: 180, daysBack: 0 },
  { value: 'future-9', label: 'Komende 9 maanden', daysAhead: 270, daysBack: 0 },
  { value: 'future-12', label: 'Komende 12 maanden', daysAhead: 360, daysBack: 0 },
  { value: 'past-6', label: 'Afgelopen 6 maanden', daysAhead: 0, daysBack: 180 },
  { value: 'past-12', label: 'Afgelopen 12 maanden', daysAhead: 0, daysBack: 360 },
  { value: 'past-18', label: 'Afgelopen 18 maanden', daysAhead: 0, daysBack: 540 },
];
const DEFAULT_PERIOD = 'future-6';

const COLUMNS = [
  createColumn({
    id: 'first_name',
    header: 'Voornaam',
    accessorFn: (row) => row.person?.first_name || '',
    cell: ({ row }) => (
      <Link to={`/people/${row.original.person.id}`} className="flex items-center gap-3 min-w-0">
        <PersonAvatar
          thumbnail={row.original.person.thumbnail}
          name={row.original.person.name}
          firstName={row.original.person.first_name}
          size="md"
        />
        <span className="font-medium text-gray-900 dark:text-gray-100 truncate">{row.original.person.first_name || '—'}</span>
      </Link>
    ),
    filterType: FILTER_TYPES.TEXT,
    sortingFn: (rowA, rowB) => comparePersonNames(rowA.original.person, rowB.original.person, 'first_name'),
    size: 190,
  }),
  createColumn({
    id: 'last_name',
    header: 'Achternaam',
    accessorFn: (row) => row.person?.last_name || '',
    cell: ({ row }) => (
      <Link to={`/people/${row.original.person.id}`} className="font-medium text-gray-900 dark:text-gray-100 hover:text-electric-cyan">
        {formatPersonSurname(row.original.person.infix, row.original.person.last_name) || '—'}
      </Link>
    ),
    filterType: FILTER_TYPES.TEXT,
    filterFn: (row, _columnId, value) => formatPersonSurname(row.original.person.infix, row.original.person.last_name)
      .toLocaleLowerCase('nl')
      .includes(String(value || '').toLocaleLowerCase('nl')),
    sortingFn: (rowA, rowB) => comparePersonNames(rowA.original.person, rowB.original.person, 'last_name'),
    size: 190,
  }),
  createColumn({
    id: 'type',
    header: 'Type',
    accessorKey: 'type',
    cell: ({ row }) => (row.original.type === 'volunteer' ? 'Vrijwilliger' : 'Lid'),
    filterType: FILTER_TYPES.SELECT,
    filterLabel: 'Type',
    filterOptions: [
      { value: 'member', label: 'Leden' },
      { value: 'volunteer', label: 'Vrijwilligers' },
    ],
    getFilterLabel: (value) => (value === 'volunteer' ? 'Vrijwilligers' : 'Leden'),
    size: 140,
  }),
  createColumn({
    id: 'milestone_label',
    header: 'Mijlpaal',
    accessorKey: 'milestone_label',
    filterType: FILTER_TYPES.TEXT,
    filterLabel: 'Mijlpaal',
    size: 130,
  }),
  createColumn({
    id: 'anniversary_date',
    header: 'Jubileumdatum',
    accessorFn: (row) => new Date(row.anniversary_date).getTime(),
    cell: ({ row }) => format(new Date(row.original.anniversary_date), 'EEEE d MMMM yyyy'),
    filterType: null,
    size: 220,
  }),
  createColumn({
    id: 'days_until',
    header: 'Wanneer',
    accessorFn: (row) => row.days_until,
    cell: ({ row }) => {
      const days = row.original.days_until;
      if (days === 0) return 'Vandaag';
      if (days > 0) return `Over ${days} dagen`;
      return `${Math.abs(days)} dagen geleden`;
    },
    filterType: null,
    size: 140,
    headerClassName: 'text-right',
    className: 'text-right',
  }),
];

export default function PeopleAnniversaries() {
  useDocumentTitle('Jubilarissen');
  const [searchParams, setSearchParams] = useSearchParams();
  const typeFilter = searchParams.get('type') || '';
  const firstNameFilter = searchParams.get('first_name') || '';
  const lastNameFilter = searchParams.get('last_name') || searchParams.get('person_name') || '';
  const milestoneFilter = searchParams.get('milestone_label') || '';
  const periodParam = searchParams.get('period') || DEFAULT_PERIOD;
  const periodOption = PERIOD_OPTIONS.find((option) => option.value === periodParam) || PERIOD_OPTIONS.find((option) => option.value === DEFAULT_PERIOD);
  const { daysAhead, daysBack, label: periodLabel } = periodOption;

  const { data = [], isLoading, error } = useQuery({
    queryKey: ['anniversaries', daysAhead, daysBack],
    queryFn: async () => {
      const response = await prmApi.getAnniversaries(daysAhead, 500, daysBack);
      return response.data || [];
    },
    staleTime: 5 * 60 * 1000,
  });

  const updateSearch = useCallback((changes) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(changes).forEach(([key, value]) => {
      if (!value || (key === 'period' && value === DEFAULT_PERIOD)) {
        next.delete(key);
      } else {
        next.set(key, value);
      }
    });
    if (!changes.period && periodParam === DEFAULT_PERIOD) {
      next.delete('period');
    }
    setSearchParams(next, { replace: true });
  }, [periodParam, searchParams, setSearchParams]);

  const filters = useMemo(() => ({
    type: typeFilter,
    first_name: firstNameFilter,
    last_name: lastNameFilter,
    milestone_label: milestoneFilter,
  }), [typeFilter, firstNameFilter, lastNameFilter, milestoneFilter]);

  const handleFilterChange = useCallback((colId, value) => {
    updateSearch({ [colId]: value });
  }, [updateSearch]);

  const handleClearFilters = useCallback(() => {
    const next = new URLSearchParams(searchParams);
    ['type', 'person_name', 'first_name', 'last_name', 'milestone_label'].forEach((key) => next.delete(key));
    setSearchParams(next, { replace: true });
  }, [searchParams, setSearchParams]);

  const setPeriod = useCallback((nextPeriod) => {
    updateSearch({ period: nextPeriod });
  }, [updateSearch]);

  return (
    <div className="space-y-6">
      {error ? (
        <div className="card p-6 text-sm text-red-600 dark:text-red-400">
          Jubilarissen konden niet worden geladen.
        </div>
      ) : (
        <DataTable
          storageKey="people-jubilarissen"
          data={data}
          columns={COLUMNS}
          isLoading={isLoading}
          emptyIcon={<Users className="w-8 h-8 text-gray-400 dark:text-gray-500" />}
          emptyTitle="Geen jubilarissen gevonden"
          emptyDescription={`Geen jubilarissen gevonden voor de geselecteerde periode (${periodLabel.toLowerCase()}).`}
          filters={filters}
          onFilterChange={handleFilterChange}
          onClearFilters={handleClearFilters}
          toolbarEnd={
            <div className="flex items-center gap-2">
              <select
                value={periodParam}
                onChange={(e) => setPeriod(e.target.value)}
                className="input w-auto min-w-[8.5rem]"
                aria-label="Periode vooruitkijken"
              >
                {PERIOD_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
          }
        />
      )}
    </div>
  );
}
