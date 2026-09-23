import { lazy, Suspense } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import RoleDashboard from '@/components/dashboard/RoleDashboard';
const LegacyDashboard = lazy(() => import('./LegacyDashboard'));
export default function Dashboard() {
  const { data: user, isLoading } = useCurrentUser();
  const [params, setParams] = useSearchParams();
  if (isLoading) return <p>Dashboard laden…</p>;
  const roleDashboard = user?.dashboard_context?.enabled;
  const showClub = user?.can_access_club_dashboard && params.get('overzicht') === 'club';
  return <>
    {roleDashboard && user?.can_access_club_dashboard && <div className="mb-4 flex justify-end"><button className="text-sm text-electric-cyan underline underline-offset-4" onClick={() => setParams(showClub ? {} : { overzicht: 'club' })}>{showClub ? 'Terug naar mijn dashboard' : 'Overige overzichten'}</button></div>}
    {roleDashboard && !showClub ? <RoleDashboard user={user} /> : <Suspense fallback={<p>Dashboard laden…</p>}><LegacyDashboard /></Suspense>}
  </>;
}
