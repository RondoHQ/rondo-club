import { useState, useCallback, useRef } from 'react';

/**
 * Hook for handling column resize via pointer events
 *
 * @param {number} initialWidth - Starting width in pixels (default: 150)
 * @param {number} minWidth - Minimum width constraint (default: 50)
 * @param {Function} onResizeEnd - Callback when resize ends with final width
 * @returns {Object} - { width, isResizing, resizeHandlers }
 */
export function useColumnResize(initialWidth = 150, minWidth = 50, onResizeEnd = null) {
  const [width, setWidth] = useState(initialWidth);
  const [isResizing, setIsResizing] = useState(false);
  const startXRef = useRef(null);
  const startWidthRef = useRef(null);
  const currentWidthRef = useRef(width);

  // Keep currentWidthRef in sync
  currentWidthRef.current = width;

  // Store callback in ref to avoid dependency issues
  const onResizeEndRef = useRef(onResizeEnd);
  onResizeEndRef.current = onResizeEnd;

  /**
   * Handle pointer down - start resize drag
   */
  const onPointerDown = useCallback(
    (e) => {
      e.preventDefault();
      e.stopPropagation();

      setIsResizing(true);
      startXRef.current = e.clientX;
      startWidthRef.current = width;

      e.currentTarget.setPointerCapture(e.pointerId);
    },
    [width]
  );

  /**
   * Handle pointer move - update width during drag
   */
  const onPointerMove = useCallback(
    (e) => {
      if (!isResizing) return;

      const delta = e.clientX - startXRef.current;
      const newWidth = Math.max(minWidth, startWidthRef.current + delta);

      setWidth(newWidth);
    },
    [isResizing, minWidth]
  );

  /**
   * Handle pointer up - end resize drag and notify
   */
  const onPointerUp = useCallback(
    (e) => {
      if (!isResizing) return;

      setIsResizing(false);

      // Release pointer capture - wrap in try-catch as it can fail
      // if pointer was already released or left the window
      try {
        e.currentTarget.releasePointerCapture(e.pointerId);
      } catch {
        // Pointer capture may already be released
      }

      // Call resize end callback with final width
      if (onResizeEndRef.current) {
        onResizeEndRef.current(currentWidthRef.current);
      }
    },
    [isResizing]
  );

  /**
   * Handle pointer cancel - same as pointer up
   */
  const onPointerCancel = useCallback(
    (e) => {
      if (!isResizing) return;

      setIsResizing(false);

      // Release pointer capture - wrap in try-catch as it can fail
      // if pointer was already released or left the window
      try {
        e.currentTarget.releasePointerCapture(e.pointerId);
      } catch {
        // Pointer capture may already be released
      }

      // Call resize end callback with final width
      if (onResizeEndRef.current) {
        onResizeEndRef.current(currentWidthRef.current);
      }
    },
    [isResizing]
  );

  return {
    width,
    isResizing,
    resizeHandlers: {
      onPointerDown,
      onPointerMove,
      onPointerUp,
      onPointerCancel,
    },
  };
}

export default useColumnResize;
