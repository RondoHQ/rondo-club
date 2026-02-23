import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Users, Award } from 'lucide-react';
import { prmApi } from '@/api/client';
import PersonAvatar from '@/components/PersonAvatar';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format, addDays } from '@/utils/dateFormat';

const MONTH_OPTIONS = [3, 6, 9, 12];
const DEFAULT_MONTHS = 6;

function AnniversaryRow({ item }) {
  const person = item.person;
  const typeLabel = item.type === 'volunteer' ? 'Vrijwilliger' : 'Lid';

  return (
    <Link
      to={`/people/${person.id}`}
      className="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
    >
      <PersonAvatar
        thumbnail={person.thumbnail}
        name={person.name}
        firstName={person.first_name}
        size="lg"
      />
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{person.name}</p>
        <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
          {item.milestone_label} {typeLabel.toLowerCase()} · {format(new Date(item.anniversary_date), 'EEEE d MMMM yyyy')}
        </p>
      </div>
      <div className="text-right flex-shrink-0">
        <div className="text-xs font-medium text-electric-cyan dark:text-electric-cyan">{typeLabel}</div>
        <div className="text-xs text-gray-500 dark:text-gray-400">
          {item.days_until === 0 ? 'Vandaag' : `Over ${item.days_until} dagen`}
        </div>
      </div>
    </Link>
  );
}

export default function PeopleAnniversaries() {
  useDocumentTitle('Jubilarissen');
  const [searchParams, setSearchParams] = useSearchParams();
  const typeFilter = searchParams.get('type') || 'all';
  const monthsParam = Number(searchParams.get('months'));
  const monthsAhead = MONTH_OPTIONS.includes(monthsParam) ? monthsParam : DEFAULT_MONTHS;
  const lookaheadDays = monthsAhead * 30;

  const { data = [], isLoading, error, refetch, isRefetching } = useQuery({
    queryKey: ['anniversaries', lookaheadDays],
    queryFn: async () => {
      const response = await prmApi.getAnniversaries(lookaheadDays, 500);
      return response.data || [];
    },
    staleTime: 5 * 60 * 1000,
  });

  const filtered = useMemo(() => {
    if (typeFilter === 'member') {
      return data.filter((item) => item.type === 'member');
    }
    if (typeFilter === 'volunteer') {
      return data.filter((item) => item.type === 'volunteer');
    }
    return data;
  }, [data, typeFilter]);

  const memberCount = useMemo(() => data.filter((item) => item.type === 'member').length, [data]);
  const volunteerCount = useMemo(() => data.filter((item) => item.type === 'volunteer').length, [data]);

  const endDate = addDays(new Date(), lookaheadDays);

  const updateSearch = (nextType, nextMonths = monthsAhead) => {
    const next = new URLSearchParams(searchParams);
    if (nextType === 'all') {
      next.delete('type');
    } else {
      next.set('type', nextType);
    }
    if (nextMonths === DEFAULT_MONTHS) {
      next.delete('months');
    } else {
      next.set('months', String(nextMonths));
    }
    setSearchParams(next);
  };

  const setFilter = (nextType) => updateSearch(nextType, monthsAhead);
  const setMonths = (nextMonths) => updateSearch(typeFilter, nextMonths);

  if (isLoading) {
    return <ContentLoadingSpinner minHeight="40vh" />;
  }

  return (
    <div className="space-y-6">
      <div className="card p-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold text-brand-gradient flex items-center gap-2">
              <Award className="w-5 h-5 text-gray-500 dark:text-gray-400" />
              Jubilarissen
            </h2>
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Overzicht van aankomende jubilea in de komende {monthsAhead} maanden (tot en met {format(endDate, 'd MMMM yyyy')}).
            </p>
          </div>
          <div className="flex items-center gap-2">
            <select
              value={monthsAhead}
              onChange={(e) => setMonths(Number(e.target.value))}
              className="input w-auto min-w-[8.5rem]"
              aria-label="Periode vooruitkijken"
            >
              {MONTH_OPTIONS.map((option) => (
                <option key={option} value={option}>
                  {option} maanden
                </option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => refetch()}
              className="btn-secondary"
              disabled={isRefetching}
            >
              {isRefetching ? 'Verversen...' : 'Verversen'}
            </button>
          </div>
        </div>
      </div>

      <div className="card p-4">
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setFilter('all')}
            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              typeFilter === 'all'
                ? 'bg-cyan-50 text-bright-cobalt dark:bg-gray-700 dark:text-electric-cyan'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700'
            }`}
          >
            Alle ({data.length})
          </button>
          <button
            type="button"
            onClick={() => setFilter('member')}
            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              typeFilter === 'member'
                ? 'bg-cyan-50 text-bright-cobalt dark:bg-gray-700 dark:text-electric-cyan'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700'
            }`}
          >
            Leden ({memberCount})
          </button>
          <button
            type="button"
            onClick={() => setFilter('volunteer')}
            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              typeFilter === 'volunteer'
                ? 'bg-cyan-50 text-bright-cobalt dark:bg-gray-700 dark:text-electric-cyan'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700'
            }`}
          >
            Vrijwilligers ({volunteerCount})
          </button>
        </div>
      </div>

      <div className="card overflow-hidden">
        {error ? (
          <div className="p-6 text-sm text-red-600 dark:text-red-400">
            Jubilarissen konden niet worden geladen.
          </div>
        ) : filtered.length === 0 ? (
          <div className="p-8 text-center">
            <Users className="w-10 h-10 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Geen jubilarissen gevonden voor deze filter in de komende {monthsAhead} maanden.
            </p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100 dark:divide-gray-700">
            {filtered.map((item) => (
              <AnniversaryRow key={item.id} item={item} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
