import { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, ChevronUp, Copy, ExternalLink, ImagePlus, MessageCircle, Pause, Play, Plus, Repeat2, RotateCcw, Search, SlidersHorizontal, X } from 'lucide-react';
import PlanningBoard, { PlanningCard } from './PlanningBoard';
import { archivedPlanningStatuses, filterPlanningItems, planningMovePayload, planningToday, sortPlanningItems } from './planningUtils';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import {
  useAddCommunicationComment, useCommunication, useCommunicationAction, useCommunicationComments,
  useCommunications, useCreateCommunication, useSeriesAction, useUpdateCommunication, useUploadCommunication,
} from '@/hooks/useCommunication';

const statuses = {
  concept: 'Concept', preparing: 'In voorbereiding', ready: 'Klaar', sent: 'Verstuurd / gepubliceerd',
  skipped: 'Overgeslagen', cancelled: 'Geannuleerd', paused: 'Gepauzeerd',
};
const recurrenceLabels = { none: 'Geen', monthly: 'Maandelijks', yearly: 'Jaarlijks' };
const openStatuses = ['concept', 'preparing', 'ready'];

const blank = {
  title: '', description: '', channel_ids: [], google_docs_url: '', audience: '', planned_date: '', assignee_id: '',
  status: 'concept', recurrence: 'none', start_date: '', end_date: '', published_url: '', attachments: [], apply_to_future: false,
};

function niceDate(value) {
  if (!value) return 'Nog niet gepland';
  return new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Europe/Amsterdam' }).format(new Date(`${value}T12:00:00`));
}

function errorText(error) {
  return error?.response?.data?.message || 'Opslaan is niet gelukt. Probeer het opnieuw.';
}

