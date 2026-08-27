import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, CheckCircle2, Plus, Send, Trash2 } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import {
  usePublishTournament,
  useExtendTournamentDeadline,
  useSaveTournament,
  useTournament,
  useTournamentAssignmentOptions,
  useTournamentEntries,
} from '@/hooks/useTournaments';
import {
  formatTournamentCurrency,
  formatTournamentDate,
  toDateTimeLocal,
} from './tournamentFormatters';

const emptyTournament = {
  name: '',
  organizer: '',
  location: '',
  description: '',
  internal_deadline: '',
  external_deadline: '',
  schedule: [{ age_group: 'O6 t/m O19', start_datetime: '', location: '' }],
  pricing_rules: [
    { min_age: 6, max_age: 7, amount: 28, game_format: '4 tegen 4, zonder doelverdediger' },
    { min_age: 8, max_age: 20, amount: 48, game_format: '5 tegen 5, inclusief doelverdediger' },
  ],
};

function errorMessage(error, fallback = 'Er ging iets mis.') {
  return error?.response?.data?.message || error?.message || fallback;
}

function tournamentFormState(tournament) {
  const source = tournament || emptyTournament;
  return {
    name: source.name || '',
    organizer: source.organizer || '',
    location: source.location || '',
    description: source.description || '',
    internal_deadline: toDateTimeLocal(source.internal_deadline),
    external_deadline: toDateTimeLocal(source.external_deadline),
    schedule: (source.schedule?.length ? source.schedule : emptyTournament.schedule).map((row) => ({
      ...row,
      start_datetime: toDateTimeLocal(row.start_datetime),
    })),
    pricing_rules: (source.pricing_rules?.length ? source.pricing_rules : emptyTournament.pricing_rules).map((row) => ({ ...row })),
  };
}

function DraftEditor({ tournament }) {
  const navigate = useNavigate();
  const saveTournament = useSaveTournament();
  const [form, setForm] = useState(() => tournamentFormState(tournament));
  const [savedMessage, setSavedMessage] = useState('');

  const updateRow = (field, index, key, value) => {
    setForm((current) => ({
      ...current,
      [field]: current[field].map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: value } : row)),
    }));
  };

  const removeRow = (field, index) => {
    setForm((current) => ({ ...current, [field]: current[field].filter((_, rowIndex) => rowIndex !== index) }));
  };

  const handleSave = async (event) => {
    event.preventDefault();
    setSavedMessage('');
    const saved = await saveTournament.mutateAsync({ id: tournament?.id, data: form });
    setSavedMessage('Concept opgeslagen.');
    if (!tournament?.id) navigate(`/toernooien/${saved.id}`, { replace: true });
  };

  return (
    <form className="space-y-6" onSubmit={handleSave}>
      <section className="card space-y-4 p-5">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Toernooi-informatie</h2>
        <div className="grid gap-4 md:grid-cols-2">
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Naam
            <input className="input mt-1" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
          </label>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Organisator
            <input className="input mt-1" value={form.organizer} onChange={(event) => setForm({ ...form, organizer: event.target.value })} />
          </label>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Algemene locatie
            <input className="input mt-1" value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} />
          </label>
          <div />
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Interne deadline
            <input className="input mt-1" type="datetime-local" required value={form.internal_deadline} onChange={(event) => setForm({ ...form, internal_deadline: event.target.value })} />
          </label>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Deadline organisatie
            <input className="input mt-1" type="datetime-local" required value={form.external_deadline} onChange={(event) => setForm({ ...form, external_deadline: event.target.value })} />
          </label>
        </div>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Uitnodiging en overige informatie
          <textarea className="input mt-1 min-h-32" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
        </label>
      </section>

      <section className="card space-y-4 p-5">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Waar en wanneer</h2>
          <button type="button" className="btn-tertiary inline-flex items-center" onClick={() => setForm({ ...form, schedule: [...form.schedule, { age_group: '', start_datetime: '', location: '' }] })}>
            <Plus className="mr-2 h-4 w-4" />Moment
          </button>
        </div>
        {form.schedule.map((row, index) => (
          <div key={index} className="grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-[1fr_1fr_1fr_auto] dark:border-gray-700">
            <input className="input" aria-label={`Leeftijdsgroep moment ${index + 1}`} placeholder="Bijv. O6 t/m O7" required value={row.age_group} onChange={(event) => updateRow('schedule', index, 'age_group', event.target.value)} />
            <input className="input" aria-label={`Datum moment ${index + 1}`} type="datetime-local" required value={row.start_datetime} onChange={(event) => updateRow('schedule', index, 'start_datetime', event.target.value)} />
            <input className="input" aria-label={`Locatie moment ${index + 1}`} placeholder="Locatie" value={row.location} onChange={(event) => updateRow('schedule', index, 'location', event.target.value)} />
            <button type="button" className="btn-tertiary p-2" aria-label="Moment verwijderen" disabled={form.schedule.length === 1} onClick={() => removeRow('schedule', index)}><Trash2 className="h-4 w-4" /></button>
          </div>
        ))}
      </section>

      <section className="card space-y-4 p-5">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Tarieven en spelvorm</h2>
          <button type="button" className="btn-tertiary inline-flex items-center" onClick={() => setForm({ ...form, pricing_rules: [...form.pricing_rules, { min_age: '', max_age: '', amount: '', game_format: '' }] })}>
            <Plus className="mr-2 h-4 w-4" />Tarief
          </button>
        </div>
        {form.pricing_rules.map((row, index) => (
          <div key={index} className="grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-[8rem_8rem_9rem_1fr_auto] dark:border-gray-700">
            <input className="input" type="number" min="1" aria-label={`Vanaf leeftijd tarief ${index + 1}`} placeholder="Vanaf O" required value={row.min_age} onChange={(event) => updateRow('pricing_rules', index, 'min_age', event.target.value)} />
            <input className="input" type="number" min="1" aria-label={`Tot leeftijd tarief ${index + 1}`} placeholder="Tot O" required value={row.max_age} onChange={(event) => updateRow('pricing_rules', index, 'max_age', event.target.value)} />
            <input className="input" type="number" min="0" step="0.01" aria-label={`Bedrag tarief ${index + 1}`} placeholder="Bedrag" required value={row.amount} onChange={(event) => updateRow('pricing_rules', index, 'amount', event.target.value)} />
            <input className="input" aria-label={`Spelvorm tarief ${index + 1}`} placeholder="Spelvorm" value={row.game_format} onChange={(event) => updateRow('pricing_rules', index, 'game_format', event.target.value)} />
            <button type="button" className="btn-tertiary p-2" aria-label="Tarief verwijderen" disabled={form.pricing_rules.length === 1} onClick={() => removeRow('pricing_rules', index)}><Trash2 className="h-4 w-4" /></button>
          </div>
        ))}
      </section>

      {saveTournament.error ? <ErrorNotice error={saveTournament.error} /> : null}
      {savedMessage ? <p className="text-sm text-green-700 dark:text-green-300">{savedMessage}</p> : null}
      <div className="flex justify-end"><button className="btn-primary" disabled={saveTournament.isPending}>{saveTournament.isPending ? 'Opslaan…' : 'Concept opslaan'}</button></div>
    </form>
  );
}

