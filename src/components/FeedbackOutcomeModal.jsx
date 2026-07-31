import { useState } from 'react';
import { X } from 'lucide-react';

// Resolving and declining ask the same thing of the beheerder — a Dutch
// explanation that gets quoted verbatim in the mail to the submitter — so they
// share one dialog rather than two near-identical copies.
const VARIANTS = {
  resolve: {
    title: 'Feedback oplossen',
    prompt: (title) => <>Leg in het Nederlands uit hoe <strong>{title}</strong> is opgelost. Deze tekst wordt in de bevestigingsmail opgenomen.</>,
    label: 'Zo hebben we het opgelost *',
    placeholder: 'Bijvoorbeeld: We hebben het formulier aangepast, zodat...',
    submit: 'Markeer als opgelost',
    busy: 'Oplossen...',
    initialValue: (feedback) => feedback.meta?.resolution_summary || '',
  },
  decline: {
    title: 'Feedback afwijzen',
    prompt: (title) => <>Leg in het Nederlands uit waarom <strong>{title}</strong> wordt afgewezen. Deze tekst wordt in de mail aan de inzender opgenomen.</>,
    label: 'Waarom we dit niet doen *',
    placeholder: 'Bijvoorbeeld: Dit kan al via de bestaande knop op het dashboard, dus...',
    submit: 'Markeer als afgewezen',
    busy: 'Afwijzen...',
    initialValue: (feedback) => feedback.meta?.decline_reason || '',
  },
};

export default function FeedbackOutcomeModal({ feedback, variant = 'resolve', onClose, onConfirm, isLoading }) {
  const config = VARIANTS[variant] || VARIANTS.resolve;
  const [text, setText] = useState(() => config.initialValue(feedback));
  const [error, setError] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    const trimmed = text.trim();
    if (!trimmed) return;

    setError('');
    try {
      await onConfirm(trimmed);
    } catch (submissionError) {
      setError(submissionError.response?.data?.message || submissionError.message || 'Opslaan is niet gelukt.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="dialog" aria-modal="true" aria-labelledby="feedback-outcome-title">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 id="feedback-outcome-title" className="text-lg font-semibold text-gray-900 dark:text-gray-50">
            {config.title}
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
              {config.prompt(feedback.title)}
            </p>
            <div>
              <label htmlFor="feedback-outcome-text" className="label">{config.label}</label>
              <textarea
                id="feedback-outcome-text"
                value={text}
                onChange={(event) => setText(event.target.value)}
                className="input"
                rows={5}
                placeholder={config.placeholder}
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
            <button type="submit" className="btn-primary" disabled={isLoading || !text.trim()}>
              {isLoading ? config.busy : config.submit}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
