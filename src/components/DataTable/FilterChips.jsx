import { X } from 'lucide-react';

/**
 * Renders active filter chips (gray pills) below the filter button.
 * Each chip shows the column label + active value and has an X to clear it.
 */
export default function FilterChips({ columns, filters, onClearFilter }) {
  const activeFilters = Object.entries(filters).filter(([, v]) => !!v);
  if (activeFilters.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2">
      {activeFilters.map(([colId, value]) => {
        const col = columns.find((c) => c.id === colId);
        if (!col) return null;

        const meta = col.meta || {};

        // Resolve display label for the active value
        let valueLabel = value;
        if (meta.getFilterLabel) {
          valueLabel = meta.getFilterLabel(value);
        } else if (meta.filterOptions?.length > 0) {
          const opt = meta.filterOptions.find((o) => o.value === value);
          if (opt) valueLabel = opt.label;
        }

        const colLabel = meta.filterLabel || (typeof col.header === 'string' ? col.header : colId);

        return (
          <span
            key={colId}
            className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs"
          >
            {colLabel}: {valueLabel}
            <button
              onClick={() => onClearFilter(colId)}
              className="hover:text-gray-600 dark:hover:text-gray-300 ml-0.5"
              aria-label={`Verwijder filter ${colLabel}`}
            >
              <X className="w-3 h-3" />
            </button>
          </span>
        );
      })}
    </div>
  );
}
