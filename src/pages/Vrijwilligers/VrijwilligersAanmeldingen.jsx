import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, ClipboardList } from 'lucide-react';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';
import ShiftSignupTable from '@/components/volunteers/ShiftSignupTable';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function VrijwilligersAanmeldingen() {
  useDocumentTitle('Aanmeldingen — Vrijwilligers');

  const { data, error, isLoading } = useQuery({
    queryKey: ['volunteer', 'shift-signups'],
    queryFn: async () => (await prmApi.getShiftSignups()).data,
    staleTime: 60 * 1000,
  });

  const shifts = Array.isArray(data?.shifts) ? data.shifts : [];
  const signupCount = shifts.reduce((total, shift) => total + shift.signups.length, 0);

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
              Alle huidige aanmeldingen. Eerst de eerstvolgende inschrijftaken, daarna de meest recente verstreken taken.
            </p>
          </div>
        </div>
      </header>

      {isLoading ? (
        <div className="card p-8 text-center text-gray-500 dark:text-gray-400">Aanmeldingen laden…</div>
      ) : error ? (
        <div role="alert" className="card border-red-200 p-6 text-red-700 dark:border-red-900 dark:text-red-300">
          De aanmeldingen konden niet worden geladen.
        </div>
      ) : shifts.length === 0 ? (
        <div className="card p-8 text-center text-gray-500 dark:text-gray-400">Er zijn nog geen aanmeldingen.</div>
      ) : (
        <section aria-labelledby="signup-overview-title">
          <h2 id="signup-overview-title" className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
            {signupCount} {signupCount === 1 ? 'aanmelding' : 'aanmeldingen'} voor {shifts.length} {shifts.length === 1 ? 'inschrijftaak' : 'inschrijftaken'}
          </h2>
          <ShiftSignupTable shifts={shifts} sortable />
        </section>
      )}
    </div>
  );
}
