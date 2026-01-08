import { useState, useEffect } from 'react';
import { X, Phone, Mail, Users, Coffee, Utensils, FileText, Circle, MessageCircle } from 'lucide-react';
import { usePeople } from '@/hooks/usePeople';

const ACTIVITY_TYPES = [
  { id: 'call', label: 'Phone call', icon: Phone },
  { id: 'email', label: 'Email', icon: Mail },
  { id: 'chat', label: 'Chat', icon: MessageCircle },
  { id: 'meeting', label: 'Meeting', icon: Users },
  { id: 'coffee', label: 'Coffee', icon: Coffee },
  { id: 'lunch', label: 'Lunch', icon: Utensils },
  { id: 'note', label: 'Other', icon: FileText },
];

export default function QuickActivityModal({ isOpen, onClose, onSubmit, isLoading, personId, initialData = null, activity = null }) {
  const [activityType, setActivityType] = useState('call');
  const [activityDate, setActivityDate] = useState('');
  const [activityTime, setActivityTime] = useState('');
  const [content, setContent] = useState('');
  const [selectedParticipants, setSelectedParticipants] = useState([]);
  const [showParticipantSelect, setShowParticipantSelect] = useState(false);
  const [participantSearch, setParticipantSearch] = useState('');

  const { data: allPeople } = usePeople();
  
  // Determine if we're in edit mode
  const isEditing = !!activity;

  useEffect(() => {
    if (isOpen) {
      // Set default date to today and current time
      const now = new Date();
      const today = now.toISOString().split('T')[0];
      const currentTime = now.toTimeString().slice(0, 5); // HH:MM format
      
      // If editing an existing activity, use its data
      if (activity) {
        setActivityDate(activity.activity_date || today);
        setActivityTime(activity.activity_time || '');
        setContent(activity.content || '');
        setSelectedParticipants(activity.participants || []);
        setActivityType(activity.activity_type || 'note');
      }
      // If initial data is provided (e.g., from todo conversion), use it to prefill the form
      else if (initialData) {
        setActivityDate(initialData.activity_date || today);
        setActivityTime(initialData.activity_time || currentTime);
        setContent(initialData.content || '');
        setSelectedParticipants(initialData.participants || []);
        setActivityType(initialData.activity_type || 'note');
      } else {
        setActivityDate(today);
        setActivityTime(currentTime);
        setContent('');
        setSelectedParticipants([]);
        setActivityType('call');
      }
    }
  }, [isOpen, initialData, activity]);

  if (!isOpen) return null;

  // Filter people for participant search (exclude current person)
  const availablePeople = (allPeople || []).filter(
    person => person.id.toString() !== personId?.toString()
  );

  const filteredPeople = availablePeople.filter(person =>
    person.name?.toLowerCase().includes(participantSearch.toLowerCase())
  );

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!content.trim()) return;
    
    onSubmit({
      activity_type: activityType,
      activity_date: activityDate || null,
      activity_time: activityTime || null,
      content: content.trim(),
      participants: selectedParticipants,
    });
    
    setContent('');
    setSelectedParticipants([]);
    setActivityDate(new Date().toISOString().split('T')[0]);
    setActivityTime(new Date().toTimeString().slice(0, 5));
  };

  const handleClose = () => {
    setContent('');
    setSelectedParticipants([]);
    setActivityDate('');
    setActivityTime('');
    setShowParticipantSelect(false);
    setParticipantSearch('');
    onClose();
  };

  const toggleParticipant = (personId) => {
    setSelectedParticipants(prev =>
      prev.includes(personId)
        ? prev.filter(id => id !== personId)
        : [...prev, personId]
    );
  };

  const removeParticipant = (personId) => {
    setSelectedParticipants(prev => prev.filter(id => id !== personId));
  };

  const getSelectedPeople = () => {
    return availablePeople.filter(p => selectedParticipants.includes(p.id));
  };

  const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    try {
      const date = new Date(dateString);
      return date.toISOString().split('T')[0];
    } catch {
      return '';
    }
  };

  const selectedType = ACTIVITY_TYPES.find(t => t.id === activityType) || ACTIVITY_TYPES[0];
  const SelectedIcon = selectedType.icon;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-4 border-b sticky top-0 bg-white z-10">
          <h2 className="text-lg font-semibold">{isEditing ? 'Edit activity' : 'Add activity'}</h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4">
          {/* Quick activity type buttons */}
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Activity type
            </label>
            <div className="grid grid-cols-3 gap-2">
              {ACTIVITY_TYPES.map((type) => {
                const Icon = type.icon;
                const isSelected = activityType === type.id;
                return (
                  <button
                    key={type.id}
                    type="button"
                    onClick={() => setActivityType(type.id)}
                    className={`flex flex-col items-center justify-center p-3 border-2 rounded-lg transition-colors ${
                      isSelected
                        ? 'border-primary-600 bg-primary-50 text-primary-700'
                        : 'border-gray-200 hover:border-gray-300 text-gray-700'
                    }`}
                    disabled={isLoading}
                  >
                    <Icon className="w-5 h-5 mb-1" />
                    <span className="text-xs">{type.label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Date and time picker */}
          <div className="mb-4 grid grid-cols-2 gap-3">
            <div>
              <label htmlFor="activity-date" className="block text-sm font-medium text-gray-700 mb-2">
                Date
              </label>
              <input
                id="activity-date"
                type="date"
                value={formatDateForInput(activityDate)}
                onChange={(e) => setActivityDate(e.target.value || '')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                disabled={isLoading}
              />
            </div>
            <div>
              <label htmlFor="activity-time" className="block text-sm font-medium text-gray-700 mb-2">
                Time
              </label>
              <input
                id="activity-time"
                type="time"
                value={activityTime}
                onChange={(e) => setActivityTime(e.target.value || '')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                disabled={isLoading}
              />
            </div>
          </div>

          {/* Description */}
          <div className="mb-4">
            <label htmlFor="activity-content" className="block text-sm font-medium text-gray-700 mb-2">
              Description
            </label>
            <textarea
              id="activity-content"
              value={content}
              onChange={(e) => setContent(e.target.value)}
              rows={4}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              placeholder="What happened?"
              disabled={isLoading}
              autoFocus
            />
          </div>

          {/* Participants */}
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Participants (optional)
            </label>
            
            {/* Selected participants */}
            {getSelectedPeople().length > 0 && (
              <div className="flex flex-wrap gap-2 mb-2">
                {getSelectedPeople().map((person) => (
                  <span
                    key={person.id}
                    className="inline-flex items-center gap-1 px-2 py-1 bg-primary-100 text-primary-700 rounded-full text-sm"
                  >
                    {person.name}
                    <button
                      type="button"
                      onClick={() => removeParticipant(person.id)}
                      className="hover:text-primary-900"
                      disabled={isLoading}
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                ))}
              </div>
            )}

            {/* Participant selector */}
            <div className="relative">
              <button
                type="button"
                onClick={() => setShowParticipantSelect(!showParticipantSelect)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-left text-sm text-gray-500 hover:border-gray-400"
                disabled={isLoading}
              >
                {selectedParticipants.length === 0 ? 'Add participants...' : 'Add more participants...'}
              </button>
              
              {showParticipantSelect && (
                <div className="absolute z-20 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div className="p-2 border-b">
                    <input
                      type="text"
                      placeholder="Search people..."
                      value={participantSearch}
                      onChange={(e) => setParticipantSearch(e.target.value)}
                      className="w-full px-2 py-1 text-sm border border-gray-300 rounded"
                      autoFocus
                    />
                  </div>
                  <div className="max-h-48 overflow-y-auto">
                    {filteredPeople.length === 0 ? (
                      <div className="p-3 text-sm text-gray-500 text-center">
                        No people found
                      </div>
                    ) : (
                      filteredPeople.map((person) => {
                        const isSelected = selectedParticipants.includes(person.id);
                        return (
                          <button
                            key={person.id}
                            type="button"
                            onClick={() => toggleParticipant(person.id)}
                            className={`w-full px-3 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2 ${
                              isSelected ? 'bg-primary-50' : ''
                            }`}
                          >
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => {}}
                              className="mr-1"
                            />
                            {person.thumbnail ? (
                              <img
                                src={person.thumbnail}
                                alt={person.name}
                                className="w-6 h-6 rounded-full"
                              />
                            ) : (
                              <div className="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center">
                                <span className="text-xs text-gray-500">
                                  {person.first_name?.[0] || '?'}
                                </span>
                              </div>
                            )}
                            <span>{person.name}</span>
                          </button>
                        );
                      })
                    )}
                  </div>
                </div>
              )}
            </div>
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
              disabled={isLoading || !content.trim()}
            >
              {isLoading ? (isEditing ? 'Saving...' : 'Adding...') : (isEditing ? 'Save' : 'Add activity')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

