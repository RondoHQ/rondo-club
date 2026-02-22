import { useState, useEffect, useMemo, useCallback } from 'react';
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  flexRender,
} from '@tanstack/react-table';
import { ArrowUp, ArrowDown } from 'lucide-react';
import DataTableToolbar from './DataTableToolbar';
import ColumnSettingsPanel from './ColumnSettingsPanel';

// Compute default column visibility from column meta
function buildDefaultVisibility(columns) {
  return columns.reduce((acc, col) => {
    if (col.meta?.defaultHidden) acc[col.id] = false;
    return acc;
  }, {});
}

// Read persisted column visibility from localStorage
function readStoredVisibility(storageKey) {
  if (!storageKey) return null;
  try {
    const stored = localStorage.getItem(`rondo-col-${storageKey}`);
    return stored ? JSON.parse(stored) : null;
  } catch {
    return null;
  }
}

/**
 * Unified DataTable component built on TanStack Table v8.
 *
 * Supports two filter modes:
 *   - Uncontrolled: DataTable manages filter state internally (pass `storageKey` for URL persistence)
 *   - Controlled: Parent manages filter state (pass `filters`, `onFilterChange`, `onClearFilters`)
 *
 * @param {object[]} props.data - Row data array
 * @param {object[]} props.columns - Column definitions from createColumn()
 * @param {string} [props.storageKey] - Key for localStorage column visibility persistence
 * @param {boolean} [props.isLoading=false] - Show loading spinner
 * @param {ReactNode} [props.emptyIcon] - Icon for empty state
 * @param {string} [props.emptyTitle] - Heading for empty state
 * @param {string} [props.emptyDescription] - Description for empty state
 * @param {ReactNode} [props.toolbarEnd] - Extra elements on the right side of the toolbar
 * @param {ReactNode} [props.footer] - Optional <tfoot> element appended inside <table>
 * @param {string} [props.className] - Additional class for the card wrapper
 *
 * Controlled filter props (optional, enables controlled mode):
 * @param {object} [props.filters] - { colId: value } — active filter values
 * @param {function} [props.onFilterChange] - (colId, value) => void
 * @param {function} [props.onClearFilters] - () => void
 */
