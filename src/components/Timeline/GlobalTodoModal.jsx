import { useState, useEffect, useMemo } from 'react';
import { X, User, ChevronDown, Search } from 'lucide-react';
import { usePeople, useCreateTodo } from '@/hooks/usePeople';
import { useQueryClient } from '@tanstack/react-query';

export default function GlobalTodoModal({ isOpen, onClose }) {
  const [content, setContent] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [selectedPersonId, setSelectedPersonId] = useState('');
  const [isPersonDropdownOpen, setIsPersonDropdownOpen] = useState(false);
  const [personSearchQuery, setPersonSearchQuery] = useState('');
  
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
  
  // Get selected person details
  const selectedPerson = useMemo(() => 
    people.find(p => p.id === parseInt(selectedPersonId)),
    [people, selectedPersonId]
  );
  
  // Get today's date in YYYY-MM-DD format
  const getTodayDate = () => {
    const today = new Date();
    return today.toISOString().split('T')[0];
  };
  
  // Reset form when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      setContent('');
      setDueDate(getTodayDate());
      setSelectedPersonId('');
      setPersonSearchQuery('');
      setIsPersonDropdownOpen(false);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!content.trim() || !selectedPersonId) return;
    
    try {
      await createTodo.mutateAsync({
        personId: parseInt(selectedPersonId),
        data: {
          content: content.trim(),
          due_date: dueDate || null,
          is_completed: false,
        },
      });
      
      // Invalidate todos query to refresh lists
      queryClient.invalidateQueries({ queryKey: ['todos'] });
      
      onClose();
    } catch (error) {
      console.error('Failed to create todo:', error);
    }
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
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Add todo</h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={createTodo.isPending}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4">
          {/* Person selection */}
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Person <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <button
                type="button"
                onClick={() => setIsPersonDropdownOpen(!isPersonDropdownOpen)}
                className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-md bg-white text-left focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                disabled={createTodo.isPending}
              >
                {selectedPerson ? (
                  <div className="flex items-center gap-2">
                    {selectedPerson.thumbnail ? (
                      <img
                        src={selectedPerson.thumbnail}
                        alt={selectedPerson.name}
                        className="w-6 h-6 rounded-full object-cover"
                      />
                    ) : (
                      <div className="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center">
                        <User className="w-4 h-4 text-gray-500" />
                      </div>
                    )}
                    <span className="text-gray-900">{selectedPerson.name}</span>
                  </div>
                ) : (
                  <span className="text-gray-400">Select a person...</span>
                )}
                <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${isPersonDropdownOpen ? 'rotate-180' : ''}`} />
              </button>
              
              {isPersonDropdownOpen && (
                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-hidden">
                  {/* Search input */}
                  <div className="p-2 border-b border-gray-100">
                    <div className="relative">
                      <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <input
                        type="text"
                        value={personSearchQuery}
                        onChange={(e) => setPersonSearchQuery(e.target.value)}
                        placeholder="Search people..."
                        className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-primary-500"
                        autoFocus
                      />
                    </div>
                  </div>
                  
                  {/* People list */}
                  <div className="overflow-y-auto max-h-48">
                    {isPeopleLoading ? (
                      <div className="p-3 text-center text-gray-500 text-sm">
                        Loading...
                      </div>
                    ) : filteredPeople.length > 0 ? (
                      filteredPeople.map((person) => (
                        <button
                          key={person.id}
                          type="button"
                          onClick={() => {
                            setSelectedPersonId(String(person.id));
                            setIsPersonDropdownOpen(false);
                            setPersonSearchQuery('');
                          }}
                          className={`w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 transition-colors ${
                            selectedPersonId === String(person.id) ? 'bg-primary-50' : ''
                          }`}
                        >
                          {person.thumbnail ? (
                            <img
                              src={person.thumbnail}
                              alt={person.name}
                              className="w-6 h-6 rounded-full object-cover"
                            />
                          ) : (
                            <div className="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center">
                              <User className="w-4 h-4 text-gray-500" />
                            </div>
                          )}
                          <span className="text-sm text-gray-900 truncate">{person.name}</span>
                        </button>
                      ))
                    ) : (
                      <div className="p-3 text-center text-gray-500 text-sm">
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
            <label htmlFor="todo-content" className="block text-sm font-medium text-gray-700 mb-2">
              Description <span className="text-red-500">*</span>
            </label>
            <textarea
              id="todo-content"
              value={content}
              onChange={(e) => setContent(e.target.value)}
              rows={4}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              placeholder="What needs to be done?"
              disabled={createTodo.isPending}
            />
          </div>
          
          {/* Due date */}
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
              disabled={createTodo.isPending}
            />
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
              disabled={createTodo.isPending || !content.trim() || !selectedPersonId}
            >
              {createTodo.isPending ? 'Adding...' : 'Add todo'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

