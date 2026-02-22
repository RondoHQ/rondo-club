import { useParams, useNavigate, Navigate } from 'react-router-dom';
import TabButton from '@/components/TabButton';
import VOGList from './VOGList';
import VOGUpcoming from './VOGUpcoming';

const TABS = [
  { id: 'overzicht', label: 'Overzicht' },
  { id: 'binnenkort', label: 'Binnenkort' },
];

export default function VOG() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const activeTab = tab || 'overzicht';
  if (activeTab === 'instellingen') {
    return <Navigate to="/settings/vog" replace />;
  }
  const isKnownTab = TABS.some((t) => t.id === activeTab);
  if (!isKnownTab) {
    return <Navigate to="/vog/overzicht" replace />;
  }

  return (
    <div className="space-y-6">
      {/* Tab bar */}
      <nav className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
        {TABS.map(t => (
          <TabButton
            key={t.id}
            label={t.label}
            isActive={activeTab === t.id}
            onClick={() => navigate(`/vog/${t.id}`)}
          />
        ))}
      </nav>

      {/* Tab content */}
      {activeTab === 'overzicht' && <VOGList />}
      {activeTab === 'binnenkort' && <VOGUpcoming />}
    </div>
  );
}
