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
 * Parse a Ymd date string (e.g. '20260218') into a Date object.
 * Also handles Y-m-d format (e.g. '2026-02-18') for robustness.
 *
 * @param {string} dateStr - Date string in Ymd or Y-m-d format.
 * @returns {Date} Parsed date object.
 */
export function parseYmd(dateStr) {
  if (!dateStr) return new Date(NaN);
  if (dateStr.includes('-')) return parse(dateStr, 'yyyy-MM-dd', new Date());
  return parse(dateStr, 'yyyyMMdd', new Date());
}

/**
 * Parse a stored datetime string (e.g. '2026-09-05 09:00:00') into a Date object.
 *
 * Shift metadata arrives in MySQL DATETIME format with a space separator, which
 * Safari's Date constructor rejects ("Invalid time value"). Normalize to ISO 8601
 * before parsing instead of passing the raw string to date-fns format().
 *
 * @param {string|Date|null|undefined} value - Stored datetime or date string.
 * @returns {Date|null} Parsed date, or null when missing or invalid.
 */
export function parseStoredDateTime(value) {
  if (value instanceof Date) return isValid(value) ? value : null;
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();
  if (!trimmed) return null;
  const parsed = parseISO(trimmed.replace(' ', 'T'));
  return isValid(parsed) ? parsed : null;
}

/**
 * Format a stored datetime string with Dutch locale, falling back when unparsable.
 *
 * @param {string|Date|null|undefined} value - Stored datetime or date string.
 * @param {string} formatStr - The format string (date-fns format).
 * @param {string} fallback - Returned when the value cannot be parsed.
 * @returns {string} Formatted date string or the fallback.
 */
export function formatStoredDateTime(value, formatStr, fallback = '—') {
  const parsed = parseStoredDateTime(value);
  return parsed ? format(parsed, formatStr) : fallback;
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
