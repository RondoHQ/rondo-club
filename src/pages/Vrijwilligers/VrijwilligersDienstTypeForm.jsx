import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, ClipboardList, Save, Trash2 } from 'lucide-react';
import { prmApi, wpApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

const EMPTY = {
  title: '',
  description: '',
  vog_required: false,
  iva_required: false,
  sleutel_involved: false,
  default_capacity: '1',
  color: '#6b7280',
  required_pool: 0,
  reminder_email_subject: 'Herinnering: {dienst} op {datum}',
  reminder_email_body: 'Hoi {naam},\n\nDit is een herinnering voor je inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd}.\n\nJe voert deze inschrijftaak uit samen met {medevrijwilligers}.',
  cancellation_early_email_subject: 'Je inschrijftaak {dienst} op {datum} gaat niet door',
  cancellation_early_email_body: 'Hoi {naam},\n\nJe inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.\n\nDeze annulering is minimaal 48 uur voor aanvang doorgegeven. De inschrijftaak telt daarom niet mee voor je vrijwilligersplicht. Kies een nieuwe inschrijftaak via Rondo.',
  cancellation_last_minute_email_subject: 'Je inschrijftaak {dienst} op {datum} gaat niet door',
  cancellation_last_minute_email_body: 'Hoi {naam},\n\nJe inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.\n\nDeze annulering is minder dan 48 uur voor aanvang doorgegeven. De inschrijftaak telt daarom wel mee voor je vrijwilligersplicht. Je hoeft hiervoor geen nieuwe inschrijftaak te kiezen.',
  survey_email_subject: 'Hoe ging je inschrijftaak {dienst}?',
  survey_email_body: 'Hoi {naam},\n\nBedankt voor je inzet bij {dienst}. We horen graag hoe de inschrijftaak is verlopen. Wil je onze korte enquête invullen?',
  survey_url: '',
};

export default function VrijwilligersDienstTypeForm() {
  const { id } = useParams();
  const isEdit = !!id;
  useDocumentTitle(isEdit ? 'Inschrijftaak bewerken' : 'Nieuwe inschrijftaak');
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState(EMPTY);
  const [feedback, setFeedback] = useState(null);

  const {
    data: commissies = [],
    isLoading: commissiesLoading,
    isError: commissiesError,
  } = useQuery({
    queryKey: ['commissies', 'list'],
    queryFn: async () => (await wpApi.getCommissies({
      per_page: 100,
      orderby: 'title',
      order: 'asc',
      _fields: 'id,title',
    })).data || [],
    staleTime: 5 * 60 * 1000,
  });

  const { data: existing, isLoading: existingLoading } = useQuery({
    queryKey: ['volunteer', 'dienst-type', id],
    queryFn: async () => (await prmApi.getDienstType(id)).data,
    enabled: isEdit,
  });

  useEffect(() => {
    if (!existing) return;
    const fields = existing.fields || {};
    setForm({
      title: existing.title?.raw ?? existing.title?.rendered ?? '',
      description: fields.description || '',
      vog_required: Boolean(fields.vog_required),
      iva_required: Boolean(fields.iva_required),
      sleutel_involved: Boolean(fields.sleutel_involved),
      default_capacity: fields.default_capacity != null ? String(fields.default_capacity) : '1',
      color: fields.color || '#6b7280',
      required_pool: Number(fields.required_pool) || 0,
      reminder_email_subject: fields.reminder_email_subject || EMPTY.reminder_email_subject,
      reminder_email_body: fields.reminder_email_body || EMPTY.reminder_email_body,
      cancellation_early_email_subject: fields.cancellation_early_email_subject || EMPTY.cancellation_early_email_subject,
      cancellation_early_email_body: fields.cancellation_early_email_body || EMPTY.cancellation_early_email_body,
      cancellation_last_minute_email_subject: fields.cancellation_last_minute_email_subject || EMPTY.cancellation_last_minute_email_subject,
      cancellation_last_minute_email_body: fields.cancellation_last_minute_email_body || EMPTY.cancellation_last_minute_email_body,
      survey_email_subject: fields.survey_email_subject || EMPTY.survey_email_subject,
      survey_email_body: fields.survey_email_body || EMPTY.survey_email_body,
      survey_url: fields.survey_url || '',
    });
  }, [existing]);

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      isEdit ? prmApi.updateDienstType(id, payload) : prmApi.createDienstType(payload),
    onSuccess: async () => {
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'dienst-types'], type: 'all' });
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'dienst-type', id] });
      navigate('/vrijwilligers/diensten');
    },
    onError: (err) => {
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Opslaan mislukt.' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => prmApi.deleteDienstType(id),
    onSuccess: async () => {
      await queryClient.refetchQueries({ queryKey: ['volunteer', 'dienst-types'], type: 'all' });
      navigate('/vrijwilligers/diensten');
    },
    onError: (err) => {
      setFeedback({ kind: 'error', message: err?.response?.data?.message || err?.message || 'Verwijderen mislukt.' });
    },
  });

  if (isEdit && existingLoading) {
    return <ContentLoadingSpinner />;
  }

  return (
    <div className="max-w-3xl mx-auto p-4 sm:p-6 space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers/diensten"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar inschrijftaken
        </Link>
        <div className="flex items-center gap-3">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <ClipboardList className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {isEdit ? 'Inschrijftaak bewerken' : 'Nieuwe inschrijftaak'}
            </h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Soort inschrijftaak (bv. kantinebar of terreinonderhoud). Sjablonen en geplande inschrijftaken verwijzen hiernaar.
            </p>
          </div>
        </div>
      </header>

      <form
        className="card p-5 space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          setFeedback(null);
          if (!form.title.trim()) {
            setFeedback({ kind: 'error', message: 'Geef een naam op.' });
            return;
          }
          saveMutation.mutate({
            title: form.title.trim(),
            status: 'publish',
            fields: {
              description: form.description,
              vog_required: Boolean(form.vog_required),
              iva_required: Boolean(form.iva_required),
              sleutel_involved: Boolean(form.sleutel_involved),
              default_capacity: form.default_capacity === '' ? 0 : Number(form.default_capacity),
              color: form.color,
              required_pool: form.required_pool ? Number(form.required_pool) : null,
              reminder_email_subject: form.reminder_email_subject,
              reminder_email_body: form.reminder_email_body,
              cancellation_early_email_subject: form.cancellation_early_email_subject,
              cancellation_early_email_body: form.cancellation_early_email_body,
              cancellation_last_minute_email_subject: form.cancellation_last_minute_email_subject,
              cancellation_last_minute_email_body: form.cancellation_last_minute_email_body,
              survey_email_subject: form.survey_email_subject,
              survey_email_body: form.survey_email_body,
              survey_url: form.survey_url,
            },
          });
        }}
      >
        <Field label="Naam">
          <input
            type="text"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            required
            placeholder="bv. Kantine bar"
            className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
          />
        </Field>

        <Field label="Omschrijving" hint="Zichtbaar voor vrijwilligers bij het aanmelden.">
          <textarea
            rows={3}
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            placeholder="Korte uitleg van wat deze inschrijftaak inhoudt."
          />
        </Field>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Toggle
            label="VOG vereist"
            checked={form.vog_required}
            onChange={(v) => setForm({ ...form, vog_required: v })}
          />
          <Toggle
            label="IVA vereist"
            checked={form.iva_required}
            onChange={(v) => setForm({ ...form, iva_required: v })}
          />
          <Toggle
            label="Sleutel betrokken"
            checked={form.sleutel_involved}
            onChange={(v) => setForm({ ...form, sleutel_involved: v })}
          />
        </div>

        <section className="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-4">
          <div>
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Herinneringsmail</h2>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Deze tekst wordt 2 weken, 1 week en 2 dagen voor iedere inschrijftaak verstuurd.
            </p>
          </div>
          <Field label="Onderwerp">
            <input
              type="text"
              value={form.reminder_email_subject}
              onChange={(e) => setForm({ ...form, reminder_email_subject: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
          <Field
            label="Tekst"
            hint="Variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}"
          >
            <textarea
              rows={8}
              value={form.reminder_email_body}
              onChange={(e) => setForm({ ...form, reminder_email_body: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
        </section>

        <section className="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-5">
          <div>
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Annuleringsmails</h2>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Deze mails worden direct verstuurd wanneer een bezette inschrijftaak wordt geannuleerd. Een ingevulde annuleringsreden wordt automatisch onder de tekst toegevoegd.
            </p>
          </div>

          <div className="space-y-4 rounded-md border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/60 dark:bg-amber-950/20">
            <div>
              <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Minimaal 48 uur vooraf — telt niet mee</h3>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">De vrijwilliger moet een nieuwe inschrijftaak kiezen.</p>
            </div>
            <Field label="Onderwerp">
              <input
                type="text"
                value={form.cancellation_early_email_subject}
                onChange={(e) => setForm({ ...form, cancellation_early_email_subject: e.target.value })}
                className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
              />
            </Field>
            <Field label="Tekst" hint="Variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}">
              <textarea
                rows={8}
                value={form.cancellation_early_email_body}
                onChange={(e) => setForm({ ...form, cancellation_early_email_body: e.target.value })}
                className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
              />
            </Field>
          </div>

          <div className="space-y-4 rounded-md border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20">
            <div>
              <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Binnen 48 uur — telt wel mee</h3>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">De vrijwilliger hoeft geen nieuwe inschrijftaak te kiezen.</p>
            </div>
            <Field label="Onderwerp">
              <input
                type="text"
                value={form.cancellation_last_minute_email_subject}
                onChange={(e) => setForm({ ...form, cancellation_last_minute_email_subject: e.target.value })}
                className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
              />
            </Field>
            <Field label="Tekst" hint="Variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}">
              <textarea
                rows={8}
                value={form.cancellation_last_minute_email_body}
                onChange={(e) => setForm({ ...form, cancellation_last_minute_email_body: e.target.value })}
                className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
              />
            </Field>
          </div>
        </section>

        <section className="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-4">
          <div>
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Enquête na de inschrijftaak</h2>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Eén dag na de inschrijftaak ontvangt iedere deelnemer deze mail. Zonder Google Forms-link wordt niets verstuurd.
            </p>
          </div>
          <Field label="Onderwerp">
            <input
              type="text"
              value={form.survey_email_subject}
              onChange={(e) => setForm({ ...form, survey_email_subject: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
          <Field
            label="Tekst"
            hint="Variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}"
          >
            <textarea
              rows={8}
              value={form.survey_email_body}
              onChange={(e) => setForm({ ...form, survey_email_body: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
          <Field label="Google Forms-link" hint="Laat leeg om de enquêtemail voor deze inschrijftaak uit te schakelen.">
            <input
              type="url"
              value={form.survey_url}
              onChange={(e) => setForm({ ...form, survey_url: e.target.value })}
              placeholder="https://docs.google.com/forms/..."
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
        </section>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <Field label="Standaard capaciteit" hint="0 = onbeperkt">
            <input
              type="number"
              min={0}
              step={1}
              value={form.default_capacity}
              onChange={(e) => setForm({ ...form, default_capacity: e.target.value })}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            />
          </Field>
          <Field label="Kleur" hint="Voor de kalender-UI">
            <input
              type="color"
              value={form.color}
              onChange={(e) => setForm({ ...form, color: e.target.value })}
              className="block h-9 w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-1 py-1"
            />
          </Field>
          <Field label="Vereiste commissie" hint="Optioneel: alleen actuele leden van deze commissie zien de inschrijftaak">
            <select
              value={form.required_pool}
              onChange={(e) => setForm({ ...form, required_pool: e.target.value })}
              disabled={commissiesLoading || commissiesError}
              className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
            >
              <option value={0}>— geen —</option>
              {commissiesLoading && <option disabled>Commissies laden…</option>}
              {commissiesError && <option disabled>Commissies konden niet worden geladen</option>}
              {!commissiesLoading && !commissiesError && commissies.length === 0 && (
                <option disabled>Geen commissies beschikbaar</option>
              )}
              {commissies.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.title?.rendered || c.title}
                </option>
              ))}
            </select>
          </Field>
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

        <div className="flex flex-wrap items-center gap-2 pt-2">
          <button type="submit" disabled={saveMutation.isLoading} className="btn-primary inline-flex items-center gap-2">
            <Save className="w-4 h-4" />
            {saveMutation.isLoading ? 'Opslaan…' : 'Opslaan'}
          </button>
          {isEdit && (
            <button
              type="button"
              onClick={() => {
                if (window.confirm('Inschrijftaak verwijderen? Bestaande sjablonen en geplande inschrijftaken verwijzen dan naar een onbekende soort.')) {
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

function Toggle({ label, checked, onChange }) {
  return (
    <label className="flex items-center gap-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 px-3 py-2 cursor-pointer">
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="rounded border-gray-300 dark:border-gray-600"
      />
      <span className="font-medium text-gray-700 dark:text-gray-200">{label}</span>
    </label>
  );
}
