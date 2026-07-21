import { useState } from 'react';
import { X } from 'lucide-react';

export default function FeedbackResolutionModal({ feedback, onClose, onConfirm, isLoading }) {
  const [summary, setSummary] = useState(feedback.meta?.resolution_summary || '');
  const [error, setError] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    const trimmedSummary = summary.trim();
    if (!trimmedSummary) return;

    setError('');
    try {
      await onConfirm(trimmedSummary);
    } catch (submissionError) {
      setError(submissionError.response?.data?.message || submissionError.message || 'Opslaan is niet gelukt.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="dialog" aria-modal="true" aria-labelledby="feedback-resolution-title">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 id="feedback-resolution-title" className="text-lg font-semibold text-gray-900 dark:text-gray-50">
            Feedback oplossen
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
            aria-label="Sluiten"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="p-4 space-y-4">
            <p className="text-sm text-gray-600 dark:text-gray-300">
              Leg in het Nederlands uit hoe <strong>{feedback.title}</strong> is opgelost. Deze tekst wordt in de bevestigingsmail opgenomen.
            </p>
            <div>
              <label htmlFor="feedback-resolution-summary" className="label">Zo hebben we het opgelost *</label>
              <textarea
                id="feedback-resolution-summary"
                value={summary}
                onChange={(event) => setSummary(event.target.value)}
                className="input"
                rows={5}
                placeholder="Bijvoorbeeld: We hebben het formulier aangepast, zodat..."
                required
                autoFocus
                disabled={isLoading}
              />
            </div>
            {error ? <p className="text-sm text-red-600 dark:text-red-400">{error}</p> : null}
          </div>

          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button type="button" onClick={onClose} className="btn-secondary" disabled={isLoading}>
              Annuleren
            </button>
            <button type="submit" className="btn-primary" disabled={isLoading || !summary.trim()}>
              {isLoading ? 'Oplossen...' : 'Markeer als opgelost'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
