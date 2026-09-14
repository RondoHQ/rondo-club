import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const newRule = () => ({ organization: '', function: 'Vrijwilliger', codes: ['84'] });

export default function VogApprovalRules() {
  const client = useQueryClient();
  const [editedRules, setEditedRules] = useState(null);
  const query = useQuery({ queryKey: ['vog', 'rules'], queryFn: async () => (await prmApi.getVogRules()).data });
  const rules = editedRules ?? (query.data?.rules.length ? query.data.rules : [newRule()]);
  const save = useMutation({
    mutationFn: () => prmApi.saveVogRules(rules.map(rule => ({ ...rule, organization: rule.organization.trim() }))),
    onSuccess: async () => { await client.invalidateQueries({ queryKey: ['vog'] }); },
  });
  const change = (index, organization) => { save.reset(); setEditedRules(rules.map((rule, i) => i === index ? { ...rule, organization } : rule)); };
  return <section className="card p-5 space-y-4">
    <h3 className="font-semibold">Automatische VOG-controle</h3>
    <p className="text-sm text-gray-600 dark:text-gray-300">Vul de volledige organisatienaam in zoals die op de VOG staat. Rondo vergelijkt die automatisch; hoofdletters en extra spaties maken geen verschil.</p>
    {query.isError && <p role="alert">De instellingen konden niet worden geladen.</p>}
    {query.data && !query.data.reader_available && <p role="alert" className="text-amber-800 dark:text-amber-300">De PDF-lezer is niet beschikbaar. Laat een beheerder de installatie controleren voordat leden PDF’s inleveren.</p>}
    <fieldset disabled={save.isPending || query.isLoading || query.isError} className="space-y-4">
      {rules.map((rule, index) => <div key={index} className="space-y-2">
        <label className="block text-sm">{rules.length > 1 ? `Organisatienaam ${index + 1} op de VOG` : 'Organisatienaam op de VOG'}<input className="input mt-1 w-full" value={rule.organization} maxLength={200} onChange={event => change(index, event.target.value)} /></label>
        <p className="text-sm text-gray-600 dark:text-gray-300">Functie: {rule.function}. Vereiste code: {[...new Set(['84', ...rule.codes])].join(', ')} (84: zorg voor minderjarigen).</p>
        <button className="btn-secondary" type="button" onClick={() => { save.reset(); setEditedRules(rules.filter((_, i) => i !== index)); }}>Organisatie verwijderen</button>
      </div>)}
      {rules.length === 0 && <p className="text-sm">Zonder organisatienaam kan Rondo digitale VOG’s niet goedkeuren.</p>}
      <div className="flex flex-wrap gap-2">
        <button className="btn-secondary" type="button" disabled={rules.length >= 10} onClick={() => { save.reset(); setEditedRules([...rules, newRule()]); }}>Organisatienaam toevoegen</button>
        <button className="btn-primary" type="button" disabled={rules.some(rule => !rule.organization.trim())} onClick={() => save.mutate()}>{save.isPending ? 'Opslaan…' : 'VOG-instellingen opslaan'}</button>
      </div>
    </fieldset>
    {save.isError && <p role="alert" className="text-red-700 dark:text-red-300">{save.error?.response?.data?.message || 'Opslaan is niet gelukt.'}</p>}
    {save.isSuccess && <p role="status" className="text-sm">VOG-instellingen opgeslagen.</p>}
  </section>;
}
