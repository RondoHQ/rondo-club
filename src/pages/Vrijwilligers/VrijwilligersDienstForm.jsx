import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CalendarClock, Save, Trash2, ExternalLink, UserX } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { format } from '@/utils/dateFormat';

const EMPTY = {
  title: '',
  dienst_type_id: 0,
  start_datetime: '',
  end_datetime: '',
  capacity: 1,
  status: 'open',
  iva_waived: false,
  notes: '',
};

const STATUSES = [
  { value: 'open', label: 'Open' },
  { value: 'vol', label: 'Vol' },
  { value: 'voltooid', label: 'Voltooid' },
];

const INPUT_CLS = 'block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm';

function toLocalInput(value) {
  if (!value) return '';
  // accept both "Y-m-d H:i:s" and "Y-m-d H:i"
  return value.replace(' ', 'T').slice(0, 16);
}
function fromLocalInput(value) {
  if (!value) return '';
  return value.replace('T', ' ') + ':00';
}

export default function VrijwilligersDienstForm() {
  const { id } = useParams();
  const isEdit = !!id;
  useDocumentTitle(isEdit ? 'Dienst bewerken' : 'Nieuwe dienst');
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState(EMPTY);
  const [feedback, setFeedback] = useState(null);
  const [cancellationOpen, setCancellationOpen] = useState(false);
  const [cancellationReason, setCancellationReason] = useState('');
  const [canRetryNotifications, setCanRetryNotifications] = useState(false);

  const { data: types = [], isLoading: typesLoading } = useQuery({
    queryKey: ['volunteer', 'dienst-types'],
    queryFn: async () => (await prmApi.getDienstTypes()).data || [],
    staleTime: 5 * 60 * 1000,
  });

  const { data: existing, isLoading: existingLoading } = useQuery({
    queryKey: ['volunteer', 'dienst-shift', id],
    queryFn: async () => (await prmApi.getDienstShift(id)).data,
    enabled: isEdit,
  });

  useEffect(() => {
    if (!existing) return;
    const acf = existing.acf || {};
    setForm({
      title: existing.title?.rendered || existing.title || '',
      dienst_type_id: Number(acf.dienst_type_id) || 0,
      start_datetime: toLocalInput(acf.start_datetime || ''),
      end_datetime: toLocalInput(acf.end_datetime || ''),
      capacity: acf.capacity ? Number(acf.capacity) : 1,
      status: acf.status || 'open',
      iva_waived: Boolean(acf.iva_waived),
      notes: acf.notes || '',
    });
  }, [existing]);

  const selectedType = useMemo(
    () => types.find((t) => t.id === Number(form.dienst_type_id)),
    [types, form.dienst_type_id]
  );
  const typeRequiresIva = Boolean(selectedType?.acf?.iva_required);

  const defaultTitle = useMemo(() => {
    if (form.title) return form.title;
    const type = types.find((t) => t.id === Number(form.dienst_type_id));
    if (type && form.start_datetime) {
      const typeName = type.title?.rendered || type.title;
      try {
        return `${typeName} — ${format(form.start_datetime, 'dd-MM-yyyy HH:mm')}`;
      } catch {
        return `${typeName} — ${form.start_datetime}`;
      }
    }
    return '';
  }, [form, types]);

  const assignedIds = useMemo(() => {
    const acf = existing?.acf || {};
    const raw = acf.assigned_persons;
    if (Array.isArray(raw)) return raw.map(Number).filter(Boolean);
    return [];
  }, [existing]);

  const isCancelled = form.status === 'geannuleerd';
  const cancellation = existing?.cancellation || null;
  const cancellationIsLastMinute = useMemo(() => {
    if (!form.start_datetime) return false;
    const millisecondsUntilStart = new Date(form.start_datetime).getTime() - Date.now();
    return millisecondsUntilStart > 0 && millisecondsUntilStart < 48 * 60 * 60 * 1000;
  }, [form.start_datetime]);

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      isEdit ? prmApi.updateDienstShift(id, payload) : prmApi.createDienstShift(payload),
    onSuccess: async () => {
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'dienst-shifts'], type: 'all' });
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'dienst-shift', id] });
      queryClient.invalidateQueries({ queryKey: ['shift-calendar'] });
      navigate('/vrijwilligers/diensten');
    },
    onError: (err) => {
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Opslaan mislukt.' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => prmApi.deleteDienstShift(id),
    onSuccess: async () => {
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'dienst-shifts'], type: 'all' });
      queryClient.invalidateQueries({ queryKey: ['shift-calendar'] });
      navigate('/vrijwilligers/diensten');
    },
    onError: (err) => {
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Verwijderen mislukt.' });
    },
  });

  const cancellationMutation = useMutation({
    mutationFn: () => prmApi.cancelDienstShift(id, { reason: cancellationReason.trim() }),
    onSuccess: async (response) => {
      const data = response?.data || {};
      const notifications = data.notifications || {};
      const issues = Number(notifications.failed || 0) + Number(notifications.no_email || 0);
      const delivered = Number(notifications.sent || 0) + Number(notifications.already_sent || 0);
      const creditText = data.credit_awarded
        ? 'De dienst telt mee voor de vrijwilligersplicht.'
        : 'De dienst telt niet mee; vrijwilligers moeten een nieuwe dienst kiezen.';
      const mailText = issues > 0
        ? ` ${delivered} bericht(en) zijn verzonden; ${notifications.failed || 0} verzending(en) mislukten en ${notifications.no_email || 0} vrijwilliger(s) hebben geen geldig e-mailadres.`
        : ` ${delivered} bericht(en) zijn verzonden.`;

      setFeedback({ kind: issues > 0 ? 'warning' : 'success', message: `Dienst geannuleerd. ${creditText}${mailText}` });
      setCanRetryNotifications(Number(notifications.failed || 0) > 0);
      setCancellationOpen(false);
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'dienst-shift', id], type: 'active' });
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'dienst-shifts'], type: 'all' });
      queryClient.invalidateQueries({ queryKey: ['shift-calendar'] });
      queryClient.invalidateQueries({ queryKey: ['my-shifts'] });
    },
    onError: (err) => {
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Annuleren mislukt.' });
    },
  });

  const removeAssigneeMutation = useMutation({
    mutationFn: (personId) => prmApi.removeShiftAssignee(id, personId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'dienst-shift', id] });
      queryClient.invalidateQueries({ queryKey: ['shift-calendar'] });
    },
  });

  if ((isEdit && existingLoading) || typesLoading) {
    return <ContentLoadingSpinner />;
  }

  return (
    <div className="max-w-3xl mx-auto p-4 sm:p-6 space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers/diensten"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar diensten
        </Link>
        <div className="flex items-center gap-3">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <CalendarClock className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {isEdit ? 'Dienst bewerken' : 'Nieuwe dienst'}
            </h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Ad-hoc dienst (bv. avondwedstrijd). Wekelijks terugkerend? Gebruik een{' '}
              <Link to="/vrijwilligers/sjablonen" className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                sjabloon
              </Link>{' '}
              in plaats hiervan.
            </p>
          </div>
        </div>
      </header>

      <form
        className="card p-5 space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          setFeedback(null);
          if (isCancelled) {
            setFeedback({ kind: 'error', message: 'Een geannuleerde dienst kan niet meer worden bewerkt.' });
            return;
          }
          if (!form.dienst_type_id) {
            setFeedback({ kind: 'error', message: 'Kies een dienst type.' });
            return;
          }
          if (!form.start_datetime || !form.end_datetime) {
            setFeedback({ kind: 'error', message: 'Vul start- en eindtijd in.' });
            return;
          }
          saveMutation.mutate({
            title: defaultTitle,
            status: 'publish',
            acf: {
              dienst_type_id: Number(form.dienst_type_id),
              start_datetime: fromLocalInput(form.start_datetime),
              end_datetime: fromLocalInput(form.end_datetime),
              capacity: Number(form.capacity) || 1,
              status: form.status,
              iva_waived: typeRequiresIva ? Boolean(form.iva_waived) : false,
              notes: form.notes,
            },
          });
        }}
      >
        <Field label="Dienst type">
          <select
            value={form.dienst_type_id}
            onChange={(e) => setForm({ ...form, dienst_type_id: e.target.value })}
            required
            className={INPUT_CLS}
          >
            <option value={0}>— kies —</option>
            {types.map((t) => (
              <option key={t.id} value={t.id}>
                {t.title?.rendered || t.title}
              </option>
            ))}
          </select>
        </Field>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field label="Start">
            <input
              type="datetime-local"
              required
              value={form.start_datetime}
              onChange={(e) => setForm({ ...form, start_datetime: e.target.value })}
              className={INPUT_CLS}
            />
          </Field>
          <Field label="Eind">
            <input
              type="datetime-local"
              required
              value={form.end_datetime}
              onChange={(e) => setForm({ ...form, end_datetime: e.target.value })}
              className={INPUT_CLS}
            />
          </Field>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field label="Capaciteit">
            <input
              type="number"
              min={1}
              required
              value={form.capacity}
              onChange={(e) => setForm({ ...form, capacity: e.target.value })}
              className={INPUT_CLS}
            />
          </Field>
          <Field label="Status">
            {isCancelled ? (
              <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                Geannuleerd
              </div>
            ) : (
              <select
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
                className={INPUT_CLS}
              >
                {STATUSES.map((s) => (
                  <option key={s.value} value={s.value}>{s.label}</option>
                ))}
              </select>
            )}
          </Field>
        </div>

        {isCancelled && cancellation && (
          <div className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
            <div className="font-semibold">
              {cancellation.credit_awarded ? 'Deze dienst telt mee' : 'Deze dienst telt niet mee'} voor de vrijwilligersplicht.
            </div>
            <p className="mt-1">
              {cancellation.credit_awarded
                ? 'De annulering was minder dan 48 uur voor aanvang. Vrijwilligers hoeven geen vervangende dienst te kiezen.'
                : 'De annulering was minimaal 48 uur voor aanvang. Vrijwilligers moeten een nieuwe dienst kiezen.'}
            </p>
            {cancellation.reason && <p className="mt-2"><strong>Reden:</strong> {cancellation.reason}</p>}
          </div>
        )}

        {typeRequiresIva && (
          <label className="flex items-start gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.iva_waived}
              onChange={(e) => setForm({ ...form, iva_waived: e.target.checked })}
              className="mt-0.5 rounded border-gray-300 dark:border-gray-600"
            />
            <span>
              <span className="font-medium text-gray-700 dark:text-gray-200">IVA niet vereist voor deze dienst</span>
              <span className="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Aanvinken als er bij deze dienst geen alcohol geschonken wordt (bv. zaterdag voor 15:00). Geldt alleen voor deze specifieke dienst — het diensttype blijft IVA-vereist.
              </span>
            </span>
          </label>
        )}

        <Field label="Notities">
          <textarea
            rows={2}
            value={form.notes}
            onChange={(e) => setForm({ ...form, notes: e.target.value })}
            className={INPUT_CLS}
            placeholder="bv. avondwedstrijd JO15-1 vs SVA"
          />
        </Field>

        {feedback && (
          <div
            className={`text-sm ${
              feedback.kind === 'success'
                ? 'text-emerald-700 dark:text-emerald-300'
                : feedback.kind === 'warning'
                  ? 'text-amber-700 dark:text-amber-300'
                  : 'text-red-700 dark:text-red-300'
            }`}
          >
            {feedback.message}
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2 pt-2">
          {!isCancelled && (
            <button type="submit" disabled={saveMutation.isLoading} className="btn-primary inline-flex items-center gap-2">
              <Save className="w-4 h-4" />
              {saveMutation.isLoading ? 'Opslaan…' : 'Opslaan'}
            </button>
          )}
          {isEdit && !isCancelled && assignedIds.length === 0 && (
            <button
              type="button"
              onClick={() => {
                if (window.confirm('Dienst definitief verwijderen? Dit kan niet ongedaan worden gemaakt.')) {
                  deleteMutation.mutate();
                }
              }}
              disabled={deleteMutation.isLoading}
              className="inline-flex items-center gap-2 px-3 py-2 rounded text-sm bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300"
            >
              <Trash2 className="w-4 h-4" />
              Verwijderen
            </button>
          )}
          {isEdit && !isCancelled && assignedIds.length > 0 && (
            <button
              type="button"
              onClick={() => {
                setFeedback(null);
                setCancellationOpen(true);
              }}
              disabled={cancellationMutation.isLoading}
              className="inline-flex items-center gap-2 px-3 py-2 rounded text-sm bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300"
            >
              <Trash2 className="w-4 h-4" />
              Dienst annuleren
            </button>
          )}
          {isCancelled && canRetryNotifications && (
            <button
              type="button"
              onClick={() => cancellationMutation.mutate()}
              disabled={cancellationMutation.isLoading}
              className="btn-tertiary inline-flex items-center gap-2"
            >
              Niet-verzonden mails opnieuw proberen
            </button>
          )}
        </div>
      </form>

      {cancellationOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="cancel-shift-title">
          <div className="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl dark:bg-gray-800">
            <h2 id="cancel-shift-title" className="text-lg font-semibold text-gray-900 dark:text-gray-100">Dienst annuleren</h2>
            <div className={`mt-3 rounded-md border p-4 text-sm ${cancellationIsLastMinute ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200'}`}>
              <div className="font-semibold">
                {cancellationIsLastMinute ? 'Minder dan 48 uur: dienst telt mee' : 'Minimaal 48 uur: dienst telt niet mee'}
              </div>
              <p className="mt-1">
                {cancellationIsLastMinute
                  ? 'Alle aangemelde vrijwilligers krijgen bericht en hoeven geen nieuwe dienst te kiezen.'
                  : 'Alle aangemelde vrijwilligers krijgen bericht en moeten een nieuwe dienst kiezen.'}
              </p>
            </div>
            <label className="mt-4 block">
              <span className="block text-sm font-medium text-gray-700 dark:text-gray-200">Reden of toelichting (optioneel)</span>
              <textarea
                rows={3}
                value={cancellationReason}
                onChange={(event) => setCancellationReason(event.target.value)}
                className={`${INPUT_CLS} mt-1`}
                placeholder="Bijvoorbeeld: de velden zijn afgekeurd."
              />
            </label>
            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
              De 48-uursregel wordt bij bevestigen opnieuw door de server berekend. De dienst en aanmeldingen blijven bewaard voor de historie.
            </p>
            <div className="mt-5 flex justify-end gap-2">
              <button type="button" className="btn-tertiary" onClick={() => setCancellationOpen(false)} disabled={cancellationMutation.isLoading}>
                Terug
              </button>
              <button type="button" className="inline-flex items-center gap-2 rounded bg-red-700 px-3 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-50" onClick={() => cancellationMutation.mutate()} disabled={cancellationMutation.isLoading}>
                {cancellationMutation.isLoading ? 'Annuleren…' : 'Bevestig annulering'}
              </button>
            </div>
          </div>
        </div>
      )}

      {isEdit && assignedIds.length > 0 && (
        <section className="card p-5">
          <h2 className="font-semibold text-gray-900 dark:text-gray-100 mb-2">Aanmeldingen ({assignedIds.length})</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
            {isCancelled
              ? 'Deze aanmeldingen blijven bewaard voor de historie en de puntentoekenning.'
              : <>Leden melden zich aan via <code>/vrijwillig</code>. Hier kun je iemand handmatig verwijderen — bv. bij vergissingen.</>}
          </p>
          <ul className="divide-y divide-gray-100 dark:divide-gray-700">
            {assignedIds.map((pid) => (
              <li key={pid} className="py-2 flex items-center justify-between gap-3 text-sm">
                <Link to={`/people/${pid}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline inline-flex items-center gap-1">
                  Persoon {pid} <ExternalLink className="w-3 h-3" />
                </Link>
                {!isCancelled && <button
                  type="button"
                  onClick={() => {
                    if (window.confirm('Aanmelding verwijderen?')) {
                      removeAssigneeMutation.mutate(pid);
                    }
                  }}
                  disabled={removeAssigneeMutation.isLoading}
                  className="text-xs text-red-700 dark:text-red-300 inline-flex items-center gap-1 hover:underline"
                >
                  <UserX className="w-3 h-3" /> Verwijder
                </button>}
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

function Field({ label, hint, children }) {
  return (
    <label className="block">
      <span className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{label}</span>
      {children}
      {hint && <span className="block text-xs text-gray-500 dark:text-gray-400 mt-1">{hint}</span>}
    </label>
  );
}
