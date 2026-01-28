import { useInstallPrompt } from '@/hooks/useInstallPrompt';
import { useEngagementTracking } from '@/hooks/useEngagementTracking';
import { Download, X } from 'lucide-react';

/**
 * InstallPrompt - Android PWA install banner
 *
 * Shows a bottom banner for Android/Chromium users after they've demonstrated
 * engagement (2 page views OR 1 note added). Respects dismissal tracking and
 * doesn't show if app is already installed.
 */
export function InstallPrompt() {
  const { canInstall, promptInstall, hidePrompt, isInstalled } = useInstallPrompt();
  const isEngaged = useEngagementTracking({ minPageViews: 2, minNotes: 1 });

  // Don't show if:
  // - Already installed
  // - Can't install (no beforeinstallprompt event)
  // - User hasn't engaged enough
  if (isInstalled || !canInstall || !isEngaged) {
    return null;
  }

  const handleInstall = async () => {
    const outcome = await promptInstall();
    if (outcome.outcome === 'accepted') {
      console.log('PWA installed successfully');
    }
  };

  return (
    <div className="fixed bottom-20 left-4 right-4 sm:left-auto sm:right-4 sm:max-w-sm z-40">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-4">
        <div className="flex items-start gap-3">
          <div className="flex-shrink-0">
            <Download className="w-5 h-5 text-accent-600 dark:text-accent-400" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
              Installeer Stadion
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Voor snellere toegang en offline gebruik
            </p>
          </div>
          <button
            onClick={hidePrompt}
            className="flex-shrink-0 p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            aria-label="Sluiten"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
        <div className="mt-3 flex gap-2 justify-end">
          <button
            onClick={hidePrompt}
            className="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
          >
            Niet nu
          </button>
          <button
            onClick={handleInstall}
            className="px-3 py-1.5 text-sm bg-accent-600 text-white rounded-md hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2"
          >
            Installeer
          </button>
        </div>
      </div>
    </div>
  );
}
