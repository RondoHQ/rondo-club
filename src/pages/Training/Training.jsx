import { useState } from 'react';
import { Link, useBeforeUnload } from 'react-router-dom';
import { useTrainingSchedules, useTrainingSettings, useTrainingMutation, useTrainingAccess } from '@/hooks/useTraining';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { TRAINING_DAYS, conflictingBlocks, editableSchedule, schedulePayload } from '@/utils/training';
import TrainingBoard from './TrainingBoard';
import TrainingBlockForm from './TrainingBlockForm';
import TrainingDialog from './TrainingDialog';

function VersionForm({ source, onSubmit, onClose, busy, error }) {
  const [name, setName] = useState(source ? `${source.name} (kopie)` : '');
  const year = new Date().getFullYear() - (new Date().getMonth() < 6 ? 1 : 0);
  const [season, setSeason] = useState(`${year}/${String(year + 1).slice(-2)}`);
  return <TrainingDialog title={source ? 'Schema kopiëren' : 'Nieuw trainingsschema'} onClose={onClose}><form className="space-y-4" onSubmit={(event) => { event.preventDefault(); onSubmit({ name, season }); }}><label className="block text-sm">Naam<input required autoFocus maxLength={100} className="input mt-1" value={name} onChange={(event) => setName(event.target.value)} placeholder="Bijv. Regulier of Slecht weer" /></label>{!source ? <label className="block text-sm">Seizoen<input required maxLength={30} className="input mt-1" value={season} onChange={(event) => setSeason(event.target.value)} /></label> : <p className="text-sm">Alle blokken worden gekopieerd naar een onafhankelijke versie. Je kunt het seizoen daarna aanpassen.</p>}<p role="alert" className="text-sm text-red-600 dark:text-red-400">{error}</p><button className="btn-primary" disabled={busy} type="submit">{busy ? 'Opslaan…' : source ? 'Kopie maken' : 'Schema maken'}</button></form></TrainingDialog>;
}

