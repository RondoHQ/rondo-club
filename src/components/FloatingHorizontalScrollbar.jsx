import { useEffect, useRef, useState } from 'react';

const INITIAL_LAYOUT = {
  contentWidth: 0,
  left: 0,
  visible: false,
  width: 0,
};

function layoutsMatch(previous, next) {
  return previous.contentWidth === next.contentWidth
    && previous.left === next.left
    && previous.visible === next.visible
    && previous.width === next.width;
}

/**
 * Keeps a wide scroll area's horizontal scrollbar within reach while its
 * native scrollbar is still below the viewport.
 */
export default function FloatingHorizontalScrollbar({ targetRef }) {
  const scrollbarRef = useRef(null);
  const animationFrameRef = useRef(null);
  const [layout, setLayout] = useState(INITIAL_LAYOUT);

  useEffect(() => {
    const target = targetRef.current;
    const scrollbar = scrollbarRef.current;

    if (!target || !scrollbar) return undefined;

    const updateLayout = () => {
      animationFrameRef.current = null;

      const rect = target.getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      const hasHorizontalOverflow = target.scrollWidth > target.clientWidth + 1;
      const left = Math.round(Math.max(0, rect.left));
      const right = Math.round(Math.min(window.innerWidth, rect.right));
      const width = Math.max(0, right - left);
      const visible = hasHorizontalOverflow
        && width > 0
        && rect.top < viewportHeight
        && rect.bottom > viewportHeight;
      const nextLayout = {
        contentWidth: target.scrollWidth,
        left,
        visible,
        width,
      };

      setLayout((previous) => layoutsMatch(previous, nextLayout) ? previous : nextLayout);

      if (Math.abs(scrollbar.scrollLeft - target.scrollLeft) > 1) {
        scrollbar.scrollLeft = target.scrollLeft;
      }
    };

    const scheduleLayoutUpdate = () => {
      if (animationFrameRef.current !== null) return;
      animationFrameRef.current = window.requestAnimationFrame(updateLayout);
    };

    const syncFromTarget = () => {
      if (Math.abs(scrollbar.scrollLeft - target.scrollLeft) > 1) {
        scrollbar.scrollLeft = target.scrollLeft;
      }
    };

    const syncFromScrollbar = () => {
      if (Math.abs(target.scrollLeft - scrollbar.scrollLeft) > 1) {
        target.scrollLeft = scrollbar.scrollLeft;
      }
    };

    const resizeObserver = new ResizeObserver(scheduleLayoutUpdate);
    resizeObserver.observe(target);
    if (target.firstElementChild) resizeObserver.observe(target.firstElementChild);

    target.addEventListener('scroll', syncFromTarget, { passive: true });
    scrollbar.addEventListener('scroll', syncFromScrollbar, { passive: true });
    document.addEventListener('scroll', scheduleLayoutUpdate, { capture: true, passive: true });
    window.addEventListener('resize', scheduleLayoutUpdate, { passive: true });
    scheduleLayoutUpdate();

    return () => {
      resizeObserver.disconnect();
      target.removeEventListener('scroll', syncFromTarget);
      scrollbar.removeEventListener('scroll', syncFromScrollbar);
      document.removeEventListener('scroll', scheduleLayoutUpdate, true);
      window.removeEventListener('resize', scheduleLayoutUpdate);
      if (animationFrameRef.current !== null) {
        window.cancelAnimationFrame(animationFrameRef.current);
      }
    };
  }, [targetRef]);

  return (
    <div
      ref={scrollbarRef}
      className={`fixed bottom-0 z-30 h-4 overflow-x-auto overflow-y-hidden border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 ${layout.visible ? '' : 'invisible pointer-events-none'}`}
      style={{ left: layout.left, width: layout.width }}
      role="region"
      aria-label="Horizontaal door de tabel scrollen"
      tabIndex={layout.visible ? 0 : -1}
    >
      <div aria-hidden="true" style={{ width: layout.contentWidth, height: 1 }} />
    </div>
  );
}
