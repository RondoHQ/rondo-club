import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, ArrowRightLeft, Check, Loader2, Mail, UserPlus } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';

export default function AccountSwitcher() {
  const queryClient = useQueryClient();
  const { data: user } = useCurrentUser();
  const { data, isPending, error: loadError, refetch } = useQuery({
    queryKey: ['account-switcher'],
    queryFn: async () => (await prmApi.getAccountSwitcher()).data,
    staleTime: 0,
  });
  const [selectedEmail, setSelectedEmail] = useState('');
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState('');
  const [sent, setSent] = useState(false);
  const email = data?.emails.includes(selectedEmail) ? selectedEmail : data?.email;

  async function verify() {
    setBusy('verify');
    setError('');
    try {
      await prmApi.verifyAccountSwitcher(email);
      setSent(true);
    } catch (err) {
      setError(err.response?.data?.message || 'De e-mail kon niet worden verstuurd. Probeer het opnieuw.');
    } finally {
      setBusy(null);
    }
  }

  async function select(account) {
    setBusy(account.person_id);
    setError('');
    try {
      // An older worker may still cache private API responses while this release waits.
      // Detach it before changing identity; the next app bootstrap registers the current worker.
      if (account.active && 'serviceWorker' in navigator) {
        const registration = await navigator.serviceWorker.getRegistration();
        if (registration) await registration.unregister();
      }
      const result = account.active
        ? await prmApi.switchAccount(account.person_id)
        : await prmApi.activateSwitchAccount(account.person_id);
      // Discard all cached data and bootstrap fresh permissions, nonce and identity.
      await queryClient.cancelQueries();
      queryClient.clear();
      // Remove legacy service-worker responses before the new account bootstraps.
      if ('caches' in window) await window.caches.delete('api-cache').catch(() => {});
      try {
        window.localStorage.setItem('rondo-account-changed', String(Date.now()));
      } catch { /* Storage may be disabled; nonce recovery also guards old tabs. */ }
      window.location.assign(result.data.redirect_url);
    } catch (err) {
      setError(err.response?.data?.message || 'Wisselen is niet gelukt. Vernieuw de lijst en probeer het opnieuw.');
      await refetch();
      setBusy(null);
    }
  }

  return (
    <div className="account-switcher max-w-xl mx-auto space-y-6">
      <Link to="/profile" className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 hover:underline">
        <ArrowLeft className="w-4 h-4" aria-hidden="true" /> Terug naar profiel
      </Link>
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Wissel van account</h1>
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
          Je bent ingelogd als <strong>{user?.linked_person_name || user?.name || '…'}</strong>.
        </p>
      </div>

      {isPending ? (
        <p role="status" className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
          <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" /> Accounts laden…
        </p>
      ) : loadError ? (
        <div role="alert" className="space-y-3">
          <p>De accounts konden niet worden geladen.</p>
          <button type="button" onClick={() => refetch()} className="btn-secondary">Opnieuw proberen</button>
        </div>
      ) : !data?.available ? (
        <p className="text-sm text-gray-600 dark:text-gray-300">Aan jouw e-mailadres zijn geen andere beschikbare personen gekoppeld.</p>
      ) : !data.verified ? (
        <div className="space-y-4">
          <p className="text-sm text-gray-700 dark:text-gray-200">
            Bevestig eerst dat je toegang hebt tot dit e-mailadres. Daarna kun je in deze sessie wisselen tussen de bijbehorende accounts.
          </p>
          {data.emails.length > 1 ? (
            <div>
              <label htmlFor="switch-email" className="block text-sm font-medium mb-1">E-mailadres</label>
              <select id="switch-email" value={email} disabled={busy !== null || sent} onChange={(event) => setSelectedEmail(event.target.value)} className="input w-full">
                {data.emails.map((address) => <option key={address} value={address}>{address}</option>)}
              </select>
            </div>
          ) : <p className="font-medium break-all">{email}</p>}
          {sent ? (
            <div role="status" className="space-y-3 text-sm">
              <p>Open de inloglink in de e-mail aan <strong className="break-all">{email}</strong>. Je komt daarna terug bij de accountkeuze.</p>
              <p className="text-gray-600 dark:text-gray-300">Geen e-mail ontvangen? Kijk ook in je spammap.</p>
              <button type="button" disabled={busy !== null} onClick={verify} className="btn-secondary">Opnieuw versturen</button>
            </div>
          ) : (
            <button type="button" onClick={verify} disabled={busy !== null} className="btn-primary inline-flex items-center gap-2">
              {busy === 'verify' ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Mail className="w-4 h-4" aria-hidden="true" />}
              {busy === 'verify' ? 'Versturen…' : 'Bevestig per e-mail'}
            </button>
          )}
        </div>
      ) : (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300 break-words">Kies een account bij <strong>{data.email}</strong>. Na het wisselen zie je de gegevens en mogelijkheden van dat account.</p>
          <ul className="divide-y divide-gray-200 dark:divide-gray-700">
            {data.accounts.map((account) => (
              <li key={account.person_id} className="py-4 flex flex-wrap items-center justify-between gap-3">
                <span className="min-w-0 break-words font-medium text-gray-900 dark:text-gray-100">{account.name}</span>
                {account.current ? (
                  <span className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><Check className="w-4 h-4" aria-hidden="true" /> Huidig account</span>
                ) : (
                  <button type="button" disabled={busy !== null} onClick={() => select(account)} className="btn-secondary inline-flex items-center gap-2" aria-label={`${account.active ? 'Wissel naar' : 'Activeer account voor'} ${account.name}`}>
                    {busy === account.person_id ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" /> : account.active ? <ArrowRightLeft className="w-4 h-4" aria-hidden="true" /> : <UserPlus className="w-4 h-4" aria-hidden="true" />}
                    {busy === account.person_id ? 'Openen…' : account.active ? 'Wisselen' : 'Account activeren'}
                  </button>
                )}
              </li>
            ))}
          </ul>
        </div>
      )}
      {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}
    </div>
  );
}
