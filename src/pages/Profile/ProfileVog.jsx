import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { FileCheck, CheckCircle2, AlertTriangle, ArrowLeft } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { format } from '@/utils/dateFormat';

const STATUS_CONFIG = {
  valid:   { label: 'Geldig',   color: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300', icon: CheckCircle2 },
  expired: { label: 'Verlopen', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',                 icon: AlertTriangle },
  missing: { label: 'Geen VOG', color: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',                icon: AlertTriangle },
};

const DEFAULT_PROFILE_TEXTS = {
  missing: 'Je hebt nog geen VOG ingeleverd. De VOG-coördinator kan een aanvraag voor je starten via Justis — de aanvraag is gratis voor vrijwilligers.',
  expired: 'Je VOG is verlopen. Neem contact op met de VOG-coördinator om een nieuwe aanvraag te starten.',
  renewal: 'Je VOG verloopt binnenkort — neem contact op met de VOG-coördinator om de aanvraag te vernieuwen.',
};

export default function ProfileVog() {
  useDocumentTitle('Mijn VOG');

  const { data: vog, isLoading, error } = useQuery({
    queryKey: ['vog', 'me'],
    queryFn: async () => (await prmApi.getMyVog()).data,
    staleTime: 60 * 1000,
  });

  if (error?.response?.status === 404) {
    return (
      <div className="max-w-2xl mx-auto p-6">
        <div className="card p-6 text-center">
          <AlertTriangle className="w-8 h-8 text-amber-500 mx-auto mb-2" />
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">Geen gekoppeld lid-profiel</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
            Je account is nog niet gekoppeld aan een lid-record. Neem contact op met de ledenadministratie.
          </p>
        </div>
      </div>
    );
  }

  const status = vog?.status || 'missing';
  const cfg    = STATUS_CONFIG[status] || STATUS_CONFIG.missing;
  const Icon   = cfg.icon;
  const profileTexts = { ...DEFAULT_PROFILE_TEXTS, ...vog?.profile_texts };

  return (
    <div className="max-w-3xl mx-auto p-4 sm:p-6 space-y-6">
      <header className="space-y-2">
        <Link
          to="/profile"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar profiel
        </Link>
        <div className="flex items-center gap-3">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <FileCheck className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Mijn VOG</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Verklaring Omtrent Gedrag. Verplicht voor sommige vrijwilligersrollen; 3 jaar geldig.
            </p>
          </div>
        </div>
      </header>

      {isLoading ? (
        <ContentLoadingSpinner />
      ) : (
        <div className="card p-5">
          <div className="flex items-start gap-3">
            <Icon className="w-5 h-5 mt-0.5 shrink-0 text-gray-500 dark:text-gray-400" />
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${cfg.color}`}>
                  {cfg.label}
                </span>
                {vog?.datum_vog && (
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    Afgegeven op {format(vog.datum_vog, 'd MMMM yyyy')}
                  </span>
                )}
                {vog?.expires_at && (
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    · Verloopt op {format(vog.expires_at, 'd MMMM yyyy')}
                  </span>
                )}
              </div>
              {status === 'missing' && (
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  {profileTexts.missing}
                </p>
              )}
              {status === 'expired' && (
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  {profileTexts.expired}
                </p>
              )}
              {vog?.needs_renewal_reminder && status === 'valid' && (
                <p className="mt-2 text-sm text-amber-700 dark:text-amber-400">
                  {profileTexts.renewal}
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      <div className="card p-5 text-sm text-gray-600 dark:text-gray-400 space-y-2">
        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Wat is een VOG?</h2>
        <p>
          Een Verklaring Omtrent Gedrag (VOG) wordt afgegeven door Justis (Ministerie van Justitie). De VOG-coördinator van de vereniging start de aanvraag voor je en zorgt dat je de uitnodiging per e-mail krijgt; je hoeft zelf alleen op &ldquo;Aanvraag bevestigen&rdquo; te klikken en met DigiD in te loggen.
        </p>
        <p>
          De vereniging is bij het NOC*NSF aangesloten en kan VOG&apos;s gratis aanvragen voor vrijwilligers. Voor sommige rollen — zoals jeugdtrainer of bestuurslid — is een geldige VOG verplicht.
        </p>
      </div>
    </div>
  );
}
