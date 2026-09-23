import { lazy, Suspense, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDown, ArrowUp, Cake, CalendarX, SlidersHorizontal, Square } from 'lucide-react';
import api from '@/api/client';
import PersonAvatar from '@/components/PersonAvatar';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';
import { useTodoCompletion } from '@/hooks/useTodoCompletion';
import { format, parseYmd, isValid } from '@/utils/dateFormat';
import { toMinutes, toTime, fieldPart } from '@/utils/training';
import { combineDashboardMatches, dashboardRoomLabel, moveDashboardBlock } from '@/utils/roleDashboard';

const TodoModal = lazy(() => import('@/components/Timeline/TodoModal'));
const LABELS = { attention: 'Aandacht nodig', birthdays: 'Verjaardagen', matches: 'Wedstrijden', teams: 'Mijn teams en trainingen' };
const EMPTY = [];
const panel = 'rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800';

function dateLabel(value, pattern = 'EEEE d MMMM') {
  const date = parseYmd(value);
  return isValid(date) ? format(date, pattern) : 'Datum onbekend';
}

function LoadError({ retry, children }) {
  return <p role="alert" className="text-sm text-red-700 dark:text-red-300">{children} <button className="underline" onClick={retry}>Opnieuw proberen</button></p>;
}

function LayoutEditor({ layout, onSave, pending, error }) {
  const [draft, setDraft] = useState(layout);
  return <div className={`${panel} mb-6 p-4`}>
    <h2 className="mb-3 font-semibold">Dashboard aanpassen</h2>
    <div className="space-y-2">{draft.order.map((id, index) => <div key={id} className="flex items-center gap-2">
      <label className="flex min-w-0 flex-1 items-center gap-3"><input type="checkbox" checked={!draft.hidden.includes(id)} onChange={(event) => setDraft({ ...draft, hidden: event.target.checked ? draft.hidden.filter(value => value !== id) : [...draft.hidden, id] })} />{LABELS[id]}</label>
      {[-1, 1].map(direction => <button key={direction} className="rounded p-2 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-700" disabled={direction < 0 ? index === 0 : index === draft.order.length - 1} aria-label={`${LABELS[id]} ${direction < 0 ? 'omhoog' : 'omlaag'}`} onClick={() => setDraft({ ...draft, order: moveDashboardBlock(draft.order, index, direction) })}>{direction < 0 ? <ArrowUp size={16} /> : <ArrowDown size={16} />}</button>)}
    </div>)}</div>
    {error && <p role="alert" className="mt-3 text-sm text-red-700 dark:text-red-300">Opslaan mislukt. Je wijzigingen staan hier nog; probeer het opnieuw.</p>}
    <div className="mt-4 flex flex-wrap justify-between gap-3"><button className="text-sm underline" onClick={() => setDraft({ order: Object.keys(LABELS).filter(id => layout.order.includes(id)), hidden: [] })}>Herstel standaard</button><button className="btn-primary" disabled={pending} onClick={() => onSave(draft)}>{pending ? 'Opslaan…' : 'Indeling opslaan'}</button></div>
  </div>;
}

