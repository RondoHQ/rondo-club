import { useState, useEffect } from 'react';
import { installTracking } from '../utils/installTracking';
import { isStandalone } from '../utils/platform';

/**
 * React hook that captures the beforeinstallprompt event for Android PWA installation.
 * Manages install prompt state for the explicit "App installeren" action.
 *
 * @returns {Object} - { hasNativePrompt, promptInstall, isInstalled }
 */
export function useInstallPrompt() {
  const [installPrompt, setInstallPrompt] = useState(null);
  const [isInstalled, setIsInstalled] = useState(false);

  useEffect(() => {
    setIsInstalled(isStandalone());

    // If already installed, don't set up listeners
    if (isStandalone()) {
      return;
    }

    const handleBeforeInstall = (e) => {
      // Prevent automatic browser prompt
      e.preventDefault();

      // Store the event for later use
      setInstallPrompt(e);
    };

    const handleAppInstalled = () => {
      // Clear prompt state
      setInstallPrompt(null);
      setIsInstalled(true);

      // Track successful installation
      installTracking.trackInstall();
    };

    // Add event listeners
    window.addEventListener('beforeinstallprompt', handleBeforeInstall);
    window.addEventListener('appinstalled', handleAppInstalled);

    // Cleanup
    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstall);
      window.removeEventListener('appinstalled', handleAppInstalled);
    };
  }, []);

  /**
   * Trigger the native install prompt
   * @returns {Promise<Object>} - { outcome: 'accepted' | 'dismissed' | 'unavailable' }
   */
  const promptInstall = async () => {
    if (!installPrompt) {
      return { outcome: 'unavailable' };
    }

    try {
      // Show the native prompt
      await installPrompt.prompt();

      // Wait for user choice
      const choiceResult = await installPrompt.userChoice;
      const outcome = choiceResult.outcome;

      // Clear state
      setInstallPrompt(null);

      return { outcome };
    } catch (error) {
      console.debug('Install prompt error:', error);
      return { outcome: 'error' };
    }
  };

  return {
    hasNativePrompt: installPrompt !== null,
    promptInstall,
    isInstalled,
  };
}
