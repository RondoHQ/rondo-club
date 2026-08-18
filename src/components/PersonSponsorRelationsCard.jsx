import { useState } from 'react';
import { Building2, Trash2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';
import { useUpdateSponsor } from '@/hooks/useSponsors';

const roleLabels = { businessclub: 'Businessclub AWC', awc_sponsor: 'AWC Sponsor' };

export default function PersonSponsorRelationsCard({ person, canManage }) {
  const relationships = person?.sponsor_relationships || [];
  const [error, setError] = useState('');
  const updateSponsor = useUpdateSponsor();

  if (!canManage && relationships.length === 0) return null;

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

      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
    </>
  );
}
