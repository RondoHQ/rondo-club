/**
 * Filter type constants for DataTable column definitions.
 */
export const FILTER_TYPES = {
  SELECT: 'select',
  TEXT: 'text',
  BOOLEAN: 'boolean',
};

/**
 * Creates a TanStack Table v8 column definition with Rondo-specific filter metadata.
 *
 * @param {object} config
 * @param {string} config.id - Unique column ID (used as URL param key for URL filters)
 * @param {string} config.header - Display label for the column header
 * @param {string} [config.accessorKey] - Object property key to access data
 * @param {function} [config.accessorFn] - Custom accessor function: (row) => value
 * @param {function} [config.cell] - Custom cell renderer: ({ row, getValue }) => ReactNode
 * @param {string} [config.filterType] - One of FILTER_TYPES values, or null/undefined for no filter
 * @param {Array<{value: string, label: string}>} [config.filterOptions] - Options for SELECT filter
 * @param {function} [config.filterFn] - Custom TanStack filter function (overrides default)
 * @param {function} [config.getFilterLabel] - Returns display label for active filter chip: (value) => string
 * @param {string} [config.filterLabel] - Override column header in filter dropdown/chip
 * @param {string} [config.filterSection] - Optional section heading to group this filter under in the FilterDropdown. Filters without a section land in an "Overige" group.
 * @param {boolean} [config.sortable=true] - Whether the column supports sorting
 * @param {boolean} [config.defaultHidden=false] - Hide column by default
 * @param {boolean} [config.enableHiding=true] - Whether column appears in column picker (false for action columns)
 * @param {number} [config.size] - Default column width in pixels
 * @param {string} [config.className] - Additional CSS class for <td> cells
 * @param {string} [config.headerClassName] - Additional CSS class for <th> header
 */
export function createColumn({
  id,
  header,
  accessorKey,
  accessorFn,
  cell,
  filterType = null,
  filterOptions = [],
  filterFn: customFilterFn,
  getFilterLabel,
  filterLabel,
  filterSection = null,
  sortable = true,
  defaultHidden = false,
  enableHiding = true,
  size,
  className = '',
  headerClassName = '',
}) {
  const def = {
    id,
    header,
    enableSorting: sortable,
    enableHiding,
    enableColumnFilter: !!filterType,
    meta: {
      filterType: filterType || null,
      filterOptions,
      getFilterLabel: getFilterLabel || null,
      filterLabel: filterLabel || header,
      filterSection,
      defaultHidden,
      className,
      headerClassName,
    },
  };

  if (accessorKey !== undefined) def.accessorKey = accessorKey;
  if (accessorFn !== undefined) def.accessorFn = accessorFn;
  if (cell !== undefined) def.cell = cell;
  if (size !== undefined) def.size = size;

  // Apply filter function
  if (customFilterFn) {
    def.filterFn = customFilterFn;
  } else if (filterType === FILTER_TYPES.SELECT || filterType === FILTER_TYPES.BOOLEAN) {
    // Exact-match: empty value = no filter, otherwise compare strictly
    def.filterFn = (row, colId, value) => !value || row.getValue(colId) === value;
  } else if (filterType === FILTER_TYPES.TEXT) {
    def.filterFn = 'includesString';
  }

  return def;
}
