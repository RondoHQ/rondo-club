import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { X } from 'lucide-react';

export default function AddressEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading, 
  address = null 
}) {
  const isEditing = !!address;

  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      address_label: '',
      street: '',
      postal_code: '',
      city: '',
      state: '',
      country: '',
    },
  });

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      if (address) {
        reset({
          address_label: address.address_label || '',
          street: address.street || '',
          postal_code: address.postal_code || '',
          city: address.city || '',
          state: address.state || '',
          country: address.country || '',
        });
      } else {
        reset({
          address_label: '',
          street: '',
          postal_code: '',
          city: '',
          state: '',
          country: '',
        });
      }
    }
  }, [isOpen, address, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      address_label: data.address_label || '',
      street: data.street || '',
      postal_code: data.postal_code || '',
      city: data.city || '',
      state: data.state || '',
      country: data.country || '',
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Edit address' : 'Add address'}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Label */}
            <div>
              <label className="label">Label</label>
              <input
                {...register('address_label')}
                className="input"
                placeholder="e.g., Home, Work"
                disabled={isLoading}
              />
            </div>

            {/* Street */}
            <div>
              <label className="label">Street</label>
              <input
                {...register('street')}
                className="input"
                placeholder="e.g., 123 Main Street"
                disabled={isLoading}
              />
            </div>

            {/* City and Postal Code row */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Postal code</label>
                <input
                  {...register('postal_code')}
                  className="input"
                  placeholder="e.g., 12345"
                  disabled={isLoading}
                />
              </div>
              
              <div>
                <label className="label">City</label>
                <input
                  {...register('city')}
                  className="input"
                  placeholder="e.g., Amsterdam"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* State and Country row */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">State/Province</label>
                <input
                  {...register('state')}
                  className="input"
                  placeholder="e.g., North Holland"
                  disabled={isLoading}
                />
              </div>
              
              <div>
                <label className="label">Country</label>
                <input
                  {...register('country')}
                  className="input"
                  placeholder="e.g., Netherlands"
                  disabled={isLoading}
                />
              </div>
            </div>
          </div>
          
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
              {isLoading ? 'Saving...' : (isEditing ? 'Save changes' : 'Add address')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
