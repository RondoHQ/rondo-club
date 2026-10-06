import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useBeforeUnload, useBlocker, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, ArrowRight, Check, ExternalLink, Loader2, Monitor, Smartphone, Plus, Settings2, Trash2 } from 'lucide-react';
import RichTextEditor from '@/components/RichTextEditor';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { newsletterKeys, useNewsletter, useNewsletterLists, useNewsletterMetadata, useNewsletterSegments } from '@/hooks/useNewsletter';
import { prmApi } from '@/api/client';

const errorText = (error) => error?.response?.data?.message || 'Dit is niet gelukt. Probeer het opnieuw.';
const statusLabels = { local: 'Concept in Rondo', exported: 'Concept staat in Laposta', changed: 'Wijzigingen nog niet in Laposta', attention: 'Vorige export vraagt controle' };
const sectionClass = 'space-y-4 border-b border-gray-200 py-6 first:pt-0 last:border-0 dark:border-gray-700';
const labelClass = 'block text-sm font-medium text-gray-800 dark:text-gray-100';

function AudienceRow({ row, lists, index, onChange, onRemove }) {
  const segments = useNewsletterSegments(row.list_id, row.scope === 'segment');
  const prefix = `audience-${index}`;
  return <div className="space-y-3 border-t border-gray-200 pt-4 first:border-0 first:pt-0 dark:border-gray-700">
    <div className="flex items-end gap-2">
      <label className={`${labelClass} min-w-0 flex-1`} htmlFor={`${prefix}-list`}>Lijst<select id={`${prefix}-list`} className="input mt-1 w-full" value={row.list_id} onChange={(e) => onChange({ list_id: e.target.value, scope: '', segment_id: '' })}>
        <option value="">Kies een lijst</option>
        {row.list_id && !lists.some((l) => l.id === row.list_id) && <option value={row.list_id}>Lijst niet beschikbaar</option>}
        {lists.map((list) => <option key={list.id} value={list.id}>{list.name}</option>)}
      </select></label>
      <button type="button" className="btn-tertiary min-h-11 min-w-11 justify-center" aria-label={`Doelgroep ${index + 1} verwijderen`} onClick={onRemove}><Trash2 className="h-4 w-4" /></button>
    </div>
    {row.list_id && <fieldset className="space-y-2"><legend className="mb-1 text-sm font-medium">Ontvangers binnen deze lijst</legend>
      <label className="flex min-h-9 items-center gap-2 text-sm"><input type="radio" name={`${prefix}-scope`} checked={row.scope === 'segment'} onChange={() => onChange({ ...row, scope: 'segment', segment_id: '' })} />Een segment</label>
      {row.scope === 'segment' && <div className="pl-6"><label className="sr-only" htmlFor={`${prefix}-segment`}>Segment</label><select id={`${prefix}-segment`} className="input w-full" disabled={segments.isPending || segments.isError} value={row.segment_id} onChange={(e) => onChange({ ...row, segment_id: e.target.value })}>
        <option value="">{segments.isPending ? 'Segmenten laden…' : 'Kies een segment'}</option>
        {row.segment_id && !segments.data?.some((s) => s.id === row.segment_id) && <option value={row.segment_id}>Segment niet beschikbaar</option>}
        {segments.data?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
      </select>{segments.isError && <p role="alert" className="mt-2 text-sm text-red-700 dark:text-red-300">{errorText(segments.error)} <button type="button" className="underline" onClick={() => segments.refetch()}>Opnieuw laden</button></p>}
      {segments.data?.length === 0 && <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">Deze lijst heeft geen segmenten.</p>}</div>}
      <label className="flex min-h-9 items-center gap-2 text-sm"><input type="radio" name={`${prefix}-scope`} checked={row.scope === 'all'} onChange={() => onChange({ ...row, scope: 'all', segment_id: '' })} />De hele lijst</label>
    </fieldset>}
  </div>;
}

function NewsletterEditor({ initial, metadata }) {
  const client = useQueryClient();
  const { data: user } = useCurrentUser();
  const [saved, setSaved] = useState(initial);
  const [draft, setDraft] = useState(initial.fields);
  const [previewDraft, setPreviewDraft] = useState(initial.fields);
  const [review, setReview] = useState(null);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [mobileTab, setMobileTab] = useState('edit');
  const [previewSize, setPreviewSize] = useState('desktop');
  const noticeRef = useRef(null);
  const lists = useNewsletterLists(metadata.configured);
  const dirty = JSON.stringify(draft) !== JSON.stringify(saved.fields);
  const profileUser = metadata.users.find((u) => u.id === draft.assignee_id);
  const profile = profileUser?.profile;
  const nameParts = (profile?.name || '').trim().split(/\s+/);
  const initials = nameParts.length ? `${nameParts[0]?.[0] || ''}${nameParts.length > 1 ? nameParts.at(-1)[0] : ''}`.toUpperCase() : '';
  const blocker = useBlocker(dirty || Boolean(busy));
  useBeforeUnload(useCallback((e) => { if (dirty || busy) { e.preventDefault(); e.returnValue = ''; } }, [dirty, busy]));
  useEffect(() => {
    if (blocker.state === 'blocked') {
      if (!busy && window.confirm('Je hebt niet-opgeslagen wijzigingen. Wil je deze pagina toch verlaten?')) blocker.proceed();
      else blocker.reset();
    }
  }, [blocker, busy]);
  useEffect(() => { const timer = setTimeout(() => setPreviewDraft(draft), 400); return () => clearTimeout(timer); }, [draft]);
  useEffect(() => { if (error) noticeRef.current?.focus(); }, [error]);
  const preview = useQuery({
    queryKey: ['newsletter', 'preview', initial.id, previewDraft],
    queryFn: async ({ signal }) => (await prmApi.previewNewsletter(initial.id, previewDraft, signal)).data,
    enabled: Boolean(profileUser?.ready), retry: false, gcTime: 0,
  });

  function change(key, value) { setDraft((previous) => ({ ...previous, [key]: value })); setReview(null); setMessage(''); }
  function refresh(data) {
    setSaved(data); setDraft(data.fields); client.setQueryData(newsletterKeys.detail(String(initial.id)), data);
    client.invalidateQueries({ queryKey: ['communications'] });
  }
  async function save() {
    if (!dirty) return saved;
    const fields = Object.fromEntries(Object.entries(draft).filter(([key, value]) => JSON.stringify(value) !== JSON.stringify(saved.fields[key])));
    const { data } = await prmApi.saveNewsletter(initial.id, { fields, revision: saved.revision });
    refresh(data); return data;
  }
  async function perform(action) {
    if (busy) return;
    setBusy(action); setError(''); setMessage('');
    try {
      if (action === 'export') {
        const { data } = await prmApi.exportNewsletter(initial.id, review.token);
        refresh(data); setReview(null); setMessage('Het concept is opgeslagen en gecontroleerd in Laposta. Je kunt daar testen, inplannen en versturen.');
      } else {
        const data = await save();
        if (action === 'review') {
          setReview((await prmApi.reviewNewsletter(initial.id, data.revision)).data); setMobileTab('edit');
        } else setMessage('Concept opgeslagen in Rondo.');
      }
    } catch (err) {
      setError(errorText(err));
      if (action === 'export') {
        setReview(null);
        try { const { data } = await prmApi.getNewsletter(initial.id); setSaved(data); } catch { /* Keep the current draft when status refresh fails. */ }
      }
    } finally { setBusy(''); }
  }

  const html = review?.html || preview.data?.html;
  return <div className="mx-auto max-w-[1560px] space-y-5 text-gray-900 dark:text-gray-100">
    <Link to={`/communicatie/planning?item=${initial.id}`} className="inline-flex min-h-9 items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"><ArrowLeft className="h-4 w-4" />Terug naar planning</Link>
    <header className="flex flex-wrap items-start justify-between gap-4">
      <div className="min-w-0"><h1 className="text-2xl font-bold sm:text-3xl">Nieuwsbrief</h1><p className="mt-1 break-words text-gray-600 dark:text-gray-300">{initial.title}</p></div>
      <div className="flex flex-wrap items-center gap-3 text-sm"><span role="status" className="text-gray-600 dark:text-gray-300">{dirty ? 'Niet-opgeslagen wijzigingen' : statusLabels[saved.status]}</span>{user?.is_admin && <Link to="/communicatie/nieuwsbrief-instellingen" className="btn-tertiary min-h-11" aria-label="Nieuwsbriefinstellingen"><Settings2 className="h-4 w-4" /></Link>}</div>
    </header>
    {!metadata.configured && <p className="rounded-lg bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">De Laposta-verbinding en template moeten nog worden ingesteld. {user?.is_admin ? <Link className="underline" to="/communicatie/nieuwsbrief-instellingen">Open instellingen</Link> : 'Vraag een beheerder om dit in te stellen.'}</p>}
    {error && <div ref={noticeRef} tabIndex={-1} role="alert" className="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{error}</div>}
    {message && <div role="status" className="rounded-lg bg-green-50 p-4 text-sm text-green-900 dark:bg-green-950/40 dark:text-green-200">{message}{saved.campaign_url && <a className="ml-2 inline-flex items-center gap-1 underline" href={saved.campaign_url} target="_blank" rel="noreferrer">Open in Laposta<ExternalLink className="h-4 w-4" /></a>}</div>}
    <div className="flex gap-2 lg:hidden" aria-label="Weergave">{[['edit', review ? 'Controleren' : 'Bewerken'], ['preview', 'Voorbeeld']].map(([value, label]) => <button key={value} className={`${mobileTab === value ? 'btn-primary' : 'btn-secondary'} min-h-11 flex-1`} aria-pressed={mobileTab === value} onClick={() => setMobileTab(value)}>{label}</button>)}</div>
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(330px,0.85fr)_minmax(360px,1.15fr)] xl:gap-8">
      <div className={`${mobileTab === 'edit' ? '' : 'hidden'} min-w-0 lg:block`}>
        {review ? <section className="space-y-6 rounded-xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-700 dark:bg-gray-800" aria-labelledby="review-title">
          <div><h2 id="review-title" className="text-xl font-semibold">Controleer je nieuwsbrief</h2><p className="mt-2 text-sm text-gray-600 dark:text-gray-300">Rondo maakt een concept in Laposta. Testen en versturen doe je daarna in Laposta.</p></div>
          <dl className="space-y-4 text-sm"><div><dt className="text-gray-600 dark:text-gray-300">Onderwerp</dt><dd className="mt-1 font-medium break-words">{draft.newsletter_subject}</dd></div><div><dt className="text-gray-600 dark:text-gray-300">Afzender</dt><dd className="mt-1 break-words">{review.profile.from_name}<br />{review.profile.from_email}</dd></div><div><dt className="text-gray-600 dark:text-gray-300">Antwoorden naar</dt><dd className="mt-1 break-words">{review.profile.reply_to}</dd></div><div><dt className="text-gray-600 dark:text-gray-300">Doelgroep</dt><dd className="mt-1 space-y-2">{review.audiences.map((a) => <p key={a.list_id}><strong>{a.list_name}</strong><br />{a.scope === 'all' ? 'De hele lijst' : a.segment_name}</p>)}</dd></div></dl>
          <p className="text-xs text-gray-600 dark:text-gray-300">Doelgroep gecontroleerd om {new Date(review.checked_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })}. Het uiteindelijke aantal ontvangers controleer je in Laposta.</p>
          <div className="flex flex-wrap gap-3"><button disabled={Boolean(busy)} className="btn-primary min-h-11 gap-2" onClick={() => perform('export')}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}{saved.campaign_id ? 'Concept bijwerken in Laposta' : 'Concept maken in Laposta'}</button><button className="btn-secondary min-h-11" disabled={Boolean(busy)} onClick={() => setReview(null)}>Terug naar bewerken</button></div>
        </section> : <>
          <fieldset disabled={Boolean(busy)} className="min-w-0 rounded-xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <section className={sectionClass}><h2 className="text-lg font-semibold">Afzender</h2><label className={labelClass} htmlFor="newsletter-assignee">Verantwoordelijke<select id="newsletter-assignee" className="input mt-1 w-full" value={draft.assignee_id} onChange={(e) => change('assignee_id', Number(e.target.value))}><option value={0}>Kies een verantwoordelijke</option>{metadata.users.map((u) => <option key={u.id} value={u.id}>{u.name}{!u.ready ? ' · profiel ontbreekt' : ''}</option>)}</select></label>
              {profileUser?.ready ? <div className="text-sm"><p className="font-medium">{profile.from_name}</p><p className="mt-1 break-words text-gray-600 dark:text-gray-300">{profile.from_email}</p><p className="mt-1 break-words text-gray-600 dark:text-gray-300">Antwoorden naar: {profile.reply_to}</p><p className="mt-1 text-gray-600 dark:text-gray-300">Handtekening: {profile.name}, {profile.role}</p></div> : <p className="text-sm text-gray-600 dark:text-gray-300">Kies een verantwoordelijke met een ingesteld ondertekeningsprofiel.</p>}
            </section>
            <section className={sectionClass}><h2 className="text-lg font-semibold">Doelgroep</h2><p className="text-sm text-gray-600 dark:text-gray-300">Kies een Laposta-lijst en bepaal wie daarbinnen de nieuwsbrief ontvangt.</p>
              {lists.isLoading && <p role="status" className="text-sm">Lijsten laden…</p>}
              {lists.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{errorText(lists.error)} <button type="button" className="underline" onClick={() => lists.refetch()}>Opnieuw laden</button></p>}
              {draft.newsletter_audiences.map((row, index) => <AudienceRow key={index} index={index} row={row} lists={lists.data || []} onChange={(value) => change('newsletter_audiences', draft.newsletter_audiences.map((r, i) => i === index ? value : r))} onRemove={() => change('newsletter_audiences', draft.newsletter_audiences.filter((_, i) => i !== index))} />)}
              {!draft.newsletter_audiences.length && <p className="text-sm text-gray-600 dark:text-gray-300">Nog geen doelgroep gekozen.</p>}
              <button type="button" className="btn-secondary min-h-11 gap-2" disabled={!lists.data?.length || draft.newsletter_audiences.length >= 10} onClick={() => change('newsletter_audiences', [...draft.newsletter_audiences, { list_id: '', scope: '', segment_id: '' }])}><Plus className="h-4 w-4" />Doelgroep toevoegen</button>
            </section>
            <section className={sectionClass}><h2 className="text-lg font-semibold">Inhoud</h2>
              <label className={labelClass} htmlFor="newsletter-subject">Onderwerp<input id="newsletter-subject" className="input mt-1 w-full" maxLength={200} value={draft.newsletter_subject} onChange={(e) => change('newsletter_subject', e.target.value)} placeholder="Wat ziet de lezer in de inbox?" /></label>
              <label className={labelClass} htmlFor="newsletter-preheader">Voorbeeldtekst <span className="font-normal text-gray-600 dark:text-gray-300">(optioneel)</span><input id="newsletter-preheader" className="input mt-1 w-full" maxLength={250} value={draft.newsletter_preheader} onChange={(e) => change('newsletter_preheader', e.target.value)} /><span className="mt-1 block text-xs font-normal text-gray-600 dark:text-gray-300">De korte tekst naast het onderwerp in de inbox.</span></label>
              <label className={labelClass} htmlFor="newsletter-heading">Titel in de nieuwsbrief<input id="newsletter-heading" className="input mt-1 w-full" maxLength={200} value={draft.newsletter_heading} onChange={(e) => change('newsletter_heading', e.target.value)} /></label>
              <div className="newsletter-editor" role="group" aria-label="Bericht"><p className={`${labelClass} mb-1`}>Bericht</p><RichTextEditor value={draft.newsletter_body} onChange={(value) => change('newsletter_body', value)} placeholder="Schrijf je bericht…" minHeight="260px" disabled={Boolean(busy)} ariaLabel="Bericht" enableHeadings enableImages imageSpacing={12} /></div>
            </section>
          </fieldset>
          <div className="sticky bottom-0 z-10 mt-4 flex flex-wrap gap-3 border-t border-gray-200 bg-gray-50 py-4 dark:border-gray-700 dark:bg-gray-900"><button className="btn-secondary min-h-11" disabled={Boolean(busy) || !dirty} onClick={() => perform('save')}>{busy === 'save' ? 'Opslaan…' : 'Concept opslaan'}</button><button className="btn-primary min-h-11 gap-2" disabled={Boolean(busy) || !metadata.configured || !profileUser?.ready} onClick={() => perform('review')}>{busy === 'review' ? 'Controleren…' : 'Controleren'}<ArrowRight className="h-4 w-4" /></button></div>
        </>}
        {saved.campaign_url && !message && <p className="mt-4 text-sm"><a className="inline-flex items-center gap-1 underline" href={saved.campaign_url} target="_blank" rel="noreferrer">Bestaand concept in Laposta<ExternalLink className="h-4 w-4" /></a></p>}
      </div>
      <aside className={`${mobileTab === 'preview' ? '' : 'hidden'} min-w-0 lg:sticky lg:top-5 lg:block`} aria-label="Nieuwsbriefvoorbeeld">
        <div className="mb-3 flex items-center justify-between gap-3"><h2 className="text-lg font-semibold">Voorbeeld</h2><span className="text-xs text-gray-600 dark:text-gray-300">{preview.isFetching ? 'Bijwerken…' : 'Weergave kan per mailprogramma verschillen'}</span></div>
        <div className="mb-3 hidden gap-2 lg:flex" aria-label="Voorbeeldformaat"><button className={`${previewSize === 'desktop' ? 'btn-primary' : 'btn-secondary'} min-h-11 gap-2`} aria-pressed={previewSize === 'desktop'} onClick={() => setPreviewSize('desktop')}><Monitor className="h-4 w-4" />Desktop</button><button className={`${previewSize === 'mobile' ? 'btn-primary' : 'btn-secondary'} min-h-11 gap-2`} aria-pressed={previewSize === 'mobile'} onClick={() => setPreviewSize('mobile')}><Smartphone className="h-4 w-4" />Mobiel</button></div>
        <div className={`mx-auto overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 ${previewSize === 'mobile' ? 'max-w-[375px]' : 'w-full'}`}>
          <div className="space-y-1 border-b border-gray-200 p-4 text-sm text-gray-900"><div className="mb-3 flex items-center gap-3"><span aria-hidden="true" className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700">{initials || '–'}</span><div className="min-w-0"><p className="break-words font-medium">{profile?.from_name || 'Afzender'}</p><p className="break-words text-xs text-gray-600">{profile?.from_email || 'Kies een verantwoordelijke'}</p></div></div><p className="break-words font-semibold">{draft.newsletter_subject || 'Onderwerp van je nieuwsbrief'}</p><p className="break-words text-gray-600">{draft.newsletter_preheader || 'Voorbeeldtekst voor de inbox'}</p></div>
          {html && (review || !preview.isError) ? <iframe className="block h-[70vh] min-h-[500px] w-full bg-white" title="Voorbeeld van de nieuwsbrief" sandbox="" referrerPolicy="no-referrer" srcDoc={html} /> : <p role="status" className="p-8 text-sm text-gray-600">{preview.isError ? errorText(preview.error) : 'Het voorbeeld verschijnt zodra de verbinding en het ondertekeningsprofiel zijn ingesteld.'}</p>}
        </div>
      </aside>
    </div>
  </div>;
}

export default function Newsletter() {
  useDocumentTitle('Nieuwsbrief');
  const { id } = useParams();
  const draft = useNewsletter(id);
  const metadata = useNewsletterMetadata();
  if (draft.isPending || metadata.isPending) return <p role="status" className="p-6 text-gray-600 dark:text-gray-300">Nieuwsbrief laden…</p>;
  if (draft.isError || metadata.isError) return <div className="space-y-4 p-6"><p role="alert" className="text-red-700 dark:text-red-300">{errorText(draft.error || metadata.error)}</p><button className="btn-secondary" onClick={() => { draft.refetch(); metadata.refetch(); }}>Opnieuw laden</button><Link className="ml-4 underline" to="/communicatie/planning">Terug naar planning</Link></div>;
  return <NewsletterEditor key={id} initial={draft.data} metadata={metadata.data} />;
}
