import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { HeartHandshake, FileCheck, Wine, CalendarClock, UsersRound, Users, AlertTriangle, RefreshCw } from 'lucide-react';
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
  const queryClient = useQueryClient();

  const refreshMutation = useMutation({
    mutationFn: () => prmApi.refreshVolunteerCache(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'eligibility'] });
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'relationship-quality'] });
    },
  });

  const { data: eligibility, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'eligibility'],
    queryFn: async () => {
      const response = await prmApi.getVolunteerEligibility();
      return response.data;
    },
    staleTime: 5 * 60 * 1000,
  });

  const totalRequired = useMemo(() => {
    if (!eligibility?.units) return 0;
    return eligibility.units.reduce((total, unit) => total + (Number(unit.required_count) || 0), 0);
  }, [eligibility]);

  const diagnostics = eligibility?.diagnostics || null;
  const hasDataIssues =
    diagnostics &&
    ((diagnostics.skipped_no_leeftijdsgroep || 0) > 0 ||
      (diagnostics.skipped_former_members || 0) > 0 ||
      (diagnostics.gezinnen_orphan || 0) > 0 ||
      (diagnostics.gezinnen_via_address || 0) > 0 ||
      (diagnostics.no_email || 0) > 0 ||
      (diagnostics.suspect_relationships || 0) > 0);

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-3">
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <HeartHandshake className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div className="flex-1 min-w-0">
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijwilligers</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Vrijwilligersbeleid — 2 inschrijftaken voor het eerste kind t/m JO16 en per speler vanaf O17; volgende kinderen tellen met gezinskorting mee.
          </p>
        </div>
        <button
          type="button"
          onClick={() => refreshMutation.mutate()}
          disabled={refreshMutation.isLoading}
          className="shrink-0 inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-50"
          title="Cache wissen en data opnieuw berekenen (~10s)"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${refreshMutation.isLoading ? 'animate-spin' : ''}`} />
          {refreshMutation.isLoading ? 'Bezig…' : 'Ververs'}
        </button>
      </header>

      {error && (
        <div className="card p-4 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
          Kon eligibility niet ophalen: {error?.message || 'onbekende fout'}
        </div>
      )}

      <section>
        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-3">
          Doelgroep en planning dit seizoen
        </h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label="Inschrijftaken vereist"
            value={isLoading ? '…' : totalRequired.toLocaleString('nl-NL')}
            sub={eligibility?.season ? `Seizoen ${eligibility.season}` : null}
            icon={Users}
          />
          <StatCard
            label="Inschrijftaken totaal"
            value={isLoading ? '…' : (eligibility?.shift_capacity?.total_slots ?? 0).toLocaleString('nl-NL')}
            sub="Totaal aantal plekken in inschrijftaken"
            icon={CalendarClock}
          />
          <StatCard
            label="Inschrijftaken ingeroosterd"
            value={isLoading ? '…' : (eligibility?.shift_capacity?.assigned_slots ?? 0).toLocaleString('nl-NL')}
            sub="Aantal plekken met een vrijwilliger"
            icon={UsersRound}
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
                {(diagnostics.no_email || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/datakwaliteit/no_email"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.no_email.toLocaleString('nl-NL')}</strong> actieve leden
                    </Link>{' '}
                    <em>zonder e-mailadres</em> — zij kunnen geen account activeren via <code>/activeren</code> tot iemand een adres verzamelt. Ledenadministratie kan de lijst exporteren en nabellen.
                  </li>
                )}
                {(diagnostics.skipped_no_leeftijdsgroep || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/datakwaliteit/missing_leeftijdsgroep"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.skipped_no_leeftijdsgroep.toLocaleString('nl-NL')}</strong> actieve leden
                    </Link>{' '}
                    zonder <code>leeftijdsgroep</code>. Sportlink-sync ontbreekt, of het leeftijdsgroep-veld is leeg — corrigeer het op de persoonspagina zodat ze meegenomen worden in de doelgroep.
                  </li>
                )}
                {(diagnostics.skipped_former_members || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/datakwaliteit/former_members"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.skipped_former_members.toLocaleString('nl-NL')}</strong> ex-leden
                    </Link>{' '}
                    buiten de doelgroep gehouden (former_member). Contributievrije leden zijn bewust niet uitgesloten — een vrijstelling van contributie is geen vrijstelling van vrijwilligerstaken.
                  </li>
                )}
                {(diagnostics.suspect_relationships || 0) > 0 && (
                  <li>
                    <Link
                      to="/vrijwilligers/relatie-check"
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      <strong>{diagnostics.suspect_relationships.toLocaleString('nl-NL')}</strong> verdachte familie-relaties
                    </Link>{' '}
                    — ouder/kind-koppels waar het leeftijdsverschil te klein is (vaak in werkelijkheid siblings) of sibling-koppels met een wel erg groot leeftijdsverschil.
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
          <StatCard label="Inschrijftaken" value="Planner" sub="Inschrijftaken en aanmeldingen" icon={CalendarClock} href="/vrijwilligers/diensten" />
          <StatCard label="Vrijstellingen" value="Beheer" sub="Handmatige + auto vrijstellingen" icon={UsersRound} href="/vrijwilligers/vrijstellingen" />
        </div>
      </section>
    </div>
  );
}
