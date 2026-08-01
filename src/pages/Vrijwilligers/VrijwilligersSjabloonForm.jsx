import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CalendarClock, RefreshCw, Save, Trash2, X } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { refreshShiftCalendars } from '@/utils/shiftQueryCache';

const DAYS = [
  { value: 1, label: 'Maandag' },
  { value: 2, label: 'Dinsdag' },
  { value: 3, label: 'Woensdag' },
  { value: 4, label: 'Donderdag' },
  { value: 5, label: 'Vrijdag' },
  { value: 6, label: 'Zaterdag' },
  { value: 7, label: 'Zondag' },
];

const EMPTY = {
  title: '',
  dienst_type_id: 0,
  day_of_week: 6,
  start_time: '09:00',
  end_time: '12:00',
  capacity: '',
  active_from: '',
  active_until: '',
  iva_waived: false,
  notes: '',
};

function fieldDateToInput(value) {
  if (!value) return '';
  if (/^\d{8}$/.test(value)) return `${value.slice(0, 4)}-${value.slice(4, 6)}-${value.slice(6, 8)}`;
  return value;
}

export default function VrijwilligersSjabloonForm() {
  const { id } = useParams();
  const isEdit = !!id;
  useDocumentTitle(isEdit ? 'Sjabloon bewerken' : 'Nieuw sjabloon');
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState(EMPTY);
  const [feedback, setFeedback] = useState(null);
  const [showRerunModal, setShowRerunModal] = useState(false);

  const { data: types = [], isLoading: typesLoading } = useQuery({
    queryKey: ['volunteer', 'dienst-types'],
    queryFn: async () => (await prmApi.getDienstTypes()).data || [],
    staleTime: 5 * 60 * 1000,
  });

  const { data: existing, isLoading: existingLoading } = useQuery({
    queryKey: ['volunteer', 'sjabloon', id],
    queryFn: async () => (await prmApi.getShiftTemplate(id)).data,
    enabled: isEdit,
  });

  useEffect(() => {
    if (!existing) return;
    const fields = existing.fields || {};
    setForm({
      title: existing.title?.rendered || existing.title || '',
      dienst_type_id: Number(fields.dienst_type_id) || 0,
      day_of_week: Number(fields.day_of_week) || 6,
      start_time: fields.start_time || '09:00',
      end_time: fields.end_time || '12:00',
      capacity: fields.capacity ? String(fields.capacity) : '',
      active_from: fieldDateToInput(fields.active_from),
      active_until: fieldDateToInput(fields.active_until),
      iva_waived: Boolean(fields.iva_waived),
      notes: fields.notes || '',
    });
  }, [existing]);

  const selectedType = useMemo(
    () => types.find((t) => t.id === Number(form.dienst_type_id)),
    [types, form.dienst_type_id]
  );
  const typeRequiresIva = Boolean(selectedType?.fields?.iva_required);

  const defaultTitle = useMemo(() => {
    if (form.title) return form.title;
    const type = types.find((t) => t.id === Number(form.dienst_type_id));
    const day  = DAYS.find((d) => d.value === Number(form.day_of_week));
    if (type && day) {
      return `${type.title?.rendered || type.title} — ${day.label} ${form.start_time}`;
    }
    return '';
  }, [form, types]);

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      isEdit ? prmApi.updateShiftTemplate(id, payload) : prmApi.createShiftTemplate(payload),
    onSuccess: async () => {
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'shift-templates'], type: 'all' });
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'sjabloon', id] });
      await refreshShiftCalendars(queryClient);
      navigate('/vrijwilligers/sjablonen');
    },
    onError: (err) => {
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Opslaan mislukt.' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => prmApi.deleteShiftTemplate(id),
    onSuccess: async () => {
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'shift-templates'], type: 'all' });
      navigate('/vrijwilligers/sjablonen');
    },
  });

  const rerunMutation = useMutation({
    mutationFn: () => prmApi.rerunShiftTemplate(id),
    onSuccess: async (response) => {
      const data = response?.data || {};
      const deleted = Number(data.deleted || 0);
      const created = Number(data.created || 0);
      const kept = Number(data.kept || 0);
      const keptSignups = Number(data.kept_signups || 0);
      const preserved = [];
      if (kept > 0) preserved.push(`${kept} handmatig aangepast of geannuleerd`);
      if (keptSignups > 0) preserved.push(`${keptSignups} met aanmeldingen`);
      const preservedText = preserved.length ? ` ${preserved.join(' en ')} bleven ongewijzigd.` : '';
      setShowRerunModal(false);
      setFeedback({
        kind: 'success',
        message: `Opnieuw uitgerold: ${deleted} verwijderd, ${created} opnieuw aangemaakt.${preservedText}`,
      });
      await refreshShiftCalendars(queryClient);
    },
    onError: (err) => {
      setShowRerunModal(false);
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Opnieuw uitrollen mislukt.' });
    },
  });

  if ((isEdit && existingLoading) || typesLoading) {
    return <ContentLoadingSpinner />;
  }

  return (
    <div className="max-w-3xl mx-auto p-4 sm:p-6 space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers/sjablonen"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar sjablonen
        </Link>
        <div className="flex items-center gap-3">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <CalendarClock className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {isEdit ? 'Sjabloon bewerken' : 'Nieuw sjabloon'}
            </h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Wekelijks terugkerende regel. De template-expander rolt deze uit naar geplande inschrijftaken.
            </p>
          </div>
        </div>
      </header>

      <form
        className="card p-5 space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          setFeedback(null);
          if (!form.dienst_type_id) {
            setFeedback({ kind: 'error', message: 'Kies een inschrijftaak.' });
            return;
          }
          saveMutation.mutate({
            title: defaultTitle,
            status: 'publish',
            fields: {
              dienst_type_id: Number(form.dienst_type_id),
              day_of_week: String(form.day_of_week),
              start_time: form.start_time,
              end_time: form.end_time,
              capacity: form.capacity === '' ? 0 : Number(form.capacity),
              active_from: form.active_from || null,
              active_until: form.active_until || null,
              iva_waived: typeRequiresIva ? Boolean(form.iva_waived) : false,
              notes: form.notes,
            },
          });
        }}
      >
        <Field label="Inschrijftaak">
          <select
            value={form.dienst_type_id}
            onChange={(e) => setForm({ ...form, dienst_type_id: e.target.value })}
            required
            className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
          >
            <option value={0}>— kies —</option>
            {types.map((t) => (
              <option key={t.id} value={t.id}>
                {t.title?.rendered || t.title}
              </option>
            ))}
          </select>
        </Field>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <Field label="Dag">
            <select
              value={form.day_of_week}
              onChange={(e) => setForm({ ...form, day_of_week: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            >
              {DAYS.map((d) => (
                <option key={d.value} value={d.value}>{d.label}</option>
              ))}
            </select>
          </Field>
          <Field label="Starttijd">
            <input
              type="time"
              required
              value={form.start_time}
              onChange={(e) => setForm({ ...form, start_time: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
          <Field label="Eindtijd">
            <input
              type="time"
              required
              value={form.end_time}
              onChange={(e) => setForm({ ...form, end_time: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <Field label="Capaciteit" hint="0 of leeg = gebruik de standaard van de inschrijftaak">
            <input
              type="number"
              min={0}
              value={form.capacity}
              onChange={(e) => setForm({ ...form, capacity: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
          <Field label="Actief vanaf">
            <input
              type="date"
              value={form.active_from}
              onChange={(e) => setForm({ ...form, active_from: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
          <Field label="Actief tot" hint="Leeg laten voor doorlopend">
            <input
              type="date"
              value={form.active_until}
              onChange={(e) => setForm({ ...form, active_until: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
        </div>

        {typeRequiresIva && (
          <label className="flex items-start gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.iva_waived}
              onChange={(e) => setForm({ ...form, iva_waived: e.target.checked })}
              className="mt-0.5 rounded border-gray-300 dark:border-gray-600"
            />
            <span>
              <span className="font-medium text-gray-700 dark:text-gray-200">IVA niet vereist voor uitgerolde inschrijftaken</span>
              <span className="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Aanvinken als bij deze sjabloon geen alcohol geschonken wordt (bv. zaterdagochtend voor 15:00). De expander zet dezelfde waarde op elke uitgerolde inschrijftaak — bestaande inschrijftaken worden niet aangepast.
              </span>
            </span>
          </label>
        )}

        <Field label="Notities">
          <textarea
            rows={2}
            value={form.notes}
            onChange={(e) => setForm({ ...form, notes: e.target.value })}
            className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            placeholder="bv. alleen bij thuiswedstrijden"
          />
        </Field>

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

        <div className="flex flex-wrap items-center gap-2 pt-2">
          <button type="submit" disabled={saveMutation.isLoading} className="btn-primary inline-flex items-center gap-2">
            <Save className="w-4 h-4" />
            {saveMutation.isLoading ? 'Opslaan…' : 'Opslaan'}
          </button>
          {isEdit && (
            <button
              type="button"
              onClick={() => {
                setFeedback(null);
                setShowRerunModal(true);
              }}
              disabled={rerunMutation.isLoading}
              className="btn-tertiary inline-flex items-center gap-2"
              title="Verwijder de nog niet aangepaste uitgerolde inschrijftaken en rol ze opnieuw uit met de huidige instellingen"
            >
              <RefreshCw className="w-4 h-4" />
              Opnieuw uitrollen
            </button>
          )}
          {isEdit && (
            <button
              type="button"
              onClick={() => {
                if (window.confirm('Sjabloon verwijderen? Reeds uitgerolde inschrijftaken blijven bestaan.')) {
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
        </div>
      </form>

      {showRerunModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="rerun-template-title">
          <div className="w-full max-w-md rounded-lg bg-white shadow-xl dark:bg-gray-800">
            <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
              <h2 id="rerun-template-title" className="text-lg font-semibold text-gray-900 dark:text-gray-50">
                Sjabloon opnieuw uitrollen
              </h2>
              <button
                type="button"
                onClick={() => setShowRerunModal(false)}
                disabled={rerunMutation.isLoading}
                className="text-gray-400 hover:text-gray-600 disabled:opacity-50 dark:hover:text-gray-300"
                aria-label="Sluiten"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="space-y-3 p-4 text-sm text-gray-600 dark:text-gray-400">
              <p>
                Alle nog niet aangepaste, toekomstige inschrijftaken van dit sjabloon worden verwijderd en opnieuw
                uitgerold met de huidige instellingen.
              </p>
              <p>
                Inschrijftaken die je handmatig hebt aangepast, waarvoor al aanmeldingen zijn, of die geannuleerd zijn,
                blijven <strong>ongewijzigd</strong> bestaan.
              </p>
            </div>
            <div className="flex justify-end gap-2 rounded-b-lg border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
              <button type="button" onClick={() => setShowRerunModal(false)} disabled={rerunMutation.isLoading} className="btn-secondary">
                Annuleren
              </button>
              <button type="button" onClick={() => rerunMutation.mutate()} disabled={rerunMutation.isLoading} className="btn-primary inline-flex items-center gap-2">
                <RefreshCw className="h-4 w-4" />
                {rerunMutation.isLoading ? 'Uitrollen…' : 'Opnieuw uitrollen'}
              </button>
            </div>
          </div>
        </div>
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
