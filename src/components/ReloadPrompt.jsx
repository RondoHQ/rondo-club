import { useEffect, useRef, useState } from 'react';
import { useVersionCheck } from '@/hooks/useVersionCheck';
import { reloadForUpdate } from '@/utils/app-update';
import { useRegisterSW } from 'virtual:pwa-register/react';
import { RefreshCw } from 'lucide-react';
import { getAppName } from '@/constants/app';

/** One explicit update action for both server releases and waiting service workers. */
export function ReloadPrompt() {
  const appName = getAppName();
  const registrationRef = useRef(null);
  const [dismissedUpdate, setDismissedUpdate] = useState(null);
  const [reloading, setReloading] = useState(false);
  const { hasUpdate, latestBuildTime, checkVersion } = useVersionCheck();
  const { needRefresh: [needRefresh] } = useRegisterSW({
    onRegisteredSW(_swUrl, registration) {
      registrationRef.current = registration;
    },
    onNeedReload() {
      // Activation in another tab must never discard this tab's unsaved work.
      // The explicit button below owns reloading this document.
    },
    onRegisterError(error) {
      console.error('SW registration error:', error);
    },
  });

  // A waiting worker can belong to the build this document already loaded.
  // Verify the server build before asking the user to reload again.
  useEffect(() => {
    if (needRefresh) checkVersion();
  }, [needRefresh, checkVersion]);

  useEffect(() => {
    const check = () => {
      if (navigator.onLine && document.visibilityState === 'visible') {
        registrationRef.current?.update().catch(() => {});
      }
    };
    const interval = setInterval(check, 15 * 60 * 1000);
    document.addEventListener('visibilitychange', check);
    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', check);
    };
  }, []);

  const updateKey = latestBuildTime;
  if (!hasUpdate || dismissedUpdate === updateKey) return null;

  const reload = async () => {
    setReloading(true);
    await reloadForUpdate(registrationRef.current);
  };

  return (
    <div className="fixed bottom-4 right-4 z-50 max-w-sm">
      {/* Update available notification */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-cyan-200 dark:border-bright-cobalt p-4">
        <div className="flex items-start gap-3">
          <div className="flex-shrink-0">
            <RefreshCw className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
              Update beschikbaar
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Een nieuwe versie van {appName} is beschikbaar. Sla je wijzigingen op voordat je herlaadt.
            </p>
          </div>
        </div>
        <div className="mt-3 flex gap-2 justify-end">
          <button
            onClick={() => setDismissedUpdate(updateKey)}
            disabled={reloading}
            className="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
          >
            Later
          </button>
          <button
            onClick={reload}
            disabled={reloading}
            className="px-3 py-1.5 text-sm bg-electric-cyan text-white rounded-md hover:bg-bright-cobalt focus:outline-none focus:ring-2 focus:ring-electric-cyan focus:ring-offset-2"
          >
            {reloading ? 'Herladen…' : 'Nu herladen'}
          </button>
        </div>
      </div>
    </div>
  );
}

export default ReloadPrompt;
