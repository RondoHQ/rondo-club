import { useState } from 'react';
import { CheckCircle2, CircleAlert, Clock3, Database, HardDrive } from 'lucide-react';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useProfileChangeLog } from '@/hooks/useMemberProfile';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

const STATUS = {
  pending: { label: 'Wacht op Sportlink', icon: Clock3, classes: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200' },
  synced: { label: 'Gesynchroniseerd', icon: CheckCircle2, classes: 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-200' },
  failed: { label: 'Synchronisatie mislukt', icon: CircleAlert, classes: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-200' },
  local_only: { label: 'Alleen Rondo', icon: HardDrive, classes: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' },
};

function StatusBadge({ value }) {
  const status = STATUS[value] || STATUS.local_only;
  const Icon = status.icon;
  return <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${status.classes}`}><Icon className="h-3.5 w-3.5" />{status.label}</span>;
}

function Change({ change }) {
  return (
    <li className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
      <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{change.person_name}: {change.label}</div>
      <div className="mt-1 break-words text-xs text-gray-500">
        {change.old || 'Leeg'} <span aria-hidden="true">→</span> {change.new || 'Leeg'}
      </div>
    </li>
  );
}

export default function ProfileChangeLog() {
  useDocumentTitle('Wijzigingslog leden');
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useProfileChangeLog(page);
  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Wijzigingslog leden</h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Zelf door leden aangepaste contactgegevens en adressen. Logregels worden 24 maanden bewaard.</p>
      </div>
      {isError ? <div className="card p-5 text-sm text-red-600">De wijzigingslog kon niet worden opgehaald.</div> : null}
      {!isError && data?.items?.length === 0 ? <div className="card p-5 text-sm text-gray-600">Er zijn nog geen wijzigingen vastgelegd.</div> : null}
      {data?.items?.map((item) => (
        <article key={item.id} className="card max-w-4xl p-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="font-semibold text-gray-900 dark:text-gray-100">{item.label}</h2>
              <p className="mt-1 text-xs text-gray-500">{new Date(item.created_at).toLocaleString('nl-NL')} door {item.actor}{item.verified ? ' · e-mailadres geverifieerd' : ''}</p>
            </div>
            <StatusBadge value={item.sync_status} />
          </div>
          <ul className="mt-4 grid gap-2 sm:grid-cols-2">{item.changes.map((change, index) => <Change key={`${change.person_id}-${change.field}-${index}`} change={change} />)}</ul>
          {item.sync_errors?.length ? <p className="mt-3 flex items-start gap-2 text-sm text-red-600"><Database className="mt-0.5 h-4 w-4 shrink-0" />{item.sync_errors.at(-1).message || 'Sportlink heeft de wijziging niet verwerkt.'}</p> : null}
        </article>
      ))}
      {data?.total_pages > 1 ? (
        <div className="flex items-center justify-between max-w-4xl">
          <button type="button" className="btn-secondary" disabled={page === 1} onClick={() => setPage((current) => current - 1)}>Vorige</button>
          <span className="text-sm text-gray-500">Pagina {page} van {data.total_pages}</span>
          <button type="button" className="btn-secondary" disabled={page === data.total_pages} onClick={() => setPage((current) => current + 1)}>Volgende</button>
        </div>
      ) : null}
    </div>
  );
}
