import { useState, useCallback, useRef } from 'react';

/**
 * Hook for handling column resize via pointer events
 *
 * Usage:
 * ```jsx
 * function ResizableHeader({ column, onWidthChange }) {
 *   const { width, isResizing, resizeHandlers } = useColumnResize(
 *     column.id,
 *     column.width || 150,
 *     50
 *   );
 *
 *   return (
 *     <th style={{ width: `${width}px` }}>
 *       {column.label}
 *       <div
 *         {...resizeHandlers}
 *         className="resize-handle"
 *         style={{ touchAction: 'none' }}
 *       />
 *     </th>
 *   );
 * }
 * ```
 *
 * @param {string} columnId - Unique column identifier (used for tracking)
 * @param {number} initialWidth - Starting width in pixels (default: 150)
 * @param {number} minWidth - Minimum width constraint (default: 50)
 * @returns {Object} - { width, isResizing, resizeHandlers }
 */
export function useColumnResize(columnId, initialWidth = 150, minWidth = 50) {
  const [width, setWidth] = useState(initialWidth);
  const [isResizing, setIsResizing] = useState(false);
  const startXRef = useRef(null);
  const startWidthRef = useRef(null);

  /**
   * Handle pointer down - start resize drag
   * Captures pointer for smooth tracking outside element
   */
  const onPointerDown = useCallback(
    (e) => {
      e.preventDefault();
      e.stopPropagation();

      setIsResizing(true);
      startXRef.current = e.clientX;
      startWidthRef.current = width;

      // Capture pointer to track movement outside element bounds
      e.currentTarget.setPointerCapture(e.pointerId);
    },
    [width]
  );

  /**
   * Handle pointer move - update width during drag
   * Enforces minimum width constraint
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
   * Handle pointer up - end resize drag
   * Releases pointer capture
   */
  const onPointerUp = useCallback(
    (e) => {
      if (!isResizing) return;

      setIsResizing(false);
      e.currentTarget.releasePointerCapture(e.pointerId);
    },
    [isResizing]
  );

  /**
   * Handle pointer cancel - same as pointer up
   * Handles edge cases like losing focus
   */
  const onPointerCancel = useCallback(
    (e) => {
      if (!isResizing) return;

      setIsResizing(false);
      e.currentTarget.releasePointerCapture(e.pointerId);
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
