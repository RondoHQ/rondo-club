import { useState, useMemo, useRef, useEffect, lazy, Suspense } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft, Edit, Trash2, Mail, Phone,
  MapPin, Globe, Building2, Calendar, Plus, Gift, Heart, Pencil, MessageCircle, X, Camera, Download,
  CheckSquare2, Square, TrendingUp, StickyNote, Share2, Clock, User, Video, ExternalLink, AlertCircle
} from 'lucide-react';
import { SiFacebook, SiInstagram, SiX, SiBluesky, SiThreads, SiSlack, SiWhatsapp } from '@icons-pack/react-simple-icons';

// Custom LinkedIn SVG component
const LinkedInIcon = ({ className }) => (
  <svg 
    className={className}
    viewBox="0 0 382 382" 
    xmlns="http://www.w3.org/2000/svg"
    fill="currentColor"
  >
    <path d="M347.445,0H34.555C15.471,0,0,15.471,0,34.555v312.889C0,366.529,15.471,382,34.555,382h312.889
      C366.529,382,382,366.529,382,347.444V34.555C382,15.471,366.529,0,347.445,0z M118.207,329.844c0,5.554-4.502,10.056-10.056,10.056
      H65.345c-5.554,0-10.056-4.502-10.056-10.056V150.403c0-5.554,4.502-10.056,10.056-10.056h42.806
      c5.554,0,10.056,4.502,10.056,10.056V329.844z M86.748,123.432c-22.459,0-40.666-18.207-40.666-40.666S64.289,42.1,86.748,42.1
      s40.666,18.207,40.666,40.666S109.208,123.432,86.748,123.432z M341.91,330.654c0,5.106-4.14,9.246-9.246,9.246H286.73
      c-5.106,0-9.246-4.14-9.246-9.246v-84.168c0-12.556,3.683-55.021-32.813-55.021c-28.309,0-34.051,29.066-35.204,42.11v97.079
      c0,5.106-4.139,9.246-9.246,9.246h-44.426c-5.106,0-9.246-4.14-9.246-9.246V149.593c0-5.106,4.14-9.246,9.246-9.246h44.426
      c5.106,0,9.246,4.14,9.246,9.246v15.655c10.497-15.753,26.097-27.912,59.312-27.912c73.552,0,73.131,68.716,73.131,106.472
      L341.91,330.654L341.91,330.654z"/>
  </svg>
);
import { usePerson, usePersonTimeline, usePersonDates, useDeletePerson, useDeleteNote, useDeleteDate, useUpdatePerson, useCreateNote, useCreateActivity, useUpdateActivity, useCreateTodo, useUpdateTodo, useDeleteActivity, useDeleteTodo, usePeople, peopleKeys } from '@/hooks/usePeople';
import { usePersonMeetings, useLogMeetingAsActivity } from '@/hooks/useMeetings';
import TimelineView from '@/components/Timeline/TimelineView';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import NoteModal from '@/components/Timeline/NoteModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';
import TodoModal from '@/components/Timeline/TodoModal';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import ContactEditModal from '@/components/ContactEditModal';
import ImportantDateModal from '@/components/ImportantDateModal';
import RelationshipEditModal from '@/components/RelationshipEditModal';
import AddressEditModal from '@/components/AddressEditModal';
import WorkHistoryEditModal from '@/components/WorkHistoryEditModal';
import PersonEditModal from '@/components/PersonEditModal';
import ShareModal from '@/components/ShareModal';
import CustomFieldsSection from '@/components/CustomFieldsSection';
const MeetingDetailModal = lazy(() => import('@/components/MeetingDetailModal'));
import { format, differenceInYears } from '@/utils/dateFormat';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml, getTeamName, sanitizePersonAcf } from '@/utils/formatters';
import { downloadVCard } from '@/utils/vcard';
import { isTodoOverdue, getAwaitingDays, getAwaitingUrgencyClass } from '@/utils/timeline';
import { stripHtmlTags } from '@/utils/richTextUtils';

// Helper to validate date strings
function isValidDate(dateString) {
  if (!dateString) return false;
  const date = new Date(dateString);
  return date instanceof Date && !isNaN(date.getTime());
}

// Helper to get gender symbol
function getGenderSymbol(gender) {
  if (!gender) return null;
  switch (gender) {
    case 'male':
      return '♂';
    case 'female':
      return '♀';
    case 'non_binary':
    case 'other':
    case 'prefer_not_to_say':
      return '⚧';
    default:
      return null;
  }
}

// Helper to get initials from a name or email
function getInitials(name, email) {
  if (name && !name.includes('@')) {
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name[0].toUpperCase();
  }
  if (email) {
    return email[0].toUpperCase();
  }
  return '?';
}

// Helper to calculate VOG status
function getVogStatus(acf) {
  // Check if person has work functions other than "Donateur"
  const werkfuncties = acf?.werkfuncties || [];
  const hasNonDonateurFunction = werkfuncties.some(fn => fn !== 'Donateur');

  if (!hasNonDonateurFunction) {
    return null; // Don't show VOG indicator for Donateurs only
  }

  const vogDate = acf?.vog_datum;
  if (!vogDate) {
    return { status: 'missing', label: 'Geen VOG', color: 'red' };
  }

  const vogDateObj = new Date(vogDate);
  const threeYearsAgo = new Date();
  threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);

  if (vogDateObj >= threeYearsAgo) {
    return { status: 'valid', label: 'VOG OK', color: 'green' };
  } else {
    return { status: 'expired', label: 'VOG verlopen', color: 'orange' };
  }
}

