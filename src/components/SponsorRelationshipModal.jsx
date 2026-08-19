import { useEffect, useState } from 'react';
import { Building2, Search, X } from 'lucide-react';
import { useSponsors, useUpdateSponsor } from '@/hooks/useSponsors';

export default function SponsorRelationshipModal({ isOpen, onClose, person }) {
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const relationships = person?.sponsor_relationships || [];
  const updateSponsor = useUpdateSponsor();
  const canSearch = isOpen && search.trim().length >= 2;
  const { data, isLoading } = useSponsors(
    { search, status: 'active', per_page: 20 },
    { enabled: canSearch },
  );

  useEffect(() => {
    if (isOpen) {
      setSearch('');
      setError('');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const availableSponsors = (data?.items || [])
    .filter((sponsor) => !relationships.some((relationship) => Number(relationship.sponsor_id) === Number(sponsor.id)))
    .filter((sponsor) => sponsor.fields?.sponsor_type !== 'person' || (sponsor.fields?.contacts || []).length === 0);

  const linkSponsor = async (sponsor) => {
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
      onClose();
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Sponsor kon niet worden gekoppeld.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="presentation">
      <div
        className="mx-4 w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sponsor-relationship-title"
      >
        <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
          <h2 id="sponsor-relationship-title" className="text-lg font-semibold text-gray-900 dark:text-gray-50">Sponsor koppelen</h2>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" disabled={updateSponsor.isPending} aria-label="Sluiten">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="space-y-4 p-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">Zoek de sponsor waaraan je deze persoon wilt koppelen.</p>
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            <input
              className="input w-full pl-9"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Zoek op sponsornaam"
              autoFocus
              disabled={updateSponsor.isPending}
            />
          </div>

          {canSearch && (
            <div className="max-h-64 overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">
              {isLoading ? (
                <p className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Zoeken…</p>
              ) : availableSponsors.length > 0 ? (
                availableSponsors.map((sponsor) => (
                  <button
                    key={sponsor.id}
                    type="button"
                    className="flex w-full items-center gap-3 border-b border-gray-100 px-4 py-3 text-left text-sm last:border-b-0 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:hover:bg-gray-700"
                    onClick={() => linkSponsor(sponsor)}
                    disabled={updateSponsor.isPending}
                  >
                    <Building2 className="h-5 w-5 shrink-0 text-gray-400" />
                    <span>{sponsor.title}</span>
                  </button>
                ))
              ) : (
                <p className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Geen beschikbare sponsoren gevonden.</p>
              )}
            </div>
          )}

          {error && <p className="text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>}
        </div>
      </div>
    </div>
  );
}
