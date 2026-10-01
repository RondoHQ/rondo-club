import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useNewsletterMetadata } from '@/hooks/useNewsletter';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const labelClass = 'block text-sm font-medium text-gray-800 dark:text-gray-100';
const errorText = (e) => e?.response?.data?.message || 'Opslaan is niet gelukt. Probeer het opnieuw.';

function ProfileForm({ user, onSaved }) {
  const [profile, setProfile] = useState(user.profile);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  async function save(e) {
    e.preventDefault(); setBusy(true); setMessage(''); setError('');
    try { const { data } = await prmApi.saveNewsletterProfile(user.id, profile); setProfile(data); onSaved(); setMessage('Ondertekeningsprofiel opgeslagen.'); }
    catch (err) { setError(errorText(err)); } finally { setBusy(false); }
  }
  return <form onSubmit={save} className="space-y-4">
    <fieldset disabled={busy} className="space-y-4"><legend className="sr-only">Ondertekeningsprofiel van {user.name}</legend>
      <div className="grid gap-4 sm:grid-cols-2">{[['name', 'Naam in handtekening'], ['role', 'Functie'], ['from_name', 'Afzendernaam'], ['from_email', 'Afzendadres'], ['reply_to', 'Antwoorden naar']].map(([key, label]) => <label className={labelClass} key={key}>{label}<input className="input mt-1 w-full" type={key.includes('email') || key === 'reply_to' ? 'email' : 'text'} maxLength={250} value={profile[key]} onChange={(e) => setProfile({ ...profile, [key]: e.target.value })} /></label>)}</div>
      <p className="text-sm text-gray-600 dark:text-gray-300">Gebruik een afzendadres dat in Laposta is goedgekeurd.</p>
      <label className={labelClass}>Afbeelding van handtekening (optioneel)<input type="url" className="input mt-1 w-full" placeholder="https://…" maxLength={2000} value={profile.signature_url} onChange={(e) => setProfile({ ...profile, signature_url: e.target.value })} /></label>
      <label className={labelClass}>Hoogte afbeelding (pixels)<input type="number" className="input mt-1 w-32" min={1} max={600} value={profile.signature_height} onChange={(e) => setProfile({ ...profile, signature_height: Number(e.target.value) })} /></label>
      <label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" checked={profile.active} onChange={(e) => setProfile({ ...profile, active: e.target.checked })} />Profiel gebruiken voor nieuwsbrieven</label>
      <button className="btn-primary min-h-11" type="submit">{busy ? 'Opslaan…' : 'Profiel opslaan'}</button>
    </fieldset>
    {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}
    {message && <p role="status" className="text-sm text-green-800 dark:text-green-300">{message}</p>}
  </form>;
}

