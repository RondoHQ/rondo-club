import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { PROFILE_SCOPE } from './auth.mjs';
import { PHONE_LABELS, ADDRESS_LABELS, phoneValues, homeAddress, addressLabel } from './profile-model.mjs';
import { SPORTLINK_EMAIL_SYNC_DELAY_MESSAGE } from '../../src/constants/contact';

// Drafts live only on this screen; no personal fields or queued writes go into device storage.
export default function ProfileEditor({ data, session, logout, onExpired, changeProfile, refresh }) {
  const [editing, setEditing] = useState(null);
  const [values, setValues] = useState({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [needsCheck, setNeedsCheck] = useState(false);
  const alive = useRef(true);
  const running = useRef(false);
  useEffect(() => { alive.current = true; return () => { alive.current = false; }; }, []);
  const fields = data.person?.fields || {};
  const allowed = session.scope === PROFILE_SCOPE;

  function open(action, next) {
    setEditing(action); setValues(next); setError(''); setMessage('');
    window.scrollTo(0, 0);
  }

  async function verify() {
    if (running.current) return;
    running.current = true; setBusy(true);
    try {
      await refresh();
      if (alive.current) { setNeedsCheck(false); setEditing(null); setValues({}); setError(''); setMessage('Je gegevens zijn opnieuw opgehaald.'); }
    } catch { if (alive.current) setError('Je gegevens konden nog niet worden opgehaald. Controleer je verbinding.'); }
    finally { running.current = false; if (alive.current) setBusy(false); }
  }

  async function submit(event) {
    event.preventDefault();
    if (running.current || !editing || needsCheck) return;
    running.current = true; setBusy(true); setError(''); setMessage('');
    let saved = false;
    try {
      const result = await changeProfile(editing, values);
      saved = editing === 'email_request' ? result.pending === true : result.success === true;
      if (!saved) throw new Error('Geen geldige bevestiging ontvangen.');
      await refresh();
      if (alive.current) {
        setMessage(editing === 'email_request' ? 'Open de verificatielink in de e-mail aan je nieuwe adres. Je huidige adres blijft actief totdat je bevestigt.' : editing === 'email_cancel' ? 'De verificatieaanvraag is geannuleerd.' : 'Je gegevens zijn opgeslagen.');
        setEditing(null); setValues({}); window.scrollTo(0, 0);
      }
    } catch (failure) {
      if (!alive.current) return;
      window.scrollTo(0, 0);
      if (failure.status === 401) { onExpired(); return; }
      if (saved || !failure.status || failure.status >= 500) {
        setNeedsCheck(true);
        setError(saved ? 'De wijziging is verwerkt, maar je gegevens konden nog niet worden vernieuwd.' : 'Geen bevestiging ontvangen. Haal eerst je gegevens op voordat je opnieuw probeert.');
      } else {
        setError(failure.message);
        if (failure.status === 403) { setEditing(null); await refresh().catch(() => {}); }
      }
    } finally { running.current = false; if (alive.current) setBusy(false); }
  }

  const update = (field) => (event) => setValues((current) => ({ ...current, [field]: event.target.value }));
  const title = editing === 'phones' ? 'Telefoonnummers' : editing === 'address' ? 'Woonadres' : editing === 'email_request' ? values.slot === 'primary' ? 'Primair e-mailadres' : 'Tweede e-mailadres' : editing === 'email_remove' ? 'E-mailadres verwijderen' : 'Aanvraag annuleren';
  return <section className="profile-editor" aria-busy={busy}>
    <Link className="back-link" to="/gegevens">‹ Mijn gegevens</Link>
    <h1>{editing ? title : 'Gegevens wijzigen'}</h1>
    {message && <p className="positive" role="status">{message}</p>}
    {error && <p className="error" role="alert">{error}</p>}
    {!data.can_edit ? <p className="empty">{data.readonly_reason || 'Dit profiel kan niet via de app worden gewijzigd. Neem contact op met je club.'}</p> : !allowed ? <article className="panel"><p>Log opnieuw in en geef toestemming om je eigen contactgegevens en het gezinsadres via de app te wijzigen.</p><button onClick={logout}>Opnieuw inloggen</button></article> : needsCheck ? <button disabled={busy} onClick={verify}>Controleer mijn gegevens</button> : editing ? <form onSubmit={submit}>
      <fieldset disabled={busy}>
        {editing === 'phones' && <>{Object.entries(PHONE_LABELS).map(([field, label]) => <label key={field} htmlFor={field}>{label}<input id={field} type="tel" autoComplete="off" maxLength={40} value={values[field]} onChange={update(field)} /></label>)}<p className="caption">Telefoon 2 blijft alleen in Rondo; Sportlink ondersteunt dit veld niet.</p></>}
        {editing === 'address' && <><p>Je wijzigt hiermee het woonadres van jezelf en je minderjarige kinderen binnen Rondo.</p>{Object.entries(ADDRESS_LABELS).map(([field, label]) => <label key={field} htmlFor={field}>{label}<input id={field} required={!['house_number_addition', 'state'].includes(field)} maxLength={field === 'country_code' ? 3 : 254} autoCapitalize={['postal_code', 'country_code'].includes(field) ? 'characters' : 'words'} value={values[field]} onChange={update(field)} /></label>)}</>}
        {editing === 'email_request' && <><p>Je nieuwe adres wordt pas actief nadat je de verificatielink hebt geopend. Een overeenkomend adres bij je minderjarige kinderen wordt ook aangepast.</p><label htmlFor="profile-email">Nieuw e-mailadres<input id="profile-email" type="email" autoComplete="email" autoCapitalize="none" required maxLength={254} value={values.email} onChange={update('email')} /></label><p>{SPORTLINK_EMAIL_SYNC_DELAY_MESSAGE}</p></>}
        {editing === 'email_remove' && <p>Wil je {fields.email_2} verwijderen? Hetzelfde tweede adres wordt ook verwijderd bij je minderjarige kinderen.</p>}
        {editing === 'email_cancel' && <p>De verificatielink voor {data.pending_email?.email} wordt ongeldig. Je huidige e-mailadres blijft behouden.</p>}
        <button type="submit">{busy ? 'Bezig…' : editing === 'email_request' ? 'Verificatielink sturen' : editing === 'address' ? 'Opslaan voor gezin' : editing === 'email_remove' ? 'Bevestig verwijderen' : editing === 'email_cancel' ? 'Bevestig annuleren' : 'Opslaan'}</button>
        <button type="button" className="secondary" onClick={() => { setEditing(null); setValues({}); setError(''); }}>Terug zonder opslaan</button>
      </fieldset>
    </form> : <>
      {data.pending_email && <article className="panel"><h2>Wacht op bevestiging</h2><p>{data.pending_email.email}</p><p>Open de verificatielink in je e-mail en keer terug naar de app.</p><button disabled={busy} onClick={verify}>Bevestiging controleren</button><button disabled={busy} className="secondary" onClick={() => open('email_cancel', {})}>Aanvraag annuleren</button></article>}
      <article className="panel"><h2>Telefoonnummers</h2><dl>{Object.entries(PHONE_LABELS).map(([field, label]) => <div key={field}><dt>{label}</dt><dd>{fields[field] || 'Niet ingesteld'}</dd></div>)}</dl><button disabled={busy} className="secondary" onClick={() => open('phones', phoneValues(fields))}>Telefoonnummers wijzigen</button></article>
      <article className="panel"><h2>E-mailadressen</h2><p>Primair: {fields.email_1 || 'Niet ingesteld'}</p><button disabled={busy || Boolean(data.pending_email)} className="secondary" onClick={() => open('email_request', { slot: 'primary', email: fields.email_1 || '' })}>Primair e-mailadres wijzigen</button><p>Tweede: {fields.email_2 || 'Niet ingesteld'}</p><button disabled={busy || Boolean(data.pending_email)} className="secondary" onClick={() => open('email_request', { slot: 'secondary', email: fields.email_2 || '' })}>Tweede e-mailadres wijzigen</button>{fields.email_2 && <button disabled={busy || Boolean(data.pending_email)} className="secondary" onClick={() => open('email_remove', {})}>Tweede e-mailadres verwijderen</button>}</article>
      <article className="panel"><h2>Woonadres</h2><p>{addressLabel(fields)}</p><button disabled={busy} className="secondary" onClick={() => open('address', homeAddress(fields))}>Woonadres wijzigen</button></article>
    </>}
  </section>;
}
