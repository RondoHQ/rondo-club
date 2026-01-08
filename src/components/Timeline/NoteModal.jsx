import { useState, useEffect } from 'react';
import { X } from 'lucide-react';
import RichTextEditor, { isRichTextEmpty } from '@/components/RichTextEditor';

export default function NoteModal({ isOpen, onClose, onSubmit, isLoading, initialContent = '' }) {
  const [content, setContent] = useState('');

  useEffect(() => {
    if (isOpen) {
      setContent(initialContent || '');
    }
  }, [isOpen, initialContent]);

  if (!isOpen) return null;

  const handleSubmit = (e) => {
    e.preventDefault();
    if (isRichTextEmpty(content)) return;
    
    onSubmit(content);
    setContent('');
  };

  const handleClose = () => {
    setContent('');
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Add note</h2>
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
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Note
            </label>
            <RichTextEditor
              value={content}
              onChange={setContent}
              placeholder="Enter your note..."
              disabled={isLoading}
              autoFocus
              minHeight="150px"
            />
          </div>
          
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
              disabled={isLoading || isRichTextEmpty(content)}
            >
              {isLoading ? 'Adding...' : 'Add note'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
