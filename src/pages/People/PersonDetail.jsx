import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft, Trash2, Mail, Phone,
  MapPin, Globe, Building2, Calendar, Plus, Pencil, MessageCircle, X, Camera, Download,
  CheckSquare2, TrendingUp, StickyNote, ExternalLink, Gavel, RefreshCw
} from 'lucide-react';
import { usePerson, usePersonTimeline, useDeleteNote, useUpdatePerson, useCreateNote, useCreateActivity, useUpdateActivity, useCreateTodo, useUpdateTodo, useDeleteActivity, useDeleteTodo, usePeople } from '@/hooks/usePeople';
import TimelineView from '@/components/Timeline/TimelineView';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';
import DisciplineCaseTable from '@/components/DisciplineCaseTable';
import { usePersonDisciplineCases } from '@/hooks/useDisciplineCases';
import NoteModal from '@/components/Timeline/NoteModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';
import TodoModal from '@/components/Timeline/TodoModal';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import ContactEditModal from '@/components/ContactEditModal';
import RelationshipEditModal from '@/components/RelationshipEditModal';
import CustomFieldsSection from '@/components/CustomFieldsSection';
import FinancesCard from '@/components/FinancesCard';
import VOGCard from '@/components/VOGCard';
import SportlinkCard from '@/components/SportlinkCard';
import { format, parseISO, differenceInYears } from '@/utils/dateFormat';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml, getTeamName, sanitizePersonAcf, isValidDate, getGenderSymbol, getVogStatus, formatPhoneForTel } from '@/utils/formatters';
import { downloadVCard } from '@/utils/vcard';
import { getSocialIcon, getSocialIconColor, sortSocialLinks, SOCIAL_TYPES } from '@/utils/socialIcons';
import TodoItem from '@/components/TodoItem.jsx';
import TabButton from '@/components/TabButton.jsx';

