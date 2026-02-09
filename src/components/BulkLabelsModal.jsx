import { useState, useEffect } from 'react';
import { X, Tag, Check } from 'lucide-react';

/**
 * Modal for managing labels on multiple items in bulk.
 * Supports both adding and removing labels.
 *
 * @param {Object} props
 * @param {boolean} props.isOpen - Whether the modal is visible
 * @param {Function} props.onClose - Called when closing the modal
 * @param {number} props.selectedCount - Number of selected items
 * @param {Array} props.labels - Available labels to choose from
 * @param {Function} props.onSubmit - Called with (mode, labelIds) on submit
 * @param {boolean} props.isLoading - Whether a submission is in progress
 * @param {string} [props.entityName='item'] - Singular name of the entity type
 * @param {string} [props.entityNamePlural='items'] - Plural name of the entity type
 */
export default function BulkLabelsModal({
  isOpen,
  onClose,
  selectedCount,
  labels,
  onSubmit,
  isLoading,
  entityName = 'item',
  entityNamePlural = 'items',
}) {
  const [mode, setMode] = useState('add');
  const [selectedLabelIds, setSelectedLabelIds] = useState([]);

  // Reset when modal opens
  useEffect(() => {
    if (isOpen) {
      setMode('add');
      setSelectedLabelIds([]);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleLabelToggle = (labelId) => {
    setSelectedLabelIds((prev) =>
      prev.includes(labelId)
        ? prev.filter((id) => id !== labelId)
        : [...prev, labelId]
    );
  };

  const displayEntityName = selectedCount === 1 ? entityName : entityNamePlural;
  const modeVerb = mode === 'add' ? 'toevoegen aan' : 'verwijderen van';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Labels beheren</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Labels {modeVerb} {selectedCount} {displayEntityName}:
          </p>

          {/* Mode toggle */}
          <div className="flex rounded-lg border border-gray-200 dark:border-gray-600 p-1">
            <button
              type="button"
              onClick={() => {
                setMode('add');
                setSelectedLabelIds([]);
              }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'add'
                  ? 'bg-cyan-100 dark:bg-obsidian/50 text-bright-cobalt dark:text-electric-cyan-light'
                  : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Labels toevoegen
            </button>
            <button
              type="button"
              onClick={() => {
                setMode('remove');
                setSelectedLabelIds([]);
              }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'remove'
                  ? 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300'
                  : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Labels verwijderen
            </button>
          </div>

          {/* Label list */}
          {!labels || labels.length === 0 ? (
            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
              <Tag className="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
              <p className="text-sm">Geen labels beschikbaar.</p>
              <p className="text-xs">Maak eerst labels aan om deze functie te gebruiken.</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {labels.map((label) => {
                const isChecked = selectedLabelIds.includes(label.id);
                return (
                  <button
                    key={label.id}
                    type="button"
                    onClick={() => handleLabelToggle(label.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isChecked
                        ? mode === 'add'
                          ? 'border-electric-cyan bg-cyan-50 dark:bg-deep-midnight'
                          : 'border-red-500 bg-red-50 dark:bg-red-900/30'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div
                      className={`flex items-center justify-center w-5 h-5 border-2 rounded ${
                        isChecked
                          ? mode === 'add'
                            ? 'bg-electric-cyan border-electric-cyan'
                            : 'bg-red-600 border-red-600'
                          : 'border-gray-300 dark:border-gray-500'
                      }`}
                    >
                      {isChecked && <Check className="w-3 h-3 text-white" />}
                    </div>
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-50">
                      {label.name}
                    </span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button
            type="button"
            onClick={onClose}
            className="btn-secondary"
            disabled={isLoading}
          >
            Annuleren
          </button>
          <button
            type="button"
            onClick={() => onSubmit(mode, selectedLabelIds)}
            className={
              mode === 'add'
                ? 'btn-primary'
                : 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50'
            }
            disabled={isLoading || selectedLabelIds.length === 0}
          >
            {isLoading
              ? mode === 'add'
                ? 'Toevoegen...'
                : 'Verwijderen...'
              : `${selectedLabelIds.length} ${selectedLabelIds.length === 1 ? 'label' : 'labels'} ${mode === 'add' ? 'toevoegen' : 'verwijderen'}`}
          </button>
        </div>
      </div>
    </div>
  );
}
