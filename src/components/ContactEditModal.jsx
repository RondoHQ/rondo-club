import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { X } from 'lucide-react';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

export default function ContactEditModal({ isOpen, onClose, onSubmit, isLoading, email1 = '', email2 = '', mobile1 = '', mobile2 = '', telephone1 = '', telephone2 = '' }) {
  const isOnline = useOnlineStatus();
  const { register, handleSubmit, reset } = useForm({
    defaultValues: {
      email_1: email1,
      email_2: email2,
      mobile_1: mobile1,
      mobile_2: mobile2,
      telephone_1: telephone1,
      telephone_2: telephone2,
    },
  });

  // Reset form when modal opens with current values
  useEffect(() => {
    if (isOpen) {
      reset({
        email_1: email1,
        email_2: email2,
        mobile_1: mobile1,
        mobile_2: mobile2,
        telephone_1: telephone1,
        telephone_2: telephone2,
      });
    }
  }, [isOpen, email1, email2, mobile1, mobile2, telephone1, telephone2, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      email_1: data.email_1?.trim() || '',
      email_2: data.email_2?.trim() || '',
      mobile_1: data.mobile_1?.trim() || '',
      mobile_2: data.mobile_2?.trim() || '',
      telephone_1: data.telephone_1?.trim() || '',
      telephone_2: data.telephone_2?.trim() || '',
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Contactgegevens bewerken</h2>
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
            {/* Email fields */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="label">Email</label>
                <input
                  {...register('email_1')}
                  type="email"
                  placeholder="naam@voorbeeld.nl"
                  className="input"
                  disabled={isLoading}
                />
              </div>
              <div>
                <label className="label">Email (2e)</label>
                <input
                  {...register('email_2')}
                  type="email"
                  placeholder="naam@voorbeeld.nl"
                  className="input"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Mobile fields */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="label">Mobiel</label>
                <input
                  {...register('mobile_1')}
                  type="tel"
                  placeholder="+31 6 12345678"
                  className="input"
                  disabled={isLoading}
                />
              </div>
              <div>
                <label className="label">Mobiel (2e)</label>
                <input
                  {...register('mobile_2')}
                  type="tel"
                  placeholder="+31 6 12345678"
                  className="input"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Telephone fields */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="label">Telefoon</label>
                <input
                  {...register('telephone_1')}
                  type="tel"
                  placeholder="+31 20 1234567"
                  className="input"
                  disabled={isLoading}
                />
              </div>
              <div>
                <label className="label">Telefoon (2e)</label>
                <input
                  {...register('telephone_2')}
                  type="tel"
                  placeholder="+31 20 1234567"
                  className="input"
                  disabled={isLoading}
                />
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Annuleren
            </button>
            <button
              type="submit"
              className={`btn-primary ${!isOnline ? 'opacity-50 cursor-not-allowed' : ''}`}
              disabled={!isOnline || isLoading}
            >
              {isLoading ? 'Opslaan...' : 'Wijzigingen opslaan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
