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
 * Accent color hex values (Tailwind -500 values)
 */
const ACCENT_HEX = {
  orange: '#f97316',
  teal: '#14b8a6',
  indigo: '#6366f1',
  emerald: '#10b981',
  violet: '#8b5cf6',
  pink: '#ec4899',
  fuchsia: '#d946ef',
  rose: '#f43f5e',
};

/**
 * Accent color hex values for dark mode (Tailwind -600 values)
 */
const ACCENT_HEX_DARK = {
  orange: '#ea580c',
  teal: '#0d9488',
  indigo: '#4f46e5',
  emerald: '#059669',
  violet: '#7c3aed',
  pink: '#db2777',
  fuchsia: '#c026d3',
  rose: '#e11d48',
};

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
 * Update favicon to match accent color
 * @param {string} accentColor - The accent color name
 */
function updateFavicon(accentColor) {
  if (typeof document === 'undefined') return;

  const hex = ACCENT_HEX[accentColor] || ACCENT_HEX.orange;

  // SVG stadium icon (same as favicon.svg)
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${hex}">
    <path fill-rule="evenodd" d="M12 2C6.5 2 2 5.5 2 9v6c0 3.5 4.5 7 10 7s10-3.5 10-7V9c0-3.5-4.5-7-10-7zm0 2c4.4 0 8 2.7 8 5s-3.6 5-8 5-8-2.7-8-5 3.6-5 8-5zm0 4c-2.2 0-4 .9-4 2s1.8 2 4 2 4-.9 4-2-1.8-2-4-2z" clip-rule="evenodd"/>
  </svg>`;

  const dataUrl = `data:image/svg+xml,${encodeURIComponent(svg)}`;

  // Update existing favicon link or create new one
  let link = document.querySelector('link[rel="icon"]');
  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    document.head.appendChild(link);
  }
  link.type = 'image/svg+xml';
  link.href = dataUrl;
}

/**
 * Update theme-color meta tags for PWA
 * These control the browser/OS chrome color on mobile
 * @param {string} accentColor - The accent color name
 */
function updateThemeColorMeta(accentColor) {
  if (typeof document === 'undefined') return;

  const lightHex = ACCENT_HEX[accentColor] || ACCENT_HEX.orange;
  const darkHex = ACCENT_HEX_DARK[accentColor] || ACCENT_HEX_DARK.orange;

  // Update light mode theme-color
  const lightMeta = document.querySelector('meta[name="theme-color"][media*="light"]');
  if (lightMeta) {
    lightMeta.content = lightHex;
  }

  // Update dark mode theme-color
  const darkMeta = document.querySelector('meta[name="theme-color"][media*="dark"]');
  if (darkMeta) {
    darkMeta.content = darkHex;
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

  // Update favicon to match accent color
  updateFavicon(accentColor);

  // Update theme-color meta tags for PWA
  updateThemeColorMeta(accentColor);
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