function PublishPanel({ tournament }) {
  const optionsQuery = useTournamentAssignmentOptions(true);
  const publishTournament = usePublishTournament();
  const [selected, setSelected] = useState({});

  const toggleTeam = (team) => {
    setSelected((current) => {
      if (current[team.id]) {
        const next = { ...current };
        delete next[team.id];
        return next;
      }
      return { ...current, [team.id]: team.assignees.map((assignee) => assignee.user_id) };
    });
  };

  const toggleAssignee = (teamId, userId) => {
    setSelected((current) => {
      const ids = current[teamId] || [];
      return { ...current, [teamId]: ids.includes(userId) ? ids.filter((id) => id !== userId) : [...ids, userId] };
    });
  };

  const publish = async () => {
    const assignments = Object.entries(selected).map(([teamId, userIds]) => ({ team_id: Number(teamId), user_ids: userIds }));
    await publishTournament.mutateAsync({ id: tournament.id, assignments });
  };

  return (
    <section className="card space-y-4 p-5">
      <div>
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Teams en kader uitnodigen</h2>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Publiceren maakt per gekozen Rondo-team één gedeelde inschrijfopdracht en mailt alle geselecteerde kaderleden.</p>
      </div>
      {optionsQuery.isLoading ? <ContentLoadingSpinner /> : null}
      <div className="grid gap-3 lg:grid-cols-2">
        {(optionsQuery.data || []).map((team) => {
          const checked = Object.hasOwn(selected, team.id);
          return (
            <div key={team.id} className={`rounded-lg border p-4 ${checked ? 'border-electric-cyan bg-cyan-50/60 dark:bg-cyan-950/20' : 'border-gray-200 dark:border-gray-700'}`}>
              <label className="flex cursor-pointer items-start gap-3">
                <input type="checkbox" className="mt-1" checked={checked} disabled={team.assignees.length === 0} onChange={() => toggleTeam(team)} />
                <span><span className="font-medium text-gray-900 dark:text-gray-100">{team.name}</span><span className="block text-xs text-gray-500">{team.age_group}</span></span>
              </label>
              {team.assignees.length === 0 ? <p className="mt-3 text-sm text-amber-700 dark:text-amber-300">Geen actueel kaderlid met Rondo-account.</p> : null}
              {checked ? <div className="mt-3 space-y-2 border-t border-gray-200 pt-3 dark:border-gray-700">{team.assignees.map((assignee) => (
                <label key={assignee.user_id} className="flex cursor-pointer items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                  <input type="checkbox" className="mt-0.5" checked={(selected[team.id] || []).includes(assignee.user_id)} onChange={() => toggleAssignee(team.id, assignee.user_id)} />
                  <span>{assignee.name}<span className="block text-xs text-gray-500">{assignee.role}</span></span>
                </label>
              ))}</div> : null}
            </div>
          );
        })}
      </div>
      {publishTournament.error ? <ErrorNotice error={publishTournament.error} /> : null}
      <div className="flex justify-end"><button type="button" className="btn-primary inline-flex items-center" disabled={publishTournament.isPending || Object.keys(selected).length === 0} onClick={publish}><Send className="mr-2 h-4 w-4" />{publishTournament.isPending ? 'Publiceren…' : 'Publiceren en uitnodigen'}</button></div>
    </section>
  );
}

