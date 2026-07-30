/**
 * Application constants
 * Centralized configuration for app-wide values
 */

export const APP_NAME = 'Rondo Club';

/**
 * The name this deployment is known by.
 *
 * The WordPress site title already carries the right value per deployment
 * ("AWC Rondo" on production, "Rondo Demo" on demo), so user-facing copy and
 * the installed app name should read it rather than hardcode a product name.
 *
 * @returns {string} Site title, or APP_NAME when it is unavailable.
 */
export function getAppName() {
  const siteName = window.rondoConfig?.siteName?.trim();

  return siteName || APP_NAME;
}
