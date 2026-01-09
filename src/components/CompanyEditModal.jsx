import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { X } from 'lucide-react';

export default function CompanyEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading 
}) {
  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      title: '',
      website: '',
      industry: '',
    },
  });

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      reset({
        title: '',
        website: '',
        industry: '',
      });
    }
  }, [isOpen, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit(data);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Add organization</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Organization name */}
            <div>
              <label className="label">Organization name *</label>
              <input
                {...register('title', { required: 'Organization name is required' })}
                className="input"
                placeholder="Acme Inc."
                disabled={isLoading}
                autoFocus
              />
              {errors.title && (
                <p className="text-sm text-red-600 mt-1">{errors.title.message}</p>
              )}
            </div>

            {/* Website */}
            <div>
              <label className="label">Website</label>
              <input
                {...register('website')}
                type="url"
                className="input"
                placeholder="https://example.com"
                disabled={isLoading}
              />
            </div>

            {/* Industry */}
            <div>
              <label className="label">Industry</label>
              <input
                {...register('industry')}
                className="input"
                placeholder="Technology"
                disabled={isLoading}
              />
            </div>
          </div>
          
          <div className="flex justify-end gap-2 p-4 border-t bg-gray-50">
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
              {isLoading ? 'Creating...' : 'Create organization'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