function EntriesOverview({ tournamentId }) {
  const { data: entries = [], isLoading, error } = useTournamentEntries(tournamentId);
  if (isLoading) return <ContentLoadingSpinner />;
  return (
    <section className="card overflow-hidden">
      <div className="border-b border-gray-200 p-5 dark:border-gray-700"><h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Voortgang per Rondo-team</h2></div>
      {error ? <div className="p-5"><ErrorNotice error={error} /></div> : null}
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th className="px-4 py-3">Team</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Ingeschreven</th><th className="px-4 py-3">Contact</th><th className="px-4 py-3">Kader</th><th className="px-4 py-3">Bedrag</th></tr></thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-700">{entries.map((entry) => (
            <tr key={entry.id}>
              <td className="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{entry.team_name}</td>
              <td className="px-4 py-3">{entry.registration_status === 'submitted' ? <span className="inline-flex items-center text-green-700 dark:text-green-300"><CheckCircle2 className="mr-1 h-4 w-4" />Ingeschreven</span> : <span className="text-amber-700 dark:text-amber-300">Niet ingeschreven</span>}</td>
              <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{entry.registered_team_count} teams · {entry.player_count} spelers</td>
              <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{entry.contact_name || 'Nog niet ingevuld'}</td>
              <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{entry.assignees.map((assignee) => assignee.name).join(', ')}</td>
              <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{entry.registration_status === 'submitted' ? formatTournamentCurrency(entry.total_amount) : '—'}</td>
            </tr>
          ))}</tbody>
        </table>
      </div>
    </section>
  );
}

function DeadlinePanel({ tournament }) {
  const extendDeadline = useExtendTournamentDeadline();
  const [deadline, setDeadline] = useState(() => toDateTimeLocal(tournament.internal_deadline));
  const [message, setMessage] = useState('');
  const save = async (event) => {
    event.preventDefault();
    setMessage('');
    await extendDeadline.mutateAsync({ id: tournament.id, internalDeadline: deadline });
    setMessage('Interne deadline bijgewerkt.');
  };
  return (
    <form className="card flex flex-col gap-4 p-5 sm:flex-row sm:items-end" onSubmit={save}>
      <label className="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">Interne deadline verlengen
        <input className="input mt-1" type="datetime-local" required value={deadline} onChange={(event) => setDeadline(event.target.value)} />
      </label>
      <button className="btn-tertiary" disabled={extendDeadline.isPending}>{extendDeadline.isPending ? 'Opslaan…' : 'Deadline opslaan'}</button>
      {extendDeadline.error ? <ErrorNotice error={extendDeadline.error} /> : null}
      {message ? <p className="text-sm text-green-700 dark:text-green-300">{message}</p> : null}
    </form>
  );
}

function ErrorNotice({ error }) {
  return <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{errorMessage(error)}</div>;
}

export default function TournamentDetail() {
  const { id } = useParams();
  const isNew = !id || id === 'nieuw';
  const tournamentQuery = useTournament(isNew ? null : id);
  const tournament = tournamentQuery.data;
  useDocumentTitle(isNew ? 'Toernooi toevoegen' : tournament?.name || 'Toernooi');

  if (!isNew && tournamentQuery.isLoading) return <ContentLoadingSpinner />;
  if (!isNew && tournamentQuery.error) return <ErrorNotice error={tournamentQuery.error} />;

  return (
    <div className="space-y-6">
      <div>
        <Link to="/toernooien" className="mb-3 inline-flex items-center text-sm text-bright-cobalt dark:text-electric-cyan"><ArrowLeft className="mr-1 h-4 w-4" />Alle toernooien</Link>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{isNew ? 'Toernooi toevoegen' : tournament.name}</h1>
        {!isNew && tournament.lifecycle_status !== 'draft' ? <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Interne deadline: {formatTournamentDate(tournament.internal_deadline)}</p> : null}
      </div>
      {(isNew || tournament.lifecycle_status === 'draft') ? <DraftEditor key={tournament?.id || 'new'} tournament={tournament} /> : null}
      {tournament?.lifecycle_status === 'draft' ? <PublishPanel tournament={tournament} /> : null}
      {tournament?.lifecycle_status === 'open' ? <DeadlinePanel key={tournament.internal_deadline} tournament={tournament} /> : null}
      {tournament && tournament.lifecycle_status !== 'draft' ? <EntriesOverview tournamentId={tournament.id} /> : null}
    </div>
  );
}