export default function Training() {
  useDocumentTitle('Trainingsschema');
  const { data, isLoading, error } = useTrainingSchedules();
  const { data: config, error: configError } = useTrainingSettings();
  const { isAdmin } = useTrainingAccess();
  const mutation = useTrainingMutation();
  const [selectedId, setSelectedId] = useState(null);
  const [draft, setDraft] = useState(null);
  const [day, setDay] = useState(1);
  const [editor, setEditor] = useState(null);
  const [versionAction, setVersionAction] = useState(null);
  const [message, setMessage] = useState('');
  const source = data?.schedules.find((item) => item.id === selectedId) || data?.schedules.find((item) => item.id === data.active_id) || data?.schedules[0];
  const schedule = draft || (source ? editableSchedule(source) : null);
  const conflicts = conflictingBlocks(schedule?.blocks || []);
  const busy = mutation.isPending;
  useBeforeUnload((event) => { if (draft) { event.preventDefault(); event.returnValue = ''; } });
  const edit = (patch) => { setDraft({ ...schedule, ...patch }); setMessage(''); };
  const applyBlock = (block) => { edit({ blocks: schedule.blocks.some((item) => item.block_id === block.block_id) ? schedule.blocks.map((item) => item.block_id === block.block_id ? block : item) : [...schedule.blocks, block] }); setEditor(null); };
  const discard = () => !draft || window.confirm('Niet-opgeslagen wijzigingen weggooien?');
  const run = async (args, success) => {
    setMessage('');
    try { const result = await mutation.mutateAsync(args); success?.(result); }
    catch (failure) { setMessage(failure.response?.data?.message || 'Opslaan mislukt. Je wijzigingen staan nog in de planner.'); }
  };
  const newBlock = () => setEditor({ block_id: crypto.randomUUID(), label: '', team_ids: [], pitch_id: data.pitches[0]?.id || '', day: day || 1, start: '18:00', duration: 60, size: 1, offset: 0 });
  if (isLoading) return <p>Trainingsschema’s laden…</p>;
  if (error) return <p role="alert">Trainingsschema’s konden niet worden geladen. Vernieuw de pagina.</p>;
  const apiRoot = `${(window.rondoConfig?.apiUrl || `${window.location.origin}/wp-json`).replace(/\/$/, '')}/rondo/v1/training`;
  return <div className="space-y-5 text-gray-900 dark:text-gray-100">
    <div className="flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-2xl font-bold text-brand-gradient">Trainingsschema</h1><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Maak weekschema’s en kies welke versie de teams zien.</p></div>{isAdmin ? <Link to="/settings/training" onClick={(event) => { if (!discard()) event.preventDefault(); }} className="btn-secondary">Trainingsinstellingen</Link> : null}</div>
    {configError ? <p role="alert">Teaminstellingen konden niet worden geladen. Vernieuw de pagina om blokken te bewerken.</p> : null}
    <div className="card p-4 space-y-4"><div className="flex flex-wrap items-end gap-3"><label className="min-w-48 flex-1 text-sm">Schema<select className="input mt-1" disabled={busy} value={source?.id || ''} onChange={(event) => { if (discard()) { setSelectedId(Number(event.target.value)); setDraft(null); setMessage(''); } }}><option value="" disabled>Kies een schema</option>{data?.schedules.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.season}{item.id === data.active_id ? ' (actief)' : ''}</option>)}</select></label>{isAdmin ? <><button className="btn-secondary" disabled={busy} onClick={() => { if (discard()) { setDraft(null); setMessage(''); setVersionAction('new'); } }}>Nieuw schema</button><button className="btn-secondary" disabled={!schedule || busy || Boolean(draft)} onClick={() => { setMessage(''); setVersionAction('copy'); }}>Kopie maken</button></> : null}</div>
      {schedule ? <div className={`rounded-lg p-3 text-sm ${schedule.id === data.active_id ? 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-gray-50 text-gray-600 dark:bg-gray-900 dark:text-gray-300'}`}>{schedule.id === data.active_id ? 'Actief: de trainingstijden uit dit schema staan op de teampagina’s.' : `Deze versie is beschikbaar via de API. Actief voor teams: ${data.schedules.find((item) => item.id === data.active_id)?.name || 'nog geen schema'}.`}{isAdmin && schedule.id !== data.active_id ? <button className="btn-secondary ml-3" disabled={busy || Boolean(draft)} onClick={() => run({ path: `schedules/${schedule.id}/activate`, data: { revision: schedule.revision } }, () => setMessage('Dit schema is nu actief voor de teams.'))}>Dit schema activeren</button> : null}</div> : <p className="text-sm text-gray-500 dark:text-gray-400">Er zijn nog geen trainingsschema’s. Maak bijvoorbeeld ‘Regulier’ en kopieer dit later naar ‘Slecht weer’.</p>}
    </div>
    {message ? <p role="alert" className="card p-4 text-sm">{message}</p> : null}
    {schedule ? <>
      {isAdmin ? <div className="flex flex-wrap gap-3"><label className="flex-1 text-sm">Naam<input className="input mt-1" maxLength={100} value={schedule.name} disabled={busy} onChange={(event) => edit({ name: event.target.value })} /></label><label className="w-36 text-sm">Seizoen<input className="input mt-1" maxLength={30} value={schedule.season} disabled={busy} onChange={(event) => edit({ season: event.target.value })} /></label></div> : null}
      <div className="flex flex-wrap items-center justify-between gap-3"><div className="flex flex-wrap gap-1" aria-label="Dag kiezen">{[{ value: 0, name: 'Hele week' }, ...TRAINING_DAYS.map((name, index) => ({ value: index + 1, name }))].map((item) => <button key={item.value} aria-pressed={day === item.value} onClick={() => setDay(item.value)} className={day === item.value ? 'btn-primary' : 'btn-tertiary'}>{item.name}</button>)}</div>{isAdmin ? <button className="btn-secondary" onClick={newBlock} disabled={!data.pitches.length || !config || busy}>Blok toevoegen</button> : null}</div>
      {conflicts.size ? <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{conflicts.size} blokken overlappen in veldbezetting of team. Pas de rode blokken aan voordat je opslaat.</p> : null}
      {!data.pitches.length ? <p className="card p-5">Voeg eerst velden toe bij de trainingsinstellingen.</p> : <><p className="text-sm text-gray-500 dark:text-gray-400">{isAdmin ? 'Sleep aan de greep om een blok te verplaatsen. Klik op het blok om dag, tijd, veld of teams te wijzigen. Klik daarna op Schema opslaan.' : 'Elk blok toont teams, trainingstijd en velddeel.'}</p><TrainingBoard settings={config?.settings} schedule={schedule} pitches={data.pitches} teams={config?.teams || []} selectedDay={day} conflicts={conflicts} onChange={applyBlock} onEdit={setEditor} disabled={!isAdmin || busy || !config} /></>}
      {isAdmin ? <div className="sticky bottom-0 z-40 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800"><button className="btn-primary" disabled={!draft || busy || Boolean(conflicts.size) || !schedule.name.trim() || !schedule.season.trim()} onClick={() => run({ path: `schedules/${schedule.id}`, method: 'put', data: schedulePayload(schedule) }, () => { setDraft(null); setMessage('Trainingsschema opgeslagen.'); })}>{busy ? 'Opslaan…' : 'Schema opslaan'}</button>{draft ? <><span className="text-sm">Niet-opgeslagen wijzigingen</span><button className="btn-tertiary" disabled={busy} onClick={() => { if (discard()) setDraft(null); }}>Wijzigingen weggooien</button></> : <span className="text-sm text-gray-500 dark:text-gray-400">Alle wijzigingen opgeslagen</span>}<button className="btn-tertiary ml-auto text-red-600 dark:text-red-400" disabled={busy || Boolean(draft) || schedule.id === data.active_id} onClick={() => { if (window.confirm(`Schema “${schedule.name}” naar de prullenbak verplaatsen?`)) run({ path: `schedules/${schedule.id}`, method: 'delete', data: { revision: schedule.revision } }, () => { setSelectedId(null); setDraft(null); setMessage('Schema verwijderd.'); }); }}>Schema verwijderen</button></div> : null}
    </> : null}
    {isAdmin ? <details className="card p-4 text-sm"><summary className="cursor-pointer font-medium">API voor de website</summary><div className="mt-3 space-y-2"><p>Elke versie heeft een vaste numerieke identifier. Alle opgeslagen versies zijn opvraagbaar.</p><p>Alle versies: <code className="break-all">{apiRoot}/schedules</code></p>{schedule ? <p>Deze versie (identifier {schedule.id}): <code className="break-all">{apiRoot}/schedules/{schedule.id}</code></p> : null}<p>Actieve versie: <code className="break-all">{apiRoot}/active</code></p><p>Bij ‘Alleen admins’ vereist de API een aangemelde beheerder of diens application password. Bij ‘Aan’ zijn de schema’s openbaar; instellingen en bewerken blijven voor admins.</p></div></details> : null}
    {editor && config ? <TrainingBlockForm initial={editor} teams={config.teams} settings={config.settings} onClose={() => setEditor(null)} onSave={applyBlock} onDelete={schedule.blocks.some((block) => block.block_id === editor.block_id) ? () => { edit({ blocks: schedule.blocks.filter((block) => block.block_id !== editor.block_id) }); setEditor(null); } : null} /> : null}
    {versionAction ? <VersionForm error={message} source={versionAction === 'copy' ? source : null} busy={busy} onClose={() => setVersionAction(null)} onSubmit={({ name, season }) => run({ path: versionAction === 'copy' ? `schedules/${source.id}/copy` : 'schedules', data: versionAction === 'copy' ? { name } : { name, season, revision: 0, blocks: [] } }, (result) => { setSelectedId(result.id); setDraft(null); setVersionAction(null); setMessage('Schema aangemaakt.'); })} /> : null}
  </div>;
}
