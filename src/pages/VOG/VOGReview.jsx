import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { VOG_SUBMISSION_LABELS, refreshVog } from '@/utils/vog';

function Submission({ item }) {
  const client = useQueryClient();
  const [note, setNote] = useState('');
  const [date, setDate] = useState(item.parsed?.date || '');
  const [confirmed, setConfirmed] = useState(false);
  const [fileError, setFileError] = useState('');
  const action = useMutation({
    mutationFn: name => name === 'retry' ? prmApi.retryVog(item.id) : prmApi.reviewVog(item.id, { action: name, version: item.version, note, date, confirmed, method: item.source === 'paper' ? 'paper_original' : 'gaav_manual' }),
    onSuccess: () => refreshVog(client),
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
  const canApprove = item.source === 'paper' || item.code === 0;
  return (
    <article className="card p-5 space-y-3">
      <div><Link to={`/people/${item.person_id}`} className="font-semibold text-bright-cobalt dark:text-electric-cyan">{item.name}</Link><p className="text-sm">{VOG_SUBMISSION_LABELS[item.status]}</p></div>
      {item.source === 'digital' && <p className="text-sm">Echtheid: {item.code === 0 ? 'Bevestigd door Justid' : item.status === 'technical' ? 'Controle tijdelijk niet beschikbaar' : item.code === null ? 'Nog niet bevestigd' : 'Niet bevestigd; originele PDF nodig'}</p>}
      {item.parsed?.purpose && <p className="text-sm">{item.parsed.purpose} · screeningscodes {item.parsed.codes?.join(', ')}</p>}
      {item.reasons?.length > 0 && <ul className="list-disc pl-5 text-sm">{item.reasons.map(reason => <li key={reason}>{reason}</li>)}</ul>}
      <div className="flex flex-wrap gap-2">{item.files?.map((file, index) => <button key={index} className="btn-secondary" type="button" onClick={() => openFile(index)}>Bekijk {file.type === 'application/pdf' ? 'PDF' : `foto ${index + 1}`}</button>)}</div>
      {fileError && <p role="alert" className="text-red-700 dark:text-red-300 text-sm">{fileError}</p>}
      {item.status === 'needs_original' && <p className="text-sm">Vraag de originele PDF uit MijnOverheid. Een scan, screenshot of afdruk van een digitale VOG is onvoldoende om de echtheid vast te stellen.</p>}
      <fieldset disabled={action.isPending} className="space-y-3">
        {canApprove && <>
          <label className="block text-sm">Afgiftedatum<input className="input block mt-1" type="date" value={date} readOnly={!!item.parsed?.date} onChange={event => setDate(event.target.value)} /></label>
          <label className="flex items-start gap-2 text-sm"><input className="mt-1" type="checkbox" checked={confirmed} onChange={event => setConfirmed(event.target.checked)} /><span>{item.source === 'paper' ? 'Ik heb het echte originele papier op echtheid gecontroleerd en persoon, functie, organisatie en screeningsprofiel gecontroleerd. Het is geen afdruk van een digitaal document.' : 'Ik heb persoon, functie, organisatie en screeningsprofiel op het originele document gecontroleerd.'}</span></label>
          {item.source === 'paper' && <a className="text-sm underline" href="https://www.justis.nl/producten/verklaring-omtrent-het-gedrag/informatie-over-de-vog-voor-werkgevers-en-organisaties/controleren-van-de-vog" target="_blank" rel="noreferrer">Echtheidskenmerken bij Justis</a>}
        </>}
        <label className="block text-sm">Toelichting voor het lid<textarea className="input block w-full mt-1" value={note} maxLength={500} onChange={event => setNote(event.target.value)} placeholder="Wat is gecontroleerd of wat moet het lid opnieuw aanleveren? Vermeld geen overbodige persoonsgegevens." /></label>
        <div className="flex flex-wrap gap-2">
          {canApprove && <button className="btn-primary" type="button" disabled={!confirmed || !date || note.trim().length < 3} onClick={() => action.mutate('approve')}>{item.source === 'paper' ? 'Papieren origineel gecontroleerd' : 'Goedkeuren'}</button>}
          <button className="btn-secondary" type="button" disabled={note.trim().length < 3} onClick={() => action.mutate('reject')}>Afwijzen</button>
          {item.status === 'technical' && item.attempts < 5 && <button className="btn-secondary" type="button" onClick={() => action.mutate('retry')}>Opnieuw controleren</button>}
        </div>
      </fieldset>
      {action.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{action.error?.response?.data?.message || 'Verwerken is niet gelukt.'}</p>}
    </article>
  );
}

export default function VOGReview() {
  const [page, setPage] = useState(1);
  const query = useQuery({ queryKey: ['vog', 'submissions', page], queryFn: async () => (await prmApi.getVogSubmissions(page)).data });
  return <section className="space-y-4">
    <h1 className="text-xl font-semibold">VOG’s beoordelen</h1>
    {query.isLoading && <p>Inzendingen laden…</p>}
    {query.isError && <p role="alert">De inzendingen konden niet worden geladen.</p>}
    {query.data?.items.length === 0 && <p>Er zijn geen inzendingen om te beoordelen.</p>}
    {query.data?.items.map(item => <Submission key={`${item.id}-${item.version}`} item={item} />)}
    <div className="flex gap-3"><button className="btn-secondary" disabled={page <= 1} onClick={() => setPage(old => old - 1)}>Vorige</button><span>Pagina {page}</span><button className="btn-secondary" disabled={!query.data || page >= query.data.pages} onClick={() => setPage(old => old + 1)}>Volgende</button></div>
  </section>;
}
