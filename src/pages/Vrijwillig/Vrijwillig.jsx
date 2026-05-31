import { useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { HeartHandshake, AlertTriangle, CheckCircle2, XCircle, Clock, Calendar, ShieldAlert } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

function StatusBadge({ kind, children }) {
  const styles = {
    voldaan: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    'op-weg': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
    risico: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    'geen-actie': 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    exempt: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  };
  return (
    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${styles[kind] || styles['geen-actie']}`}>
      {children}
    </span>
  );
}

function ObligationCard({ obligation, exemption }) {
  if (exemption) {
    return (
      <div className="card p-5 border-l-4 border-purple-400">
        <div className="flex items-start gap-3">
          <CheckCircle2 className="w-5 h-5 text-purple-500 mt-0.5 shrink-0" />
          <div>
            <h2 className="font-semibold text-gray-900 dark:text-gray-100">Je bent vrijgesteld</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              {exemption.reason_label}. Je hoeft dit seizoen geen diensten te plannen, maar je mag natuurlijk wel meedoen.
            </p>
          </div>
        </div>
      </div>
    );
  }

  if (!obligation) {
    return (
      <div className="card p-5">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          Je valt op dit moment niet onder de 2-diensten-plicht.
        </p>
      </div>
    );
  }

  const required = obligation.required_count || 0;
  const completed = obligation.completed_count || 0;
  const pending = obligation.pending_count || 0;
  const remaining = Math.max(0, required - completed);

  return (
    <div className="card p-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">Jouw vrijwilligersplicht</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Je hebt <strong>{completed}</strong> van <strong>{required}</strong> diensten gedaan dit seizoen.
            {pending > 0 && <> Je staat al ingepland voor {pending} {pending === 1 ? 'dienst' : 'diensten'}.</>}
            {remaining > 0 && <> Plan nog <strong>{remaining}</strong> {remaining === 1 ? 'dienst' : 'diensten'} in.</>}
          </p>
        </div>
        <StatusBadge kind={obligation.status}>
          {obligation.status === 'voldaan' && 'Voldaan'}
          {obligation.status === 'op-weg' && 'Op weg'}
          {obligation.status === 'risico' && 'Risico'}
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
              Diensten waarvoor een geldige VOG nodig is, zijn nog niet zichtbaar. De VOG-coördinator kan een aanvraag voor je starten.
            </p>
          </div>
        </div>
      )}
      {blockReasons.includes('iva') && (
        <div className="card p-4 border-l-4 border-amber-400 flex items-start gap-3">
          <ShieldAlert className="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
          <div className="text-sm">
            <strong className="text-gray-900 dark:text-gray-100">Je kunt nog geen bardiensten draaien</strong>
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
              <Link to="/vrijwillig/profiel" className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                hier uploaden
              </Link>
              .
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
  const color = shift.dienst_type_color || '#6b7280';

  return (
    <li className="card p-4 flex items-start gap-4">
      <span className="w-2 h-12 rounded-full mt-1 shrink-0" style={{ background: color }} />
      <div className="flex-1 min-w-0">
        <div className="flex items-baseline justify-between gap-2 flex-wrap">
          <h3 className="font-semibold text-gray-900 dark:text-gray-100">{shift.dienst_type_name || shift.title}</h3>
          {shift.no_show && (
            <span className="text-xs font-medium text-red-700 dark:text-red-300 inline-flex items-center gap-1">
              <XCircle className="w-3.5 h-3.5" /> No-show
            </span>
          )}
        </div>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-0.5 inline-flex items-center gap-1">
          <Calendar className="w-3.5 h-3.5" />
          {start ? format(start, 'EEEE d MMMM, HH:mm') : '—'} – {end ? format(end, 'HH:mm') : ''}
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
      </div>
      <div className="shrink-0">
        {isMine ? (
          shift.status === 'voltooid' || shift.no_show ? null : (
            <button
              onClick={() => onCancel(shift.id)}
              disabled={cancelMutation.isLoading}
              className="text-xs px-3 py-1.5 rounded bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300"
            >
              Afmelden
            </button>
          )
        ) : shift.is_signed_up ? (
          <button
            onClick={() => onCancel(shift.id)}
            disabled={cancelMutation.isLoading}
            className="text-xs px-3 py-1.5 rounded bg-emerald-100 text-emerald-800 hover:bg-red-100 hover:text-red-800 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-red-900/30 dark:hover:text-red-300 inline-flex items-center gap-1"
            title="Klik om af te melden"
          >
            <CheckCircle2 className="w-3.5 h-3.5" /> Reeds aangemeld
          </button>
        ) : (
          <button
            onClick={() => onSignup(shift.id)}
            disabled={signupMutation.isLoading || !shift.can_signup}
            className="text-xs px-3 py-1.5 rounded bg-bright-cobalt text-white hover:bg-bright-cobalt/90 disabled:opacity-50 dark:bg-electric-cyan dark:text-gray-900"
          >
            {shift.can_signup ? 'Aanmelden' : 'Niet beschikbaar'}
          </button>
        )}
      </div>
    </li>
  );
}

export default function Vrijwillig() {
  useDocumentTitle('Vrijwilligers — Aanmelden');
  const queryClient = useQueryClient();
  const [tab, setTab] = useState('available');
  const [confirmOverlap, setConfirmOverlap] = useState(null); // { shiftId, message }

  const { data: mine, isLoading: mineLoading, error: mineError } = useQuery({
    queryKey: ['my-shifts'],
    queryFn: async () => (await prmApi.getMyShifts()).data,
    staleTime: 60 * 1000,
  });

  const { data: available, isLoading: availLoading } = useQuery({
    queryKey: ['available-shifts'],
    queryFn: async () => (await prmApi.getAvailableShifts()).data,
    staleTime: 60 * 1000,
  });

  const signupMutation = useMutation({
    mutationFn: ({ shiftId, forceOverlap = false }) => prmApi.signupForShift(shiftId, { force_overlap: forceOverlap }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['my-shifts'] });
      queryClient.invalidateQueries({ queryKey: ['available-shifts'] });
      setConfirmOverlap(null);
    },
    onError: (error) => {
      const code = error?.response?.data?.code;
      const data = error?.response?.data?.data || {};
      if (code === 'overlap_warning' && data.can_force) {
        setConfirmOverlap({
          shiftId: data.shift_id || null,
          message: error?.response?.data?.message,
          overlap: data.overlap_shift || null,
        });
      }
    },
  });

  const cancelMutation = useMutation({
    mutationFn: (shiftId) => prmApi.cancelShift(shiftId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['my-shifts'] });
      queryClient.invalidateQueries({ queryKey: ['available-shifts'] });
    },
  });

  const upcomingShifts = useMemo(() => (mine?.shifts || []).filter(s => s.status !== 'voltooid'), [mine]);
  const pastShifts     = useMemo(() => (mine?.shifts || []).filter(s => s.status === 'voltooid'), [mine]);
  const availableShifts = available?.shifts || [];

  if (mineError && mineError?.response?.status === 404) {
    return (
      <div className="max-w-2xl mx-auto p-6">
        <div className="card p-6 text-center">
          <AlertTriangle className="w-8 h-8 text-amber-500 mx-auto mb-2" />
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">Geen gekoppeld lid-profiel</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
            Je account is nog niet gekoppeld aan een lid-record. Neem contact op met de ledenadministratie zodat je je voor diensten kunt aanmelden.
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
        <div className="flex-1 min-w-0">
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijwilligers</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Plan je diensten in en houd je voortgang bij voor het seizoen {mine?.season}.
          </p>
        </div>
        <Link
          to="/vrijwillig/profiel"
          className="text-sm text-bright-cobalt dark:text-electric-cyan hover:underline shrink-0"
        >
          Mijn certificaten
        </Link>
      </header>

      {mineLoading ? (
        <ContentLoadingSpinner />
      ) : (
        <>
          <ObligationCard obligation={mine?.obligation} exemption={mine?.exemption} />
          <BlockBanners blockReasons={available?.block_reasons} />

          <nav className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
            <button
              onClick={() => setTab('available')}
              className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
                tab === 'available'
                  ? 'border-bright-cobalt text-bright-cobalt dark:border-electric-cyan dark:text-electric-cyan'
                  : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
              }`}
            >
              Beschikbaar ({availableShifts.length})
            </button>
            <button
              onClick={() => setTab('mine')}
              className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
                tab === 'mine'
                  ? 'border-bright-cobalt text-bright-cobalt dark:border-electric-cyan dark:text-electric-cyan'
                  : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
              }`}
            >
              Mijn diensten ({(mine?.shifts || []).length})
            </button>
          </nav>

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

          {tab === 'available' && (
            <section>
              {availLoading ? (
                <ContentLoadingSpinner />
              ) : availableShifts.length === 0 ? (
                <div className="card p-8 text-center text-sm text-gray-600 dark:text-gray-400">
                  <Clock className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                  Geen open diensten beschikbaar in de komende periode.
                </div>
              ) : (
                <ul className="space-y-2">
                  {availableShifts.map(shift => (
                    <ShiftRow
                      key={shift.id}
                      shift={shift}
                      onSignup={(id) => signupMutation.mutate({ shiftId: id })}
                      onCancel={(id) => cancelMutation.mutate(id)}
                      signupMutation={signupMutation}
                      cancelMutation={cancelMutation}
                      isMine={false}
                    />
                  ))}
                </ul>
              )}
            </section>
          )}

          {tab === 'mine' && (
            <section className="space-y-4">
              <div>
                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-2">
                  Komende diensten
                </h2>
                {upcomingShifts.length === 0 ? (
                  <div className="card p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Geen komende diensten gepland.
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
                    Voltooid
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
