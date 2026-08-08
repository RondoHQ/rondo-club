import { useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { HeartHandshake, AlertTriangle, CheckCircle2, XCircle, Calendar, ShieldAlert, Users } from 'lucide-react';
import { SiWhatsapp } from '@icons-pack/react-simple-icons';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { formatStoredDateTime } from '@/utils/dateFormat';
import { openHtmlLinksInNewTab } from '@/utils/richTextUtils';
import { shiftCalendarKeys } from '@/utils/shiftQueryCache';
import { decodeHtml } from '@/utils/formatters';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import ShiftCoverageCalendar from '@/components/volunteers/ShiftCoverageCalendar';

// Diensten in the second half of the season are visible before they open, so
// members can see what is coming instead of wondering where spring went.
function formatOpensAt(value) {
  return formatStoredDateTime(value, 'd MMMM', value);
}

function StatusBadge({ kind, children }) {
  const styles = {
    voldaan: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    'op-weg': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
    risico: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    'geen-actie': 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    exempt: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  };
  return (
    <span className={`inline-block shrink-0 whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium ${styles[kind] || styles['geen-actie']}`}>
      {children}
    </span>
  );
}

function obligationTitle(obligation, hasBoth) {
  if (obligation.kind !== 'gezin') {
    return hasBoth ? 'Jouw eigen dienstplicht' : 'Jouw vrijwilligersplicht';
  }
  const children = obligation.child_count || 0;
  return children > 1 ? `Jullie gezinsplicht (${children} kinderen)` : 'Jullie gezinsplicht';
}

function ExemptionCard({ exemption, family = false }) {
  const hasActiveRole = ['commissie', 'staff'].includes(exemption.reason);

  return (
    <div className="card p-5 border-l-4 border-purple-400">
      <div className="flex items-start gap-3">
        <CheckCircle2 className="w-5 h-5 text-purple-500 mt-0.5 shrink-0" />
        <div>
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">
            {family ? 'Jullie gezin is vrijgesteld' : 'Je bent vrijgesteld'}
          </h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {family ? (
              hasActiveRole ? (
                <><strong>{exemption.person_name || 'Een ouder/verzorger'}</strong> heeft al een actieve vrijwilligersrol binnen de club. Jullie gezin hoeft daarom dit seizoen geen inschrijftaken in te plannen, maar jullie <em>mogen</em> natuurlijk wel meedoen.</>
              ) : (
                <><strong>{exemption.person_name || 'Een ouder/verzorger'}</strong> heeft een vrijstelling ({exemption.reason_label}). Jullie gezin hoeft daarom dit seizoen geen inschrijftaken te plannen, maar jullie mogen natuurlijk wel meedoen.</>
              )
            ) : hasActiveRole ? (
              <>Je hebt al een actieve vrijwilligersrol binnen de club. Je hoeft daarom dit seizoen geen inschrijftaken in te plannen, maar je <em>mag</em> natuurlijk wel meedoen.</>
            ) : (
              <>{exemption.reason_label}. Je hoeft dit seizoen geen inschrijftaken te plannen, maar je mag natuurlijk wel meedoen.</>
            )}
          </p>
        </div>
      </div>
    </div>
  );
}

function ObligationCard({ obligation, hasBoth }) {
  if (obligation.exemption) {
    return <ExemptionCard exemption={obligation.exemption} family={obligation.kind === 'gezin'} />;
  }

  const required = obligation.required_count || 0;
  const completed = obligation.completed_count || 0;
  const pending = obligation.pending_count || 0;
  const remainingToSchedule = Math.max(0, required - completed - pending);

  return (
    <div className="card p-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">{obligationTitle(obligation, hasBoth)}</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Je hebt <strong>{completed}</strong> van <strong>{required}</strong> inschrijftaken gedaan dit seizoen.
            {pending > 0 && <> Je staat al ingepland voor {pending} {pending === 1 ? 'inschrijftaak' : 'inschrijftaken'}.</>}
            {remainingToSchedule > 0 ? (
              <> Kies wanneer het jou uitkomt nog <strong>{remainingToSchedule}</strong> {remainingToSchedule === 1 ? 'inschrijftaak' : 'inschrijftaken'}.</>
            ) : completed < required && (
              <> Je hebt alle benodigde inschrijftaken ingepland.</>
            )}
          </p>
        </div>
        <StatusBadge kind={obligation.status}>
          {obligation.status === 'voldaan' && 'Voldaan'}
          {obligation.status === 'op-weg' && 'Op weg'}
          {obligation.status === 'risico' && 'Nog te plannen'}
          {obligation.status === 'geen-actie' && 'Open'}
        </StatusBadge>
      </div>
      {/* Progress bar */}
      <div className="mt-4 h-2 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
        <div
          className="h-full bg-emerald-500 dark:bg-emerald-400"
          style={{ width: `${required > 0 ? Math.min(100, (completed / required) * 100) : 0}%` }}
        />
      </div>
    </div>
  );
}

function ObligationList({ obligations, exemption, identity }) {
  if (exemption) {
    return <ExemptionCard exemption={exemption} />;
  }

  if (!obligations || obligations.length === 0) {
    return (
      <div className="card p-5">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          {identity?.is_youth && !identity?.pending_guardian
            ? `Dit account staat op naam van ${identity.linked_person_name}. Als je zelf ${identity.linked_person_name} bent, heb je geen persoonlijke vrijwilligersplicht. Ben je de ouder of verzorger, geef dat dan hierboven aan zodat de gezinsplicht zichtbaar wordt.`
            : 'Je valt op dit moment niet onder de vrijwilligersplicht. Je mag je natuurlijk wel aanmelden voor inschrijftaken.'}
        </p>
      </div>
    );
  }

  // A speler who is also a parent carries both duties. Diensten vullen eerst de
  // eigen dienstplicht, daarna de gezinsplicht.
  const hasBoth = obligations.length > 1;

  return (
    <div className="space-y-3">
      {obligations.map((obligation) => (
        <ObligationCard key={obligation.unit_id} obligation={obligation} hasBoth={hasBoth} />
      ))}
    </div>
  );
}

function GuardianIdentityCard({ identity, name, setName, isOpen, setIsOpen, mutation }) {
  if (!identity?.is_youth) return null;

  if (identity.pending_guardian) {
    return (
      <div className="card p-5 border-l-4 border-cyan-400">
        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Aangemeld als ouder/verzorger</h2>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Je gebruikt Rondo als <strong>{identity.pending_guardian.name}</strong>, ouder/verzorger van {identity.linked_person_name}.
          De ledenadministratie koppelt dit account aan je eigen persoonsrecord zodra dat uit Sportlink is gesynchroniseerd.
        </p>
      </div>
    );
  }

  return (
    <div className="card p-5 border-l-4 border-amber-400">
      <h2 className="font-semibold text-gray-900 dark:text-gray-100">Gebruik je dit account als ouder/verzorger?</h2>
      <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
        Dit account staat op naam van {identity.linked_person_name}. Ben je de ouder of verzorger, dan kun je alvast doorgaan terwijl de ledenadministratie je eigen persoonsrecord verwerkt.
      </p>
      {isOpen ? (
        <form
          className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:flex-wrap"
          onSubmit={(event) => {
            event.preventDefault();
            mutation.mutate(name);
          }}
        >
          <label className="flex-1 text-sm font-medium text-gray-700 dark:text-gray-200">
            Jouw volledige naam
            <input
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              minLength={2}
              maxLength={120}
              autoComplete="name"
              required
              className="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            />
          </label>
          <div className="flex gap-2">
            <button type="button" onClick={() => setIsOpen(false)} className="btn-tertiary">Annuleren</button>
            <button type="submit" disabled={mutation.isPending} className="btn-primary disabled:opacity-50">
              {mutation.isPending ? 'Bezig…' : 'Ga verder als ouder/verzorger'}
            </button>
          </div>
          {mutation.isError && (
            <p className="basis-full text-sm text-red-600 dark:text-red-400">
              {mutation.error?.response?.data?.message || 'Aanmelden als ouder/verzorger is niet gelukt.'}
            </p>
          )}
        </form>
      ) : (
        <button type="button" onClick={() => setIsOpen(true)} className="btn-primary mt-4">
          Ik ben ouder/verzorger van {identity.linked_person_name}
        </button>
      )}
    </div>
  );
}

function BlockBanners({ blockReasons }) {
  if (!blockReasons || blockReasons.length === 0) return null;
  return (
    <div className="space-y-2">
      {blockReasons.includes('vog') && (
        <div className="card p-4 border-l-4 border-amber-400 flex items-start gap-3">
          <ShieldAlert className="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
          <div className="text-sm">
            <strong className="text-gray-900 dark:text-gray-100">VOG ontbreekt of is verlopen.</strong>
            <p className="text-gray-600 dark:text-gray-400 mt-0.5">
              Inschrijftaken waarvoor een geldige VOG nodig is, zijn nog niet zichtbaar. De VOG-coördinator kan een aanvraag voor je starten.
            </p>
          </div>
        </div>
      )}
      {blockReasons.includes('iva') && (
        <div className="card p-4 border-l-4 border-amber-400 flex items-start gap-3">
          <ShieldAlert className="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
          <div className="text-sm">
            <strong className="text-gray-900 dark:text-gray-100">Je kunt nog geen inschrijftaken achter de bar uitvoeren</strong>
            <p className="text-gray-600 dark:text-gray-400 mt-0.5">
              Hiervoor moet je eerst je{' '}
              <a
                href="https://www.nocnsf.nl/over-nocnsf/sport-en-maatschappij/gezonde-sportomgeving/e-learning-verantwoord-alcohol-schenken"
                target="_blank"
                rel="noreferrer noopener"
                className="text-bright-cobalt dark:text-electric-cyan hover:underline"
              >
                IVA certificaat halen via NOC*NSF
              </a>
              , waarin je leert wat verantwoord alcohol schenken betekent. Dat is gratis en kost je 20–30 minuten. Als je dat gedaan hebt, kun je het{' '}
              <Link to="/profile/iva" className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                hier uploaden
              </Link>
              . Heb je al een certificaat Sociale Hygiëne? Dat is ook goed — upload dat dan.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}

function ShiftRow({ shift, onSignup, onCancel, signupMutation, cancelMutation, isMine }) {
  const start = shift.start_datetime;
  const end = shift.end_datetime;
  const fellowVolunteers = shift.fellow_volunteers || [];
  const fellowVolunteerContacts = shift.fellow_volunteer_contacts || [];
  const volunteerLabel = fellowVolunteers.length > 0
    ? `Aangemeld: ${fellowVolunteers.join(', ')}`
    : (shift.is_signed_up ? 'Je bent tot nu toe de enige aanmelding.' : 'Nog niemand aangemeld.');
  const action = isMine ? (
    ['voltooid', 'geannuleerd'].includes(shift.status) || shift.no_show ? null : (
      shift.can_cancel ? (
        <button
          onClick={() => onCancel(shift.id)}
          disabled={cancelMutation.isLoading}
          className="text-xs px-3 py-1.5 rounded bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300"
        >
          Afmelden
        </button>
      ) : (
        <span
          className="text-xs text-gray-500 dark:text-gray-400"
          title="Neem contact op met de vrijwilligerscoördinator"
        >
          Afmelden niet meer mogelijk
        </span>
      )
    )
  ) : shift.is_signed_up ? (
    <button
      onClick={() => onCancel(shift.id)}
      disabled={cancelMutation.isLoading || !shift.can_cancel}
      className="text-xs px-3 py-1.5 rounded bg-emerald-100 text-emerald-800 hover:bg-red-100 hover:text-red-800 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-red-900/30 dark:hover:text-red-300 inline-flex items-center gap-1"
      title={shift.can_cancel ? 'Klik om af te melden' : 'Afmelden kan alleen via de vrijwilligerscoördinator'}
    >
      <CheckCircle2 className="w-3.5 h-3.5" /> {shift.can_cancel ? 'Reeds aangemeld' : 'Aangemeld'}
    </button>
  ) : (
    <button
      onClick={() => {
        if (shift.signup_is_final_after_grace) {
          const confirmed = window.confirm(
            'Deze inschrijftaak begint binnen 3 weken. Na aanmelden heb je 30 minuten om een fout te herstellen; daarna kan alleen de vrijwilligerscoördinator je afmelden. Wil je doorgaan?'
          );
          if (!confirmed) return;
        }
        onSignup(shift.id);
      }}
      disabled={signupMutation.isLoading || !shift.can_signup}
      className="text-xs px-3 py-1.5 rounded bg-bright-cobalt text-white hover:bg-bright-cobalt/90 disabled:opacity-50 dark:bg-electric-cyan dark:text-gray-900"
      title={shift.signup_opens_at ? `Inschrijven kan vanaf ${formatOpensAt(shift.signup_opens_at)}` : undefined}
    >
      {shift.signup_opens_at
        ? `Vanaf ${formatOpensAt(shift.signup_opens_at)}`
        : shift.can_signup ? 'Aanmelden' : 'Niet beschikbaar'}
    </button>
  );

  return (
    <li className="card p-4">
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-3">
          <h3 className="font-semibold text-gray-900 dark:text-gray-100">{decodeHtml(shift.dienst_type_name || shift.title)}</h3>
          <div className="shrink-0">
            {shift.no_show ? (
              <span className="text-xs font-medium text-red-700 dark:text-red-300 inline-flex items-center gap-1">
                <XCircle className="w-3.5 h-3.5" /> No-show
              </span>
            ) : action}
          </div>
        </div>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-0.5 inline-flex items-center gap-1">
          <Calendar className="w-3.5 h-3.5" />
          {formatStoredDateTime(start, 'EEEE d MMMM, HH:mm')} – {formatStoredDateTime(end, 'HH:mm', '')}
        </p>
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-start gap-1">
          <Users className="w-3.5 h-3.5 mt-0.5 shrink-0" />
          {isMine && fellowVolunteerContacts.length > 0 ? (
            <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span>Samen met:</span>
              {fellowVolunteerContacts.map((volunteer) => (
                volunteer.whatsapp_url ? (
                  <a
                    key={volunteer.name}
                    href={volunteer.whatsapp_url}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="inline-flex items-center gap-1 text-[#128C7E] hover:underline dark:text-[#25D366]"
                    aria-label={`Stuur ${volunteer.name} een WhatsApp-bericht`}
                  >
                    {volunteer.name}
                    <SiWhatsapp className="w-3.5 h-3.5" aria-hidden="true" />
                  </a>
                ) : (
                  <span key={volunteer.name}>{volunteer.name}</span>
                )
              ))}
            </span>
          ) : (
            <span>{isMine ? 'Je bent tot nu toe de enige aanmelding.' : volunteerLabel}</span>
          )}
        </p>
        {shift.capacity > 0 && (
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {shift.assigned_count} van {shift.capacity} plekken bezet
            {shift.spots_remaining > 0 && <> · {shift.spots_remaining} vrij</>}
          </p>
        )}
        {shift.status === 'voltooid' && (
          <span className="inline-block mt-2 text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
            Voltooid
          </span>
        )}
        {shift.status === 'geannuleerd' && (
          <div className="mt-2">
            <span className={`inline-block text-xs px-2 py-0.5 rounded ${shift.cancellation?.credit_awarded ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'}`}>
              {shift.cancellation?.credit_awarded ? 'Geannuleerd — telt mee' : 'Geannuleerd — telt niet mee'}
            </span>
            {shift.cancellation?.reason && (
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Reden: {shift.cancellation.reason}</p>
            )}
          </div>
        )}
      </div>
    </li>
  );
}

export default function Vrijwillig() {
  useDocumentTitle('Vrijwilligers — Aanmelden');
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const [tab, setTab] = useState('available');
  const [confirmOverlap, setConfirmOverlap] = useState(null); // { shiftId, message }
  const [actionError, setActionError] = useState('');
  const [guardianClaimOpen, setGuardianClaimOpen] = useState(false);
  const [guardianName, setGuardianName] = useState('');
  const selectedDienstType = searchParams.get('diensttype') || '';
  const volunteerSignupInfo = window.rondoConfig?.volunteerSignupInfo || '';

  const { data: mine, isLoading: mineLoading, error: mineError } = useQuery({
    queryKey: ['my-shifts'],
    queryFn: async () => (await prmApi.getMyShifts()).data,
    staleTime: 60 * 1000,
  });

  const { data: calendarData, isLoading: calendarLoading } = useQuery({
    queryKey: shiftCalendarKeys.signup(selectedDienstType),
    queryFn: async () => (await prmApi.getShiftCalendar({
      view: 'signup',
      dienst_type_id: selectedDienstType || undefined,
    })).data,
    staleTime: 60 * 1000,
  });

  const signupMutation = useMutation({
    mutationFn: ({ shiftId, forceOverlap = false }) => prmApi.signupForShift(shiftId, { force_overlap: forceOverlap }),
    onMutate: () => {
      setActionError('');
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['my-shifts'] });
      queryClient.invalidateQueries({ queryKey: shiftCalendarKeys.all });
      setConfirmOverlap(null);
    },
    onError: (error, variables) => {
      const code = error?.response?.data?.code;
      const data = error?.response?.data?.data || {};
      if (code === 'overlap_warning' && data.can_force) {
        setConfirmOverlap({
          shiftId: variables?.shiftId ?? data.shift_id,
          message: error?.response?.data?.message,
          overlap: data.overlap_shift || null,
        });
        return;
      }
      setActionError(error?.response?.data?.message || 'Aanmelden is niet gelukt.');
    },
  });

  const cancelMutation = useMutation({
    mutationFn: (shiftId) => prmApi.cancelShift(shiftId),
    onSuccess: () => {
      setActionError('');
      queryClient.invalidateQueries({ queryKey: ['my-shifts'] });
      queryClient.invalidateQueries({ queryKey: shiftCalendarKeys.all });
    },
    onError: (error) => {
      setActionError(error?.response?.data?.message || 'Afmelden is niet gelukt.');
    },
  });

  const guardianMutation = useMutation({
    mutationFn: (name) => prmApi.claimGuardianAccount(name),
    onSuccess: () => {
      setGuardianClaimOpen(false);
      queryClient.invalidateQueries({ queryKey: ['my-shifts'] });
      queryClient.invalidateQueries({ queryKey: ['current-user'] });
    },
  });

  const upcomingShifts = useMemo(
    () => (mine?.shifts || []).filter(
      (shift) => ['open', 'vol'].includes(shift.status)
        && (!selectedDienstType || String(shift.dienst_type_id) === selectedDienstType)
    ),
    [mine, selectedDienstType]
  );
  const pastShifts = useMemo(
    () => (mine?.shifts || []).filter(
      (shift) => ['voltooid', 'geannuleerd'].includes(shift.status)
        && (!selectedDienstType || String(shift.dienst_type_id) === selectedDienstType)
    ),
    [mine, selectedDienstType]
  );
  const availableCount = useMemo(
    () => (calendarData?.days || []).reduce(
      (count, day) => count + day.shifts.filter((shift) => shift.can_signup).length,
      0
    ),
    [calendarData]
  );

  const handleDienstTypeChange = (dienstType) => {
    setSearchParams((previousParams) => {
      const nextParams = new URLSearchParams(previousParams);
      if (dienstType) {
        nextParams.set('diensttype', dienstType);
      } else {
        nextParams.delete('diensttype');
      }
      return nextParams;
    }, { replace: true });
  };

  if (mineError && mineError?.response?.status === 404) {
    return (
      <div className="max-w-2xl mx-auto p-6">
        <div className="card p-6 text-center">
          <AlertTriangle className="w-8 h-8 text-amber-500 mx-auto mb-2" />
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">Geen gekoppeld lid-profiel</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
            Je account is nog niet gekoppeld aan een lid-record. Neem contact op met de ledenadministratie zodat je je voor inschrijftaken kunt aanmelden.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
      <header className="flex items-center gap-3">
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <HeartHandshake className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div className="flex-1 min-w-0 sm:pr-6">
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijwilligers</h1>
          {volunteerSignupInfo ? (
            <div className="text-sm text-gray-500 dark:text-gray-400">
              <div
                className="contents prose prose-sm max-w-none prose-p:inline prose-p:my-0 prose-p:text-inherit prose-a:text-bright-cobalt prose-a:font-medium hover:prose-a:underline dark:prose-invert dark:prose-a:text-electric-cyan"
                dangerouslySetInnerHTML={{ __html: openHtmlLinksInNewTab(volunteerSignupInfo) }}
              />
            </div>
          ) : null}
        </div>
        <Link
          to="/profile"
          className="text-sm text-bright-cobalt dark:text-electric-cyan hover:underline shrink-0"
        >
          Mijn certificaten
        </Link>
      </header>

      <GuardianIdentityCard
        identity={mine?.identity}
        name={guardianName}
        setName={setGuardianName}
        isOpen={guardianClaimOpen}
        setIsOpen={setGuardianClaimOpen}
        mutation={guardianMutation}
      />

      {mineLoading ? (
        <ContentLoadingSpinner />
      ) : (
        <>
          <ObligationList obligations={mine?.obligations} exemption={mine?.exemption} identity={mine?.identity} />
          <BlockBanners blockReasons={calendarData?.block_reasons} />

          <nav className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
            <button
              onClick={() => setTab('available')}
              className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
                tab === 'available'
                  ? 'border-bright-cobalt text-bright-cobalt dark:border-electric-cyan dark:text-electric-cyan'
                  : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
              }`}
            >
              Beschikbaar ({availableCount})
            </button>
            <button
              onClick={() => setTab('mine')}
              className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
                tab === 'mine'
                  ? 'border-bright-cobalt text-bright-cobalt dark:border-electric-cyan dark:text-electric-cyan'
                  : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
              }`}
            >
              Mijn inschrijftaken ({(mine?.shifts || []).length})
            </button>
          </nav>

          {tab === 'mine' && ((calendarData?.dienst_types || []).length > 1 || selectedDienstType) && (
            <div className="flex flex-col gap-1.5 sm:max-w-xs">
              <label
                htmlFor="diensttype-filter"
                className="text-sm font-medium text-gray-700 dark:text-gray-200"
              >
                Inschrijftaak
              </label>
              <select
                id="diensttype-filter"
                value={selectedDienstType}
                onChange={(event) => handleDienstTypeChange(event.target.value)}
                className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-bright-cobalt focus:outline-none focus:ring-1 focus:ring-bright-cobalt dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-electric-cyan dark:focus:ring-electric-cyan"
              >
                <option value="">Alle inschrijftaken</option>
                {selectedDienstType && !(calendarData?.dienst_types || []).some((type) => String(type.id) === selectedDienstType) && (
                  <option value={selectedDienstType}>Geselecteerde inschrijftaak</option>
                )}
                {(calendarData?.dienst_types || []).map((type) => (
                  <option key={type.id} value={type.id}>{type.name}</option>
                ))}
              </select>
            </div>
          )}

          {confirmOverlap && (
            <div className="card p-4 border-l-4 border-amber-400">
              <p className="text-sm text-gray-900 dark:text-gray-100">{confirmOverlap.message}</p>
              <div className="mt-3 flex gap-2">
                <button
                  className="text-xs px-3 py-1.5 rounded bg-bright-cobalt text-white dark:bg-electric-cyan dark:text-gray-900"
                  onClick={() => signupMutation.mutate({ shiftId: confirmOverlap.shiftId, forceOverlap: true })}
                >
                  Toch aanmelden
                </button>
                <button
                  className="text-xs px-3 py-1.5 rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                  onClick={() => setConfirmOverlap(null)}
                >
                  Annuleren
                </button>
              </div>
            </div>
          )}

          {actionError && (
            <div className="card p-4 border-l-4 border-red-400 text-sm text-red-700 dark:text-red-300">
              {actionError}
            </div>
          )}

          {tab === 'available' && (
            <ShiftCoverageCalendar
              data={calendarData}
              isLoading={calendarLoading}
              selectedDienstType={selectedDienstType}
              onDienstTypeChange={handleDienstTypeChange}
              title="Wanneer kun je helpen?"
              description="Rood betekent dat er nog iemand nodig is. Klik op een datum om je aan te melden."
              detailsVariant="popover"
              renderShift={(shift) => (
                <ul key={shift.id}>
                    <ShiftRow
                      shift={shift}
                      onSignup={(id) => signupMutation.mutate({ shiftId: id })}
                      onCancel={(id) => cancelMutation.mutate(id)}
                      signupMutation={signupMutation}
                      cancelMutation={cancelMutation}
                      isMine={false}
                    />
                </ul>
              )}
            />
          )}

          {tab === 'mine' && (
            <section className="space-y-4">
              <div>
                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-2">
                  Komende inschrijftaken
                </h2>
                {upcomingShifts.length === 0 ? (
                  <div className="card p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    {selectedDienstType ? 'Geen komende inschrijftaken van deze soort gepland.' : 'Geen komende inschrijftaken gepland.'}
                  </div>
                ) : (
                  <ul className="space-y-2">
                    {upcomingShifts.map(shift => (
                      <ShiftRow
                        key={shift.id}
                        shift={shift}
                        onCancel={(id) => cancelMutation.mutate(id)}
                        signupMutation={signupMutation}
                        cancelMutation={cancelMutation}
                        isMine
                      />
                    ))}
                  </ul>
                )}
              </div>
              {pastShifts.length > 0 && (
                <div>
                  <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-2">
                    Historie
                  </h2>
                  <ul className="space-y-2">
                    {pastShifts.map(shift => (
                      <ShiftRow
                        key={shift.id}
                        shift={shift}
                        signupMutation={signupMutation}
                        cancelMutation={cancelMutation}
                        isMine
                      />
                    ))}
                  </ul>
                </div>
              )}
            </section>
          )}
        </>
      )}
    </div>
  );
}
