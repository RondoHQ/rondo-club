import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Plus, Send, Trash2 } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import {
  useDeleteTournament,
  usePublishTournament,
  useExtendTournamentDeadline,
  useSaveTournament,
  useTournament,
  useTournamentAssignmentOptions,
} from '@/hooks/useTournaments';
import {
  formatTournamentDate,
  toDateInput,
} from './tournamentFormatters';
import TournamentOperations from './TournamentOperations';

const emptyTournament = {
  name: '',
  organizer: '',
  location: '',
  description: '',
  internal_deadline: '',
  external_deadline: '',
  payment_deadline: '',
  payment_reminder_days: [7, 2],
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
    internal_deadline: toDateInput(source.internal_deadline),
    external_deadline: toDateInput(source.external_deadline),
    payment_deadline: toDateInput(source.payment_deadline || source.internal_deadline),
    payment_reminder_days: source.payment_reminder_days?.length ? source.payment_reminder_days.map(Number) : [7, 2],
    schedule: (source.schedule?.length ? source.schedule : emptyTournament.schedule).map((row) => ({
      ...row,
      start_datetime: toDateInput(row.start_datetime),
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
            <input className="input mt-1" type="date" required value={form.internal_deadline} onChange={(event) => setForm({ ...form, internal_deadline: event.target.value })} />
          </label>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Deadline organisatie
            <input className="input mt-1" type="date" required value={form.external_deadline} onChange={(event) => setForm({ ...form, external_deadline: event.target.value })} />
          </label>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Betaaldeadline
            <input className="input mt-1" type="date" required value={form.payment_deadline} onChange={(event) => setForm({ ...form, payment_deadline: event.target.value })} />
          </label>
          <fieldset className="text-sm font-medium text-gray-700 dark:text-gray-300">
            <legend>Betaalherinneringen, dagen vooraf</legend>
            <div className="mt-1 flex flex-wrap gap-2">
              {form.payment_reminder_days.map((days, index) => (
                <div key={index} className="flex items-center gap-1">
                  <input
                    className="input w-24"
                    type="number"
                    min="0"
                    max="60"
                    aria-label={`Betaalherinnering ${index + 1}`}
                    value={days}
                    onChange={(event) => setForm({ ...form, payment_reminder_days: form.payment_reminder_days.map((value, rowIndex) => rowIndex === index ? Number(event.target.value) : value) })}
                  />
                  <button type="button" className="btn-tertiary p-2" aria-label="Herinnering verwijderen" onClick={() => setForm({ ...form, payment_reminder_days: form.payment_reminder_days.filter((_, rowIndex) => rowIndex !== index) })}><Trash2 className="h-4 w-4" /></button>
                </div>
              ))}
              <button type="button" className="btn-tertiary" onClick={() => setForm({ ...form, payment_reminder_days: [...form.payment_reminder_days, 1] })}>Herinnering toevoegen</button>
            </div>
          </fieldset>
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
            <input className="input" aria-label={`Datum moment ${index + 1}`} type="date" required value={row.start_datetime} onChange={(event) => updateRow('schedule', index, 'start_datetime', event.target.value)} />
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
          <div key={index} className="grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-[10rem_11rem_11rem_1fr_auto] dark:border-gray-700">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Vanaf leeftijd
              <input className="input mt-1" type="number" min="1" placeholder="Bijv. 6" required value={row.min_age} onChange={(event) => updateRow('pricing_rules', index, 'min_age', event.target.value)} />
            </label>
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Tot en met leeftijd
              <input className="input mt-1" type="number" min="1" placeholder="Bijv. 7" required value={row.max_age} onChange={(event) => updateRow('pricing_rules', index, 'max_age', event.target.value)} />
            </label>
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Bedrag per team (€)
              <input className="input mt-1" type="number" min="0" step="0.01" placeholder="Bijv. 28" required value={row.amount} onChange={(event) => updateRow('pricing_rules', index, 'amount', event.target.value)} />
            </label>
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Spelvorm
              <input className="input mt-1" placeholder="Bijv. 4 tegen 4" value={row.game_format} onChange={(event) => updateRow('pricing_rules', index, 'game_format', event.target.value)} />
            </label>
            <button type="button" className="btn-tertiary self-end p-2" aria-label="Tarief verwijderen" disabled={form.pricing_rules.length === 1} onClick={() => removeRow('pricing_rules', index)}><Trash2 className="h-4 w-4" /></button>
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

function DeadlinePanel({ tournament }) {
  const extendDeadline = useExtendTournamentDeadline();
  const [deadline, setDeadline] = useState(() => toDateInput(tournament.internal_deadline));
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
        <input className="input mt-1" type="date" required value={deadline} onChange={(event) => setDeadline(event.target.value)} />
      </label>
      <button className="btn-tertiary" disabled={extendDeadline.isPending}>{extendDeadline.isPending ? 'Opslaan…' : 'Deadline opslaan'}</button>
      {extendDeadline.error ? <ErrorNotice error={extendDeadline.error} /> : null}
      {message ? <p className="text-sm text-green-700 dark:text-green-300">{message}</p> : null}
    </form>
  );
}

function DeleteTournamentPanel({ tournament }) {
  const navigate = useNavigate();
  const deleteTournament = useDeleteTournament();

  const remove = async () => {
    const entryCount = Number(tournament.entry_count || 0);
    const submittedCount = Number(tournament.submitted_entry_count || 0);
    const entrySummary = entryCount === 0
      ? 'Dit toernooi heeft geen gekoppelde inschrijvingen.'
      : `Dit verwijdert ook ${entryCount} gekoppelde ${entryCount === 1 ? 'inschrijving' : 'inschrijvingen'}, waarvan ${submittedCount} definitief.`;
    const confirmed = window.confirm(
      `Weet je zeker dat je “${tournament.name}” wilt verwijderen?\n\n${entrySummary}\n\nRondo verstuurt geen bericht. Je moet aangemelde teams zelf informeren.`,
    );
    if (!confirmed) return;

    await deleteTournament.mutateAsync(tournament.id);
    navigate('/toernooien', { replace: true });
  };

  return (
    <section className="card border-red-200 p-5 dark:border-red-900">
      <h2 className="text-lg font-semibold text-red-700 dark:text-red-300">Toernooi verwijderen</h2>
      <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
        Het toernooi en alle gekoppelde inschrijvingen verdwijnen uit Rondo. Rondo verstuurt geen bericht; je moet aangemelde teams zelf informeren.
      </p>
      {deleteTournament.error ? <div className="mt-4"><ErrorNotice error={deleteTournament.error} /></div> : null}
      <button type="button" className="btn-tertiary mt-4 inline-flex items-center text-red-700 dark:text-red-300" disabled={deleteTournament.isPending} onClick={remove}>
        <Trash2 className="mr-2 h-4 w-4" />{deleteTournament.isPending ? 'Verwijderen…' : 'Toernooi verwijderen'}
      </button>
    </section>
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
      {tournament && tournament.lifecycle_status !== 'draft' ? <TournamentOperations tournament={tournament} /> : null}
      {tournament ? <DeleteTournamentPanel tournament={tournament} /> : null}
    </div>
  );
}
