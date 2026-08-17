import { useState } from 'react';
import { Building2, Plus, Search, Trash2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';
import { useSponsors, useUpdateSponsor } from '@/hooks/useSponsors';

const roleLabels = { businessclub: 'Businessclub AWC', awc_sponsor: 'AWC Sponsor' };

export default function PersonSponsorRelationsCard({ person, canManage }) {
  const relationships = person?.sponsor_relationships || [];
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const { data } = useSponsors({ search, status: 'active', per_page: 20 }, { enabled: canManage && search.trim().length >= 2 });
  const updateSponsor = useUpdateSponsor();

  if (!canManage && relationships.length === 0) return null;

  const linkSponsor = async (sponsor) => {
    if (relationships.some((relationship) => relationship.sponsor_id === sponsor.id)) return;
    setError('');
    try {
      const contacts = sponsor.fields?.contacts || [];
      await updateSponsor.mutateAsync({
        id: sponsor.id,
        data: {
          fields: {
            contacts: [...contacts, {
              person_id: person.id,
              contact_role: 'Contactpersoon',
              is_primary: contacts.length === 0,
              receives_pass: true,
              is_primary_pass: false,
              sponsit_person_id: '',
            }],
          },
        },
      });
      setSearch('');
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Sponsorbedrijf kon niet worden gekoppeld.');
    }
  };

  const unlinkSponsor = async (relationship) => {
    if (!confirm(`Contactrelatie met ${relationship.sponsor_name} verwijderen?`)) return;
    setError('');
    try {
      const sponsor = (await prmApi.getSponsor(relationship.sponsor_id)).data;
      await updateSponsor.mutateAsync({
        id: relationship.sponsor_id,
        data: {
          fields: {
            contacts: (sponsor.fields?.contacts || []).filter((contact) => Number(contact.person_id) !== Number(person.id)),
          },
        },
      });
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Contactrelatie kon niet worden verwijderd.');
    }
  };

  return (
    <section className="card p-6">
      <div className="mb-3 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Building2 className="h-5 w-5 text-gray-500" />
          <h2 className="font-semibold text-brand-gradient">Sponsorcontact voor</h2>
        </div>
        {canManage && <Link to="/sponsors/new" className="btn-tertiary p-2" title="Sponsorbedrijf toevoegen"><Plus className="h-4 w-4" /></Link>}
      </div>

      {relationships.length === 0 ? <p className="text-sm text-gray-500">Nog niet aan een sponsorbedrijf gekoppeld.</p> : (
        <div className="space-y-2">
          {relationships.map((relationship) => (
            <div key={relationship.sponsor_id} className="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
              <div>
                <Link to={`/sponsors/${relationship.sponsor_id}`} className="font-medium text-electric-cyan hover:underline">{relationship.sponsor_name}</Link>
                <p className="mt-0.5 text-xs text-gray-500">{relationship.contact_role} · {roleLabels[relationship.sponsor_role] || relationship.sponsor_role}{relationship.receives_pass ? ' · sponsorpas' : ''}</p>
              </div>
              {canManage && <button type="button" className="p-1.5 text-red-600" onClick={() => unlinkSponsor(relationship)} aria-label="Ontkoppelen"><Trash2 className="h-4 w-4" /></button>}
            </div>
          ))}
        </div>
      )}

      {canManage && <div className="relative mt-4"><Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" /><input className="input w-full pl-9" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Koppel aan sponsorbedrijf" />{search.trim().length >= 2 && <div className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">{(data?.items || []).map((sponsor) => <button key={sponsor.id} type="button" className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700" onClick={() => linkSponsor(sponsor)}>{sponsor.title}</button>)}</div>}</div>}
      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
    </section>
  );
}
