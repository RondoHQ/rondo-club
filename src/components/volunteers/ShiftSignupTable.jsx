import { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil } from 'lucide-react';
import { Link } from 'react-router-dom';
import { formatStoredDateTime, parseStoredDateTime } from '@/utils/dateFormat';
import { decodeHtml } from '@/utils/formatters';

const collator = new Intl.Collator('nl', { sensitivity: 'base' });

function shiftName(shift) {
  return decodeHtml(shift.dienst_type_name || shift.title) || `Inschrijftaak ${shift.id}`;
}

function startTimestamp(shift) {
  return parseStoredDateTime(shift.start_datetime)?.getTime() ?? 0;
}

function sortByNearestDate(a, b, now) {
  const aTimestamp = startTimestamp(a);
  const bTimestamp = startTimestamp(b);
  const aIsUpcoming = aTimestamp >= now;
  const bIsUpcoming = bTimestamp >= now;

  if (aIsUpcoming !== bIsUpcoming) return aIsUpcoming ? -1 : 1;
  return aIsUpcoming ? aTimestamp - bTimestamp : bTimestamp - aTimestamp;
}

function SortIcon({ active, direction }) {
  if (!active || direction === 'nearest') return <ArrowUpDown className="h-3.5 w-3.5" aria-hidden="true" />;
  return direction === 'asc'
    ? <ArrowUp className="h-3.5 w-3.5" aria-hidden="true" />
    : <ArrowDown className="h-3.5 w-3.5" aria-hidden="true" />;
}

export default function ShiftSignupTable({ shifts, sortable = false }) {
  const [sort, setSort] = useState(() => sortable
    ? { key: 'date', direction: 'nearest' }
    : null);

  const sortedShifts = useMemo(() => {
    if (!sort) return shifts;

    const now = Date.now();
    return [...shifts].sort((a, b) => {
      if (sort.key === 'task') {
        const result = collator.compare(shiftName(a), shiftName(b));
        return sort.direction === 'asc' ? result : -result;
      }
      if (sort.direction === 'nearest') return sortByNearestDate(a, b, now);

      const result = startTimestamp(a) - startTimestamp(b);
      return sort.direction === 'asc' ? result : -result;
    });
  }, [shifts, sort]);

  const toggleSort = (key) => {
    setSort((current) => {
      if (current?.key !== key) return { key, direction: 'asc' };
      if (current.direction === 'nearest') return { key, direction: 'asc' };
      return { key, direction: current.direction === 'asc' ? 'desc' : 'asc' };
    });
  };

  const headerSort = (key) => {
    if (!sortable || sort?.key !== key) return undefined;
    if (sort.direction === 'nearest') return 'other';
    return sort.direction === 'asc' ? 'ascending' : 'descending';
  };

  return (
    <div className="card overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-300">
          <tr>
            <th className="px-4 py-2" aria-sort={headerSort('task')}>
              {sortable ? (
                <button type="button" onClick={() => toggleSort('task')} className="inline-flex items-center gap-1.5 hover:text-gray-800 dark:hover:text-gray-100">
                  Inschrijftaak
                  <SortIcon active={sort?.key === 'task'} direction={sort?.direction} />
                </button>
              ) : 'Inschrijftaak'}
            </th>
            <th className="px-4 py-2" aria-sort={headerSort('date')}>
              {sortable ? (
                <button type="button" onClick={() => toggleSort('date')} className="inline-flex items-center gap-1.5 hover:text-gray-800 dark:hover:text-gray-100">
                  Dienstmoment
                  <SortIcon active={sort?.key === 'date'} direction={sort?.direction} />
                </button>
              ) : 'Dienstmoment'}
            </th>
            <th className="px-4 py-2">Ingeschreven</th>
            <th className="px-4 py-2">Laatste aanmelding</th>
            <th className="w-12 px-4 py-2"><span className="sr-only">Acties</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
          {sortedShifts.map((shift) => (
            <tr key={shift.id} className={shift.status === 'geannuleerd' ? 'bg-gray-50/70 text-gray-500 hover:bg-gray-100 dark:bg-gray-800/40 dark:text-gray-400 dark:hover:bg-gray-700/60' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'}>
              <td className="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                <Link to={`/vrijwilligers/diensten/${shift.id}`} className="text-bright-cobalt hover:underline dark:text-electric-cyan">
                  {shiftName(shift)}
                </Link>
                {shift.status === 'geannuleerd' ? (
                  <span className="ml-2 inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                    Geannuleerd
                  </span>
                ) : null}
              </td>
              <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                {formatStoredDateTime(shift.start_datetime, 'EEE d MMM yyyy, HH:mm')}–{formatStoredDateTime(shift.end_datetime, 'HH:mm')}
              </td>
              <td className="px-4 py-3">
                {shift.signups.map((signup, index) => (
                  <span key={signup.person_id}>
                    {index > 0 ? ', ' : ''}
                    <Link to={`/people/${signup.person_id}`} className="text-bright-cobalt hover:underline dark:text-electric-cyan">
                      {signup.name}
                    </Link>
                  </span>
                ))}
              </td>
              <td className="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {formatStoredDateTime(shift.latest_signup_at, 'dd-MM-yyyy HH:mm')}
              </td>
              <td className="px-4 py-3">
                <Link to={`/vrijwilligers/diensten/${shift.id}`} className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Openen">
                  <Pencil className="h-4 w-4" aria-hidden="true" />
                  <span className="sr-only">{shiftName(shift)} openen</span>
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
