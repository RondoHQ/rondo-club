import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/api/client';
import { ErrorMessage, Field, Options, QuestionSelect } from './AnalysisControls';
import { Source } from './AnalysisReports';

const actionStatuses = { open: 'Open', in_progress: 'In behandeling', done: 'Afgerond', no_action: 'Geen actie', proposed: 'Voorstel', dismissed: 'Verworpen' };
const fields = { title: 'Omschrijving', owner: 'Eigenaar', due: 'Deadline', status: 'Status', notes: 'Notities', reason: 'Reden' };
const emptyRule = { title: '', active: true, kind: 'answer', question: '', options: [], theme: '' };

function OwnerSelect({ value, onChange, owners, required = false, all = false }) {
  return <Field label="Eigenaar"><select className="input w-full" value={value || ''} required={required} onChange={(e) => onChange(Number(e.target.value))}>
    <option value="">{all ? 'Alle eigenaren' : 'Kies een interne eigenaar'}</option>
    {value > 0 && !owners.some((owner) => owner.id === value) && <option value={value} disabled>Eigenaar niet meer bevoegd</option>}
    {owners.map((owner) => <option key={owner.id} value={owner.id}>{owner.name}</option>)}
  </select></Field>;
}

function ActionEditor({ action, source, sources, owners, base, version, saved, cancel }) {
  const proposal = action?.status === 'proposed';
  const [draft, setDraft] = useState(() => action
    ? { ...action, status: proposal ? 'open' : action.status }
    : { title: '', owner: 0, due: '', status: 'open', notes: '', reason: '', source: source || null });
  const [showSource, setShowSource] = useState(false);
  const change = (patch) => setDraft((old) => ({ ...old, ...patch }));
  const needsOwner = !['proposed', 'dismissed'].includes(draft.status);
  const save = useMutation({
    mutationFn: () => api.post(`${base}/analysis/actions`, {
      id: action?.id || 0, revision: action?.revision, version, title: draft.title, notes: draft.notes,
      reason: draft.reason, source: draft.source, status: draft.status,
      owner: needsOwner ? draft.owner : 0, due: needsOwner ? draft.due : '',
    }),
    onSuccess: saved,
  });
  const choices = [...sources];
  if (draft.source && !choices.some((ref) => ref.type === draft.source.type && ref.id === draft.source.id)) choices.unshift(draft.source);
  return <section className="card space-y-5 p-5">
    <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
      <h2 className="text-lg font-semibold">{proposal ? 'Voorstel beoordelen' : action ? 'Actie bewerken' : 'Actie maken'}</h2>
      <p className="max-w-prose text-sm text-gray-600 dark:text-gray-300">De eigenaar is een interne enquêtebeheerder. Opslaan verstuurt geen bericht en maakt geen persoonlijke taak.</p>
      {action ? <p className="text-sm">Bron: {action.source.label}</p> : <Field label="Bron"><select required className="input w-full" value={draft.source ? `${draft.source.type}:${draft.source.id}` : ''} onChange={(e) => change({ source: choices.find((ref) => `${ref.type}:${ref.id}` === e.target.value) || null })}>
        <option value="">Kies een bron</option>{choices.map((ref) => <option key={`${ref.type}:${ref.id}`} value={`${ref.type}:${ref.id}`}>{ref.label || `Inzending ${ref.id}`}</option>)}
      </select></Field>}
      {!action && choices.length === 0 && <p className="text-sm">Kies Actie maken bij een open antwoord, thema of conclusie om de bron mee te nemen.</p>}
      {draft.source?.type === 'response' && draft.source.available !== false && <div><button type="button" className="btn-tertiary" onClick={() => setShowSource(!showSource)}>{showSource ? 'Bron verbergen' : 'Bronantwoord bekijken'}</button>{showSource && <Source base={base} id={draft.source.id} />}</div>}
      {action?.source.available === false && <p className="text-sm text-amber-800 dark:text-amber-200">De bron is verwijderd. Je kunt de bestaande actie afronden of het voorstel verwerpen.</p>}
      <Field label="Omschrijving"><input required maxLength={200} className="input w-full" value={draft.title} onChange={(e) => change({ title: e.target.value })} /></Field>
      <div className="grid gap-4 md:grid-cols-3">
        <Field label="Status"><select className="input w-full" value={draft.status} onChange={(e) => change({ status: e.target.value })}>
          {(proposal ? ['open', 'dismissed'] : action?.status === 'dismissed' ? ['dismissed', 'open'] : ['open', 'in_progress', 'done', 'no_action']).map((status) => <option key={status} value={status}>{status === 'open' && proposal ? 'Accepteren als actie' : actionStatuses[status]}</option>)}
        </select></Field>
        {needsOwner && <><OwnerSelect value={draft.owner} onChange={(owner) => change({ owner })} owners={owners} required /><Field label="Deadline"><input required type="date" className="input w-full" value={draft.due} onChange={(e) => change({ due: e.target.value })} /></Field></>}
      </div>
      {['no_action', 'dismissed'].includes(draft.status) && <Field label="Reden"><textarea required rows={2} maxLength={2000} className="input w-full" value={draft.reason} onChange={(e) => change({ reason: e.target.value })} /></Field>}
      <Field label="Interne notities"><textarea rows={3} maxLength={5000} className="input w-full" value={draft.notes} onChange={(e) => change({ notes: e.target.value })} /></Field>
      <ErrorMessage error={save.error} />
      <div className="flex flex-wrap gap-3"><button className="btn-primary" disabled={save.isPending || (proposal && action.source.available === false && needsOwner)}>{proposal ? 'Beoordeling opslaan' : 'Actie opslaan'}</button><button type="button" className="btn-secondary" onClick={cancel}>Annuleren</button></div>
    </form>
    {action?.history?.length > 0 && <details className="border-t pt-4 dark:border-gray-700"><summary className="cursor-pointer font-medium">Wijzigingshistorie · {action.history.length}</summary>
      <ol className="mt-4 space-y-4 text-sm">{[...action.history].reverse().map((event, index) => <li key={index}><p className="font-medium">{event.author_name} · {new Date(event.at).toLocaleString('nl-NL')}</p>{event.rule && <p>Voorstel uit regel {event.rule.id}, versie {event.rule.version}.</p>}<dl className="mt-2 space-y-1">{Object.entries(event.changes).map(([key, value]) => {
        const display = (v) => v === null || v === '' ? 'Leeg' : key === 'status' ? actionStatuses[v] : key === 'owner' ? owners.find((owner) => owner.id === v)?.name || (v ? `Gebruiker ${v}` : 'Niet toegewezen') : String(v);
        return <div key={key} className="break-words"><dt className="font-medium">{fields[key] || key}</dt><dd className="whitespace-pre-wrap">{display(value.from)} → {display(value.to)}</dd></div>;
      })}</dl></li>)}</ol>
    </details>}
  </section>;
}

