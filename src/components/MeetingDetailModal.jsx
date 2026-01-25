import { useState, useEffect, useCallback, useRef, lazy, Suspense } from 'react';
import { Link } from 'react-router-dom';
import { X, MapPin, Video, Clock, User, ChevronDown, UserPlus, ExternalLink, Calendar } from 'lucide-react';
import { format } from 'date-fns';
import RichTextEditor from '@/components/RichTextEditor';
import { useMeetingNotes, useUpdateMeetingNotes } from '@/hooks/useMeetings';
import { useCreatePerson, useAddEmailToPerson } from '@/hooks/usePeople';
import AddAttendeePopup from '@/components/AddAttendeePopup';

const PersonEditModal = lazy(() => import('@/components/PersonEditModal'));

/**
 * Extract first/last name from attendee data
 * Handles both display names ("John Doe") and email-only ("john.doe@example.com")
 */
function extractNameFromAttendee(attendee) {
  // Prefer explicit name over email-derived name
  if (attendee.name && !attendee.name.includes('@')) {
    const parts = attendee.name.trim().split(/\s+/);
    return {
      first_name: parts[0] || '',
      last_name: parts.slice(1).join(' ') || '',
    };
  }

  // Fall back to email local part
  if (attendee.email) {
    const localPart = attendee.email.split('@')[0];
    // Handle john.doe, john_doe, john-doe patterns
    const nameParts = localPart.split(/[._-]/).map(
      part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase()
    );
    return {
      first_name: nameParts[0] || '',
      last_name: nameParts.slice(1).join(' ') || '',
    };
  }

  return { first_name: '', last_name: '' };
}

