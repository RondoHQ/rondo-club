import { useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Wine, Upload, CheckCircle2, Clock, AlertTriangle, ArrowLeft } from 'lucide-react';
import { prmApi } from '@/api/client';
import IvaCertificateLink from '@/components/IvaCertificateLink';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { format } from '@/utils/dateFormat';

const STATUS_CONFIG = {
  valid:   { label: 'Geldig',                color: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300', icon: CheckCircle2 },
  pending: { label: 'Wacht op goedkeuring',  color: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',         icon: Clock },
  expired: { label: 'Verlopen',              color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',                 icon: AlertTriangle },
  missing: { label: 'Niet ingeleverd',       color: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',                icon: AlertTriangle },
};

export default function ProfileIva() {
  useDocumentTitle('Mijn IVA-certificaat');
  const queryClient = useQueryClient();
  const fileRef = useRef(null);
  const [file, setFile] = useState(null);
  const [datum, setDatum] = useState('');
  const [feedback, setFeedback] = useState(null);

  const { data: iva, isLoading, error } = useQuery({
    queryKey: ['iva', 'me'],
    queryFn: async () => (await prmApi.getMyIva()).data,
    staleTime: 60 * 1000,
  });

  const uploadMutation = useMutation({
    mutationFn: ({ file, datumIva }) => prmApi.uploadMyIva(file, datumIva),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['iva', 'me'] });
      setFile(null);
      setDatum('');
      if (fileRef.current) fileRef.current.value = '';
      const autoVerified = res?.data?.auto_verified;
      setFeedback({
        kind: 'success',
        message: autoVerified
          ? 'Certificaat geüpload en automatisch geverifieerd — je IVA is direct geldig.'
          : 'Certificaat geüpload. De kantinebeheerder beoordeelt het binnenkort.',
      });
    },
    onError: (err) => {
      const msg = err?.response?.data?.message || err?.message || 'Upload mislukt.';
      setFeedback({ kind: 'error', message: msg });
    },
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

  const status = iva?.status || 'missing';
  const cfg    = STATUS_CONFIG[status] || STATUS_CONFIG.missing;
  const Icon   = cfg.icon;

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
            <Wine className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Mijn IVA-certificaat</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Verplicht voor inschrijftaken achter de bar. Gratis online behaalbaar via{' '}
              <a
                href="https://www.nocnsf.nl/over-nocnsf/sport-en-maatschappij/gezonde-sportomgeving/e-learning-verantwoord-alcohol-schenken"
                target="_blank"
                rel="noreferrer noopener"
                className="text-bright-cobalt dark:text-electric-cyan hover:underline"
              >
                NOC*NSF
              </a>
              ; 5 jaar geldig.
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
                {iva?.datum_iva && (
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    Behaald op {format(iva.datum_iva, 'd MMMM yyyy')}
                  </span>
                )}
                {iva?.expires_at && (
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    · Verloopt op {format(iva.expires_at, 'd MMMM yyyy')}
                  </span>
                )}
              </div>
              {iva?.iva_certificaat_url && (
                <IvaCertificateLink personId={iva.person_id} className="mt-2 text-sm">
                  Bekijk huidige certificaat
                </IvaCertificateLink>
              )}
              {status === 'pending' && (
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  De kantinebeheerder beoordeelt je certificaat binnenkort. Zodra het is goedgekeurd verschijnen inschrijftaken achter de bar in het rooster.
                </p>
              )}
              {status === 'expired' && (
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  Je certificaat is verlopen. Behaal een nieuwe via NOC*NSF en upload het hieronder.
                </p>
              )}
              {iva?.needs_renewal_reminder && status === 'valid' && (
                <p className="mt-2 text-sm text-amber-700 dark:text-amber-400">
                  Je certificaat verloopt binnenkort — vernieuw het op tijd om inschrijftaken achter de bar te kunnen blijven doen.
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      <section className="card p-5">
        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Upload nieuw certificaat</h2>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
          PDF, JPG of PNG; max 10 MB. Officiële NOC*NSF PDF-certificaten worden automatisch gecontroleerd en direct goedgekeurd; anders beoordeelt de kantinebeheerder het certificaat.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault();
            setFeedback(null);
            if (!file) {
              setFeedback({ kind: 'error', message: 'Kies eerst een bestand.' });
              return;
            }
            uploadMutation.mutate({ file, datumIva: datum });
          }}
          className="mt-4 space-y-4"
        >
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Datum certificaat</label>
            <input
              type="date"
              value={datum}
              onChange={(e) => setDatum(e.target.value)}
              max={new Date().toISOString().slice(0, 10)}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Optioneel — leeg laten gebruikt vandaag.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Bestand</label>
            <input
              ref={fileRef}
              type="file"
              accept="application/pdf,image/jpeg,image/png"
              onChange={(e) => setFile(e.target.files?.[0] || null)}
              className="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-bright-cobalt file:text-white dark:file:bg-electric-cyan dark:file:text-gray-900"
            />
          </div>

          {feedback && (
            <div
              className={`text-sm ${
                feedback.kind === 'success'
                  ? 'text-emerald-700 dark:text-emerald-300'
                  : 'text-red-700 dark:text-red-300'
              }`}
            >
              {feedback.message}
            </div>
          )}

          <button
            type="submit"
            disabled={!file || uploadMutation.isLoading}
            className="inline-flex items-center gap-2 btn-primary disabled:opacity-50"
          >
            <Upload className="w-4 h-4" />
            {uploadMutation.isLoading ? 'Bezig met uploaden…' : 'Certificaat uploaden'}
          </button>
        </form>
      </section>
    </div>
  );
}
