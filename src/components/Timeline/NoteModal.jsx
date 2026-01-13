import { useState, useEffect } from 'react';
import { X, Lock, Users } from 'lucide-react';
import RichTextEditor, { isRichTextEmpty } from '@/components/RichTextEditor';
import MentionInput from '@/components/MentionInput';

export default function NoteModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  initialContent = '',
  isContactShared = false,
  workspaceIds = [],
}) {
  const [content, setContent] = useState('');
  const [visibility, setVisibility] = useState('private');

  useEffect(() => {
    if (isOpen) {
      setContent(initialContent || '');
      setVisibility('private'); // Default to private on open
    }
  }, [isOpen, initialContent]);

  if (!isOpen) return null;

  // Check if content is empty - MentionInput returns plain text, RichTextEditor returns HTML
  const isContentEmpty = workspaceIds.length > 0
    ? !content || content.trim() === ''
    : isRichTextEmpty(content);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (isContentEmpty) return;

    onSubmit(content, visibility);
    setContent('');
    setVisibility('private');
  };

  const handleClose = () => {
    setContent('');
    setVisibility('private');
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
            {workspaceIds.length > 0 ? (
              <MentionInput
                value={content}
                onChange={setContent}
                placeholder="Add a note... Use @ to mention someone"
                workspaceIds={workspaceIds}
              />
            ) : (
              <RichTextEditor
                value={content}
                onChange={setContent}
                placeholder="Enter your note..."
                disabled={isLoading}
                autoFocus
                minHeight="150px"
              />
            )}
          </div>

          {/* Only show visibility toggle when contact is shared */}
          {isContactShared && (
            <div className="mb-4">
              <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={visibility === 'shared'}
                  onChange={(e) => setVisibility(e.target.checked ? 'shared' : 'private')}
                  className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  disabled={isLoading}
                />
                <span className="flex items-center gap-1.5">
                  {visibility === 'shared' ? (
                    <Users className="w-4 h-4 text-blue-500" />
                  ) : (
                    <Lock className="w-4 h-4 text-gray-400" />
                  )}
                  Share this note with others who can see this contact
                </span>
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
              disabled={isLoading || isContentEmpty}
            >
              {isLoading ? 'Adding...' : 'Add note'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
