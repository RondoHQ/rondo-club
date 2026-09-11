import { useEffect, useRef, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

import { VOG_SUBMISSION_LABELS, refreshVog } from '@/utils/vog';

export default function VogUpload({ submission }) {
  const [source, setSource] = useState('digital');
  const [files, setFiles] = useState([]);
  const [previews, setPreviews] = useState([]);
  const [error, setError] = useState('');
  const input = useRef(null);
  const client = useQueryClient();
  const clearFiles = () => {
    setFiles([]);
    if (input.current) input.current.value = '';
  };
  const upload = useMutation({
    mutationFn: () => prmApi.uploadVog(files, source),
    onSuccess: async () => {
      clearFiles();
      await refreshVog(client);
    },
  });
  useEffect(() => {
    const urls = files.map(file => URL.createObjectURL(file));
    setPreviews(urls);
    return () => urls.forEach(url => { if (url) URL.revokeObjectURL(url); });
  }, [files]);

  const choose = event => {
    const selected = Array.from(event.target.files || []);
    setError('');
    upload.reset();
    if (selected.length > 5 || selected.reduce((sum, file) => sum + file.size, 0) > 10 * 1024 * 1024) {
      clearFiles();
      setError('Kies maximaal vijf bestanden, samen maximaal 10 MB.');
      return;
    }
    if (selected.some(file => !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type))) {
      clearFiles();
      setError('Kies een PDF, JPG of PNG. Andere fotoformaten worden nog niet ondersteund.');
      return;
    }
    if (selected.some(file => file.type === 'application/pdf') && selected.length !== 1) {
      clearFiles();
      setError('Kies één PDF of meerdere foto’s.');
      return;
    }
    if (selected.some(file => file.type.startsWith('image/')) && source === 'digital') setSource('unknown');
    setFiles(selected);
  };
  const move = (index, direction) => setFiles(old => {
    const result = [...old];
    [result[index], result[index + direction]] = [result[index + direction], result[index]];
    return result;
  });

  return (
    <section className="card p-5 space-y-4">
      {submission && (
        <div className="rounded-lg bg-gray-50 dark:bg-gray-700 p-3 space-y-2" role="status">
          <h2 className="font-semibold">{VOG_SUBMISSION_LABELS[submission.status] || 'In behandeling'}</h2>
          {submission.status === 'needs_original' && <p className="text-sm">Download de oorspronkelijke PDF uit de Berichtenbox van MijnOverheid. Gebruik geen screenshot of afdruk naar PDF. Kom je er niet uit? De VOG-coördinator kan je helpen.</p>}
          {submission.status === 'waiting_paper' && <p className="text-sm">Neem het originele papieren document mee naar de VOG-coördinator. Een scan is nog geen goedkeuring.</p>}
          {submission.status === 'technical' && <p className="text-sm">{submission.attempts < 3 ? 'We proberen het automatisch opnieuw.' : 'De VOG-coördinator kan de controle opnieuw starten.'} Een eerder geldige VOG blijft geldig.</p>}
          {submission.status === 'approved' && <p className="text-sm">{submission.method === 'paper_original' ? 'Origineel op papier gecontroleerd.' : 'Digitaal gecontroleerd via Justid.'}</p>}
          {submission.note && <p className="text-sm">{submission.note}</p>}
        </div>
      )}
      <h2 className="font-semibold">VOG inleveren</h2>
      <fieldset disabled={upload.isPending} className="space-y-3">
        <legend className="text-sm mb-2">Wat wil je inleveren?</legend>
        <label className="flex items-start gap-2 text-sm"><input type="radio" name="vog-source" checked={source === 'digital'} onChange={() => { setSource('digital'); clearFiles(); }} className="mt-1" />PDF uit MijnOverheid uploaden</label>
        <label className="flex items-start gap-2 text-sm"><input type="radio" name="vog-source" checked={source !== 'digital'} onChange={() => setSource('unknown')} className="mt-1" />Ik heb een papieren VOG of een foto/scan</label>
        {source !== 'digital' && (
          <div className="space-y-3">
            <p className="text-sm">Heb je de VOG digitaal ontvangen? Gebruik bij voorkeur de oorspronkelijke PDF. Een foto of scan kunnen we alvast in ontvangst nemen.</p>
            <label className="block text-sm">Hoe heb je de VOG oorspronkelijk ontvangen?
              <select className="input mt-1 w-full" value={source} onChange={event => setSource(event.target.value)}>
                <option value="unknown">Weet ik niet</option><option value="paper">Per post, op papier</option><option value="digital_scan">Digitaal via MijnOverheid</option>
              </select>
            </label>
          </div>
        )}
        <p className="text-sm text-gray-600 dark:text-gray-300">
          {source === 'digital' ? 'We sturen de originele PDF naar de officiële validatiedienst van Justid en vergelijken de gegevens met je profiel.' : 'De VOG-coördinator bekijkt je upload. Voor goedkeuring is de originele digitale PDF of controle van het echte papieren origineel nodig.'}
          {' '}Na afronding verwijderen we je upload. Openstaande uploads zijn maximaal 30 dagen beschikbaar. Bewaar zelf je origineel.
        </p>
        <label className="block text-sm">{source === 'digital' ? 'Originele PDF (maximaal 10 MB en vijf pagina’s)' : 'Eén PDF of maximaal vijf JPG/PNG-foto’s (samen maximaal 10 MB)'}
          <input ref={input} type="file" accept={source === 'digital' ? '.pdf,image/jpeg,image/png' : '.pdf,.jpg,.jpeg,.png'} multiple={source !== 'digital'} onChange={choose} className="block w-full mt-2 text-sm" />
        </label>
        {source !== 'digital' && <p className="text-sm text-gray-500">Zorg dat alle pagina’s scherp, volledig en zonder afgesneden randen zichtbaar zijn.</p>}
        {files.length > 0 && <ol className="space-y-2">{files.map((file, index) => (
          <li key={`${file.name}-${index}`} className="flex flex-wrap items-center gap-2 text-sm border rounded p-2">
            <div className="flex w-full items-center gap-3">
              {previews[index] && file.type.startsWith('image/') && <img src={previews[index]} alt={`Voorbeeld pagina ${index + 1}`} className="h-20 w-16 shrink-0 object-contain" />}
              <span className="min-w-0 flex-1 break-words">{index + 1}. {file.name}{previews[index] && <a href={previews[index]} target="_blank" rel="noopener noreferrer" className="block underline mt-1">Voorbeeld openen</a>}</span>
            </div>
            <button type="button" disabled={index === 0} onClick={() => move(index, -1)} className="btn-secondary" aria-label={`Pagina ${index + 1} omhoog`}>↑</button>
            <button type="button" disabled={index === files.length - 1} onClick={() => move(index, 1)} className="btn-secondary" aria-label={`Pagina ${index + 1} omlaag`}>↓</button>
            <button type="button" onClick={() => setFiles(old => old.filter((_, i) => i !== index))} className="btn-secondary">Verwijderen</button>
          </li>
        ))}</ol>}
        <button type="button" disabled={!files.length || upload.isPending} className="btn-primary" onClick={() => upload.mutate()}>{upload.isPending ? 'Bezig met verwerken…' : source === 'digital' ? 'Uploaden en controleren' : 'Scan inleveren voor beoordeling'}</button>
      </fieldset>
      {(error || upload.isError) && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error || upload.error?.response?.data?.message || 'Uploaden is niet gelukt. Probeer het opnieuw.'}</p>}
    </section>
  );
}
