import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CalendarClock, Save, Trash2 } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

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
  notes: '',
};

export default function VrijwilligersSjabloonForm() {
  const { id } = useParams();
  const isEdit = !!id;
  useDocumentTitle(isEdit ? 'Sjabloon bewerken' : 'Nieuw sjabloon');
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState(EMPTY);
  const [feedback, setFeedback] = useState(null);

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
    const meta = existing.meta || {};
    setForm({
      title: existing.title?.rendered || existing.title || '',
      dienst_type_id: Number(meta.dienst_type_id) || 0,
      day_of_week: Number(meta.day_of_week) || 6,
      start_time: meta.start_time || '09:00',
      end_time: meta.end_time || '12:00',
      capacity: meta.capacity ? String(meta.capacity) : '',
      active_from: meta.active_from || '',
      active_until: meta.active_until || '',
      notes: meta.notes || '',
    });
  }, [existing]);

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
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'shift-templates'] });
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'sjabloon', id] });
      navigate('/vrijwilligers/sjablonen');
    },
    onError: (err) => {
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Opslaan mislukt.' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => prmApi.deleteShiftTemplate(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'shift-templates'] });
      navigate('/vrijwilligers/sjablonen');
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
              Wekelijks terugkerende shift-regel. De template-expander rolt deze uit naar concrete diensten voor de komende 12 weken.
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
            setFeedback({ kind: 'error', message: 'Kies een dienst type.' });
            return;
          }
          saveMutation.mutate({
            title: defaultTitle,
            status: 'publish',
            meta: {
              dienst_type_id: Number(form.dienst_type_id),
              day_of_week: Number(form.day_of_week),
              start_time: form.start_time,
              end_time: form.end_time,
              capacity: form.capacity === '' ? 0 : Number(form.capacity),
              active_from: form.active_from,
              active_until: form.active_until,
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
          <Field label="Capaciteit" hint="0 of leeg = gebruik default van dienst type">
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
                if (window.confirm('Sjabloon verwijderen? Reeds uitgerolde shifts blijven bestaan.')) {
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
