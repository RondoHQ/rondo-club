import { useEffect, useMemo, useState } from 'react';
import {
  addMonths,
  eachDayOfInterval,
  eachMonthOfInterval,
  endOfMonth,
  getDay,
  isAfter,
  isBefore,
  parseISO,
  startOfMonth,
} from 'date-fns';
import { CheckCircle2, Circle, XCircle } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { format } from '@/utils/dateFormat';

const WEEKDAYS = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];

function dayStatusLabel(day) {
  if (!day) return 'geen diensten';
  if (day.state === 'full') return `alle ${day.shift_count} diensten ingevuld`;
  return `${day.spots_remaining} ${day.spots_remaining === 1 ? 'plek' : 'plekken'} open`;
}

function Month({ month, from, to, daysByDate, selectedDate, onSelectDate }) {
  const monthStart = startOfMonth(month);
  const monthEnd = endOfMonth(month);
  const days = eachDayOfInterval({ start: monthStart, end: monthEnd });
  const leadingBlanks = (getDay(monthStart) + 6) % 7;

  return (
    <section aria-label={format(month, 'MMMM yyyy')}>
      <h3 className="mb-3 text-center text-sm font-semibold capitalize text-gray-800 dark:text-gray-100">
        {format(month, 'MMMM yyyy')}
      </h3>
      <div className="grid grid-cols-7 gap-1 text-center">
        {WEEKDAYS.map((weekday) => (
          <div key={weekday} className="pb-1 text-[11px] font-medium uppercase text-gray-400 dark:text-gray-500">
            {weekday}
          </div>
        ))}
        {Array.from({ length: leadingBlanks }, (_, index) => (
          <span key={`blank-${index}`} aria-hidden="true" />
        ))}
        {days.map((date) => {
          const dateKey = format(date, 'yyyy-MM-dd');
          const day = daysByDate.get(dateKey);
          const outsideRange = isBefore(date, from) || isAfter(date, to);
          const isSelected = selectedDate === dateKey;
          const stateClasses = day?.state === 'full'
            ? 'border-emerald-300 bg-emerald-100 text-emerald-900 hover:bg-emerald-200 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-100'
            : day?.state === 'open'
              ? 'border-red-300 bg-red-100 text-red-900 hover:bg-red-200 dark:border-red-700 dark:bg-red-900/40 dark:text-red-100'
              : 'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400';

          return (
            <button
              key={dateKey}
              type="button"
              disabled={outsideRange || !day}
              onClick={() => onSelectDate(dateKey)}
              aria-pressed={isSelected}
              aria-label={`${format(date, 'EEEE d MMMM')}: ${dayStatusLabel(day)}`}
              className={`relative flex aspect-square min-h-9 items-center justify-center rounded-md border text-xs font-medium transition ${stateClasses} ${
                outsideRange ? 'invisible' : ''
              } ${isSelected ? 'ring-2 ring-bright-cobalt ring-offset-1 dark:ring-electric-cyan dark:ring-offset-gray-900' : ''} disabled:cursor-default`}
            >
              {format(date, 'd')}
              {day && (
                <span className="absolute bottom-0.5 right-1 text-[9px] font-semibold" aria-hidden="true">
                  {day.state === 'full' ? '✓' : day.spots_remaining}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </section>
  );
}

export default function ShiftCoverageCalendar({
  data,
  isLoading,
  selectedDienstType,
  onDienstTypeChange,
  title = 'Bezetting komende drie maanden',
  description,
  renderShift,
}) {
  const [selectedDate, setSelectedDate] = useState('');
  const from = data?.from ? parseISO(data.from) : new Date();
  const to = data?.to ? parseISO(data.to) : endOfMonth(addMonths(from, 2));
  const daysByDate = useMemo(
    () => new Map((data?.days || []).map((day) => [day.date, day])),
    [data]
  );
  const months = eachMonthOfInterval({ start: startOfMonth(from), end: startOfMonth(to) });
  const selectedDay = selectedDate ? daysByDate.get(selectedDate) : null;

  useEffect(() => {
    if (selectedDate && !daysByDate.has(selectedDate)) setSelectedDate('');
  }, [daysByDate, selectedDate]);

  return (
    <section className="card p-4 sm:p-5 space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
          {description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>}
        </div>
        {((data?.dienst_types || []).length > 1 || selectedDienstType) && (
          <div className="flex min-w-56 flex-col gap-1">
            <label htmlFor="calendar-diensttype-filter" className="text-xs font-medium text-gray-600 dark:text-gray-300">
              Diensttype
            </label>
            <select
              id="calendar-diensttype-filter"
              value={selectedDienstType}
              onChange={(event) => onDienstTypeChange(event.target.value)}
              className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-bright-cobalt focus:outline-none focus:ring-1 focus:ring-bright-cobalt dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-electric-cyan dark:focus:ring-electric-cyan"
            >
              <option value="">Alle diensttypes</option>
              {selectedDienstType && !(data?.dienst_types || []).some((type) => String(type.id) === selectedDienstType) && (
                <option value={selectedDienstType}>Geselecteerd diensttype</option>
              )}
              {(data?.dienst_types || []).map((type) => (
                <option key={type.id} value={type.id}>{type.name}</option>
              ))}
            </select>
          </div>
        )}
      </div>

      <div className="flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600 dark:text-gray-300" aria-label="Legenda">
        <span className="inline-flex items-center gap-1.5"><CheckCircle2 className="h-4 w-4 text-emerald-600" /> Alles ingevuld</span>
        <span className="inline-flex items-center gap-1.5"><XCircle className="h-4 w-4 text-red-600" /> Nog plekken open</span>
        <span className="inline-flex items-center gap-1.5"><Circle className="h-4 w-4 text-gray-400" /> Geen diensten</span>
      </div>

      {isLoading ? (
        <ContentLoadingSpinner />
      ) : (
        <>
          <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            {months.map((month) => (
              <Month
                key={format(month, 'yyyy-MM')}
                month={month}
                from={from}
                to={to}
                daysByDate={daysByDate}
                selectedDate={selectedDate}
                onSelectDate={setSelectedDate}
              />
            ))}
          </div>

          {(data?.days || []).length === 0 && (
            <p className="rounded-md bg-gray-50 p-4 text-center text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
              Geen diensten gepland in deze periode.
            </p>
          )}

          {selectedDay && (
            <div className="border-t border-gray-200 pt-4 dark:border-gray-700">
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-semibold capitalize text-gray-900 dark:text-gray-100">
                  {format(parseISO(selectedDay.date), 'EEEE d MMMM')}
                </h3>
                <span className={`text-xs font-medium ${selectedDay.state === 'full' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'}`}>
                  {dayStatusLabel(selectedDay)}
                </span>
              </div>
              <div className="space-y-2">
                {selectedDay.shifts.map((shift) => renderShift(shift))}
              </div>
            </div>
          )}
        </>
      )}
    </section>
  );
}
