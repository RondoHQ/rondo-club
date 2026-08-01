import { useState, useCallback } from 'react';

/**
 * Manages column visibility state with localStorage persistence.
 * Used for pages that don't use TanStack Table (Teams, Commissies, etc.)
 *
 * @param {string} storageKey - localStorage key (prefixed with 'rondo-col-')
 * @returns {{ isVisible: (id: string) => boolean, toggle: (id: string) => void }}
 */
export function useColumnVisibility(storageKey) {
  const [colVis, setColVis] = useState(() => {
    if (!storageKey) return {};
    try {
      const stored = localStorage.getItem(`rondo-col-${storageKey}`);
      return stored ? JSON.parse(stored) : {};
    } catch {
      return {};
    }
  });

  // Default is visible (true) unless explicitly set to false
  const isVisible = useCallback((id) => colVis[id] !== false, [colVis]);

  const toggle = useCallback(
    (id) => {
      setColVis((prev) => {
        const currentlyVisible = prev[id] !== false;
        const next = { ...prev, [id]: !currentlyVisible };
        if (storageKey) {
          try {
            localStorage.setItem(`rondo-col-${storageKey}`, JSON.stringify(next));
          } catch { /* storage not available */ }
        }
        return next;
      });
    },
    [storageKey],
  );

  const migrateAliases = useCallback((aliases) => {
    if (!aliases || Object.keys(aliases).length === 0) return;
    setColVis((previous) => {
      const next = {};
      let changed = false;
      Object.entries(previous).forEach(([identifier, value]) => {
        const canonical = aliases[identifier] || identifier;
        next[canonical] = value;
        changed ||= canonical !== identifier;
      });
      if (changed && storageKey) {
        try {
          localStorage.setItem(`rondo-col-${storageKey}`, JSON.stringify(next));
        } catch { /* storage not available */ }
      }
      return changed ? next : previous;
    });
  }, [storageKey]);

  return { isVisible, toggle, migrateAliases };
}
