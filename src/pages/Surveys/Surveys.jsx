import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import QRCode from 'qrcode';
import api from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const base = '/rondo/v1/surveys';
const key = () => `q${crypto.randomUUID().replaceAll('-', '')}`;
const question = () => ({ id: key(), label: '', type: 'single', required: false, description: '', options: [{ id: key(), label: '' }, { id: key(), label: '' }], condition: null });
const section = () => ({ id: key(), title: 'Nieuwe sectie', description: '', condition: null, questions: [question()] });
const statusLabel = { draft: 'Concept', published: 'Gepubliceerd', closed: 'Gesloten' };
const errorText = (error) => error?.response?.data?.message || error?.message || 'Er ging iets mis.';
const query = (queryKey, url, enabled = true) => ({ queryKey, queryFn: async () => (await api.get(url)).data, enabled });

function ErrorMessage({ error }) {
  return error ? <p role="alert" className="rounded-lg bg-red-50 p-3 text-red-800 dark:bg-red-950 dark:text-red-200">{errorText(error)}</p> : null;
}
function Field({ label, children }) {
  return <label className="block space-y-1 text-sm font-medium"><span>{label}</span>{children}</label>;
}
function MoveButtons({ index, length, move, remove, label }) {
  return <div className="flex gap-2 text-sm"><button type="button" className="btn-tertiary" aria-label={`${label} omhoog`} disabled={!index} onClick={() => move(-1)}>↑</button><button type="button" className="btn-tertiary" aria-label={`${label} omlaag`} disabled={index === length - 1} onClick={() => move(1)}>↓</button><button type="button" className="btn-tertiary text-red-700" onClick={remove}>Verwijderen</button></div>;
}
function Rule({ value, previous, onChange }) {
  const questions = previous.filter((q) => q.type !== 'text');
  const selected = questions.find((q) => q.id === value?.question);
  return <div className="flex flex-wrap items-end gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
    <Field label="Zichtbaar als"><select className="input" value={value?.question || ''} onChange={(e) => onChange(e.target.value ? { question: e.target.value, value: questions.find((q) => q.id === e.target.value)?.options[0]?.id || '' } : null)}><option value="">Altijd zichtbaar</option>{questions.map((q) => <option key={q.id} value={q.id}>{q.label || 'Vraag zonder tekst'}</option>)}</select></Field>
    {value && <Field label="Het antwoord bevat / is"><select className="input" value={value.value} onChange={(e) => onChange({ ...value, value: e.target.value })}>{!selected && <option value={value.value}>Verwijderde of verplaatste vraag — pas de regel aan</option>}{selected?.options.map((o) => <option key={o.id} value={o.id}>{o.label || 'Optie zonder tekst'}</option>)}</select></Field>}
  </div>;
}
function reordered(items, index, delta) {
  const result = [...items];
  [result[index], result[index + delta]] = [result[index + delta], result[index]];
  return result;
}

