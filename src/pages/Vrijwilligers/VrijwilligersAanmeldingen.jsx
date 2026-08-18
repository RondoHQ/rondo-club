import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, ClipboardList } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { prmApi } from '@/api/client';
import ShiftSignupTable from '@/components/volunteers/ShiftSignupTable';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function VrijwilligersAanmeldingen() {
  useDocumentTitle('Aanmeldingen — Vrijwilligers');

  const [searchParams, setSearchParams] = useSearchParams();
  const requestedStatus = searchParams.get('status');
  const statusFilter = ['cancelled', 'all'].includes(requestedStatus) ? requestedStatus : 'active';

  const { data, error, isLoading } = useQuery({
    queryKey: ['volunteer', 'shift-signups', statusFilter],
    queryFn: async () => (await prmApi.getShiftSignups({ status: statusFilter })).data,
    staleTime: 60 * 1000,
  });

  const shifts = Array.isArray(data?.shifts) ? data.shifts : [];
  const countedShifts = shifts.filter((shift) => shift.status !== 'geannuleerd');
  const signupCount = countedShifts.reduce((total, shift) => total + shift.signups.length, 0);
  const cancelledShiftCount = shifts.length - countedShifts.length;

  const updateStatusFilter = (status) => {
    const nextParams = new URLSearchParams(searchParams);
    if (status === 'active') nextParams.delete('status');
    else nextParams.set('status', status);
    setSearchParams(nextParams);
  };

  const emptyMessage = statusFilter === 'cancelled'
    ? 'Er zijn geen geannuleerde inschrijftaken met bewaarde aanmeldingen.'
    : 'Er zijn nog geen aanmeldingen.';

  return (
    <div className="space-y-6">
      <header>
        <Link to="/vrijwilligers/diensten" className="mb-3 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" /> Terug naar inschrijftaken
        </Link>
        <div className="flex items-center gap-3">
          <div className="rounded-lg bg-cyan-50 p-2 dark:bg-gray-700">
            <ClipboardList className="h-6 w-6 text-bright-cobalt dark:text-electric-cyan" aria-hidden="true" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Aanmeldingen</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Geannuleerde inschrijftaken blijven bewaard, maar zijn standaard verborgen en tellen niet mee.
            </p>
          </div>
        </div>
      </header>

      <div className="flex justify-end">
        <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300" htmlFor="signup-status-filter">
          Status
          <select
            id="signup-status-filter"
            className="input w-auto min-w-52"
            value={statusFilter}
            onChange={(event) => updateStatusFilter(event.target.value)}
          >
            <option value="active">Zonder geannuleerde taken</option>
            <option value="cancelled">Alleen geannuleerde taken</option>
            <option value="all">Alle statussen</option>
          </select>
        </label>
      </div>

      {isLoading ? (
        <div className="card p-8 text-center text-gray-500 dark:text-gray-400">Aanmeldingen laden…</div>
      ) : error ? (
        <div role="alert" className="card border-red-200 p-6 text-red-700 dark:border-red-900 dark:text-red-300">
          De aanmeldingen konden niet worden geladen.
        </div>
      ) : shifts.length === 0 ? (
        <div className="card p-8 text-center text-gray-500 dark:text-gray-400">{emptyMessage}</div>
      ) : (
        <section aria-labelledby="signup-overview-title">
          <h2 id="signup-overview-title" className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
            {statusFilter === 'cancelled' ? (
              <>{shifts.length} geannuleerde {shifts.length === 1 ? 'inschrijftaak' : 'inschrijftaken'} gevonden</>
            ) : (
              <>
                {signupCount} {signupCount === 1 ? 'aanmelding' : 'aanmeldingen'} voor {countedShifts.length} {countedShifts.length === 1 ? 'inschrijftaak' : 'inschrijftaken'}
                {cancelledShiftCount > 0 ? ` · ${cancelledShiftCount} geannuleerd en niet meegeteld` : ''}
              </>
            )}
          </h2>
          <ShiftSignupTable shifts={shifts} sortable />
        </section>
      )}
    </div>
  );
}
