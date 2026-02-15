import { ArrowUp, ArrowDown } from 'lucide-react';

export default function SortableHeader({
  columnId,
  label,
  sortField,
  sortOrder,
  onSort,
  sortable = true,
  defaultOrder = 'asc',
  className = '',
}) {
  if (!sortable) {
    return (
      <th
        scope="col"
        className={`px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800 ${className}`}
      >
        {label}
      </th>
    );
  }

  const isActive = sortField === columnId;
  const nextOrder = isActive ? (sortOrder === 'asc' ? 'desc' : 'asc') : defaultOrder;

  return (
    <th
      scope="col"
      className={`px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 ${className}`}
      onClick={() => onSort(columnId, nextOrder)}
    >
      <div className={`flex items-center gap-1 ${className.includes('text-right') ? 'justify-end' : className.includes('text-center') ? 'justify-center' : ''}`}>
        {label}
        {isActive && (
          sortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </div>
    </th>
  );
}