export default function RoleDashboard({ user }) {
  const client = useQueryClient();
  const [customizing, setCustomizing] = useState(false);
  const [birthdayTeam, setBirthdayTeam] = useState('all');
  const [matchScope, setMatchScope] = useState(user.dashboard_context.secretary ? 'home' : 'own');
  const todo = useTodoCompletion();
  const key = ['role-dashboard', user.id];
  const workspace = useQuery({ queryKey: key, queryFn: async () => (await api.get('/rondo/v1/dashboard/workspace')).data, staleTime: 60_000 });
  const data = workspace.data;
  const teams = data?.teams || EMPTY;
  const context = data?.context || user.dashboard_context;
  const club = useQuery({ queryKey: ['role-dashboard-matches', user.id, 'club'], queryFn: async () => (await api.get('/rondo/v1/dashboard/matches')).data, enabled: Boolean(context.secretary), staleTime: 60_000, refetchInterval: 60_000 });
  const teamFeeds = useQueries({ queries: context.coordinator && (!context.secretary || matchScope === 'own') ? teams.map(team => ({
    queryKey: ['role-dashboard-matches', user.id, team.id], queryFn: async () => (await api.get('/rondo/v1/dashboard/matches', { params: { team_id: team.id } })).data,
    staleTime: 60_000, refetchInterval: 5 * 60_000,
  })) : EMPTY });
  const save = useMutation({ mutationFn: async (layout) => (await api.post('/rondo/v1/dashboard/layout', layout)).data, onSuccess: (layout) => { client.setQueryData(key, value => ({ ...value, layout })); setCustomizing(false); } });
  const sources = matchScope === 'own' ? teamFeeds : [club];
  const feeds = sources.map((source, index) => ({ data: source.isError ? null : source.data, teamId: matchScope === 'own' ? teams[index]?.id : null }));
  const matches = combineDashboardMatches(feeds, data?.today || '', data?.end_date || '');
  const visibleMatches = matches.filter(match => matchScope === 'own' || (matchScope === 'home' ? match.club_side === 'home' : match.club_side === 'away'));
  // A match-tab selection must not hide club-wide alerts from a secretary.
  const attentionSources = context.secretary ? [club] : teamFeeds;
  const cancellations = combineDashboardMatches(attentionSources.map((source, index) => ({ data: source.isError ? null : source.data, teamId: context.secretary ? null : teams[index]?.id })), data?.today || '', data?.end_date || '').filter(match => match.cancelled);
  const attentionLoading = attentionSources.some(source => source.isPending);
  const attentionIncomplete = attentionSources.some(source => source.isError || source.data?.stale || source.data?.expired || source.data?.matched === false);
  const matchLoading = sources.some(source => source.isPending);
  const matchErrors = sources.some(source => source.isError);
  const notMatched = sources.some(source => source.data?.matched === false);
  const stale = sources.some(source => source.data?.stale || source.data?.expired);
  const updated = sources.map(source => source.data?.updated_at).filter(Boolean).sort()[0];
  const retryMatches = () => Promise.all(sources.map(source => source.refetch()));
  const refresh = () => Promise.all([workspace.refetch(), retryMatches()]);

  if (workspace.isPending) return <p className="p-6" role="status">Dashboard laden…</p>;
  if (workspace.isError) return <LoadError retry={() => workspace.refetch()}>Het dashboard kon niet worden geladen.</LoadError>;
  const visibleBlocks = data.layout.order.filter(id => !data.layout.hidden.includes(id));
  const birthdays = data.birthdays.filter(person => birthdayTeam === 'all' || person.team_ids.includes(Number(birthdayTeam)));
  const todayTraining = data.training.filter(block => block.day === data.day).sort((a, b) => a.start.localeCompare(b.start));
  const teamName = (id) => teams.find(team => team.id === id)?.name || '';
  const tasks = data.tasks || [];

  const sections = {
    attention: <section aria-labelledby="dashboard-attention" className="lg:col-span-12">
      <h2 id="dashboard-attention" className="mb-3 text-lg font-semibold">Aandacht nodig <span className="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">{tasks.length} {tasks.length === 1 ? 'taak' : 'taken'}</span></h2>
      {todo.updateError && <p role="alert" className="mb-3 text-sm text-red-700 dark:text-red-300">De taak kon niet worden opgeslagen. Probeer het opnieuw.</p>}
      <div className={`${panel} divide-y divide-gray-100 px-4 dark:divide-gray-700`}>
        {tasks.map(task => <div key={task.id} className="flex flex-wrap items-start gap-3 py-3">
          <button aria-label={`Taak afronden: ${task.content}`} className="mt-1 shrink-0 text-gray-400 hover:text-electric-cyan" onClick={() => todo.handleToggleTodo(task)}><Square size={20} /></button>
          <button className="min-w-0 flex-1 text-left" onClick={() => todo.handleViewTodo(task)}><span className="block text-sm font-medium">{task.content}</span><span className="text-xs text-gray-500 dark:text-gray-400">Eigen taak{task.person_name ? ` · ${task.person_name}` : ''}</span></button>
          {task.due_date && <span className={`text-xs ${task.due_date.slice(0, 10) < data.today ? 'text-red-700 dark:text-red-300' : 'text-gray-500 dark:text-gray-400'}`}>{task.due_date.slice(0, 10) === data.today ? 'Vandaag' : dateLabel(task.due_date.slice(0, 10), 'd MMM')}{task.due_date.slice(0, 10) < data.today ? ' · achterstallig' : ''}</span>}
        </div>)}
        {cancellations.map(match => <div key={match.id} className="flex gap-3 py-3"><CalendarX size={18} className="mt-0.5 shrink-0 text-red-600 dark:text-red-300" /><div><p className="text-sm">{match.home_team} · {match.away_team} afgelast</p><p className="text-xs text-gray-500 dark:text-gray-400">{dateLabel(match.date, 'EEEE d MMM')} · {match.time}{attentionIncomplete ? ' · eerder opgehaald' : ''}</p></div></div>)}
        {attentionLoading && <p className="py-3 text-sm" role="status">Wedstrijdmeldingen laden…</p>}
        {attentionIncomplete && <p className="py-3 text-sm text-amber-800 dark:text-amber-200" role="status">Wedstrijdmeldingen zijn niet volledig actueel. <button className="underline" onClick={() => Promise.all(attentionSources.map(source => source.refetch()))}>Opnieuw proberen</button></p>}
        {!tasks.length && !cancellations.length && <p className="py-4 text-sm text-gray-500 dark:text-gray-400">Geen open taken.{!attentionLoading && !attentionIncomplete ? ' Geen afgelastingen in het beschikbare programma.' : ''}</p>}
      </div>
    </section>,
    birthdays: <section aria-labelledby="dashboard-birthdays" className="lg:col-span-12">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3"><h2 id="dashboard-birthdays" className="flex items-center gap-2 text-lg font-semibold"><Cake size={20} className="text-electric-cyan" />Verjaardagen</h2>{teams.length > 0 && <select className="input !w-auto max-w-full" aria-label="Verjaardagen filteren op team" value={birthdayTeam} onChange={event => setBirthdayTeam(event.target.value)}><option value="all">Alle toegankelijke personen</option>{teams.map(team => <option key={team.id} value={team.id}>{team.name}</option>)}</select>}</div>
      <div className={`${panel} grid overflow-hidden sm:grid-cols-2 xl:grid-cols-3`}>{birthdays.length ? birthdays.map(birthday => <Link key={birthday.id} to={`/people/${birthday.id}`} className={`border-b border-gray-100 p-4 dark:border-gray-700 ${birthday.days_until === 0 ? 'bg-cyan-50 dark:bg-cyan-950/40' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'}`}>
        <p className={`mb-3 text-xs ${birthday.days_until === 0 ? 'font-semibold text-cyan-800 dark:text-cyan-200' : 'text-gray-500 dark:text-gray-400'}`}>{birthday.days_until === 0 ? 'Vandaag jarig' : birthday.days_until === 1 ? 'Morgen' : dateLabel(birthday.next_occurrence, 'EEEE d MMM')}</p>
        <div className="flex items-center gap-3"><PersonAvatar thumbnail={birthday.related_people?.[0]?.thumbnail} name={birthday.title} size="md" /><div className="min-w-0"><p className="text-sm font-semibold">{birthday.title}</p><p className="text-xs text-gray-500 dark:text-gray-400">{birthday.team_ids.map(teamName).filter(Boolean).join(', ')}{birthday.team_ids.length ? ' · ' : ''}wordt {Number(birthday.next_occurrence.slice(0, 4)) - Number(birthday.date_value.slice(0, 4))}</p></div></div>
      </Link>) : <p className="p-4 text-sm text-gray-500 dark:text-gray-400 sm:col-span-2 xl:col-span-3">Geen verjaardagen in de komende zeven dagen.</p>}</div>
    </section>,
    matches: <section aria-labelledby="dashboard-matches" className={visibleBlocks.includes('teams') ? 'min-w-0 lg:col-span-8' : 'min-w-0 lg:col-span-12'}>
      <h2 id="dashboard-matches" className="text-lg font-semibold">Wedstrijden deze week</h2><p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{dateLabel(data.today, 'd MMM')}–{dateLabel(data.end_date, 'd MMM')} · tijd, veld en kleedkamers</p>
      <div className="mt-4 flex flex-wrap gap-5 border-b border-gray-200 dark:border-gray-700">{[...(context.secretary ? [['home', 'Thuiswedstrijden'], ['away', 'Uitwedstrijden']] : []), ...(context.coordinator ? [['own', 'Mijn teams']] : [])].map(([value, label]) => <button key={value} aria-pressed={matchScope === value} className={`border-b-2 pb-3 text-sm ${matchScope === value ? 'border-electric-cyan font-medium text-cyan-800 dark:text-electric-cyan' : 'border-transparent text-gray-500 dark:text-gray-400'}`} onClick={() => setMatchScope(value)}>{label}</button>)}</div>
      {matchLoading && <p className="py-4 text-sm" role="status">Wedstrijden laden…</p>}
      {matchErrors && <div className="py-4"><LoadError retry={retryMatches}>Niet alle wedstrijden konden worden opgehaald. Het overzicht kan onvolledig zijn.</LoadError></div>}
      {notMatched && <p className="py-3 text-sm text-amber-800 dark:text-amber-200">Niet alle teams konden aan Sportlink worden gekoppeld.</p>}
      {stale && <p className="py-3 text-sm text-amber-800 dark:text-amber-200">Dit zijn eerder opgehaalde gegevens. Controleer wijzigingen in Sportlink.</p>}
      {visibleMatches.map((match, index) => <div key={match.id}>{(!index || visibleMatches[index - 1].date !== match.date) && <h3 className="mt-5 text-xs font-semibold text-gray-500 dark:text-gray-400">{dateLabel(match.date)}</h3>}
        <details className="border-b border-gray-200 py-3 dark:border-gray-700"><summary className="cursor-pointer text-sm"><span className="ml-2 inline-block w-12 align-top font-semibold tabular-nums">{match.time_known === false ? 'N.t.b.' : match.time}</span><span className="inline-block max-w-[calc(100%-5rem)] align-top"><span className="block font-medium">{match.home_team} · {match.away_team}</span><span className={`mt-1 block text-xs ${match.cancelled ? 'text-red-700 dark:text-red-300' : 'text-gray-500 dark:text-gray-400'}`}>{match.cancelled ? 'Afgelast' : `${match.pitch || 'Veld nog niet ingevuld'} · ${match.location || (match.home === false || match.club_side === 'away' ? 'Uitwedstrijd' : 'Thuiswedstrijd')}`}</span>{!match.cancelled && <span className="mt-1 block text-xs text-gray-500 dark:text-gray-400">Kleedkamers: thuis {dashboardRoomLabel(match.dressing_rooms?.home)} · uit {dashboardRoomLabel(match.dressing_rooms?.away)}</span>}</span></summary>
          <dl className="ml-6 mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs sm:ml-[4.5rem]"><dt>Kleedkamer thuis</dt><dd>{dashboardRoomLabel(match.dressing_rooms?.home)}</dd><dt>Kleedkamer uit</dt><dd>{dashboardRoomLabel(match.dressing_rooms?.away)}</dd><dt>Status</dt><dd>{match.cancelled ? 'Afgelast' : match.status || 'Gepland'}</dd></dl>
        </details>
      </div>)}
      {!matchLoading && !matchErrors && !notMatched && !stale && !visibleMatches.length && <p className="py-4 text-sm text-gray-500 dark:text-gray-400">{matchScope === 'own' && !teams.length ? 'Er zijn nog geen teams aan je coördinatorrol gekoppeld.' : 'Geen wedstrijden in dit overzicht.'}</p>}
      {updated && isValid(new Date(updated)) && <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">Sportlink · bijgewerkt {format(new Date(updated), 'd MMM HH:mm')}</p>}
    </section>,
    teams: <aside aria-labelledby="dashboard-teams" className={`${panel} self-start p-4 lg:col-span-4`}><h2 id="dashboard-teams" className="text-lg font-semibold">Mijn teams</h2><p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{teams.length} {teams.length === 1 ? 'team' : 'teams'} binnen jouw verantwoordelijkheid</p>
      <ul className="mt-2 divide-y divide-gray-100 dark:divide-gray-700">{teams.map(team => <li key={team.id} className="py-3"><Link to={`/teams/${team.id}`} className="block text-sm font-semibold hover:underline">{team.name}</Link><p className="text-xs text-gray-500 dark:text-gray-400">{team.player_count} spelers · <Link to={`/teams/${team.id}`} className="underline">Spelers en begeleiding</Link></p></li>)}</ul>
      {!teams.length && <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">Vraag een beheerder om teams aan je coördinatorrol te koppelen. Daarna verschijnen hier je teams, trainingen en wedstrijden.</p>}
      {teams.length > 0 && <><h3 className="mt-5 text-sm font-semibold text-gray-900 dark:text-gray-100">Trainingen vandaag</h3>{todayTraining.map(block => <div key={block.block_id} className="mt-3"><p className="text-sm font-medium">{block.start}–{toTime(toMinutes(block.start) + block.duration)} · {block.team_ids.map(teamName).join(', ')}</p><p className="text-xs text-gray-500 dark:text-gray-400">{data.pitches.find(pitch => pitch.id === block.pitch_id)?.name || 'Veld onbekend'} · {fieldPart(block)}</p></div>)}{!todayTraining.length && <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{data.has_training_schedule ? 'Geen trainingen vandaag.' : 'Er is nog geen actief trainingsschema.'}</p>}</>}
    </aside>,
  };

  return <div className="pb-6 text-gray-900 dark:text-gray-100">
    <header className="mb-6 flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-2xl font-semibold tracking-tight">Jouw dashboard</h1><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{dateLabel(data.today)}</p><p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{[context.coordinator && 'Coördinator', context.secretary && 'Wedstrijdsecretaris'].filter(Boolean).join(' · ')}</p></div><div className="flex gap-2"><button className="btn-secondary text-sm !text-cyan-800 dark:!text-cyan-200" onClick={refresh}>Verversen</button><button className="btn-secondary flex items-center gap-2 text-sm !text-cyan-800 dark:!text-cyan-200" aria-expanded={customizing} aria-controls="dashboard-layout" onClick={() => setCustomizing(value => !value)}><SlidersHorizontal size={16} />Aanpassen</button></div></header>
    {customizing && <div id="dashboard-layout"><LayoutEditor layout={data.layout} onSave={save.mutate} pending={save.isPending} error={save.isError} /></div>}
    {!visibleBlocks.length && <p className="py-8 text-sm text-gray-500 dark:text-gray-400">Alle blokken zijn verborgen. Kies ‘Aanpassen’ om ze weer te tonen.</p>}
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">{visibleBlocks.map(id => <Suspense key={id} fallback={null}>{sections[id]}</Suspense>)}</div>
    <CompleteTodoModal isOpen={todo.showCompleteModal} onClose={todo.closeCompleteModal} todo={todo.todoToComplete} onAwaiting={todo.handleMarkAwaiting} onComplete={todo.handleJustComplete} onCompleteAsActivity={todo.handleCompleteAsActivity} allowActivity={Boolean(todo.todoToComplete?.person_id)} hideAwaitingOption={todo.todoToComplete?.status === 'awaiting'} />
    <QuickActivityModal isOpen={todo.showActivityModal} onClose={todo.closeActivityModal} onSubmit={todo.handleCreateActivity} isLoading={todo.isCreatingActivity} personId={todo.todoToComplete?.person_id} initialData={todo.activityInitialData} />
    <Suspense fallback={null}><TodoModal isOpen={todo.showTodoModal} onClose={todo.closeTodoModal} onSubmit={todo.handleUpdateTodo} isLoading={todo.isUpdatingTodo} todo={todo.todoToView} /></Suspense>
  </div>;
}
