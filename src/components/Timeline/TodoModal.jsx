import { useState, useEffect } from 'react';
import { X } from 'lucide-react';

export default function TodoModal({ isOpen, onClose, onSubmit, isLoading, todo = null }) {
  const [content, setContent] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [isCompleted, setIsCompleted] = useState(false);

  useEffect(() => {
    if (todo) {
      setContent(todo.content || '');
      setDueDate(todo.due_date || '');
      setIsCompleted(todo.is_completed || false);
    } else {
      setContent('');
      setDueDate('');
      setIsCompleted(false);
    }
  }, [todo, isOpen]);

  if (!isOpen) return null;

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!content.trim()) return;
    
    onSubmit({
      content: content.trim(),
      due_date: dueDate || null,
      is_completed: isCompleted,
    });
    
    if (!todo) {
      setContent('');
      setDueDate('');
      setIsCompleted(false);
    }
  };

  const handleClose = () => {
    if (!todo) {
      setContent('');
      setDueDate('');
      setIsCompleted(false);
    }
    onClose();
  };

  // Format date for input (YYYY-MM-DD)
  const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    try {
      const date = new Date(dateString);
      return date.toISOString().split('T')[0];
    } catch {
      return '';
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">{todo ? 'Edit todo' : 'Add todo'}</h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4">
          <div className="mb-4">
            <label htmlFor="todo-content" className="block text-sm font-medium text-gray-700 mb-2">
              Description
            </label>
            <textarea
              id="todo-content"
              value={content}
              onChange={(e) => setContent(e.target.value)}
              rows={4}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              placeholder="What needs to be done?"
              disabled={isLoading}
              autoFocus
            />
          </div>
          
          <div className="mb-4">
            <label htmlFor="todo-due-date" className="block text-sm font-medium text-gray-700 mb-2">
              Due date (optional)
            </label>
            <input
              id="todo-due-date"
              type="date"
              value={formatDateForInput(dueDate)}
              onChange={(e) => setDueDate(e.target.value || '')}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              disabled={isLoading}
            />
          </div>
          
          {todo && (
            <div className="mb-4">
              <label className="flex items-center">
                <input
                  type="checkbox"
                  checked={isCompleted}
                  onChange={(e) => setIsCompleted(e.target.checked)}
                  className="mr-2"
                  disabled={isLoading}
                />
                <span className="text-sm text-gray-700">Completed</span>
              </label>
            </div>
          )}
          
          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={handleClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading || !content.trim()}
            >
              {isLoading ? (todo ? 'Saving...' : 'Adding...') : (todo ? 'Save' : 'Add todo')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

