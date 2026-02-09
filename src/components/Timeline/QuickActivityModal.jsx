import { useState, useEffect, lazy, Suspense } from 'react';
import { X, Phone, Mail, Users, Coffee, Utensils, FileText, Circle, MessageCircle, Video } from 'lucide-react';
import { usePeople } from '@/hooks/usePeople';
import { isRichTextEmpty } from '@/utils/richTextUtils';

const RichTextEditor = lazy(() => import('@/components/RichTextEditor'));

const ACTIVITY_TYPES = [
  { id: 'email', label: 'Email', icon: Mail },
  { id: 'chat', label: 'Chat', icon: MessageCircle },
  { id: 'call', label: 'Telefoon', icon: Phone },
  { id: 'video', label: 'Videogesprek', icon: Video },
  { id: 'meeting', label: 'Vergadering', icon: Users },
  { id: 'coffee', label: 'Koffie', icon: Coffee },
  { id: 'lunch', label: 'Lunch', icon: Utensils },
  { id: 'dinner', label: 'Diner', icon: Utensils },
  { id: 'note', label: 'Overig', icon: FileText },
];

export default function QuickActivityModal({ isOpen, onClose, onSubmit, isLoading, personId, initialData = null, activity = null }) {
  const [activityType, setActivityType] = useState('call');
  const [activityDate, setActivityDate] = useState('');
  const [activityTime, setActivityTime] = useState('');
  const [content, setContent] = useState('');
  const [selectedParticipants, setSelectedParticipants] = useState([]);
  const [showParticipantSelect, setShowParticipantSelect] = useState(false);
  const [participantSearch, setParticipantSearch] = useState('');

  const { data: allPeople } = usePeople({}, { enabled: isOpen });
  
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
    if (isRichTextEmpty(content)) return;
    
    onSubmit({
      activity_type: activityType,
      activity_date: activityDate || null,
      activity_time: activityTime || null,
      content: content,
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
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Activiteit bewerken' : 'Activiteit toevoegen'}</h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {/* Left column - 1/3 width on desktop */}
            <div className="space-y-4 md:col-span-1">
              {/* Quick activity type buttons */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Type activiteit
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
                            ? 'border-electric-cyan bg-cyan-50 dark:bg-deep-midnight text-bright-cobalt dark:text-cyan-100'
                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 text-gray-700 dark:text-gray-300'
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
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label htmlFor="activity-date" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Datum
                  </label>
                  <input
                    id="activity-date"
                    type="date"
                    value={formatDateForInput(activityDate)}
                    onChange={(e) => setActivityDate(e.target.value || '')}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-electric-cyan focus:border-transparent"
                    disabled={isLoading}
                  />
                </div>
                <div>
                  <label htmlFor="activity-time" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tijd
                  </label>
                  <input
                    id="activity-time"
                    type="time"
                    value={activityTime}
                    onChange={(e) => setActivityTime(e.target.value || '')}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-electric-cyan focus:border-transparent"
                    disabled={isLoading}
                  />
                </div>
              </div>

              {/* Participants */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Deelnemers (optioneel)
                </label>

                {/* Selected participants */}
                {getSelectedPeople().length > 0 && (
                  <div className="flex flex-wrap gap-2 mb-2">
                    {getSelectedPeople().map((person) => (
                      <span
                        key={person.id}
                        className="inline-flex items-center gap-1 px-2 py-1 bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light rounded-full text-sm"
                      >
                        {person.name}
                        <button
                          type="button"
                          onClick={() => removeParticipant(person.id)}
                          className="hover:text-obsidian dark:hover:text-cyan-100"
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
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-left text-sm text-gray-500 dark:text-gray-400 hover:border-gray-400 dark:hover:border-gray-500"
                    disabled={isLoading}
                  >
                    {selectedParticipants.length === 0 ? 'Deelnemers toevoegen...' : 'Meer deelnemers toevoegen...'}
                  </button>

                  {showParticipantSelect && (
                    <div className="absolute z-20 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                      <div className="p-2 border-b border-gray-200 dark:border-gray-700">
                        <input
                          type="text"
                          placeholder="Personen zoeken..."
                          value={participantSearch}
                          onChange={(e) => setParticipantSearch(e.target.value)}
                          className="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50"
                          autoFocus
                        />
                      </div>
                      <div className="max-h-48 overflow-y-auto">
                        {filteredPeople.length === 0 ? (
                          <div className="p-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                            Geen personen gevonden
                          </div>
                        ) : (
                          filteredPeople.map((person) => {
                            const isSelected = selectedParticipants.includes(person.id);
                            return (
                              <button
                                key={person.id}
                                type="button"
                                onClick={() => toggleParticipant(person.id)}
                                className={`w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 text-gray-900 dark:text-gray-50 ${
                                  isSelected ? 'bg-cyan-50 dark:bg-deep-midnight' : ''
                                }`}
                              >
                                <input
                                  type="checkbox"
                                  checked={isSelected}
                                  onChange={() => {}}
                                  className="mr-1 dark:bg-gray-700"
                                />
                                {person.thumbnail ? (
                                  <img
                                    src={person.thumbnail}
                                    alt={person.name}
                                    className="w-6 h-6 rounded-full"
                                  />
                                ) : (
                                  <div className="w-6 h-6 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">
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
            </div>

            {/* Right column - Description - 2/3 width on desktop */}
            <div className="flex flex-col md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Beschrijving
              </label>
              <div className="flex-1">
                <Suspense fallback={
                  <div className="border border-gray-300 dark:border-gray-600 rounded-md p-3 min-h-[280px] animate-pulse bg-gray-100 dark:bg-gray-700" />
                }>
                  <RichTextEditor
                    value={content}
                    onChange={setContent}
                    placeholder="Wat is er gebeurd? Voeg je gespreksnotities toe..."
                    disabled={isLoading}
                    minHeight="280px"
                  />
                </Suspense>
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="button"
              onClick={handleClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Annuleren
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading || isRichTextEmpty(content)}
            >
              {isLoading ? (isEditing ? 'Opslaan...' : 'Toevoegen...') : (isEditing ? 'Opslaan' : 'Activiteit toevoegen')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