function Editor({ survey, saved, saving }) {
  const [draft, setDraft] = useState(survey);
  const [dirty, setDirty] = useState(false);
  const update = (patch) => { setDirty(true); setDraft((d) => ({ ...d, ...patch })); };
  useEffect(() => {
    if (!dirty) return undefined;
    const handler = (e) => { e.preventDefault(); e.returnValue = ''; };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty]);
  const sections = draft.document.sections;
  const changeSections = (next) => update({ document: { sections: next } });
  const changeSection = (index, patch) => changeSections(sections.map((s, i) => i === index ? { ...s, ...patch } : s));
  const changeQuestion = (si, qi, patch) => changeSection(si, { questions: sections[si].questions.map((q, i) => i === qi ? { ...q, ...patch } : q) });
  const dateValue = draft.closes_at ? new Date(new Date(draft.closes_at).getTime() - new Date(draft.closes_at).getTimezoneOffset() * 60000).toISOString().slice(0, 16) : '';
  return <form className="space-y-6" onSubmit={(e) => { e.preventDefault(); saved(draft); }}>
    <div className="card space-y-4 p-5">
      <Field label="Titel"><input className="input w-full" required value={draft.title} onChange={(e) => update({ title: e.target.value })} /></Field>
      <Field label="Introductie"><textarea className="input w-full" rows={4} value={draft.intro} onChange={(e) => update({ intro: e.target.value })} /></Field>
      <Field label="Bedanktekst"><textarea className="input w-full" rows={3} value={draft.thanks} onChange={(e) => update({ thanks: e.target.value })} /></Field>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label="Sluitingsdatum (jouw lokale tijd)"><input type="datetime-local" className="input w-full" value={dateValue} onChange={(e) => update({ closes_at: e.target.value ? new Date(e.target.value).toISOString().replace('.000Z', 'Z') : '' })} /></Field>
        <Field label="Automatisch één herinnering na aantal dagen (0 = uit)"><input type="number" min={0} max={90} className="input w-full" value={draft.reminder_days} onChange={(e) => update({ reminder_days: Number(e.target.value) })} /></Field>
        <Field label="Toegang"><select className="input w-full" disabled={!!draft.current_version} value={draft.access} onChange={(e) => update({ access: e.target.value })}><option value="public">Algemene link én persoonlijke uitnodigingen</option><option value="invitation">Alleen persoonlijke uitnodigingen</option></select></Field>
        <Field label="Persoonsgegevens bij antwoorden"><select className="input w-full" disabled={!!draft.current_version} value={draft.linked ? 'linked' : 'unlinked'} onChange={(e) => update({ linked: e.target.value === 'linked' })}><option value="unlinked">Naam en e-mailadres niet bij antwoorden opslaan</option><option value="linked">Naam en e-mailadres uit uitnodiging opslaan</option></select></Field>
      </div>
      <p className="text-sm text-gray-500">Toegang en persoonskoppeling staan vast na de eerste publicatie. Een persoonlijke uitnodiging houdt apart bij of iemand heeft meegedaan. Antwoorden zonder naam zijn niet gegarandeerd anoniem tegenover beheerders met opslagtoegang.</p>
      {survey.current_version > 0 && <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Je bewerkt het concept. Publiceer na opslaan een nieuwe versie. Bestaande uitnodigingen blijven hun oorspronkelijke vragenlijst gebruiken; de algemene link gebruikt de nieuwste versie.</p>}
    </div>
    {sections.map((s, si) => {
      const previous = sections.slice(0, si).flatMap((x) => x.questions);
      return <section key={s.id} className="card space-y-4 p-5">
        <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold">Sectie {si + 1}</h2><MoveButtons index={si} length={sections.length} label="Sectie" move={(delta) => changeSections(reordered(sections, si, delta))} remove={() => changeSections(sections.filter((_, i) => i !== si))} /></div>
        <Field label="Sectietitel"><input required className="input w-full" value={s.title} onChange={(e) => changeSection(si, { title: e.target.value })} /></Field>
        <Field label="Uitleg bij deze sectie"><textarea className="input w-full" value={s.description || ''} onChange={(e) => changeSection(si, { description: e.target.value })} /></Field>
        <Rule previous={previous} value={s.condition} onChange={(condition) => changeSection(si, { condition })} />
        {s.questions.map((q, qi) => <div key={q.id} className="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="text-sm font-semibold">Vraag {qi + 1}</h3><MoveButtons index={qi} length={s.questions.length} label="Vraag" move={(delta) => changeSection(si, { questions: reordered(s.questions, qi, delta) })} remove={() => changeSection(si, { questions: s.questions.filter((_, i) => i !== qi) })} /></div>
          <Field label="Vraagtekst"><input className="input w-full" required value={q.label} onChange={(e) => changeQuestion(si, qi, { label: e.target.value })} /></Field>
          <Field label="Vraagtype"><select className="input w-full" value={q.type} onChange={(e) => changeQuestion(si, qi, { type: e.target.value, options: e.target.value === 'text' ? [] : q.options.length ? q.options : question().options })}><option value="single">Eén antwoord</option><option value="multiple">Meerdere antwoorden</option><option value="scale">Beoordelingsschaal</option><option value="text">Open tekst</option></select></Field>
          <Field label="Uitleg bij de vraag"><input className="input w-full" value={q.description || ''} onChange={(e) => changeQuestion(si, qi, { description: e.target.value })} /></Field>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={q.required} onChange={(e) => changeQuestion(si, qi, { required: e.target.checked })} />Verplicht wanneer zichtbaar</label>
          {q.type !== 'text' && <div className="space-y-2">{q.options.map((o, oi) => <div key={o.id} className="flex gap-2"><input aria-label={`Antwoordoptie ${oi + 1}`} className="input min-w-0 flex-1" required value={o.label} onChange={(e) => changeQuestion(si, qi, { options: q.options.map((option, i) => i === oi ? { ...option, label: e.target.value } : option) })} /><button type="button" className="btn-tertiary" aria-label={`Verwijder antwoordoptie ${oi + 1}`} onClick={() => changeQuestion(si, qi, { options: q.options.filter((_, i) => i !== oi) })}>×</button></div>)}<button type="button" className="btn-secondary" onClick={() => changeQuestion(si, qi, { options: [...q.options, { id: key(), label: '' }] })}>Antwoordoptie toevoegen</button></div>}
          <Rule previous={[...previous, ...s.questions.slice(0, qi)]} value={q.condition} onChange={(condition) => changeQuestion(si, qi, { condition })} />
        </div>)}
        <button type="button" className="btn-secondary" onClick={() => changeSection(si, { questions: [...s.questions, question()] })}>Vraag toevoegen</button>
      </section>;
    })}
    <div className="sticky bottom-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900"><button type="button" className="btn-secondary" onClick={() => changeSections([...sections, section()])}>Sectie toevoegen</button><span className="text-sm">{dirty ? 'Niet-opgeslagen wijzigingen' : 'Concept opgeslagen'}</span><button className="btn-primary" disabled={saving}>{saving ? 'Opslaan…' : 'Concept opslaan'}</button></div>
  </form>;
}

