import { useParams, useNavigate, Navigate } from 'react-router-dom';
import TabButton from '@/components/TabButton';
import VOGList from './VOGList';
import VOGUpcoming from './VOGUpcoming';
import VOGSettings from './VOGSettings';

const TABS = [
  { id: 'overzicht', label: 'Overzicht' },
  { id: 'binnenkort', label: 'Binnenkort' },
  { id: 'instellingen', label: 'Instellingen', adminOnly: true },
];

export default function VOG() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  const activeTab = tab || 'overzicht';

  // Non-admin navigating to instellingen → redirect to overzicht
  if (activeTab === 'instellingen' && !isAdmin) {
    return <Navigate to="/vog/overzicht" replace />;
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
            onClick={() => navigate(`/vog/${t.id}`)}
          />
        ))}
      </nav>

      {/* Tab content */}
      {activeTab === 'overzicht' && <VOGList />}
      {activeTab === 'binnenkort' && <VOGUpcoming />}
      {activeTab === 'instellingen' && isAdmin && <VOGSettings />}
    </div>
  );
}
