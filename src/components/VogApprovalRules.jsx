import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export default function VogApprovalRules() {
  const client = useQueryClient();
  const [rules, setRules] = useState([]);
  const query = useQuery({ queryKey: ['vog', 'rules'], queryFn: async () => (await prmApi.getVogRules()).data });
  useEffect(() => { if (query.data) setRules(query.data.rules.map(rule => ({ ...rule, codes: rule.codes.join(', ') }))); }, [query.data]);
  const save = useMutation({
    mutationFn: () => prmApi.saveVogRules(rules.map(rule => ({ ...rule, codes: rule.codes.split(/[,;\s]+/).filter(Boolean) }))),
    onSuccess: () => client.invalidateQueries({ queryKey: ['vog', 'rules'] }),
  });
  const change = (index, key, value) => setRules(old => old.map((rule, i) => i === index ? { ...rule, [key]: value } : rule));
  return <section className="card p-5 space-y-4">
    <h3 className="font-semibold">Automatische VOG-goedkeuring</h3>
    <p className="text-sm">Zonder regels controleert Rondo de echtheid digitaal en beoordeelt de VOG-coördinator de inhoud. Voeg alleen regels toe waarvan de club heeft bevestigd dat ze voldoende zijn voor deze functie.</p>
    {query.isError && <p role="alert">De regels konden niet worden geladen.</p>}
    {query.data && !query.data.reader_available && <p role="alert" className="text-amber-800 dark:text-amber-300">De PDF-lezer is niet beschikbaar. Laat een beheerder de installatie controleren voordat leden PDF’s inleveren.</p>}
    <fieldset disabled={save.isPending || query.isLoading || query.isError} className="space-y-4">
      {rules.map((rule, index) => <div key={index} className="border rounded p-3 space-y-2">
        <label className="block text-sm">Organisatie zoals vermeld op de VOG<input className="input mt-1 w-full" value={rule.organization} maxLength={200} onChange={event => change(index, 'organization', event.target.value)} /></label>
        <label className="block text-sm">Functie zoals vermeld op de VOG<input className="input mt-1 w-full" value={rule.function} maxLength={200} onChange={event => change(index, 'function', event.target.value)} /></label>
        <label className="block text-sm">Verplichte screeningscodes, gescheiden door komma’s<input className="input mt-1 w-full" value={rule.codes} onChange={event => change(index, 'codes', event.target.value)} /></label>
        <button className="btn-secondary" type="button" onClick={() => setRules(old => old.filter((_, i) => i !== index))}>Regel verwijderen</button>
      </div>)}
      <div className="flex flex-wrap gap-2"><button className="btn-secondary" type="button" disabled={rules.length >= 10} onClick={() => setRules(old => [...old, { organization: '', function: '', codes: '' }])}>Regel toevoegen</button><button className="btn-primary" type="button" onClick={() => save.mutate()}>{save.isPending ? 'Opslaan…' : 'Goedkeuringsregels opslaan'}</button></div>
    </fieldset>
    {save.isError && <p role="alert" className="text-red-700 dark:text-red-300">{save.error?.response?.data?.message || 'Opslaan is niet gelukt.'}</p>}
    {save.isSuccess && <p role="status" className="text-sm">Goedkeuringsregels opgeslagen.</p>}
  </section>;
}
