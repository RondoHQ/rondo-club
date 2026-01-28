import { useRegisterSW } from 'virtual:pwa-register/react';
import { RefreshCw, X, CheckCircle } from 'lucide-react';

/**
 * ReloadPrompt - Shows notifications for PWA updates and offline readiness
 *
 * Uses vite-plugin-pwa's useRegisterSW hook to:
 * - Register the service worker
 * - Detect when a new version is available
 * - Allow users to trigger update (reload)
 * - Show "ready for offline" notification
 * - Check for updates periodically (every hour)
 */
export function ReloadPrompt() {
  const intervalMS = 60 * 60 * 1000; // Check every hour

  const {
    offlineReady: [offlineReady, setOfflineReady],
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
    setOfflineReady(false);
    setNeedRefresh(false);
  };

  // Don't render anything if no notification needed
  if (!offlineReady && !needRefresh) {
    return null;
  }

  return (
    <div className="fixed bottom-4 right-4 z-50 max-w-sm">
      {/* Offline ready notification */}
      {offlineReady && (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-4 flex items-start gap-3">
          <div className="flex-shrink-0">
            <CheckCircle className="w-5 h-5 text-green-500" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
              Klaar voor offline gebruik
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Stadion werkt nu ook zonder internet
            </p>
          </div>
          <button
            onClick={close}
            className="flex-shrink-0 p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            aria-label="Sluiten"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Update available notification */}
      {needRefresh && (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-accent-200 dark:border-accent-700 p-4">
          <div className="flex items-start gap-3">
            <div className="flex-shrink-0">
              <RefreshCw className="w-5 h-5 text-accent-600 dark:text-accent-400" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                Update beschikbaar
              </p>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Een nieuwe versie van Stadion is beschikbaar
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
              className="px-3 py-1.5 text-sm bg-accent-600 text-white rounded-md hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2"
            >
              Nu herladen
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default ReloadPrompt;
