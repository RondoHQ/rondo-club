import { useState } from 'react';
import { Building2, Search, Trash2 } from 'lucide-react';
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
      const isPersonalSponsor = sponsor.fields?.sponsor_type === 'person';
      await updateSponsor.mutateAsync({
        id: sponsor.id,
        data: {
          fields: {
            contacts: [...contacts, {
              person_id: person.id,
              contact_role: isPersonalSponsor ? 'Sponsor' : 'Contactpersoon',
              is_primary: isPersonalSponsor || contacts.length === 0,
              receives_pass: true,
              is_primary_pass: isPersonalSponsor,
              sponsit_person_id: '',
            }],
          },
        },
      });
      setSearch('');
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Sponsor kon niet worden gekoppeld.');
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

  const relationshipDetails = (relationship) => [
    relationship.contact_role,
    relationship.is_primary ? 'primair' : '',
    roleLabels[relationship.sponsor_role] || relationship.sponsor_role,
    relationship.receives_pass ? 'sponsorpas' : '',
  ].filter(Boolean).join(' · ');

  return (
    <>
      {relationships.map((relationship) => (
        <div key={`sponsor-${relationship.sponsor_id}`} className="group flex items-center rounded p-2 hover:bg-gray-50 dark:hover:bg-gray-700">
          <Link to={`/sponsors/${relationship.sponsor_id}`} className="flex min-w-0 flex-1 items-center">
            <span className="mr-2 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300">
              <Building2 className="h-5 w-5" />
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-medium">{relationship.sponsor_name}</p>
              <p className="truncate text-xs text-gray-500 dark:text-gray-400">{relationshipDetails(relationship)}</p>
            </div>
          </Link>
          {canManage && (
            <button
              type="button"
              className="ml-2 rounded p-1 opacity-0 transition-opacity hover:bg-red-50 group-hover:opacity-100 focus:opacity-100"
              onClick={() => unlinkSponsor(relationship)}
              title="Relatie verwijderen"
              aria-label={`Relatie met ${relationship.sponsor_name} verwijderen`}
            >
              <Trash2 className="h-4 w-4 text-gray-400 hover:text-red-600" />
            </button>
          )}
        </div>
      ))}

      {canManage && (
        <div className="relative pt-2">
          <Search className="pointer-events-none absolute left-3 top-4.5 h-4 w-4 text-gray-400" />
          <input
            className="input w-full pl-9"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Sponsorrelatie toevoegen"
            aria-label="Sponsorrelatie toevoegen"
          />
          {search.trim().length >= 2 && (
            <div className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
              {(data?.items || [])
                .filter((sponsor) => !relationships.some((relationship) => Number(relationship.sponsor_id) === Number(sponsor.id)))
                .filter((sponsor) => sponsor.fields?.sponsor_type !== 'person' || (sponsor.fields?.contacts || []).length === 0)
                .map((sponsor) => (
                  <button
                    key={sponsor.id}
                    type="button"
                    className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                    onClick={() => linkSponsor(sponsor)}
                  >
                    {sponsor.title}
                  </button>
                ))}
            </div>
          )}
        </div>
      )}
      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
    </>
  );
}
