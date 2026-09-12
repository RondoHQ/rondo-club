import { CalendarClock } from 'lucide-react';
import { formatStoredDateTime } from '@/utils/dateFormat';
import { decodeHtml } from '@/utils/formatters';

function PersonShiftItem({ shift }) {
  const status = shift.no_show
    ? { label: 'No-show', classes: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }
    : shift.status === 'voltooid'
      ? { label: 'Voltooid', classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' }
      : shift.status === 'geannuleerd'
        ? { label: 'Geannuleerd', classes: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' }
        : { label: 'Ingepland', classes: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300' };

  return (
    <li className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
            {decodeHtml(shift.dienst_type_name || shift.title)}
          </p>
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {formatStoredDateTime(shift.start_datetime, 'EEEE d MMMM yyyy, HH:mm')}
            {shift.end_datetime ? ` – ${formatStoredDateTime(shift.end_datetime, 'HH:mm')}` : ''}
          </p>
        </div>
        <span className={`shrink-0 rounded px-2 py-0.5 text-xs font-medium ${status.classes}`}>
          {status.label}
        </span>
      </div>
    </li>
  );
}

export default function PersonShiftOverview({ overview, isLoading }) {
  const upcoming = overview?.upcoming || [];
  const recent = overview?.recent || [];
  const obligations = overview?.obligations || [];
  const active = obligations.filter(obligation => !obligation.exemption);
  const required = active.reduce((sum, obligation) => sum + obligation.required_count, 0);
  const completed = active.reduce((sum, obligation) => sum + Math.min(obligation.required_count, obligation.completed_count ?? 0), 0);
  const exempt = obligations.length > 0 && obligations.every(obligation => obligation.exemption);
  const hasDetails = obligations.length > 0 || recent.length > 0 || upcoming.length > 1;

  return (
    <section className="card p-6" aria-label="Inschrijftaken">
      <h2 className="mb-4 flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100">
        <CalendarClock className="h-5 w-5 shrink-0 text-bright-cobalt" aria-hidden="true" />Inschrijftaken
      </h2>
      {isLoading ? <p className="text-sm text-gray-500 dark:text-gray-400">Inschrijftaken laden…</p> : !overview ? (
        <p className="text-sm text-gray-500 dark:text-gray-400">Inschrijftaken konden niet worden geladen.</p>
      ) : (
        <div className="space-y-3">
          <p className="font-medium text-gray-900 dark:text-gray-100">
            {exempt ? 'Vrijgesteld' : required > 0 ? `${completed} van ${required} afgerond` : 'Geen inschrijftaken vereist'}
          </p>
          {upcoming.length > 0 ? (
            <div>
              <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">Eerstvolgende inschrijftaak</p>
              <ul><PersonShiftItem shift={upcoming[0]} /></ul>
            </div>
          ) : <p className="text-sm text-gray-500 dark:text-gray-400">Geen inschrijftaken gepland.</p>}
          {hasDetails && (
            <details className="text-sm">
              <summary className="cursor-pointer text-bright-cobalt dark:text-electric-cyan">Details</summary>
              <div className="mt-3 space-y-4">
                {obligations.length > 0 && (
                  <div className="space-y-2 text-gray-600 dark:text-gray-300">
                    {obligations.map(obligation => (
                      <p key={obligation.kind}>
                        <span className="font-medium">{obligation.kind === 'gezin' ? `Gezin${obligation.child_count ? ` (${obligation.child_count} ${obligation.child_count === 1 ? 'kind' : 'kinderen'})` : ''}` : 'Persoonlijk'}:</span>{' '}
                        {obligation.exemption ? 'vrijgesteld' : `${obligation.completed_count ?? 0} van ${obligation.required_count} afgerond`}
                      </p>
                    ))}
                    {obligations.some(obligation => obligation.kind === 'gezin' && !obligation.exemption) && <p>Inschrijftaken van gezinsleden tellen mee voor de gezinsplicht.</p>}
                  </div>
                )}
                {upcoming.length > 1 && <div><h3 className="mb-2 font-medium">Overige geplande inschrijftaken</h3><ul className="space-y-2">{upcoming.slice(1).map(shift => <PersonShiftItem key={shift.id} shift={shift} />)}</ul></div>}
                {recent.length > 0 && <div><h3 className="mb-2 font-medium">Laatste inschrijftaken</h3><ul className="space-y-2">{recent.map(shift => <PersonShiftItem key={shift.id} shift={shift} />)}</ul></div>}
              </div>
            </details>
          )}
        </div>
      )}
    </section>
  );
}
