import { useState, useEffect, useMemo } from 'react';
import { X, User, ChevronDown, Search, Plus } from 'lucide-react';
import { usePeople, useCreateTodo } from '@/hooks/usePeople';
import { useQueryClient } from '@tanstack/react-query';
import RichTextEditor from '@/components/RichTextEditor';

export default function GlobalTodoModal({ isOpen, onClose }) {
  const [content, setContent] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [selectedPersonIds, setSelectedPersonIds] = useState([]);
  const [isPersonDropdownOpen, setIsPersonDropdownOpen] = useState(false);
  const [personSearchQuery, setPersonSearchQuery] = useState('');
  const [notes, setNotes] = useState('');
  const [showNotes, setShowNotes] = useState(false);
  
  const { data: people = [], isLoading: isPeopleLoading } = usePeople();
  const createTodo = useCreateTodo();
  const queryClient = useQueryClient();
  
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
  
  // Reset form when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      setContent('');
      setDueDate(getTomorrowDate());
      setSelectedPersonIds([]);
      setPersonSearchQuery('');
      setIsPersonDropdownOpen(false);
      setNotes('');
      setShowNotes(false);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!content.trim() || selectedPersonIds.length === 0) return;

    try {
      // Build data object
      const data = {
        content: content.trim(),
        due_date: dueDate || null,
        person_ids: selectedPersonIds,
      };

      // Include notes only if not empty
      if (notes.trim()) {
        data.notes = notes;
      }

      // New todos are always created with 'open' status (handled by backend)
      // Use the first person for the endpoint URL (backend handles multi-person via person_ids)
      await createTodo.mutateAsync({
        personId: selectedPersonIds[0],
        data,
      });

      // Invalidate todos query to refresh lists
      queryClient.invalidateQueries({ queryKey: ['todos'] });

      onClose();
    } catch {
      // Todo creation failed - user can retry
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

  const handleClose = () => {
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
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Add todo</h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={createTodo.isPending}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-4">
          {/* People selection - multi-person */}
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              People <span className="text-red-500 dark:text-red-400">*</span>
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
                      disabled={createTodo.isPending}
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
                className="flex items-center gap-1 text-sm text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300"
                disabled={createTodo.isPending}
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
                        className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-1 focus:ring-accent-500"
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

          {/* Description */}
          <div className="mb-4">
            <label htmlFor="todo-content" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Description <span className="text-red-500 dark:text-red-400">*</span>
            </label>
            <textarea
              id="todo-content"
              value={content}
              onChange={(e) => setContent(e.target.value)}
              rows={4}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
              placeholder="What needs to be done?"
              disabled={createTodo.isPending}
            />
          </div>

          {/* Due date */}
          <div className="mb-4">
            <label htmlFor="todo-due-date" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Due date (optional)
            </label>
            <input
              id="todo-due-date"
              type="date"
              value={formatDateForInput(dueDate)}
              onChange={(e) => setDueDate(e.target.value || '')}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
              disabled={createTodo.isPending}
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
                disabled={createTodo.isPending}
                minHeight="80px"
              />
            )}
          </div>

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={handleClose}
              className="btn-secondary"
              disabled={createTodo.isPending}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={createTodo.isPending || !content.trim() || selectedPersonIds.length === 0}
            >
              {createTodo.isPending ? 'Adding...' : 'Add todo'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

