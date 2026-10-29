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
 * @returns {Object} - { hasUpdate, currentVersion, latestVersion }
 */
export function useVersionCheck({ checkInterval = 15 * 60 * 1000 } = {}) {
  const [hasUpdate, setHasUpdate] = useState(false);
  const [currentBuildTime] = useState(() => window.rondoConfig?.buildTime || null);
  const [latestVersion, setLatestVersion] = useState(null);
  const [latestBuildTime, setLatestBuildTime] = useState(null);
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

      // A fresh server response is authoritative, including when an earlier
      // mismatch has disappeared. A waiting worker alone is not a new app build.
      const updateAvailable = Boolean(serverBuildTime && serverBuildTime !== currentBuildTime);
      setLatestVersion(updateAvailable ? serverVersion : null);
      setLatestBuildTime(updateAvailable ? serverBuildTime : null);
      setHasUpdate(updateAvailable);
    } catch (error) {
      // Silently fail - version check is not critical
      console.debug('Version check failed:', error.message);
    } finally {
      checkInFlightRef.current = false;
    }
  }, [currentBuildTime]);

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
    latestBuildTime,
    checkVersion,
  };
}

export default useVersionCheck;
