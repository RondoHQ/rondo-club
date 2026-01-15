import { useState, useEffect, useCallback } from 'react';

/**
 * Valid color scheme values
 */
const COLOR_SCHEMES = ['light', 'dark', 'system'];

/**
 * Valid accent color values
 */
const ACCENT_COLORS = ['orange', 'teal', 'indigo', 'emerald', 'violet', 'pink', 'fuchsia', 'rose'];

/**
 * localStorage key for theme preferences
 */
const STORAGE_KEY = 'theme-preferences';

/**
 * Default theme preferences
 */
const DEFAULT_PREFERENCES = {
  colorScheme: 'system',
  accentColor: 'orange',
};

/**
 * Get system color scheme preference
 * @returns {'light' | 'dark'} The system's preferred color scheme
 */
function getSystemColorScheme() {
  if (typeof window === 'undefined') return 'light';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * Load preferences from localStorage
 * @returns {Object} Theme preferences with colorScheme and accentColor
 */
function loadPreferences() {
  if (typeof window === 'undefined') return DEFAULT_PREFERENCES;

  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
      const parsed = JSON.parse(stored);
      return {
        colorScheme: COLOR_SCHEMES.includes(parsed.colorScheme)
          ? parsed.colorScheme
          : DEFAULT_PREFERENCES.colorScheme,
        accentColor: ACCENT_COLORS.includes(parsed.accentColor)
          ? parsed.accentColor
          : DEFAULT_PREFERENCES.accentColor,
      };
    }
  } catch {
    // Invalid JSON in localStorage, use defaults
  }

  return DEFAULT_PREFERENCES;
}

/**
 * Save preferences to localStorage
 * @param {Object} preferences - Theme preferences to save
 */
function savePreferences(preferences) {
  if (typeof window === 'undefined') return;

  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
  } catch {
    // localStorage not available or quota exceeded
  }
}

/**
 * Apply theme to DOM
 * @param {string} effectiveColorScheme - 'light' or 'dark'
 * @param {string} accentColor - The accent color name
 */
function applyTheme(effectiveColorScheme, accentColor) {
  if (typeof document === 'undefined') return;

  const root = document.documentElement;

  // Apply dark mode class
  if (effectiveColorScheme === 'dark') {
    root.classList.add('dark');
  } else {
    root.classList.remove('dark');
  }

  // Apply accent color via data attribute
  root.setAttribute('data-accent', accentColor);
}

/**
 * Hook for managing theme preferences (color scheme and accent color)
 *
 * Manages localStorage persistence and applies theme changes to the DOM.
 * Supports system color scheme preference detection and respects user overrides.
 *
 * @returns {Object} Theme state and setters
 * @property {string} colorScheme - Current color scheme ('light' | 'dark' | 'system')
 * @property {string} accentColor - Current accent color
 * @property {string} effectiveColorScheme - Resolved color scheme ('light' | 'dark')
 * @property {Function} setColorScheme - Set the color scheme preference
 * @property {Function} setAccentColor - Set the accent color
 */
export function useTheme() {
  // Initialize state from localStorage (runs once on mount)
  const [preferences, setPreferences] = useState(() => loadPreferences());
  const [systemScheme, setSystemScheme] = useState(() => getSystemColorScheme());

  // Calculate effective color scheme
  const effectiveColorScheme = preferences.colorScheme === 'system'
    ? systemScheme
    : preferences.colorScheme;

  // Apply theme on mount and when preferences change
  useEffect(() => {
    applyTheme(effectiveColorScheme, preferences.accentColor);
  }, [effectiveColorScheme, preferences.accentColor]);

  // Listen to system color scheme changes
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const handleChange = (e) => {
      setSystemScheme(e.matches ? 'dark' : 'light');
    };

    // Modern browsers use addEventListener
    if (mediaQuery.addEventListener) {
      mediaQuery.addEventListener('change', handleChange);
      return () => mediaQuery.removeEventListener('change', handleChange);
    }

    // Fallback for older browsers
    mediaQuery.addListener(handleChange);
    return () => mediaQuery.removeListener(handleChange);
  }, []);

  // Save preferences to localStorage whenever they change
  useEffect(() => {
    savePreferences(preferences);
  }, [preferences]);

  /**
   * Set color scheme preference
   * @param {string} scheme - 'light', 'dark', or 'system'
   */
  const setColorScheme = useCallback((scheme) => {
    if (!COLOR_SCHEMES.includes(scheme)) {
      console.warn(`Invalid color scheme: ${scheme}. Valid values: ${COLOR_SCHEMES.join(', ')}`);
      return;
    }
    setPreferences(prev => ({ ...prev, colorScheme: scheme }));
  }, []);

  /**
   * Set accent color
   * @param {string} color - One of the valid accent colors
   */
  const setAccentColor = useCallback((color) => {
    if (!ACCENT_COLORS.includes(color)) {
      console.warn(`Invalid accent color: ${color}. Valid values: ${ACCENT_COLORS.join(', ')}`);
      return;
    }
    setPreferences(prev => ({ ...prev, accentColor: color }));
  }, []);

  return {
    colorScheme: preferences.colorScheme,
    accentColor: preferences.accentColor,
    effectiveColorScheme,
    setColorScheme,
    setAccentColor,
  };
}

/**
 * Export constants for use in other components
 */
export { COLOR_SCHEMES, ACCENT_COLORS };
