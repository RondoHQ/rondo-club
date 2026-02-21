import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { FILTER_TYPES } from './columnHelpers';

/**
 * Filter dropdown panel — matches the People list filter style.
 * Rendered via portal (appended to <body>) and positioned with position:fixed
 * so it is never clipped by overflow:hidden/auto ancestors.
 *
 * Renders one control per filterable column:
 *   SELECT  → <select> with options
 *   TEXT    → <input type="text">
 *   BOOLEAN → toggle switch
 */
export default function FilterDropdown({ anchorRef, columns, filters, onFilterChange, onClearFilters, hasActiveFilters }) {
  const filterableColumns = columns.filter((col) => col.enableColumnFilter && col.meta?.filterType);
  const [position, setPosition] = useState({ top: 0, left: 0 });
  const dropdownRef = useRef(null);

  // Compute position from the anchor button's bounding rect
  useEffect(() => {
    if (!anchorRef?.current) return;

    const updatePosition = () => {
      const rect = anchorRef.current.getBoundingClientRect();
      setPosition({
        top: rect.bottom + 8,
        left: rect.left,
      });
    };

    updatePosition();

    // Re-position on scroll or resize
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
    return () => {
      window.removeEventListener('scroll', updatePosition, true);
      window.removeEventListener('resize', updatePosition);
    };
  }, [anchorRef]);

  if (filterableColumns.length === 0) return null;

  return createPortal(
    <div
      ref={dropdownRef}
      data-filter-dropdown
      className="fixed w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-[9999]"
      style={{ top: position.top, left: position.left }}
    >
      <div className="p-4 space-y-4">
        {filterableColumns.map((col) => {
          const meta = col.meta;
          const value = filters[col.id] || '';
          const label = meta.filterLabel || (typeof col.header === 'string' ? col.header : col.id);

          if (meta.filterType === FILTER_TYPES.SELECT) {
            return (
              <div key={col.id}>
                <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
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
                <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
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
              <div key={col.id}>
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
        })}

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
