import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { shouldPlacePopoverBelow } from '@/utils/popoverPosition';

export default function AnchoredPopover({
  anchor,
  children,
  className = '',
  id,
  initialFocusRef,
  labelledBy,
  maxWidth = 448,
  onClose,
  preferredHeight = 360,
}) {
  const popoverRef = useRef(null);
  const hasFocused = useRef(false);
  const [position, setPosition] = useState(null);

  const updatePosition = useCallback(() => {
    if (!anchor) return;

    const margin = 16;
    const gap = 8;
    const width = Math.min(maxWidth, window.innerWidth - (margin * 2));
    const anchorRect = anchor.getBoundingClientRect();
    const availableBelow = window.innerHeight - anchorRect.bottom - gap - margin;
    const availableAbove = anchorRect.top - gap - margin;
    const placeBelow = shouldPlacePopoverBelow(anchorRect, window.innerHeight, preferredHeight, margin, gap);
    const availableHeight = placeBelow ? availableBelow : availableAbove;
    const centeredLeft = anchorRect.left + (anchorRect.width / 2) - (width / 2);

    setPosition({
      bottom: placeBelow ? undefined : window.innerHeight - anchorRect.top + gap,
      left: Math.max(margin, Math.min(centeredLeft, window.innerWidth - width - margin)),
      maxHeight: Math.max(120, availableHeight),
      top: placeBelow ? anchorRect.bottom + gap : undefined,
      width,
    });
  }, [anchor, maxWidth, preferredHeight]);

  useLayoutEffect(() => {
    updatePosition();
  }, [updatePosition]);

  useEffect(() => {
    if (!position || hasFocused.current) return;
    initialFocusRef?.current?.focus();
    hasFocused.current = true;
  }, [initialFocusRef, position]);

  useEffect(() => {
    const handlePointerDown = (event) => {
      if (popoverRef.current?.contains(event.target) || anchor?.contains(event.target)) return;
      onClose();
    };
    const handleKeyDown = (event) => {
      if (event.key !== 'Escape') return;
      onClose();
      anchor?.focus();
    };
    const handleScroll = (event) => {
      if (popoverRef.current?.contains(event.target)) return;
      updatePosition();
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    window.addEventListener('resize', updatePosition);
    window.addEventListener('scroll', handleScroll, true);

    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
      window.removeEventListener('resize', updatePosition);
      window.removeEventListener('scroll', handleScroll, true);
    };
  }, [anchor, onClose, updatePosition]);

  if (!position) return null;

  return createPortal(
    <div
      ref={popoverRef}
      id={id}
      role="dialog"
      aria-labelledby={labelledBy}
      className={`fixed z-50 overflow-y-auto overscroll-contain rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900 ${className}`}
      style={position}
    >
      {children}
    </div>,
    document.body
  );
}