function Invitations({ survey }) {
  const client = useQueryClient();
  const [recipients, setRecipients] = useState('');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState([]);
  const [notice, setNotice] = useState('');
  const invites = useQuery(query(['survey-invites', survey.id], `${base}/${survey.id}/invitations`));
  const people = useQuery({ ...query(['survey-recipients', search], `${base}/recipients?search=${encodeURIComponent(search)}`, search.length > 1), placeholderData: [] });
  const mutation = useMutation({
    mutationFn: async (action) => {
      if (action === 'prepare') {
        const rows = recipients.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => { const parts = line.split(';'); return { email: parts.length > 1 ? parts[1].trim() : line, name: parts.length > 1 ? parts[0].trim() : '' }; });
        return (await api.post(`${base}/${survey.id}/invitations`, { recipients: rows, person_ids: selected.map((p) => p.id) })).data;
      }
      return (await api.post(`${base}/${survey.id}/action`, { action })).data;
    },
    onSuccess: (result) => { setNotice(result.prepared !== undefined ? `${result.prepared} uitnodigingen voorbereid. Er is nog niets verzonden.` : `${result.queued} berichten klaargezet. Rondo verstuurt ze in de achtergrond, uiterlijk bij de volgende uurlijkse verwerking.`); client.invalidateQueries({ queryKey: ['survey-invites', survey.id] }); },
  });
  return <div className="space-y-5">
    <div className="card space-y-4 p-5"><h2 className="font-semibold">Ontvangers voorbereiden</h2><p className="text-sm">Kies leden of voeg e-mailadressen toe. Per e-mailadres wordt één uitnodiging gemaakt. Voorbereiden verstuurt geen mail.</p>
      <Field label="Zoek een lid op naam"><input className="input w-full" value={search} onChange={(e) => setSearch(e.target.value)} /></Field>
      <ErrorMessage error={people.error} />
      {people.data?.length > 0 && <div className="max-h-48 overflow-auto rounded border dark:border-gray-700">{people.data.map((p) => <button type="button" className="block w-full p-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800" key={p.id} onClick={() => { setSelected((rows) => rows.some((row) => row.id === p.id) ? rows : [...rows, p]); setSearch(''); }}>{p.name} — {p.email}</button>)}</div>}
      {selected.map((p) => <button key={p.id} className="btn-secondary mr-2" onClick={() => setSelected(selected.filter((row) => row.id !== p.id))}>{p.name} ×</button>)}
      <Field label="Eén ontvanger per regel: e-mailadres of Naam; e-mailadres"><textarea className="input w-full" rows={6} value={recipients} onChange={(e) => setRecipients(e.target.value)} placeholder={'naam@voorbeeld.nl\nJan Jansen; jan@voorbeeld.nl'} /></Field>
      <button className="btn-secondary" disabled={mutation.isPending || (!recipients.trim() && !selected.length)} onClick={() => mutation.mutate('prepare')}>Uitnodigingen voorbereiden</button>
    </div>
    <div className="card space-y-4 p-5"><h2 className="font-semibold">Verzenden</h2><p className="text-sm">Uitnodigingen gebruiken de vragenlijstversie die bij het voorbereiden gepubliceerd was. Herinneringen gaan alleen naar deelnemers die nog niet hebben geantwoord, maximaal één per uitnodiging.</p><div className="flex flex-wrap gap-3"><button className="btn-primary" disabled={mutation.isPending || !invites.data?.some((i) => ['prepared', 'failed'].includes(i.status) && !i.completed)} onClick={() => { if (window.confirm('Alle voorbereide uitnodigingen nu per e-mail verzenden?')) mutation.mutate('send'); }}>Uitnodigingen verzenden</button><button className="btn-secondary" disabled={mutation.isPending || !invites.data?.some((i) => ['sent', 'reminder_failed'].includes(i.status) && !i.completed && !i.reminded_at)} onClick={() => { if (window.confirm('Eén herinnering sturen aan deelnemers die nog niet hebben geantwoord?')) mutation.mutate('remind'); }}>Herinnering verzenden</button><button className="btn-tertiary" onClick={() => invites.refetch()}>Status vernieuwen</button></div>
      <ErrorMessage error={mutation.error || invites.error} />{notice && <p role="status">{notice}</p>}
    </div>
    <div className="card overflow-x-auto p-5"><h2 className="mb-3 font-semibold">{invites.data?.length || 0} uitnodigingen · {invites.data?.filter((i) => i.completed).length || 0} ingevuld</h2><table className="w-full text-left text-sm"><thead><tr><th className="p-2">Ontvanger</th><th className="p-2">Status</th><th className="p-2">Ingevuld</th><th className="p-2">Herinnering</th></tr></thead><tbody>{invites.data?.map((i) => <tr className="border-t dark:border-gray-700" key={i.id}><td className="p-2">{i.name}<br />{i.email}</td><td className="p-2">{{ prepared: 'Voorbereid', queued: 'In wachtrij', sending: 'Wordt verstuurd / uitkomst onbekend', sent: 'Verzonden', failed: 'Mislukt', reminder_queued: 'Herinnering in wachtrij', reminder_failed: 'Herinnering mislukt' }[i.status] || i.status}</td><td className="p-2">{i.completed ? 'Ja' : 'Nee'}</td><td className="p-2">{i.reminded_at ? new Date(i.reminded_at).toLocaleDateString('nl-NL') : '—'}</td></tr>)}</tbody></table></div>
  </div>;
}

