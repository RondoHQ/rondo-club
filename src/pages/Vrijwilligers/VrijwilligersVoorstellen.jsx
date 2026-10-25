import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { formatStoredDateTime } from '@/utils/dateFormat';

const phases = { draft: 'Concept', partial: 'Deels gepubliceerd / open plekken', published: 'Gepubliceerd', closed: 'Afgesloten' };
const date = (value) => value ? formatStoredDateTime(value, 'EEE d MMM yyyy, HH:mm') : 'Dienst ontbreekt';

function CandidateEditor({ plan, row, onSaved, onClose }) {
  const [person, setPerson] = useState(String(row.person_id));
  const [search, setSearch] = useState('');
  const { data: candidates = [], isPending, error, refetch } = useQuery({
    queryKey: ['volunteer', 'plan-candidates', plan.id, row.index, plan.revision],
    queryFn: async () => (await prmApi.getVolunteerPlanCandidates(plan.id, row.index)).data,
    staleTime: 0,
  });
  const save = useMutation({
    mutationFn: () => prmApi.editVolunteerPlan(plan.id, row.index, { revision: plan.revision, person_id: Number(person) }),
    onSuccess: (response) => onSaved(response.data),
  });
  const visible = candidates.filter((candidate) => candidate.name.toLocaleLowerCase('nl').includes(search.toLocaleLowerCase('nl')));
  return (
    <div className="space-y-3 mt-3 border-t border-gray-200 dark:border-gray-700 pt-3">
      {isPending ? <p role="status">Beschikbaarheid controleren…</p> : error ? <p role="alert">Kandidaten ophalen mislukt. <button type="button" className="underline" onClick={() => refetch()}>Opnieuw proberen</button></p> : <>
        <label className="label">Zoek een kandidaat<input type="search" className="input mt-1" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        <label className="label">Kandidaat<select className="input mt-1" value={person} onChange={(event) => setPerson(event.target.value)}>
          <option value="0">Open laten</option>
          {visible.map((candidate) => <option key={`${candidate.unit}-${candidate.person_id}`} value={candidate.person_id}>{candidate.name} · {candidate.remaining} diensten open</option>)}
        </select></label>
        {!visible.length && <p className="text-sm text-amber-700 dark:text-amber-300">Geen beschikbare kandidaten met deze zoekopdracht en de huidige vereisten.</p>}
        <p className="text-sm text-gray-500">Alleen kandidaten met een open verplichting, bereikbaar e-mailadres, geldige vereisten en gecontroleerde beschikbaarheid worden getoond.</p>
      </>}
      <div className="flex gap-2"><button type="button" className="btn-primary" disabled={isPending || !!error || save.isPending} onClick={() => save.mutate()}>Keuze opslaan</button><button type="button" className="btn-secondary" onClick={onClose}>Sluiten</button></div>
      {save.error && <p role="alert" className="text-red-700 dark:text-red-300">{save.error.response?.data?.message || 'Opslaan mislukt; ververs het voorstel.'}</p>}
    </div>
  );
}