// MeetingCard component for displaying meeting info
function MeetingCard({ meeting, showLogButton, onLog, isLogging, onClick, currentPersonId }) {
  // Format the meeting date/time
  const startDate = meeting.start_time ? new Date(meeting.start_time) : null;
  const endDate = meeting.end_time ? new Date(meeting.end_time) : null;

  // Format time display
  const formatTime = (date) => {
    if (!date) return '';
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  };

  const formatDate = (date) => {
    if (!date) return '';
    return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  };

  const isLowConfidence = meeting.confidence && meeting.confidence < 80;

  return (
    <div
      className={`flex items-start p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 ${onClick ? 'cursor-pointer hover:border-accent-300 dark:hover:border-accent-600 transition-colors' : ''}`}
      onClick={onClick ? () => onClick(meeting) : undefined}
    >
      {/* Calendar icon */}
      <div className="flex-shrink-0 mr-3">
        <div className="w-10 h-10 rounded-lg bg-accent-100 dark:bg-accent-800 flex items-center justify-center">
          <Calendar className="w-5 h-5 text-accent-600 dark:text-accent-100" />
        </div>
      </div>

      {/* Meeting details */}
      <div className="flex-1 min-w-0">
        <h3 className="font-medium text-gray-900 dark:text-gray-100 truncate">
          {meeting.title}
        </h3>

        {/* Date and time */}
        {startDate && (
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {formatDate(startDate)}
            {!meeting.all_day && (
              <>
                {' '}{formatTime(startDate)}
                {endDate && <> - {formatTime(endDate)}</>}
              </>
            )}
            {meeting.all_day && ' (Hele dag)'}
          </p>
        )}

        {/* Location or meeting URL */}
        {(meeting.location || meeting.meeting_url) && (
          <div className="flex items-center gap-1 mt-1">
            {meeting.meeting_url ? (
              <a
                href={meeting.meeting_url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-sm text-accent-600 dark:text-accent-400 hover:underline flex items-center gap-1"
              >
                <Video className="w-3.5 h-3.5" />
                <span className="truncate">{meeting.location || 'Video meeting'}</span>
                <ExternalLink className="w-3 h-3 flex-shrink-0" />
              </a>
            ) : (
              <span className="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <MapPin className="w-3.5 h-3.5" />
                <span className="truncate">{meeting.location}</span>
              </span>
            )}
          </div>
        )}

        {/* Calendar name badge */}
        {meeting.calendar_name && (
          <span className="inline-block mt-2 text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
            {meeting.calendar_name}
          </span>
        )}

        {/* Other attendees - show photos for matched, initials for unmatched */}
        {meeting.attendees && meeting.attendees.length > 0 && (() => {
          // Filter out the current person being viewed and the current user's linked person
          const currentUserPersonId = window.stadionConfig?.currentUserPersonId;
          const otherAttendees = meeting.attendees.filter(att => {
            if (!att.person_id) return true; // Keep unmatched attendees
            const personId = parseInt(att.person_id);
            // Filter out the person being viewed
            if (personId === parseInt(currentPersonId)) return false;
            // Filter out the current user's linked person
            if (currentUserPersonId && personId === currentUserPersonId) return false;
            return true;
          });
          if (otherAttendees.length === 0) return null;

          const displayedAttendees = otherAttendees.slice(0, 5);
          const remainingCount = otherAttendees.length - 5;

          return (
            <div className="mt-2 flex items-center gap-1">
              <span className="text-xs font-medium text-gray-500 dark:text-gray-400 mr-1">Ook aanwezig:</span>
              <div className="flex -space-x-2">
                {displayedAttendees.map((attendee, index) => {
                  const displayName = attendee.person_name || attendee.name || attendee.email || 'Unknown';
                  const initials = getInitials(attendee.name, attendee.email);

                  const avatar = attendee.matched && attendee.thumbnail ? (
                    <img
                      src={attendee.thumbnail}
                      alt={displayName}
                      className="w-6 h-6 rounded-full object-cover ring-2 ring-white dark:ring-gray-800"
                    />
                  ) : (
                    <div className="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 ring-2 ring-white dark:ring-gray-800 flex items-center justify-center text-xs font-medium text-gray-600 dark:text-gray-300">
                      {initials}
                    </div>
                  );

                  // Wrap matched attendees in a Link
                  if (attendee.matched && attendee.person_id) {
                    return (
                      <Link
                        key={attendee.email || index}
                        to={`/people/${attendee.person_id}`}
                        title={displayName}
                        className="hover:z-10 transition-transform hover:scale-110"
                        onClick={(e) => e.stopPropagation()}
                      >
                        {avatar}
                      </Link>
                    );
                  }

                  return (
                    <div key={attendee.email || index} title={displayName}>
                      {avatar}
                    </div>
                  );
                })}
                {remainingCount > 0 && (
                  <div className="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 ring-2 ring-white dark:ring-gray-800 flex items-center justify-center text-xs font-medium text-gray-500 dark:text-gray-400">
                    +{remainingCount}
                  </div>
                )}
              </div>
            </div>
          );
        })()}

        {/* Low confidence warning */}
        {isLowConfidence && (
          <div className="flex items-center gap-1 mt-2 text-xs text-amber-600 dark:text-amber-400">
            <AlertCircle className="w-3.5 h-3.5" />
            <span>Betrouwbaarheid: {meeting.confidence}%</span>
          </div>
        )}
      </div>

      {/* Log as Activity button */}
      {showLogButton && (
        <div className="flex-shrink-0 ml-2">
          {meeting.logged_as_activity ? (
            <span className="text-xs text-green-600 dark:text-green-400 px-2 py-1 rounded bg-green-50 dark:bg-green-900/20">
              Vastgelegd
            </span>
          ) : (
            <button
              onClick={onLog}
              disabled={isLogging}
              className="text-xs px-3 py-1.5 rounded-md bg-accent-600 hover:bg-accent-700 text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {isLogging ? 'Vastleggen...' : 'Activiteit vastleggen'}
            </button>
          )}
        </div>
      )}
    </div>
  );
}

export default function PersonDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { data: person, isLoading, error } = usePerson(id);
  const { data: timeline } = usePersonTimeline(id);
  const { data: personDates } = usePersonDates(id);
  const deletePerson = useDeletePerson();
  const deleteNote = useDeleteNote();
  const deleteDate = useDeleteDate();
  const updatePerson = useUpdatePerson();
  const createNote = useCreateNote();
  const createActivity = useCreateActivity();
  const updateActivity = useUpdateActivity();
  const createTodo = useCreateTodo();
  const updateTodo = useUpdateTodo();
  const deleteActivity = useDeleteActivity();
  const deleteTodo = useDeleteTodo();
  const { data: allPeople, isLoading: isPeopleLoading } = usePeople();

  const handleRefresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['people', 'detail', id] }),
      queryClient.invalidateQueries({ queryKey: ['people', id, 'timeline'] }),
    ]);
  };

  // Fetch teams where this person is an investor
  const { data: investments = [] } = useQuery({
    queryKey: ['investments', id],
    queryFn: async () => {
      const response = await prmApi.getInvestments(id);
      return response.data;
    },
    enabled: !!id,
  });

  // Fetch meetings for this person
  const { data: meetings, isLoading: meetingsLoading } = usePersonMeetings(id);
  const logMeeting = useLogMeetingAsActivity();

  const [activeTab, setActiveTab] = useState('profile');
  const [isAddingLabel, setIsAddingLabel] = useState(false);
  const [selectedLabelToAdd, setSelectedLabelToAdd] = useState('');
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false);
  const fileInputRef = useRef(null);
  
  // Modal states
  const [showNoteModal, setShowNoteModal] = useState(false);
  const [showActivityModal, setShowActivityModal] = useState(false);
  const [showTodoModal, setShowTodoModal] = useState(false);
  const [showContactModal, setShowContactModal] = useState(false);
  const [showDateModal, setShowDateModal] = useState(false);
  const [showRelationshipModal, setShowRelationshipModal] = useState(false);
  const [showAddressModal, setShowAddressModal] = useState(false);
  const [showWorkHistoryModal, setShowWorkHistoryModal] = useState(false);
  const [showPersonEditModal, setShowPersonEditModal] = useState(false);
  const [showShareModal, setShowShareModal] = useState(false);
  const [isSavingContacts, setIsSavingContacts] = useState(false);
  const [isSavingDate, setIsSavingDate] = useState(false);
  const [isSavingRelationship, setIsSavingRelationship] = useState(false);
  const [isSavingAddress, setIsSavingAddress] = useState(false);
  const [isSavingWorkHistory, setIsSavingWorkHistory] = useState(false);
  const [isSavingPerson, setIsSavingPerson] = useState(false);
  const [editingTodo, setEditingTodo] = useState(null);
  const [editingActivity, setEditingActivity] = useState(null);
  const [editingDate, setEditingDate] = useState(null);
  const [editingRelationship, setEditingRelationship] = useState(null);
  const [editingRelationshipIndex, setEditingRelationshipIndex] = useState(null);
  const [editingAddress, setEditingAddress] = useState(null);
  const [editingAddressIndex, setEditingAddressIndex] = useState(null);
  const [editingWorkHistory, setEditingWorkHistory] = useState(null);
  const [editingWorkHistoryIndex, setEditingWorkHistoryIndex] = useState(null);
  
  // Complete todo flow states
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);

  // Mobile todos panel state
  const [showMobileTodos, setShowMobileTodos] = useState(false);

  // Meeting logging state
  const [loggingMeetingId, setLoggingMeetingId] = useState(null);

  // Meeting detail modal state
  const [selectedMeeting, setSelectedMeeting] = useState(null);
  const [showMeetingModal, setShowMeetingModal] = useState(false);

  // Fetch available labels
  const { data: availableLabelsData } = useQuery({
    queryKey: ['person-labels'],
    queryFn: async () => {
      const response = await wpApi.getPersonLabels();
      return response.data;
    },
  });
  
  const availableLabels = availableLabelsData || [];
  const currentLabelNames = person?.labels || [];
  
  // Get current label term IDs from embedded terms
  const currentLabelTermIds = person?._embedded?.['wp:term']?.flat()
    ?.filter(term => term?.taxonomy === 'person_label')
    ?.map(term => term.id) || [];
  
  const availableLabelsToAdd = availableLabels.filter(
    label => !currentLabelNames.includes(label.name)
  );
  
  // Update document title with person's name - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(person?.name || person?.title?.rendered || person?.title || 'Lid');

  // Find birthday from person dates
  // date_type is an array of term names from the API
  const birthday = personDates?.find(d => {
    const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
    return dateType?.toLowerCase() === 'birthday';
  });
  // The API returns date_value, not date
  const birthDate = birthday?.date_value ? new Date(birthday.date_value) : null;
  const birthdayYearUnknown = birthday?.year_unknown ?? false;

  // Find death date from person dates
  const deathDate = personDates?.find(d => {
    const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
    return dateType?.toLowerCase() === 'died';
  });
  const deathDateValue = deathDate?.date_value ? new Date(deathDate.date_value) : null;
  const deathYearUnknown = deathDate?.year_unknown ?? false;
  
  // Calculate age - if died, show age at death, otherwise current age
  // Skip age calculation if birthday year is unknown
  const isDeceased = !!deathDateValue;
  const age = (birthDate && !birthdayYearUnknown) ? (isDeceased 
    ? differenceInYears(deathDateValue, birthDate)
    : differenceInYears(new Date(), birthDate)
  ) : null;

  // Get all dates including birthday (for Important Dates card)
  // Sort by date ascending
  const allDates = personDates ? [...personDates].sort((a, b) => {
    const dateA = a.date_value ? new Date(a.date_value) : new Date(0);
    const dateB = b.date_value ? new Date(b.date_value) : new Date(0);
    // Sort by date ascending (earliest first)
    return dateA - dateB;
  }) : [];
  
  const handleDelete = async () => {
    if (!window.confirm('Weet je zeker dat je dit lid wilt verwijderen?')) {
      return;
    }

    try {
      await deletePerson.mutateAsync(id);
      // Navigation will happen in onSuccess callback
      navigate('/people');
    } catch {
      alert('Lid kon niet worden verwijderd. Probeer het opnieuw.');
    }
  };

  // Helper function to format phone number for tel: and WhatsApp links
  // Removes all non-digit characters except + at the start, removes Unicode marks,
  // and converts Dutch mobile numbers (06...) to international format (+316...)
  const formatPhoneForTel = (phone) => {
    if (!phone) return '';
    // Remove all Unicode marks and invisible characters
    let cleaned = phone.replace(/[\u200B-\u200D\uFEFF\u200E\u200F\u202A-\u202E]/g, '');
    // Extract + if present at the start
    const hasPlus = cleaned.startsWith('+');
    // Remove all non-digit characters
    cleaned = cleaned.replace(/\D/g, '');
    // Convert Dutch mobile numbers (06...) to international format (+316...)
    if (!hasPlus && cleaned.startsWith('06')) {
      return `+316${cleaned.slice(2)}`;
    }
    // Prepend + if it was at the start
    return hasPlus ? `+${cleaned}` : cleaned;
  };

  // Handle saving all contacts from modal
  const handleSaveContacts = async (contacts) => {
    setIsSavingContacts(true);
    try {
      const acfData = sanitizePersonAcf(person.acf, {
        contact_info: contacts,
      });
      
      await updatePerson.mutateAsync({
        id,
        data: {
          acf: acfData,
        },
      });
      
      setShowContactModal(false);
    } catch {
      alert('Contacten konden niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingContacts(false);
    }
  };

  // Handle saving an important date from modal
  const handleSaveDate = async (data) => {
    setIsSavingDate(true);
    try {
      if (editingDate) {
        // Update existing date
        await wpApi.updateDate(editingDate.id, {
          title: data.title,
          date_type: data.date_type,
          acf: {
            date_value: data.date_value,
            related_people: data.related_people,
            is_recurring: data.is_recurring,
            year_unknown: data.year_unknown,
          },
        });
      } else {
        // Create new date
        await wpApi.createDate({
          title: data.title,
          status: 'publish',
          date_type: data.date_type,
          acf: {
            date_value: data.date_value,
            related_people: data.related_people,
            is_recurring: data.is_recurring,
            year_unknown: data.year_unknown,
          },
        });
      }
      
      queryClient.invalidateQueries({ queryKey: peopleKeys.dates(id) });
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      setShowDateModal(false);
      setEditingDate(null);
    } catch {
      alert('Datum kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingDate(false);
    }
  };

  // Handle saving a relationship from modal
  const handleSaveRelationship = async (data) => {
    setIsSavingRelationship(true);
    try {
      const relationships = [...(person.acf?.relationships || [])];
      
      const relationshipItem = {
        related_person: data.related_person || null,
        relationship_type: data.relationship_type || null,
        relationship_label: data.relationship_label || '',
      };

      if (editingRelationshipIndex !== null) {
        relationships[editingRelationshipIndex] = relationshipItem;
      } else {
        relationships.push(relationshipItem);
      }

      const acfData = sanitizePersonAcf(person.acf, {
        relationships: relationships,
      });

      await updatePerson.mutateAsync({
        id,
        data: {
          acf: acfData,
        },
      });
      
      queryClient.invalidateQueries({ queryKey: ['person', id] });
      setShowRelationshipModal(false);
      setEditingRelationship(null);
      setEditingRelationshipIndex(null);
    } catch {
      alert('Relatie kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingRelationship(false);
    }
  };

  // Handle saving an address from modal
  const handleSaveAddress = async (data) => {
    setIsSavingAddress(true);
    try {
      const addresses = [...(person.acf?.addresses || [])];
      
      const addressItem = {
        address_label: data.address_label || '',
        street: data.street || '',
        postal_code: data.postal_code || '',
        city: data.city || '',
        state: data.state || '',
        country: data.country || '',
      };

      if (editingAddressIndex !== null) {
        addresses[editingAddressIndex] = addressItem;
      } else {
        addresses.push(addressItem);
      }

      const acfData = sanitizePersonAcf(person.acf, {
        addresses: addresses,
      });

      await updatePerson.mutateAsync({
        id,
        data: {
          acf: acfData,
        },
      });
      
      setShowAddressModal(false);
      setEditingAddress(null);
      setEditingAddressIndex(null);
    } catch {
      alert('Adres kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingAddress(false);
    }
  };

  // Handle saving work history from modal
  const handleSaveWorkHistory = async (data) => {
    setIsSavingWorkHistory(true);
    try {
      const workHistory = [...(person.acf?.work_history || [])];
      
      const workHistoryItem = {
        team: data.team || null,
        entity_type: data.entity_type || null,
        job_title: data.job_title || '',
        description: data.description || '',
        start_date: data.start_date || '',
        end_date: data.end_date || '',
        is_current: data.is_current || false,
      };

      if (editingWorkHistoryIndex !== null) {
        workHistory[editingWorkHistoryIndex] = workHistoryItem;
      } else {
        workHistory.push(workHistoryItem);
      }

      const acfData = sanitizePersonAcf(person.acf, {
        work_history: workHistory,
      });

      await updatePerson.mutateAsync({
        id,
        data: {
          acf: acfData,
        },
      });
      
      setShowWorkHistoryModal(false);
      setEditingWorkHistory(null);
      setEditingWorkHistoryIndex(null);
    } catch {
      alert('Functie kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingWorkHistory(false);
    }
  };

  // Handle saving person details
  const handleSavePerson = async (data) => {
    setIsSavingPerson(true);
    try {
      const acfData = sanitizePersonAcf(person.acf, {
        first_name: data.first_name,
        last_name: data.last_name,
        nickname: data.nickname,
        gender: data.gender || null,
        pronouns: data.pronouns || null,
        how_we_met: data.how_we_met,
      });

      await updatePerson.mutateAsync({
        id,
        data: {
          title: `${data.first_name} ${data.last_name}`.trim(),
          acf: acfData,
        },
      });
      
      setShowPersonEditModal(false);
    } catch {
      alert('Lid kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingPerson(false);
    }
  };

  // Handle deleting an address
  const handleDeleteAddress = async (index) => {
    if (!window.confirm('Weet je zeker dat je dit adres wilt verwijderen?')) {
      return;
    }
    
    const updatedAddresses = [...(person.acf?.addresses || [])];
    updatedAddresses.splice(index, 1);
    
    const acfData = sanitizePersonAcf(person.acf, {
      addresses: updatedAddresses,
    });
    
    await updatePerson.mutateAsync({
      id,
      data: {
        acf: acfData,
      },
    });
  };

  // Handle deleting a relationship
  const handleDeleteRelationship = async (index) => {
    if (!window.confirm('Weet je zeker dat je deze relatie wilt verwijderen?')) {
      return;
    }
    
    const relationshipToDelete = person.acf?.relationships?.[index];
    if (!relationshipToDelete) {
      return;
    }
    
    const relatedPersonId = relationshipToDelete.related_person;
    const relationshipTypeId = relationshipToDelete.relationship_type;
    
    // Ask if inverse should be deleted
    let deleteInverse = true; // Default to true since backend automatically deletes it
    if (relatedPersonId && relationshipTypeId) {
      deleteInverse = window.confirm('Wil je ook de omgekeerde relatie bij de andere persoon verwijderen?');
    }
    
    // If user doesn't want to delete inverse, we need to save it and re-add it after deletion
    let inverseToRestore = null;
    if (!deleteInverse && relatedPersonId && relationshipTypeId) {
      try {
        // Fetch the related person and relationship types to find the inverse
        const [relatedPerson, relationshipTypes] = await Promise.all([
          wpApi.getPerson(relatedPersonId, { _embed: true }),
          wpApi.getRelationshipTypes(),
        ]);
        
        const typeMap = {};
        relationshipTypes.data.forEach(type => {
          typeMap[type.id] = type;
        });
        
        const currentType = typeMap[relationshipTypeId];
        const inverseTypeId = currentType?.acf?.inverse_relationship_type;
        
        if (inverseTypeId) {
          // Find the inverse relationship to save it
          const relatedPersonRelationships = relatedPerson.data.acf?.relationships || [];
          const inverseRel = relatedPersonRelationships.find(rel => {
            const relPersonId = typeof rel.related_person === 'object' ? rel.related_person?.ID : rel.related_person;
            const relTypeId = typeof rel.relationship_type === 'object' ? rel.relationship_type?.term_id : rel.relationship_type;
            return relPersonId === parseInt(id) && relTypeId === inverseTypeId;
          });
          
          if (inverseRel) {
            inverseToRestore = {
              related_person: parseInt(id),
              relationship_type: inverseTypeId,
              relationship_label: inverseRel.relationship_label || '',
            };
          }
        }
      } catch {
        // Continue with deletion anyway
      }
    }
    
    // Delete the relationship from current person
    const updatedRelationships = [...(person.acf?.relationships || [])];
    updatedRelationships.splice(index, 1);
    
    const acfData = sanitizePersonAcf(person.acf, {
      relationships: updatedRelationships,
    });
    
    await updatePerson.mutateAsync({
      id,
      data: {
        acf: acfData,
      },
    });
    
    // If user doesn't want to delete inverse, re-add it after backend sync
    if (!deleteInverse && inverseToRestore && relatedPersonId) {
      // Wait a bit for backend sync to complete
      await new Promise(resolve => setTimeout(resolve, 500));
      
      try {
        // Fetch the related person again to get current state
        const relatedPerson = await wpApi.getPerson(relatedPersonId, { _embed: true });
        const relatedPersonRelationships = relatedPerson.data.acf?.relationships || [];
        
        // Check if inverse was already deleted (it should be)
        const inverseExists = relatedPersonRelationships.some(rel => {
          const relPersonId = typeof rel.related_person === 'object' ? rel.related_person?.ID : rel.related_person;
          const relTypeId = typeof rel.relationship_type === 'object' ? rel.relationship_type?.term_id : rel.relationship_type;
          return relPersonId === inverseToRestore.related_person && relTypeId === inverseToRestore.relationship_type;
        });
        
        // If inverse doesn't exist, re-add it
        if (!inverseExists) {
          const updatedRelatedRelationships = [...relatedPersonRelationships, inverseToRestore];
          const relatedPersonAcfData = sanitizePersonAcf(relatedPerson.data.acf, {
            relationships: updatedRelatedRelationships,
          });
          
          await wpApi.updatePerson(relatedPersonId, {
            acf: relatedPersonAcfData,
          });
        }
      } catch {
        // Don't show error to user - the main relationship was deleted successfully
      }
    }
    
    // Invalidate queries to refresh the UI
    queryClient.invalidateQueries({ queryKey: ['person', id] });
    if (relatedPersonId) {
      queryClient.invalidateQueries({ queryKey: ['person', relatedPersonId] });
    }
    queryClient.invalidateQueries({ queryKey: ['people'] });
  };

  // Handle deleting a date
  const handleDeleteDate = async (dateId) => {
    if (!window.confirm('Weet je zeker dat je deze belangrijke datum wilt verwijderen?')) {
      return;
    }

    await deleteDate.mutateAsync({ dateId, personId: id });
  };

  // Handle deleting a note
  const handleDeleteNote = async (noteId) => {
    if (!window.confirm('Weet je zeker dat je deze notitie wilt verwijderen?')) {
      return;
    }

    await deleteNote.mutateAsync({ noteId, personId: id });
  };

  // Handle creating a note
  const handleCreateNote = async (content, visibility = 'private') => {
    try {
      await createNote.mutateAsync({ personId: id, content, visibility });
      setShowNoteModal(false);
    } catch {
      alert('Notitie kon niet worden aangemaakt. Probeer het opnieuw.');
    }
  };

  // Handle creating or updating an activity
  const handleCreateActivity = async (data) => {
    try {
      if (editingActivity) {
        // Update existing activity
        await updateActivity.mutateAsync({ 
          activityId: editingActivity.id, 
          data, 
          personId: id 
        });
        setEditingActivity(null);
      } else {
        // Create new activity
        await createActivity.mutateAsync({ personId: id, data });
        
        // If we're completing a todo as activity, also mark the todo as complete
        if (todoToComplete) {
          await updateTodo.mutateAsync({
            todoId: todoToComplete.id,
            data: { status: 'completed' },
            personId: id,
          });
          setTodoToComplete(null);
          setActivityInitialData(null);
        }
      }
      
      setShowActivityModal(false);
    } catch {
      alert('Activiteit kon niet worden opgeslagen. Probeer het opnieuw.');
    }
  };

  // Handle creating a todo
  const handleCreateTodo = async (data) => {
    try {
      await createTodo.mutateAsync({ personId: id, data });
      setShowTodoModal(false);
      setEditingTodo(null);
    } catch {
      alert('Taak kon niet worden aangemaakt. Probeer het opnieuw.');
    }
  };

  // Handle updating a todo
  const handleUpdateTodo = async (data) => {
    if (!editingTodo) return;
    
    try {
      await updateTodo.mutateAsync({ todoId: editingTodo.id, data, personId: id });
      setShowTodoModal(false);
      setEditingTodo(null);
    } catch {
      alert('Taak kon niet worden bijgewerkt. Probeer het opnieuw.');
    }
  };

  // Handle toggling todo completion
  const handleToggleTodo = async (todo) => {
    // If it's an open todo, show the complete modal with options
    if (todo.status === 'open') {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
      return;
    }

    // If awaiting, show the complete modal (without awaiting option)
    if (todo.status === 'awaiting') {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
      return;
    }

    // If completed, reopen
    if (todo.status === 'completed') {
      try {
        await updateTodo.mutateAsync({
          todoId: todo.id,
          data: { status: 'open' },
          personId: id,
        });
      } catch {
        alert('Taak kon niet worden heropend. Probeer het opnieuw.');
      }
    }
  };
  
  // Handle marking todo as awaiting response
  const handleMarkAwaiting = async () => {
    if (!todoToComplete) return;

    try {
      await updateTodo.mutateAsync({
        todoId: todoToComplete.id,
        data: { status: 'awaiting' },
        personId: id,
      });

      setShowCompleteModal(false);
      setTodoToComplete(null);
    } catch {
      alert('Taak kon niet worden bijgewerkt. Probeer het opnieuw.');
    }
  };

  // Handle just completing a todo (no activity)
  const handleJustComplete = async () => {
    if (!todoToComplete) return;

    try {
      await updateTodo.mutateAsync({
        todoId: todoToComplete.id,
        data: { status: 'completed' },
        personId: id,
      });

      setShowCompleteModal(false);
      setTodoToComplete(null);
    } catch {
      alert('Taak kon niet worden voltooid. Probeer het opnieuw.');
    }
  };
  
  // Handle completing todo as activity
  const handleCompleteAsActivity = () => {
    if (!todoToComplete) return;

    // Prepare initial data for activity modal
    const today = new Date().toISOString().split('T')[0];
    setActivityInitialData({
      content: todoToComplete.content,
      activity_date: today,
      activity_type: 'note',
      participants: [],
    });

    setShowCompleteModal(false);
    setShowActivityModal(true);
  };

  // Handle logging a meeting as activity
  const handleLogMeeting = async (meeting) => {
    setLoggingMeetingId(meeting.id);
    try {
      await logMeeting.mutateAsync(meeting.id);
      // Success - invalidation happens in the mutation hook
    } catch (error) {
      console.error('Failed to log meeting:', error);
      alert('Afspraak kon niet als activiteit worden vastgelegd. Probeer het opnieuw.');
    } finally {
      setLoggingMeetingId(null);
    }
  };

  // Handle deleting an activity
  const handleDeleteActivity = async (activityId) => {
    if (!window.confirm('Weet je zeker dat je deze activiteit wilt verwijderen?')) {
      return;
    }

    await deleteActivity.mutateAsync({ activityId, personId: id });
  };

  // Handle deleting a todo
  const handleDeleteTodo = async (todoId) => {
    if (!window.confirm('Weet je zeker dat je deze taak wilt verwijderen?')) {
      return;
    }

    await deleteTodo.mutateAsync({ todoId, personId: id });
  };

  // Handle editing timeline item
  const handleEditTimelineItem = (item) => {
    if (item.type === 'todo') {
      setEditingTodo(item);
      setShowTodoModal(true);
    } else if (item.type === 'activity') {
      setEditingActivity(item);
      setShowActivityModal(true);
    }
    // Note editing can be added later if needed
  };

  // Handle deleting timeline item
  const handleDeleteTimelineItem = (item) => {
    if (item.type === 'note') {
      handleDeleteNote(item.id);
    } else if (item.type === 'activity') {
      handleDeleteActivity(item.id);
    } else if (item.type === 'todo') {
      handleDeleteTodo(item.id);
    }
  };

  // Handle deleting a work history item
  const handleDeleteWorkHistory = async (index) => {
    if (!window.confirm('Weet je zeker dat je dit werkverleden wilt verwijderen?')) {
      return;
    }
    
    const updatedWorkHistory = [...(person.acf?.work_history || [])];
    updatedWorkHistory.splice(index, 1);
    
    const acfData = sanitizePersonAcf(person.acf, {
      work_history: updatedWorkHistory,
    });
    
    await updatePerson.mutateAsync({
      id,
      data: {
        acf: acfData,
      },
    });
  };

  // Handle removing a label
  const handleRemoveLabel = async (labelToRemove) => {
    // Find the term ID for the label to remove
    const labelTerm = person?._embedded?.['wp:term']?.flat()
      ?.find(term => term?.taxonomy === 'person_label' && term?.name === labelToRemove);
    
    if (!labelTerm) return;
    
    // Remove the term ID from current labels
    const updatedTermIds = currentLabelTermIds.filter(termId => termId !== labelTerm.id);
    
    await updatePerson.mutateAsync({
      id,
      data: {
        person_label: updatedTermIds,
      },
    });
  };

  // Handle adding a label
  const handleAddLabel = async () => {
    if (!selectedLabelToAdd) return;
    
    const labelToAdd = availableLabels.find(l => l.id.toString() === selectedLabelToAdd);
    if (!labelToAdd) return;
    
    // Add the new term ID to current labels
    const updatedTermIds = [...currentLabelTermIds, labelToAdd.id];
    
    await updatePerson.mutateAsync({
      id,
      data: {
        person_label: updatedTermIds,
      },
    });
    
    setSelectedLabelToAdd('');
    setIsAddingLabel(false);
  };

  // Handle photo upload
  const handlePhotoUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
      alert('Selecteer een afbeeldingsbestand');
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('Afbeelding moet kleiner zijn dan 5MB');
      return;
    }

    setIsUploadingPhoto(true);

    try {
      // Upload the file using the new endpoint that properly names files
      await prmApi.uploadPersonPhoto(id, file);

      // Invalidate queries to refresh person data
      queryClient.invalidateQueries({ queryKey: ['person', id] });
      queryClient.invalidateQueries({ queryKey: ['people'] });
    } catch {
      alert('Foto kon niet worden geüpload. Probeer het opnieuw.');
    } finally {
      setIsUploadingPhoto(false);
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  // Handle vCard export
  const handleExportVCard = () => {
    if (!person) return;
    
    try {
      downloadVCard(person, {
        teamMap,
        personDates: personDates || [],
      });
    } catch {
      alert('vCard kon niet worden geëxporteerd. Probeer het opnieuw.');
    }
  };

  // Fetch team/commissie names for work history entries
  // Build a map of entity ID to type from work history for efficient lookup
  const entityTypeMap = useMemo(() => {
    const map = new Map();
    if (!person?.acf?.work_history) return map;
    person.acf.work_history.forEach(job => {
      if (job.team && job.entity_type && !map.has(job.team)) {
        map.set(job.team, job.entity_type);
      }
    });
    return map;
  }, [person?.acf?.work_history]);

  // Get unique entity IDs from work history
  const entityIds = useMemo(() => {
    if (!person?.acf?.work_history) return [];
    const ids = person.acf.work_history
      .map(job => job.team)
      .filter(Boolean);
    return [...new Set(ids)];
  }, [person?.acf?.work_history]);

  const entityQueries = useQueries({
    queries: entityIds.map(entityId => ({
      queryKey: ['work-history-entity', entityId],
      queryFn: async () => {
        // Check if we know the entity type from the work history data
        const knownType = entityTypeMap.get(entityId);

        // If we know the entity type, fetch directly from the correct endpoint
        if (knownType === 'team') {
          const response = await wpApi.getTeam(entityId, { _embed: true });
          return { ...response.data, _entityType: 'team' };
        }
        if (knownType === 'commissie') {
          const response = await wpApi.getCommissie(entityId, { _embed: true });
          return { ...response.data, _entityType: 'commissie' };
        }
        // Legacy data without entity_type: try team first, then commissie
        try {
          const response = await wpApi.getTeam(entityId, { _embed: true });
          return { ...response.data, _entityType: 'team' };
        } catch (error) {
          // If 404, try fetching as commissie
          if (error.response?.status === 404) {
            const response = await wpApi.getCommissie(entityId, { _embed: true });
            return { ...response.data, _entityType: 'commissie' };
          }
          throw error;
        }
      },
      enabled: !!entityId,
      retry: false, // Don't retry 404s
      staleTime: 5 * 60 * 1000, // Cache for 5 minutes
    })),
  });

  // Fetch dates for related people to calculate ages for sorting
  const relatedPersonIds = person?.acf?.relationships
    ?.map(rel => rel.related_person)
    .filter(Boolean) || [];
  
  const relatedPersonDatesQueries = useQueries({
    queries: relatedPersonIds.map(personId => ({
      queryKey: ['person-dates', personId],
      queryFn: async () => {
        const response = await prmApi.getPersonDates(personId);
        return response.data;
      },
      enabled: !!personId,
    })),
  });

  // Create a map of person ID to age for sorting
  const personAgeMap = useMemo(() => {
    const ageMap = {};
    relatedPersonDatesQueries.forEach((query, index) => {
      if (query.data && relatedPersonIds[index]) {
        const personId = relatedPersonIds[index];
        // Find birthday
        const birthday = query.data.find(d => {
          const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
          return dateType?.toLowerCase() === 'birthday';
        });
        if (birthday?.date_value) {
          const birthDate = new Date(birthday.date_value);
          ageMap[personId] = differenceInYears(new Date(), birthDate);
        } else {
          // If no birthday, use a very large number so they appear last
          ageMap[personId] = -1;
        }
      }
    });
    return ageMap;
  }, [relatedPersonDatesQueries, relatedPersonIds]);

  // Create a map of person ID to deceased status
  const personDeceasedMap = useMemo(() => {
    const map = {};
    relatedPersonDatesQueries.forEach((query, index) => {
      if (query.data && relatedPersonIds[index]) {
        const personId = relatedPersonIds[index];
        const hasDiedDate = query.data.some(d => {
          const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
          return dateType?.toLowerCase() === 'died';
        });
        map[personId] = hasDiedDate;
      }
    });
    return map;
  }, [relatedPersonDatesQueries, relatedPersonIds]);

  // Sort relationships by age (descending - oldest first)
  const sortedRelationships = useMemo(() => {
    if (!person?.acf?.relationships) return [];
    
    return [...person.acf.relationships].sort((a, b) => {
      const ageA = personAgeMap[a.related_person] ?? -1;
      const ageB = personAgeMap[b.related_person] ?? -1;
      
      // Sort descending (oldest first)
      // If age is -1 (no birthday), put at the end
      if (ageA === -1 && ageB === -1) return 0;
      if (ageA === -1) return 1;
      if (ageB === -1) return -1;
      
      return ageB - ageA;
    });
  }, [person?.acf?.relationships, personAgeMap]);

  // Create a map of entity ID to entity data (name, logo, type)
  const entityMap = {};
  entityQueries.forEach((query, index) => {
    if (query.data) {
      const entityId = entityIds[index];
      entityMap[entityId] = {
        name: getTeamName(query.data),
        logo: query.data._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
              query.data._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url ||
              null,
        type: query.data._entityType || 'team',
      };
    }
  });

  // Keep teamMap for backward compatibility (references old teamMap in other parts of code)
  const teamMap = entityMap;
  
  // Sort work history by start date descending (most recent first)
  // Current jobs come first, then sorted by start_date descending
  // Preserve original index for edit/delete operations
  const sortedWorkHistory = useMemo(() => {
    if (!person?.acf?.work_history) return [];
    
    return [...person.acf.work_history]
      .map((job, originalIndex) => ({ ...job, originalIndex }))
      .sort((a, b) => {
        // Current jobs come first
        if (a.is_current && !b.is_current) return -1;
        if (!a.is_current && b.is_current) return 1;
        
        // Both current or both not current - sort by start_date descending
        const dateA = a.start_date ? new Date(a.start_date) : new Date(0);
        const dateB = b.start_date ? new Date(b.start_date) : new Date(0);
        
        // Most recent first (descending)
        return dateB - dateA;
      });
  }, [person?.acf?.work_history]);

  // Get current position(s) for header display
  const currentPositions = useMemo(() => {
    if (!sortedWorkHistory?.length) return [];
    return sortedWorkHistory.filter(job => job.is_current);
  }, [sortedWorkHistory]);

  // Process positions for grouped display in header
  const groupedPositions = useMemo(() => {
    if (!currentPositions?.length) return [];

    // Filter out "Kaderlid Algemeen" if there are multiple positions
    let positionsToShow = currentPositions;
    if (currentPositions.length > 1) {
      positionsToShow = currentPositions.filter(job => job.job_title !== 'Kaderlid Algemeen');
      // If all positions were "Kaderlid Algemeen", keep them all
      if (positionsToShow.length === 0) {
        positionsToShow = currentPositions;
      }
    }

    // Group positions by team
    // Use null key for "Verenigingsbreed" or positions without a team
    const groups = new Map();

    positionsToShow.forEach(job => {
      const team = job.team && teamMap[job.team];
      const isVerenigingsbreed = team?.name === 'Verenigingsbreed';
      const groupKey = (!team || isVerenigingsbreed) ? null : job.team;

      if (!groups.has(groupKey)) {
        groups.set(groupKey, {
          teamId: groupKey,
          team: groupKey ? team : null,
          teamName: groupKey ? team.name : null,
          teamType: groupKey ? team.type : null,
          showTeamLink: groupKey !== null, // Don't show link for Verenigingsbreed or no-team
          titles: []
        });
      }

      if (job.job_title) {
        groups.get(groupKey).titles.push(job.job_title);
      }
    });

    // Convert to array and sort: Verenigingsbreed (null key) first, then other teams
    const result = Array.from(groups.values())
      .filter(group => group.titles.length > 0) // Only show groups with titles
      .sort((a, b) => {
        // null (Verenigingsbreed/no-team) first
        if (a.teamId === null && b.teamId !== null) return -1;
        if (a.teamId !== null && b.teamId === null) return 1;
        return 0;
      });

    return result;
  }, [currentPositions, teamMap]);

  // Extract and sort todos from timeline
  // Open first, awaiting second, completed last
  const sortedTodos = useMemo(() => {
    if (!timeline) return [];

    const todos = timeline.filter(item => item.type === 'todo');

    return todos.sort((a, b) => {
      // Status priority: open first, awaiting second, completed last
      const statusOrder = { open: 0, awaiting: 1, completed: 2 };
      const aOrder = statusOrder[a.status] ?? 0;
      const bOrder = statusOrder[b.status] ?? 0;

      if (aOrder !== bOrder) return aOrder - bOrder;

      // For open todos, sort by due date (earliest first)
      if (a.status === 'open') {
        if (a.due_date && b.due_date) {
          return new Date(a.due_date) - new Date(b.due_date);
        }
        if (a.due_date && !b.due_date) return -1;
        if (!a.due_date && b.due_date) return 1;
      }

      // For awaiting todos, sort by awaiting_since (oldest first)
      if (a.status === 'awaiting') {
        if (a.awaiting_since && b.awaiting_since) {
          return new Date(a.awaiting_since) - new Date(b.awaiting_since);
        }
      }

      // Default: sort by creation date (newest first)
      return new Date(b.created) - new Date(a.created);
    });
  }, [timeline]);

  // Count of open (non-completed) todos for sidebar badge
  const openTodosCount = useMemo(() => {
    return sortedTodos.filter(todo => todo.status !== 'completed').length;
  }, [sortedTodos]);

  // Redirect if person is trashed
  useEffect(() => {
    if (person?.status === 'trash') {
      navigate('/people', { replace: true });
    }
  }, [person, navigate]);
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
      </div>
    );
  }

  if (error || !person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400">Lid kon niet worden geladen.</p>
        <Link to="/people" className="btn-secondary mt-4">Terug naar leden</Link>
      </div>
    );
  }
  
  // Don't render if person is trashed (redirect will happen)
  if (person.status === 'trash') {
    return null;
  }
  
  const acf = person.acf || {};

  // Calculate VOG status
  const vogStatus = getVogStatus(acf);

  // Extract social links for header display (slack is now in contact details, not social)
  const socialTypes = ['facebook', 'linkedin', 'instagram', 'twitter', 'bluesky', 'threads', 'website'];
  const socialLinks = acf.contact_info?.filter(contact => socialTypes.includes(contact.contact_type)) || [];
  
  // Check if there's a mobile number for WhatsApp
  const mobileContact = acf.contact_info?.find(contact => contact.contact_type === 'mobile');
  
  // Define display order for social icons
  const socialIconOrder = {
    'linkedin': 1,
    'twitter': 2,
    'bluesky': 3,
    'threads': 4,
    'instagram': 5,
    'facebook': 6,
    'whatsapp': 7,
    'website': 8,
  };
  
  // Sort social links by display order, and add WhatsApp and Sportlink if applicable
  const sortedSocialLinks = (() => {
    const links = [...socialLinks];

    // Add WhatsApp if there's a mobile number
    if (mobileContact) {
      links.push({
        contact_type: 'whatsapp',
        contact_value: `https://wa.me/${formatPhoneForTel(mobileContact.contact_value)}`,
      });
    }

    // Add Sportlink if there's a KNVB ID
    if (acf['knvb-id']) {
      links.push({
        contact_type: 'sportlink',
        contact_value: `https://club.sportlink.com/member/${acf['knvb-id']}`,
      });
    }

    return links.sort((a, b) => {
      const orderA = socialIconOrder[a.contact_type] || 99;
      const orderB = socialIconOrder[b.contact_type] || 99;
      return orderA - orderB;
    });
  })();
  
  // Helper to get social icon
  const getSocialIcon = (type) => {
    switch (type) {
      case 'facebook': return SiFacebook;
      case 'linkedin': return LinkedInIcon;
      case 'instagram': return SiInstagram;
      case 'twitter': return SiX; // Twitter/X uses SiX in Simple Icons
      case 'bluesky': return SiBluesky;
      case 'threads': return SiThreads;
      case 'whatsapp': return SiWhatsapp;
      case 'website': return Globe; // Use Lucide Globe for website
      default: return Globe;
    }
  };
  
  // Helper to get social icon color
  const getSocialIconColor = (type) => {
    switch (type) {
      case 'facebook': return 'text-[#1877F2]';
      case 'linkedin': return 'text-[#0077B7]'; // LinkedIn brand color
      case 'instagram': return 'text-[#E4405F]';
      case 'twitter': return 'text-[#000000] dark:text-white';
      case 'bluesky': return 'text-[#00A8E8]'; // Bluesky brand color
      case 'threads': return 'text-[#000000] dark:text-white'; // Threads brand color (black, white in dark mode)
      case 'whatsapp': return 'text-[#25D366]'; // WhatsApp brand color
      case 'website': return 'text-gray-600';
      default: return 'text-gray-600';
    }
  };

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
        <Link to="/people" className="flex items-center text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Terug naar leden</span>
        </Link>
        <div className="flex gap-2">
          <button onClick={handleExportVCard} className="btn-secondary">
            <Download className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Exporteer vCard</span>
          </button>
          <button onClick={() => setShowShareModal(true)} className="btn-secondary" title="Delen">
            <Share2 className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Delen</span>
          </button>
          <button onClick={() => setShowPersonEditModal(true)} className="btn-secondary">
            <Edit className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Bewerken</span>
          </button>
          <button onClick={handleDelete} className="btn-danger-outline">
            <Trash2 className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Verwijderen</span>
          </button>
        </div>
      </div>
      
      {/* Profile header */}
      <div className={`card p-6 relative ${acf['financiele-blokkade'] ? 'bg-red-50 dark:bg-red-950/30' : ''}`}>
        {/* VOG Status Badge */}
        {vogStatus && (
          <div className="absolute top-4 right-4">
            <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
              vogStatus.color === 'green'
                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                : vogStatus.color === 'orange'
                ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
                : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
            }`}>
              {vogStatus.label}
            </span>
          </div>
        )}
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
          <div className="relative group">
            {person.thumbnail ? (
              <img
                src={person.thumbnail}
                alt={person.name}
                className="w-28 h-28 rounded-full object-cover"
              />
            ) : (
              <div className="w-28 h-28 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                <span className="text-3xl font-medium text-gray-500 dark:text-gray-300">
                  {person.first_name?.[0] || '?'}
                </span>
              </div>
            )}
            {/* Upload overlay */}
            <div className="absolute inset-0 rounded-full bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 flex items-center justify-center cursor-pointer"
                 onClick={() => fileInputRef.current?.click()}
            >
              {isUploadingPhoto ? (
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
              ) : (
                <Camera className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
              )}
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={handlePhotoUpload}
              className="hidden"
              disabled={isUploadingPhoto}
            />
          </div>

          <div className="flex-1 space-y-3">
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold dark:text-gray-50">
                {person.name}
                {isDeceased && <span className="ml-1 text-gray-500 dark:text-gray-400">&#8224;</span>}
              </h1>
            </div>
            {groupedPositions.length > 0 && (
              <p className="text-base text-gray-600 dark:text-gray-300">
                {groupedPositions.map((group, groupIdx) => (
                  <span key={groupIdx}>
                    {groupIdx > 0 && ', '}
                    {group.titles.join(', ')}
                    {group.showTeamLink && group.team && (
                      <>
                        <span className="text-gray-400 dark:text-gray-500"> bij </span>
                        <Link
                          to={`/${group.teamType === 'commissie' ? 'commissies' : 'teams'}/${group.teamId}`}
                          className="text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 hover:underline"
                        >
                          {group.teamName}
                        </Link>
                      </>
                    )}
                  </span>
                ))}
              </p>
            )}
            {acf.nickname && (
              <p className="text-gray-500 dark:text-gray-400">"{acf.nickname}"</p>
            )}
            {isDeceased && deathDateValue && (
              <p className="text-gray-500 dark:text-gray-400 text-sm inline-flex items-center flex-wrap">
                {getGenderSymbol(acf.gender) && <span>{getGenderSymbol(acf.gender)}</span>}
                {getGenderSymbol(acf.gender) && acf.pronouns && <span>&nbsp;—&nbsp;</span>}
                {acf.pronouns && <span>{acf.pronouns}</span>}
                {(getGenderSymbol(acf.gender) || acf.pronouns) && <span>&nbsp;—&nbsp;</span>}
                <span>Overleden op {format(deathDateValue, deathYearUnknown ? 'd MMMM' : 'd MMMM yyyy')}{age !== null && ` op ${age}-jarige leeftijd`}</span>
              </p>
            )}
            {!isDeceased && (getGenderSymbol(acf.gender) || acf.pronouns || age !== null || acf['financiele-blokkade']) && (
              <p className="text-gray-500 dark:text-gray-400 text-sm inline-flex items-center flex-wrap">
                {getGenderSymbol(acf.gender) && <span>{getGenderSymbol(acf.gender)}</span>}
                {getGenderSymbol(acf.gender) && acf.pronouns && <span>&nbsp;—&nbsp;</span>}
                {acf.pronouns && <span>{acf.pronouns}</span>}
                {(getGenderSymbol(acf.gender) || acf.pronouns) && age !== null && <span>&nbsp;—&nbsp;</span>}
                {age !== null && <span>{age} jaar</span>}
                {acf['financiele-blokkade'] && (
                  <>
                    {(getGenderSymbol(acf.gender) || acf.pronouns || age !== null) && <span>&nbsp;—&nbsp;</span>}
                    <span className="text-red-600 dark:text-red-400 font-medium">Financiële blokkade</span>
                  </>
                )}
              </p>
            )}
            <div>
              <div className="flex flex-wrap gap-2 items-center">
                {person.labels && person.labels.length > 0 && (
                  person.labels.map((label, index) => (
                    <span
                      key={index}
                      className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 group/label"
                    >
                      {label}
                      <button
                        onClick={() => handleRemoveLabel(label)}
                        className="opacity-0 group-hover/label:opacity-100 transition-opacity hover:bg-gray-200 dark:bg-gray-600 rounded-full p-0.5"
                        title="Label verwijderen"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </span>
                  ))
                )}
                {!isAddingLabel ? (
                  availableLabelsToAdd.length > 0 && (
                    <button
                      onClick={() => setIsAddingLabel(true)}
                      className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition-colors"
                    >
                      <Plus className="w-3 h-3 mr-1" />
                      Label toevoegen
                    </button>
                  )
                ) : (
                  <div className="inline-flex items-center gap-2">
                    <select
                      value={selectedLabelToAdd}
                      onChange={(e) => setSelectedLabelToAdd(e.target.value)}
                      className="text-xs border border-gray-300 rounded px-2 py-1"
                      autoFocus
                      disabled={availableLabelsToAdd.length === 0}
                    >
                      <option value="">Selecteer een label...</option>
                      {availableLabelsToAdd.map(label => (
                        <option key={label.id} value={label.id}>
                          {label.name}
                        </option>
                      ))}
                    </select>
                    <button
                      onClick={handleAddLabel}
                      disabled={!selectedLabelToAdd}
                      className="text-xs px-2 py-1 bg-accent-600 text-white rounded hover:bg-accent-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Toevoegen
                    </button>
                    <button
                      onClick={() => {
                        setIsAddingLabel(false);
                        setSelectedLabelToAdd('');
                      }}
                      className="text-xs px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
                    >
                      Annuleren
                    </button>
                  </div>
                )}
              </div>
              {sortedSocialLinks.length > 0 && (
                <div className="flex items-center gap-3 mt-4">
                  {sortedSocialLinks.map((contact, index) => {
                    // Ensure URL has protocol
                    let url = contact.contact_value;
                    if (!url.match(/^https?:\/\//i)) {
                      url = `https://${url}`;
                    }

                    // Handle Sportlink specially with custom icon
                    if (contact.contact_type === 'sportlink') {
                      return (
                        <a
                          key={index}
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex-shrink-0 hover:opacity-80 transition-opacity"
                          title="Bekijk in Sportlink Club"
                        >
                          <img src="/icons/sportlink.png" alt="Sportlink" className="w-5 h-5" />
                        </a>
                      );
                    }

                    // Regular social icons
                    const SocialIcon = getSocialIcon(contact.contact_type);
                    const iconColor = getSocialIconColor(contact.contact_type);

                    return (
                      <a
                        key={index}
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`flex-shrink-0 hover:opacity-80 transition-opacity ${iconColor}`}
                        title={`${contact.contact_type.charAt(0).toUpperCase() + contact.contact_type.slice(1)}: ${contact.contact_value}`}
                      >
                        <SocialIcon className="w-5 h-5" />
                      </a>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
      
      {/* Tab Navigation */}
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav className="flex gap-8">
          <button
            onClick={() => setActiveTab('profile')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'profile'
                ? 'border-accent-600 text-accent-600'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'
            }`}
          >
            Profiel
          </button>
          <button
            onClick={() => setActiveTab('timeline')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'timeline'
                ? 'border-accent-600 text-accent-600'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'
            }`}
          >
            Tijdlijn
          </button>
          <button
            onClick={() => setActiveTab('work')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'work'
                ? 'border-accent-600 text-accent-600'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'
            }`}
          >
            Rollen
          </button>
          <button
            onClick={() => setActiveTab('meetings')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'meetings'
                ? 'border-accent-600 text-accent-600'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'
            }`}
          >
            Afspraken
            {meetings?.total_upcoming > 0 && (
              <span className="ml-1 text-xs bg-accent-100 dark:bg-accent-800 text-accent-700 dark:text-accent-100 px-1.5 py-0.5 rounded-full">
                {meetings.total_upcoming}
              </span>
            )}
          </button>
        </nav>
      </div>

      {/* Tab Content */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main content */}
        <div className="lg:col-span-2 min-w-0 space-y-6">
        {/* Profile Tab */}
        {activeTab === 'profile' && (
          <div className="columns-1 md:columns-2 gap-6">
            {/* Contact info - only show for living people */}
          {!isDeceased && (
            <div className="card p-6 break-inside-avoid mb-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold">Contactgegevens</h2>
                <button
                  onClick={() => setShowContactModal(true)}
                  className="btn-secondary text-sm"
                >
                  <Pencil className="w-4 h-4 md:mr-1" />
                  <span className="hidden md:inline">Bewerken</span>
                </button>
              </div>
            {acf.contact_info?.filter(contact => !socialTypes.includes(contact.contact_type)).length > 0 ? (
              <div className="space-y-2">
                {(() => {
                  // Define display order for contact information
                  const contactOrder = {
                    'email': 1,
                    'phone': 2,
                    'mobile': 2, // Phone numbers grouped together
                    'slack': 3,
                    'calendar': 4,
                    'other': 5,
                  };
                  
                  // Filter and sort contact information
                  const nonSocialContacts = acf.contact_info
                    .filter(contact => !socialTypes.includes(contact.contact_type))
                    .map((contact, originalIndex) => ({ ...contact, originalIndex }))
                    .sort((a, b) => {
                      const orderA = contactOrder[a.contact_type] || 99;
                      const orderB = contactOrder[b.contact_type] || 99;
                      if (orderA !== orderB) {
                        return orderA - orderB;
                      }
                      // If same order (e.g., multiple phone numbers), maintain original order
                      return a.originalIndex - b.originalIndex;
                    });
                  
                  return nonSocialContacts.map((contact) => {
                        const Icon = contact.contact_type === 'email' ? Mail :
                                     contact.contact_type === 'phone' || contact.contact_type === 'mobile' ? Phone :
                                     contact.contact_type === 'slack' ? SiSlack :
                                     contact.contact_type === 'calendar' ? Calendar : Globe;

                        const isEmail = contact.contact_type === 'email';
                        const isPhone = contact.contact_type === 'phone' || contact.contact_type === 'mobile';
                        const isSlack = contact.contact_type === 'slack';
                        const isCalendar = contact.contact_type === 'calendar';
                        
                        let linkHref = null;
                        let linkTarget = null;
                        
                        if (isEmail) {
                          linkHref = `mailto:${contact.contact_value}`;
                        } else if (isPhone) {
                          linkHref = `tel:${formatPhoneForTel(contact.contact_value)}`;
                        } else if (isSlack || isCalendar) {
                          linkHref = contact.contact_value;
                          linkTarget = '_blank';
                        }

                        return (
                      <div key={contact.originalIndex}>
                        <div className="flex items-center rounded-md -mx-2 px-2 py-1.5">
                          <Icon className="w-4 h-4 text-gray-400 mr-3 flex-shrink-0" />
                          <div className="flex-1 min-w-0 flex items-center gap-2">
                            {isSlack ? (
                              <a
                                href={contact.contact_value}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 hover:underline"
                              >
                                {contact.contact_label || 'Slack'}
                              </a>
                            ) : (
                              <>
                                <span className="text-sm text-gray-500 dark:text-gray-400">{contact.contact_label || contact.contact_type}: </span>
                                {linkHref ? (
                                  <a
                                    href={linkHref}
                                    target={linkTarget || undefined}
                                    rel={linkTarget === '_blank' ? 'noopener noreferrer' : undefined}
                                    className="text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 hover:underline"
                                  >
                                    {contact.contact_value}
                                  </a>
                                ) : (
                                  <span>{contact.contact_value}</span>
                                )}
                              </>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })})()}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                Nog geen contactgegevens. <button onClick={() => setShowContactModal(true)} className="text-accent-600 hover:underline">Toevoegen</button>
              </p>
            )}
            {/* View in Google Contacts link - only for synced contacts with email */}
            {person.google_contact_id && acf.contact_info?.find(c => c.contact_type === 'email')?.contact_value && (
              <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <a
                  href={`https://contacts.google.com/${acf.contact_info.find(c => c.contact_type === 'email').contact_value}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400"
                >
                  <ExternalLink className="w-4 h-4" />
                  <span>View in Google Contacts</span>
                </a>
              </div>
            )}
            </div>
          )}

          {/* Important Dates */}
          <div className="card p-6 break-inside-avoid mb-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Belangrijke datums</h2>
              <button
                onClick={() => {
                  setEditingDate(null);
                  setShowDateModal(true);
                }}
                className="btn-secondary text-sm"
                title="Belangrijke datum toevoegen"
              >
                <Plus className="w-4 h-4" />
              </button>
            </div>
            {allDates.length > 0 ? (
              <div className="space-y-3">
                {allDates.map((date) => {
                  const dateType = Array.isArray(date.date_type) ? date.date_type[0] : date.date_type;
                  const dateTypeLower = dateType?.toLowerCase() || '';
                  const isDied = dateTypeLower.includes('died');
                  const Icon = dateTypeLower.includes('wedding') || dateTypeLower.includes('marriage') ? Heart :
                               dateTypeLower.includes('birthday') ? Gift : Calendar;

                  const dateFormat = date.year_unknown ? 'MMMM d' : 'MMMM d, yyyy';

                  return (
                    <div key={date.id} className="flex items-start group">
                      <div className="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center mr-3">
                        {isDied ? (
                          <span className="text-gray-500 text-lg font-semibold">†</span>
                        ) : (
                          <Icon className="w-4 h-4 text-gray-500" />
                        )}
                      </div>
                      <div className="flex-1">
                        <p className="text-sm font-medium">{date.title}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                          {date.date_value && format(new Date(date.date_value), dateFormat)}
                        </p>
                        {dateType && (
                          <p className="text-xs text-gray-400 capitalize">{dateType}</p>
                        )}
                      </div>
                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          onClick={() => {
                            setEditingDate(date);
                            setShowDateModal(true);
                          }}
                          className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                          title="Datum bewerken"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </button>
                        <button
                          onClick={() => handleDeleteDate(date.id)}
                          className="p-1 hover:bg-red-50 rounded"
                          title="Datum verwijderen"
                        >
                          <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                Nog geen belangrijke datums. <button onClick={() => { setEditingDate(null); setShowDateModal(true); }} className="text-accent-600 hover:underline">Toevoegen</button>
              </p>
            )}
          </div>

            {/* Addresses - only show for living people */}
            {!isDeceased && (
              <div className="card p-6 break-inside-avoid mb-6">
                <div className="flex items-center justify-between mb-4">
                  <h2 className="font-semibold">Adressen</h2>
                  <button
                    onClick={() => {
                      setEditingAddress(null);
                      setEditingAddressIndex(null);
                      setShowAddressModal(true);
                    }}
                    className="btn-secondary text-sm"
                    title="Adres toevoegen"
                  >
                    <Plus className="w-4 h-4" />
                  </button>
                </div>
                {acf.addresses?.length > 0 ? (
                  <div className="space-y-3">
                    {acf.addresses.map((address, index) => {
                      const addressLines = [
                        address.street,
                        [address.city, address.state, address.postal_code].filter(Boolean).join(', '),
                        address.country
                      ].filter(Boolean);
                      
                      const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressLines.join(', '))}`;
                      
                      return (
                        <div key={index} className="flex items-start group">
                          <MapPin className="w-4 h-4 text-gray-400 mt-1 mr-3 flex-shrink-0" />
                          <div className="flex-1 min-w-0">
                            {address.address_label && (
                              <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">{address.address_label}</p>
                            )}
                            <a
                              href={googleMapsUrl}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 hover:underline text-sm"
                            >
                              {addressLines.map((line, i) => (
                                <span key={i} className="block">{line}</span>
                              ))}
                            </a>
                          </div>
                          <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                            <button
                              onClick={() => {
                                setEditingAddress(address);
                                setEditingAddressIndex(index);
                                setShowAddressModal(true);
                              }}
                              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                              title="Adres bewerken"
                            >
                              <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                            </button>
                            <button
                              onClick={() => handleDeleteAddress(index)}
                              className="p-1 hover:bg-red-50 rounded"
                              title="Adres verwijderen"
                            >
                              <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p className="text-sm text-gray-500 text-center py-4">
                    Nog geen adressen. <button onClick={() => { setEditingAddress(null); setEditingAddressIndex(null); setShowAddressModal(true); }} className="text-accent-600 hover:underline">Toevoegen</button>
                  </p>
                )}
              </div>
            )}

            {/* How we met */}
            {(acf.how_we_met || acf.met_date) && (
              <div className="card p-6 break-inside-avoid mb-6">
                <h2 className="font-semibold mb-3">How we met</h2>
                {acf.met_date && (
                  <p className="text-sm text-gray-500 mb-2">
                    <Calendar className="w-4 h-4 inline mr-1" />
                    {format(new Date(acf.met_date), 'MMMM d, yyyy')}
                  </p>
                )}
                {acf.how_we_met && (
                  <p className="text-sm">{acf.how_we_met}</p>
                )}
              </div>
            )}

            {/* Relationships */}
            <div className="card p-6 break-inside-avoid mb-6">
              <div className="flex items-center justify-between mb-3">
                <h2 className="font-semibold">Relaties</h2>
                <div className="flex items-center gap-2">
                  <Link
                    to={`/people/${id}/family-tree`}
                    className="btn-secondary text-sm"
                  >
                    Bekijk stamboom
                  </Link>
                  <button
                    onClick={() => {
                      setEditingRelationship(null);
                      setEditingRelationshipIndex(null);
                      setShowRelationshipModal(true);
                    }}
                    className="btn-secondary text-sm"
                    title="Relatie toevoegen"
                  >
                    <Plus className="w-4 h-4" />
                  </button>
                </div>
              </div>
              {sortedRelationships?.length > 0 ? (
                <div className="space-y-2">
                  {sortedRelationships.map((rel, index) => {
                    const originalIndex = person?.acf?.relationships?.findIndex(
                      r => r.related_person === rel.related_person && 
                           r.relationship_type === rel.relationship_type
                    ) ?? index;
                    
                    return (
                      <div key={index} className="flex items-center p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 group">
                        <Link
                          to={`/people/${rel.related_person}`}
                          className="flex items-center flex-1 min-w-0"
                        >
                          {rel.person_thumbnail ? (
                            <img
                              src={rel.person_thumbnail}
                              alt={decodeHtml(rel.person_name) || ''}
                              className="w-8 h-8 rounded-full object-cover mr-2"
                            />
                          ) : (
                            <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full mr-2 flex items-center justify-center">
                              <span className="text-xs font-medium text-gray-500">
                                {decodeHtml(rel.person_name)?.[0] || '?'}
                              </span>
                            </div>
                          )}
                          <div>
                            <p className="text-sm font-medium">
                              {decodeHtml(rel.person_name) || `Person #${rel.related_person}`}
                              {personDeceasedMap[rel.related_person] && (
                                <span className="text-gray-400 ml-1" title="Overleden">†</span>
                              )}
                            </p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">{decodeHtml(rel.relationship_name || rel.relationship_label)}</p>
                          </div>
                        </Link>
                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                          <button
                            onClick={() => {
                              const relData = person?.acf?.relationships?.[originalIndex];
                              setEditingRelationship(relData);
                              setEditingRelationshipIndex(originalIndex);
                              setShowRelationshipModal(true);
                            }}
                            className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                            title="Relatie bewerken"
                          >
                            <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                          </button>
                          <button
                            onClick={() => handleDeleteRelationship(originalIndex)}
                            className="p-1 hover:bg-red-50 rounded"
                            title="Relatie verwijderen"
                          >
                            <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-4">
                  Nog geen relaties. <button onClick={() => { setEditingRelationship(null); setEditingRelationshipIndex(null); setShowRelationshipModal(true); }} className="text-accent-600 hover:underline">Toevoegen</button>
                </p>
              )}
            </div>

            {/* Custom Fields */}
            <CustomFieldsSection
              postType="person"
              postId={parseInt(id)}
              acfData={person?.acf}
              onUpdate={(newAcfValues) => {
                const acfData = sanitizePersonAcf(person.acf, newAcfValues);
                updatePerson.mutateAsync({
                  id,
                  data: { acf: acfData },
                });
              }}
              isUpdating={updatePerson.isPending}
            />
          </div>
        )}

        {/* Timeline Tab */}
        {activeTab === 'timeline' && (
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Tijdlijn</h2>
              <div className="flex gap-2">
                <button
                  onClick={() => setShowNoteModal(true)}
                  className="btn-secondary text-sm"
                  title="Notitie toevoegen"
                >
                  <StickyNote className="w-4 h-4 md:mr-1" />
                  <span className="hidden md:inline">Notitie</span>
                </button>
                <button
                  onClick={() => setShowActivityModal(true)}
                  className="btn-secondary text-sm"
                  title="Activiteit toevoegen"
                >
                  <MessageCircle className="w-4 h-4 md:mr-1" />
                  <span className="hidden md:inline">Activiteit</span>
                </button>
              </div>
            </div>

            <TimelineView
              timeline={timeline || []}
              onEdit={handleEditTimelineItem}
              onDelete={handleDeleteTimelineItem}
              onToggleTodo={handleToggleTodo}
              personId={id}
              allPeople={allPeople || []}
            />
          </div>
        )}

        {/* Work Tab */}
        {activeTab === 'work' && (
          <div className="columns-1 md:columns-2 gap-6">
            {/* Work history - spans both columns */}
          <div className="card p-6 mb-6 [column-span:all]">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Functiegeschiedenis</h2>
              <button
                onClick={() => {
                  setEditingWorkHistory(null);
                  setEditingWorkHistoryIndex(null);
                  setShowWorkHistoryModal(true);
                }}
                className="btn-secondary text-sm"
                title="Functie toevoegen"
              >
                <Plus className="w-4 h-4" />
              </button>
            </div>
            {sortedWorkHistory?.length > 0 ? (
              <div className="space-y-4">
                {sortedWorkHistory.map((job, index) => {
                  const teamData = job.team ? teamMap[job.team] : null;
                  const originalIndex = job.originalIndex;
                  
                  return (
                    <div key={originalIndex} className="flex items-start group">
                      {teamData?.logo ? (
                        <img
                          src={teamData.logo}
                          alt={teamData.name}
                          className="w-20 h-20 rounded-lg object-contain mr-3 flex-shrink-0"
                        />
                      ) : (
                        <div className="w-20 h-20 bg-white rounded-lg flex items-center justify-center mr-3 flex-shrink-0 border border-gray-200">
                          <Building2 className="w-10 h-10 text-gray-500" />
                        </div>
                      )}
                      <div className="flex-1 min-w-0">
                        <p className="font-medium">{job.job_title}</p>
                        {job.team && teamData && (
                          <Link
                            to={`/${teamData.type === 'commissie' ? 'commissies' : 'teams'}/${job.team}`}
                            className="text-sm text-accent-600 hover:underline"
                          >
                            {teamData.name}
                          </Link>
                        )}
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                          {job.start_date && isValidDate(job.start_date) && format(new Date(job.start_date), 'MMM yyyy')}
                          {' - '}
                          {job.is_current ? 'Heden' : job.end_date && isValidDate(job.end_date) ? format(new Date(job.end_date), 'MMM yyyy') : ''}
                        </p>
                        {job.description && (
                          <p className="text-sm text-gray-600 mt-1">{job.description}</p>
                        )}
                      </div>
                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                        <button
                          onClick={() => {
                            const jobData = person?.acf?.work_history?.[originalIndex];
                            setEditingWorkHistory(jobData);
                            setEditingWorkHistoryIndex(originalIndex);
                            setShowWorkHistoryModal(true);
                          }}
                          className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                          title="Functie bewerken"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </button>
                        <button
                          onClick={() => handleDeleteWorkHistory(originalIndex)}
                          className="p-1 hover:bg-red-50 rounded"
                          title="Functie verwijderen"
                        >
                          <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                Nog geen functiegeschiedenis. <button onClick={() => { setEditingWorkHistory(null); setEditingWorkHistoryIndex(null); setShowWorkHistoryModal(true); }} className="text-accent-600 hover:underline">Toevoegen</button>
              </p>
            )}
          </div>
          
          {/* Investments */}
          {investments.length > 0 && (
            <div className="card p-6 break-inside-avoid mb-6">
              <h2 className="font-semibold mb-4 flex items-center">
                <TrendingUp className="w-5 h-5 mr-2" />
                Investments
              </h2>
              <div className="space-y-3">
                {investments.map((team) => (
                  <Link
                    key={team.id}
                    to={`/teams/${team.id}`}
                    className="flex items-center p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group"
                  >
                    {team.thumbnail ? (
                      <img
                        src={team.thumbnail}
                        alt={team.name}
                        className="w-12 h-12 rounded-lg object-contain border border-gray-200"
                      />
                    ) : (
                      <div className="w-12 h-12 bg-white rounded-lg flex items-center justify-center border border-gray-200">
                        <Building2 className="w-6 h-6 text-gray-400" />
                      </div>
                    )}
                    <div className="ml-3">
                      <p className="text-sm font-medium group-hover:text-accent-600">{team.name}</p>
                      {team.industry && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">{team.industry}</p>
                      )}
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          )}

          </div>
        )}

        {/* Meetings Tab */}
        {activeTab === 'meetings' && (
          <div className="space-y-6">
            {/* Upcoming Meetings */}
            <div className="card p-6">
              <h2 className="font-semibold mb-4">Aankomende afspraken</h2>
              {meetingsLoading ? (
                <div className="text-gray-500 dark:text-gray-400">Laden...</div>
              ) : meetings?.upcoming?.length > 0 ? (
                <div className="space-y-3">
                  {meetings.upcoming.map(meeting => (
                    <MeetingCard
                      key={meeting.id}
                      meeting={meeting}
                      showLogButton={false}
                      currentPersonId={id}
                      onClick={(m) => {
                        setSelectedMeeting(m);
                        setShowMeetingModal(true);
                      }}
                    />
                  ))}
                </div>
              ) : (
                <div className="text-gray-500 dark:text-gray-400 text-sm">Geen aankomende afspraken</div>
              )}
            </div>

            {/* Past Meetings */}
            <div className="card p-6">
              <h2 className="font-semibold mb-4">Afspraken in het verleden</h2>
              {meetings?.past?.length > 0 ? (
                <div className="space-y-3">
                  {meetings.past.map(meeting => (
                    <MeetingCard
                      key={meeting.id}
                      meeting={meeting}
                      showLogButton={true}
                      currentPersonId={id}
                      onLog={() => handleLogMeeting(meeting)}
                      isLogging={loggingMeetingId === meeting.id}
                      onClick={(m) => {
                        setSelectedMeeting(m);
                        setShowMeetingModal(true);
                      }}
                    />
                  ))}
                </div>
              ) : (
                <div className="text-gray-500 dark:text-gray-400 text-sm">Geen afspraken in het verleden gevonden</div>
              )}
            </div>
          </div>
        )}
        </div>

        {/* Todos Sidebar - always visible */}
        <aside className="hidden lg:block">
          <div className="sticky top-6">
            <div className="card p-6">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <h2 className="font-semibold">Taken</h2>
                  {openTodosCount > 0 && (
                    <span className="bg-accent-100 text-accent-700 text-xs font-medium px-2 py-0.5 rounded-full">
                      {openTodosCount}
                    </span>
                  )}
                </div>
                <button
                  onClick={() => {
                    setEditingTodo(null);
                    setShowTodoModal(true);
                  }}
                  className="btn-secondary text-sm"
                  title="Taak toevoegen"
                >
                  <Plus className="w-4 h-4" />
                </button>
              </div>
              {sortedTodos.length > 0 ? (
                <div className="space-y-2">
                  {sortedTodos.map((todo) => {
                    const isOverdue = isTodoOverdue(todo);
                    const awaitingDays = getAwaitingDays(todo);
                    return (
                      <div key={todo.id} className="flex items-start p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 group">
                        <button
                          onClick={() => handleToggleTodo(todo)}
                          className="mt-0.5 mr-2 flex-shrink-0"
                          title={todo.status === 'completed' ? 'Heropenen' : todo.status === 'awaiting' ? 'Markeer als voltooid' : 'Voltooien'}
                        >
                          {todo.status === 'completed' ? (
                            <CheckSquare2 className="w-5 h-5 text-accent-600" />
                          ) : todo.status === 'awaiting' ? (
                            <Clock className="w-5 h-5 text-orange-500" />
                          ) : (
                            <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600 dark:text-red-300' : 'text-gray-400 dark:text-gray-500'}`} />
                          )}
                        </button>
                        <div className="flex-1 min-w-0">
                          <p className={`text-sm ${
                            todo.status === 'completed'
                              ? 'line-through text-gray-400 dark:text-gray-500'
                              : todo.status === 'awaiting'
                              ? 'text-orange-700 dark:text-orange-400'
                              : isOverdue
                              ? 'text-red-600 dark:text-red-300'
                              : 'dark:text-gray-100'
                          }`}>
                            {todo.content}
                          </p>
                          {/* Notes preview */}
                          {todo.notes && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">
                              {stripHtmlTags(todo.notes).slice(0, 60)}{stripHtmlTags(todo.notes).length > 60 ? '...' : ''}
                            </p>
                          )}
                          {/* Due date - only show for open todos */}
                          {todo.due_date && todo.status === 'open' && (
                            <p className={`text-xs mt-0.5 ${isOverdue ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400'}`}>
                              Due: {format(new Date(todo.due_date), 'MMM d, yyyy')}
                              {isOverdue && ' (overdue)'}
                            </p>
                          )}
                          {/* Awaiting indicator */}
                          {todo.status === 'awaiting' && awaitingDays !== null && (
                            <span className={`text-xs px-1.5 py-0.5 rounded-full inline-flex items-center gap-0.5 mt-1 ${getAwaitingUrgencyClass(awaitingDays)}`}>
                              <Clock className="w-3 h-3" />
                              {awaitingDays === 0 ? 'Waiting since today' : `Waiting ${awaitingDays}d`}
                            </span>
                          )}
                          {/* Multi-person indicator with stacked avatars */}
                          {todo.persons && todo.persons.length > 1 && (() => {
                            // Get other persons (exclude the current person we're viewing)
                            const currentPersonId = parseInt(id, 10);
                            const otherPersons = todo.persons.filter(p => p.id !== currentPersonId);
                            if (otherPersons.length === 0) return null;

                            return (
                              <div className="flex items-center gap-1 mt-1">
                                <span className="text-xs text-gray-500 dark:text-gray-400">Also:</span>
                                <div className="flex -space-x-1.5" title={otherPersons.map(p => p.name).join(', ')}>
                                  {otherPersons.slice(0, 2).map((person, idx) => (
                                    <Link
                                      key={person.id}
                                      to={`/people/${person.id}`}
                                      className="relative hover:z-10"
                                      style={{ zIndex: 2 - idx }}
                                      onClick={(e) => e.stopPropagation()}
                                    >
                                      {person.thumbnail ? (
                                        <img
                                          src={person.thumbnail}
                                          alt={person.name}
                                          className="w-5 h-5 rounded-full object-cover border border-white"
                                        />
                                      ) : (
                                        <div className="w-5 h-5 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center border border-white">
                                          <User className="w-2.5 h-2.5 text-gray-500" />
                                        </div>
                                      )}
                                    </Link>
                                  ))}
                                  {otherPersons.length > 2 && (
                                    <span className="w-5 h-5 rounded-full bg-gray-200 text-[10px] flex items-center justify-center border border-white text-gray-600">
                                      +{otherPersons.length - 2}
                                    </span>
                                  )}
                                </div>
                              </div>
                            );
                          })()}
                        </div>
                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                          <button
                            onClick={() => {
                              setEditingTodo(todo);
                              setShowTodoModal(true);
                            }}
                            className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                            title="Taak bewerken"
                          >
                            <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                          </button>
                          <button
                            onClick={() => handleDeleteTodo(todo.id)}
                            className="p-1 hover:bg-red-50 rounded"
                            title="Taak verwijderen"
                          >
                            <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-4">
                  Nog geen taken.
                </p>
              )}
            </div>
          </div>
        </aside>
      </div>

      {/* Mobile Todos FAB - visible on screens below lg */}
      <button
        onClick={() => setShowMobileTodos(true)}
        className="fixed bottom-6 right-6 z-40 lg:hidden bg-accent-600 hover:bg-accent-700 text-white rounded-full p-4 shadow-lg transition-colors"
        title="Taken bekijken"
      >
        <CheckSquare2 className="w-6 h-6" />
        {openTodosCount > 0 && (
          <span className="absolute -top-1 -right-1 bg-accent-100 text-accent-700 text-xs font-medium px-2 py-0.5 rounded-full min-w-[20px] text-center">
            {openTodosCount}
          </span>
        )}
      </button>

      {/* Mobile Todos Slide-up Panel */}
      {showMobileTodos && (
        <div className="fixed inset-0 z-50 lg:hidden">
          {/* Backdrop */}
          <div
            className="absolute inset-0 bg-black bg-opacity-50"
            onClick={() => setShowMobileTodos(false)}
          />
          {/* Panel */}
          <div className="absolute bottom-0 left-0 right-0 bg-white rounded-t-xl max-h-[80vh] overflow-hidden flex flex-col animate-slide-up">
            {/* Drag indicator */}
            <div className="flex justify-center pt-3 pb-2">
              <div className="w-10 h-1 bg-gray-300 rounded-full" />
            </div>
            {/* Header */}
            <div className="flex items-center justify-between px-4 pb-3 border-b border-gray-200 dark:border-gray-700">
              <div className="flex items-center gap-2">
                <h2 className="font-semibold text-lg">Taken</h2>
                {openTodosCount > 0 && (
                  <span className="bg-accent-100 text-accent-700 text-xs font-medium px-2 py-0.5 rounded-full">
                    {openTodosCount}
                  </span>
                )}
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => {
                    setEditingTodo(null);
                    setShowTodoModal(true);
                    setShowMobileTodos(false);
                  }}
                  className="btn-secondary text-sm"
                  title="Taak toevoegen"
                >
                  <Plus className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setShowMobileTodos(false)}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full"
                  title="Sluiten"
                >
                  <X className="w-5 h-5 text-gray-500" />
                </button>
              </div>
            </div>
            {/* Scrollable content */}
            <div className="flex-1 overflow-y-auto p-4">
              {sortedTodos.length > 0 ? (
                <div className="space-y-2">
                  {sortedTodos.map((todo) => {
                    const isOverdue = isTodoOverdue(todo);
                    const awaitingDays = getAwaitingDays(todo);
                    return (
                      <div key={todo.id} className="flex items-start p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 group">
                        <button
                          onClick={() => handleToggleTodo(todo)}
                          className="mt-0.5 mr-2 flex-shrink-0"
                          title={todo.status === 'completed' ? 'Heropenen' : todo.status === 'awaiting' ? 'Markeer als voltooid' : 'Voltooien'}
                        >
                          {todo.status === 'completed' ? (
                            <CheckSquare2 className="w-5 h-5 text-accent-600" />
                          ) : todo.status === 'awaiting' ? (
                            <Clock className="w-5 h-5 text-orange-500" />
                          ) : (
                            <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600 dark:text-red-300' : 'text-gray-400 dark:text-gray-500'}`} />
                          )}
                        </button>
                        <div className="flex-1 min-w-0">
                          <p className={`text-sm ${
                            todo.status === 'completed'
                              ? 'line-through text-gray-400 dark:text-gray-500'
                              : todo.status === 'awaiting'
                              ? 'text-orange-700 dark:text-orange-400'
                              : isOverdue
                              ? 'text-red-600 dark:text-red-300'
                              : 'dark:text-gray-100'
                          }`}>
                            {todo.content}
                          </p>
                          {/* Notes preview */}
                          {todo.notes && (
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">
                              {stripHtmlTags(todo.notes).slice(0, 60)}{stripHtmlTags(todo.notes).length > 60 ? '...' : ''}
                            </p>
                          )}
                          {/* Due date - only show for open todos */}
                          {todo.due_date && todo.status === 'open' && (
                            <p className={`text-xs mt-0.5 ${isOverdue ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400'}`}>
                              Due: {format(new Date(todo.due_date), 'MMM d, yyyy')}
                              {isOverdue && ' (overdue)'}
                            </p>
                          )}
                          {/* Awaiting indicator */}
                          {todo.status === 'awaiting' && awaitingDays !== null && (
                            <span className={`text-xs px-1.5 py-0.5 rounded-full inline-flex items-center gap-0.5 mt-1 ${getAwaitingUrgencyClass(awaitingDays)}`}>
                              <Clock className="w-3 h-3" />
                              {awaitingDays === 0 ? 'Waiting since today' : `Waiting ${awaitingDays}d`}
                            </span>
                          )}
                          {/* Multi-person indicator with stacked avatars */}
                          {todo.persons && todo.persons.length > 1 && (() => {
                            // Get other persons (exclude the current person we're viewing)
                            const currentPersonId = parseInt(id, 10);
                            const otherPersons = todo.persons.filter(p => p.id !== currentPersonId);
                            if (otherPersons.length === 0) return null;

                            return (
                              <div className="flex items-center gap-1 mt-1">
                                <span className="text-xs text-gray-500 dark:text-gray-400">Also:</span>
                                <div className="flex -space-x-1.5" title={otherPersons.map(p => p.name).join(', ')}>
                                  {otherPersons.slice(0, 2).map((person, idx) => (
                                    <Link
                                      key={person.id}
                                      to={`/people/${person.id}`}
                                      className="relative hover:z-10"
                                      style={{ zIndex: 2 - idx }}
                                      onClick={(e) => e.stopPropagation()}
                                    >
                                      {person.thumbnail ? (
                                        <img
                                          src={person.thumbnail}
                                          alt={person.name}
                                          className="w-5 h-5 rounded-full object-cover border border-white"
                                        />
                                      ) : (
                                        <div className="w-5 h-5 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center border border-white">
                                          <User className="w-2.5 h-2.5 text-gray-500" />
                                        </div>
                                      )}
                                    </Link>
                                  ))}
                                  {otherPersons.length > 2 && (
                                    <span className="w-5 h-5 rounded-full bg-gray-200 text-[10px] flex items-center justify-center border border-white text-gray-600">
                                      +{otherPersons.length - 2}
                                    </span>
                                  )}
                                </div>
                              </div>
                            );
                          })()}
                        </div>
                        <div className="flex items-center gap-1 ml-2">
                          <button
                            onClick={() => {
                              setEditingTodo(todo);
                              setShowTodoModal(true);
                              setShowMobileTodos(false);
                            }}
                            className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                            title="Taak bewerken"
                          >
                            <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                          </button>
                          <button
                            onClick={() => handleDeleteTodo(todo.id)}
                            className="p-1 hover:bg-red-50 rounded"
                            title="Taak verwijderen"
                          >
                            <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-4">
                  Nog geen taken.
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Modals */}
      <NoteModal
            isOpen={showNoteModal}
            onClose={() => setShowNoteModal(false)}
            onSubmit={handleCreateNote}
            isLoading={createNote.isPending}
            isContactShared={false}
            workspaceIds={[]}
          />
          
          <QuickActivityModal
            isOpen={showActivityModal}
            onClose={() => {
              setShowActivityModal(false);
              setEditingActivity(null);
              setTodoToComplete(null);
              setActivityInitialData(null);
            }}
            onSubmit={handleCreateActivity}
            isLoading={editingActivity ? updateActivity.isPending : createActivity.isPending}
            personId={id}
            initialData={activityInitialData}
            activity={editingActivity}
          />
          
          <CompleteTodoModal
            isOpen={showCompleteModal}
            onClose={() => {
              setShowCompleteModal(false);
              setTodoToComplete(null);
            }}
            todo={todoToComplete}
            onAwaiting={handleMarkAwaiting}
            onComplete={handleJustComplete}
            onCompleteAsActivity={handleCompleteAsActivity}
            hideAwaitingOption={todoToComplete?.status === 'awaiting'}
          />
          
          <TodoModal
            isOpen={showTodoModal}
            onClose={() => {
              setShowTodoModal(false);
              setEditingTodo(null);
            }}
            onSubmit={editingTodo ? handleUpdateTodo : handleCreateTodo}
            isLoading={editingTodo ? updateTodo.isPending : createTodo.isPending}
            todo={editingTodo}
          />
          
          <ContactEditModal
            isOpen={showContactModal}
            onClose={() => setShowContactModal(false)}
            onSubmit={handleSaveContacts}
            isLoading={isSavingContacts}
            contactInfo={acf.contact_info || []}
          />
          
          <ImportantDateModal
            isOpen={showDateModal}
            onClose={() => {
              setShowDateModal(false);
              setEditingDate(null);
            }}
            onSubmit={handleSaveDate}
            isLoading={isSavingDate}
            dateItem={editingDate}
            personId={id}
            allPeople={allPeople || []}
            isPeopleLoading={isPeopleLoading}
          />
          
          <RelationshipEditModal
            isOpen={showRelationshipModal}
            onClose={() => {
              setShowRelationshipModal(false);
              setEditingRelationship(null);
              setEditingRelationshipIndex(null);
            }}
            onSubmit={handleSaveRelationship}
            isLoading={isSavingRelationship}
            relationship={editingRelationship}
            personId={id}
            allPeople={allPeople || []}
            isPeopleLoading={isPeopleLoading}
          />
          
          <AddressEditModal
            isOpen={showAddressModal}
            onClose={() => {
              setShowAddressModal(false);
              setEditingAddress(null);
              setEditingAddressIndex(null);
            }}
            onSubmit={handleSaveAddress}
            isLoading={isSavingAddress}
            address={editingAddress}
          />
          
          <WorkHistoryEditModal
            isOpen={showWorkHistoryModal}
            onClose={() => {
              setShowWorkHistoryModal(false);
              setEditingWorkHistory(null);
              setEditingWorkHistoryIndex(null);
            }}
            onSubmit={handleSaveWorkHistory}
            isLoading={isSavingWorkHistory}
            workHistoryItem={editingWorkHistory}
          />
          
      <PersonEditModal
        isOpen={showPersonEditModal}
        onClose={() => setShowPersonEditModal(false)}
        onSubmit={handleSavePerson}
        isLoading={isSavingPerson}
        person={person}
      />

      <ShareModal
        isOpen={showShareModal}
        onClose={() => setShowShareModal(false)}
        postType="people"
        postId={person.id}
        postTitle={person.name || person.title?.rendered}
      />

      <Suspense fallback={null}>
        <MeetingDetailModal
          isOpen={showMeetingModal}
          onClose={() => {
            setShowMeetingModal(false);
            setSelectedMeeting(null);
          }}
          meeting={selectedMeeting}
        />
      </Suspense>
      </div>
    </PullToRefreshWrapper>
  );
}
