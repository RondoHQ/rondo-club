import { useState, useEffect, useCallback } from 'react';
import { prmApi } from '../api/client';

/**
 * Hook that periodically checks for new app versions
 * and prompts the user to reload when a new version is available.
 * 
 * This is particularly useful for PWA/mobile app installs where
 * the browser cache might prevent automatic updates.
 * 
 * @param {Object} options - Configuration options
 * @param {number} options.checkInterval - How often to check for updates (ms), default 5 minutes
 * @returns {Object} - { hasUpdate, currentVersion, latestVersion, reload }
 */
export function useVersionCheck({ checkInterval = 5 * 60 * 1000 } = {}) {
  const [hasUpdate, setHasUpdate] = useState(false);
  const [currentVersion] = useState(() => window.prmConfig?.version || null);
  const [latestVersion, setLatestVersion] = useState(null);

  const checkVersion = useCallback(async () => {
    if (!currentVersion) return;
    
    try {
      const response = await prmApi.getVersion();
      const serverVersion = response.data?.version;
      
      if (serverVersion && serverVersion !== currentVersion) {
        setLatestVersion(serverVersion);
        setHasUpdate(true);
      }
    } catch (error) {
      // Silently fail - version check is not critical
      console.debug('Version check failed:', error.message);
    }
  }, [currentVersion]);

  const reload = useCallback(() => {
    // Clear TanStack Query cache before reload
    window.location.reload(true);
  }, []);

  // Initial check on mount (with small delay to not block initial render)
  useEffect(() => {
    const initialTimeout = setTimeout(checkVersion, 5000);
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
      if (document.visibilityState === 'visible') {
        checkVersion();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, [checkVersion]);

  return {
    hasUpdate,
    currentVersion,
    latestVersion,
    reload,
    checkVersion,
  };
}

export default useVersionCheck;
