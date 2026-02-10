import { useParams, useNavigate, Navigate } from 'react-router-dom';
import TabButton from '@/components/TabButton';
import { ContributieOverzicht } from './ContributieOverzicht';
import { ContributieList } from './ContributieList';
import FeeCategorySettings from '../Settings/FeeCategorySettings';

const TABS = [
  { id: 'overzicht', label: 'Overzicht' },
  { id: 'per-lid', label: 'Per lid' },
  { id: 'instellingen', label: 'Instellingen', adminOnly: true },
];

export default function Contributie() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  const activeTab = tab || 'overzicht';

  // Non-admin navigating to instellingen → redirect to overzicht
  if (activeTab === 'instellingen' && !isAdmin) {
    return <Navigate to="/contributie/overzicht" replace />;
  }

  const visibleTabs = TABS.filter(t => !t.adminOnly || isAdmin);

  return (
    <div className="space-y-6">
      {/* Tab bar */}
      <nav className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
        {visibleTabs.map(t => (
          <TabButton
            key={t.id}
            label={t.label}
            isActive={activeTab === t.id}
            onClick={() => navigate(`/contributie/${t.id}`)}
          />
        ))}
      </nav>

      {/* Tab content */}
      {activeTab === 'overzicht' && <ContributieOverzicht />}
      {activeTab === 'per-lid' && <ContributieList />}
      {activeTab === 'instellingen' && isAdmin && <FeeCategorySettings />}
    </div>
  );
}