function Results({ survey }) {
  const [version, setVersion] = useState(survey.current_version);
  const [filter, setFilter] = useState({ question: '', value: '' });
  const [page, setPage] = useState(1);
  const [downloadError, setDownloadError] = useState(null);
  const params = new URLSearchParams({ version, ...filter, page }).toString();
  const result = useQuery(query(['survey-results', survey.id, params], `${base}/${survey.id}/results?${params}`, !!version));
  const choices = result.data?.summary.filter((q) => q.type !== 'text') || [];
  const download = async () => {
    try {
      const { data } = await api.get(`${base}/${survey.id}/export?${params}`);
      const url = URL.createObjectURL(new Blob([data.csv], { type: 'text/csv;charset=utf-8;' }));
      const a = document.createElement('a'); a.href = url; a.download = data.filename; a.click(); URL.revokeObjectURL(url);
    } catch (error) { setDownloadError(error); }
  };
  return <div className="space-y-5"><div className="card flex flex-wrap items-end gap-4 p-5">
    <Field label="Vragenlijstversie"><select className="input" value={version} onChange={(e) => { setVersion(Number(e.target.value)); setFilter({ question: '', value: '' }); setPage(1); }}>{survey.versions.map((v, i) => <option key={v.id} value={v.id}>Versie {i + 1} — {new Date(`${v.date.replace(' ', 'T')}Z`).toLocaleString('nl-NL')}</option>)}</select></Field>
    <Field label="Filter op antwoord"><select className="input max-w-xs" value={filter.question} onChange={(e) => { setFilter({ question: e.target.value, value: '' }); setPage(1); }}><option value="">Alle antwoorden</option>{choices.map((q) => <option key={q.id} value={q.id}>{q.label}</option>)}</select></Field>
    {filter.question && <Field label="Antwoord"><select className="input max-w-xs" value={filter.value} onChange={(e) => { setFilter({ ...filter, value: e.target.value }); setPage(1); }}><option value="">Alle opties</option>{choices.find((q) => q.id === filter.question)?.options.map((o) => <option key={o.id} value={o.id}>{o.label}</option>)}</select></Field>}
    <button className="btn-secondary" disabled={!version || !result.data} onClick={download}>CSV downloaden</button><button className="btn-tertiary" onClick={() => result.refetch()}>Vernieuwen</button>
  </div><ErrorMessage error={result.error || downloadError} />{!version && <p>Publiceer eerst een vragenlijstversie.</p>}{result.isPending && !!version && <p>Resultaten laden…</p>}
    {result.data && <><p className="text-lg font-semibold">{result.data.total} inzendingen binnen deze selectie</p><p className="text-sm text-gray-500">Percentages gebruiken het aantal antwoorden op die vraag. Bij meerdere antwoorden kan het totaal boven 100% uitkomen.</p>{result.data.summary.map((q) => <section className="card p-5" key={q.id}><p className="mb-1 text-xs text-gray-500">{q.section}</p><h2 className="font-semibold">{q.label}</h2><p className="mb-3 text-sm text-gray-500">{q.answered} antwoorden</p>{q.type === 'text' ? <ul className="space-y-3">{q.texts.map((text, i) => <li key={i} className="whitespace-pre-wrap border-t pt-2 dark:border-gray-700">{text}</li>)}</ul> : <div className="space-y-3">{q.options.map((o) => { const count = q.counts[o.id] || 0; const percent = q.answered ? Math.round(count / q.answered * 100) : 0; return <div key={o.id}><div className="flex justify-between gap-3 text-sm"><span>{o.label}</span><span className="shrink-0">{count} ({percent}%)</span></div><div className="mt-1 h-2 rounded bg-gray-100 dark:bg-gray-700"><div className="h-2 rounded bg-bright-cobalt" style={{ width: `${percent}%` }} /></div></div>; })}</div>}</section>)}
      <section className="card space-y-3 p-5"><h2 className="font-semibold">Inzendingen bekijken</h2>{result.data.rows.map((row, i) => <details key={i} className="rounded border p-3 dark:border-gray-700"><summary className="cursor-pointer">Inzending {(page - 1) * 50 + i + 1}{row.respondent ? ` — ${row.respondent.name || row.respondent.email}` : ''}</summary>{row.respondent && <p className="my-2 text-sm">{row.respondent.email}{row.respondent.context ? ` · ${row.respondent.context}` : ''}</p>}<dl className="mt-3 space-y-3">{result.data.summary.filter((q) => Object.hasOwn(row.answers, q.id)).map((q) => <div key={q.id}><dt className="text-sm font-medium">{q.label}</dt><dd className="whitespace-pre-wrap text-sm">{q.type === 'text' ? row.answers[q.id] : [row.answers[q.id]].flat().map((v) => q.options.find((o) => o.id === v)?.label || v).join(', ')}</dd></div>)}</dl></details>)}<div className="flex items-center gap-3"><button className="btn-secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Vorige</button><span>{page} / {Math.max(1, result.data.pages)}</span><button className="btn-secondary" disabled={page >= result.data.pages} onClick={() => setPage(page + 1)}>Volgende</button></div></section>
    </>}
  </div>;
}

