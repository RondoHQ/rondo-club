import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { HeartHandshake, FileCheck, Wine, CalendarClock, UsersRound, Users } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

function StatCard({ label, value, sub, icon: Icon, href }) {
  const content = (
    <div className="card p-5 flex items-start gap-4 hover:shadow-md transition-shadow">
      {Icon && (
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg shrink-0">
          <Icon className="w-5 h-5 text-bright-cobalt dark:text-electric-cyan" />
        </div>
      )}
      <div className="min-w-0">
        <div className="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">{label}</div>
        <div className="text-2xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{value}</div>
        {sub && <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">{sub}</div>}
      </div>
    </div>
  );
  return href ? <Link to={href} className="block">{content}</Link> : content;
}

export default function VrijwilligersDashboard() {
  useDocumentTitle('Vrijwilligers');

  const { data: eligibility, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'eligibility'],
    queryFn: async () => {
      const response = await prmApi.getVolunteerEligibility();
      return response.data;
    },
    staleTime: 5 * 60 * 1000,
  });

  const stats = useMemo(() => {
    if (!eligibility?.units) return null;
    const units = eligibility.units;
    const total = units.length;
    const gezin = units.filter((u) => u.kind === 'gezin').length;
    const speler = units.filter((u) => u.kind === 'speler').length;
    return { total, gezin, speler };
  }, [eligibility]);

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-3">
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <HeartHandshake className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijwilligers</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Vrijwilligersbeleid — 2 diensten per jaar voor ouders (t/m JO16) en spelers (vanaf O17).
          </p>
        </div>
      </header>

      {error && (
        <div className="card p-4 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
          Kon eligibility niet ophalen: {error?.message || 'onbekende fout'}
        </div>
      )}

      <section>
        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-3">
          Doelgroep dit seizoen
        </h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label="Eenheden totaal"
            value={isLoading ? '…' : (stats?.total ?? 0).toLocaleString('nl-NL')}
            sub={eligibility?.season ? `Seizoen ${eligibility.season}` : null}
            icon={Users}
          />
          <StatCard
            label="Gezinnen (ouderplicht)"
            value={isLoading ? '…' : (stats?.gezin ?? 0).toLocaleString('nl-NL')}
            sub="Huishoudens met ≥1 speler t/m JO16"
            icon={UsersRound}
          />
          <StatCard
            label="Spelers (vanaf O17)"
            value={isLoading ? '…' : (stats?.speler ?? 0).toLocaleString('nl-NL')}
            sub="Individuele spelersplicht"
            icon={Users}
          />
        </div>
      </section>

      <section>
        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-3">
          Snelle navigatie
        </h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="VOG" value="Beheer" sub="Verklaring Omtrent Gedrag" icon={FileCheck} href="/vrijwilligers/vog" />
          <StatCard label="IVA" value="Beheer" sub="Alcoholcertificaat kantine" icon={Wine} href="/vrijwilligers/iva" />
          <StatCard label="Diensten" value="Planner" sub="Shifts en aanmeldingen" icon={CalendarClock} href="/vrijwilligers/diensten" />
          <StatCard label="Vrijstellingen" value="Beheer" sub="Handmatige + auto vrijstellingen" icon={UsersRound} href="/vrijwilligers/vrijstellingen" />
        </div>
      </section>

      <section className="card p-5">
        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Status van de uitrol</h2>
        <ul className="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc pl-5">
          <li>Eligibility-derivatie en vrijstellingsresolver zijn live, inclusief multi-child schaal (kind 1 = 2 diensten, kind 2 = 1,5, kind 3+ = 1, naar beneden afgerond).</li>
          <li>VOG verhuisd onder Vrijwilligers; hard-block bij ontbrekende VOG actief in de signup-flow.</li>
          <li>IVA-tracking met 5-jaar geldigheid; goedkeuring door bestuurslid kantine via een dedicated capability.</li>
          <li>Diensten-planner staat live: dagelijkse template-expander, uurlijkse auto-complete cron, 72-uurs no-show venster.</li>
          <li>Boete-pipeline genereert direct bij no-show een €30 factuur (volunteer_fine) naar de primaire ouder of speler zelf.</li>
          <li>Member-facing /vrijwillig route is operationeel zodra Magic Login en bulk-provisioning van WP-accounts zijn ingericht.</li>
        </ul>
      </section>
    </div>
  );
}
