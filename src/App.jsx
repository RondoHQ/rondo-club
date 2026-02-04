import { Outlet } from 'react-router-dom';
import { useVersionCheck } from '@/hooks/useVersionCheck';
import { useTheme } from '@/hooks/useTheme';
import { OfflineBanner } from '@/components/OfflineBanner';
import { InstallPrompt } from '@/components/InstallPrompt';
import { IOSInstallModal } from '@/components/IOSInstallModal';
import { RefreshCw } from 'lucide-react';

function UpdateBanner() {
  const { hasUpdate, latestVersion, reload } = useVersionCheck();
  
  if (!hasUpdate) return null;
  
  return (
    <div className="fixed top-0 left-0 right-0 z-[100] bg-accent-600 text-white py-2 px-4 shadow-lg">
      <div className="max-w-7xl mx-auto flex items-center justify-center gap-4 text-sm">
        <span>
          A new version ({latestVersion}) is available.
        </span>
        <button
          onClick={reload}
          className="inline-flex items-center gap-2 px-3 py-1 bg-white text-accent-600 rounded-md font-medium hover:bg-accent-50 transition-colors"
        >
          <RefreshCw className="w-4 h-4" />
          Reload
        </button>
      </div>
    </div>
  );
}

function App() {
  // Initialize theme on app mount (applies dark class and accent color)
  useTheme();

  return (
    <div className="app-root">
      <UpdateBanner />
      <OfflineBanner />
      <InstallPrompt />
      <IOSInstallModal />
      <Outlet />
    </div>
  );
}

export default App;
