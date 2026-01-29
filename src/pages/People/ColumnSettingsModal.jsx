import { useState, useEffect, useCallback } from 'react';
import { Settings, X, GripVertical, RotateCcw } from 'lucide-react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useListPreferences } from '@/hooks/useListPreferences';

/**
 * Sortable column item for drag-and-drop reordering
 * Uses dnd-kit's useSortable hook
 */
function SortableColumnItem({ column, isVisible, onToggleVisibility, isCustomField }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: column.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-center gap-3 p-3 rounded-lg border-2 ${
        isDragging
          ? 'border-accent-300 dark:border-accent-600 shadow-lg bg-white dark:bg-gray-800'
          : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'
      }`}
    >
      {/* Drag handle */}
      <button
        {...attributes}
        {...listeners}
        className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-grab active:cursor-grabbing touch-none"
        aria-label="Sleep om te herordenen"
      >
        <GripVertical className="w-4 h-4" />
      </button>

      {/* Checkbox */}
      <label className="flex items-center flex-1 cursor-pointer">
        <input
          type="checkbox"
          checked={isVisible}
          onChange={() => onToggleVisibility(column.id)}
          className="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500 dark:bg-gray-700"
        />
        <span className="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">
          {column.label}
        </span>
        {isCustomField && (
          <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">
            (aangepast veld)
          </span>
        )}
      </label>
    </div>
  );
}

/**
 * Column Settings Modal for customizing People list columns
 *
 * Features:
 * - Drag-and-drop column reordering using dnd-kit
 * - Checkbox toggles for column visibility
 * - Instant apply (no save button)
 * - Reset to defaults functionality
 * - Name column is always first and not in sortable list
 *
 * @param {Object} props
 * @param {boolean} props.isOpen - Whether the modal is open
 * @param {function} props.onClose - Callback to close the modal
 */
export default function ColumnSettingsModal({ isOpen, onClose }) {
  const { preferences, isLoading, updatePreferences } = useListPreferences();

  // Local state for column order (for drag-drop preview)
  const [localOrder, setLocalOrder] = useState([]);

  // Initialize local order from preferences
  useEffect(() => {
    if (preferences?.column_order?.length > 0) {
      // Filter out 'name' column - it's always first and not sortable
      setLocalOrder(preferences.column_order.filter(id => id !== 'name'));
    } else if (preferences?.available_columns?.length > 0) {
      // Fall back to available columns order
      setLocalOrder(
        preferences.available_columns
          .map(col => col.id)
          .filter(id => id !== 'name')
      );
    }
  }, [preferences?.column_order, preferences?.available_columns]);

  // Get set of visible column IDs for quick lookup
  const visibleColumnsSet = new Set(preferences?.visible_columns || []);

  // Create map of column metadata by ID
  const columnMap = {};
  if (preferences?.available_columns) {
    preferences.available_columns.forEach(col => {
      columnMap[col.id] = col;
    });
  }

  // Dnd-kit sensors configuration
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    }),
    useSensor(TouchSensor, {
      activationConstraint: { delay: 200, tolerance: 8 },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  // Handle drag end - reorder columns
  const handleDragEnd = useCallback((event) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const oldIndex = localOrder.indexOf(active.id);
      const newIndex = localOrder.indexOf(over.id);
      const newOrder = arrayMove(localOrder, oldIndex, newIndex);

      setLocalOrder(newOrder);

      // Update preferences with new order (name always first, then the reordered columns)
      updatePreferences({ column_order: ['name', ...newOrder] });
    }
  }, [localOrder, updatePreferences]);

  // Handle visibility toggle
  const handleToggleVisibility = useCallback((columnId) => {
    const currentVisible = preferences?.visible_columns || [];
    const newVisible = currentVisible.includes(columnId)
      ? currentVisible.filter(id => id !== columnId)
      : [...currentVisible, columnId];

    updatePreferences({ visible_columns: newVisible });
  }, [preferences?.visible_columns, updatePreferences]);

  // Handle reset to defaults
  const handleReset = useCallback(() => {
    updatePreferences({ reset: true });
  }, [updatePreferences]);

  // Handle ESC key to close modal
  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  // Handle backdrop click to close
  const handleBackdropClick = useCallback((event) => {
    if (event.target === event.currentTarget) {
      onClose();
    }
  }, [onClose]);

  if (!isOpen) return null;

  // Get columns to display in sortable list (everything except 'name')
  const sortableColumns = localOrder
    .map(id => columnMap[id])
    .filter(Boolean);

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
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
          {isLoading ? (
            <div className="flex items-center justify-center h-32">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
            </div>
          ) : (
            <>
              {/* Instructions */}
              <p className="text-sm text-gray-600 dark:text-gray-300 mb-4">
                Sleep om te herordenen, vink aan om te tonen.
              </p>

              {/* Name column - always visible, not sortable */}
              <div className="mb-4">
                <div className="flex items-center gap-3 p-3 rounded-lg border-2 border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                  <div className="p-1 text-gray-300 dark:text-gray-600">
                    <GripVertical className="w-4 h-4" />
                  </div>
                  <label className="flex items-center flex-1 cursor-not-allowed">
                    <input
                      type="checkbox"
                      checked={true}
                      disabled
                      className="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 bg-gray-100 dark:bg-gray-600 cursor-not-allowed"
                    />
                    <span className="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                      {columnMap['name']?.label || 'Naam'}
                    </span>
                    <span className="ml-2 text-xs text-gray-400 dark:text-gray-500">
                      (altijd zichtbaar)
                    </span>
                  </label>
                </div>
              </div>

              {/* Sortable columns */}
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
              >
                <SortableContext
                  items={localOrder}
                  strategy={verticalListSortingStrategy}
                >
                  <div className="space-y-2">
                    {sortableColumns.map((column) => (
                      <SortableColumnItem
                        key={column.id}
                        column={column}
                        isVisible={visibleColumnsSet.has(column.id)}
                        onToggleVisibility={handleToggleVisibility}
                        isCustomField={column.is_custom_field || false}
                      />
                    ))}
                  </div>
                </SortableContext>
              </DndContext>

              {/* Empty state */}
              {sortableColumns.length === 0 && !isLoading && (
                <p className="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                  Geen kolommen beschikbaar om aan te passen.
                </p>
              )}
            </>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button
            onClick={handleReset}
            className="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400"
          >
            <RotateCcw className="w-4 h-4" />
            Standaardinstellingen herstellen
          </button>
          <button
            onClick={onClose}
            className="btn-secondary"
          >
            Sluiten
          </button>
        </div>
      </div>
    </div>
  );
}
