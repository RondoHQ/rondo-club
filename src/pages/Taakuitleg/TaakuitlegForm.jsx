import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, BookOpen, Save, Trash2, QrCode } from 'lucide-react';
import {
  useTaakuitlegItem,
  useDienstTypes,
  useSaveTaakuitleg,
  useDeleteTaakuitleg,
} from '@/hooks/useTaakuitleg';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import RichTextEditor from '@/components/RichTextEditor';
import QrPrintModal from './QrPrintModal';

const publicUrl = (slug) => `${window.location.origin}/uitleg/${slug}`;

export default function TaakuitlegForm() {
  const { id } = useParams();
  const isEdit = !!id;
  useDocumentTitle(isEdit ? 'Taakuitleg bewerken' : 'Nieuwe taakuitleg');
  const navigate = useNavigate();

  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [dienstTypes, setDienstTypes] = useState([]);
  const [slug, setSlug] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [showQr, setShowQr] = useState(false);

  const { data: existing, isLoading: existingLoading } = useTaakuitlegItem(id);
  const { data: types = [], isLoading: typesLoading } = useDienstTypes();
  const saveMutation = useSaveTaakuitleg(id);
  const deleteMutation = useDeleteTaakuitleg();

  useEffect(() => {
    if (!existing) return;
    setTitle(existing.title?.raw ?? existing.title?.rendered ?? '');
    setBody(existing.content?.raw ?? existing.content?.rendered ?? '');
    setDienstTypes((existing.fields?.dienst_types || []).map(Number));
    setSlug(existing.slug || '');
  }, [existing]);

  const toggleType = (typeId) => {
    setDienstTypes((prev) =>
      prev.includes(typeId) ? prev.filter((t) => t !== typeId) : [...prev, typeId]
    );
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setFeedback(null);
    if (!title.trim()) {
      setFeedback({ kind: 'error', message: 'Geef een titel op.' });
      return;
    }
    saveMutation.mutate(
      {
        title: title.trim(),
        content: body,
        status: 'publish',
        fields: { dienst_types: dienstTypes },
      },
      {
        onSuccess: (res) => {
          const saved = res?.data;
          if (!isEdit && saved?.id) {
            navigate(`/vrijwilligers/taakuitleg/${saved.id}`, { replace: true });
          } else if (saved?.slug) {
            setSlug(saved.slug);
          }
          setFeedback({ kind: 'success', message: 'Opgeslagen.' });
        },
        onError: (err) => {
          setFeedback({
            kind: 'error',
            message: err?.response?.data?.message || err?.message || 'Opslaan mislukt.',
          });
        },
      }
    );
  };

  if ((isEdit && existingLoading) || typesLoading) {
    return <ContentLoadingSpinner />;
  }

  return (
    <div className="max-w-3xl mx-auto p-4 sm:p-6 space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers/taakuitleg"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar taakuitleg
        </Link>
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
              <BookOpen className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
            </div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {isEdit ? 'Taakuitleg bewerken' : 'Nieuwe taakuitleg'}
            </h1>
          </div>
          {isEdit && slug && (
            <button
              type="button"
              onClick={() => setShowQr(true)}
              className="btn-tertiary inline-flex items-center gap-2 whitespace-nowrap"
            >
              <QrCode className="w-4 h-4" /> QR printen
            </button>
          )}
        </div>
      </header>

      <form className="card p-5 space-y-4" onSubmit={handleSubmit}>
        <label className="block">
          <span className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Titel</span>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            required
            placeholder="bv. Koekenpan schoonmaken"
            className="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-3 py-2 text-sm"
          />
        </label>

        <div className="block">
          <span className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Tekst</span>
          <RichTextEditor
            value={body}
            onChange={setBody}
            enableImages
            minHeight="240px"
            placeholder="Beschrijf stap voor stap hoe deze taak werkt. Voeg afbeeldingen toe met de knop in de werkbalk."
          />
        </div>

        <div className="block">
          <span className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Inschrijftaken</span>
          {types.length === 0 ? (
            <p className="text-sm text-gray-500 dark:text-gray-400">Er zijn nog geen inschrijftaken.</p>
          ) : (
            <div className="flex flex-wrap gap-2">
              {types.map((t) => {
                const checked = dienstTypes.includes(t.id);
                return (
                  <button
                    key={t.id}
                    type="button"
                    onClick={() => toggleType(t.id)}
                    className={`px-3 py-1.5 rounded-full text-sm border transition-colors ${
                      checked
                        ? 'bg-electric-cyan text-white border-electric-cyan'
                        : 'bg-white text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600'
                    }`}
                  >
                    {t.title?.rendered || t.title}
                  </button>
                );
              })}
            </div>
          )}
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
                if (window.confirm('Deze taakuitleg verwijderen? Geprinte QR-codes werken daarna niet meer.')) {
                  deleteMutation.mutate(id, {
                    onSuccess: () => navigate('/vrijwilligers/taakuitleg'),
                  });
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

      {showQr && slug && (
        <QrPrintModal url={publicUrl(slug)} title={title || 'Taakuitleg'} onClose={() => setShowQr(false)} />
      )}
    </div>
  );
}