function RuleEditor({ rule, data, base, version, saved, cancel }) {
  const [draft, setDraft] = useState(() => rule ? { ...rule, ...rule.predicate } : { ...emptyRule });
  const change = (patch) => setDraft((old) => ({ ...old, ...patch }));
  const save = useMutation({ mutationFn: () => api.post(`${base}/analysis/rules`, { ...draft, version }), onSuccess: saved });
  return <form className="space-y-4 border-t pt-4 dark:border-gray-700" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
    <h3 className="font-semibold">{rule ? 'Regel bewerken' : 'Voorstelregel maken'}</h3>
    <Field label="Omschrijving van het voorstel"><input required maxLength={200} className="input w-full" value={draft.title} onChange={(e) => change({ title: e.target.value })} /></Field>
    <Field label="Voorwaarde"><select className="input w-full" value={draft.kind} onChange={(e) => change({ kind: e.target.value })}><option value="answer">Een gekozen antwoordoptie</option><option value="theme">Een bevestigd thema</option></select></Field>
    {draft.kind === 'answer' ? <><QuestionSelect label="Vraag voor deze regel" questions={data.summary} value={draft.question} onChange={(question) => change({ question, options: [] })} /><Options label="Eén van deze opties" question={data.summary.find((q) => q.id === draft.question)} value={draft.options || []} onChange={(options) => change({ options })} /></> : <Field label="Bevestigd thema"><select required className="input w-full" value={draft.theme || ''} onChange={(e) => change({ theme: e.target.value })}><option value="">Kies een thema</option>{data.themes.map((theme) => <option key={theme.id} value={theme.id}>{theme.label}</option>)}</select></Field>}
    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={draft.active} onChange={(e) => change({ active: e.target.checked })} />Regel beschikbaar voor handmatige verwerking</label>
    <p className="max-w-prose text-sm text-gray-600 dark:text-gray-300">Opslaan verwerkt geen inzendingen. Een gewijzigde voorwaarde of omschrijving maakt een nieuwe regelversie, die nieuwe voorstellen kan opleveren voor eerder beoordeelde bronnen.</p>
    <ErrorMessage error={save.error} /><div className="flex flex-wrap gap-3"><button className="btn-primary" disabled={save.isPending}>Regel opslaan</button><button type="button" className="btn-secondary" onClick={cancel}>Annuleren</button></div>
  </form>;
}

