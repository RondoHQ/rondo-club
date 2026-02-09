import { Clock, CheckSquare, FileText, X } from 'lucide-react';

export default function CompleteTodoModal({ isOpen, onClose, todo, onAwaiting, onComplete, onCompleteAsActivity, hideAwaitingOption = false }) {
  if (!isOpen || !todo) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Taak afronden</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4">
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
            "{todo.content}"
          </p>

          <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Wat is de status van deze taak?
          </p>

          <div className="space-y-3">
            {!hideAwaitingOption && (
              <button
                onClick={onAwaiting}
                className="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-orange-300 dark:hover:border-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors text-left"
              >
                <Clock className="w-5 h-5 text-orange-600 dark:text-orange-400 flex-shrink-0" />
                <div className="flex-1">
                  <p className="font-medium text-gray-900 dark:text-gray-50">Openstaand</p>
                  <p className="text-sm text-gray-500 dark:text-gray-400">Je hebt je deel gedaan, wachten op hun reactie</p>
                </div>
              </button>
            )}

            <button
              onClick={onComplete}
              className="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-electric-cyan-light dark:hover:border-electric-cyan hover:bg-cyan-50 dark:hover:bg-obsidian/30 transition-colors text-left"
            >
              <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan flex-shrink-0" />
              <div className="flex-1">
                <p className="font-medium text-gray-900 dark:text-gray-50">Afronden</p>
                <p className="text-sm text-gray-500 dark:text-gray-400">Markeer de taak als volledig afgerond</p>
              </div>
            </button>

            <button
              onClick={onCompleteAsActivity}
              className="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors text-left"
            >
              <FileText className="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0" />
              <div className="flex-1">
                <p className="font-medium text-gray-900 dark:text-gray-50">Afronden & activiteit loggen</p>
                <p className="text-sm text-gray-500 dark:text-gray-400">Leg dit vast als activiteit op de tijdlijn</p>
              </div>
            </button>
          </div>
        </div>

        <div className="flex justify-end p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 rounded-b-lg">
          <button
            type="button"
            onClick={onClose}
            className="btn-secondary"
          >
            Annuleren
          </button>
        </div>
      </div>
    </div>
  );
}
