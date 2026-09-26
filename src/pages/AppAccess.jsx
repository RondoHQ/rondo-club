import { useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { EyeOff, Loader2, QrCode } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

const statusKey = ['app-access', 'laposta'];

export default function AppAccess() {
  const { data: user } = useCurrentUser();
  const queryClient = useQueryClient();
  const { data: status, isPending, isError, refetch } = useQuery({
    queryKey: statusKey,
    queryFn: async () => (await prmApi.getAppAccess()).data,
    gcTime: 0,
    staleTime: 0,
    retry: false,
  });
  const [qr, setQr] = useState('');
  const [revealing, setRevealing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const request = useRef(null);

  function hideQr() {
    request.current?.abort();
    setQr('');
    setRevealing(false);
  }

  useEffect(() => {
    const onHide = () => {
      if (document.visibilityState === 'hidden') {
        request.current?.abort();
        setQr('');
        setRevealing(false);
      }
    };
    document.addEventListener('visibilitychange', onHide);
    return () => {
      request.current?.abort();
      document.removeEventListener('visibilitychange', onHide);
    };
  }, []);

  useEffect(() => {
    if (!qr) return;
    const timeout = window.setTimeout(() => setQr(''), 120000);
    return () => window.clearTimeout(timeout);
  }, [qr]);

  async function reveal() {
    hideQr();
    const controller = new AbortController();
    request.current = controller;
    setRevealing(true);
    setError('');
    try {
      const [response, { default: QRCode }] = await Promise.all([
        prmApi.revealAppAccess(controller.signal),
        import('qrcode'),
      ]);
      const image = await QRCode.toDataURL(response.data.uri, {
        width: 512, margin: 4, errorCorrectionLevel: 'M', color: { dark: '#000000', light: '#ffffff' },
      });
      // A hidden tab or unmounted page must not reveal a late response.
      if (!controller.signal.aborted) setQr(image);
    } catch (err) {
      if (!controller.signal.aborted) setError(err.response?.data?.message || 'De QR-code kon niet worden geladen. Probeer het opnieuw.');
    } finally {
      if (!controller.signal.aborted) setRevealing(false);
    }
  }

  async function save(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const uri = form.elements.namedItem('authenticator_uri').value.trim();
    if (!uri) return;
    hideQr();
    setSaving(true);
    setSaved(false);
    setError('');
    try {
      const response = await prmApi.saveAppAccess(uri);
      form.reset();
      queryClient.setQueryData(statusKey, response.data);
      setSaved(true);
    } catch (err) {
      setError(err.response?.data?.message || 'Opslaan is niet gelukt. Probeer het opnieuw.');
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    if (!window.confirm('De QR-code uit Rondo verwijderen? Eerder ingestelde authenticator-apps blijven werken totdat je de geheime sleutel in Laposta vervangt.')) return;
    hideQr();
    setSaving(true);
    setSaved(false);
    setError('');
    try {
      const response = await prmApi.removeAppAccess();
      queryClient.setQueryData(statusKey, response.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Verwijderen is niet gelukt. Probeer het opnieuw.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="max-w-2xl space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">App access</h1>
        <p className="mt-2 text-gray-600 dark:text-gray-400">Authenticator instellen voor het gedeelde Laposta-account.</p>
      </div>

      {error && <p role="alert" className="rounded-lg bg-red-50 p-4 text-red-700 dark:bg-red-900/30 dark:text-red-300">{error}</p>}

      {isPending ? <ContentLoadingSpinner /> : isError ? (
        <div className="card p-6 space-y-4">
          <p role="alert">De instellingen konden niet worden geladen.</p>
          <button type="button" onClick={() => refetch()} className="btn-secondary">Opnieuw laden</button>
        </div>
      ) : (
        <>
          <section className="card p-6" aria-labelledby="laposta-title">
            <h2 id="laposta-title" className="text-xl font-semibold text-gray-900 dark:text-gray-100">Laposta</h2>
            {status?.configured ? (
              <>
                <p className="mt-1 break-words text-gray-600 dark:text-gray-400">{status.account}</p>
                <p className="mt-4 text-gray-700 dark:text-gray-300">Open je authenticator-app, voeg een account toe en scan de QR-code.</p>
                <div className="mt-5">
                  {qr ? (
                    <>
                      <img src={qr} alt="QR-code om het gedeelde Laposta-account aan je authenticator toe te voegen" width="320" height="320" className="mb-4 h-auto w-80 max-w-full bg-white" />
                      <button type="button" onClick={hideQr} className="btn-secondary"><EyeOff className="mr-2 h-4 w-4" aria-hidden="true" />QR-code verbergen</button>
                      <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">De QR-code verdwijnt na twee minuten of zodra je deze tab verlaat.</p>
                    </>
                  ) : (
                    <button type="button" onClick={reveal} disabled={revealing || saving} className="btn-primary">
                      {revealing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" /> : <QrCode className="mr-2 h-4 w-4" aria-hidden="true" />}
                      {revealing ? 'QR-code laden…' : 'QR-code tonen'}
                    </button>
                  )}
                </div>
                <p className="mt-5 text-sm text-gray-600 dark:text-gray-400">Deel deze QR-code alleen met het bestuur. Een gescande code blijft toegang geven, ook als iemand geen bestuurslid meer is. Vervang de geheime sleutel in Laposta om die toegang in te trekken.</p>
              </>
            ) : (
              <p className="mt-3 text-gray-600 dark:text-gray-400">{user?.is_admin ? 'Voeg hieronder de authenticator-URL toe om de QR-code beschikbaar te maken voor het bestuur.' : 'De QR-code is nog niet ingesteld. Vraag een beheerder om de Laposta-toegang in te stellen.'}</p>
            )}
          </section>

          {user?.is_admin && (
            <section className="card p-6" aria-labelledby="settings-title">
              <h2 id="settings-title" className="text-lg font-semibold text-gray-900 dark:text-gray-100">Toegang beheren</h2>
              <form onSubmit={save} className="mt-4 space-y-4" autoComplete="off">
                <div>
                  <label htmlFor="authenticator-uri" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{status?.configured ? 'Nieuwe authenticator-URL' : 'Authenticator-URL'}</label>
                  <input id="authenticator-uri" name="authenticator_uri" type="password" required maxLength={1024} autoComplete="off" spellCheck={false} autoCapitalize="none" aria-describedby="authenticator-help" className="input mt-1 w-full" disabled={saving} onChange={() => setSaved(false)} />
                  <p id="authenticator-help" className="mt-2 text-sm text-gray-600 dark:text-gray-400">Plak de volledige otpauth://totp/ URL uit 1Password. Deze wordt versleuteld opgeslagen en niet opnieuw in dit veld getoond.</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  <button type="submit" disabled={saving} className="btn-primary">{saving ? 'Bezig…' : 'Opslaan'}</button>
                  {status?.configured && <button type="button" onClick={remove} disabled={saving} className="btn-tertiary">Uit Rondo verwijderen</button>}
                  {saved && <span role="status" className="text-sm text-gray-600 dark:text-gray-400">Opgeslagen</span>}
                </div>
              </form>
            </section>
          )}
        </>
      )}
    </div>
  );
}
