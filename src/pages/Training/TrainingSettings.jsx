import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTrainingSettings, useTrainingMutation } from '@/hooks/useTraining';
import { FIELD_SIZES } from '@/utils/training';

function SizeSelect({ value, onChange, inherit = false, label }) {
  return <select className="input" aria-label={label} value={value ?? ''} onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}>{inherit ? <option value="">Volgens leeftijdslaag</option> : null}{FIELD_SIZES.map((size) => <option key={size.value} value={size.value}>{size.label}</option>)}</select>;
}

function SettingsForm({ initial, teams }) {
  const [settings, setSettings] = useState(() => ({ ...initial, teams: initial.teams.filter((item) => teams.some((team) => team.id === item.team_id)) }));
  const [dirty, setDirty] = useState(false);
  const [message, setMessage] = useState('');
  const [filter, setFilter] = useState('');
  const mutation = useTrainingMutation();
  const change = (key, value) => { setSettings((current) => ({ ...current, [key]: value })); setDirty(true); setMessage(''); };
  const changeRow = (key, id, patch) => change(key, settings[key].map((item) => item.id === id ? { ...item, ...patch } : item));
  const changeTeam = (id, patch) => {
    const existing = settings.teams.find((item) => item.team_id === id) || { team_id: id, age_group_id: '', duration: null, size: null };
    change('teams', [...settings.teams.filter((item) => item.team_id !== id), { ...existing, ...patch }]);
  };
  const save = async (event) => {
    event.preventDefault();
    try {
      const result = await mutation.mutateAsync({ path: 'settings', method: 'put', data: settings });
      setSettings(result); setDirty(false); setMessage('Trainingsinstellingen opgeslagen.');
    } catch (error) { setMessage(error.response?.data?.message || 'Opslaan mislukt.'); }
  };
  return (
    <form onSubmit={save} className="space-y-6">
      <div className="flex items-center justify-between gap-4"><h2 className="text-xl font-semibold text-brand-gradient">Trainingsinstellingen</h2><Link className="btn-secondary" to="/trainingsschema">Naar trainingsschema</Link></div>
      <fieldset disabled={mutation.isPending} className="space-y-6 disabled:opacity-60">
        <section className="card p-5 space-y-4">
          <h3 className="font-semibold">Velden</h3><p className="text-sm text-gray-500 dark:text-gray-400">Elk veld heeft vier delen: A, B, C en D. Je kunt kwart-, halve en hele velden plannen.</p>
          {settings.pitches.map((pitch) => <div key={pitch.id} className="flex gap-3"><input required maxLength={100} aria-label="Veldnaam" className="input" value={pitch.name} onChange={(event) => changeRow('pitches', pitch.id, { name: event.target.value })} /><button type="button" className="btn-tertiary" aria-label={`Verwijder ${pitch.name || 'veld'}`} onClick={() => change('pitches', settings.pitches.filter((item) => item.id !== pitch.id))}>Verwijderen</button></div>)}
          <button type="button" className="btn-secondary" onClick={() => change('pitches', [...settings.pitches, { id: crypto.randomUUID(), name: '' }])}>Veld toevoegen</button>
        </section>
        <section className="card p-5 space-y-4">
          <h3 className="font-semibold">Standaarden per leeftijdslaag</h3><p className="text-sm text-gray-500 dark:text-gray-400">Deze waarden worden ingevuld bij nieuwe trainingsblokken. Bestaande blokken veranderen niet.</p>
          {settings.age_groups.map((group) => <div key={group.id} className="grid gap-3 sm:grid-cols-4"><label className="text-sm">Leeftijdslaag<input required maxLength={100} className="input mt-1" placeholder="Bijv. O13" value={group.name} onChange={(event) => changeRow('age_groups', group.id, { name: event.target.value })} /></label><label className="text-sm">Duur (minuten)<input required className="input mt-1" type="number" min={15} max={360} step={15} value={group.duration} onChange={(event) => changeRow('age_groups', group.id, { duration: Number(event.target.value) })} /></label><label className="text-sm">Veldgrootte<SizeSelect value={group.size} onChange={(size) => changeRow('age_groups', group.id, { size })} label={`Veldgrootte ${group.name}`} /></label><button type="button" className="btn-tertiary self-end" onClick={() => change('age_groups', settings.age_groups.filter((item) => item.id !== group.id))}>Leeftijdslaag verwijderen</button></div>)}
          <button type="button" className="btn-secondary" onClick={() => change('age_groups', [...settings.age_groups, { id: crypto.randomUUID(), name: '', duration: 60, size: 1 }])}>Leeftijdslaag toevoegen</button>
        </section>
        <section className="card p-5 space-y-4">
          <h3 className="font-semibold">Instellingen per team</h3><p className="text-sm text-gray-500 dark:text-gray-400">Koppel de leeftijdslaag en vul alleen afwijkende tijden of veldgroottes in.</p>
          <input type="search" aria-label="Zoek team" placeholder="Zoek team…" className="input" value={filter} onChange={(event) => setFilter(event.target.value)} />
          <div className="overflow-x-auto"><table className="w-full min-w-[650px] text-sm"><thead><tr className="text-left"><th className="p-2">Team</th><th className="p-2">Leeftijdslaag</th><th className="p-2">Afwijkende duur (minuten)</th><th className="p-2">Veldgrootte</th></tr></thead><tbody>{teams.filter((team) => team.name.toLowerCase().includes(filter.toLowerCase())).map((team) => {
            const value = settings.teams.find((item) => item.team_id === team.id);
            const group = settings.age_groups.find((item) => item.id === value?.age_group_id);
            return <tr key={team.id} className="border-t border-gray-200 dark:border-gray-700"><th scope="row" className="p-2 text-left">{team.name}</th><td className="p-2"><select className="input" aria-label={`Leeftijdslaag ${team.name}`} value={value?.age_group_id || ''} onChange={(event) => changeTeam(team.id, { age_group_id: event.target.value })}><option value="">Kies leeftijdslaag</option>{settings.age_groups.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></td><td className="p-2"><input className="input" aria-label={`Duur ${team.name}`} type="number" min={15} max={360} step={15} placeholder={String(group?.duration || 60)} value={value?.duration ?? ''} onChange={(event) => changeTeam(team.id, { duration: event.target.value === '' ? null : Number(event.target.value) })} /></td><td className="p-2"><SizeSelect inherit value={value?.size} label={`Veldgrootte ${team.name}`} onChange={(size) => changeTeam(team.id, { size })} /></td></tr>;
          })}</tbody></table></div>
        </section>
        <div className="sticky bottom-0 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><button className="btn-primary" type="submit" disabled={!dirty}>{mutation.isPending ? 'Opslaan…' : 'Instellingen opslaan'}</button>{dirty ? <span className="text-sm">Niet-opgeslagen wijzigingen</span> : null}<p role="status" className="text-sm">{message}</p></div>
      </fieldset>
    </form>
  );
}

export default function TrainingSettings() {
  const { data, isLoading, error, refetch } = useTrainingSettings();
  if (isLoading) return <p>Trainingsinstellingen laden…</p>;
  if (error) return <p role="alert">Instellingen konden niet worden geladen. <button className="underline" onClick={() => refetch()}>Opnieuw proberen</button></p>;
  return data ? <SettingsForm initial={data.settings} teams={data.teams} /> : null;
}
