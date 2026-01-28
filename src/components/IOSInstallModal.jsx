import { useState, useEffect } from 'react';
import { installTracking } from '@/utils/installTracking';
import { Share, Plus, X } from 'lucide-react';

/**
 * IOSInstallModal - iOS Safari install instructions modal
 *
 * Shows a modal with step-by-step instructions for installing the PWA on iOS
 * since Safari doesn't support the beforeinstallprompt event. Only shows for
 * iOS Safari users who aren't already in standalone mode.
 */
export function IOSInstallModal() {
  const [show, setShow] = useState(false);

  useEffect(() => {
    // Detect iOS Safari (not already installed)
    const isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);
    const isStandalone = window.navigator.standalone === true;
    const isSafari = /Safari/.test(navigator.userAgent) && !/CriOS|FxiOS|EdgiOS/.test(navigator.userAgent);

    if (isIOS && !isStandalone && isSafari) {
      // Check if dismissed recently
      if (!installTracking.shouldShowIOSPrompt()) {
        return;
      }

      // Check engagement - show after 3 page views
      const pageViews = parseInt(sessionStorage.getItem('pwa-page-views') || '0', 10);
      if (pageViews >= 3) {
        setShow(true);
      }
    }
  }, []);

  const handleDismiss = () => {
    installTracking.trackIOSDismissal();
    setShow(false);
  };

  if (!show) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl p-6 max-w-sm w-full mx-4 sm:mx-0 shadow-lg">
        <div className="flex justify-between items-start mb-4">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Installeer Stadion
          </h3>
          <button
            onClick={handleDismiss}
            className="p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            aria-label="Sluiten"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
          Voor snellere toegang kun je Stadion installeren op je beginscherm:
        </p>

        <ol className="space-y-4">
          <li className="flex items-start gap-3">
            <div className="flex-shrink-0 w-6 h-6 rounded-full bg-accent-100 dark:bg-accent-900 text-accent-600 dark:text-accent-400 flex items-center justify-center text-sm font-medium">
              1
            </div>
            <div className="flex-1 text-sm">
              <p className="text-gray-900 dark:text-gray-100 mb-2">
                Tik op het Deel-icoon
              </p>
              <div className="inline-flex items-center justify-center p-2 bg-gray-100 dark:bg-gray-700 rounded-md">
                <Share className="w-5 h-5 text-accent-600 dark:text-accent-400" />
              </div>
            </div>
          </li>

          <li className="flex items-start gap-3">
            <div className="flex-shrink-0 w-6 h-6 rounded-full bg-accent-100 dark:bg-accent-900 text-accent-600 dark:text-accent-400 flex items-center justify-center text-sm font-medium">
              2
            </div>
            <div className="flex-1 text-sm">
              <p className="text-gray-900 dark:text-gray-100 mb-2">
                Scroll omlaag en kies "Zet op beginscherm"
              </p>
              <div className="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-md text-accent-600 dark:text-accent-400">
                <Plus className="w-4 h-4" />
                <span className="text-sm font-medium">Zet op beginscherm</span>
              </div>
            </div>
          </li>

          <li className="flex items-start gap-3">
            <div className="flex-shrink-0 w-6 h-6 rounded-full bg-accent-100 dark:bg-accent-900 text-accent-600 dark:text-accent-400 flex items-center justify-center text-sm font-medium">
              3
            </div>
            <div className="flex-1 text-sm">
              <p className="text-gray-900 dark:text-gray-100">
                Tik op "Voeg toe"
              </p>
            </div>
          </li>
        </ol>

        <button
          onClick={handleDismiss}
          className="mt-6 w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
        >
          Misschien later
        </button>
      </div>
    </div>
  );
}
