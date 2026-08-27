import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, CheckCircle2, Plus, Trash2 } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import {
  useSaveTournamentEntryDraft,
  useSubmitTournamentEntry,
  useTournamentEntry,
} from '@/hooks/useTournaments';
import { formatTournamentCurrency, formatTournamentDate } from './tournamentFormatters';

function errorMessage(error) {
  return error?.response?.data?.message || error?.message || 'Er ging iets mis.';
}

function EntryEditor({ entry }) {
  const saveDraft = useSaveTournamentEntryDraft();
  const submitEntry = useSubmitTournamentEntry();
  const initialRows = entry.draft_team_entries?.length ? entry.draft_team_entries : [{ sequence: 1, player_count: '' }];
  const [form, setForm] = useState({
    contact_name: entry.contact_name || '',
    contact_email: entry.contact_email || '',
    contact_mobile: entry.contact_mobile || '',
    team_entries: initialRows.map((row) => ({ ...row })),
  });
  const [message, setMessage] = useState('');

  const payload = () => ({ ...form, version: entry.version });
  const save = async () => {
    setMessage('');
    await saveDraft.mutateAsync({ id: entry.id, data: payload() });
    setMessage('Concept opgeslagen. Andere kaderleden zien deze versie direct.');
  };
  const submit = async (event) => {
    event.preventDefault();
    setMessage('');
    await submitEntry.mutateAsync({ id: entry.id, data: payload() });
  };
  const updatePlayers = (index, value) => setForm((current) => ({
    ...current,
    team_entries: current.team_entries.map((row, rowIndex) => (rowIndex === index ? { ...row, player_count: value } : row)),
  }));
  const addTeam = () => setForm((current) => ({
    ...current,
    team_entries: [...current.team_entries, { sequence: current.team_entries.length + 1, player_count: '' }],
  }));
  const removeTeam = (index) => setForm((current) => ({
    ...current,
    team_entries: current.team_entries.filter((_, rowIndex) => rowIndex !== index).map((row, rowIndex) => ({ ...row, sequence: rowIndex + 1 })),
  }));

  const mutationError = saveDraft.error || submitEntry.error;
  return (
    <form className="space-y-6" onSubmit={submit}>
      <section className="card space-y-4 p-5">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div><h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Teams inschrijven</h2><p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Vul per toernooiteam het aantal spelers in. De contactpersoon geldt voor alle teams hieronder.</p></div>
          <button type="button" className="btn-tertiary inline-flex items-center justify-center" onClick={addTeam}><Plus className="mr-2 h-4 w-4" />Team toevoegen</button>
        </div>
        <div className="space-y-3">{form.team_entries.map((team, index) => (
          <div key={team.sequence} className="flex items-end gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <label className="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">{entry.team_name} · team {index + 1}
              <input className="input mt-1" type="number" min="1" required placeholder="Aantal spelers" value={team.player_count} onChange={(event) => updatePlayers(index, event.target.value)} />
            </label>
            <button type="button" className="btn-tertiary p-2" aria-label={`Team ${index + 1} verwijderen`} disabled={form.team_entries.length === 1} onClick={() => removeTeam(index)}><Trash2 className="h-4 w-4" /></button>
          </div>
        ))}</div>
      </section>

      <section className="card space-y-4 p-5">
        <div><h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Contactpersoon</h2><p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Eén contactpersoon voor alle hierboven ingeschreven teams.</p></div>
        <div className="grid gap-4 md:grid-cols-3">
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Naam<input className="input mt-1" required value={form.contact_name} onChange={(event) => setForm({ ...form, contact_name: event.target.value })} /></label>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">E-mailadres<input className="input mt-1" type="email" required value={form.contact_email} onChange={(event) => setForm({ ...form, contact_email: event.target.value })} /></label>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Mobiel nummer<input className="input mt-1" required value={form.contact_mobile} onChange={(event) => setForm({ ...form, contact_mobile: event.target.value })} /></label>
        </div>
      </section>

      {mutationError ? <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{errorMessage(mutationError)}</div> : null}
      {message ? <p className="text-sm text-green-700 dark:text-green-300">{message}</p> : null}
      <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
        <button type="button" className="btn-tertiary" disabled={saveDraft.isPending || submitEntry.isPending} onClick={save}>{saveDraft.isPending ? 'Opslaan…' : 'Concept opslaan'}</button>
        <button className="btn-primary" disabled={saveDraft.isPending || submitEntry.isPending}>{submitEntry.isPending ? 'Bevestigen…' : 'Inschrijving bevestigen'}</button>
      </div>
    </form>
  );
}

