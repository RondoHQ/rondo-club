import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { BookOpen, Plus, QrCode, Pencil, ExternalLink } from 'lucide-react';
import { useTaakuitlegList, useDienstTypes } from '@/hooks/useTaakuitleg';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import QrPrintModal from './QrPrintModal';

const publicUrl = (slug) => `${window.location.origin}/uitleg/${slug}`;

export default function TaakuitlegList() {
  useDocumentTitle('Taakuitleg');
  const { data: items = [], isLoading } = useTaakuitlegList();
  const { data: types = [] } = useDienstTypes();
  const [filter, setFilter] = useState(0);
  const [qrTarget, setQrTarget] = useState(null);

  const typeName = useMemo(() => {
    const map = {};
    types.forEach((t) => {
      map[t.id] = t.title?.rendered || t.title || '';
    });
    return map;
  }, [types]);

  const filtered = useMemo(() => {
    if (!filter) return items;
    return items.filter((item) => (item.acf?.dienst_types || []).map(Number).includes(Number(filter)));
  }, [items, filter]);

  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="max-w-3xl mx-auto p-4 sm:p-6 space-y-6">
      <header className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <BookOpen className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Taakuitleg</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Uitleg voor vrijwilligers met QR-codes om te printen en op te plakken.
            </p>
          </div>
        </div>
        <Link to="/vrijwilligers/taakuitleg/nieuw" className="btn-primary inline-flex items-center gap-2 whitespace-nowrap">
          <Plus className="w-4 h-4" /> Nieuwe uitleg
        </Link>
      </header>

      {types.length > 0 && (
        <div className="flex flex-wrap items-center gap-2">
          <button
            onClick={() => setFilter(0)}
            className={`px-3 py-1 rounded-full text-sm ${filter === 0 ? 'bg-electric-cyan text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'}`}
          >
            Alle
          </button>
          {types.map((t) => (
            <button
              key={t.id}
              onClick={() => setFilter(t.id)}
              className={`px-3 py-1 rounded-full text-sm ${Number(filter) === t.id ? 'bg-electric-cyan text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'}`}
            >
              {t.title?.rendered || t.title}
            </button>
          ))}
        </div>
      )}

      {filtered.length === 0 ? (
        <div className="card p-8 text-center text-gray-500 dark:text-gray-400">
          Nog geen taakuitleg. Maak de eerste aan met “Nieuwe uitleg”.
        </div>
      ) : (
        <ul className="space-y-2">
          {filtered.map((item) => {
            const title = item.title?.rendered || '(zonder titel)';
            const typeIds = (item.acf?.dienst_types || []).map(Number);
            const isDraft = item.status === 'draft';
            return (
              <li key={item.id} className="card p-4 flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <Link
                      to={`/vrijwilligers/taakuitleg/${item.id}`}
                      className="font-medium text-gray-900 dark:text-gray-100 hover:text-electric-cyan truncate"
                    >
                      {title}
                    </Link>
                    {isDraft && (
                      <span className="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                        concept
                      </span>
                    )}
                  </div>
                  {typeIds.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-1">
                      {typeIds.map((id) => (
                        <span key={id} className="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">
                          {typeName[id] || '—'}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
                <div className="flex items-center gap-1 flex-shrink-0">
                  {!isDraft && (
                    <>
                      <button
                        onClick={() => setQrTarget({ url: publicUrl(item.slug), title })}
                        title="QR-code printen"
                        className="p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                      >
                        <QrCode className="w-4 h-4" />
                      </button>
                      <a
                        href={publicUrl(item.slug)}
                        target="_blank"
                        rel="noopener noreferrer"
                        title="Publieke pagina openen"
                        className="p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                      >
                        <ExternalLink className="w-4 h-4" />
                      </a>
                    </>
                  )}
                  <Link
                    to={`/vrijwilligers/taakuitleg/${item.id}`}
                    title="Bewerken"
                    className="p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                  >
                    <Pencil className="w-4 h-4" />
                  </Link>
                </div>
              </li>
            );
          })}
        </ul>
      )}

      {qrTarget && (
        <QrPrintModal url={qrTarget.url} title={qrTarget.title} onClose={() => setQrTarget(null)} />
      )}
    </div>
  );
}
