import { useState } from 'react';
import { Link } from 'react-router-dom';
import { CheckCircle2, AlertTriangle } from 'lucide-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { VOG_SUBMISSION_LABELS, refreshVog } from '@/utils/vog';
import { format } from '@/utils/dateFormat';

const identityMethods = {
  original_id: 'Origineel identiteitsbewijs persoonlijk gezien',
  verified_records: 'Eerder gecontroleerde identiteitsgegevens geraadpleegd',
};
const showValue = row => value => !value ? '—' : row.key === 'birthdate' ? format(value, 'd MMMM yyyy') : value;

function Submission({ item, onApproved }) {
  const client = useQueryClient();
  const [note, setNote] = useState(item.note || '');
  const [date, setDate] = useState(item.parsed?.date || '');
  const [confirmed, setConfirmed] = useState(false);
  const [identityMethod, setIdentityMethod] = useState('');
  const [identityConfirmed, setIdentityConfirmed] = useState(false);
  const [remember, setRemember] = useState(false);
  const [contactAction, setContactAction] = useState('');
  const [fileError, setFileError] = useState('');
  const assessment = item.assessment;
  const paper = item.source === 'paper';
  const identityRequired = assessment && !assessment.identity_matches;
  const identityChecked = !!identityMethod && identityConfirmed;
  const canApprove = paper ? confirmed && !!date : item.code === 0 && assessment?.content_passed && (!identityRequired || identityChecked) && (!remember || identityChecked);
  const action = useMutation({
    mutationFn: name => name === 'retry' ? prmApi.retryVog(item.id) : prmApi.reviewVog(item.id, {
      action: name, version: item.version, assessment_revision: assessment?.revision,
      note: name === 'approve' ? '' : note, date, confirmed: paper ? confirmed : true,
      method: paper ? 'paper_original' : 'gaav_manual',
      identity_method: identityMethod, identity_confirmed: identityConfirmed, remember_identity: remember,
    }),
    onSuccess: async (response, name) => {
      if (name === 'approve') onApproved({ name: item.name, date: response.data.approved_date, remembered: response.data.identity_remembered, method: identityMethod });
      setContactAction('');
      await refreshVog(client);
    },
  });
  const openFile = async index => {
    const preview = window.open('', '_blank');
    if (preview) { preview.opener = null; preview.document.body.textContent = 'Document laden…'; }
    setFileError('');
    try {
      const response = await prmApi.getVogFile(item.id, index);
      const url = URL.createObjectURL(response.data);
      if (preview) preview.location.replace(url);
      else { const link = document.createElement('a'); link.href = url; link.download = 'vog'; link.click(); }
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch { preview?.close(); setFileError('Het document kan niet worden geopend. Mogelijk is het al verwijderd.'); }
  };
  const startContact = name => {
    setContactAction(name);
    if (!note && name === 'inquire' && identityRequired) setNote('De naam op je VOG wijkt af van je ledenprofiel. We moeten nog bevestigen dat deze VOG bij jou hoort. Laat je originele identiteitsbewijs aan de VOG-coördinator zien; je hoeft geen kopie te uploaden.');
  };
  return (
    <article className="card p-5 space-y-5">
      <div className="flex flex-wrap justify-between gap-2">
        <div><Link to={`/people/${item.person_id}`} className="font-semibold text-bright-cobalt dark:text-electric-cyan">{item.name}</Link><p className="text-sm text-gray-600 dark:text-gray-300">{VOG_SUBMISSION_LABELS[item.status]} · inzending {item.id}</p></div>
        {item.parsed?.date && <p className="text-sm">Afgegeven op {format(item.parsed.date, 'd MMMM yyyy')}</p>}
      </div>
      {item.source === 'digital' && <p className="text-sm">Echtheid: {item.code === 0 ? 'Bevestigd door Justid' : item.status === 'technical' ? 'Controle tijdelijk niet beschikbaar' : item.code === null ? 'Nog niet bevestigd' : 'Niet bevestigd; originele PDF nodig'}</p>}
      <div className="flex flex-wrap gap-2">{item.files?.map((file, index) => <button key={index} className="btn-secondary" type="button" onClick={() => openFile(index)}>Bekijk {file.type === 'application/pdf' ? 'PDF' : `foto ${index + 1}`}</button>)}</div>
      {fileError && <p role="alert" className="text-red-700 dark:text-red-300 text-sm">{fileError}</p>}
      {item.status === 'needs_original' && <p className="text-sm">Vraag de originele PDF uit MijnOverheid. Een scan, screenshot of afdruk van een digitale VOG is onvoldoende om de echtheid vast te stellen.</p>}
      {item.note && <p className="text-sm rounded-lg bg-gray-50 dark:bg-gray-700 p-3">Bericht voor het lid: {item.note}</p>}
      <fieldset disabled={action.isPending} className="space-y-5">
        {assessment && <>
          <section className="space-y-3 border-t border-gray-200 dark:border-gray-600 pt-4">
            <h2 className="font-semibold">Van wie is deze VOG?</h2>
            <div className="space-y-3">
              {assessment.identity_rows.filter(row => row.key !== 'infix' || row.profile || row.document).map(row => <div key={row.key} className="grid sm:grid-cols-[9rem_1fr_1fr] gap-1 sm:gap-4 border-b border-gray-100 dark:border-gray-700 pb-3 text-sm">
                <span className="text-gray-600 dark:text-gray-300">{row.label}</span>
                <div className="min-w-0 break-words"><span className="block text-xs text-gray-500 dark:text-gray-400">Ledenprofiel</span>{showValue(row)(row.profile)}{assessment.verified_at && row.expected !== row.profile && <span className="block mt-1">Bevestigd: {showValue(row)(row.expected)}</span>}</div>
                <div className={`min-w-0 break-words rounded p-2 ${row.matches ? 'bg-gray-50 dark:bg-gray-700' : 'bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-200'}`}><span className="block text-xs">Op de VOG{!row.matches ? ' · wijkt af' : ''}</span>{showValue(row)(row.document)}</div>
              </div>)}
            </div>
            {assessment.verified_at && <p className="text-sm text-gray-600 dark:text-gray-300">Vergeleken met namen die zijn bevestigd op {format(assessment.verified_at, 'd MMMM yyyy')}.</p>}
            {identityRequired ? <>
              <label className="block text-sm">Hoe heb je de identiteit gecontroleerd?
                <select className="input mt-1 w-full" value={identityMethod} onChange={event => { setIdentityMethod(event.target.value); setIdentityConfirmed(false); setRemember(false); }}>
                  <option value="">Kies na de controle…</option>{Object.entries(identityMethods).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
              </label>
              {identityMethod && <>
                <label className="flex items-start gap-2 text-sm"><input className="mt-1" type="checkbox" checked={identityConfirmed} onChange={event => setIdentityConfirmed(event.target.checked)} /><span>Ik bevestig dat deze VOG bij dit lid hoort en dat de volledige voornamen, geboortenaam en geboortedatum overeenkomen met de gecontroleerde identiteit.</span></label>
                <label className="flex items-start gap-2 text-sm"><input className="mt-1" type="checkbox" checked={remember} onChange={event => setRemember(event.target.checked)} /><span>Bewaar de bevestigde namen voor volgende VOG-controles. De naam in de ledenlijst blijft {item.name}.</span></label>
              </>}
              <p className="text-sm text-gray-600 dark:text-gray-300">We registreren de controlemethode, beoordelaar en datum. Geen ID-kopie, BSN of documentnummer.</p>
            </> : <p className="text-sm text-emerald-800 dark:text-emerald-300">Identiteitsgegevens komen overeen.</p>}
          </section>
          <section className="space-y-3 border-t border-gray-200 dark:border-gray-600 pt-4">
            <h2 className="font-semibold">Automatisch gecontroleerd</h2>
            {item.parsed?.purpose && <p className="text-sm">{item.parsed.purpose}</p>}
            <ul className="space-y-2 text-sm">{assessment.content_checks.map(check => <li key={check.key} className={`flex gap-2 ${check.passed ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-900 dark:text-amber-200'}`}>
              {check.passed ? <CheckCircle2 className="size-4 shrink-0 mt-0.5" aria-hidden="true" /> : <AlertTriangle className="size-4 shrink-0 mt-0.5" aria-hidden="true" />}<span>{check.passed ? 'In orde: ' : 'Niet bevestigd: '}{check.label}</span>
            </li>)}</ul>
            {item.parsed?.codes?.length > 0 && <p className="text-sm text-gray-600 dark:text-gray-300">Screeningscodes op de VOG: {item.parsed.codes.join(', ')}.</p>}
            {!assessment.content_passed && <p className="text-sm">Controleer de VOG-instellingen met een beheerder of doe navraag. Deze VOG kan nu niet worden goedgekeurd.</p>}
          </section>
        </>}
        {paper && <>
          <label className="block text-sm">Afgiftedatum<input className="input block mt-1" type="date" value={date} onChange={event => setDate(event.target.value)} /></label>
          <label className="flex items-start gap-2 text-sm"><input className="mt-1" type="checkbox" checked={confirmed} onChange={event => setConfirmed(event.target.checked)} /><span>Ik heb het echte originele papier op echtheid gecontroleerd en persoon, functie, organisatie en screeningsprofiel gecontroleerd. Het is geen afdruk van een digitaal document.</span></label>
          <a className="text-sm underline" href="https://www.justis.nl/producten/verklaring-omtrent-het-gedrag/informatie-over-de-vog-voor-werkgevers-en-organisaties/controleren-van-de-vog" target="_blank" rel="noreferrer">Echtheidskenmerken bij Justis</a>
        </>}
        {contactAction ? <div className="space-y-3">
          <label className="block text-sm">Bericht dat het lid in Rondo ziet<textarea className="input block w-full mt-1" value={note} maxLength={500} onChange={event => setNote(event.target.value)} placeholder="Wat moet het lid doen? Vermeld geen overbodige persoonsgegevens." /></label>
          <p className="text-sm text-gray-600 dark:text-gray-300">{contactAction === 'inquire' ? 'De inzending blijft open. Het bericht verschijnt op Mijn VOG; er wordt geen e-mail verstuurd.' : 'De inzending wordt afgesloten en de upload verwijderd. Een eerder goedgekeurde VOG blijft geldig.'}</p>
          <div className="flex flex-wrap gap-2"><button className="btn-primary" type="button" disabled={note.trim().length < 3} onClick={() => action.mutate(contactAction)}>{contactAction === 'inquire' ? 'Navraag vastleggen' : 'Inzending afwijzen'}</button><button className="btn-secondary" type="button" onClick={() => setContactAction('')}>Annuleren</button></div>
        </div> : <div className="space-y-3">
          {identityRequired && !identityChecked && <p className="text-sm">Bevestig nog de identiteit voordat je deze VOG goedkeurt.</p>}
          <div className="flex flex-wrap gap-2">
            {(paper || assessment) && <button className="btn-primary" type="button" disabled={!canApprove} onClick={() => action.mutate('approve')}>VOG goedkeuren</button>}
            <button className="btn-secondary" type="button" onClick={() => startContact('inquire')}>Eerst navraag doen</button>
            <button className="btn-secondary" type="button" onClick={() => startContact('reject')}>Afwijzen</button>
            {item.status === 'technical' && item.attempts < 5 && <button className="btn-secondary" type="button" onClick={() => action.mutate('retry')}>Opnieuw controleren</button>}
          </div>
        </div>}
      </fieldset>
      {action.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{action.error?.response?.data?.message || 'Verwerken is niet gelukt.'}</p>}
    </article>
  );
}

export default function VOGReview() {
  const [page, setPage] = useState(1);
  const [receipt, setReceipt] = useState(null);
  const query = useQuery({ queryKey: ['vog', 'submissions', page], queryFn: async () => (await prmApi.getVogSubmissions(page)).data });
  return <section className="space-y-4">
    <h1 className="text-xl font-semibold">VOG’s beoordelen</h1>
    {receipt && <div role="status" className="rounded-lg bg-emerald-50 text-emerald-900 dark:bg-emerald-900/20 dark:text-emerald-200 p-4 space-y-1"><p className="font-semibold">VOG van {receipt.name} goedgekeurd</p><p className="text-sm">Afgegeven op {format(receipt.date, 'd MMMM yyyy')}.{receipt.method && ` Identiteit gecontroleerd: ${identityMethods[receipt.method]}.`}</p>{receipt.remembered && <p className="text-sm">De bevestigde namen zijn opgeslagen voor volgende controles. De naam in de ledenlijst blijft gelijk.</p>}</div>}
    {query.isLoading && <p>Inzendingen laden…</p>}
    {query.isError && <p role="alert">De inzendingen konden niet worden geladen.</p>}
    {query.data?.items.length === 0 && <p>Er zijn geen inzendingen om te beoordelen.</p>}
    {query.data?.items.map(item => <Submission key={`${item.id}-${item.version}-${item.assessment?.revision || ''}`} item={item} onApproved={setReceipt} />)}
    <div className="flex gap-3"><button className="btn-secondary" disabled={page <= 1} onClick={() => setPage(old => old - 1)}>Vorige</button><span>Pagina {page}</span><button className="btn-secondary" disabled={!query.data || page >= query.data.pages} onClick={() => setPage(old => old + 1)}>Volgende</button></div>
  </section>;
}