function Rules({ rules, data, base, version, refresh }) {
  const [editing, setEditing] = useState(null);
  const [progress, setProgress] = useState({});
  const run = useMutation({
    mutationFn: ({ rule, after }) => api.post(`${base}/analysis/rules/run`, { id: rule.id, revision: rule.revision, version, after }),
    onSuccess: ({ data: result }, { rule }) => { setProgress((old) => ({ ...old, [rule.id]: { ...result, revision: rule.revision } })); refresh(); },
  });
  return <details className="card space-y-4 p-5"><summary className="cursor-pointer font-semibold">Voorstelregels · {rules.length}</summary>
    <p className="max-w-prose text-sm text-gray-600 dark:text-gray-300">Verwerk maximaal 100 inzendingen per keer. Alleen expliciete antwoordopties of bevestigde thema’s maken een voorstel. Een beheerder beoordeelt elk voorstel; herhalen maakt geen duplicaten binnen dezelfde regelversie.</p>
    <ErrorMessage error={run.error} />
    <ul className="divide-y dark:divide-gray-700">{rules.map((rule) => {
      const state = progress[rule.id]?.revision === rule.revision ? progress[rule.id] : null;
      const condition = rule.predicate.kind === 'theme' ? `Thema: ${data.themes.find((t) => t.id === rule.predicate.theme)?.label || 'Thema niet meer beschikbaar'}` : `${data.summary.find((q) => q.id === rule.predicate.question)?.label || 'Vraag niet meer beschikbaar'}: ${rule.predicate.options.map((id) => data.summary.find((q) => q.id === rule.predicate.question)?.options.find((o) => o.id === id)?.label || id).join(' of ')}`;
      return <li key={rule.id} className="space-y-2 py-4"><h3 className="font-medium">{rule.title}</h3><p className="text-sm">{condition}</p><p className="text-xs text-gray-600 dark:text-gray-300">Versie {rule.rule_version} · {rule.active ? 'Beschikbaar' : 'Uitgeschakeld'}</p>
        <div className="flex flex-wrap gap-3"><button className="btn-secondary" disabled={!rule.active || run.isPending} onClick={() => run.mutate({ rule, after: state?.next || 0 })}>{state?.next ? 'Volgende 100 verwerken' : state ? 'Opnieuw controleren' : 'Inzendingen verwerken'}</button><button className="btn-tertiary" onClick={() => setEditing(rule)}>Regel bewerken</button></div>
        {state && <p role="status" className="text-sm">{state.processed} inzendingen gecontroleerd; {state.created} nieuwe voorstellen. {state.next ? 'Er zijn nog inzendingen te verwerken.' : 'Deze verwerking is klaar.'}</p>}
      </li>;
    })}</ul>
    {editing ? <RuleEditor key={editing.id || 'new'} rule={editing.id ? editing : null} data={data} base={base} version={version} saved={() => { setEditing(null); refresh(); }} cancel={() => setEditing(null)} /> : <button className="btn-secondary" onClick={() => setEditing({})}>Regel toevoegen</button>}
  </details>;
}

