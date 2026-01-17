import { useState, useRef, useEffect } from 'react';
import { User, Search, ChevronLeft, UserPlus } from 'lucide-react';
import { useSearch } from '@/hooks/useDashboard';

/**
 * Popup for adding meeting attendee - choice between adding to existing person or creating new person.
 * Two modes: 'choice' (default) and 'search' (when selecting existing person).
 */
export default function AddAttendeePopup({
  isOpen,
  onClose,
  onCreateNew,
  onSelectPerson,
  attendee,
  isLoading = false,
}) {
  const [mode, setMode] = useState('choice');
  const [searchQuery, setSearchQuery] = useState('');
  const popupRef = useRef(null);
  const inputRef = useRef(null);

  // Search hook
  const trimmedQuery = searchQuery.trim();
  const { data: searchResults, isLoading: isSearching } = useSearch(trimmedQuery);
  const people = searchResults?.people || [];
  const showResults = trimmedQuery.length >= 2;

  // Close on click outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (popupRef.current && !popupRef.current.contains(event.target)) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isOpen, onClose]);

  // Handle escape key
  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleKeyDown);
      return () => document.removeEventListener('keydown', handleKeyDown);
    }
  }, [isOpen, onClose]);

  // Focus search input when entering search mode
  useEffect(() => {
    if (mode === 'search') {
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [mode]);

  // Reset state when popup closes
  useEffect(() => {
    if (!isOpen) {
      setMode('choice');
      setSearchQuery('');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleSelectPerson = (person) => {
    onSelectPerson(person);
  };

  const handleCreateNew = () => {
    onCreateNew();
  };

  const handleBackToChoice = () => {
    setMode('choice');
    setSearchQuery('');
  };

  return (
    <div
      ref={popupRef}
      className="absolute left-0 right-0 top-full z-50 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg overflow-hidden"
      style={{ minWidth: '288px' }}
    >
      {mode === 'choice' && (
        <div className="py-1">
          {/* Header with attendee info */}
          <div className="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
            <p className="text-xs text-gray-500 dark:text-gray-400">
              Add {attendee?.email || 'attendee'}
            </p>
          </div>

          {/* Choice buttons */}
          <button
            onClick={() => setMode('search')}
            disabled={isLoading}
            className="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
          >
            <Search className="w-4 h-4 text-gray-400" />
            <span>Add to existing person</span>
          </button>
          <button
            onClick={handleCreateNew}
            disabled={isLoading}
            className="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
          >
            <UserPlus className="w-4 h-4 text-gray-400" />
            <span>Create new person</span>
          </button>
        </div>
      )}

      {mode === 'search' && (
        <div>
          {/* Search header */}
          <div className="flex items-center border-b border-gray-200 dark:border-gray-700">
            <button
              onClick={handleBackToChoice}
              className="p-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
              title="Back"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <input
              ref={inputRef}
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search people..."
              className="flex-1 px-2 py-2.5 text-sm outline-none placeholder:text-gray-400 bg-transparent dark:text-gray-100"
              autoComplete="off"
            />
          </div>

          {/* Search results */}
          <div className="max-h-72 overflow-y-auto">
            {!showResults ? (
              <div className="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                Type at least 2 characters
              </div>
            ) : isSearching ? (
              <div className="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                Searching...
              </div>
            ) : people.length === 0 ? (
              <div className="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                No people found
              </div>
            ) : (
              <div className="py-1">
                {people.map((person) => (
                  <button
                    key={person.id}
                    onClick={() => handleSelectPerson(person)}
                    disabled={isLoading}
                    className="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                  >
                    {person.thumbnail ? (
                      <img
                        src={person.thumbnail}
                        alt={person.name}
                        className="w-8 h-8 rounded-full object-cover"
                      />
                    ) : (
                      <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                        <User className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                      </div>
                    )}
                    <span className="text-sm text-gray-900 dark:text-gray-100 truncate">
                      {person.name}
                    </span>
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Loading indicator */}
          {isLoading && (
            <div className="px-3 py-2 border-t border-gray-200 dark:border-gray-700 text-center text-xs text-gray-500 dark:text-gray-400">
              Adding email...
            </div>
          )}
        </div>
      )}
    </div>
  );
}
