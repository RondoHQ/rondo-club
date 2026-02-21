import { useState, useRef, useEffect } from 'react';
import { Filter, Settings } from 'lucide-react';
import FilterDropdown from './FilterDropdown';
import FilterChips from './FilterChips';

/**
 * Unified toolbar row for DataTable pages.
 *
 * Layout:
 *   [Filter button] [active chips ...]          [custom slot] [column settings cog]
 *
 * @param {object[]} props.columns - Column definitions (createColumn output)
 * @param {object} props.filters - Active filter values { colId: value }
 * @param {function} props.onFilterChange - (colId, value) => void
 * @param {function} props.onClearFilters - () => void
 * @param {boolean} props.hasActiveFilters
 * @param {number} props.activeFilterCount
 * @param {function} props.onOpenColumnSettings - Opens the column settings modal
 * @param {ReactNode} [props.toolbarEnd] - Extra buttons/content on the right side
 */
export default function DataTableToolbar({
  columns,
  filters,
  onFilterChange,
  onClearFilters,
  hasActiveFilters,
  activeFilterCount,
  onOpenColumnSettings,
  toolbarEnd,
}) {
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const buttonRef = useRef(null);
  const wrapperRef = useRef(null);

  const filterableColumns = columns.filter((c) => c.enableColumnFilter && c.meta?.filterType);
  const hasFilterableColumns = filterableColumns.length > 0;

  // Close dropdown on outside click.
  // The dropdown is rendered via portal so we check both the wrapper and document.
  useEffect(() => {
    if (!isFilterOpen) return;
    const handler = (e) => {
      // Allow clicks inside the button wrapper
      if (wrapperRef.current && wrapperRef.current.contains(e.target)) return;
      // Allow clicks inside the portal-rendered dropdown (it is appended to body,
      // not inside wrapperRef, so we check via closest data attribute)
      if (e.target.closest('[data-filter-dropdown]')) return;
      setIsFilterOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [isFilterOpen]);

  return (
    <div className="flex flex-row flex-wrap items-center justify-between gap-2">
      {/* Left: filter button + active chips */}
      <div className="flex flex-wrap items-center gap-2">
        {hasFilterableColumns && (
          <div ref={wrapperRef}>
            <button
              ref={buttonRef}
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${
                hasActiveFilters
                  ? 'bg-cyan-50 text-bright-cobalt border-cyan-200 dark:bg-cyan-900/20 dark:text-electric-cyan dark:border-cyan-700'
                  : ''
              }`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-electric-cyan text-white text-xs rounded-full">
                  {activeFilterCount}
                </span>
              )}
            </button>

            {isFilterOpen && (
              <FilterDropdown
                anchorRef={buttonRef}
                columns={columns}
                filters={filters}
                onFilterChange={(colId, value) => {
                  onFilterChange(colId, value);
                }}
                onClearFilters={() => {
                  onClearFilters();
                  setIsFilterOpen(false);
                }}
                hasActiveFilters={hasActiveFilters}
              />
            )}
          </div>
        )}

        <FilterChips
          columns={columns}
          filters={filters}
          onClearFilter={(colId) => onFilterChange(colId, '')}
        />
      </div>

      {/* Right: custom slot + column settings cog */}
      <div className="flex items-center gap-2">
        {toolbarEnd}
        <button
          onClick={onOpenColumnSettings}
          className="btn-secondary"
          title="Kolommen aanpassen"
          aria-label="Kolommen aanpassen"
        >
          <Settings className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