function Sharing({ survey }) {
  const [qr, setQr] = useState('');
  useEffect(() => { let active = true; QRCode.toDataURL(survey.url, { width: 240 }).then((value) => { if (active) setQr(value); }).catch(() => {}); return () => { active = false; }; }, [survey.url]);
  return <div className="card space-y-3 p-5"><h2 className="font-semibold">Enquête delen</h2>{survey.access === 'invitation' ? <p>Deze enquête is alleen via persoonlijke uitnodigingen toegankelijk.</p> : <><p className="text-sm">De algemene link vraagt geen account. Eén inzending per persoon is bij deze link niet afdwingbaar.</p><a className="block break-all text-bright-cobalt underline" href={survey.url} target="_blank" rel="noreferrer">{survey.url}</a>{qr && <a href={qr} download={`enquete-${survey.id}-qr.png`} className="inline-block"><img src={qr} alt="QR-code voor de enquête — klik om te downloaden" width="240" height="240" /><span className="text-sm underline">QR-code downloaden</span></a>}</>}</div>;
}

function Detail({ survey, updatedAt }) {
  const client = useQueryClient();
  const navigate = useNavigate();
  const [tab, setTab] = useState(survey.can_manage ? 'edit' : 'results');
  const [notice, setNotice] = useState('');
  const refresh = () => { client.invalidateQueries({ queryKey: ['survey', survey.id] }); client.invalidateQueries({ queryKey: ['surveys'] }); };
  const save = useMutation({ mutationFn: (draft) => api.put(`${base}/${survey.id}`, draft), onSuccess: () => { setNotice('Concept opgeslagen. Gebruik Publiceren om de wijzigingen beschikbaar te maken.'); refresh(); } });
  const action = useMutation({ mutationFn: async (name) => (await api.post(`${base}/${survey.id}/action`, { action: name })).data, onSuccess: (data, name) => { if (name === 'duplicate') navigate(`/communicatie/enquetes/${data.id}`); else { setNotice(name === 'publish' ? 'Een nieuwe vragenlijstversie is gepubliceerd.' : 'De enquête is gesloten.'); refresh(); } } });
  const tabs = [...(survey.can_manage ? [['edit', 'Bewerken'], ['preview', 'Voorbeeld'], ['share', 'Delen'], ['invitations', 'Uitnodigingen']] : []), ...(survey.can_results ? [['results', 'Resultaten']] : [])];
  return <div className="space-y-5"><Link to="/communicatie/enquetes" className="text-sm underline">← Alle enquêtes</Link><div className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-2xl font-semibold">{survey.title}</h1><p className="text-sm text-gray-500">{statusLabel[survey.status]}{survey.closes_at && new Date(survey.closes_at) <= new Date() ? ' · Sluitingsdatum verstreken' : ''}</p></div>{survey.can_manage && <div className="flex flex-wrap gap-2"><button className="btn-secondary" disabled={action.isPending} onClick={() => action.mutate('duplicate')}>Dupliceren</button><button className="btn-secondary" disabled={action.isPending || survey.status !== 'published'} onClick={() => action.mutate('close')}>Sluiten</button><button className="btn-primary" disabled={action.isPending || save.isPending} onClick={() => { if (window.confirm('Het opgeslagen concept publiceren als nieuwe versie? Sla eventuele wijzigingen eerst op.')) action.mutate('publish'); }}>Publiceren</button></div>}</div>
    <ErrorMessage error={save.error || action.error} />{notice && <p role="status" className="rounded-lg bg-cyan-50 p-3 text-cyan-950">{notice}</p>}
    <nav aria-label="Enquêteonderdelen" className="flex flex-wrap gap-2 border-b pb-3 dark:border-gray-700">{tabs.map(([value, label]) => <button key={value} className={tab === value ? 'btn-primary' : 'btn-tertiary'} aria-current={tab === value ? 'page' : undefined} onClick={() => setTab(value)}>{label}</button>)}</nav>
    {survey.can_manage && <div hidden={tab !== 'edit'}><Editor key={updatedAt} survey={survey} saved={save.mutate} saving={save.isPending} /></div>}
    {tab === 'preview' && <div className="space-y-3"><p className="text-sm">Dit voorbeeld gebruikt het opgeslagen concept. Doorloop alle routes om de vragenlijst te controleren.</p><iframe title="Voorbeeld van de enquête" src={`${survey.url}?preview=1`} className="h-[75vh] w-full rounded-lg border dark:border-gray-700" /></div>}
    {tab === 'share' && <Sharing survey={survey} />}{tab === 'invitations' && <Invitations survey={survey} />}{tab === 'results' && <Results key={survey.current_version} survey={survey} />}
  </div>;
}

