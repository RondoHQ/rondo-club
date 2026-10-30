import { useId, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const engagementLabels = {
  unknown: 'Nog niet ingedeeld',
  employee: 'Personeel in loondienst',
  compensated: 'Vrijwilliger met vergoeding',
  volunteer: 'Onbetaalde vrijwilliger',
};

export function RoleEngagementBadges({ data, isStaff }) {
  const types = data ? Object.keys(engagementLabels).filter(type => data.summary[type] > 0) : [];
  if (types.length === 0 && !isStaff) return null;
  return (types.length ? types : ['staff']).map(type => <span key={type} className="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">{type === 'staff' ? 'Kaderlid' : type === 'unknown' ? 'Kaderlid · functietype onbekend' : engagementLabels[type]}</span>);
}

export function RoleEngagementControl({ personId, role, version, canEdit }) {
  const inputId = useId();
  const [editing, setEditing] = useState(false);
  const [editVersion, setEditVersion] = useState(version);
  const [type, setType] = useState(role.engagement_type);
  const [saved, setSaved] = useState(false);
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: async () => (await prmApi.updateRoleEngagement(personId, { role_key: role.role_key, engagement_type: type, version: editVersion })).data,
    onSuccess: async (data) => {
      queryClient.setQueriesData({ queryKey: ['people', 'detail'] }, old => Number(old?.id) === Number(personId) ? { ...old, role_engagements: data } : old);
      setEditing(false);
      setSaved(true);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['people', 'detail', personId] }),
        queryClient.invalidateQueries({ queryKey: ['volunteer'] }),
      ]);
    },
  });
  return (
    <div className="space-y-2 sm:col-span-3">
      {editing ? (
        <form className="flex flex-wrap items-end gap-2" onSubmit={event => { event.preventDefault(); mutation.mutate(); }}>
          <div className="w-full min-w-0 sm:w-auto sm:flex-1 sm:max-w-sm">
            <label htmlFor={inputId} className="mb-1 block text-sm font-medium">Functietype</label>
            <select id={inputId} className="input w-full" value={type} disabled={mutation.isPending} onChange={event => setType(event.target.value)}>
              {Object.entries(engagementLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}
            </select>
          </div>
          <button className="btn-primary" type="submit" disabled={mutation.isPending}>{mutation.isPending ? 'Opslaan…' : 'Opslaan'}</button>
          <button className="btn-secondary" type="button" disabled={mutation.isPending} onClick={() => { setEditing(false); mutation.reset(); }}>Annuleren</button>
        </form>
      ) : (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <span className="text-gray-600 dark:text-gray-300">{engagementLabels[role.engagement_type]}</span>
          {canEdit && role.can_classify && <button type="button" className="btn-tertiary text-sm" onClick={() => { setType(role.engagement_type); setEditVersion(version); setEditing(true); setSaved(false); mutation.reset(); }}>Functietype wijzigen</button>}
          {saved && <span role="status" className="text-sm text-gray-600 dark:text-gray-300">Opgeslagen</span>}
        </div>
      )}
      {!role.can_classify && <p className="text-sm text-amber-800 dark:text-amber-200">Deze functie komt dubbel voor. Laat de functiegegevens eerst controleren.</p>}
      {mutation.error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{mutation.error.response?.data?.message || 'Opslaan is niet gelukt. Probeer het opnieuw.'}</p>}
    </div>
  );
}

export function RoleEngagementStatistics({ data }) {
  if (!data) return null;
  const types = Object.keys(engagementLabels);
  return (
    <section className="card space-y-4 p-5" aria-labelledby="role-engagement-statistics">
      <div>
        <h2 id="role-engagement-statistics" className="text-base font-semibold">Personeel en vrijwilligers per functie</h2>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Huidige functies, onafhankelijk van het gekozen seizoen. Deel functies in via Relaties → Rollen. Niet-beoordeelde functies blijven apart staan.</p>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <caption className="sr-only">Huidige personen en functies per functietype</caption>
          <thead><tr className="border-b border-gray-200 dark:border-gray-700"><th scope="col" className="py-2 pr-4">Functietype</th><th scope="col" className="px-3 py-2 text-right">Personen</th><th scope="col" className="py-2 pl-3 text-right">Functies</th></tr></thead>
          <tbody>{types.map(type => <tr key={type} className="border-b border-gray-100 last:border-0 dark:border-gray-700"><th scope="row" className="py-2 pr-4 font-normal">{engagementLabels[type]}</th><td className="px-3 py-2 text-right tabular-nums">{data.totals[type].people}</td><td className="py-2 pl-3 text-right tabular-nums">{data.totals[type].roles}</td></tr>)}</tbody>
        </table>
      </div>
      <p className="text-sm text-gray-600 dark:text-gray-300">Een persoon telt één keer per categorie mee en kan zowel werknemer als vrijwilliger zijn. Tel de categorieën daarom niet op als totaal aantal personen. De indeling wijzigt geen VOG-eisen of vrijstellingen.</p>
      <details>
        <summary className="cursor-pointer font-medium">Verdeling per team en commissie</summary>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full min-w-[640px] text-left text-sm">
            <caption className="sr-only">Aantal personen per team of commissie en functietype</caption>
            <thead><tr className="border-b border-gray-200 dark:border-gray-700"><th scope="col" className="py-2 pr-3">Team of commissie</th>{types.map(type => <th scope="col" key={type} className="px-3 py-2 text-right">{engagementLabels[type]}</th>)}</tr></thead>
            <tbody>{data.by_entity.map(entity => <tr key={entity.id} className="border-b border-gray-100 dark:border-gray-700"><th scope="row" className="py-2 pr-3 font-normal">{entity.name}</th>{types.map(type => <td key={type} className="px-3 py-2 text-right tabular-nums">{entity.counts[type].people}</td>)}</tr>)}</tbody>
          </table>
          {data.by_entity.length === 0 && <p className="py-3 text-sm">Er zijn geen huidige kaderfuncties.</p>}
        </div>
      </details>
    </section>
  );
}
