import { useEffect, useState } from 'react';
import { User, Lock, Briefcase, Bell, Sun, Moon, Monitor } from 'lucide-react';
import { useCurrentUser, useChangePassword } from '@/hooks/useCurrentUser';
import { prmApi } from '@/api/client';
import { useTheme } from '@/hooks/useTheme';

export default function Profile() {
  const { data: user, isLoading } = useCurrentUser();
  const changePassword = useChangePassword();
  const { colorScheme, setColorScheme, effectiveColorScheme } = useTheme();

  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const [notificationChannels, setNotificationChannels] = useState([]);
  const [notificationTime, setNotificationTime] = useState('09:00');
  const [mentionNotifications, setMentionNotifications] = useState('digest');
  const [notificationsLoading, setNotificationsLoading] = useState(true);
  const [savingChannels, setSavingChannels] = useState(false);
  const [savingTime, setSavingTime] = useState(false);
  const [savingMentionPref, setSavingMentionPref] = useState(false);

  const isDemoUser = window.rondoConfig?.isDemoUser;
  const colorSchemeOptions = [
    { id: 'light', label: 'Licht', icon: Sun },
    { id: 'dark', label: 'Donker', icon: Moon },
    { id: 'system', label: 'Systeem', icon: Monitor },
  ];

  useEffect(() => {
    const fetchNotificationChannels = async () => {
      try {
        const response = await prmApi.getNotificationChannels();
        setNotificationChannels(response.data.channels || ['email']);
        setNotificationTime(response.data.notification_time || '09:00');
        setMentionNotifications(response.data.mention_notifications || 'digest');
      } catch {
        // Notification channels fetch failed silently
      } finally {
        setNotificationsLoading(false);
      }
    };
    fetchNotificationChannels();
  }, []);

  const toggleChannel = async (channelId) => {
    const newChannels = notificationChannels.includes(channelId)
      ? notificationChannels.filter(c => c !== channelId)
      : [...notificationChannels, channelId];

    setSavingChannels(true);
    try {
      await prmApi.updateNotificationChannels(newChannels);
      setNotificationChannels(newChannels);
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan meldingskanalen niet bijwerken');
    } finally {
      setSavingChannels(false);
    }
  };

  const handleNotificationTimeChange = async (time) => {
    const [hours, minutes] = time.split(':').map(Number);
    const roundedMinutes = Math.round(minutes / 5) * 5;
    const adjustedHours = roundedMinutes === 60 ? (hours + 1) % 24 : hours;
    const adjustedMinutes = roundedMinutes === 60 ? 0 : roundedMinutes;
    const roundedTime = `${String(adjustedHours).padStart(2, '0')}:${String(adjustedMinutes).padStart(2, '0')}`;

    setNotificationTime(roundedTime);
    setSavingTime(true);

    try {
      await prmApi.updateNotificationTime(roundedTime);
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan meldingstijd niet bijwerken');
      const response = await prmApi.getNotificationChannels();
      setNotificationTime(response.data.notification_time || '09:00');
    } finally {
      setSavingTime(false);
    }
  };

  const handleMentionNotificationsChange = async (preference) => {
    setSavingMentionPref(true);
    const previousValue = mentionNotifications;
    setMentionNotifications(preference);

    try {
      await prmApi.updateMentionNotifications(preference);
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan vermeldingsvoorkeur niet bijwerken');
      setMentionNotifications(previousValue);
    } finally {
      setSavingMentionPref(false);
    }
  };

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

      {/* Color scheme card */}
      <div className="card p-6">
        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Kleurenschema</h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Kies hoe de app eruitziet. Selecteer een thema of synchroniseer met je systeeminstellingen.
        </p>

        <div className="flex gap-2">
          {colorSchemeOptions.map((option) => {
            const Icon = option.icon;
            const isSelected = colorScheme === option.id;
            return (
              <button
                key={option.id}
                onClick={() => setColorScheme(option.id)}
                className={`
                  flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium transition-colors
                  ${isSelected
                    ? 'bg-electric-cyan text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}
                `}
              >
                <Icon className="w-5 h-5" />
                <span>{option.label}</span>
              </button>
            );
          })}
        </div>

        <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
          Momenteel <span className="font-medium">{effectiveColorScheme}</span> modus
          {colorScheme === 'system' && ' (op basis van je systeeminstelling)'}
        </p>
      </div>

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

      {/* Notifications card */}
      <div className="card p-6">
        <div className="flex items-center gap-3 mb-4">
          <Bell className="w-5 h-5 text-gray-400" />
          <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Meldingen</h2>
        </div>
        <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
          Kies hoe je dagelijkse herinneringen en vermeldingen wilt ontvangen.
        </p>

        {notificationsLoading ? (
          <div className="animate-pulse">
            <div className="h-10 bg-gray-200 rounded mb-3 dark:bg-gray-700"></div>
            <div className="h-10 bg-gray-200 rounded dark:bg-gray-700"></div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
              <div>
                <p className="font-medium dark:text-gray-200">E-mail</p>
                <p className="text-sm text-gray-500 dark:text-gray-400">Ontvang dagelijkse samenvattingen per e-mail</p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={notificationChannels.includes('email')}
                  onChange={() => toggleChannel('email')}
                  disabled={savingChannels}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-electric-cyan-light rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-electric-cyan dark:bg-gray-600 dark:peer-checked:bg-electric-cyan"></div>
              </label>
            </div>

            <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Meldingstijd (UTC)</label>
              <input
                type="time"
                value={notificationTime}
                onChange={(e) => handleNotificationTimeChange(e.target.value)}
                className="input"
                disabled={savingTime}
                step="300"
              />
            </div>

            <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
              <label className="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-300">Vermeldingsmeldingen</label>
              <select
                value={mentionNotifications}
                onChange={(e) => handleMentionNotificationsChange(e.target.value)}
                className="input"
                disabled={savingMentionPref}
              >
                <option value="digest">Opnemen in dagelijkse samenvatting (standaard)</option>
                <option value="immediate">Direct verzenden</option>
                <option value="never">Niet melden</option>
              </select>
            </div>
          </div>
        )}
      </div>

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
