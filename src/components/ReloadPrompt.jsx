import { useRegisterSW } from 'virtual:pwa-register/react';
import { RefreshCw } from 'lucide-react';
import { getAppName } from '@/constants/app';

/**
 * ReloadPrompt - Shows notifications for PWA updates
 *
 * Uses vite-plugin-pwa's useRegisterSW hook to:
 * - Register the service worker
 * - Detect when a new version is available
 * - Allow users to trigger update (reload)
 * - Check for updates periodically (every hour)
 */
export function ReloadPrompt() {
  const intervalMS = 60 * 60 * 1000; // Check every hour
  const appName = getAppName();

  const {
    needRefresh: [needRefresh, setNeedRefresh],
    updateServiceWorker
  } = useRegisterSW({
    onRegisteredSW(swUrl, registration) {
      console.log('SW Registered:', registration);

      if (registration) {
        setInterval(async () => {
          // Only check when online
          if (navigator.onLine) {
            try {
              const resp = await fetch(swUrl, {
                cache: 'no-store',
                headers: {
                  'cache': 'no-store',
                  'cache-control': 'no-cache',
                },
              });

              if (resp?.status === 200) {
                await registration.update();
              }
            } catch (error) {
              console.debug('SW update check failed:', error);
            }
          }
        }, intervalMS);
      }
    },
    onRegisterError(error) {
      console.error('SW registration error:', error);
    }
  });

  const close = () => {
    setNeedRefresh(false);
  };

  // Don't render anything if no notification needed
  if (!needRefresh) {
    return null;
  }

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
              Een nieuwe versie van {appName} is beschikbaar
            </p>
          </div>
        </div>
        <div className="mt-3 flex gap-2 justify-end">
          <button
            onClick={close}
            className="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
          >
            Later
          </button>
          <button
            onClick={() => updateServiceWorker(true)}
            className="px-3 py-1.5 text-sm bg-electric-cyan text-white rounded-md hover:bg-bright-cobalt focus:outline-none focus:ring-2 focus:ring-electric-cyan focus:ring-offset-2"
          >
            Nu herladen
          </button>
        </div>
      </div>
    </div>
  );
}

export default ReloadPrompt;