export default function MeetingDetailModal({ isOpen, onClose, meeting }) {
  // State for notes
  const [notes, setNotes] = useState('');
  const [showNotes, setShowNotes] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);

  // Person creation state
  const [showPersonModal, setShowPersonModal] = useState(false);
  const [personPrefill, setPersonPrefill] = useState(null);

  // Popup state - which attendee has the popup open
  const [popupAttendee, setPopupAttendee] = useState(null);

  // Local attendee state - allows optimistic updates when email is added
  const [localAttendees, setLocalAttendees] = useState(null);

  // Track which attendee email we're creating a person for (ref to avoid closure issues)
  const creatingForEmailRef = useRef(null);

  // Reset local attendees when meeting changes
  useEffect(() => {
    setLocalAttendees(null);
  }, [meeting?.id]);

  const createPersonMutation = useCreatePerson({
    onSuccess: (createdPerson) => {
      // Update local attendees to show the newly created person as matched
      const emailToMatch = creatingForEmailRef.current;
      if (emailToMatch) {
        setLocalAttendees(current => {
          const attendees = current || meeting?.attendees || [];
          return attendees.map(att =>
            att.email?.toLowerCase() === emailToMatch.toLowerCase()
              ? {
                  ...att,
                  matched: true,
                  person_id: createdPerson.id,
                  person_name: `${createdPerson.acf?.first_name || ''} ${createdPerson.acf?.last_name || ''}`.trim(),
                  thumbnail: createdPerson._embedded?.['wp:featuredmedia']?.[0]?.source_url || null,
                }
              : att
          );
        });
        creatingForEmailRef.current = null;
      }
      setShowPersonModal(false);
      setPersonPrefill(null);
    },
  });

  const addEmailMutation = useAddEmailToPerson();

  // Handle add person button click - opens the choice popup
  const handleAddPerson = (attendee) => {
    setPopupAttendee(attendee);
  };

  // Handle "Create new person" from popup
  const handleCreateNew = () => {
    const { first_name, last_name } = extractNameFromAttendee(popupAttendee);
    const emailForCreation = popupAttendee.email || '';
    creatingForEmailRef.current = emailForCreation;
    setPersonPrefill({
      first_name,
      last_name,
      email: emailForCreation,
    });
    setPopupAttendee(null);
    setShowPersonModal(true);
  };

  // Handle selecting existing person from popup search
  const handleSelectPerson = async (person) => {
    if (!popupAttendee?.email) return;

    const emailToAdd = popupAttendee.email;

    await addEmailMutation.mutateAsync({
      personId: person.id,
      email: emailToAdd,
    });

    // Update local attendees to show the person as matched
    const currentAttendees = localAttendees || meeting?.attendees || [];
    setLocalAttendees(currentAttendees.map(att =>
      att.email?.toLowerCase() === emailToAdd.toLowerCase()
        ? {
            ...att,
            matched: true,
            person_id: person.id,
            person_name: person.name,
            thumbnail: person.thumbnail,
          }
        : att
    ));

    setPopupAttendee(null);
  };

  // Handle person creation submit
  const handleCreatePerson = async (data) => {
    await createPersonMutation.mutateAsync(data);
  };

  // Fetch existing notes
  const { data: notesData, isLoading: isLoadingNotes } = useMeetingNotes(meeting?.id);
  const updateNotes = useUpdateMeetingNotes();

  // Initialize notes from fetched data
  useEffect(() => {
    if (notesData?.notes !== undefined) {
      setNotes(notesData.notes);
      setShowNotes(!!notesData.notes); // Auto-expand if notes exist
    }
  }, [notesData]);

  // Reset state when modal closes
  useEffect(() => {
    if (!isOpen) {
      setHasChanges(false);
      setPopupAttendee(null);
    }
  }, [isOpen]);

  // Handle notes change with debounced auto-save
  const handleNotesChange = useCallback((value) => {
    setNotes(value);
    setHasChanges(true);
  }, []);

  // Save notes on blur
  const handleNotesSave = useCallback(() => {
    if (hasChanges && meeting?.id) {
      updateNotes.mutate({ eventId: meeting.id, notes });
      setHasChanges(false);
    }
  }, [hasChanges, meeting?.id, notes, updateNotes]);

  if (!isOpen || !meeting) return null;

  // Parse times
  const startDate = new Date(meeting.start_time);
  const endDate = new Date(meeting.end_time);

  // Calculate duration in minutes
  const durationMs = endDate - startDate;
  const durationMinutes = Math.round(durationMs / (1000 * 60));
  const durationHours = Math.floor(durationMinutes / 60);
  const durationMins = durationMinutes % 60;

  let durationText = '';
  if (durationHours > 0 && durationMins > 0) {
    durationText = `${durationHours}h ${durationMins}m`;
  } else if (durationHours > 0) {
    durationText = durationHours === 1 ? '1 hour' : `${durationHours} hours`;
  } else {
    durationText = `${durationMins} min`;
  }

  // Format time display
  const dateStr = format(startDate, 'EEEE, MMMM d, yyyy');
  const timeStr = meeting.all_day
    ? 'All day'
    : `${format(startDate, 'h:mm a')} - ${format(endDate, 'h:mm a')} (${durationText})`;

  // Sort attendees: matched first, then alphabetically
  // Use localAttendees if available (after email was added), otherwise use meeting data
  // Filter out the current user's linked person
  const currentUserPersonId = window.stadionConfig?.currentUserPersonId;
  const attendeesSource = localAttendees || meeting.attendees || [];
  const filteredAttendees = attendeesSource.filter(
    att => !currentUserPersonId || !att.person_id || att.person_id !== currentUserPersonId
  );
  const sortedAttendees = [...filteredAttendees].sort((a, b) => {
    if (a.matched !== b.matched) return b.matched - a.matched;
    return (a.name || a.email || '').localeCompare(b.name || b.email || '');
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50 pr-4">{meeting.title}</h2>
          <div className="flex items-center gap-2 flex-shrink-0">
            {meeting.google_calendar_link && (
              <a
                href={meeting.google_calendar_link}
                target="_blank"
                rel="noopener noreferrer"
                className="text-gray-400 hover:text-accent-600 dark:hover:text-accent-400"
                title="Open in Google Calendar"
              >
                <ExternalLink className="w-5 h-5" />
              </a>
            )}
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Content - scrollable */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* Date and time */}
          <div className="flex items-start gap-3">
            <Clock className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
            <div>
              <p className="text-sm font-medium text-gray-900 dark:text-gray-50">{dateStr}</p>
              <p className="text-sm text-gray-600 dark:text-gray-400">{timeStr}</p>
            </div>
          </div>

          {/* Calendar */}
          {meeting.calendar_name && (
            <div className="flex items-start gap-3">
              <Calendar className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
              <p className="text-sm text-gray-600 dark:text-gray-400">{meeting.calendar_name}</p>
            </div>
          )}

          {/* Location */}
          {meeting.location && (
            <div className="flex items-start gap-3">
              <MapPin className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
              <p className="text-sm text-gray-600 dark:text-gray-400">{meeting.location}</p>
            </div>
          )}

          {/* Meeting URL */}
          {meeting.meeting_url && (
            <div className="flex items-start gap-3">
              <Video className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
              <a
                href={meeting.meeting_url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-sm text-accent-600 dark:text-accent-400 hover:underline break-all"
              >
                Join meeting
              </a>
            </div>
          )}

          {/* Description from calendar */}
          {meeting.description && (
            <div>
              <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</p>
              <div
                className="text-sm text-gray-600 dark:text-gray-400 prose prose-sm dark:prose-invert max-w-none"
                dangerouslySetInnerHTML={{ __html: meeting.description }}
              />
            </div>
          )}

          {/* Attendees */}
          {sortedAttendees.length > 0 && (
            <div>
              <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Attendees ({sortedAttendees.length})
              </p>
              <div className="space-y-1">
                {sortedAttendees.map((attendee, index) => (
                  <AttendeeRow
                    key={attendee.email || index}
                    attendee={attendee}
                    onAddPerson={handleAddPerson}
                    isPopupOpen={popupAttendee?.email === attendee.email}
                    onClosePopup={() => setPopupAttendee(null)}
                    onCreateNew={handleCreateNew}
                    onSelectPerson={handleSelectPerson}
                    isAddingEmail={addEmailMutation.isPending}
                  />
                ))}
              </div>
            </div>
          )}

          {/* Notes section - collapsible */}
          <div>
            <button
              type="button"
              onClick={() => setShowNotes(!showNotes)}
              className="flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 hover:text-gray-900 dark:hover:text-gray-100"
            >
              <ChevronDown className={`w-4 h-4 transition-transform ${showNotes ? '' : '-rotate-90'}`} />
              Meeting notes
              {updateNotes.isPending && <span className="text-xs text-gray-400 ml-2">Saving...</span>}
            </button>
            {showNotes && (
              <RichTextEditor
                value={notes}
                onChange={handleNotesChange}
                onBlur={handleNotesSave}
                placeholder="Add meeting prep notes..."
                disabled={isLoadingNotes}
                minHeight="100px"
              />
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-end">
          <button onClick={onClose} className="btn-secondary">
            Close
          </button>
        </div>
      </div>

      {/* Person creation modal */}
      {showPersonModal && (
        <Suspense fallback={null}>
          <PersonEditModal
            isOpen={showPersonModal}
            onClose={() => {
              setShowPersonModal(false);
              setPersonPrefill(null);
            }}
            onSubmit={handleCreatePerson}
            isLoading={createPersonMutation.isPending}
            prefillData={personPrefill}
          />
        </Suspense>
      )}
    </div>
  );
}

function AttendeeRow({
  attendee,
  onAddPerson,
  isPopupOpen,
  onClosePopup,
  onCreateNew,
  onSelectPerson,
  isAddingEmail
}) {
  const displayName = attendee.person_name || attendee.name || attendee.email || 'Unknown';

  const content = (
    <div className="flex items-center gap-3 py-2">
      {/* Avatar */}
      {attendee.matched && attendee.thumbnail ? (
        <img
          src={attendee.thumbnail}
          alt={displayName}
          className="w-8 h-8 rounded-full object-cover"
        />
      ) : (
        <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
          <User className="w-4 h-4 text-gray-500 dark:text-gray-400" />
        </div>
      )}

      {/* Name and email */}
      <div className="flex-1 min-w-0">
        <p className={`text-sm truncate ${
          attendee.matched
            ? 'font-medium text-accent-600 dark:text-accent-400'
            : 'text-gray-600 dark:text-gray-400'
        }`}>
          {displayName}
        </p>
        {attendee.matched && attendee.email && attendee.email !== displayName && (
          <p className="text-xs text-gray-500 dark:text-gray-500 truncate">{attendee.email}</p>
        )}
      </div>

      {/* Add button for unmatched attendees */}
      {!attendee.matched && onAddPerson && (
        <button
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            onAddPerson(attendee);
          }}
          className="flex-shrink-0 p-1.5 text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
          title="Add as contact"
        >
          <UserPlus className="w-4 h-4" />
        </button>
      )}
    </div>
  );

  // Wrap in Link only if matched
  if (attendee.matched && attendee.person_id) {
    return (
      <Link
        to={`/people/${attendee.person_id}`}
        className="block rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors px-2 -mx-2"
      >
        {content}
      </Link>
    );
  }

  // For unmatched attendees, wrap with relative positioning for popup
  return (
    <div className="relative px-2 -mx-2">
      {content}

      {/* Choice/search popup */}
      {isPopupOpen && (
        <AddAttendeePopup
          isOpen={true}
          onClose={onClosePopup}
          onCreateNew={onCreateNew}
          onSelectPerson={onSelectPerson}
          attendee={attendee}
          isLoading={isAddingEmail}
        />
      )}
    </div>
  );
}
