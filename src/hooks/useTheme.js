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

  // SVG sparkle icon (same as favicon.svg)
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${hex}">
    <path fill-rule="evenodd" d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5ZM16.5 15a.75.75 0 0 1 .712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 0 1 0 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 0 1-1.422 0l-.395-1.183a1.5 1.5 0 0 0-.948-.948l-1.183-.395a.75.75 0 0 1 0-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0 1 16.5 15Z" clip-rule="evenodd" />
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