export default function PersonDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { data: person, isLoading, error } = usePerson(id);
  const { data: timeline } = usePersonTimeline(id);
  const deleteNote = useDeleteNote();
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

  // Fetch current user for capability check
  const { data: currentUser } = useCurrentUser();

  const canAccessFairplay = currentUser?.can_access_fairplay ?? false;

  // Fetch discipline cases for this person (fairplay users only)
  const { data: disciplineCases, isLoading: isDisciplineCasesLoading } = usePersonDisciplineCases(id, {
    enabled: canAccessFairplay,
  });

  // Check if person has any discipline cases (for hiding empty tab)
  const hasDisciplineCases = disciplineCases && disciplineCases.length > 0;

  const [activeTab, setActiveTab] = useState('profile');
  const [isAddingLabel, setIsAddingLabel] = useState(false);
  const [selectedLabelToAdd, setSelectedLabelToAdd] = useState('');
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [syncStatus, setSyncStatus] = useState(null); // null | 'success' | 'error'
  const fileInputRef = useRef(null);
  const config = window.rondoConfig || {};

  // Modal states
  const [showNoteModal, setShowNoteModal] = useState(false);
  const [showActivityModal, setShowActivityModal] = useState(false);
  const [showTodoModal, setShowTodoModal] = useState(false);
  const [showContactModal, setShowContactModal] = useState(false);
  const [showRelationshipModal, setShowRelationshipModal] = useState(false);
  const [isSavingContacts, setIsSavingContacts] = useState(false);
  const [isSavingRelationship, setIsSavingRelationship] = useState(false);
  const [editingTodo, setEditingTodo] = useState(null);
  const [editingActivity, setEditingActivity] = useState(null);
  const [editingRelationship, setEditingRelationship] = useState(null);
  const [editingRelationshipIndex, setEditingRelationshipIndex] = useState(null);
  
  // Complete todo flow states
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);

  // Mobile todos panel state
  const [showMobileTodos, setShowMobileTodos] = useState(false);

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

  // Get birthdate from ACF field
  const birthDate = person?.acf?.birthdate && person.acf.birthdate !== ''
    ? new Date(person.acf.birthdate)
    : null;

  // Deceased status (is_deceased always returns false now - death date feature removed)
  const isDeceased = person?.is_deceased || false;

  // Calculate age - current age only (death date feature removed)
  const age = birthDate ? differenceInYears(new Date(), birthDate) : null;

  // Format birthdate for display: "6 feb 1982"
  const formattedBirthdate = birthDate ? format(birthDate, 'd MMM yyyy') : null;

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
      });
    } catch {
      alert('vCard kon niet worden geëxporteerd. Probeer het opnieuw.');
    }
  };

  // Handle Sportlink sync
  const handleSyncFromSportlink = async () => {
    const knvbId = person?.acf?.['knvb-id'];
    if (!knvbId || isSyncing) return;

    setIsSyncing(true);
    setSyncStatus(null);
    try {
      await prmApi.syncFromSportlink(knvbId);
      setSyncStatus('success');
      await queryClient.invalidateQueries({ queryKey: ['people', 'detail', id] });
      setTimeout(() => setSyncStatus(null), 3000);
    } catch {
      setSyncStatus('error');
      setTimeout(() => setSyncStatus(null), 3000);
    } finally {
      setIsSyncing(false);
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
        // Legacy data without entity_type: use unified endpoint
        const response = await wpApi.getEntity(entityId);
        return { ...response.data, _entityType: response.data._entity_type || response.data.type };
      },
      enabled: !!entityId,
      retry: false, // Don't retry 404s
      staleTime: 5 * 60 * 1000, // Cache for 5 minutes
    })),
  });

  // Create a map of person ID to age for sorting (using birth_year from allPeople)
  const personAgeMap = useMemo(() => {
    const ageMap = {};
    if (!allPeople) return ageMap;

    allPeople.forEach(p => {
      if (p.birth_year) {
        const currentYear = new Date().getFullYear();
        ageMap[p.id] = currentYear - p.birth_year;
      } else {
        ageMap[p.id] = -1;
      }
    });
    return ageMap;
  }, [allPeople]);

  // Create a map of person ID to deceased status (using is_deceased from allPeople)
  const personDeceasedMap = useMemo(() => {
    const map = {};
    if (!allPeople) return map;

    allPeople.forEach(p => {
      map[p.id] = p.is_deceased || false;
    });
    return map;
  }, [allPeople]);

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

  // If on discipline tab but no cases (after loading), switch to profile
  useEffect(() => {
    if (activeTab === 'discipline' && !isDisciplineCasesLoading && !hasDisciplineCases && canAccessFairplay) {
      setActiveTab('profile');
    }
  }, [activeTab, isDisciplineCasesLoading, hasDisciplineCases, canAccessFairplay]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
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

  // Extract social links for header display
  const socialLinks = acf.contact_info?.filter(contact => SOCIAL_TYPES.includes(contact.contact_type)) || [];

  // Check if there's a mobile number for WhatsApp
  const mobileContact = acf.contact_info?.find(contact => contact.contact_type === 'mobile');

  // Sort social links by display order, and add WhatsApp, Sportlink, and Freescout if applicable
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
        contact_value: `https://club.sportlink.com/member/member-details/${acf['knvb-id']}/general`,
      });
    }

    // Add FreeScout if there's a FreeScout ID AND a configured URL
    const freescoutUrl = window.rondoConfig?.freescoutUrl;
    if (acf['freescout-id'] && freescoutUrl) {
      links.push({
        contact_type: 'freescout',
        contact_value: `${freescoutUrl}/customers/${acf['freescout-id']}`,
      });
    }

    return sortSocialLinks(links);
  })();

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
          {config.isAdmin && acf['knvb-id'] && (
            <button
              onClick={handleSyncFromSportlink}
              disabled={isSyncing}
              className="btn-secondary"
            >
              <RefreshCw className={`w-4 h-4 md:mr-2 ${isSyncing ? 'animate-spin' : ''}`} />
              <span className="hidden md:inline">
                {syncStatus === 'success' ? 'Bijgewerkt!' : syncStatus === 'error' ? 'Fout' : 'Ververs uit Sportlink'}
              </span>
            </button>
          )}
          <button onClick={handleExportVCard} className="btn-secondary">
            <Download className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Exporteer vCard</span>
          </button>
        </div>
      </div>
      
      {/* Profile header */}
      <div className={`card p-6 relative ${acf['financiele-blokkade'] ? 'bg-red-50 dark:bg-red-950/30' : acf.former_member ? 'bg-gray-50 dark:bg-gray-900/30' : ''}`}>
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
            <div className="absolute inset-0 rounded-full bg-black/0 group-hover:bg-black/50 transition-all duration-200 flex items-center justify-center cursor-pointer"
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
              <h1 className="text-2xl font-bold text-brand-gradient">
                {person.name}
                {isDeceased && <span className="ml-1 text-gray-500 dark:text-gray-400">&#8224;</span>}
              </h1>
              {acf.former_member && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                  Oud-lid
                </span>
              )}
              {acf['huidig-vrijwilliger'] && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-electric-cyan text-white dark:bg-electric-cyan dark:text-white">
                  Vrijwilliger
                </span>
              )}
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
                          className="text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light hover:underline"
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
            {(getGenderSymbol(acf.gender) || acf.pronouns || age !== null || acf['financiele-blokkade'] || acf['lid-tot']) && (
              <p className="text-gray-500 dark:text-gray-400 text-sm inline-flex items-center flex-wrap">
                {getGenderSymbol(acf.gender) && <span>{getGenderSymbol(acf.gender)}</span>}
                {getGenderSymbol(acf.gender) && acf.pronouns && <span>&nbsp;—&nbsp;</span>}
                {acf.pronouns && <span>{acf.pronouns}</span>}
                {(getGenderSymbol(acf.gender) || acf.pronouns) && age !== null && <span>&nbsp;—&nbsp;</span>}
                {age !== null && formattedBirthdate && <span>{age} jaar ({formattedBirthdate})</span>}
                {age !== null && !formattedBirthdate && <span>{age} jaar</span>}
                {acf['financiele-blokkade'] && (
                  <>
                    {(getGenderSymbol(acf.gender) || acf.pronouns || age !== null) && <span>&nbsp;—&nbsp;</span>}
                    <span className="text-red-600 dark:text-red-400 font-medium">Financiële blokkade</span>
                  </>
                )}
                {acf['lid-tot'] && (
                  <>
                    {(getGenderSymbol(acf.gender) || acf.pronouns || age !== null || acf['financiele-blokkade']) && <span>&nbsp;—&nbsp;</span>}
                    <span>Lid tot: {format(parseISO(acf['lid-tot']), 'd MMMM yyyy')}</span>
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
                      className="text-xs px-2 py-1 bg-electric-cyan text-white rounded hover:bg-bright-cobalt disabled:opacity-50 disabled:cursor-not-allowed"
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
                          <img src={`${window.rondoConfig?.themeUrl}/public/icons/sportlink.png`} alt="Sportlink" className="w-5 h-5" />
                        </a>
                      );
                    }

                    // Handle Freescout specially with custom icon
                    if (contact.contact_type === 'freescout') {
                      return (
                        <a
                          key={index}
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex-shrink-0 hover:opacity-80 transition-opacity"
                          title="Bekijk in Freescout"
                        >
                          <img src={`${window.rondoConfig?.themeUrl}/public/icons/freescout.png`} alt="Freescout" className="w-5 h-5" />
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
          <TabButton label="Profiel" isActive={activeTab === 'profile'} onClick={() => setActiveTab('profile')} />
          <TabButton label="Tijdlijn" isActive={activeTab === 'timeline'} onClick={() => setActiveTab('timeline')} />
          <TabButton label="Rollen" isActive={activeTab === 'work'} onClick={() => setActiveTab('work')} />
          {canAccessFairplay && hasDisciplineCases && (
            <TabButton label="Tuchtzaken" isActive={activeTab === 'discipline'} onClick={() => setActiveTab('discipline')} />
          )}
        </nav>
      </div>

      {/* Tab Content */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main content */}
        <div className="lg:col-span-2 min-w-0 space-y-6">
        {/* Profile Tab */}
        {activeTab === 'profile' && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Column 1: Contactgegevens, Adressen, Custom Fields */}
            <div className="space-y-6">
            {/* Contact info - only show for living people */}
          {!isDeceased && (
            <div className="card p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold text-brand-gradient">Contactgegevens</h2>
                <button
                  onClick={() => setShowContactModal(true)}
                  className="btn-secondary text-sm"
                >
                  <Pencil className="w-4 h-4 md:mr-1" />
                  <span className="hidden md:inline">Bewerken</span>
                </button>
              </div>
            {acf.contact_info?.filter(contact => !SOCIAL_TYPES.includes(contact.contact_type) && contact.contact_type !== 'slack').length > 0 ? (
              <div className="space-y-2">
                {(() => {
                  // Define display order for contact information
                  const contactOrder = {
                    'email': 1,
                    'phone': 2,
                    'mobile': 2, // Phone numbers grouped together
                    'calendar': 3,
                    'other': 4,
                  };
                  
                  // Filter and sort contact information
                  const nonSocialContacts = acf.contact_info
                    .filter(contact => !SOCIAL_TYPES.includes(contact.contact_type) && contact.contact_type !== 'slack')
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
                                     contact.contact_type === 'calendar' ? Calendar : Globe;

                        const isEmail = contact.contact_type === 'email';
                        const isPhone = contact.contact_type === 'phone' || contact.contact_type === 'mobile';
                        const isCalendar = contact.contact_type === 'calendar';
                        
                        let linkHref = null;
                        let linkTarget = null;
                        
                        if (isEmail) {
                          linkHref = `mailto:${contact.contact_value}`;
                        } else if (isPhone) {
                          linkHref = `tel:${formatPhoneForTel(contact.contact_value)}`;
                        } else if (isCalendar) {
                          linkHref = contact.contact_value;
                          linkTarget = '_blank';
                        }

                        return (
                      <div key={contact.originalIndex}>
                        <div className="flex items-center rounded-md -mx-2 px-2 py-1.5">
                          <Icon className="w-4 h-4 text-gray-400 mr-3 flex-shrink-0" />
                          <div className="flex-1 min-w-0 flex items-center gap-2">
                            <span className="text-sm text-gray-500 dark:text-gray-400">{contact.contact_label || contact.contact_type}: </span>
                            {linkHref ? (
                              <a
                                href={linkHref}
                                target={linkTarget || undefined}
                                rel={linkTarget === '_blank' ? 'noopener noreferrer' : undefined}
                                className="text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light hover:underline"
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
                Nog geen contactgegevens. <button onClick={() => setShowContactModal(true)} className="text-electric-cyan hover:underline">Toevoegen</button>
              </p>
            )}
            {/* View in Google Contacts link - only for synced contacts with email */}
            {person.google_contact_id && acf.contact_info?.find(c => c.contact_type === 'email')?.contact_value && (
              <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <a
                  href={`https://contacts.google.com/${acf.contact_info.find(c => c.contact_type === 'email').contact_value}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-electric-cyan dark:hover:text-electric-cyan"
                >
                  <ExternalLink className="w-4 h-4" />
                  <span>View in Google Contacts</span>
                </a>
              </div>
            )}
            </div>
          )}

            {/* Addresses - only show for living people */}
            {!isDeceased && (
              <div className="card p-6">
                <h2 className="font-semibold text-brand-gradient mb-4">Adressen</h2>
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
                        <div key={index} className="flex items-start">
                          <MapPin className="w-4 h-4 text-gray-400 mt-1 mr-3 flex-shrink-0" />
                          <div className="flex-1 min-w-0">
                            {address.address_label && (
                              <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">{address.address_label}</p>
                            )}
                            <a
                              href={googleMapsUrl}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light hover:underline text-sm"
                            >
                              {addressLines.map((line, i) => (
                                <span key={i} className="block">{line}</span>
                              ))}
                            </a>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p className="text-sm text-gray-500 text-center py-4">
                    Nog geen adressen.
                  </p>
                )}
              </div>
            )}

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
              excludeLabelPrefixes={['Nikki', 'Financiële', 'Datum VOG', 'VOG', 'Freescout']}
            />
            </div>

            {/* Column 2: Sportlink, Relaties, VOG */}
            <div className="space-y-6">
            {/* Sportlink Card */}
            <SportlinkCard acfData={person?.acf} />

            {/* Relationships */}
            <div className="card p-6">
              <div className="flex items-center justify-between mb-3">
                <h2 className="font-semibold text-brand-gradient">Relaties</h2>
                <div className="flex items-center gap-2">
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
                          <PersonAvatar
                            thumbnail={rel.person_thumbnail}
                            name={decodeHtml(rel.person_name)}
                            size="md"
                            className="mr-2"
                          />
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
                  Nog geen relaties. <button onClick={() => { setEditingRelationship(null); setEditingRelationshipIndex(null); setShowRelationshipModal(true); }} className="text-electric-cyan hover:underline">Toevoegen</button>
                </p>
              )}
            </div>

            {/* VOG Card */}
            <VOGCard acfData={person?.acf} />
            </div>
          </div>
        )}

        {/* Timeline Tab */}
        {activeTab === 'timeline' && (
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold text-brand-gradient">Tijdlijn</h2>
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
            <h2 className="font-semibold text-brand-gradient mb-4">Functiegeschiedenis</h2>
            {sortedWorkHistory?.length > 0 ? (
              <div className="space-y-4">
                {sortedWorkHistory.map((job) => {
                  const teamData = job.team ? teamMap[job.team] : null;
                  const originalIndex = job.originalIndex;

                  return (
                    <div key={originalIndex} className="flex items-start">
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
                            className="text-sm text-electric-cyan hover:underline"
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
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                Nog geen functiegeschiedenis.
              </p>
            )}
          </div>
          
          {/* Investments */}
          {investments.length > 0 && (
            <div className="card p-6 break-inside-avoid mb-6">
              <h2 className="font-semibold text-brand-gradient mb-4 flex items-center">
                <TrendingUp className="w-5 h-5 mr-2" />
                Investments
              </h2>
              <div className="space-y-3">
                {investments.map((investment) => {
                  const isCommissie = investment.type === 'commissie';
                  const linkPath = isCommissie
                    ? `/commissies/${investment.id}`
                    : `/teams/${investment.id}`;
                  return (
                    <Link
                      key={investment.id}
                      to={linkPath}
                      className="flex items-center p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group"
                    >
                      {investment.thumbnail ? (
                        <img
                          src={investment.thumbnail}
                          alt={investment.name}
                          className="w-12 h-12 rounded-lg object-contain border border-gray-200"
                        />
                      ) : (
                        <div className="w-12 h-12 bg-white rounded-lg flex items-center justify-center border border-gray-200">
                          <Building2 className="w-6 h-6 text-gray-400" />
                        </div>
                      )}
                      <div className="ml-3">
                        <p className="text-sm font-medium group-hover:text-electric-cyan">{investment.name}</p>
                        {investment.industry && (
                          <p className="text-xs text-gray-500 dark:text-gray-400">{investment.industry}</p>
                        )}
                      </div>
                    </Link>
                  );
                })}
              </div>
            </div>
          )}

          </div>
        )}

        {/* Discipline Cases Tab */}
        {activeTab === 'discipline' && canAccessFairplay && (
          <div className="card p-6">
            <div className="flex items-center gap-3 mb-4">
              <Gavel className="w-5 h-5 text-gray-500" />
              <h2 className="font-semibold text-brand-gradient">Tuchtzaken</h2>
            </div>
            <DisciplineCaseTable
              cases={disciplineCases}
              showPersonColumn={false}
              personMap={new Map()}
              isLoading={isDisciplineCasesLoading}
            />
          </div>
        )}

        </div>

        {/* Sidebar - always visible */}
        <aside className="hidden lg:block">
          <div className="sticky top-6">
            {/* Finances Card */}
            <FinancesCard personId={parseInt(id)} />

            {/* Todos Card */}
            <div className="card p-6">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <h2 className="font-semibold text-brand-gradient">Taken</h2>
                  {openTodosCount > 0 && (
                    <span className="bg-cyan-100 text-bright-cobalt text-xs font-medium px-2 py-0.5 rounded-full">
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
                  {sortedTodos.map((todo) => (
                    <TodoItem
                      key={todo.id}
                      todo={todo}
                      currentPersonId={parseInt(id, 10)}
                      onToggle={handleToggleTodo}
                      onEdit={(t) => {
                        setEditingTodo(t);
                        setShowTodoModal(true);
                      }}
                      onDelete={handleDeleteTodo}
                    />
                  ))}
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
        className="fixed bottom-6 right-6 z-40 lg:hidden bg-electric-cyan hover:bg-bright-cobalt text-white rounded-full p-4 shadow-lg transition-colors"
        title="Taken bekijken"
      >
        <CheckSquare2 className="w-6 h-6" />
        {openTodosCount > 0 && (
          <span className="absolute -top-1 -right-1 bg-cyan-100 text-bright-cobalt text-xs font-medium px-2 py-0.5 rounded-full min-w-[20px] text-center">
            {openTodosCount}
          </span>
        )}
      </button>

      {/* Mobile Todos Slide-up Panel */}
      {showMobileTodos && (
        <div className="fixed inset-0 z-50 lg:hidden">
          {/* Backdrop */}
          <div
            className="absolute inset-0 bg-black/50"
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
                <h2 className="font-semibold text-brand-gradient text-lg">Taken</h2>
                {openTodosCount > 0 && (
                  <span className="bg-cyan-100 text-bright-cobalt text-xs font-medium px-2 py-0.5 rounded-full">
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
                  {sortedTodos.map((todo) => (
                    <TodoItem
                      key={todo.id}
                      todo={todo}
                      currentPersonId={parseInt(id, 10)}
                      onToggle={handleToggleTodo}
                      onEdit={(t) => {
                        setEditingTodo(t);
                        setShowTodoModal(true);
                        setShowMobileTodos(false);
                      }}
                      onDelete={handleDeleteTodo}
                      showActionsAlways
                    />
                  ))}
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
            contactInfo={(acf.contact_info || []).filter(contact => contact.contact_type !== 'slack')}
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

      </div>
    </PullToRefreshWrapper>
  );
}
