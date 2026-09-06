import { useEffect, useRef, useState } from 'react';
import { Capacitor, registerPlugin } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { openWallet, walletProvider } from './wallet-model.mjs';

const appleWallet = registerPlugin('RondoWallet');

export default function WalletAction({ personId, role, wallets, requestWallet, onExpired }) {
  const provider = walletProvider(Capacitor.getPlatform());
  const label = provider === 'apple' ? 'Apple Wallet' : 'Google Wallet';
  const active = useRef(false);
  const locked = useRef(false);
  const [busy, setBusy] = useState(false);
  const [supported, setSupported] = useState(provider === 'google');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  useEffect(() => {
    active.current = true;
    if (provider === 'apple') appleWallet.available().then(({ available }) => { if (active.current) setSupported(available); }).catch(() => { if (active.current) setSupported(false); });
    return () => { active.current = false; };
  }, [provider]);

  async function add() {
    if (locked.current) return;
    locked.current = true;
    setBusy(true); setError(''); setMessage('');
    try {
      const opened = await openWallet({
        provider, active: () => active.current,
        request: () => requestWallet(personId, role, provider),
        apple: (content) => appleWallet.add({ content }),
        google: (url) => Browser.open({ url }),
      });
      if (opened && provider === 'google') setMessage('Rond het toevoegen af in Google Wallet.');
    } catch (failure) {
      if (active.current) {
        if (failure.status === 401) onExpired();
        else setError(failure.message || 'Wallet kon niet worden geopend. Probeer opnieuw.');
      }
    } finally {
      locked.current = false;
      if (active.current) setBusy(false);
    }
  }
  if (!provider) return <p className="caption">Open de iPhone- of Android-app om je pas aan Wallet toe te voegen.</p>;
  if (!wallets[provider]?.available) return <p className="caption">{`${label} is nog niet beschikbaar bij je club.`}</p>;
  if (!supported) return <p className="caption">{`${label} is niet beschikbaar op dit toestel. Je kunt de QR-code in de app gebruiken.`}</p>;
  return <div><button disabled={busy} onClick={add}>{busy ? 'Wallet openen…' : `Toevoegen aan ${label}`}</button>{error && <p role="alert" className="error">{error}</p>}{message && <p role="status">{message}</p>}</div>;
}
