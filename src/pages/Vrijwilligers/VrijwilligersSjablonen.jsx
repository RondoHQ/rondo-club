import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CalendarClock, Plus, Pencil, Play, X } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { refreshShiftCalendars } from '@/utils/shiftQueryCache';

const DAYS = ['', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];
const DUTCH_DATE_FORMATTER = new Intl.DateTimeFormat('nl-NL', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
});

function formatFieldDate(value) {
  if (!value) return '';
  if (/^\d{8}$/.test(value)) return `${value.slice(0, 4)}-${value.slice(4, 6)}-${value.slice(6, 8)}`;
  return value;
}

function formatDateInput(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function formatDisplayDate(value) {
  const [year, month, day] = value.split('-').map(Number);
  return DUTCH_DATE_FORMATTER.format(new Date(year, month - 1, day));
}

function ExpandTemplatesModal({ isLoading, error, onClose, onSubmit }) {
  const [until, setUntil] = useState('');

  const today = formatDateInput(new Date());

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="expand-templates-title"
        className="w-full max-w-md rounded-lg bg-white shadow-xl dark:bg-gray-800"
      >
        <form
          onSubmit={(event) => {
            event.preventDefault();
            onSubmit(until);
          }}
        >
          <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
            <h2 id="expand-templates-title" className="text-lg font-semibold text-gray-900 dark:text-gray-50">
              Sjablonen uitrollen
            </h2>
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="text-gray-400 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:text-gray-300"
              aria-label="Sluiten"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          <div className="space-y-4 p-4">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Kies tot en met welke datum je de actieve sjablonen naar geplande inschrijftaken wilt uitrollen.
            </p>
            <div>
              <label htmlFor="expand-until" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Uitrollen tot en met
              </label>
              <input
                id="expand-until"
                type="date"
                value={until}
                min={today}
                onChange={(event) => setUntil(event.target.value)}
                className="input w-full"
                required
                autoFocus
              />
            </div>
            {error ? <p className="text-sm text-red-600 dark:text-red-400">{error}</p> : null}
          </div>

          <div className="flex justify-end gap-2 rounded-b-lg border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
            <button type="button" onClick={onClose} disabled={isLoading} className="btn-secondary">
              Annuleren
            </button>
            <button type="submit" disabled={isLoading || !until} className="btn-primary inline-flex items-center gap-2">
              <Play className="h-4 w-4" />
              {isLoading ? 'Uitrollen…' : 'Uitrollen'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function VrijwilligersSjablonen() {
  useDocumentTitle('Sjablonen — Vrijwilligers');
  const queryClient = useQueryClient();
  const [feedback, setFeedback] = useState(null);
  const [showExpandModal, setShowExpandModal] = useState(false);
  const [expandError, setExpandError] = useState('');

  const { data: types = [] } = useQuery({
    queryKey: ['volunteer', 'dienst-types'],
    queryFn: async () => (await prmApi.getDienstTypes()).data || [],
    staleTime: 5 * 60 * 1000,
  });

  const { data: templates = [], isLoading } = useQuery({
    queryKey: ['volunteer', 'shift-templates'],
    queryFn: async () => (await prmApi.getShiftTemplates()).data || [],
    staleTime: 60 * 1000,
  });

  const expandMutation = useMutation({
    mutationFn: (until) => prmApi.expandShiftTemplates(until),
    onMutate: () => setExpandError(''),
    onSuccess: async (response, selectedUntil) => {
      const created = response?.data?.created ?? 0;
      const until = response?.data?.until ?? selectedUntil;
      await refreshShiftCalendars(queryClient);
      setShowExpandModal(false);
      setFeedback({
        kind: 'success',
        message: created > 0
          ? `${created} ${created === 1 ? 'nieuwe inschrijftaak' : 'nieuwe inschrijftaken'} aangemaakt tot en met ${formatDisplayDate(until)}.`
          : `Alle sjablonen zijn al uitgerold tot en met ${formatDisplayDate(until)} — niets toe te voegen.`,
      });
    },
    onError: (err) => {
      setExpandError(err?.response?.data?.message || err?.message || 'Uitrollen mislukt.');
    },
  });

  const typeMap = useMemo(() => {
    const map = new Map();
    for (const t of types) map.set(t.id, t.title?.rendered || t.title);
    return map;
  }, [types]);

  const rows = useMemo(() => {
    return [...templates].sort((a, b) => {
      const da = Number(a.fields?.day_of_week || 0);
      const db = Number(b.fields?.day_of_week || 0);
      if (da !== db) return da - db;
      return (a.fields?.start_time || '').localeCompare(b.fields?.start_time || '');
    });
  }, [templates]);

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers/diensten"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar inschrijftaken
        </Link>
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
              <CalendarClock className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
            </div>
            <div>
              <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Sjablonen</h1>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Wekelijks terugkerende regels. De expander rolt deze elke nacht uit naar geplande inschrijftaken.
              </p>
            </div>
          </div>
          <div className="flex gap-2 shrink-0">
            <button
              type="button"
              onClick={() => {
                setFeedback(null);
                setExpandError('');
                setShowExpandModal(true);
              }}
              disabled={expandMutation.isPending || templates.length === 0}
              className="btn-tertiary inline-flex items-center gap-2"
              title="Kies tot wanneer je alle actieve sjablonen wilt uitrollen"
            >
              <Play className="w-4 h-4" />
              Uitrollen
            </button>
            <Link to="/vrijwilligers/sjablonen/nieuw" className="btn-primary inline-flex items-center gap-2">
              <Plus className="w-4 h-4" /> Nieuw sjabloon
            </Link>
          </div>
        </div>
        {feedback && (
          <div
            className={`text-sm pt-2 ${
              feedback.kind === 'success'
                ? 'text-emerald-700 dark:text-emerald-300'
                : 'text-red-700 dark:text-red-300'
            }`}
          >
            {feedback.message}
          </div>
        )}
      </header>

      {isLoading ? (
        <ContentLoadingSpinner />
      ) : rows.length === 0 ? (
        <div className="card p-8 text-center text-sm text-gray-500 dark:text-gray-400">
          Nog geen sjablonen. Maak er één aan om wekelijkse inschrijftaken automatisch uit te rollen.
        </div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
              <tr>
                <th className="px-4 py-2">Inschrijftaak</th>
                <th className="px-4 py-2">Dag</th>
                <th className="px-4 py-2">Tijd</th>
                <th className="px-4 py-2">Capaciteit</th>
                <th className="px-4 py-2">Actief</th>
                <th className="px-4 py-2 w-12"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {rows.map((tpl) => {
                const fields = tpl.fields || {};
                return (
                  <tr key={tpl.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-2">
                      <Link to={`/vrijwilligers/sjablonen/${tpl.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                        {typeMap.get(Number(fields.dienst_type_id)) || '—'}
                      </Link>
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{DAYS[Number(fields.day_of_week) || 0] || '—'}</td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                      {fields.start_time || '—'} – {fields.end_time || '—'}
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                      {fields.capacity > 0 ? fields.capacity : <span className="text-gray-400">default</span>}
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300 text-xs">
                      {formatFieldDate(fields.active_from) || '—'} → {formatFieldDate(fields.active_until) || 'doorlopend'}
                    </td>
                    <td className="px-4 py-2 text-right">
                      <Link
                        to={`/vrijwilligers/sjablonen/${tpl.id}`}
                        className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        title="Bewerken"
                      >
                        <Pencil className="w-4 h-4" />
                      </Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {showExpandModal ? (
        <ExpandTemplatesModal
          isLoading={expandMutation.isPending}
          error={expandError}
          onClose={() => setShowExpandModal(false)}
          onSubmit={(until) => expandMutation.mutate(until)}
        />
      ) : null}
    </div>
  );
}
