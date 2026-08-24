/**
 * Utility for tracking PWA install prompt dismissals and installation status
 * Uses localStorage for persistent tracking across sessions
 */

const KEYS = {
  INSTALLED: 'pwa-installed',
  IOS_DISMISSED: 'ios-install-dismissed',
};

export const installTracking = {
  /**
   * Track successful installation
   * Marks as installed.
   */
  trackInstall() {
    localStorage.setItem(KEYS.INSTALLED, 'true');
  },

  /**
   * Check if iOS install prompt should be shown
   * @returns {boolean} True if iOS prompt should be shown
   */
  shouldShowIOSPrompt() {
    const dismissed = localStorage.getItem(KEYS.IOS_DISMISSED);
    if (!dismissed) return true;

    const weekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
    return parseInt(dismissed, 10) < weekAgo;
  },

  /**
   * Track iOS-specific dismissal
   * Uses separate key with 7-day cooldown
   */
  trackIOSDismissal() {
    localStorage.setItem(KEYS.IOS_DISMISSED, Date.now().toString());
  },
};