export default function DataTable({
  data = [],
  columns,
  storageKey,
  isLoading = false,
  emptyIcon,
  emptyTitle = 'Geen items gevonden',
  emptyDescription,
  toolbarEnd,
  footer,
  className = '',
  // Controlled filter mode
  filters: controlledFilters,
  onFilterChange: controlledOnFilterChange,
  onClearFilters: controlledOnClearFilters,
}) {
  const isControlled = controlledFilters !== undefined;

  // --- Uncontrolled filter state ---
  const [internalFilters, setInternalFilters] = useState({});

  const filters = isControlled ? controlledFilters : internalFilters;

  const setFilter = useCallback((colId, value) => {
    if (isControlled) {
      controlledOnFilterChange?.(colId, value);
    } else {
      setInternalFilters((prev) => {
        if (!value) {
          const next = { ...prev };
          delete next[colId];
          return next;
        }
        return { ...prev, [colId]: value };
      });
    }
  }, [isControlled, controlledOnFilterChange]);

  const clearFilters = useCallback(() => {
    if (isControlled) {
      controlledOnClearFilters?.();
    } else {
      setInternalFilters({});
    }
  }, [isControlled, controlledOnClearFilters]);

  // Convert filters dict → TanStack columnFilters format
  const columnFilters = useMemo(
    () =>
      Object.entries(filters)
        .filter(([, v]) => !!v)
        .map(([id, value]) => ({ id, value })),
    [filters],
  );

  const hasActiveFilters = columnFilters.length > 0;
  const activeFilterCount = columnFilters.length;

  // --- Column visibility (localStorage) ---
  const [columnVisibility, setColumnVisibility] = useState(() => {
    const stored = readStoredVisibility(storageKey);
    return stored ?? buildDefaultVisibility(columns);
  });

  useEffect(() => {
    if (storageKey) {
      localStorage.setItem(`rondo-col-${storageKey}`, JSON.stringify(columnVisibility));
    }
  }, [storageKey, columnVisibility]);

  // --- Column settings panel ---
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);

  // --- Sorting state (client-side) ---
  const [sorting, setSorting] = useState([]);

  // --- TanStack Table instance ---
  const table = useReactTable({
    data,
    columns,
    state: {
      columnFilters,
      sorting,
      columnVisibility,
    },
    // Filter state is managed externally — no-op handler
    onColumnFiltersChange: () => {},
    onSortingChange: setSorting,
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    manualFiltering: false,
    manualSorting: false,
  });

  const rows = table.getRowModel().rows;

  return (
    <div className="space-y-4">
      <DataTableToolbar
        columns={columns}
        filters={filters}
        onFilterChange={setFilter}
        onClearFilters={clearFilters}
        hasActiveFilters={hasActiveFilters}
        activeFilterCount={activeFilterCount}
        onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
        toolbarEnd={toolbarEnd}
      />

      <div
        className={`card overflow-x-auto overscroll-x-contain ${className}`}
        style={{ WebkitOverflowScrolling: 'touch', touchAction: 'pan-x' }}
      >
        {isLoading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan" />
          </div>
        ) : rows.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-12 text-center">
            {emptyIcon && (
              <div className="flex justify-center mb-4">
                <div className="p-3 bg-gray-100 rounded-full dark:bg-gray-700">
                  {emptyIcon}
                </div>
              </div>
            )}
            {emptyTitle && (
              <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                {emptyTitle}
              </h3>
            )}
            {emptyDescription && (
              <p className="text-sm text-gray-600 dark:text-gray-400">{emptyDescription}</p>
            )}
          </div>
        ) : (
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              {table.getHeaderGroups().map((headerGroup) => (
                <tr key={headerGroup.id} className="shadow-sm dark:shadow-gray-900/50">
                  {headerGroup.headers.map((header) => {
                    const meta = header.column.columnDef.meta || {};
                    const canSort = header.column.getCanSort();
                    const isSorted = header.column.getIsSorted();

                    return (
                      <th
                        key={header.id}
                        scope="col"
                        className={[
                          'px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800',
                          canSort ? 'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 select-none' : '',
                          meta.headerClassName || '',
                        ].join(' ')}
                        onClick={canSort ? header.column.getToggleSortingHandler() : undefined}
                        style={header.getSize() !== 150 ? { width: `${header.getSize()}px` } : undefined}
                      >
                        <div
                          className={[
                            'flex items-center gap-1',
                            meta.headerClassName?.includes('text-right') ? 'justify-end' : '',
                          ].join(' ')}
                        >
                          {flexRender(header.column.columnDef.header, header.getContext())}
                          {isSorted === 'asc' && <ArrowUp className="w-3 h-3 shrink-0" />}
                          {isSorted === 'desc' && <ArrowDown className="w-3 h-3 shrink-0" />}
                        </div>
                      </th>
                    );
                  })}
                </tr>
              ))}
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {rows.map((row, index) => (
                <tr
                  key={row.id}
                  className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
                    index % 2 === 1
                      ? 'bg-gray-50 dark:bg-gray-800/50'
                      : 'bg-white dark:bg-gray-800'
                  }`}
                >
                  {row.getVisibleCells().map((cell) => {
                    const meta = cell.column.columnDef.meta || {};
                    return (
                      <td
                        key={cell.id}
                        className={[
                          'px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap',
                          meta.className || '',
                        ].join(' ')}
                      >
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
            {footer}
          </table>
        )}
      </div>

      <ColumnSettingsPanel
        isOpen={isColumnSettingsOpen}
        onClose={() => setIsColumnSettingsOpen(false)}
        table={table}
      />
    </div>
  );
}
