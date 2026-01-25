/**
 * Date formatting utilities with Dutch locale pre-configured
 *
 * This module wraps date-fns functions to automatically apply the Dutch (nl) locale,
 * ensuring consistent Dutch date formatting throughout the application.
 *
 * Locale-aware functions (format, formatDistance, etc.) are wrapped to inject nl locale.
 * Non-locale functions (parseISO, isToday, etc.) are re-exported for convenience.
 *
 * @example
 * import { format, parseISO, isToday } from '@/utils/dateFormat';
 *
 * const date = parseISO('2024-01-15');
 * format(date, 'd MMMM yyyy'); // "15 januari 2024" (Dutch month name)
 */

import {
  format as dateFnsFormat,
  formatDistance as dateFnsFormatDistance,
  formatDistanceToNow as dateFnsFormatDistanceToNow,
  formatRelative as dateFnsFormatRelative,
  parseISO,
  isToday,
  isYesterday,
  isThisWeek,
  addDays,
  subDays,
  differenceInYears,
  parse,
  isValid,
} from 'date-fns';
import { nl } from 'date-fns/locale';

/**
 * Shared date configuration with Dutch locale
 */
const dateConfig = { locale: nl };

/**
 * Format a date with Dutch locale
 * @param {Date|number} date - The date to format
 * @param {string} formatStr - The format string (date-fns format)
 * @param {Object} options - Additional options (merged with Dutch locale)
 * @returns {string} Formatted date string
 */
export function format(date, formatStr, options = {}) {
  return dateFnsFormat(date, formatStr, { ...dateConfig, ...options });
}

/**
 * Format distance between two dates with Dutch locale
 * @param {Date|number} date - The date to compare
 * @param {Date|number} baseDate - The base date to compare against
 * @param {Object} options - Additional options (merged with Dutch locale)
 * @returns {string} Distance string in Dutch (e.g., "3 dagen")
 */
export function formatDistance(date, baseDate, options = {}) {
  return dateFnsFormatDistance(date, baseDate, { ...dateConfig, ...options });
}

/**
 * Format distance from now with Dutch locale
 * @param {Date|number} date - The date to compare to now
 * @param {Object} options - Additional options (merged with Dutch locale)
 * @returns {string} Distance string in Dutch (e.g., "3 uur geleden", "over 2 dagen")
 */
export function formatDistanceToNow(date, options = {}) {
  return dateFnsFormatDistanceToNow(date, { ...dateConfig, ...options });
}

/**
 * Format a date relative to a base date with Dutch locale
 * @param {Date|number} date - The date to format
 * @param {Date|number} baseDate - The base date to compare against
 * @param {Object} options - Additional options (merged with Dutch locale)
 * @returns {string} Relative date string in Dutch (e.g., "gisteren om 14:30")
 */
export function formatRelative(date, baseDate, options = {}) {
  return dateFnsFormatRelative(date, baseDate, { ...dateConfig, ...options });
}

/**
 * Re-export non-locale functions for convenience
 * These functions don't require locale configuration
 */
export {
  parseISO,
  isToday,
  isYesterday,
  isThisWeek,
  addDays,
  subDays,
  differenceInYears,
  parse,
  isValid,
};
