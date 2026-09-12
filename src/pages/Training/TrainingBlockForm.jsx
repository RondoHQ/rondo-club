import { useState } from 'react';
import { FIELD_SIZES, TRAINING_DAYS, fieldPart, fieldOffsets, toMinutes, trainingDefaults } from '@/utils/training';
import TrainingDialog from './TrainingDialog';

export default function TrainingBlockForm({ initial, teams, settings, onSave, onClose, onDelete }) {
  const [block, setBlock] = useState(initial);
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const selectableTeams = [...teams, ...block.team_ids.filter((id) => !teams.some((team) => team.id === id)).map((id) => ({ id, name: `Team ${id} (niet meer beschikbaar)` }))];
  const patch = (value) => setBlock((current) => ({ ...current, ...value }));
  const chooseTeam = (teamId, checked) => {
    const defaults = checked && !block.team_ids.length && !onDelete ? trainingDefaults(teamId, settings) : {};
    patch({ team_ids: checked ? [...block.team_ids, teamId] : block.team_ids.filter((id) => id !== teamId), ...defaults, ...(defaults.size ? { offset: 0 } : {}) });
  };
  const submit = (event) => {
    event.preventDefault();
    if (!block.team_ids.length && !block.label.trim()) { setError('Kies een team of vul een omschrijving in.'); return; }
    if (toMinutes(block.start) + block.duration > 1440) { setError('Een training moet binnen dezelfde dag eindigen.'); return; }
    onSave(block);
  };
  return <TrainingDialog title={onDelete ? 'Trainingsblok bewerken' : 'Trainingsblok toevoegen'} onClose={onClose}><form onSubmit={submit} className="space-y-4">
    <label className="block text-sm">Omschrijving (optioneel bij een team)<input autoFocus maxLength={100} className="input mt-1" value={block.label} placeholder="Bijv. Keeperstraining" onChange={(event) => patch({ label: event.target.value })} /></label>
    <fieldset className="space-y-2"><legend className="text-sm font-medium">Teams</legend><p className="text-xs text-gray-500 dark:text-gray-400">Meerdere teams mogen samen één blok delen. Laat leeg voor een los blok.</p><input className="input" type="search" aria-label="Teams zoeken" placeholder="Teams zoeken…" value={search} onChange={(event) => setSearch(event.target.value)} /><div className="max-h-36 overflow-y-auto rounded border border-gray-200 p-2 dark:border-gray-700">{selectableTeams.filter((team) => team.name.toLowerCase().includes(search.toLowerCase()) || block.team_ids.includes(team.id)).map((team) => <label key={team.id} className="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" checked={block.team_ids.includes(team.id)} onChange={(event) => chooseTeam(team.id, event.target.checked)} />{team.name}</label>)}</div></fieldset>
    <label className="block text-sm">Kleur van leeftijdslaag<select className="input mt-1" value={block.age_group_id || ''} onChange={(event) => patch({ age_group_id: event.target.value })}><option value="">Volgens gekoppeld team</option>{settings.age_groups.map((group) => <option key={group.id} value={group.id}>{group.name}</option>)}</select></label>
    <div className="grid grid-cols-2 gap-3"><label className="text-sm">Dag<select className="input mt-1" value={block.day} onChange={(event) => patch({ day: Number(event.target.value) })}>{TRAINING_DAYS.map((name, index) => <option key={name} value={index + 1}>{name}</option>)}</select></label><label className="text-sm">Begintijd<input required className="input mt-1" type="time" step={900} value={block.start} onChange={(event) => patch({ start: event.target.value })} /></label><label className="text-sm">Duur (minuten)<input required className="input mt-1" type="number" min={15} max={360} step={15} value={block.duration} onChange={(event) => patch({ duration: Number(event.target.value) })} /></label><label className="text-sm">Veld<select required className="input mt-1" value={block.pitch_id} onChange={(event) => patch({ pitch_id: event.target.value })}>{settings.pitches.map((pitch) => <option key={pitch.id} value={pitch.id}>{pitch.name}</option>)}</select></label><label className="text-sm">Veldgrootte<select className="input mt-1" value={block.size} onChange={(event) => patch({ size: Number(event.target.value), offset: 0 })}>{FIELD_SIZES.map((size) => <option key={size.value} value={size.value}>{size.label}</option>)}</select></label><label className="text-sm">Velddeel<select className="input mt-1" value={block.offset} onChange={(event) => patch({ offset: Number(event.target.value) })}>{fieldOffsets(block.size).map((offset) => <option key={offset} value={offset}>{fieldPart({ offset, size: block.size })}</option>)}</select></label></div>
    {error ? <p role="alert" className="text-sm text-red-600 dark:text-red-400">{error}</p> : null}
    <div className="flex flex-wrap justify-between gap-3"><button type="submit" className="btn-primary">Blok toepassen</button>{onDelete ? <button type="button" className="btn-tertiary text-red-600 dark:text-red-400" onClick={onDelete}>Blok verwijderen</button> : null}</div>
  </form></TrainingDialog>;
}
