import { useEffect } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import { X, Plus, Trash2 } from 'lucide-react';

const CONTACT_TYPES = [
  { value: 'email', label: 'Email' },
  { value: 'phone', label: 'Phone' },
  { value: 'mobile', label: 'Mobile' },
  { value: 'website', label: 'Website' },
  { value: 'calendar', label: 'Calendar link' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'twitter', label: 'Twitter' },
  { value: 'bluesky', label: 'Bluesky' },
  { value: 'threads', label: 'Threads' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'slack', label: 'Slack' },
  { value: 'other', label: 'Other' },
];

export default function ContactEditModal({ isOpen, onClose, onSubmit, isLoading, contactInfo = [] }) {
  const { register, control, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      contacts: contactInfo.length > 0 
        ? contactInfo.map(c => ({
            contact_type: c.contact_type || '',
            contact_label: c.contact_label || '',
            contact_value: c.contact_value || '',
          }))
        : [],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'contacts',
  });

  // Reset form when modal opens with current contact info
  useEffect(() => {
    if (isOpen) {
      reset({
        contacts: contactInfo.length > 0 
          ? contactInfo.map(c => ({
              contact_type: c.contact_type || '',
              contact_label: c.contact_label || '',
              contact_value: c.contact_value || '',
            }))
          : [],
      });
    }
  }, [isOpen, contactInfo, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    // Filter out empty rows and submit
    const validContacts = data.contacts.filter(
      c => c.contact_type && c.contact_value
    );
    onSubmit(validContacts);
  };

  const handleAddRow = () => {
    append({ contact_type: '', contact_label: '', contact_value: '' });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Edit contact details</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4">
            {fields.length === 0 ? (
              <p className="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
                No contact details yet. Click "Add contact detail" to add one.
              </p>
            ) : (
              <div className="space-y-3">
                {/* Header row - visible on larger screens */}
                <div className="hidden md:grid md:grid-cols-12 gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 px-1">
                  <div className="col-span-3">Type</div>
                  <div className="col-span-3">Label</div>
                  <div className="col-span-5">Value</div>
                  <div className="col-span-1"></div>
                </div>

                {fields.map((field, index) => (
                  <div
                    key={field.id}
                    className="grid grid-cols-1 md:grid-cols-12 gap-2 p-3 md:p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                  >
                    {/* Type */}
                    <div className="md:col-span-3">
                      <label className="md:hidden text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 block">Type</label>
                      <select
                        {...register(`contacts.${index}.contact_type`, { required: true })}
                        className="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        disabled={isLoading}
                      >
                        <option value="">Select type...</option>
                        {CONTACT_TYPES.map(type => (
                          <option key={type.value} value={type.value}>
                            {type.label}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Label */}
                    <div className="md:col-span-3">
                      <label className="md:hidden text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 block">Label</label>
                      <input
                        {...register(`contacts.${index}.contact_label`)}
                        placeholder="e.g., Work"
                        className="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        disabled={isLoading}
                      />
                    </div>

                    {/* Value */}
                    <div className="md:col-span-5">
                      <label className="md:hidden text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 block">Value</label>
                      <input
                        {...register(`contacts.${index}.contact_value`, { required: true })}
                        placeholder="e.g., john@example.com"
                        className="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        disabled={isLoading}
                      />
                    </div>

                    {/* Delete button */}
                    <div className="md:col-span-1 flex items-center justify-end md:justify-center">
                      <button
                        type="button"
                        onClick={() => remove(index)}
                        className="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors"
                        title="Remove"
                        disabled={isLoading}
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Add button */}
            <button
              type="button"
              onClick={handleAddRow}
              className="mt-4 w-full py-2 px-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:border-gray-400 dark:hover:border-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors flex items-center justify-center gap-2"
              disabled={isLoading}
            >
              <Plus className="w-4 h-4" />
              Add contact detail
            </button>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading}
            >
              {isLoading ? 'Saving...' : 'Save changes'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
