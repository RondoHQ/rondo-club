/**
 * Utility for tracking PWA install prompt dismissals and installation status
 * Uses localStorage for persistent tracking across sessions
 */

const KEYS = {
  DISMISSED: 'pwa-install-dismissed',
  DISMISS_COUNT: 'pwa-install-dismiss-count',
  INSTALLED: 'pwa-installed',
  IOS_DISMISSED: 'ios-install-dismissed',
};

export const installTracking = {
  /**
   * Check if install prompt should be shown
   * @returns {boolean} True if prompt should be shown
   */
  shouldShowPrompt() {
    const lastDismissed = localStorage.getItem(KEYS.DISMISSED);
    const dismissCount = this.getDismissCount();
    const isInstalled = localStorage.getItem(KEYS.INSTALLED) === 'true';

    if (isInstalled) return false;

    // Don't show if dismissed 3+ times
    if (dismissCount >= 3) return false;

    // Show if never dismissed
    if (!lastDismissed) return true;

    // Show if dismissed > 7 days ago
    const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
    return parseInt(lastDismissed, 10) < sevenDaysAgo;
  },

  /**
   * Track when user dismisses install prompt
   * Increments dismiss count and stores timestamp
   */
  trackDismissal() {
    const count = this.getDismissCount();
    localStorage.setItem(KEYS.DISMISSED, Date.now().toString());
    localStorage.setItem(KEYS.DISMISS_COUNT, (count + 1).toString());
  },

  /**
   * Track successful installation
   * Marks as installed and clears dismissal tracking
   */
  trackInstall() {
    localStorage.setItem(KEYS.INSTALLED, 'true');
    localStorage.removeItem(KEYS.DISMISSED);
    localStorage.removeItem(KEYS.DISMISS_COUNT);
  },

  /**
   * Get current dismiss count
   * @returns {number} Number of times user dismissed prompt
   */
  getDismissCount() {
    return parseInt(localStorage.getItem(KEYS.DISMISS_COUNT) || '0', 10);
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
