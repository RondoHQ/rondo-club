import { useMemo } from 'react';
import { Link, useParams, Navigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, ArrowLeft, ExternalLink } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

const CATEGORIES = {
  orphan: {
    title: 'Gezinnen zonder ouder-relatie',
    intro:
      'Deze JO16- spelers hebben geen ouder-relatie in hun relationships-veld én geen volwassen huisgenoot op hetzelfde adres. ' +
      'Zolang er geen ouder bekend is, kunnen e-mails en boetes nergens heen. ' +
      'Voeg een ouder toe in het tabblad "Relaties" op de persoonspagina.',
  },
  address_fallback: {
    title: 'Gezinnen via adres-overeenkomst',
    intro:
      'Voor deze personen kon Rondo geen expliciete ouder-relatie vinden, dus is het gezin afgeleid uit een gedeeld adres. ' +
      'Dat werkt, maar wordt fragiel zodra iemand verhuist. Voeg expliciete relationship_type=parent entries toe op de persoonspagina van de speler.',
  },
  missing_leeftijdsgroep: {
    title: 'Actieve leden zonder leeftijdsgroep',
    intro:
      'Deze actieve leden hebben geen leeftijdsgroep én geen andere zichtbare reden om er geen te hebben. ' +
      'Al uitgefilterd: huidige vrijwilligers, honorary leden (Donateur, Erelid, Lid van Verdienste, Verenigingslid voor het leven), ' +
      'en ouders die al via een gezin gekoppeld zijn (relationships of adres). ' +
      'Wat overblijft is meestal een Sportlink-sync die gehaperd heeft — corrigeer het op de persoonspagina ' +
      '(leeftijdsgroep zetten, een passende werkfunctie of "huidig vrijwilliger" aanvinken, of als ex-lid markeren).',
  },
  former_members: {
    title: 'Ex-leden',
    intro:
      'Deze personen vallen buiten de vrijwilligersplicht omdat ze als ex-lid (former_member) gemarkeerd zijn. ' +
      'Contributievrijstelling (donateur, erelid, Lid van Verdienste, contributievrij) is hier bewust geen reden — ' +
      'die leden zitten gewoon in de doelgroep, tenzij ze via een andere route vrijgesteld zijn (commissie, staf-rol, betaalde vrijwilliger, handmatige vrijstelling).',
  },
};

export default function VrijwilligersDataQuality() {
  const { category } = useParams();
  const cfg = CATEGORIES[category];
  useDocumentTitle(`Datakwaliteit — ${cfg?.title || 'Vrijwilligers'}`);

  const { data, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'data-quality', category],
    queryFn: async () => (await prmApi.getVolunteerDataQuality(category)).data,
    enabled: !!cfg,
    staleTime: 60 * 1000,
  });

  const persons = data?.persons || [];

  const groupedByAddress = useMemo(() => {
    if (category !== 'address_fallback') return null;
    const map = new Map();
    for (const person of persons) {
      const key = `${person.postal_code || '?'}-${person.address || '?'}`;
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(person);
    }
    return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0], 'nl'));
  }, [persons, category]);

  if (!cfg) {
    return <Navigate to="/vrijwilligers" replace />;
  }

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar Vrijwilligers-dashboard
        </Link>
        <div className="flex items-start gap-3">
          <div className="p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
            <AlertTriangle className="w-6 h-6 text-amber-500" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{cfg.title}</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-3xl">
              {cfg.intro}
            </p>
          </div>
        </div>
      </header>

      {error && (
        <div className="card p-4 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
          Kon de lijst niet ophalen: {error?.message || 'onbekende fout'}
        </div>
      )}

      {isLoading ? (
        <ContentLoadingSpinner />
      ) : persons.length === 0 ? (
        <div className="card p-8 text-center text-sm text-gray-500 dark:text-gray-400">
          Geen personen in deze categorie. 🎉
        </div>
      ) : groupedByAddress ? (
        <section className="space-y-4">
          <p className="text-xs text-gray-500 dark:text-gray-400">
            {persons.length} personen, gegroepeerd op {groupedByAddress.length} adressen.
          </p>
          {groupedByAddress.map(([key, group]) => (
            <div key={key} className="card overflow-hidden">
              <div className="px-4 py-2 bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-500 dark:text-gray-300 font-semibold flex items-center justify-between">
                <span>{group[0].address || '—'}, {group[0].postal_code || '—'} {group[0].city || ''}</span>
                <span>{group.length} {group.length === 1 ? 'persoon' : 'personen'}</span>
              </div>
              <PersonsTable persons={group} />
            </div>
          ))}
        </section>
      ) : (
        <section>
          <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">
            {persons.length} personen.
          </p>
          <div className="card overflow-hidden">
            <PersonsTable persons={persons} />
          </div>
        </section>
      )}
    </div>
  );
}

function PersonsTable({ persons }) {
  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
        <tr>
          <th className="px-4 py-2">Naam</th>
          <th className="px-4 py-2">Leeftijdsgroep</th>
          <th className="px-4 py-2">Adres</th>
          <th className="px-4 py-2"># relaties</th>
          <th className="px-4 py-2 w-12"></th>
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
        {persons.map((person) => (
          <tr key={person.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td className="px-4 py-2">
              <Link
                to={`/people/${person.id}`}
                className="text-bright-cobalt dark:text-electric-cyan hover:underline"
              >
                {person.name}
              </Link>
            </td>
            <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
              {person.leeftijdsgroep || <span className="text-gray-400">—</span>}
            </td>
            <td className="px-4 py-2 text-gray-700 dark:text-gray-300 text-xs">
              {person.address ? (
                <>
                  {person.address}
                  {person.postal_code ? `, ${person.postal_code}` : ''}
                  {person.city ? ` ${person.city}` : ''}
                </>
              ) : (
                <span className="text-gray-400">—</span>
              )}
            </td>
            <td className="px-4 py-2 text-gray-700 dark:text-gray-300 text-xs">
              {person.relationships_count}
            </td>
            <td className="px-4 py-2">
              <Link
                to={`/people/${person.id}`}
                className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                title="Bewerk persoon"
              >
                <ExternalLink className="w-4 h-4" />
              </Link>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
