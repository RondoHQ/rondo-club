import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { X, Mail } from 'lucide-react';
import { useCreateWorkspaceInvite } from '@/hooks/useWorkspaces';

export default function WorkspaceInviteModal({ isOpen, onClose, workspaceId, workspaceName }) {
  const inviteMutation = useCreateWorkspaceInvite();

  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      email: '',
      role: 'member',
    },
  });

  useEffect(() => {
    if (isOpen) {
      reset({ email: '', role: 'member' });
    }
  }, [isOpen, reset]);

  const onSubmit = async (data) => {
    await inviteMutation.mutateAsync({
      workspaceId,
      email: data.email,
      role: data.role,
    });
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="fixed inset-0 bg-black bg-opacity-50" onClick={onClose} />

      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-md bg-white rounded-lg shadow-xl">
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Uitnodigen voor werkruimte</h2>
              <p className="text-sm text-gray-500">{workspaceName}</p>
            </div>
            <button onClick={onClose} className="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100">
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit(onSubmit)}>
            <div className="px-6 py-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  E-mailadres *
                </label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="email"
                    {...register('email', {
                      required: 'E-mailadres is verplicht',
                      pattern: {
                        value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                        message: 'Ongeldig e-mailadres',
                      },
                    })}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
                    placeholder="collega@voorbeeld.nl"
                  />
                </div>
                {errors.email && (
                  <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Rol
                </label>
                <select
                  {...register('role')}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
                >
                  <option value="admin">Beheerder - Kan leden en instellingen beheren</option>
                  <option value="member">Lid - Kan contacten bekijken en bewerken</option>
                  <option value="viewer">Kijker - Kan alleen contacten bekijken</option>
                </select>
              </div>

              {inviteMutation.isError && (
                <p className="text-sm text-red-600">
                  {inviteMutation.error?.response?.data?.message || 'Uitnodiging verzenden mislukt'}
                </p>
              )}
            </div>

            {/* Footer */}
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
              <button type="button" onClick={onClose} className="btn-secondary">
                Annuleren
              </button>
              <button
                type="submit"
                disabled={inviteMutation.isPending}
                className="btn-primary"
              >
                {inviteMutation.isPending ? 'Verzenden...' : 'Uitnodiging versturen'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
