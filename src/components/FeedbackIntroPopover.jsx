import { MessageSquarePlus, X } from 'lucide-react';

export default function FeedbackIntroPopover({ onAcknowledge, onOpenFeedback }) {
  return (
    <div
      className="absolute right-0 top-full z-50 mt-3 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-cyan-200 bg-white p-4 text-left shadow-xl dark:border-cyan-800 dark:bg-gray-800"
      role="dialog"
      aria-modal="false"
      aria-labelledby="feedback-intro-title"
      aria-describedby="feedback-intro-description"
    >
      <span
        className="absolute -top-2 right-3 h-4 w-4 rotate-45 border-l border-t border-cyan-200 bg-white dark:border-cyan-800 dark:bg-gray-800"
        aria-hidden="true"
      />

      <div className="relative flex items-start gap-3">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300">
          <MessageSquarePlus className="h-5 w-5" aria-hidden="true" />
        </div>

        <div className="min-w-0 flex-1">
          <h2 id="feedback-intro-title" className="pr-6 font-semibold text-gray-900 dark:text-gray-100">
            Help Rondo beter te maken
          </h2>
          <p id="feedback-intro-description" className="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
            Heb je een idee of werkt iets niet? Gebruik deze knop om het direct door te geven. Rondo vermeldt automatisch op welke pagina je bent.
          </p>
        </div>

        <button
          type="button"
          onClick={onAcknowledge}
          className="absolute -right-1 -top-1 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-hidden focus:ring-2 focus:ring-cyan-500 dark:hover:bg-gray-700 dark:hover:text-gray-200"
          aria-label="Uitleg sluiten"
        >
          <X className="h-4 w-4" aria-hidden="true" />
        </button>
      </div>

      <div className="relative mt-4 flex flex-wrap justify-end gap-2">
        <button type="button" onClick={onAcknowledge} className="btn-tertiary px-3 py-1.5 text-sm">
          Begrepen
        </button>
        <button type="button" onClick={onOpenFeedback} className="btn-primary px-3 py-1.5 text-sm">
          Feedback geven
        </button>
      </div>
    </div>
  );
}