function SubmittedEntry({ entry }) {
  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
        <div className="flex items-center font-semibold"><CheckCircle2 className="mr-2 h-5 w-5" />Inschrijving bevestigd</div>
        <p className="mt-1 text-sm">De inschrijving is opgeslagen. Je ontvangt de betaallink zodra deze beschikbaar is.</p>
      </div>
      <section className="card p-5">
        <dl className="grid gap-4 md:grid-cols-3">
          <div><dt className="text-xs uppercase text-gray-500">Toernooiteams</dt><dd className="mt-1 font-semibold text-gray-900 dark:text-gray-100">{entry.registered_team_count}</dd></div>
          <div><dt className="text-xs uppercase text-gray-500">Spelers</dt><dd className="mt-1 font-semibold text-gray-900 dark:text-gray-100">{entry.player_count}</dd></div>
          <div><dt className="text-xs uppercase text-gray-500">Totaal</dt><dd className="mt-1 font-semibold text-gray-900 dark:text-gray-100">{formatTournamentCurrency(entry.total_amount)}</dd></div>
        </dl>
        <div className="mt-5 border-t border-gray-200 pt-5 dark:border-gray-700"><h2 className="font-semibold text-gray-900 dark:text-gray-100">Contactpersoon</h2><p className="mt-2 text-sm text-gray-600 dark:text-gray-300">{entry.contact_name}<br />{entry.contact_email}<br />{entry.contact_mobile}</p></div>
      </section>
    </div>
  );
}

export default function TournamentEntry() {
  const { id } = useParams();
  const { data: entry, isLoading, error } = useTournamentEntry(id);
  useDocumentTitle(entry ? `${entry.tournament.name} · ${entry.team_name}` : 'Toernooi-inschrijving');
  if (isLoading) return <ContentLoadingSpinner />;
  if (error) return <div className="card p-5 text-sm text-red-700 dark:text-red-300">{errorMessage(error)}</div>;

  return (
    <div className="space-y-6">
      <div>
        <Link to="/mijn-toernooien" className="mb-3 inline-flex items-center text-sm text-bright-cobalt dark:text-electric-cyan"><ArrowLeft className="mr-1 h-4 w-4" />Mijn toernooien</Link>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{entry.tournament.name}</h1>
        <p className="mt-1 text-gray-600 dark:text-gray-400">{entry.team_name} · deadline {formatTournamentDate(entry.tournament.internal_deadline)}</p>
      </div>
      <section className="card space-y-4 p-5">
        {entry.tournament.description ? <div className="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{entry.tournament.description}</div> : null}
        <div className="grid gap-3 md:grid-cols-2">{entry.tournament.schedule.map((row, index) => <div key={index} className="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800"><strong className="text-gray-900 dark:text-gray-100">{row.age_group}</strong><span className="mt-1 block text-gray-600 dark:text-gray-300">{formatTournamentDate(row.start_datetime)}{row.location ? ` · ${row.location}` : ''}</span></div>)}</div>
        <div className="grid gap-3 md:grid-cols-2">{entry.tournament.pricing_rules.map((row, index) => <div key={index} className="text-sm text-gray-600 dark:text-gray-300">O{row.min_age} t/m O{row.max_age}: <strong>{formatTournamentCurrency(row.amount)} per team</strong>{row.game_format ? ` · ${row.game_format}` : ''}</div>)}</div>
      </section>
      {entry.registration_status === 'submitted' ? <SubmittedEntry entry={entry} /> : entry.can_edit ? <EntryEditor key={`${entry.id}:${entry.version}`} entry={entry} /> : <div className="card p-5 text-sm text-amber-700 dark:text-amber-300">De interne deadline is verstreken. De toernooicoördinator kan de deadline verlengen.</div>}
    </div>
  );
}
