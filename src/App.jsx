import { Outlet } from 'react-router-dom';
import { useTheme } from '@/hooks/useTheme';
import { OfflineBanner } from '@/components/OfflineBanner';
import { IOSInstallModal } from '@/components/IOSInstallModal';
import { ReloadPrompt } from '@/components/ReloadPrompt';

function App() {
  // Initialize theme on app mount (applies dark class and accent color)
  useTheme();

  return (
    <div className="app-root">
      <OfflineBanner />
      {/* Registers the service worker and enables browser-managed installation. */}
      <ReloadPrompt />
      <IOSInstallModal />
      <Outlet />
    </div>
  );
}

export default App;