function SettingsForm({ initial, metadata }) {
  const client = useQueryClient();
  const [config, setConfig] = useState({ ...initial.config, api_key: '' });
  const [hasKey, setHasKey] = useState(initial.has_key);
  const [selected, setSelected] = useState(metadata.users[0]?.id || 0);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const user = metadata.users.find((u) => u.id === selected);
  const refresh = () => client.invalidateQueries({ queryKey: ['newsletter'] });
  async function save(e) {
    e.preventDefault(); setBusy(true); setMessage(''); setError('');
    try { const { data } = await prmApi.saveNewsletterSettings(config); setConfig({ ...data.config, api_key: '' }); setHasKey(data.has_key); refresh(); setMessage('Nieuwsbriefinstellingen opgeslagen.'); }
    catch (err) { setError(errorText(err)); } finally { setBusy(false); }
  }
  async function importTemplate(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 500000) { setError('De template mag maximaal 500 kB zijn.'); return; }
    setConfig((previous) => ({ ...previous, template: '' }));
    try { const template = await file.text(); setConfig((previous) => ({ ...previous, template })); }
    catch { setError('Het bestand kon niet worden gelezen. Probeer het opnieuw.'); }
  }
  return <div className="mx-auto max-w-4xl space-y-8 text-gray-900 dark:text-gray-100">
    <Link to="/communicatie/planning" className="inline-flex min-h-9 items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><ArrowLeft className="h-4 w-4" />Terug naar planning</Link>
    <header><h1 className="text-2xl font-bold">Nieuwsbriefinstellingen</h1><p className="mt-2 text-gray-600 dark:text-gray-300">Eén mailtemplate, met een eigen afzender en handtekening per verantwoordelijke.</p></header>
    <form onSubmit={save} className="space-y-5 rounded-xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-700 dark:bg-gray-800">
      <fieldset disabled={busy} className="space-y-5"><legend className="mb-4 text-lg font-semibold">Verbinding en template</legend>
        <label className={labelClass}>Laposta API-sleutel<input type="password" autoComplete="new-password" className="input mt-1 w-full" value={config.api_key} onChange={(e) => setConfig({ ...config, api_key: e.target.value })} placeholder={hasKey ? 'Sleutel is ingesteld; leeg laten om te behouden' : 'Plak de API-sleutel'} /></label>
        <label className={labelClass}>Kanaal voor nieuwsbrieven<select className="input mt-1 w-full" value={config.channel_id} onChange={(e) => setConfig({ ...config, channel_id: e.target.value })}>{initial.channels.filter((c) => c.active).map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}</select></label>
        <label className={labelClass}>Aanhef<input className="input mt-1 w-full" maxLength={250} value={config.salutation} onChange={(e) => setConfig({ ...config, salutation: e.target.value })} /><span className="mt-1 block text-xs font-normal text-gray-600 dark:text-gray-300">Gebruik een algemene aanhef, of Laposta-personalisatie die beschikbaar is in alle gekozen lijsten.</span></label>
        <details><summary className="cursor-pointer py-2 text-sm font-medium">HTML-template beheren</summary><div className="mt-3 space-y-3"><label className={labelClass}>HTML-bestand importeren<input type="file" accept=".html,.htm,text/html" className="mt-2 block w-full text-sm" onChange={importTemplate} /></label><label className={labelClass}>Template<textarea className="input mt-1 min-h-60 w-full font-mono text-xs" value={config.template} onChange={(e) => setConfig({ ...config, template: e.target.value })} spellCheck={false} /></label><p className="break-words text-xs text-gray-600 dark:text-gray-300">Verplichte velden: %%PREHEADER%%, %%SALUTATION%%, %%HEADING%%, %%BODY_HTML%%, %%SIGNER_NAME%% en %%SIGNER_ROLE%%. Behoud de Laposta-tags voor afmelden en webversie. Optioneel: %%DOCUMENT_TITLE%%, %%SIGNATURE_URL%% en %%SIGNATURE_HEIGHT%%.</p></div></details>
        <button className="btn-primary min-h-11" type="submit">{busy ? 'Opslaan…' : 'Instellingen opslaan'}</button>
      </fieldset>
      {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}{message && <p role="status" className="text-sm text-green-800 dark:text-green-300">{message}</p>}
    </form>
    <section className="space-y-5 rounded-xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-700 dark:bg-gray-800"><h2 className="text-lg font-semibold">Ondertekeningsprofielen</h2><label className={labelClass}>Verantwoordelijke<select className="input mt-1 w-full" value={selected} onChange={(e) => setSelected(Number(e.target.value))}>{metadata.users.map((u) => <option key={u.id} value={u.id}>{u.name}{u.ready ? '' : ' · profiel ontbreekt'}</option>)}</select></label>{user && <ProfileForm key={user.id} user={user} onSaved={refresh} />}</section>
  </div>;
}

export default function NewsletterSettings() {
  useDocumentTitle('Nieuwsbriefinstellingen');
  const settings = useQuery({ queryKey: ['newsletter', 'settings'], queryFn: async () => (await prmApi.getNewsletterSettings()).data });
  const metadata = useNewsletterMetadata();
  if (settings.isPending || metadata.isPending) return <p role="status" className="p-6">Instellingen laden…</p>;
  if (settings.isError || metadata.isError) return <p role="alert" className="p-6 text-red-700 dark:text-red-300">{errorText(settings.error || metadata.error)}</p>;
  return <SettingsForm initial={settings.data} metadata={metadata.data} />;
}