export default function VrijwilligersVoorstellen() {
  useDocumentTitle('Indelingsvoorstellen — Vrijwilligers');
  const client = useQueryClient();
  const [params, setParams] = useSearchParams();
  const [editor, setEditor] = useState(null);
  const [acceptConversions, setAcceptConversions] = useState(false);
  const [message, setMessage] = useState('');
  const list = useQuery({ queryKey: ['volunteer', 'plans'], queryFn: async () => (await prmApi.getVolunteerPlans()).data });
  const id = Number(params.get('plan') || list.data?.plans?.[0]?.id || 0);
  const queryKey = ['volunteer', 'plan', id];
  const detail = useQuery({ queryKey, queryFn: async () => (await prmApi.getVolunteerPlan(id)).data, enabled: id > 0, staleTime: 0 });
  const plan = detail.data;
  const receive = (data) => {
    client.setQueryData(['volunteer', 'plan', data.id], data);
    client.invalidateQueries({ queryKey: ['volunteer'] });
    setEditor(null); setAcceptConversions(false);
  };
  const create = useMutation({ mutationFn: () => prmApi.createVolunteerPlan(), onSuccess: (response) => {
    setParams({ plan: response.data.id }); receive(response.data); setMessage('Nieuw voorstel gemaakt. Er zijn nog geen diensten toegewezen aan leden.');
  } });
  const publish = useMutation({ mutationFn: async () => {
    let current = plan;
    let response;
    do {
      response = await prmApi.publishVolunteerPlan(id, { revision: current.revision, accept_conversions: acceptConversions });
      current = response.data;
      client.setQueryData(['volunteer', 'plan', id], current);
    } while (current.publish_more);
    return response;
  }, onSuccess: (response) => {
    receive(response.data); setMessage(response.data.phase === 'published' ? 'De indelingen zijn gepubliceerd. De toewijzingsmails staan klaar voor verzending.' : 'De geldige indelingen zijn gepubliceerd. Controleer de resterende open regels en eventuele gewijzigde beschikbaarheid.');
    client.invalidateQueries({ queryKey: ['people'] });
  } });
  const close = useMutation({ mutationFn: () => prmApi.closeVolunteerPlan(id, { revision: plan.revision }), onSuccess: (response) => { receive(response.data); setMessage('Voorstel afgesloten. Gepubliceerde indelingen blijven staan.'); } });
  const writable = plan && ['draft', 'partial'].includes(plan.phase);
  const pending = plan?.rows.filter((row) => ['pending', 'publishing'].includes(row.state) && row.person_id > 0) || [];
  const conversions = pending.filter((row) => row.conversion);
  const busy = create.isPending || publish.isPending || close.isPending;
  const actionError = create.error || publish.error || close.error;

  return (
    <div className="space-y-6">
      <Link to="/vrijwilligers" className="text-sm hover:underline">← Terug naar vrijwilligers</Link>
      <header className="flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-xl font-semibold">Indelingsvoorstellen</h1><p className="text-sm text-gray-600 dark:text-gray-300 mt-1">Elke woensdag een voorstel voor vier kalenderweken vooruit, inclusief open plekken op kortere termijn. Pas na jouw akkoord worden diensten toegewezen.</p></div><button type="button" className="btn-primary" disabled={busy} onClick={() => create.mutate()}>{create.isPending ? 'Voorstel berekenen…' : 'Nieuw voorstel / gaten aanvullen'}</button></header>
      {list.data && <p className="text-sm text-gray-600 dark:text-gray-300">Volgende automatische ronde: {date(list.data.next_at)} · Berichten naar {list.data.notification_email || 'geen adres ingesteld'}. Het adres is instelbaar op het vrijwilligersdashboard.</p>}
      {message && <p role="status" className="card p-4 text-green-800 dark:text-green-300">{message}</p>}
      {actionError && <p role="alert" className="card p-4 text-red-700 dark:text-red-300">{actionError.response?.data?.message || 'Verwerking mislukt. Ververs om de actuele status te bekijken.'}</p>}
      {(list.error || detail.error) && <p role="alert" className="card p-4 text-red-700 dark:text-red-300">Voorstellen ophalen mislukt. <button type="button" className="underline" onClick={() => { list.refetch(); detail.refetch(); }}>Opnieuw proberen</button></p>}
      {list.isPending && <p role="status">Voorstellen laden…</p>}
      {list.data?.plans?.length > 0 && <label className="label max-w-xl">Voorstel<select className="input mt-1" value={id} disabled={busy} onChange={(event) => { setParams({ plan: event.target.value }); setEditor(null); setMessage(''); setAcceptConversions(false); }}>
        {list.data.plans.map((item) => <option key={item.id} value={item.id}>{date(item.created_at)} · {phases[item.phase]}</option>)}
      </select></label>}
      {id > 0 && detail.isPending && <p role="status">Indeling laden…</p>}
      {plan && <>
        <section className="card p-5 space-y-3"><h2 className="font-semibold">{phases[plan.phase]} · {plan.from} t/m {plan.until}</h2>
          <p>{plan.coverage.filled} van {plan.coverage.places} plekken definitief bezet · {pending.length} toewijzingen in dit voorstel.</p>
          {(plan.coverage.filled < plan.coverage.places || plan.coverage.unlimited > 0) && <p className="text-amber-700 dark:text-amber-300">Het rooster is nog niet volledig bezet. {plan.coverage.unlimited > 0 && `${plan.coverage.unlimited} diensten hebben onbegrensde capaciteit; stel daar eerst een aantal plekken in.`}</p>}
          <p className="text-sm text-gray-600 dark:text-gray-300">Wedstrijden: twee uur marge voor en na. Trainingen: dertig minuten marge. Ontbrekende of verouderde beschikbaarheidsgegevens geven geen automatische toewijzing. Toegewezen diensten kunnen tot drie weken vooraf worden afgemeld; vrijgekomen plekken komen opnieuw in een voorstel.</p>
          {writable && <>
            {conversions.length > 0 && <label className="flex gap-2 text-sm text-amber-800 dark:text-amber-300"><input type="checkbox" checked={acceptConversions} onChange={(event) => setAcceptConversions(event.target.checked)} />Ik ga expliciet akkoord met de hieronder gemarkeerde omzettingen van bar naar luik.</label>}
            <div className="flex flex-wrap gap-2"><button type="button" className="btn-primary" disabled={busy || !pending.length || (conversions.length > 0 && !acceptConversions)} onClick={() => publish.mutate()}>{publish.isPending ? 'Publiceren…' : `Akkoord en ${pending.length} toewijzingen publiceren`}</button><button type="button" className="btn-secondary" disabled={busy} onClick={() => { detail.refetch(); }}>Verversen</button><button type="button" className="btn-secondary" disabled={busy} onClick={() => close.mutate()}>Voorstel afsluiten</button></div>
            <p className="text-xs text-gray-500">Publiceren controleert alle regels opnieuw. Gewijzigde of onmogelijke regels blijven open; gepubliceerde regels worden bij opnieuw klikken niet nogmaals verstuurd. Afsluiten verwijdert alleen de nog niet gepubliceerde voorstellen.</p>
          </>}
        </section>
        <section className="card divide-y divide-gray-200 dark:divide-gray-700" aria-label="Voorgestelde indelingen">
          {!plan.rows.length && <p className="p-5">Geen nieuwe toewijzingen nodig. Open plekken kunnen al in een ander conceptvoorstel staan.</p>}
          {plan.rows.map((row) => <div key={row.index} className="p-4 space-y-2">
            <div className="flex flex-wrap justify-between gap-3"><div><Link to={`/vrijwilligers/diensten/${row.shift_id}`} className="font-medium hover:underline">{row.title}</Link><p className="text-sm">{date(row.start)} – {row.end?.slice(11, 16)}</p></div><span className="text-sm">{row.state === 'published' ? 'Gepubliceerd' : row.state === 'publishing' ? 'Verwerking controleren' : row.person_id ? 'Voorgesteld' : 'Open plek'}</span></div>
            {row.conversion && <p className="text-sm font-semibold text-amber-700 dark:text-amber-300">Voorgestelde omzetting: {row.title} → {row.target_title}</p>}
            <p>{row.person_name || 'Geen kandidaat gekozen'}</p>
            {row.issue && <p className="text-sm text-amber-700 dark:text-amber-300">{row.issue}</p>}
            {Object.keys(row.diagnostics).length > 0 && <p className="text-xs text-gray-500">Blokkades: {Object.entries(row.diagnostics).map(([reason, count]) => `${reason}: ${count}`).join(' · ')}</p>}
            {writable && row.state === 'pending' && <button type="button" className="btn-secondary text-sm" disabled={busy} onClick={() => setEditor(editor === row.index ? null : row.index)}>Kandidaat aanpassen</button>}
            {editor === row.index && <CandidateEditor key={`${id}-${row.index}-${plan.revision}`} plan={plan} row={row} onSaved={receive} onClose={() => setEditor(null)} />}
          </div>)}
        </section>
      </>}
    </div>
  );
}
