import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { FILTER_TYPES } from './columnHelpers';

const FALLBACK_SECTION = 'Overige';
// Panel width on >= sm screens. Below sm we shrink to roughly the old single-column width.
const PANEL_WIDTH = 576;
const PANEL_WIDTH_NARROW = 288;
const VIEWPORT_MARGIN = 12; // keep panel this far from viewport edges

/**
 * Filter dropdown panel — grouped sections + 2-col grid on roomy screens, 1-col below.
 * Rendered via portal (appended to <body>) and positioned with position:fixed
 * so it is never clipped by overflow:hidden/auto ancestors.
 *
 * Filters are grouped by their `meta.filterSection` value. Columns without a
 * section fall into a default "Overige" group at the bottom. Within a section,
 * controls render in source order:
 *   SELECT  → <select> with options
 *   TEXT    → <input type="text">
 *   BOOLEAN → toggle switch
 */
export default function FilterDropdown({ anchorRef, columns, filters, onFilterChange, onClearFilters, hasActiveFilters }) {
  const filterableColumns = columns.filter((col) => col.enableColumnFilter && col.meta?.filterType);
  const [position, setPosition] = useState({ top: 0, left: 0 });
  const [isWide, setIsWide] = useState(() => typeof window !== 'undefined' && window.innerWidth >= 640);
  const dropdownRef = useRef(null);

  // Compute position from the anchor button's bounding rect, clamping to viewport.
  useEffect(() => {
    if (!anchorRef?.current) return;

    const updatePosition = () => {
      const rect = anchorRef.current.getBoundingClientRect();
      const vw = window.innerWidth;
      const wide = vw >= 640;
      setIsWide(wide);

      const panelWidth = wide ? PANEL_WIDTH : PANEL_WIDTH_NARROW;
      let left = rect.left;
      // Prevent panel from running off the right edge of the viewport.
      const maxLeft = vw - panelWidth - VIEWPORT_MARGIN;
      if (left > maxLeft) left = Math.max(VIEWPORT_MARGIN, maxLeft);

      setPosition({
        top: rect.bottom + 8,
        left,
      });
    };

    updatePosition();

    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
    return () => {
      window.removeEventListener('scroll', updatePosition, true);
      window.removeEventListener('resize', updatePosition);
    };
  }, [anchorRef]);

  // Group filterable columns by section, preserving section order from first appearance.
  const sections = useMemo(() => {
    const order = [];
    const buckets = new Map();
    for (const col of filterableColumns) {
      const key = col.meta?.filterSection || FALLBACK_SECTION;
      if (!buckets.has(key)) {
        buckets.set(key, []);
        order.push(key);
      }
      buckets.get(key).push(col);
    }
    return order.map((name) => ({ name, cols: buckets.get(name) }));
  }, [filterableColumns]);

  if (filterableColumns.length === 0) return null;

  const widthClass = isWide ? 'w-[36rem]' : 'w-72';
  const gridClass = isWide ? 'grid grid-cols-2 gap-x-4 gap-y-3' : 'space-y-3';

  return createPortal(
    <div
      ref={dropdownRef}
      data-filter-dropdown
      className={`fixed ${widthClass} max-h-[80vh] overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-[9999]`}
      style={{ top: position.top, left: position.left }}
    >
      <div className="p-4 space-y-4">
        {sections.map((section, sectionIdx) => (
          <div key={section.name}>
            {(sections.length > 1 || sectionIdx > 0) && (
              <h2 className="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2 pb-1 border-b border-gray-100 dark:border-gray-700">
                {section.name}
              </h2>
            )}
            <div className={gridClass}>
              {section.cols.map((col) => renderControl(col, filters, onFilterChange))}
            </div>
          </div>
        ))}

        {hasActiveFilters && (
          <button
            onClick={onClearFilters}
            className="w-full text-sm text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light font-medium pt-2 border-t border-gray-200 dark:border-gray-700"
          >
            Alle filters wissen
          </button>
        )}
      </div>
    </div>,
    document.body,
  );
}

function renderControl(col, filters, onFilterChange) {
  const meta = col.meta;
  const value = filters[col.id] || '';
  const label = meta.filterLabel || (typeof col.header === 'string' ? col.header : col.id);

  if (meta.filterType === FILTER_TYPES.SELECT) {
    return (
      <div key={col.id}>
        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
          {label}
        </h3>
        <select
          value={value}
          onChange={(e) => onFilterChange(col.id, e.target.value)}
          className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
        >
          <option value="">Alle</option>
          {meta.filterOptions.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>
    );
  }

  if (meta.filterType === FILTER_TYPES.TEXT) {
    return (
      <div key={col.id}>
        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
          {label}
        </h3>
        <input
          type="text"
          value={value}
          onChange={(e) => onFilterChange(col.id, e.target.value)}
          placeholder={`Zoek ${label.toLowerCase()}...`}
          className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
        />
      </div>
    );
  }

  if (meta.filterType === FILTER_TYPES.BOOLEAN) {
    return (
      <div key={col.id} className="flex items-center min-h-[36px]">
        <label className="flex items-center cursor-pointer">
          <div className="relative">
            <input
              type="checkbox"
              checked={value === '1'}
              onChange={(e) => onFilterChange(col.id, e.target.checked ? '1' : '')}
              className="sr-only peer"
            />
            <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:bg-electric-cyan transition-colors"></div>
            <div className="absolute left-[2px] top-[2px] bg-white w-4 h-4 rounded-full transition-transform peer-checked:translate-x-4"></div>
          </div>
          <span className="ml-3 text-sm font-medium text-gray-700 dark:text-gray-200">
            {label}
          </span>
        </label>
      </div>
    );
  }

  return null;
}
