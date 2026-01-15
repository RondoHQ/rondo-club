import { useState, useEffect, useMemo } from 'react';
import { X, User, ChevronDown, Search, Plus, Pencil } from 'lucide-react';
import RichTextEditor from '@/components/RichTextEditor';
import { usePeople } from '@/hooks/usePeople';
import { format } from 'date-fns';

export default function TodoModal({ isOpen, onClose, onSubmit, isLoading, todo = null }) {
  const [content, setContent] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [notes, setNotes] = useState('');
  const [selectedPersonIds, setSelectedPersonIds] = useState([]);
  const [isPersonDropdownOpen, setIsPersonDropdownOpen] = useState(false);
  const [personSearchQuery, setPersonSearchQuery] = useState('');
  const [showNotes, setShowNotes] = useState(false);
  const [isViewMode, setIsViewMode] = useState(false);

  const { data: people = [], isLoading: isPeopleLoading } = usePeople();

  // Filter and sort people based on search query
  const filteredPeople = useMemo(() => {
    const query = personSearchQuery.toLowerCase().trim();
    let filtered = people;

    if (query) {
      filtered = people.filter(person =>
        person.name?.toLowerCase().includes(query) ||
        person.first_name?.toLowerCase().includes(query) ||
        person.last_name?.toLowerCase().includes(query)
      );
    }

    // Sort alphabetically by name
    return [...filtered].sort((a, b) =>
      (a.name || '').localeCompare(b.name || '')
    );
  }, [people, personSearchQuery]);

  // Get selected persons details
  const selectedPersons = useMemo(() =>
    people.filter(p => selectedPersonIds.includes(p.id)),
    [people, selectedPersonIds]
  );

  // Get tomorrow's date in YYYY-MM-DD format (default for new todos)
  const getTomorrowDate = () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().split('T')[0];
  };

  useEffect(() => {
    if (todo) {
      setContent(todo.content || '');
      setDueDate(todo.due_date || '');
      setNotes(todo.notes || '');
      // Support both new persons array and legacy single person_id
      setSelectedPersonIds(todo.persons?.map(p => p.id) || [todo.person_id].filter(Boolean));
      // Show notes section if there are existing notes
      setShowNotes(!!todo.notes);
      // Start in view mode for existing todos
      setIsViewMode(true);
    } else {
      setContent('');
      setDueDate(getTomorrowDate());
      setNotes('');
      setSelectedPersonIds([]);
      setShowNotes(false);
      // Start in edit mode for new todos
      setIsViewMode(false);
    }
    setIsPersonDropdownOpen(false);
    setPersonSearchQuery('');
  }, [todo, isOpen]);

  if (!isOpen) return null;

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!content.trim()) return;

    // Build submission data
    const data = {
      content: content.trim(),
      due_date: dueDate || null,
    };

    // Include notes only if not empty
    if (notes.trim()) {
      data.notes = notes;
    }

    // Include person_ids for existing todos
    if (todo && selectedPersonIds.length > 0) {
      data.person_ids = selectedPersonIds;
    }

    onSubmit(data);

    if (!todo) {
      setContent('');
      setDueDate(getTomorrowDate());
      setNotes('');
      setSelectedPersonIds([]);
      setShowNotes(false);
    }
  };

  const handleClose = () => {
    if (!todo) {
      setContent('');
      setDueDate(getTomorrowDate());
      setNotes('');
      setSelectedPersonIds([]);
      setShowNotes(false);
    }
    setIsPersonDropdownOpen(false);
    setPersonSearchQuery('');
    setIsViewMode(false);
    onClose();
  };

  const handleCancel = () => {
    if (todo) {
      // For existing todos, go back to view mode instead of closing
      setIsViewMode(true);
      // Reset form values to original
      setContent(todo.content || '');
      setDueDate(todo.due_date || '');
      setNotes(todo.notes || '');
      setSelectedPersonIds(todo.persons?.map(p => p.id) || [todo.person_id].filter(Boolean));
    } else {
      // For new todos, just close
      handleClose();
    }
  };

  const handleRemovePerson = (personId) => {
    setSelectedPersonIds(prev => prev.filter(id => id !== personId));
  };

  const handleAddPerson = (personId) => {
    if (!selectedPersonIds.includes(personId)) {
      setSelectedPersonIds(prev => [...prev, personId]);
    }
    setIsPersonDropdownOpen(false);
    setPersonSearchQuery('');
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

  // Format date for display (e.g., "Jan 16, 2026")
  const formatDateForDisplay = (dateString) => {
    if (!dateString) return 'No due date';
    try {
      return format(new Date(dateString), 'MMM d, yyyy');
    } catch {
      return 'No due date';
    }
  };

  // Get the modal title based on mode and todo state
  const getModalTitle = () => {
    if (!todo) return 'Add todo';
    return isViewMode ? 'View todo' : 'Edit todo';
  };

  // View mode layout
  const renderViewMode = () => (
    <div className="p-4">
      {/* Todo content */}
      <div className="mb-4">
        <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</p>
        <p className="text-gray-900 dark:text-gray-50 whitespace-pre-wrap">{todo?.content}</p>
      </div>

      {/* Due date */}
      <div className="mb-4">
        <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due date</p>
        <p className="text-gray-600 dark:text-gray-400">{formatDateForDisplay(todo?.due_date)}</p>
      </div>

      {/* Notes - only show if there are notes */}
      {todo?.notes && (
        <div className="mb-4">
          <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</p>
          <div
            className="text-gray-600 dark:text-gray-400 prose prose-sm dark:prose-invert max-w-none"
            dangerouslySetInnerHTML={{ __html: todo.notes }}
          />
        </div>
      )}

      {/* Related people */}
      {selectedPersons.length > 0 && (
        <div className="mb-4">
          <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Related people</p>
          <div className="flex flex-wrap gap-2">
            {selectedPersons.map(person => (
              <span
                key={person.id}
                className="inline-flex items-center gap-1.5 px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded-full text-sm"
              >
                {person.thumbnail ? (
                  <img
                    src={person.thumbnail}
                    alt={person.name}
                    className="w-5 h-5 rounded-full object-cover"
                  />
                ) : (
                  <div className="w-5 h-5 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                    <User className="w-3 h-3 text-gray-500 dark:text-gray-400" />
                  </div>
                )}
                <span className="text-gray-700 dark:text-gray-200">{person.name}</span>
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Footer buttons */}
      <div className="flex justify-end gap-2 pt-2">
        <button
          type="button"
          onClick={handleClose}
          className="btn-secondary"
        >
          Close
        </button>
        <button
          type="button"
          onClick={() => setIsViewMode(false)}
          className="btn-primary flex items-center gap-1"
        >
          <Pencil className="w-4 h-4" />
          Edit
        </button>
      </div>
    </div>
  );

  // Edit mode layout
  const renderEditMode = () => (
    <form onSubmit={handleSubmit} className="p-4">
      <div className="mb-4">
        <label htmlFor="todo-content" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Description
        </label>
        <textarea
          id="todo-content"
          value={content}
          onChange={(e) => setContent(e.target.value)}
          rows={4}
          className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          placeholder="What needs to be done?"
          disabled={isLoading}
          autoFocus
        />
      </div>

      <div className="mb-4">
        <label htmlFor="todo-due-date" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Due date (optional)
        </label>
        <input
          id="todo-due-date"
          type="date"
          value={formatDateForInput(dueDate)}
          onChange={(e) => setDueDate(e.target.value || '')}
          className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          disabled={isLoading}
        />
      </div>

      {/* Notes field - collapsible */}
      <div className="mb-4">
        <button
          type="button"
          onClick={() => setShowNotes(!showNotes)}
          className="flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 hover:text-gray-900 dark:hover:text-gray-100"
        >
          <ChevronDown className={`w-4 h-4 transition-transform ${showNotes ? '' : '-rotate-90'}`} />
          Notes (optional)
        </button>
        {showNotes && (
          <RichTextEditor
            value={notes}
            onChange={setNotes}
            placeholder="Add detailed notes..."
            disabled={isLoading}
            minHeight="80px"
          />
        )}
      </div>

      {/* Related People section - only show when editing existing todo */}
      {todo && (
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Related people
          </label>

          {/* Selected persons as chips */}
          {selectedPersons.length > 0 && (
            <div className="flex flex-wrap gap-2 mb-2">
              {selectedPersons.map(person => (
                <span
                  key={person.id}
                  className="inline-flex items-center gap-1.5 px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded-full text-sm"
                >
                  {person.thumbnail ? (
                    <img
                      src={person.thumbnail}
                      alt={person.name}
                      className="w-5 h-5 rounded-full object-cover"
                    />
                  ) : (
                    <div className="w-5 h-5 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                      <User className="w-3 h-3 text-gray-500 dark:text-gray-400" />
                    </div>
                  )}
                  <span className="text-gray-700 dark:text-gray-200">{person.name}</span>
                  <button
                    type="button"
                    onClick={() => handleRemovePerson(person.id)}
                    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    disabled={isLoading}
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                </span>
              ))}
            </div>
          )}

          {/* Add person button and dropdown */}
          <div className="relative">
            <button
              type="button"
              onClick={() => setIsPersonDropdownOpen(!isPersonDropdownOpen)}
              className="flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
              disabled={isLoading}
            >
              <Plus className="w-4 h-4" />
              Add person
            </button>

            {isPersonDropdownOpen && (
              <div className="absolute z-10 left-0 mt-1 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-hidden">
                {/* Search input */}
                <div className="p-2 border-b border-gray-100 dark:border-gray-700">
                  <div className="relative">
                    <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input
                      type="text"
                      value={personSearchQuery}
                      onChange={(e) => setPersonSearchQuery(e.target.value)}
                      placeholder="Search people..."
                      className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-1 focus:ring-primary-500"
                      autoFocus
                    />
                  </div>
                </div>

                {/* People list */}
                <div className="overflow-y-auto max-h-48">
                  {isPeopleLoading ? (
                    <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                      Loading...
                    </div>
                  ) : filteredPeople.length > 0 ? (
                    filteredPeople
                      .filter(person => !selectedPersonIds.includes(person.id))
                      .map(person => (
                        <button
                          key={person.id}
                          type="button"
                          onClick={() => handleAddPerson(person.id)}
                          className="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                          {person.thumbnail ? (
                            <img
                              src={person.thumbnail}
                              alt={person.name}
                              className="w-6 h-6 rounded-full object-cover"
                            />
                          ) : (
                            <div className="w-6 h-6 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                              <User className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            </div>
                          )}
                          <span className="text-sm text-gray-900 dark:text-gray-50 truncate">{person.name}</span>
                        </button>
                      ))
                  ) : (
                    <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                      No people found
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Status hint for existing todos */}
      {todo && (
        <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
          Tip: Use the status buttons on the todo list to change between Open, Awaiting, and Completed.
        </p>
      )}

      <div className="flex justify-end gap-2">
        <button
          type="button"
          onClick={handleCancel}
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
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{getModalTitle()}</h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {isViewMode && todo ? renderViewMode() : renderEditMode()}
      </div>
    </div>
  );
}
