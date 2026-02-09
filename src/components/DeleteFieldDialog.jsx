import { useState, useEffect } from 'react';
import { X, AlertTriangle, Archive, Trash2 } from 'lucide-react';

export default function DeleteFieldDialog({
  isOpen,
  onClose,
  onArchive,
  onDelete,
  field,
  isDeleting = false,
  usageCount = 0,
}) {
  const [confirmText, setConfirmText] = useState('');

  // Reset confirmation text when dialog opens or field changes
  useEffect(() => {
    if (isOpen) {
      setConfirmText('');
    }
  }, [isOpen, field]);

  // Handle Escape key
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen && !isDeleting) {
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, isDeleting, onClose]);

  const canDelete = confirmText === field?.label;
  const entityName = usageCount === 1 ? 'record' : 'records';

  if (!isOpen || !field) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/50 transition-opacity"
        onClick={isDeleting ? undefined : onClose}
      />

      {/* Dialog */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-lg shadow-xl">
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-red-100 dark:bg-red-900/30 rounded-full">
                <AlertTriangle className="w-5 h-5 text-red-600 dark:text-red-400" />
              </div>
              <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Delete Field</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400">"{field.label}"</p>
              </div>
            </div>
            <button
              onClick={onClose}
              disabled={isDeleting}
              className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Content */}
          <div className="px-6 py-4 space-y-4">
            {/* Usage warning */}
            <div className="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
              <p className="text-sm text-gray-600 dark:text-gray-400">
                {usageCount > 0 ? (
                  <>
                    This field has values on <span className="font-medium text-gray-900 dark:text-gray-50">{usageCount}</span> {entityName}.
                  </>
                ) : (
                  'This field has no stored values.'
                )}
              </p>
            </div>

            {/* Archive option */}
            <div className="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <Archive className="w-5 h-5 text-gray-500 dark:text-gray-400 mt-0.5" />
                <div className="flex-1">
                  <h3 className="font-medium text-gray-900 dark:text-gray-50">
                    Archive Field
                    <span className="ml-2 text-xs font-normal text-electric-cyan dark:text-electric-cyan bg-cyan-50 dark:bg-deep-midnight px-2 py-0.5 rounded">
                      Recommended
                    </span>
                  </h3>
                  <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Hide this field from the UI. Data is preserved and can be restored later.
                  </p>
                  <button
                    onClick={onArchive}
                    disabled={isDeleting}
                    className="mt-3 btn-secondary inline-flex items-center gap-2"
                  >
                    {isDeleting && (
                      <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle
                          className="opacity-25"
                          cx="12"
                          cy="12"
                          r="10"
                          stroke="currentColor"
                          strokeWidth="4"
                          fill="none"
                        />
                        <path
                          className="opacity-75"
                          fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                      </svg>
                    )}
                    <Archive className="w-4 h-4" />
                    Archive Field
                  </button>
                </div>
              </div>
            </div>

            {/* Permanent delete option */}
            <div className="border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <Trash2 className="w-5 h-5 text-red-500 dark:text-red-400 mt-0.5" />
                <div className="flex-1">
                  <h3 className="font-medium text-red-700 dark:text-red-300">
                    Permanently Delete
                  </h3>
                  <p className="text-sm text-red-600 dark:text-red-400 mt-1">
                    Remove field definition AND all stored values. This cannot be undone.
                  </p>

                  {/* Type to confirm */}
                  <div className="mt-3">
                    <label className="block text-sm text-red-700 dark:text-red-300 mb-1">
                      Type <span className="font-mono font-semibold">{field.label}</span> to confirm:
                    </label>
                    <input
                      type="text"
                      value={confirmText}
                      onChange={(e) => setConfirmText(e.target.value)}
                      disabled={isDeleting}
                      placeholder={field.label}
                      className="w-full px-3 py-2 border border-red-300 dark:border-red-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-red-500 focus:border-red-500 disabled:opacity-50"
                    />
                  </div>

                  <button
                    onClick={onDelete}
                    disabled={!canDelete || isDeleting}
                    className="mt-3 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2 transition-colors"
                  >
                    {isDeleting && (
                      <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle
                          className="opacity-25"
                          cx="12"
                          cy="12"
                          r="10"
                          stroke="currentColor"
                          strokeWidth="4"
                          fill="none"
                        />
                        <path
                          className="opacity-75"
                          fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                      </svg>
                    )}
                    <Trash2 className="w-4 h-4" />
                    Delete Permanently
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button
              onClick={onClose}
              disabled={isDeleting}
              className="btn-secondary"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
