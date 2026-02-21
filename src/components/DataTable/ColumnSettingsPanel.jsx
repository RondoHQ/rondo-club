import { useEffect, useCallback } from 'react';
import { Settings, X } from 'lucide-react';

/**
 * Column visibility modal for DataTable.
 * Checkboxes to show/hide columns. Changes are applied instantly.
 * Column order reordering can be added in a future iteration.
 *
 * @param {boolean} props.isOpen
 * @param {function} props.onClose
 * @param {object} props.table - TanStack Table instance
 */
export default function ColumnSettingsPanel({ isOpen, onClose, table }) {
  const columns = table.getAllLeafColumns().filter((col) => col.getCanHide());

  useEffect(() => {
    if (!isOpen) return;
    const handler = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [isOpen, onClose]);

  const handleBackdropClick = useCallback((e) => {
    if (e.target === e.currentTarget) onClose();
  }, [onClose]);

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      onClick={handleBackdropClick}
    >
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <div className="flex items-center gap-2">
            <Settings className="w-5 h-5 text-gray-600 dark:text-gray-400" />
            <h2 className="text-lg font-semibold dark:text-gray-50">Kolommen aanpassen</h2>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            aria-label="Sluiten"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-4">
          <p className="text-sm text-gray-600 dark:text-gray-300 mb-4">
            Vink kolommen aan of uit om ze te tonen of verbergen.
          </p>

          {columns.length === 0 ? (
            <p className="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
              Geen kolommen beschikbaar om aan te passen.
            </p>
          ) : (
            <div className="space-y-2">
              {columns.map((column) => {
                const label =
                  typeof column.columnDef.header === 'string'
                    ? column.columnDef.header
                    : column.columnDef.meta?.filterLabel || column.id;

                return (
                  <label
                    key={column.id}
                    className="flex items-center gap-3 p-3 rounded-lg border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 cursor-pointer hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
                  >
                    <input
                      type="checkbox"
                      checked={column.getIsVisible()}
                      onChange={column.getToggleVisibilityHandler()}
                      className="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-electric-cyan focus:ring-electric-cyan dark:bg-gray-700"
                    />
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {label}
                    </span>
                  </label>
                );
              })}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button onClick={onClose} className="btn-secondary">
            Sluiten
          </button>
        </div>
      </div>
    </div>
  );
}
