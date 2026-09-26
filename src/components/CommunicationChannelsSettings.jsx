import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Plus, X } from 'lucide-react';
import { prmApi } from '@/api/client';

export default function CommunicationChannelsSettings({ clubConfig, setClubConfig, loading }) {
  const [channels, setChannels] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const client = useQueryClient();

  useEffect(() => { setChannels(clubConfig?.communication_channels || []); }, [clubConfig]);

  function change(index, values) {
    setSaved(false);
    setChannels((previous) => previous.map((channel, i) => i === index ? { ...channel, ...values } : channel));
  }

  async function save(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    setSaved(false);
    try {
      const response = await prmApi.updateClubConfig({ communication_channels: channels });
      setClubConfig(response.data);
      client.invalidateQueries({ queryKey: ['communications'] });
      setSaved(true);
    } catch (err) {
      setError(err.response?.data?.message || 'Kanalen opslaan is niet gelukt. Probeer het opnieuw.');
    } finally { setBusy(false); }
  }

  return <section className="card p-6" aria-labelledby="communication-channels-title">
    <h2 id="communication-channels-title" className="mb-2 text-lg font-semibold text-brand-gradient">Communicatiekanalen</h2>
    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">Bepaal welke kanalen je club gebruikt, zoals nieuwsbrief, website of LinkedIn. Zet een kanaal uit om het niet meer bij nieuwe items te kunnen kiezen. Bestaande items blijven bewaard.</p>
    <form onSubmit={save} className="space-y-3">
      <fieldset disabled={busy || loading} className="space-y-3">
        <legend className="sr-only">Kanalen beheren</legend>
        {channels.map((channel, index) => <div key={channel.id || `new-${index}`} className="flex flex-wrap items-center gap-3">
          <input className="input min-w-0 flex-1" aria-label={`Naam kanaal ${index + 1}`} value={channel.label} maxLength={80} required onChange={(e) => change(index, { label: e.target.value })} />
          <label className="flex min-h-10 items-center gap-2 text-sm dark:text-gray-200"><input type="checkbox" checked={channel.active} onChange={(e) => change(index, { active: e.target.checked })} />Actief</label>
          {!channel.id && <button type="button" className="btn-tertiary" aria-label={`Nieuw kanaal ${index + 1} verwijderen`} onClick={() => { setSaved(false); setChannels(channels.filter((_, i) => i !== index)); }}><X className="h-4 w-4" /></button>}
        </div>)}
        <button type="button" className="btn-secondary" disabled={channels.length >= 100} onClick={() => { setSaved(false); setChannels([...channels, { label: '', active: true }]); }}><Plus className="mr-2 h-4 w-4" />Kanaal toevoegen</button>
      </fieldset>
      {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}
      <div className="flex items-center gap-3"><button type="submit" className="btn-primary" disabled={busy || loading}>{busy ? 'Opslaan…' : 'Kanalen opslaan'}</button>{saved && <span role="status" className="text-sm text-green-700 dark:text-green-300">Opgeslagen</span>}</div>
    </form>
  </section>;
}
