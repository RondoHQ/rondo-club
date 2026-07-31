import { useState, useEffect, useCallback, useRef } from 'react';
import { prmApi } from '../api/client';

/**
 * Hook that periodically checks for new app builds
 * and prompts the user to reload when a new build is available.
 *
 * Uses build timestamps instead of version numbers to detect updates,
 * ensuring that every deployment triggers a refresh prompt even if
 * the version number hasn't changed.
 *
 * This is particularly useful for PWA/mobile app installs where
 * the browser cache might prevent automatic updates.
 *
 * @param {Object} options - Configuration options
 * @param {number} options.checkInterval - How often to check for updates (ms), default 15 minutes
 * @returns {Object} - { hasUpdate, currentVersion, latestVersion, reload }
 */
export function useVersionCheck({ checkInterval = 15 * 60 * 1000 } = {}) {
  const [hasUpdate, setHasUpdate] = useState(false);
  const [currentBuildTime] = useState(() => window.rondoConfig?.buildTime || null);
  const [latestVersion, setLatestVersion] = useState(null);
  const lastCheckRef = useRef(0);
  const checkInFlightRef = useRef(false);

  const checkVersion = useCallback(async () => {
    if (!currentBuildTime || checkInFlightRef.current) return;

    checkInFlightRef.current = true;
    lastCheckRef.current = Date.now();

    try {
      const response = await prmApi.getVersion();
      const serverBuildTime = response.data?.buildTime;
      const serverVersion = response.data?.version;

      // Compare build times instead of versions
      if (serverBuildTime && serverBuildTime !== currentBuildTime) {
        // Store the version for display in the banner
        setLatestVersion(serverVersion);
        setHasUpdate(true);
      }
    } catch (error) {
      // Silently fail - version check is not critical
      console.debug('Version check failed:', error.message);
    } finally {
      checkInFlightRef.current = false;
    }
  }, [currentBuildTime]);

  const reload = useCallback(async () => {
    // A plain reload is not enough on PWA installs: the *old* service worker
    // keeps controlling the page (registerType: 'prompt' leaves the new SW
    // "waiting"), so cached assets are served again and the user stays on the
    // stale build. Tear down the SW + caches first, then hard-reload.
    try {
      if ('serviceWorker' in navigator) {
        const regs = await navigator.serviceWorker.getRegistrations();
        await Promise.all(regs.map((reg) => reg.unregister()));
      }
      if ('caches' in window) {
        const keys = await caches.keys();
        await Promise.all(keys.map((key) => caches.delete(key)));
      }
    } catch (error) {
      console.debug('Cache teardown before reload failed:', error?.message);
    }
    window.location.reload();
  }, []);

  // The HTML already contains the current build timestamp. Delay the fallback
  // check so a login wave does not create an immediate second REST request.
  useEffect(() => {
    const initialTimeout = setTimeout(checkVersion, 60 * 1000);
    return () => clearTimeout(initialTimeout);
  }, [checkVersion]);

  // Periodic checks
  useEffect(() => {
    const interval = setInterval(checkVersion, checkInterval);
    return () => clearInterval(interval);
  }, [checkVersion, checkInterval]);

  // Also check when the page becomes visible (user returns to tab)
  useEffect(() => {
    const handleVisibilityChange = () => {
      const checkIsDue = Date.now() - lastCheckRef.current >= checkInterval;
      if (document.visibilityState === 'visible' && checkIsDue) {
        checkVersion();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, [checkInterval, checkVersion]);

  // Keep currentVersion for backward compatibility with consumers
  const currentVersion = window.rondoConfig?.version || null;

  return {
    hasUpdate,
    currentVersion,
    latestVersion,
    reload,
    checkVersion,
  };
}

export default useVersionCheck;
