import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft, Edit, Trash2, Star, Mail, Phone,
  MapPin, Globe, Building2, Calendar, Plus, Gift, Heart, Pencil, MessageCircle, X, Camera, Download,
  CheckSquare2, Square, TrendingUp, StickyNote, Share2
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
import { usePerson, usePersonTimeline, usePersonDates, useDeletePerson, useDeleteNote, useDeleteDate, useUpdatePerson, useCreateNote, useCreateActivity, useUpdateActivity, useCreateTodo, useUpdateTodo, useDeleteActivity, useDeleteTodo, usePeople } from '@/hooks/usePeople';
import TimelineView from '@/components/Timeline/TimelineView';
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
import { format, differenceInYears } from 'date-fns';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml, getCompanyName, sanitizePersonAcf } from '@/utils/formatters';
import { downloadVCard } from '@/utils/vcard';
import { isTodoOverdue } from '@/utils/timeline';

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
  
  // Fetch companies where this person is an investor
  const { data: investments = [] } = useQuery({
    queryKey: ['investments', id],
    queryFn: async () => {
      const response = await prmApi.getInvestments(id);
      return response.data;
    },
    enabled: !!id,
  });
  
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
  useDocumentTitle(person?.name || person?.title?.rendered || person?.title || 'Person');

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
    if (!window.confirm('Are you sure you want to delete this person?')) {
      return;
    }
    
    try {
      await deletePerson.mutateAsync(id);
      // Navigation will happen in onSuccess callback
      navigate('/people');
    } catch {
      alert('Failed to delete person. Please try again.');
    }
  };

  // Helper function to format phone number for tel: link
  // Removes all non-digit characters except + at the start, and removes Unicode marks
  const formatPhoneForTel = (phone) => {
    if (!phone) return '';
    // Remove all Unicode marks and invisible characters
    let cleaned = phone.replace(/[\u200B-\u200D\uFEFF\u200E\u200F\u202A-\u202E]/g, '');
    // Extract + if present at the start
    const hasPlus = cleaned.startsWith('+');
    // Remove all non-digit characters
    cleaned = cleaned.replace(/\D/g, '');
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
      alert('Failed to save contacts. Please try again.');
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
            _visibility: 'private',
          },
        });
      }
      
      queryClient.invalidateQueries({ queryKey: ['person-dates', id] });
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      setShowDateModal(false);
      setEditingDate(null);
    } catch {
      alert('Failed to save date. Please try again.');
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
      alert('Failed to save relationship. Please try again.');
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
      alert('Failed to save address. Please try again.');
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
        company: data.company || null,
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
      alert('Failed to save work history. Please try again.');
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
        is_favorite: data.is_favorite,
        _visibility: data.visibility || 'private',
        _assigned_workspaces: data.assigned_workspaces || [],
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
      alert('Failed to save person. Please try again.');
    } finally {
      setIsSavingPerson(false);
    }
  };

  // Handle deleting an address
  const handleDeleteAddress = async (index) => {
    if (!window.confirm('Are you sure you want to delete this address?')) {
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
    if (!window.confirm('Are you sure you want to delete this relationship?')) {
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
      deleteInverse = window.confirm('Do you also want to delete the inverse relationship from the other person?');
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
    if (!window.confirm('Are you sure you want to delete this important date?')) {
      return;
    }
    
    await deleteDate.mutateAsync({ dateId, personId: id });
  };

  // Handle deleting a note
  const handleDeleteNote = async (noteId) => {
    if (!window.confirm('Are you sure you want to delete this note?')) {
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
      alert('Failed to create note. Please try again.');
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
            data: {
              content: todoToComplete.content,
              due_date: todoToComplete.due_date,
              is_completed: true,
            },
            personId: id,
          });
          setTodoToComplete(null);
          setActivityInitialData(null);
        }
      }
      
      setShowActivityModal(false);
    } catch {
      alert('Failed to save activity. Please try again.');
    }
  };

  // Handle creating a todo
  const handleCreateTodo = async (data) => {
    try {
      await createTodo.mutateAsync({ personId: id, data });
      setShowTodoModal(false);
      setEditingTodo(null);
    } catch {
      alert('Failed to create todo. Please try again.');
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
      alert('Failed to update todo. Please try again.');
    }
  };

  // Handle toggling todo completion
  const handleToggleTodo = async (todo) => {
    // If completing a todo, show the complete modal
    if (!todo.is_completed) {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
      return;
    }
    
    // If uncompleting, just update directly
    try {
      await updateTodo.mutateAsync({
        todoId: todo.id,
        data: {
          content: todo.content,
          due_date: todo.due_date,
          is_completed: false,
        },
        personId: id,
      });
    } catch {
      alert('Failed to update todo. Please try again.');
    }
  };
  
  // Handle just completing a todo (no activity)
  const handleJustComplete = async () => {
    if (!todoToComplete) return;
    
    try {
      await updateTodo.mutateAsync({
        todoId: todoToComplete.id,
        data: {
          content: todoToComplete.content,
          due_date: todoToComplete.due_date,
          is_completed: true,
        },
        personId: id,
      });
      
      setShowCompleteModal(false);
      setTodoToComplete(null);
    } catch {
      alert('Failed to complete todo. Please try again.');
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

  // Handle deleting an activity
  const handleDeleteActivity = async (activityId) => {
    if (!window.confirm('Are you sure you want to delete this activity?')) {
      return;
    }
    
    await deleteActivity.mutateAsync({ activityId, personId: id });
  };

  // Handle deleting a todo
  const handleDeleteTodo = async (todoId) => {
    if (!window.confirm('Are you sure you want to delete this todo?')) {
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
    if (!window.confirm('Are you sure you want to delete this work history item?')) {
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
      alert('Please select an image file');
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('Image size must be less than 5MB');
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
      alert('Failed to upload photo. Please try again.');
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
        companyMap,
        personDates: personDates || [],
      });
    } catch {
      alert('Failed to export vCard. Please try again.');
    }
  };

  // Fetch company names for work history entries
  const companyIds = person?.acf?.work_history
    ?.map(job => job.company)
    .filter(Boolean) || [];
  
  const companyQueries = useQueries({
    queries: companyIds.map(companyId => ({
      queryKey: ['company', companyId],
      queryFn: async () => {
        const response = await wpApi.getCompany(companyId, { _embed: true });
        return response.data;
      },
      enabled: !!companyId,
    })),
  });

  // Get current job company IDs (jobs without end_date)
  const currentJobCompanyIds = useMemo(() => {
    if (!person?.acf?.work_history) return [];
    return person.acf.work_history
      .filter(job => !job.end_date && job.company)
      .map(job => job.company);
  }, [person?.acf?.work_history]);

  // Fetch colleagues for current jobs
  const colleagueQueries = useQueries({
    queries: currentJobCompanyIds.map(companyId => ({
      queryKey: ['company-people', companyId],
      queryFn: async () => {
        const response = await prmApi.getCompanyPeople(companyId);
        return { companyId, ...response.data };
      },
      enabled: !!companyId,
    })),
  });

  // Process colleagues data - combine all current employees from all companies, excluding self
  const colleagues = useMemo(() => {
    const colleagueMap = new Map();
    
    colleagueQueries.forEach(query => {
      if (query.data?.current) {
        query.data.current.forEach(employee => {
          // Exclude the current person
          if (employee.id !== parseInt(id)) {
            // If already in map, just add the company; otherwise create new entry
            if (colleagueMap.has(employee.id)) {
              const existing = colleagueMap.get(employee.id);
              if (!existing.companies.includes(query.data.companyId)) {
                existing.companies.push(query.data.companyId);
              }
            } else {
              colleagueMap.set(employee.id, {
                ...employee,
                companies: [query.data.companyId],
              });
            }
          }
        });
      }
    });
    
    // Convert to array and sort alphabetically by name
    return Array.from(colleagueMap.values()).sort((a, b) => 
      (a.name || '').localeCompare(b.name || '')
    );
  }, [colleagueQueries, id]);

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

  // Create a map of company ID to company data (name and logo)
  const companyMap = {};
  companyQueries.forEach((query, index) => {
    if (query.data) {
      const companyId = companyIds[index];
      companyMap[companyId] = {
        name: getCompanyName(query.data),
        logo: query.data._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
              query.data._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url ||
              null,
      };
    }
  });
  
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

  // Extract and sort todos from timeline
  // Incomplete todos first (by due date), then completed
  const sortedTodos = useMemo(() => {
    if (!timeline) return [];
    
    const todos = timeline.filter(item => item.type === 'todo');
    
    return todos.sort((a, b) => {
      // Completed todos go to the bottom
      if (a.is_completed && !b.is_completed) return 1;
      if (!a.is_completed && b.is_completed) return -1;

      // For incomplete todos, sort by due date (earliest first)
      // Todos without due date go after those with due dates
      if (!a.is_completed && !b.is_completed) {
        if (a.due_date && b.due_date) {
          return new Date(a.due_date) - new Date(b.due_date);
        }
        if (a.due_date && !b.due_date) return -1;
        if (!a.due_date && b.due_date) return 1;
      }

      // For completed todos, sort by most recently completed (newest first)
      return new Date(b.created) - new Date(a.created);
    });
  }, [timeline]);
  
  // Redirect if person is trashed
  useEffect(() => {
    if (person?.status === 'trash') {
      navigate('/people', { replace: true });
    }
  }, [person, navigate]);
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error || !person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600">Failed to load person.</p>
        <Link to="/people" className="btn-secondary mt-4">Back to people</Link>
      </div>
    );
  }
  
  // Don't render if person is trashed (redirect will happen)
  if (person.status === 'trash') {
    return null;
  }
  
  const acf = person.acf || {};
  
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
  
  // Sort social links by display order, and add WhatsApp if mobile exists
  const sortedSocialLinks = (() => {
    const links = [...socialLinks];
    
    // Add WhatsApp if there's a mobile number
    if (mobileContact) {
      links.push({
        contact_type: 'whatsapp',
        contact_value: `https://wa.me/${mobileContact.contact_value.replace(/[^0-9+]/g, '')}`,
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
      case 'twitter': return 'text-[#1DA1F2]';
      case 'bluesky': return 'text-[#00A8E8]'; // Bluesky brand color
      case 'threads': return 'text-[#000000]'; // Threads brand color (black)
      case 'whatsapp': return 'text-[#25D366]'; // WhatsApp brand color
      case 'website': return 'text-gray-600';
      default: return 'text-gray-600';
    }
  };
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to="/people" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to people</span>
        </Link>
        <div className="flex gap-2">
          <button onClick={handleExportVCard} className="btn-secondary">
            <Download className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Export vCard</span>
          </button>
          <button onClick={() => setShowShareModal(true)} className="btn-secondary" title="Share">
            <Share2 className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Share</span>
          </button>
          <button onClick={() => setShowPersonEditModal(true)} className="btn-secondary">
            <Edit className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Edit</span>
          </button>
          <button onClick={handleDelete} className="btn-danger">
            <Trash2 className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Delete</span>
          </button>
        </div>
      </div>
      
      {/* Profile header */}
      <div className="card p-6">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
          <div className="relative group">
            {person.thumbnail ? (
              <img
                src={person.thumbnail}
                alt={person.name}
                className="w-28 h-28 rounded-full object-cover"
              />
            ) : (
              <div className="w-28 h-28 bg-gray-200 rounded-full flex items-center justify-center">
                <span className="text-3xl font-medium text-gray-500">
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
              <h1 className="text-2xl font-bold">
                {person.name}
                {isDeceased && <span className="ml-1 text-gray-500">†</span>}
              </h1>
              {person.is_favorite && (
                <Star className="w-5 h-5 text-yellow-400 fill-current" />
              )}
            </div>
            {acf.nickname && (
              <p className="text-gray-500">"{acf.nickname}"</p>
            )}
            {isDeceased && deathDateValue && (
              <p className="text-gray-500 text-sm inline-flex items-center flex-wrap">
                {getGenderSymbol(acf.gender) && <span>{getGenderSymbol(acf.gender)}</span>}
                {getGenderSymbol(acf.gender) && acf.pronouns && <span>&nbsp;—&nbsp;</span>}
                {acf.pronouns && <span>{acf.pronouns}</span>}
                {(getGenderSymbol(acf.gender) || acf.pronouns) && <span>&nbsp;—&nbsp;</span>}
                <span>Died {format(deathDateValue, deathYearUnknown ? 'MMMM d' : 'MMMM d, yyyy')}{age !== null && ` at age ${age}`}</span>
              </p>
            )}
            {!isDeceased && (getGenderSymbol(acf.gender) || acf.pronouns || age !== null) && (
              <p className="text-gray-500 text-sm inline-flex items-center flex-wrap">
                {getGenderSymbol(acf.gender) && <span>{getGenderSymbol(acf.gender)}</span>}
                {getGenderSymbol(acf.gender) && acf.pronouns && <span>&nbsp;—&nbsp;</span>}
                {acf.pronouns && <span>{acf.pronouns}</span>}
                {(getGenderSymbol(acf.gender) || acf.pronouns) && age !== null && <span>&nbsp;—&nbsp;</span>}
                {age !== null && <span>{age} years old</span>}
              </p>
            )}
            <div>
              <div className="flex flex-wrap gap-2 items-center">
                {person.labels && person.labels.length > 0 && (
                  person.labels.map((label, index) => (
                    <span
                      key={index}
                      className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 group/label"
                    >
                      {label}
                      <button
                        onClick={() => handleRemoveLabel(label)}
                        className="opacity-0 group-hover/label:opacity-100 transition-opacity hover:bg-gray-200 rounded-full p-0.5"
                        title="Remove label"
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
                      className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors"
                    >
                      <Plus className="w-3 h-3 mr-1" />
                      Add label
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
                      <option value="">Select a label...</option>
                      {availableLabelsToAdd.map(label => (
                        <option key={label.id} value={label.id}>
                          {label.name}
                        </option>
                      ))}
                    </select>
                    <button
                      onClick={handleAddLabel}
                      disabled={!selectedLabelToAdd}
                      className="text-xs px-2 py-1 bg-primary-600 text-white rounded hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Add
                    </button>
                    <button
                      onClick={() => {
                        setIsAddingLabel(false);
                        setSelectedLabelToAdd('');
                      }}
                      className="text-xs px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
                    >
                      Cancel
                    </button>
                  </div>
                )}
              </div>
              {sortedSocialLinks.length > 0 && (
                <div className="flex items-center gap-3 mt-4">
                  {sortedSocialLinks.map((contact, index) => {
                    const SocialIcon = getSocialIcon(contact.contact_type);
                    const iconColor = getSocialIconColor(contact.contact_type);
                    
                    // Ensure URL has protocol
                    let url = contact.contact_value;
                    if (!url.match(/^https?:\/\//i)) {
                      url = `https://${url}`;
                    }
                    
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
      <div className="border-b border-gray-200">
        <nav className="flex gap-8">
          <button
            onClick={() => setActiveTab('profile')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'profile'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Profile
          </button>
          <button
            onClick={() => setActiveTab('timeline')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'timeline'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Timeline
          </button>
          <button
            onClick={() => setActiveTab('work')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'work'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Work
          </button>
        </nav>
      </div>

      {/* Tab Content */}
      <div className="space-y-6">
        {/* Profile Tab */}
        {activeTab === 'profile' && (
          <div className="columns-1 md:columns-2 gap-6">
            {/* Contact info - only show for living people */}
          {!isDeceased && (
            <div className="card p-6 break-inside-avoid mb-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold">Contact information</h2>
                <button
                  onClick={() => setShowContactModal(true)}
                  className="btn-secondary text-sm"
                >
                  <Pencil className="w-4 h-4 md:mr-1" />
                  <span className="hidden md:inline">Edit</span>
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
                            <span className="text-sm text-gray-500">{contact.contact_label || contact.contact_type}: </span>
                            {linkHref ? (
                              <a
                                href={linkHref}
                                target={linkTarget || undefined}
                                rel={linkTarget === '_blank' ? 'noopener noreferrer' : undefined}
                                className="text-primary-600 hover:text-primary-700 hover:underline"
                              >
                                {contact.contact_value}
                              </a>
                            ) : (
                              <span>{contact.contact_value}</span>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })})()}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                No contact information yet. <button onClick={() => setShowContactModal(true)} className="text-primary-600 hover:underline">Add some</button>
              </p>
            )}
            </div>
          )}

          {/* Important Dates */}
          <div className="card p-6 break-inside-avoid mb-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Important dates</h2>
              <button
                onClick={() => {
                  setEditingDate(null);
                  setShowDateModal(true);
                }}
                className="btn-secondary text-sm"
                title="Add important date"
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
                      <div className="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                        {isDied ? (
                          <span className="text-gray-500 text-lg font-semibold">†</span>
                        ) : (
                          <Icon className="w-4 h-4 text-gray-500" />
                        )}
                      </div>
                      <div className="flex-1">
                        <p className="text-sm font-medium">{date.title}</p>
                        <p className="text-xs text-gray-500">
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
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Edit date"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </button>
                        <button
                          onClick={() => handleDeleteDate(date.id)}
                          className="p-1 hover:bg-red-50 rounded"
                          title="Delete date"
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
                No important dates yet. <button onClick={() => { setEditingDate(null); setShowDateModal(true); }} className="text-primary-600 hover:underline">Add one</button>
              </p>
            )}
          </div>

            {/* Addresses - only show for living people */}
            {!isDeceased && (
              <div className="card p-6 break-inside-avoid mb-6">
                <div className="flex items-center justify-between mb-4">
                  <h2 className="font-semibold">Addresses</h2>
                  <button
                    onClick={() => {
                      setEditingAddress(null);
                      setEditingAddressIndex(null);
                      setShowAddressModal(true);
                    }}
                    className="btn-secondary text-sm"
                    title="Add address"
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
                              <p className="text-xs text-gray-500 mb-1">{address.address_label}</p>
                            )}
                            <a
                              href={googleMapsUrl}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-primary-600 hover:text-primary-700 hover:underline text-sm"
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
                              className="p-1 hover:bg-gray-100 rounded"
                              title="Edit address"
                            >
                              <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                            </button>
                            <button
                              onClick={() => handleDeleteAddress(index)}
                              className="p-1 hover:bg-red-50 rounded"
                              title="Delete address"
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
                    No addresses yet. <button onClick={() => { setEditingAddress(null); setEditingAddressIndex(null); setShowAddressModal(true); }} className="text-primary-600 hover:underline">Add one</button>
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
                <h2 className="font-semibold">Relationships</h2>
                <div className="flex items-center gap-2">
                  <Link
                    to={`/people/${id}/family-tree`}
                    className="btn-secondary text-sm"
                  >
                    View family tree
                  </Link>
                  <button
                    onClick={() => {
                      setEditingRelationship(null);
                      setEditingRelationshipIndex(null);
                      setShowRelationshipModal(true);
                    }}
                    className="btn-secondary text-sm"
                    title="Add relationship"
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
                      <div key={index} className="flex items-center p-2 rounded hover:bg-gray-50 group">
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
                            <div className="w-8 h-8 bg-gray-200 rounded-full mr-2 flex items-center justify-center">
                              <span className="text-xs font-medium text-gray-500">
                                {decodeHtml(rel.person_name)?.[0] || '?'}
                              </span>
                            </div>
                          )}
                          <div>
                            <p className="text-sm font-medium">
                              {decodeHtml(rel.person_name) || `Person #${rel.related_person}`}
                              {personDeceasedMap[rel.related_person] && (
                                <span className="text-gray-400 ml-1" title="Deceased">†</span>
                              )}
                            </p>
                            <p className="text-xs text-gray-500">{decodeHtml(rel.relationship_name || rel.relationship_label)}</p>
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
                            className="p-1 hover:bg-gray-100 rounded"
                            title="Edit relationship"
                          >
                            <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                          </button>
                          <button
                            onClick={() => handleDeleteRelationship(originalIndex)}
                            className="p-1 hover:bg-red-50 rounded"
                            title="Delete relationship"
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
                  No relationships yet. <button onClick={() => { setEditingRelationship(null); setEditingRelationshipIndex(null); setShowRelationshipModal(true); }} className="text-primary-600 hover:underline">Add one</button>
                </p>
              )}
            </div>
          </div>
        )}

        {/* Timeline Tab */}
        {activeTab === 'timeline' && (
          <div className="columns-1 md:columns-2 gap-6">
            {/* Todos */}
            <div className="card p-6 break-inside-avoid mb-6">
              <div className="flex items-center justify-between mb-3">
                <h2 className="font-semibold">Todos</h2>
                <button
                  onClick={() => {
                    setEditingTodo(null);
                    setShowTodoModal(true);
                  }}
                  className="btn-secondary text-sm"
                  title="Add todo"
                >
                  <Plus className="w-4 h-4" />
                </button>
              </div>
              {sortedTodos.length > 0 ? (
                <div className="space-y-2">
                  {sortedTodos.map((todo) => {
                    const isOverdue = isTodoOverdue(todo);
                    return (
                      <div key={todo.id} className="flex items-start p-2 rounded hover:bg-gray-50 group">
                        <button
                          onClick={() => handleToggleTodo(todo)}
                          className="mt-0.5 mr-2 flex-shrink-0"
                          title={todo.is_completed ? 'Mark as incomplete' : 'Mark as complete'}
                        >
                          {todo.is_completed ? (
                            <CheckSquare2 className="w-5 h-5 text-primary-600" />
                          ) : (
                            <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600' : 'text-gray-400'}`} />
                          )}
                        </button>
                        <div className="flex-1 min-w-0">
                          <p className={`text-sm ${todo.is_completed ? 'line-through text-gray-400' : isOverdue ? 'text-red-600' : ''}`}>
                            {todo.content}
                          </p>
                          {todo.due_date && (
                            <p className={`text-xs mt-0.5 ${isOverdue && !todo.is_completed ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
                              Due: {format(new Date(todo.due_date), 'MMM d, yyyy')}
                              {isOverdue && !todo.is_completed && ' (overdue)'}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                          <button
                            onClick={() => {
                              setEditingTodo(todo);
                              setShowTodoModal(true);
                            }}
                            className="p-1 hover:bg-gray-100 rounded"
                            title="Edit todo"
                          >
                            <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                          </button>
                          <button
                            onClick={() => handleDeleteTodo(todo.id)}
                            className="p-1 hover:bg-red-50 rounded"
                            title="Delete todo"
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
                  No todos yet.
                </p>
              )}
            </div>

            {/* Timeline */}
            <div className="card p-6 break-inside-avoid mb-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold">Timeline</h2>
                <div className="flex gap-2">
                  <button
                    onClick={() => setShowNoteModal(true)}
                    className="btn-secondary text-sm"
                    title="Add note"
                  >
                    <StickyNote className="w-4 h-4 md:mr-1" />
                    <span className="hidden md:inline">Note</span>
                  </button>
                  <button
                    onClick={() => setShowActivityModal(true)}
                    className="btn-secondary text-sm"
                    title="Add activity"
                  >
                    <MessageCircle className="w-4 h-4 md:mr-1" />
                    <span className="hidden md:inline">Activity</span>
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
          </div>
        )}

        {/* Work Tab */}
        {activeTab === 'work' && (
          <div className="columns-1 md:columns-2 gap-6">
            {/* Work history */}
          <div className="card p-6 break-inside-avoid mb-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Work history</h2>
              <button
                onClick={() => {
                  setEditingWorkHistory(null);
                  setEditingWorkHistoryIndex(null);
                  setShowWorkHistoryModal(true);
                }}
                className="btn-secondary text-sm"
                title="Add work history"
              >
                <Plus className="w-4 h-4" />
              </button>
            </div>
            {sortedWorkHistory?.length > 0 ? (
              <div className="space-y-4">
                {sortedWorkHistory.map((job, index) => {
                  const companyData = job.company ? companyMap[job.company] : null;
                  const originalIndex = job.originalIndex;
                  
                  return (
                    <div key={originalIndex} className="flex items-start group">
                      {companyData?.logo ? (
                        <img
                          src={companyData.logo}
                          alt={companyData.name}
                          className="w-20 h-20 rounded-lg object-contain bg-white mr-3 flex-shrink-0"
                        />
                      ) : (
                        <div className="w-20 h-20 bg-white rounded-lg flex items-center justify-center mr-3 flex-shrink-0 border border-gray-200">
                          <Building2 className="w-10 h-10 text-gray-500" />
                        </div>
                      )}
                      <div className="flex-1 min-w-0">
                        <p className="font-medium">{job.job_title}</p>
                        {job.company && companyData && (
                          <Link 
                            to={`/companies/${job.company}`}
                            className="text-sm text-primary-600 hover:underline"
                          >
                            {companyData.name}
                          </Link>
                        )}
                        <p className="text-sm text-gray-500">
                          {job.start_date && format(new Date(job.start_date), 'MMM yyyy')}
                          {' - '}
                          {job.is_current ? 'Present' : job.end_date ? format(new Date(job.end_date), 'MMM yyyy') : ''}
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
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Edit work history"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </button>
                        <button
                          onClick={() => handleDeleteWorkHistory(originalIndex)}
                          className="p-1 hover:bg-red-50 rounded"
                          title="Delete work history"
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
                No work history yet. <button onClick={() => { setEditingWorkHistory(null); setEditingWorkHistoryIndex(null); setShowWorkHistoryModal(true); }} className="text-primary-600 hover:underline">Add one</button>
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
                {investments.map((company) => (
                  <Link
                    key={company.id}
                    to={`/companies/${company.id}`}
                    className="flex items-center p-2 rounded-lg hover:bg-gray-50 transition-colors group"
                  >
                    {company.thumbnail ? (
                      <img
                        src={company.thumbnail}
                        alt={company.name}
                        className="w-12 h-12 rounded-lg object-contain bg-white border border-gray-200"
                      />
                    ) : (
                      <div className="w-12 h-12 bg-white rounded-lg flex items-center justify-center border border-gray-200">
                        <Building2 className="w-6 h-6 text-gray-400" />
                      </div>
                    )}
                    <div className="ml-3">
                      <p className="text-sm font-medium group-hover:text-primary-600">{company.name}</p>
                      {company.industry && (
                        <p className="text-xs text-gray-500">{company.industry}</p>
                      )}
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          )}

          {/* Colleagues - only show if person has current job(s) */}
          {colleagues.length > 0 && (
            <div className="card p-6 break-inside-avoid mb-6">
              <div className="flex items-center justify-between mb-3">
                <h2 className="font-semibold">Colleagues</h2>
                <span className="text-xs text-gray-500">{colleagues.length} {colleagues.length === 1 ? 'colleague' : 'colleagues'}</span>
              </div>
              <div className="space-y-2">
                {colleagues.map((colleague) => (
                  <Link
                    key={colleague.id}
                    to={`/people/${colleague.id}`}
                    className="flex items-center p-2 rounded hover:bg-gray-50"
                  >
                    {colleague.thumbnail ? (
                      <img
                        src={colleague.thumbnail}
                        alt={colleague.name || ''}
                        className="w-8 h-8 rounded-full object-cover mr-2"
                      />
                    ) : (
                      <div className="w-8 h-8 bg-gray-200 rounded-full mr-2 flex items-center justify-center">
                        <span className="text-xs font-medium text-gray-500">
                          {colleague.name?.[0] || '?'}
                        </span>
                      </div>
                    )}
                    <div className="min-w-0">
                      <p className="text-sm font-medium truncate">{colleague.name}</p>
                      {colleague.job_title && (
                        <p className="text-xs text-gray-500 truncate">{colleague.job_title}</p>
                      )}
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          )}
          </div>
        )}
      </div>

      {/* Modals */}
      <NoteModal
            isOpen={showNoteModal}
            onClose={() => setShowNoteModal(false)}
            onSubmit={handleCreateNote}
            isLoading={createNote.isPending}
            isContactShared={person?.acf?._visibility === 'workspace' || person?.acf?._visibility === 'shared'}
            workspaceIds={person?.acf?._visibility === 'workspace' ? (person?.acf?._assigned_workspaces || []) : []}
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
            onComplete={handleJustComplete}
            onCompleteAsActivity={handleCompleteAsActivity}
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
    </div>
  );
}
