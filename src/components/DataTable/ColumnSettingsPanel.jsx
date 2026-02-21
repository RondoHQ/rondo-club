import { useEffect, useCallback } from 'react';
import { Settings, X } from 'lucide-react';

/**
 * Column visibility modal — works in two modes:
 *
 * **TanStack Table mode** (pass `table`):
 *   Uses TanStack's column visibility API directly.
 *
 * **Simple mode** (pass `columns` + `onToggleColumn`):
 *   `columns` is an array of `{ id, label, isVisible }`.
 *   `onToggleColumn(id)` flips visibility.
 *
 * @param {boolean} props.isOpen
 * @param {function} props.onClose
 * @param {object} [props.table] - TanStack Table instance (mode A)
 * @param {Array<{id: string, label: string, isVisible: boolean}>} [props.columns] - Simple mode (mode B)
 * @param {function} [props.onToggleColumn] - (colId) => void — simple mode callback
 */
export default function ColumnSettingsPanel({ isOpen, onClose, table, columns: simpleColumns, onToggleColumn }) {
  // Normalise to a single interface regardless of mode
  const columns = table
    ? table.getAllLeafColumns().filter((col) => col.getCanHide()).map((col) => ({
        id: col.id,
        label: typeof col.columnDef.header === 'string'
          ? col.columnDef.header
          : col.columnDef.meta?.filterLabel || col.id,
        isVisible: col.getIsVisible(),
        toggle: col.getToggleVisibilityHandler(),
      }))
    : (simpleColumns || []).map((col) => ({
        id: col.id,
        label: col.label,
        isVisible: col.isVisible,
        toggle: () => onToggleColumn?.(col.id),
      }));

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
              {columns.map((col) => (
                <label
                  key={col.id}
                  className="flex items-center gap-3 p-3 rounded-lg border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 cursor-pointer hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
                >
                  <input
                    type="checkbox"
                    checked={col.isVisible}
                    onChange={col.toggle}
                    className="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-electric-cyan focus:ring-electric-cyan dark:bg-gray-700"
                  />
                  <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {col.label}
                  </span>
                </label>
              ))}
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
