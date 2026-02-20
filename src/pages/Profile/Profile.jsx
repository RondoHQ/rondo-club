import { useState } from 'react';
import { User, Lock, Briefcase } from 'lucide-react';
import { useCurrentUser, useChangePassword } from '@/hooks/useCurrentUser';

export default function Profile() {
  const { data: user, isLoading } = useCurrentUser();
  const changePassword = useChangePassword();

  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  const isDemoUser = window.rondoConfig?.isDemoUser;

  const handlePasswordSubmit = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');

    // Client-side validation
    if (newPassword.length < 8) {
      setErrorMessage('Nieuw wachtwoord moet minimaal 8 tekens bevatten.');
      return;
    }
    if (newPassword !== confirmPassword) {
      setErrorMessage('Nieuw wachtwoord en bevestiging komen niet overeen.');
      return;
    }

    try {
      await changePassword.mutateAsync({ currentPassword, newPassword });
      setSuccessMessage('Wachtwoord succesvol gewijzigd. Je wordt doorgestuurd...');
      // Session is dead — hard redirect to login
      window.location.href = window.rondoConfig?.loginUrl || '/wp-login.php';
    } catch (err) {
      setErrorMessage(
        err.response?.data?.message || 'Er is een fout opgetreden. Probeer het opnieuw.'
      );
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan"></div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Profiel</h1>

      {/* Account card */}
      <div className="card p-6">
        <div className="flex items-center gap-3 mb-4">
          <User className="w-5 h-5 text-gray-400" />
          <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Account</h2>
        </div>
        <dl className="space-y-3">
          <div>
            <dt className="text-sm text-gray-500 dark:text-gray-400">Naam</dt>
            <dd className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-0.5">
              {user?.name || '—'}
            </dd>
          </div>
          <div>
            <dt className="text-sm text-gray-500 dark:text-gray-400">E-mailadres</dt>
            <dd className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-0.5">
              {user?.email || '—'}
            </dd>
          </div>
        </dl>
      </div>

      {/* Sportlink koppeling card — only shown when linked */}
      {user?.linked_person_name && (
        <div className="card p-6">
          <div className="flex items-center gap-3 mb-4">
            <Briefcase className="w-5 h-5 text-gray-400" />
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">
              Sportlink koppeling
            </h2>
          </div>
          <dl className="space-y-3">
            <div>
              <dt className="text-sm text-gray-500 dark:text-gray-400">Gekoppeld persoon</dt>
              <dd className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-0.5">
                {user.linked_person_name}
              </dd>
            </div>
            <div>
              <dt className="text-sm text-gray-500 dark:text-gray-400 mb-1.5">Actieve functies</dt>
              <dd>
                {user.active_functies && user.active_functies.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {user.active_functies.map((functie) => (
                      <span
                        key={functie}
                        className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-cyan-100 text-bright-cobalt dark:bg-obsidian dark:text-electric-cyan"
                      >
                        {functie}
                      </span>
                    ))}
                  </div>
                ) : (
                  <span className="text-sm text-gray-500 dark:text-gray-400">
                    Geen actieve functies
                  </span>
                )}
              </dd>
            </div>
          </dl>
        </div>
      )}

      {/* Password change card — hidden for demo users */}
      {!isDemoUser && (
        <div className="card p-6">
          <div className="flex items-center gap-3 mb-4">
            <Lock className="w-5 h-5 text-gray-400" />
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">
              Wachtwoord wijzigen
            </h2>
          </div>

          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <div>
              <label
                htmlFor="current-password"
                className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Huidig wachtwoord
              </label>
              <input
                id="current-password"
                type="password"
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                required
                autoComplete="current-password"
                className="input w-full"
              />
              {errorMessage && (
                <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errorMessage}</p>
              )}
            </div>

            <div>
              <label
                htmlFor="new-password"
                className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Nieuw wachtwoord
              </label>
              <input
                id="new-password"
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                required
                autoComplete="new-password"
                className="input w-full"
              />
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimaal 8 tekens</p>
            </div>

            <div>
              <label
                htmlFor="confirm-password"
                className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Bevestig nieuw wachtwoord
              </label>
              <input
                id="confirm-password"
                type="password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                required
                autoComplete="new-password"
                className="input w-full"
              />
            </div>

            {successMessage && (
              <p className="text-sm text-green-600 dark:text-green-400">{successMessage}</p>
            )}

            <button
              type="submit"
              disabled={changePassword.isPending}
              className="btn btn-primary flex items-center gap-2"
            >
              {changePassword.isPending && (
                <span className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
              )}
              Wachtwoord wijzigen
            </button>
          </form>
        </div>
      )}
    </div>
  );
}
