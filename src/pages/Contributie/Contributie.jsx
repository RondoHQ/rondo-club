import { useParams, useNavigate, Navigate } from 'react-router-dom';
import TabButton from '@/components/TabButton';
import { ContributieOverzicht } from './ContributieOverzicht';
import { ContributieList } from './ContributieList';
import { NogTeFactureren } from './NogTeFactureren';
import FeeCategorySettings from '../Settings/FeeCategorySettings';
import { useFeeSummary } from '@/hooks/useFees';

const TABS = [
  { id: 'overzicht', label: 'Overzicht' },
  { id: 'per-lid', label: 'Per lid' },
  { id: 'nog-te-factureren', label: 'Nog te factureren', nikkiOnly: true },
  { id: 'instellingen', label: 'Instellingen', adminOnly: true },
];

export default function Contributie() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  const { data: summaryData } = useFeeSummary();
  const billingMethod = summaryData?.billing_method ?? 'nikki';

  const activeTab = tab || 'overzicht';

  // Non-admin navigating to instellingen → redirect to overzicht
  if (activeTab === 'instellingen' && !isAdmin) {
    return <Navigate to="/financien/contributie/overzicht" replace />;
  }

  // Navigating to nog-te-factureren when billing is not nikki → redirect
  if (activeTab === 'nog-te-factureren' && billingMethod !== 'nikki') {
    return <Navigate to="/financien/contributie/overzicht" replace />;
  }

  const visibleTabs = TABS.filter(t => {
    if (t.adminOnly && !isAdmin) return false;
    if (t.nikkiOnly && billingMethod !== 'nikki') return false;
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Tab bar */}
      <nav className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
        {visibleTabs.map(t => (
          <TabButton
            key={t.id}
            label={t.label}
            isActive={activeTab === t.id}
            onClick={() => navigate(`/financien/contributie/${t.id}`)}
          />
        ))}
      </nav>

      {/* Tab content */}
      {activeTab === 'overzicht' && <ContributieOverzicht />}
      {activeTab === 'per-lid' && <ContributieList />}
      {activeTab === 'nog-te-factureren' && <NogTeFactureren />}
      {activeTab === 'instellingen' && isAdmin && <FeeCategorySettings />}
    </div>
  );
}