function ChannelCompletionDetails({ itemId, channel, disabled }) {
  const [date, setDate] = useState(channel.actual_date);
  const [url, setUrl] = useState(channel.published_url || '');
  const [message, setMessage] = useState('');
  const action = useCommunicationAction();
  useEffect(() => { setDate(channel.actual_date); setUrl(channel.published_url || ''); }, [channel.actual_date, channel.published_url]);

  async function save(event) {
    event.preventDefault();
    setMessage('');
    try {
      await action.mutateAsync({ id: itemId, data: { action: 'complete', channel_id: channel.channel_id, actual_date: date, published_url: url } });
      setMessage('Opgeslagen');
    } catch (err) { setMessage(errorText(err)); }
  }

  return <details className="w-full pl-6 text-sm">
    <summary className="cursor-pointer text-gray-600 dark:text-gray-300">Datum en link</summary>
    <form className="mt-2 flex flex-wrap items-end gap-3" onSubmit={save}>
      <label className="block text-sm text-gray-700 dark:text-gray-200">Afgehandeld op<input className="input mt-1" type="date" value={date} max={new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Amsterdam', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())} onChange={(e) => setDate(e.target.value)} disabled={disabled || action.isPending} required /></label>
      <label className="block min-w-0 flex-1 text-sm text-gray-700 dark:text-gray-200">Link naar bericht<input className="input mt-1" type="url" value={url} onChange={(e) => setUrl(e.target.value)} disabled={disabled || action.isPending} placeholder="https://…" /></label>
      <button type="submit" className="btn-secondary" disabled={disabled || action.isPending}>{action.isPending ? 'Opslaan…' : 'Opslaan'}</button>
      {message && <p role="status" className="w-full text-sm dark:text-gray-200">{message}</p>}
    </form>
  </details>;
}

function ChannelChecklist({ item }) {
  const action = useCommunicationAction();
  const [error, setError] = useState('');
  const available = [...openStatuses, 'sent'].includes(item.status);
  const completed = item.channels.filter((channel) => channel.actual_date).length;

  async function toggle(channel, checked) {
    setError('');
    try {
      await action.mutateAsync({ id: item.id, data: { action: checked ? 'complete' : 'reopen', channel_id: channel.channel_id } });
    } catch (err) { setError(errorText(err)); }
  }

  return <fieldset className="space-y-2 border-t border-gray-200 pt-3 dark:border-gray-700">
    <legend className="px-1 text-sm font-medium text-gray-700 dark:text-gray-200">Kanalen · {completed} van {item.channels.length} afgerond</legend>
    {item.channels.map((channel) => <div key={channel.channel_id} className="flex flex-wrap items-center gap-x-3 gap-y-1 pl-3">
      <label className="flex min-h-9 items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
        <input type="checkbox" checked={Boolean(channel.actual_date)} disabled={!available || action.isPending} onChange={(event) => toggle(channel, event.target.checked)} />
        {channel.label}
      </label>
      {channel.actual_date && <span className="text-xs text-gray-500">Afgerond op {niceDate(channel.actual_date)}</span>}
      {channel.published_url && <a className="text-sm text-electric-cyan underline" href={channel.published_url} target="_blank" rel="noreferrer">Bekijk bericht<ExternalLink className="ml-1 inline h-3 w-3" /></a>}
      {channel.actual_date && <ChannelCompletionDetails itemId={item.id} channel={channel} disabled={!available || action.isPending} />}
    </div>)}
    {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}
  </fieldset>;
}

function ItemForm({ item, users, channels, onClose }) {
  const editing = Boolean(item?.id);
  const { data: detail } = useCommunication(item?.id);
  const current = detail || item;
  const [form, setForm] = useState(item ? { ...blank, ...item } : blank);
  const [files, setFiles] = useState([]);
  const [error, setError] = useState('');
  const [comment, setComment] = useState('');
  const today = new Date().toISOString().slice(0, 10);
  const create = useCreateCommunication();
  const update = useUpdateCommunication();
  const action = useCommunicationAction();
  const seriesAction = useSeriesAction();
  const upload = useUploadCommunication();
  const addComment = useAddCommunicationComment();
  const { data: comments = [] } = useCommunicationComments(item?.id);

  useEffect(() => {
    if (detail) setForm((previous) => ({ ...previous, ...detail, version: detail.modified_gmt }));
  }, [detail]);

  const set = (key, value) => setForm((previous) => ({ ...previous, [key]: value }));
  const busy = create.isPending || update.isPending || action.isPending || upload.isPending || seriesAction.isPending;

  async function save(event) {
    event.preventDefault();
    setError('');
    try {
      if (!editing && form.recurrence !== 'none' && form.start_date < new Date().toISOString().slice(0, 10)
        && !window.confirm('De startdatum ligt in het verleden. Ook eerdere openstaande keren worden aangemaakt. Doorgaan?')) return;
      if (!form.channel_ids.length) { setError('Kies minimaal één kanaal.'); return; }
      const payload = { ...form, assignee_id: Number(form.assignee_id || 0) };
      const response = editing
        ? await update.mutateAsync({ id: item.id, data: payload })
        : await create.mutateAsync(payload);
      const id = response.data.id;
      for (const file of files) await upload.mutateAsync({ id, file });
      onClose();
    } catch (err) { setError(errorText(err)); }
  }

  async function runAction(name, extra = {}) {
    setError('');
    try {
      const response = await action.mutateAsync({ id: item.id, data: { action: name, ...extra } });
      if (name === 'duplicate') onClose(response.data);
      else if (['cancel', 'skip'].includes(name)) onClose();
      else setForm((previous) => ({ ...previous, ...response.data, version: response.data.modified_gmt }));
    } catch (err) { setError(errorText(err)); }
  }

  function removeAttachment(key) { set('attachments', form.attachments.filter((attachment) => attachment.key !== key)); }
  function moveAttachment(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= form.attachments.length) return;
    const next = [...form.attachments];
    [next[index], next[target]] = [next[target], next[index]];
    set('attachments', next);
  }

  async function postComment(event) {
    event.preventDefault();
    if (!comment.trim()) return;
    await addComment.mutateAsync({ id: item.id, content: comment });
    setComment('');
  }

  const isClosed = ['sent', 'skipped', 'cancelled'].includes(form.status);
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-3 sm:p-8" role="dialog" aria-modal="true" aria-labelledby="communication-dialog-title">
      <div className="mx-auto max-w-3xl rounded-xl bg-white shadow-xl dark:bg-gray-800">
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
          <h2 id="communication-dialog-title" className="text-lg font-semibold text-gray-900 dark:text-white">{editing ? form.title : 'Nieuw communicatie-item'}</h2>
          <button type="button" onClick={() => onClose()} className="rounded p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Sluiten"><X className="h-5 w-5" /></button>
        </div>
        <form onSubmit={save} className="space-y-5 p-5">
          {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">{error}</div>}
          <div>
            <label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-title">Titel *</label>
            <input id="comm-title" className="input w-full" value={form.title} onChange={(e) => set('title', e.target.value)} required />
          </div>
          <fieldset>
            <legend className="mb-2 text-sm font-medium dark:text-gray-200">Kanalen *</legend>
            <div className="flex flex-wrap gap-4">
              {channels.filter((channel) => channel.active || form.channel_ids.includes(channel.id)).map((channel) => <label key={channel.id} className="flex items-center gap-2 text-sm dark:text-gray-200"><input type="checkbox" checked={form.channel_ids.includes(channel.id)} disabled={Boolean(current?.channels?.find((entry) => entry.channel_id === channel.id)?.actual_date)} onChange={(e) => set('channel_ids', e.target.checked ? [...form.channel_ids, channel.id] : form.channel_ids.filter((id) => id !== channel.id))} /> {channel.label}{!channel.active && ' (inactief)'}</label>)}
              {channels.every((channel) => !channel.active) && <p className="text-sm text-gray-500">Vraag een beheerder om kanalen toe te voegen bij Instellingen → Club.</p>}
            </div>
          </fieldset>
          <div>
            <label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-description">Beschrijving {form.status !== 'concept' && '*'}</label>
            <textarea id="comm-description" className="input min-h-28 w-full" value={form.description} onChange={(e) => set('description', e.target.value)} required={form.status !== 'concept'} />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-date">Geplande datum {form.status !== 'concept' && '*'}</label><input id="comm-date" type="date" className="input w-full" value={form.planned_date} onChange={(e) => set('planned_date', e.target.value)} required={form.status !== 'concept'} /></div>
            <div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-assignee">Verantwoordelijke {form.status !== 'concept' && '*'}</label><select id="comm-assignee" className="input w-full" value={form.assignee_id} onChange={(e) => set('assignee_id', e.target.value)} required={form.status !== 'concept'}><option value="">Niet toegewezen</option>{users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></div>
          </div>
          <div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-audience">Doelgroep / bestemming {form.status !== 'concept' && '*'}</label><input id="comm-audience" className="input w-full" placeholder="Bijv. WhatsApp trainers O13" value={form.audience} onChange={(e) => set('audience', e.target.value)} required={form.status !== 'concept'} /></div>
          <div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-doc">Google Docs-link</label><input id="comm-doc" type="url" className="input w-full" placeholder="https://docs.google.com/document/..." value={form.google_docs_url} onChange={(e) => set('google_docs_url', e.target.value)} /><p className="mt-1 text-xs text-gray-500">Toegang tot het document wordt in Google geregeld.</p></div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-status">Status</label><select id="comm-status" className="input w-full" value={form.status} onChange={(e) => set('status', e.target.value)} disabled={isClosed}>{openStatuses.map((status) => <option key={status} value={status}>{statuses[status]}</option>)}{isClosed && <option value={form.status}>{statuses[form.status]}</option>}</select></div>
            {!editing && <div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-repeat">Herhaling</label><select id="comm-repeat" className="input w-full" value={form.recurrence} onChange={(e) => set('recurrence', e.target.value)}>{Object.entries(recurrenceLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>}
          </div>
          {!editing && form.recurrence !== 'none' && <div className="grid gap-4 sm:grid-cols-2"><div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-start">Startdatum *</label><input id="comm-start" type="date" className="input w-full" value={form.start_date} onChange={(e) => { set('start_date', e.target.value); set('planned_date', e.target.value); }} required /></div><div><label className="mb-1 block text-sm font-medium dark:text-gray-200" htmlFor="comm-end">Einddatum</label><input id="comm-end" type="date" min={form.start_date} className="input w-full" value={form.end_date} onChange={(e) => set('end_date', e.target.value)} /></div></div>}
          <div>
            <div className="mb-2 flex items-center justify-between"><span className="text-sm font-medium dark:text-gray-200">Afbeeldingen</span><label className="btn-tertiary cursor-pointer text-sm"><ImagePlus className="mr-2 h-4 w-4" />Toevoegen<input type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" onChange={(e) => setFiles([...files, ...e.target.files].slice(0, 10 - form.attachments.length))} /></label></div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {form.attachments.map((attachment, index) => <div key={attachment.key} className="rounded-lg border border-gray-200 p-2 dark:border-gray-700"><img src={attachment.url} alt={attachment.alt || attachment.name} className="mb-2 h-28 w-full rounded object-cover" /><input className="input mb-2 w-full text-xs" value={attachment.alt || ''} placeholder="Beschrijving afbeelding" onChange={(e) => set('attachments', form.attachments.map((entry) => entry.key === attachment.key ? { ...entry, alt: e.target.value } : entry))} /><div className="flex justify-between"><button type="button" onClick={() => moveAttachment(index, -1)} disabled={index === 0} aria-label="Naar voren"><ChevronUp className="h-4 w-4" /></button><button type="button" onClick={() => removeAttachment(attachment.key)} className="text-red-600" aria-label="Verwijderen"><X className="h-4 w-4" /></button><button type="button" onClick={() => moveAttachment(index, 1)} disabled={index === form.attachments.length - 1} aria-label="Naar achteren"><ChevronDown className="h-4 w-4" /></button></div></div>)}
              {files.map((file) => <div key={`${file.name}-${file.size}`} className="flex h-28 items-center justify-center rounded-lg border border-dashed border-gray-300 p-2 text-center text-xs dark:border-gray-600 dark:text-gray-300">{file.name}<br />(na opslaan)</div>)}
            </div>
          </div>
          {editing && form.series_id && openStatuses.includes(form.status) && <label className="flex items-start gap-2 rounded-lg bg-blue-50 p-3 text-sm text-blue-800 dark:bg-blue-900/20 dark:text-blue-200"><input type="checkbox" className="mt-1" checked={form.apply_to_future} onChange={(e) => set('apply_to_future', e.target.checked)} />Pas titel, inhoud, kanalen, doelgroep en verantwoordelijke ook toe op toekomstige keren waaraan nog niet is begonnen.</label>}
          <div className="flex flex-wrap justify-between gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <div className="flex flex-wrap gap-2">
              {editing && <button type="button" className="btn-tertiary" onClick={() => runAction('duplicate')}><Copy className="mr-2 h-4 w-4" />Dupliceren</button>}
              {editing && openStatuses.includes(form.status) && <button type="button" className="btn-tertiary text-red-600" onClick={() => runAction(form.series_id ? 'skip' : 'cancel', { reason: window.prompt('Reden (optioneel)') || '' })}>{form.series_id ? 'Deze keer overslaan' : 'Annuleren'}</button>}
              {editing && ['skipped', 'cancelled'].includes(form.status) && <button type="button" className="btn-tertiary" onClick={() => runAction('restore')}><RotateCcw className="mr-2 h-4 w-4" />Ongedaan maken</button>}
            </div>
            <button type="submit" className="btn-primary" disabled={busy}>{busy ? 'Bezig…' : 'Opslaan'}</button>
          </div>
        </form>

        {editing && <div className="space-y-5 border-t border-gray-200 p-5 dark:border-gray-700">
          <ChannelChecklist item={current} />
          {form.series_id && form.series_status !== 'ended' && <div className="flex flex-wrap items-center gap-2"><Repeat2 className="h-4 w-4 text-gray-500" /><span className="text-sm dark:text-gray-200">{recurrenceLabels[form.recurrence]} · reeks {form.series_status === 'paused' ? 'gepauzeerd' : 'actief'}</span>{form.series_status === 'paused' ? <button className="btn-tertiary text-sm" onClick={() => seriesAction.mutate({ id: form.series_id, data: { action: 'resume' } })}><Play className="mr-1 h-4 w-4" />Hervatten</button> : <button className="btn-tertiary text-sm" onClick={() => seriesAction.mutate({ id: form.series_id, data: { action: 'pause' } })}><Pause className="mr-1 h-4 w-4" />Pauzeren</button>}<button className="btn-tertiary text-sm text-red-600" onClick={() => { if (window.confirm('Reeks beëindigen en toekomstige openstaande keren annuleren?')) seriesAction.mutate({ id: form.series_id, data: { action: 'end', end_date: today } }); }}>Reeks beëindigen</button></div>}
          <div><h3 className="mb-3 font-semibold dark:text-white">Interne opmerkingen</h3><div className="space-y-2">{comments.map((entry) => <div key={entry.id} className="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900"><div className="mb-1 flex justify-between text-xs text-gray-500"><span>{entry.author}</span><time>{new Date(entry.date).toLocaleString('nl-NL')}</time></div><p className="whitespace-pre-wrap dark:text-gray-200">{entry.content}</p></div>)}{comments.length === 0 && <p className="text-sm text-gray-500">Nog geen opmerkingen.</p>}</div><form onSubmit={postComment} className="mt-3 flex gap-2"><input className="input flex-1" value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Voeg een interne opmerking toe" /><button className="btn-secondary" disabled={addComment.isPending}><MessageCircle className="h-4 w-4" /><span className="sr-only">Plaatsen</span></button></form></div>
          {current?.audit?.length > 0 && <details><summary className="cursor-pointer text-sm font-medium dark:text-gray-200">Wijzigingshistorie ({current.audit.length})</summary><ol className="mt-3 space-y-2 text-xs text-gray-500">{[...current.audit].reverse().map((entry, index) => <li key={`${entry.date}-${index}`}><time>{new Date(entry.date).toLocaleString('nl-NL')}</time> · {entry.user_name} · {entry.event}</li>)}</ol></details>}
        </div>}
      </div>
    </div>
  );
}

export default function Planning() {
  useDocumentTitle('Planning');
  const { data, isLoading, error, refetch } = useCommunications();
  const update = useUpdateCommunication();
  const moveLock = useRef(false);
  const [today, setToday] = useState(planningToday);
  const [period, setPeriod] = useState('2');
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [channel, setChannel] = useState('');
  const [assignee, setAssignee] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [overdueOnly, setOverdueOnly] = useState(false);
  const [showHidden, setShowHidden] = useState(false);
  const [selected, setSelected] = useState(null);
  const [movingId, setMovingId] = useState(null);
  const [moveError, setMoveError] = useState(null);
  const [announcement, setAnnouncement] = useState('');
  const items = useMemo(() => data?.items || [], [data]);
  const users = data?.users || [];
  const channels = data?.channels || [];

  useEffect(() => {
    const timer = window.setInterval(() => setToday(planningToday()), 60000);
    return () => window.clearInterval(timer);
  }, []);

  const filtered = useMemo(() => filterPlanningItems(items, {
    period, search, channel, assignee, dateFrom, dateTo, overdueOnly,
  }, today), [items, period, search, channel, assignee, dateFrom, dateTo, overdueOnly, today]);
  const archived = sortPlanningItems(filtered.filter((item) => archivedPlanningStatuses.includes(item.status)));
  const filterCount = [search.trim(), channel, assignee, dateFrom || dateTo, overdueOnly, showHidden].filter(Boolean).length;
  const customRange = Boolean(dateFrom || dateTo);

  function clearFilters() {
    setSearch(''); setChannel(''); setAssignee(''); setDateFrom(''); setDateTo(''); setOverdueOnly(false); setShowHidden(false);
  }

  async function moveItem(item, status) {
    const payload = planningMovePayload(item, status);
    if (!payload || moveLock.current) return;
    moveLock.current = true;
    setMovingId(item.id);
    setMoveError(null);
    setAnnouncement('');
    try {
      await update.mutateAsync({ id: item.id, data: payload });
      await refetch();
      setAnnouncement(`${item.title} verplaatst naar ${statuses[status]}.`);
    } catch (err) {
      setMoveError({ item, message: errorText(err) });
      if (err.response?.status === 409) await refetch();
    } finally {
      moveLock.current = false;
      setMovingId(null);
    }
  }

  if (isLoading) return <div className="p-8 text-gray-500" role="status">Communicatieplanning laden…</div>;
  if (error && !data) return <div className="space-y-3 p-6"><p role="alert" className="text-red-700 dark:text-red-300">De communicatieplanning kon niet worden geladen.</p><button type="button" className="btn-secondary" onClick={() => refetch()}>Opnieuw proberen</button></div>;

  return (
    <div className="min-w-0 space-y-3">
      <div className="flex flex-wrap items-center gap-2">
        <div className={customRange ? 'w-48 shrink-0' : 'w-36 shrink-0'}>
        <select
          className="input min-h-11 w-full text-sm"
          aria-label="Periode vanaf vandaag"
          value={customRange ? 'custom' : period}
          onChange={(event) => { setPeriod(event.target.value); setDateFrom(''); setDateTo(''); }}
        >
          <option value="2">2 maanden</option><option value="1">1 maand</option><option value="all">Alle berichten</option>
          {customRange && <option value="custom">Aangepaste periode</option>}
        </select>
        </div>
        <button type="button" className="btn-secondary min-h-11 gap-2" aria-expanded={filtersOpen} aria-controls="planning-filters" onClick={() => setFiltersOpen((open) => !open)}>
          <SlidersHorizontal aria-hidden="true" className="h-4 w-4" />Filters{filterCount > 0 && <span className="text-xs tabular-nums">· {filterCount}</span>}
        </button>
        <button type="button" className="btn-primary ml-auto min-h-11 gap-2" onClick={() => setSelected({})}><Plus aria-hidden="true" className="h-4 w-4" /><span className="sr-only sm:not-sr-only">Nieuw item</span></button>
      </div>

      {filtersOpen && <div id="planning-filters" className="space-y-3 border-b border-gray-200 pb-4 dark:border-gray-700">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <label className="text-sm text-gray-700 dark:text-gray-200">Zoeken<span className="relative mt-1 block"><Search aria-hidden="true" className="absolute left-3 top-3 h-4 w-4 text-gray-400" /><input className="input input-leading-icon w-full" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Titel of beschrijving" /></span></label>
          <label className="text-sm text-gray-700 dark:text-gray-200">Kanaal<select className="input mt-1 w-full" value={channel} onChange={(event) => setChannel(event.target.value)}><option value="">Alle kanalen</option>{channels.map((entry) => <option key={entry.id} value={entry.id}>{entry.label}{!entry.active ? ' (inactief)' : ''}</option>)}</select></label>
          <label className="text-sm text-gray-700 dark:text-gray-200">Verantwoordelijke<select className="input mt-1 w-full" value={assignee} onChange={(event) => setAssignee(event.target.value)}><option value="">Iedereen</option><option value="unassigned">Niet toegewezen</option>{users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></label>
          <label className="text-sm text-gray-700 dark:text-gray-200">Vanaf<input type="date" className="input mt-1 w-full" max={dateTo || undefined} value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} /></label>
          <label className="text-sm text-gray-700 dark:text-gray-200">Tot en met<input type="date" className="input mt-1 w-full" min={dateFrom || undefined} value={dateTo} onChange={(event) => setDateTo(event.target.value)} /></label>
        </div>
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-700 dark:text-gray-200">
          <label className="flex min-h-9 items-center gap-2"><input type="checkbox" checked={overdueOnly} onChange={(event) => setOverdueOnly(event.target.checked)} />Alleen achterstallig</label>
          <label className="flex min-h-9 items-center gap-2"><input type="checkbox" checked={showHidden} onChange={(event) => setShowHidden(event.target.checked)} />Toon overgeslagen en geannuleerde items</label>
          {filterCount > 0 && <button type="button" className="btn-tertiary" onClick={clearFilters}>Filters wissen</button>}
        </div>
        <p className="text-xs text-gray-600 dark:text-gray-300">{customRange ? 'De aangepaste periode gebruikt de geplande datum, of de afhandeldatum bij afgeronde berichten.' : period === 'all' ? 'Alle geplande, ongeplande en afgeronde berichten.' : 'Achterstallige en ongeplande berichten blijven in beeld. Afgerond: laatste 30 dagen.'}</p>
        {dateFrom && dateTo && dateFrom > dateTo && <p role="alert" className="text-sm text-red-700 dark:text-red-300">De einddatum moet op of na de begindatum liggen.</p>}
      </div>}

      {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">Vernieuwen is niet gelukt. <button type="button" className="underline" onClick={() => refetch()}>Opnieuw proberen</button></p>}
      {moveError && <div role="alert" className="flex flex-wrap items-center gap-3 rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/50 dark:text-red-200"><span>{moveError.message}</span><button type="button" className="underline" onClick={() => { setSelected(items.find((item) => item.id === moveError.item.id) || moveError.item); setMoveError(null); }}>Open bericht</button><button type="button" aria-label="Melding sluiten" onClick={() => setMoveError(null)}><X className="h-4 w-4" /></button></div>}
      <div role="status" aria-live="polite" className="sr-only">{announcement}</div>
      <PlanningBoard items={filtered} today={today} onOpen={setSelected} onMove={moveItem} movingId={movingId} />
      {showHidden && <section aria-label="Overgeslagen en geannuleerd" className="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Overgeslagen en geannuleerd · {archived.length}</h2>
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{archived.map((item) => <PlanningCard key={item.id} item={item} today={today} onOpen={setSelected} />)}</div>
        {!archived.length && <p className="text-sm text-gray-500 dark:text-gray-400">Geen berichten</p>}
      </section>}
      {selected && <ItemForm item={selected.id ? selected : null} users={users} channels={channels} onClose={(duplicate) => { setSelected(duplicate?.id ? duplicate : null); }} />}
    </div>
  );
}