export default function SurveysPage() {
  useDocumentTitle('Enquêtes');
  const { id } = useParams();
  const navigate = useNavigate();
  const user = useCurrentUser();
  const list = useQuery(query(['surveys'], base, !id));
  const detail = useQuery(query(['survey', Number(id)], `${base}/${id}`, !!id));
  const create = useMutation({ mutationFn: async (template) => (await api.post(base, template ? { template } : { title: 'Nieuwe enquête', document: { sections: [{ ...section(), title: 'Algemeen', questions: [{ ...question(), label: 'Nieuwe vraag', type: 'text', options: [] }] }] } })).data, onSuccess: (data) => navigate(`/communicatie/enquetes/${data.id}`) });
  if (id) return detail.data ? <Detail key={id} survey={detail.data} updatedAt={detail.dataUpdatedAt} /> : <><ErrorMessage error={detail.error} />{detail.isPending && <p>Enquête laden…</p>}</>;
  return <div className="space-y-5"><div className="flex flex-wrap items-center justify-between gap-3"><h1 className="text-2xl font-semibold">Enquêtes</h1>{user.data?.can_access_enquetes && <div className="flex gap-2"><button className="btn-secondary" disabled={create.isPending} onClick={() => create.mutate('volunteers')}>Vrijwilligersenquête gebruiken</button><button className="btn-primary" disabled={create.isPending} onClick={() => create.mutate('')}>Nieuwe enquête</button></div>}</div><p className="text-gray-500">Maak vragenlijsten, nodig deelnemers uit en bekijk de resultaten. Dupliceer een enquête om deze opnieuw te gebruiken.</p><ErrorMessage error={list.error || create.error} />{list.isPending && <p>Enquêtes laden…</p>}{list.data?.length === 0 && <div className="card p-6">Er zijn nog geen enquêtes.</div>}{list.data?.map((s) => <Link key={s.id} to={`/communicatie/enquetes/${s.id}`} className="card block p-5 hover:shadow-md"><h2 className="font-semibold">{s.title}</h2><p className="text-sm text-gray-500">{statusLabel[s.status]} · {s.linked ? 'Met persoonskoppeling' : 'Antwoorden zonder naam'}{s.closes_at ? ` · Tot ${new Date(s.closes_at).toLocaleString('nl-NL')}` : ''}</p></Link>)}</div>;
}
