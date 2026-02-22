import { useParams, useNavigate, Navigate } from 'react-router-dom';
import TabButton from '@/components/TabButton';
import { ContributieList } from './ContributieList';
import { NogTeFactureren } from './NogTeFactureren';
import { useFeeSummary } from '@/hooks/useFees';

const TABS = [
  { id: 'per-lid', label: 'Per lid' },
  { id: 'nog-te-factureren', label: 'Nog te factureren', nikkiOnly: true },
  { id: 'afwijking', label: 'Afwijking', nikkiOnly: true },
];

export default function Contributie() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const { data: summaryData } = useFeeSummary();
  const billingMethod = summaryData?.billing_method ?? 'nikki';

  const activeTab = tab || 'per-lid';

  // Overzicht moved to /financien dashboard
  if (activeTab === 'overzicht') {
    return <Navigate to="/financien" replace />;
  }

  // Nikki-only tabs redirect when billing is not nikki
  if ((activeTab === 'nog-te-factureren' || activeTab === 'afwijking') && billingMethod !== 'nikki') {
    return <Navigate to="/financien/contributie/per-lid" replace />;
  }

  const visibleTabs = TABS.filter(t => {
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
      {activeTab === 'per-lid' && <ContributieList />}
      {activeTab === 'afwijking' && <ContributieList onlyMismatch />}
      {activeTab === 'nog-te-factureren' && <NogTeFactureren />}
    </div>
  );
}
