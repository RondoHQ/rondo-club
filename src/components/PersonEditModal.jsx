import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { X } from 'lucide-react';

export default function PersonEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading 
}) {
  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      first_name: '',
      last_name: '',
      nickname: '',
      gender: '',
      email: '',
      phone: '',
      phone_type: 'mobile',
    },
  });

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      reset({
        first_name: '',
        last_name: '',
        nickname: '',
        gender: '',
        email: '',
        phone: '',
        phone_type: 'mobile',
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
          <h2 className="text-lg font-semibold">Add person</h2>
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
            {/* Name fields */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">First name *</label>
                <input
                  {...register('first_name', { required: 'First name is required' })}
                  className="input"
                  placeholder="John"
                  disabled={isLoading}
                  autoFocus
                />
                {errors.first_name && (
                  <p className="text-sm text-red-600 mt-1">{errors.first_name.message}</p>
                )}
              </div>
              
              <div>
                <label className="label">Last name</label>
                <input
                  {...register('last_name')}
                  className="input"
                  placeholder="Doe"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Nickname */}
            <div>
              <label className="label">Nickname</label>
              <input
                {...register('nickname')}
                className="input"
                placeholder="Johnny"
                disabled={isLoading}
              />
            </div>

            {/* Gender */}
            <div>
              <label className="label">Gender</label>
              <select
                {...register('gender')}
                className="input"
                disabled={isLoading}
              >
                <option value="">Select...</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>

            {/* Email */}
            <div>
              <label className="label">Email</label>
              <input
                {...register('email')}
                type="email"
                className="input"
                placeholder="john@example.com"
                disabled={isLoading}
              />
            </div>

            {/* Phone */}
            <div>
              <label className="label">Phone</label>
              <div className="flex gap-2">
                <select
                  {...register('phone_type')}
                  className="input w-24"
                  disabled={isLoading}
                >
                  <option value="mobile">Mobile</option>
                  <option value="phone">Phone</option>
                </select>
                <input
                  {...register('phone')}
                  type="tel"
                  className="input flex-1"
                  placeholder="+1 234 567 890"
                  disabled={isLoading}
                />
              </div>
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
              {isLoading ? 'Creating...' : 'Create person'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