export default function SurveyFollowup({ base, version, data, source, clearSource, responseRefs, openReference }) {
  const client = useQueryClient();
  const [filters, setFilters] = useState({ owner: 0, status: '', overdue: false, page: 1 });
  const [editing, setEditing] = useState(source ? 'new' : null);
  const [notice, setNotice] = useState('');
  const actions = useQuery({ queryKey: ['survey-analysis-actions', base, version, filters], queryFn: async () => (await api.get(`${base}/analysis/actions`, { params: { version, ...filters, overdue: filters.overdue ? 1 : '' } })).data });
  const owners = useQuery({ queryKey: ['survey-analysis-owners'], queryFn: async () => (await api.get('/rondo/v1/surveys/analysis-owners')).data });
  const rules = useQuery({ queryKey: ['survey-analysis-rules', base, version], queryFn: async () => (await api.get(`${base}/analysis/rules`, { params: { version } })).data });
  const reports = useQuery({ queryKey: ['survey-analysis-reports', base, version, data.source_revision], queryFn: async () => (await api.get(`${base}/analysis/reports`, { params: { version } })).data });
  const detail = useQuery({ queryKey: ['survey-analysis-action-detail', base, version, editing], queryFn: async () => (await api.get(`${base}/analysis/action-detail`, { params: { version, action_id: editing } })).data, enabled: Number.isInteger(editing) });
  const refresh = () => client.invalidateQueries({ predicate: ({ queryKey }) => String(queryKey[0]).startsWith('survey-analysis') && queryKey[1] === base });
  const close = () => { setEditing(null); clearSource(); };
  const save = () => { close(); setNotice('Actie opgeslagen.'); refresh(); };
  const changeFilters = (patch) => setFilters((old) => ({ ...old, page: 1, ...patch }));
  const sources = [
    ...responseRefs.map((id) => ({ type: 'response', id, label: `Inzending ${id}` })),
    ...data.themes.map((theme) => ({ type: 'theme', id: theme.id, label: `Thema: ${theme.label}` })),
    ...(reports.data || []).map((report) => ({ type: 'report', id: report.id, label: `Conclusie: ${report.title}` })),
  ];
  return <div className="space-y-5">
    <p className="max-w-prose text-sm text-gray-600 dark:text-gray-300">Gezamenlijke opvolging voor deze vragenlijstversie. Antwoordfilters veranderen deze werkvoorraad niet. Alleen beheerders met resultaatrechten hebben toegang.</p>
    <div className="flex flex-wrap items-end gap-3"><OwnerSelect value={filters.owner} owners={owners.data || []} onChange={(owner) => changeFilters({ owner })} all /><Field label="Actiestatus"><select className="input" value={filters.status} onChange={(e) => changeFilters({ status: e.target.value })}><option value="">Alle statussen</option>{Object.entries(actionStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field><label className="flex items-center gap-2 py-2 text-sm"><input type="checkbox" checked={filters.overdue} onChange={(e) => changeFilters({ overdue: e.target.checked })} />Deadline verlopen</label><button className="btn-secondary" onClick={() => { clearSource(); setEditing('new'); }}>Actie toevoegen</button></div>
    <ErrorMessage error={actions.error || owners.error || detail.error || rules.error || reports.error} />
    {(actions.error || owners.error || rules.error) && <button className="btn-secondary" onClick={() => { refresh(); owners.refetch(); }}>Opnieuw proberen</button>}
    {notice && <p role="status" className="text-sm">{notice}</p>}
    {editing && (editing === 'new' || detail.data) && <ActionEditor key={editing === 'new' ? `new:${source?.type}:${source?.id}` : `${detail.data.id}:${detail.data.revision}`} action={editing === 'new' ? null : detail.data} source={source} sources={sources} owners={owners.data || []} base={base} version={version} saved={save} cancel={close} />}
    {Number.isInteger(editing) && detail.isPending && <p role="status">Actie laden…</p>}
    {actions.isPending && <p role="status">Opvolging laden…</p>}
    {actions.data && <section className="card p-5"><h2 className="font-semibold">Werkvoorraad · {actions.data.total}</h2>
      {actions.data.rows.length === 0 && <p className="mt-3 text-sm">Geen acties of voorstellen binnen deze filters. Maak een actie vanuit een bron of verwerk een voorstelregel.</p>}
      <ul className="mt-3 divide-y dark:divide-gray-700">{actions.data.rows.map((action) => <li key={action.id} className="space-y-2 py-4"><div className="flex flex-wrap items-baseline justify-between gap-3"><h3 className="font-medium break-words">{action.title}</h3><span className="text-sm">{actionStatuses[action.status]}</span></div>
        <p className="text-sm text-gray-600 dark:text-gray-300">{action.owner_name}{action.owner > 0 && !action.owner_eligible ? ' · Toegang ingetrokken; wijs een andere eigenaar toe' : ''}{action.due ? ` · Deadline ${new Date(`${action.due}T12:00:00`).toLocaleDateString('nl-NL')}` : ''}</p>
        {action.overdue && <p className="text-sm font-medium text-amber-800 dark:text-amber-200">Deadline verlopen</p>}
        <div className="flex flex-wrap items-center gap-3"><button className="btn-secondary" onClick={() => setEditing(action.id)}>{action.status === 'proposed' ? 'Voorstel beoordelen' : 'Actie bekijken'}</button>{action.source.available ? <button className="btn-tertiary" onClick={() => action.source.type === 'response' ? setEditing(action.id) : openReference(action.source)}>{action.source.label}</button> : <span className="text-sm text-gray-600 dark:text-gray-300">Bron niet meer beschikbaar</span>}</div>
      </li>)}</ul>
      {actions.data.pages > 1 && <div className="mt-4 flex items-center gap-3 text-sm"><button className="btn-secondary" disabled={filters.page <= 1} onClick={() => setFilters({ ...filters, page: filters.page - 1 })}>Vorige</button><span>{filters.page} / {actions.data.pages}</span><button className="btn-secondary" disabled={filters.page >= actions.data.pages} onClick={() => setFilters({ ...filters, page: filters.page + 1 })}>Volgende</button></div>}
    </section>}
    {rules.data && <Rules rules={rules.data} data={data} base={base} version={version} refresh={refresh} />}
  </div>;
}
