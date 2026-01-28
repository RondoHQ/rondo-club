import { useState, useEffect } from 'react';
import { installTracking } from '../utils/installTracking';

/**
 * React hook that captures the beforeinstallprompt event for Android PWA installation.
 * Manages install prompt state and provides functions to trigger or dismiss the prompt.
 *
 * @returns {Object} - { canInstall, promptInstall, hidePrompt, isInstalled }
 */
export function useInstallPrompt() {
  const [installPrompt, setInstallPrompt] = useState(null);
  const [canInstall, setCanInstall] = useState(false);
  const [isInstalled, setIsInstalled] = useState(false);

  useEffect(() => {
    // Check if app is already installed
    const checkInstalled = () => {
      const standalone = window.matchMedia('(display-mode: standalone)').matches;
      const iosStandalone = window.navigator.standalone === true;
      return standalone || iosStandalone;
    };

    setIsInstalled(checkInstalled());

    // If already installed, don't set up listeners
    if (checkInstalled()) {
      return;
    }

    const handleBeforeInstall = (e) => {
      // Prevent automatic browser prompt
      e.preventDefault();

      // Store the event for later use
      setInstallPrompt(e);

      // Check if user previously dismissed and if we should show prompt
      if (installTracking.shouldShowPrompt()) {
        setCanInstall(true);
      }
    };

    const handleAppInstalled = () => {
      // Clear prompt state
      setInstallPrompt(null);
      setCanInstall(false);
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

      // Track dismissal if user declined
      if (outcome === 'dismissed') {
        installTracking.trackDismissal();
      }

      // Clear state
      setInstallPrompt(null);
      setCanInstall(false);

      return { outcome };
    } catch (error) {
      console.debug('Install prompt error:', error);
      return { outcome: 'error' };
    }
  };

  /**
   * Hide the install prompt without triggering native prompt
   * Tracks dismissal for future prompt timing
   */
  const hidePrompt = () => {
    installTracking.trackDismissal();
    setCanInstall(false);
  };

  return {
    canInstall,
    promptInstall,
    hidePrompt,
    isInstalled,
  };
}
