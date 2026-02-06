import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useRef } from 'react';
import api from '@/api/client';

// localStorage key for instant column widths on page load
const COLUMN_WIDTHS_STORAGE_KEY = 'stadion_column_widths';

/**
 * Load column widths from localStorage for instant initialization
 * @returns {Object} Column widths object or empty object
 */
function loadColumnWidthsFromStorage() {
  try {
    const stored = localStorage.getItem(COLUMN_WIDTHS_STORAGE_KEY);
    return stored ? JSON.parse(stored) : {};
  } catch {
    return {};
  }
}

/**
 * Save column widths to localStorage for instant restoration
 * @param {Object} widths Column widths object
 */
function saveColumnWidthsToStorage(widths) {
  try {
    if (widths && typeof widths === 'object') {
      localStorage.setItem(COLUMN_WIDTHS_STORAGE_KEY, JSON.stringify(widths));
    }
  } catch {
    // Ignore localStorage errors
  }
}

/**
 * Hook for managing People list column preferences
 *
 * Provides:
 * - preferences: { visible_columns, column_order, column_widths, available_columns }
 * - isLoading: boolean
 * - updatePreferences: (updates) => void - optimistic mutation
 * - updateColumnWidths: (widths) => void - debounced width updates
 * - isUpdating: boolean
 *
 * Uses TanStack Query with optimistic updates for instant UI feedback.
 * Column widths are synced to localStorage for instant restoration on page load.
 */
export function useListPreferences() {
  const queryClient = useQueryClient();
  const debounceTimerRef = useRef(null);
  const pendingWidthsRef = useRef(null);

  // GET preferences
  const { data, isLoading, error } = useQuery({
    queryKey: ['user', 'list-preferences'],
    queryFn: async () => {
      const response = await api.get('/rondo/v1/user/list-preferences');
      return response.data;
    },
    // Use localStorage widths as placeholder to prevent flicker
    placeholderData: () => {
      const storedWidths = loadColumnWidthsFromStorage();
      if (Object.keys(storedWidths).length > 0) {
        return {
          visible_columns: [],
          column_order: [],
          column_widths: storedWidths,
          available_columns: [],
        };
      }
      return undefined;
    },
  });

  // Sync column_widths to localStorage when query succeeds
  useEffect(() => {
    if (data?.column_widths && typeof data.column_widths === 'object') {
      saveColumnWidthsToStorage(data.column_widths);
    }
  }, [data?.column_widths]);

  // PATCH preferences with optimistic update
  const updateMutation = useMutation({
    mutationFn: async (updates) => {
      const response = await api.patch('/rondo/v1/user/list-preferences', updates);
      return response.data;
    },
    onMutate: async (updates) => {
      // Cancel outgoing refetches
      await queryClient.cancelQueries({ queryKey: ['user', 'list-preferences'] });

      // Snapshot previous value
      const previous = queryClient.getQueryData(['user', 'list-preferences']);

      // Optimistically update cache
      queryClient.setQueryData(['user', 'list-preferences'], (old) => {
        if (!old) return old;

        const updated = { ...old };

        // Handle reset action
        if (updates.reset) {
          return {
            ...old,
            visible_columns: old.available_columns
              ? ['team', 'labels', 'modified'] // Default columns
              : [],
            column_order: old.available_columns
              ? old.available_columns.map((c) => c.id)
              : [],
            column_widths: {},
          };
        }

        // Merge updates
        if (updates.visible_columns !== undefined) {
          updated.visible_columns = updates.visible_columns;
        }
        if (updates.column_order !== undefined) {
          updated.column_order = updates.column_order;
        }
        if (updates.column_widths !== undefined) {
          updated.column_widths = { ...old.column_widths, ...updates.column_widths };
        }

        return updated;
      });

      // Update localStorage for column_widths
      if (updates.column_widths) {
        const currentWidths = loadColumnWidthsFromStorage();
        saveColumnWidthsToStorage({ ...currentWidths, ...updates.column_widths });
      }
      if (updates.reset) {
        saveColumnWidthsToStorage({});
      }

      return { previous };
    },
    onError: (err, updates, context) => {
      // Rollback on error
      if (context?.previous) {
        queryClient.setQueryData(['user', 'list-preferences'], context.previous);
        // Restore localStorage
        if (context.previous.column_widths) {
          saveColumnWidthsToStorage(context.previous.column_widths);
        }
      }
    },
    onSettled: () => {
      // Always refetch to ensure sync with server
      queryClient.invalidateQueries({ queryKey: ['user', 'list-preferences'] });
    },
  });

  /**
   * Update preferences immediately with optimistic update
   * @param {Object} updates - Partial preferences to update
   */
  const updatePreferences = useCallback(
    (updates) => {
      updateMutation.mutate(updates);
    },
    [updateMutation]
  );

  /**
   * Update column widths with debouncing (300ms)
   * Batches multiple rapid width changes into single API call
   * @param {Object} widths - Column widths to update (merged with pending)
   */
  const updateColumnWidths = useCallback(
    (widths) => {
      // Merge with pending widths
      pendingWidthsRef.current = {
        ...pendingWidthsRef.current,
        ...widths,
      };

      // Update localStorage immediately for instant feedback
      const currentWidths = loadColumnWidthsFromStorage();
      saveColumnWidthsToStorage({ ...currentWidths, ...widths });

      // Clear existing timer
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }

      // Set new timer to batch and send after 300ms
      // Also update cache here (deferred) to avoid render loops during pointer events
      debounceTimerRef.current = setTimeout(() => {
        if (pendingWidthsRef.current && Object.keys(pendingWidthsRef.current).length > 0) {
          // Update cache before mutation to show changes immediately
          queryClient.setQueryData(['user', 'list-preferences'], (old) => {
            if (!old) return old;
            return {
              ...old,
              column_widths: { ...old.column_widths, ...pendingWidthsRef.current },
            };
          });
          updateMutation.mutate({ column_widths: pendingWidthsRef.current });
          pendingWidthsRef.current = null;
        }
      }, 300);
    },
    [queryClient, updateMutation]
  );

  // Cleanup debounce timer on unmount
  useEffect(() => {
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
        // Flush pending widths on unmount
        if (pendingWidthsRef.current && Object.keys(pendingWidthsRef.current).length > 0) {
          updateMutation.mutate({ column_widths: pendingWidthsRef.current });
        }
      }
    };
  }, [updateMutation]);

  return {
    preferences: data,
    isLoading,
    error,
    updatePreferences,
    updateColumnWidths,
    isUpdating: updateMutation.isPending,
  };
}

export default useListPreferences;
