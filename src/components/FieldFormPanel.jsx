import { useState, useEffect, useRef } from 'react';
import { X } from 'lucide-react';

// Field type options
const FIELD_TYPES = [
  { value: 'text', label: 'Text' },
  { value: 'textarea', label: 'Textarea' },
  { value: 'number', label: 'Number' },
  { value: 'email', label: 'Email' },
  { value: 'url', label: 'URL' },
  { value: 'date', label: 'Date' },
  { value: 'select', label: 'Select' },
  { value: 'checkbox', label: 'Checkbox' },
  { value: 'true_false', label: 'True/False' },
  { value: 'image', label: 'Image' },
  { value: 'file', label: 'File' },
  { value: 'link', label: 'Link' },
  { value: 'color', label: 'Color' },
  { value: 'relationship', label: 'Relationship' },
];

export default function FieldFormPanel({
  isOpen,
  onClose,
  onSubmit,
  field = null,
  postType,
  isSubmitting = false,
}) {
  const isEditing = !!field;
  const labelInputRef = useRef(null);

  const [formData, setFormData] = useState({
    label: '',
    type: 'text',
    instructions: '',
  });
  const [errors, setErrors] = useState({});

  // Reset form when opening/closing or when field changes
  useEffect(() => {
    if (isOpen) {
      if (field) {
        setFormData({
          label: field.label || '',
          type: field.type || 'text',
          instructions: field.instructions || '',
        });
      } else {
        setFormData({
          label: '',
          type: 'text',
          instructions: '',
        });
      }
      setErrors({});
      // Focus label input after panel opens
      setTimeout(() => labelInputRef.current?.focus(), 100);
    }
  }, [isOpen, field]);

  // Prevent body scroll when panel is open
  useEffect(() => {
    if (isOpen) {
      document.body.classList.add('overflow-hidden');
    } else {
      document.body.classList.remove('overflow-hidden');
    }
    return () => {
      document.body.classList.remove('overflow-hidden');
    };
  }, [isOpen]);

  // Handle Escape key
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: null }));
    }
  };

  const validate = () => {
    const newErrors = {};
    if (!formData.label.trim()) {
      newErrors.label = 'Label is required';
    }
    if (!formData.type) {
      newErrors.type = 'Type is required';
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;

    await onSubmit({
      label: formData.label.trim(),
      type: formData.type,
      instructions: formData.instructions.trim(),
    });
  };

  const postTypeLabel = postType === 'person' ? 'People' : 'Organizations';

  return (
    <>
      {/* Backdrop */}
      <div
        className={`fixed inset-0 bg-black/50 z-40 transition-opacity duration-300 ${
          isOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`}
        onClick={onClose}
      />

      {/* Panel */}
      <div
        className={`fixed inset-y-0 right-0 w-full max-w-md bg-white dark:bg-gray-800 shadow-xl transform transition-transform duration-300 ease-in-out z-50 ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <div className="h-full flex flex-col">
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">
                {isEditing ? 'Edit Field' : 'Add Field'}
              </h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                {postTypeLabel} custom field
              </p>
            </div>
            <button
              onClick={onClose}
              className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="flex-1 flex flex-col">
            <div className="flex-1 px-6 py-4 space-y-4 overflow-y-auto">
              {/* Label field */}
              <div>
                <label
                  htmlFor="field-label"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                  Label <span className="text-red-500">*</span>
                </label>
                <input
                  ref={labelInputRef}
                  id="field-label"
                  name="label"
                  type="text"
                  value={formData.label}
                  onChange={handleChange}
                  placeholder="e.g., LinkedIn Profile"
                  className={`w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500 ${
                    errors.label
                      ? 'border-red-500 dark:border-red-400'
                      : 'border-gray-300 dark:border-gray-600'
                  }`}
                />
                {errors.label && (
                  <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.label}</p>
                )}
              </div>

              {/* Type field */}
              <div>
                <label
                  htmlFor="field-type"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                  Type <span className="text-red-500">*</span>
                </label>
                <select
                  id="field-type"
                  name="type"
                  value={formData.type}
                  onChange={handleChange}
                  disabled={isEditing}
                  className={`w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500 ${
                    errors.type
                      ? 'border-red-500 dark:border-red-400'
                      : 'border-gray-300 dark:border-gray-600'
                  } ${isEditing ? 'opacity-60 cursor-not-allowed' : ''}`}
                >
                  {FIELD_TYPES.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
                {isEditing && (
                  <p className="mt-1 text-sm text-amber-600 dark:text-amber-400">
                    Field type cannot be changed after creation
                  </p>
                )}
                {errors.type && (
                  <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.type}</p>
                )}
              </div>

              {/* Description/Instructions field */}
              <div>
                <label
                  htmlFor="field-instructions"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                  Description
                </label>
                <textarea
                  id="field-instructions"
                  name="instructions"
                  value={formData.instructions}
                  onChange={handleChange}
                  rows={3}
                  placeholder="Help text shown to users when filling in this field"
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
                />
              </div>
            </div>

            {/* Footer */}
            <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
              <button
                type="button"
                onClick={onClose}
                className="btn-secondary"
                disabled={isSubmitting}
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="btn-primary inline-flex items-center gap-2"
              >
                {isSubmitting && (
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
                {isSubmitting ? 'Saving...' : 'Save'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
