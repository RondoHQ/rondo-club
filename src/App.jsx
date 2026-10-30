import { useEffect } from 'react';
import { Outlet } from 'react-router-dom';
import { useTheme } from '@/hooks/useTheme';
import { OfflineBanner } from '@/components/OfflineBanner';
import { IOSInstallModal } from '@/components/IOSInstallModal';
import { ReloadPrompt } from '@/components/ReloadPrompt';

function App() {
  // Initialize theme on app mount (applies dark class and accent color)
  useTheme();

  useEffect(() => {
    const reloadAccount = (event) => {
      if (event.key === 'rondo-account-changed') window.location.reload();
    };
    window.addEventListener('storage', reloadAccount);
    return () => window.removeEventListener('storage', reloadAccount);
  }, []);

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
