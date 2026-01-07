import { CheckSquare, FileText, X } from 'lucide-react';

export default function CompleteTodoModal({ isOpen, onClose, todo, onComplete, onCompleteAsActivity }) {
  if (!isOpen || !todo) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Complete todo</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <div className="p-4">
          <p className="text-sm text-gray-600 mb-4">
            "{todo.content}"
          </p>
          
          <p className="text-sm text-gray-500 mb-4">
            How would you like to complete this todo?
          </p>
          
          <div className="space-y-3">
            <button
              onClick={onComplete}
              className="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-gray-200 rounded-lg hover:border-primary-300 hover:bg-primary-50 transition-colors text-left"
            >
              <CheckSquare className="w-5 h-5 text-primary-600 flex-shrink-0" />
              <div className="flex-1">
                <p className="font-medium text-gray-900">Just complete</p>
                <p className="text-sm text-gray-500">Mark the todo as done</p>
              </div>
            </button>
            
            <button
              onClick={onCompleteAsActivity}
              className="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition-colors text-left"
            >
              <FileText className="w-5 h-5 text-blue-600 flex-shrink-0" />
              <div className="flex-1">
                <p className="font-medium text-gray-900">Complete & log activity</p>
                <p className="text-sm text-gray-500">Record this as an activity on the timeline</p>
              </div>
            </button>
          </div>
        </div>
        
        <div className="flex justify-end p-4 border-t bg-gray-50 rounded-b-lg">
          <button
            type="button"
            onClick={onClose}
            className="btn-secondary"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
}

