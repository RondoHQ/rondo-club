import { useQuery } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { usePassQr } from '@/hooks/usePassQr';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { prmApi } from '@/api/client';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { getMembershipPassPresentation } from './membershipPassUtils';

function PassError({ message }) {
  return (
    <div className="mx-auto max-w-md space-y-4">
      <Link to="/mijn-gegevens" className="inline-flex items-center gap-1 text-sm text-bright-cobalt hover:underline dark:text-electric-cyan">
        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
        Terug naar Mijn gegevens
      </Link>
      <div className="card p-5">
        <p className="text-sm text-gray-600 dark:text-gray-400">{message}</p>
      </div>
    </div>
  );
}

export default function MembershipPass() {
  useDocumentTitle('Mijn pas');

  const { personId = '' } = useParams();
  const [searchParams] = useSearchParams();
  const role = searchParams.get('role') || '';
  const numericPersonId = Number.parseInt(personId, 10);
  const validPersonId = Number.isInteger(numericPersonId) && numericPersonId > 0;

  const { data, isLoading, isError } = useQuery({
    queryKey: ['membership-pass-qr', numericPersonId, role],
    queryFn: async () => {
      const response = await prmApi.issueMembershipPassQrToken(numericPersonId, role ? { role } : {});
      return response.data;
    },
    enabled: validPersonId,
    retry: false,
  });

  const token = data?.token || '';
  const { url: qrDataUrl, error: qrError } = usePassQr(token);

  if (!validPersonId) return <PassError message="Deze pas kon niet worden gevonden." />;
  if (isLoading) return <ContentLoadingSpinner />;
  if (isError || !data?.payload?.pass_type || !data?.person) {
    return <PassError message="Deze pas is niet beschikbaar. Ga terug naar Mijn gegevens en probeer het opnieuw." />;
  }

  const presentation = getMembershipPassPresentation(data.payload.pass_type);
  const person = data.person;
  const roleLabel = data.pass?.role_label || '';
  const primaryDetailLabel = presentation.sponsor ? 'Bedrijf' : roleLabel ? 'Functie' : person.team ? 'Team' : '';
  const primaryDetailValue = presentation.sponsor ? person.company_name : roleLabel || person.team;
  const cardStyle = presentation.sponsor
    ? 'border-gray-200 bg-white text-gray-950'
    : 'border-white/25 text-white';
  const cardBackgroundStyle = {
    backgroundColor: data.pass?.background_color || (presentation.sponsor ? '#ffffff' : '#006935'),
  };
  const mutedStyle = presentation.sponsor ? 'text-gray-500' : 'text-white/75';

  return (
    <div className="mx-auto max-w-md space-y-4">
      <Link to="/mijn-gegevens" className="inline-flex items-center gap-1 text-sm text-bright-cobalt hover:underline dark:text-electric-cyan">
        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
        Terug naar Mijn gegevens
      </Link>

      <section
        className={`overflow-hidden rounded-[1.75rem] border shadow-xl ${cardStyle}`}
        style={cardBackgroundStyle}
        aria-label={presentation.title}
      >
        <div className="p-6 sm:p-7">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <div className={`text-xs font-semibold uppercase tracking-[0.16em] ${mutedStyle}`}>{presentation.eyebrow}</div>
              <h1 className="mt-1 text-xl font-semibold">{presentation.title}</h1>
            </div>
            <div className="flex h-16 w-20 shrink-0 items-center justify-center rounded-xl bg-white p-2 shadow-sm">
              <img src={data.pass?.logo_url} alt="" className="max-h-full max-w-full object-contain" />
            </div>
          </div>

          <div className="mt-7">
            <div className={`text-xs font-semibold uppercase tracking-wide ${mutedStyle}`}>Naam</div>
            <div className="mt-1 break-words text-2xl font-semibold leading-tight">{person.name}</div>
          </div>

          {primaryDetailValue ? (
            <div className="mt-5">
              <div className={`text-xs font-semibold uppercase tracking-wide ${mutedStyle}`}>{primaryDetailLabel}</div>
              <div className="mt-1 break-words text-sm font-medium">{primaryDetailValue}</div>
            </div>
          ) : null}

          {!presentation.sponsor && person.knvb_id ? (
            <div className="mt-5">
              <div className={`text-xs font-semibold uppercase tracking-wide ${mutedStyle}`}>KNVB-ID</div>
              <div className="mt-1 text-sm font-medium">{person.knvb_id}</div>
            </div>
          ) : null}

          <div className="mt-7 rounded-2xl bg-white p-4 shadow-sm">
            {qrError ? (
              <div className="flex aspect-square items-center justify-center text-center text-sm text-red-600">
                De QR-code kon niet worden gemaakt. Ververs deze pagina en probeer het opnieuw.
              </div>
            ) : qrDataUrl ? (
              <img src={qrDataUrl} alt={`QR-code van de pas van ${person.name}`} className="aspect-square h-auto w-full" />
            ) : (
              <div className="aspect-square animate-pulse rounded-xl bg-gray-100" aria-label="QR-code laden" />
            )}
          </div>

          <p className={`mt-4 text-center text-sm ${mutedStyle}`}>
            Laat deze QR-code bij de ingang scannen.
          </p>
        </div>
      </section>

      <p className="px-2 text-center text-xs text-gray-500 dark:text-gray-400">
        Deze pas wordt bij iedere scan gecontroleerd op geldigheid.
      </p>
    </div>
  );
}
