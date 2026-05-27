import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { HeartHandshake, FileCheck, Wine, CalendarClock, UsersRound, Users, AlertTriangle } from 'lucide-react';
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
    const gezinOrphan = units.filter((u) => u.kind === 'gezin' && u.data_quality === 'orphan').length;
    const gezinAddressFallback = units.filter((u) => u.kind === 'gezin' && u.data_quality === 'address_fallback').length;
    return { total, gezin, speler, gezinOrphan, gezinAddressFallback };
  }, [eligibility]);

  const diagnostics = eligibility?.diagnostics || null;
  const hasDataIssues =
    diagnostics &&
    ((diagnostics.skipped_no_leeftijdsgroep || 0) > 0 ||
      (diagnostics.skipped_non_paying || 0) > 0 ||
      (diagnostics.gezinnen_orphan || 0) > 0 ||
      (diagnostics.gezinnen_via_address || 0) > 0);

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-3">
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <HeartHandshake className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijwilligers</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Vrijwilligersbeleid — 2 diensten per jaar voor ouders (t/m JO16) en spelers (vanaf O17), zolang ze spelend/contributie-plichtig lid zijn.
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

      {hasDataIssues && (
        <section className="card p-5 border-l-4 border-amber-400">
          <div className="flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
            <div className="flex-1 min-w-0">
              <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Datakwaliteit</h2>
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Een deel van de doelgroep kon niet schoon worden afgeleid uit Sportlink/relaties. Deze records tellen mee, maar verdienen aandacht.
              </p>
              <ul className="mt-2 text-sm text-gray-700 dark:text-gray-300 space-y-1 list-disc pl-5">
                {(diagnostics.gezinnen_with_parents || 0) > 0 && (
                  <li><strong>{diagnostics.gezinnen_with_parents.toLocaleString('nl-NL')}</strong> gezinnen met ouder-relaties in Rondo — alles schoon.</li>
                )}
                {(diagnostics.gezinnen_via_address || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/datakwaliteit/address_fallback"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.gezinnen_via_address.toLocaleString('nl-NL')}</strong> gezinnen
                    </Link>{' '}
                    afgeleid uit adres-overeenkomst (geen ouder-relatie gevonden in <code>relationships</code>). Het loont om die relaties expliciet vast te leggen.
                  </li>
                )}
                {(diagnostics.gezinnen_orphan || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/datakwaliteit/orphan"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.gezinnen_orphan.toLocaleString('nl-NL')}</strong> gezinnen
                    </Link>{' '}
                    <em>zonder</em> ouder-relatie én zonder volwassen huisgenoot — alleen het kind staat in de eenheid. Boetes en e-mails kunnen voor deze records nergens heen tot er een ouder bekend is.
                  </li>
                )}
                {(diagnostics.skipped_no_leeftijdsgroep || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/datakwaliteit/missing_leeftijdsgroep"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.skipped_no_leeftijdsgroep.toLocaleString('nl-NL')}</strong> personen
                    </Link>{' '}
                    overgeslagen omdat ze geen <code>leeftijdsgroep</code> hebben. Vaak ex-leden of niet-spelende ouders — geen probleem als dat klopt, wel een rode vlag als er actieve jeugdspelers tussen zitten.
                  </li>
                )}
                {(diagnostics.skipped_non_paying || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/datakwaliteit/non_paying"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.skipped_non_paying.toLocaleString('nl-NL')}</strong> personen
                    </Link>{' '}
                    buiten de doelgroep gehouden omdat ze geen spelend/contributie-plichtig lid zijn (ex-leden, donateurs, ereleden, contributievrije leden).
                  </li>
                )}
              </ul>
            </div>
          </div>
        </section>
      )}

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
