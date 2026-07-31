import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft, Trash2, Mail, Phone,
  MapPin, Building2, Plus, Pencil, MessageCircle, X, Camera, Download,
  CheckSquare2, TrendingUp, StickyNote, ExternalLink, Gavel, RefreshCw, CreditCard,
  CalendarClock
} from 'lucide-react';
import { usePerson, usePersonTimeline, useDeleteNote, useDeletePerson, useUpdatePerson, useCreateNote, useCreateActivity, useUpdateActivity, useCreateTodo, useUpdateTodo, useDeleteActivity, useDeleteTodo, usePeople } from '@/hooks/usePeople';
import TimelineView from '@/components/Timeline/TimelineView';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';
import DisciplineCaseTable from '@/components/DisciplineCaseTable';
import { usePersonDisciplineCases } from '@/hooks/useDisciplineCases';
import { useInvoicedCaseIds, useCreateInvoice } from '@/hooks/useInvoices';
import NoteModal from '@/components/Timeline/NoteModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';
import TodoModal from '@/components/Timeline/TodoModal';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import ContactEditModal from '@/components/ContactEditModal';
import RelationshipEditModal from '@/components/RelationshipEditModal';
import AddressEditModal from '@/components/AddressEditModal';
import CustomFieldsSection from '@/components/CustomFieldsSection';
import FinancesCard from '@/components/FinancesCard';
import VOGCard from '@/components/VOGCard';
import SportlinkCard from '@/components/SportlinkCard';
import AccountCard from '@/components/AccountCard';
import { format, parseYmd, differenceInYears } from '@/utils/dateFormat';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml, formatPersonName, getTeamName, sanitizePersonFields, isValidDate, parseAcfDate, getGenderSymbol, formatPhoneForTel, formatPhoneForDisplay, hasSponsorRole } from '@/utils/formatters';
import { downloadVCard } from '@/utils/vcard';
import { getSocialIcon, getSocialIconColor, sortSocialLinks } from '@/utils/socialIcons';
import TodoItem from '@/components/TodoItem.jsx';
import TabButton from '@/components/TabButton.jsx';
import { useClothingPersonProfile } from '@/hooks/useClothing';

function PersonShiftItem({ shift }) {
  const status = shift.no_show
    ? { label: 'No-show', classes: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }
    : shift.status === 'voltooid'
      ? { label: 'Voltooid', classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' }
      : shift.status === 'geannuleerd'
        ? { label: 'Geannuleerd', classes: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' }
        : { label: 'Ingepland', classes: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300' };

  return (
    <li className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
            {shift.dienst_type_name || shift.title}
          </p>
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {format(shift.start_datetime, 'EEEE d MMMM yyyy, HH:mm')}
            {shift.end_datetime ? ` – ${format(shift.end_datetime, 'HH:mm')}` : ''}
          </p>
        </div>
        <span className={`shrink-0 rounded px-2 py-0.5 text-xs font-medium ${status.classes}`}>
          {status.label}
        </span>
      </div>
    </li>
  );
}

function PersonShiftOverview({ overview, isLoading }) {
  const upcoming = overview?.upcoming || [];
  const recent = overview?.recent || [];

  return (
    <section className="card p-6 md:col-span-2">
      <div className="mb-4 flex items-center gap-2">
        <CalendarClock className="h-5 w-5 text-gray-500 dark:text-gray-400" />
        <h2 className="font-semibold text-brand-gradient">Inschrijftaken</h2>
      </div>

      {isLoading ? (
        <p className="text-sm text-gray-500 dark:text-gray-400">Inschrijftaken laden…</p>
      ) : (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Komend ({upcoming.length})
            </h3>
            {upcoming.length > 0 ? (
              <ul className="space-y-2">
                {upcoming.map((shift) => <PersonShiftItem key={shift.id} shift={shift} />)}
              </ul>
            ) : (
              <p className="text-sm text-gray-500 dark:text-gray-400">Geen komende inschrijftaken gepland.</p>
            )}
          </div>

          <div>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Laatste 2 inschrijftaken
            </h3>
            {recent.length > 0 ? (
              <ul className="space-y-2">
                {recent.map((shift) => <PersonShiftItem key={shift.id} shift={shift} />)}
              </ul>
            ) : (
              <p className="text-sm text-gray-500 dark:text-gray-400">Nog geen eerdere inschrijftaken.</p>
            )}
          </div>
        </div>
      )}
    </section>
  );
}

export default function PersonDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { data: person, isLoading, error } = usePerson(id);
  const { data: timeline } = usePersonTimeline(id);
  const deleteNote = useDeleteNote();
  const deletePerson = useDeletePerson();
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
      queryClient.invalidateQueries({ queryKey: ['people', id, 'shifts'] }),
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
  const canAccessFinancieel = currentUser?.can_access_financieel ?? false;
  const canEditFinancieel = currentUser?.can_edit_financieel ?? false;
  const canAccessClothing = currentUser?.can_access_clothing ?? false;
  const canAccessToegangscontrole = currentUser?.can_access_toegangscontrole ?? false;
  const canEditAllPeople = currentUser?.can_edit_people ?? false;
  const canAccessPersonNotes = currentUser?.can_access_person_notes ?? false;
  const canManageSponsors = currentUser?.can_manage_sponsors ?? false;
  const isSponsorPerson = hasSponsorRole(person?.fields);
  const isDualRoleSponsor = isSponsorPerson && (person?.fields?.person_type || 'member') !== 'contact';
  let canEditPeople = canEditAllPeople || (canManageSponsors && isSponsorPerson && !isDualRoleSponsor);
  // Volunteer coordinators may correct contact details and photos on any person,
  // but nothing else — the server enforces the same field boundary.
  let canEditContact = canEditPeople || (currentUser?.can_edit_person_contact ?? false);
  let canEditSponsorFields = canEditAllPeople || (canManageSponsors && isSponsorPerson);
  const canSyncFromSportlink = (currentUser?.is_admin ?? window.rondoConfig?.isAdmin ?? false) || canAccessToegangscontrole;

  const { data: clothingProfile } = useClothingPersonProfile(id, {
    enabled: canAccessClothing && !!id,
  });

  const { data: shiftOverview, isLoading: isShiftOverviewLoading } = useQuery({
    queryKey: ['people', id, 'shifts'],
    queryFn: async () => (await prmApi.getPersonShifts(id)).data,
    enabled: !!id,
    staleTime: 60 * 1000,
  });

  // Fetch discipline cases for this person (fairplay users only)
  const { data: disciplineCases, isLoading: isDisciplineCasesLoading } = usePersonDisciplineCases(id, {
    enabled: canAccessFairplay,
  });

  // Check if person has any discipline cases (for hiding empty tab)
  const hasDisciplineCases = disciplineCases && disciplineCases.length > 0;

  // Fetch invoiced discipline case IDs (financieel users only)
  const { data: invoicedCaseIds = [] } = useInvoicedCaseIds(id, {
    enabled: canAccessFairplay && canAccessFinancieel,
  });
  const createInvoice = useCreateInvoice();
  const [selectedCaseIds, setSelectedCaseIds] = useState(new Set());

  const [activeTab, setActiveTab] = useState('profile');
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [syncStatus, setSyncStatus] = useState(null); // null | 'success' | 'error'
  const [syncErrorMessage, setSyncErrorMessage] = useState('');
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
  const [showAddressModal, setShowAddressModal] = useState(false);
  const [isSavingPersonType, setIsSavingPersonType] = useState(false);
  const [isSavingSponsorPassVariant, setIsSavingSponsorPassVariant] = useState(false);
  const [companyNameDraft, setCompanyNameDraft] = useState('');
  const [isSavingAddress, setIsSavingAddress] = useState(false);
  const [editingAddress, setEditingAddress] = useState(null);
  const [editingAddressIndex, setEditingAddressIndex] = useState(null);

  // Complete todo flow states
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);
  const [verificationTodoId, setVerificationTodoId] = useState(null);

  // Mobile todos panel state
  const [showMobileTodos, setShowMobileTodos] = useState(false);

  useEffect(() => {
    setCompanyNameDraft(person?.fields?.company_name || '');
  }, [person?.fields?.company_name]);

  // Update document title with person's name - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(person?.name || person?.title?.rendered || person?.title || 'Lid');

  // Get birthdate from ACF field
  const birthDate = person?.fields?.birthdate && isValidDate(person.fields.birthdate)
    ? new Date(person.fields.birthdate)
    : null;

  // Deceased status (is_deceased always returns false now - death date feature removed)
  const isDeceased = person?.is_deceased || false;

  // Calculate age - current age only (death date feature removed)
  const age = birthDate ? differenceInYears(new Date(), birthDate) : null;

  // Format birthdate for display: "6 feb 1982"
  const formattedBirthdate = birthDate ? format(birthDate, 'd MMM yyyy') : null;
  const parseSafeDate = (value) => {
    if (!value) return null;
    const raw = String(value).trim();

    if (/^\d{8}$/.test(raw)) {
      const parsed = parseYmd(raw);
      return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    if (!isValidDate(raw)) return null;
    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  };

  const lidTotDate = parseSafeDate(person?.fields?.['lid_tot']);
  const hasValidLidTot = !!lidTotDate;
  const formattedLidTot = lidTotDate ? format(lidTotDate, 'd MMMM yyyy') : null;

  // Handle saving all contacts from modal
  const handleSaveContacts = async (contactFields) => {
    setIsSavingContacts(true);
    try {
      const acfData = sanitizePersonFields(person.fields, contactFields);

      await updatePerson.mutateAsync({
        id,
        data: {
          fields: acfData,
        },
      });

      setShowContactModal(false);
    } catch {
      alert('Contacten konden niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingContacts(false);
    }
  };

  const handlePersonTypeChange = async (personType) => {
    setIsSavingPersonType(true);
    try {
      const acfData = sanitizePersonFields(person.fields, { person_type: personType });
      await updatePerson.mutateAsync({ id, data: { fields: acfData } });
    } catch {
      alert('Persoonstype kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingPersonType(false);
    }
  };

  const handleSponsorRoleChange = async (sponsorPassVariant) => {
    setIsSavingSponsorPassVariant(true);
    try {
      const sponsorFields = sponsorPassVariant
        ? { is_sponsor: true, sponsor_pass_variant: sponsorPassVariant }
        : { is_sponsor: false, sponsor_pass_variant: null };
      const acfData = canEditAllPeople
        ? sanitizePersonFields(person.fields, sponsorFields)
        : sponsorFields;
      await updatePerson.mutateAsync({ id, data: { fields: acfData } });
      window.location.reload();
    } catch {
      alert('Sponsorrol kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      setIsSavingSponsorPassVariant(false);
    }
  };

  const handleDeleteSponsor = async () => {
    if (!isSponsorPerson || isDualRoleSponsor || !confirm(`Weet je zeker dat je ${person?.name || 'deze sponsor'} definitief wilt verwijderen?`)) return;

    try {
      await deletePerson.mutateAsync(Number(id));
      navigate('/people');
    } catch (error) {
      alert(error?.response?.data?.message || 'Sponsor kon niet worden verwijderd. Probeer het opnieuw.');
    }
  };

  const handleCompanyNameSave = async () => {
    const companyName = companyNameDraft.trim();
    if (companyName === (person.fields?.company_name || '')) return;
    if (!companyName && !person.fields?.first_name) {
      alert('Vul een bedrijfsnaam of voornaam in.');
      setCompanyNameDraft(person.fields?.company_name || '');
      return;
    }

    try {
      await updatePerson.mutateAsync({ id, data: { fields: { company_name: companyName } } });
    } catch {
      alert('Bedrijfsnaam kon niet worden opgeslagen. Probeer het opnieuw.');
      setCompanyNameDraft(person.fields?.company_name || '');
    }
  };

  // Handle saving a relationship from modal
  const handleSaveRelationship = async (data) => {
    setIsSavingRelationship(true);
    try {
      const relationships = [...(person.fields?.relationships || [])];
      
      const relationshipItem = {
        related_person_id: data.related_person_id || null,
        relationship_type_id: data.relationship_type_id || null,
        relationship_label: data.relationship_label || '',
      };

      if (editingRelationshipIndex !== null) {
        relationships[editingRelationshipIndex] = relationshipItem;
      } else {
        relationships.push(relationshipItem);
      }

      const acfData = sanitizePersonFields(person.fields, {
        relationships: relationships,
      });

      await updatePerson.mutateAsync({
        id,
        data: {
          fields: acfData,
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
    
    const relationshipToDelete = person.fields?.relationships?.[index];
    if (!relationshipToDelete) {
      return;
    }
    
    const relatedPersonId = relationshipToDelete.related_person_id;
    const relationshipTypeId = relationshipToDelete.relationship_type_id;
    
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
        const inverseTypeId = currentType?.fields?.inverse_relationship_type;
        
        if (inverseTypeId) {
          // Find the inverse relationship to save it
          const relatedPersonRelationships = relatedPerson.data.fields?.relationships || [];
          const inverseRel = relatedPersonRelationships.find(rel => {
            const relPersonId = typeof rel.related_person_id === 'object' ? rel.related_person_id?.ID : rel.related_person_id;
            const relTypeId = typeof rel.relationship_type_id === 'object' ? rel.relationship_type_id?.term_id : rel.relationship_type_id;
            return relPersonId === parseInt(id) && relTypeId === inverseTypeId;
          });
          
          if (inverseRel) {
            inverseToRestore = {
              related_person_id: parseInt(id),
              relationship_type_id: inverseTypeId,
              relationship_label: inverseRel.relationship_label || '',
            };
          }
        }
      } catch {
        // Continue with deletion anyway
      }
    }
    
    // Delete the relationship from current person
    const updatedRelationships = [...(person.fields?.relationships || [])];
    updatedRelationships.splice(index, 1);
    
    const acfData = sanitizePersonFields(person.fields, {
      relationships: updatedRelationships,
    });
    
    await updatePerson.mutateAsync({
      id,
      data: {
        fields: acfData,
      },
    });
    
    // If user doesn't want to delete inverse, re-add it after backend sync
    if (!deleteInverse && inverseToRestore && relatedPersonId) {
      // Wait a bit for backend sync to complete
      await new Promise(resolve => setTimeout(resolve, 500));
      
      try {
        // Fetch the related person again to get current state
        const relatedPerson = await wpApi.getPerson(relatedPersonId, { _embed: true });
        const relatedPersonRelationships = relatedPerson.data.fields?.relationships || [];
        
        // Check if inverse was already deleted (it should be)
        const inverseExists = relatedPersonRelationships.some(rel => {
          const relPersonId = typeof rel.related_person_id === 'object' ? rel.related_person_id?.ID : rel.related_person_id;
          const relTypeId = typeof rel.relationship_type_id === 'object' ? rel.relationship_type_id?.term_id : rel.relationship_type_id;
          return relPersonId === inverseToRestore.related_person_id && relTypeId === inverseToRestore.relationship_type_id;
        });
        
        // If inverse doesn't exist, re-add it
        if (!inverseExists) {
          const updatedRelatedRelationships = [...relatedPersonRelationships, inverseToRestore];
          const relatedPersonAcfData = sanitizePersonFields(relatedPerson.data.fields, {
            relationships: updatedRelatedRelationships,
          });
          
          await wpApi.updatePerson(relatedPersonId, {
            fields: relatedPersonAcfData,
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

  // Handle saving an address from modal
  const handleSaveAddress = async (data) => {
    setIsSavingAddress(true);
    try {
      const addresses = [...(person.fields?.addresses || [])];
      if (editingAddressIndex !== null) {
        addresses[editingAddressIndex] = data;
      } else {
        addresses.push(data);
      }
      const acfData = sanitizePersonFields(person.fields, { addresses });
      await updatePerson.mutateAsync({ id, data: { fields: acfData } });
      setShowAddressModal(false);
      setEditingAddress(null);
      setEditingAddressIndex(null);
    } catch (error) {
      console.error('Failed to save address:', error);
    } finally {
      setIsSavingAddress(false);
    }
  };

  // Handle deleting an address
  const handleDeleteAddress = async (index) => {
    if (!window.confirm('Weet je zeker dat je dit adres wilt verwijderen?')) return;
    const addresses = [...(person.fields?.addresses || [])];
    addresses.splice(index, 1);
    const acfData = sanitizePersonFields(person.fields, { addresses });
    await updatePerson.mutateAsync({ id, data: { fields: acfData } });
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

  // Handle creating invoice from selected discipline cases
  const handleCreateInvoice = async () => {
    if (selectedCaseIds.size === 0) return;

    const lineItems = disciplineCases
      .filter(dc => selectedCaseIds.has(dc.id))
      .map(dc => ({
        discipline_case_id: dc.id,
        description: dc.fields?.match_description || dc.fields?.sanction_description || '',
        amount: parseFloat(dc.fields?.administrative_fee) || 0,
      }));

    try {
      await createInvoice.mutateAsync({
        person_id: parseInt(id),
        line_items: lineItems,
      });
      setSelectedCaseIds(new Set());
    } catch {
      alert('Factuur kon niet worden aangemaakt. Probeer het opnieuw.');
    }
  };

  // Handle deleting a todo
  const handleDeleteTodo = async (todoId) => {
    if (!window.confirm('Weet je zeker dat je deze taak wilt verwijderen?')) {
      return;
    }

    await deleteTodo.mutateAsync({ todoId, personId: id });
  };

  const handleSendVerificationEmail = async (todo) => {
    setVerificationTodoId(todo.id);
    try {
      const response = await prmApi.sendLettermintVerificationEmail(todo.id, todo?.email_verification?.recipient || '');
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['people', id, 'timeline'] }),
        queryClient.invalidateQueries({ queryKey: ['todos'] }),
      ]);
      const recipient = response.data?.recipient || todo?.email_verification?.recipient || 'onbekend e-mailadres';
      window.alert(`Verificatiemail verzonden naar ${recipient}.`);
    } catch (error) {
      window.alert(error?.response?.data?.message || 'Kon verificatiemail niet verzenden.');
    } finally {
      setVerificationTodoId(null);
    }
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
    const knvbId = person?.fields?.['knvb_id'];
    if (!knvbId || isSyncing) return;

    setIsSyncing(true);
    setSyncStatus(null);
    setSyncErrorMessage('');
    try {
      await prmApi.syncFromSportlink(knvbId);
      setSyncStatus('success');
      await queryClient.invalidateQueries({ queryKey: ['people', 'detail', id] });
      setTimeout(() => {
        setSyncStatus(null);
        setSyncErrorMessage('');
      }, 3000);
    } catch (error) {
      const statusCode = error?.response?.status;
      const isRateLimited = statusCode === 429;
      const isGatewayTimeout = statusCode === 502 || statusCode === 504 || statusCode === 524;
      const isClientTimeout = error?.code === 'ECONNABORTED';
      setSyncErrorMessage(
        isRateLimited
          ? 'Sportlink verwerkt nu te veel verzoeken (429). Wacht even en probeer het opnieuw.'
          : isGatewayTimeout || isClientTimeout
            ? 'Sportlink reageert nu niet op tijd. Wacht even en probeer het opnieuw.'
            : 'Synchroniseren met Sportlink mislukte. Probeer het opnieuw.'
      );
      setSyncStatus('error');
      setTimeout(() => {
        setSyncStatus(null);
        setSyncErrorMessage('');
      }, 5000);
    } finally {
      setIsSyncing(false);
    }
  };

  // Fetch team/commissie names for work history entries
  // Build a map of entity ID to type from work history for efficient lookup
  const entityTypeMap = useMemo(() => {
    const map = new Map();
    if (!person?.fields?.work_history) return map;
    person.fields.work_history.forEach(job => {
      if (job.team_id && job.entity_type && !map.has(job.team_id)) {
        map.set(job.team_id, job.entity_type);
      }
    });
    return map;
  }, [person?.fields?.work_history]);

  // Get unique entity IDs from work history
  const entityIds = useMemo(() => {
    if (!person?.fields?.work_history) return [];
    const ids = person.fields.work_history
      .map(job => job.team_id)
      .filter(Boolean);
    return [...new Set(ids)];
  }, [person?.fields?.work_history]);

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
    if (!person?.fields?.relationships) return [];
    
    return [...person.fields.relationships].sort((a, b) => {
      const ageA = personAgeMap[a.related_person_id] ?? -1;
      const ageB = personAgeMap[b.related_person_id] ?? -1;
      
      // Sort descending (oldest first)
      // If age is -1 (no birthday), put at the end
      if (ageA === -1 && ageB === -1) return 0;
      if (ageA === -1) return 1;
      if (ageB === -1) return -1;
      
      return ageB - ageA;
    });
  }, [person?.fields?.relationships, personAgeMap]);

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
    if (!person?.fields?.work_history) return [];
    
    return [...person.fields.work_history]
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
  }, [person?.fields?.work_history]);

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
      const linkedTeam = job.team_id && teamMap[job.team_id];
      const isVerenigingsbreed = linkedTeam?.name === 'Verenigingsbreed';

      // Three buckets:
      // - Linked team (not Verenigingsbreed) → group by team id, render as link
      // - Text-fallback (no linked team, but team_name_text present from
      //   historical-team sync) → group by text name, render as plain text
      // - Verenigingsbreed / no team at all → null group (sorted first)
      let groupKey, groupData;
      if (linkedTeam && !isVerenigingsbreed) {
        groupKey = `id:${job.team_id}`;
        groupData = {
          teamId: job.team_id,
          team: linkedTeam,
          teamName: linkedTeam.name,
          teamType: linkedTeam.type,
          showTeamLink: true,
          titles: []
        };
      } else if (!linkedTeam && job.team_name_text) {
        groupKey = `text:${job.team_name_text.toLowerCase()}`;
        groupData = {
          teamId: null,
          team: null,
          teamName: job.team_name_text,
          teamType: null,
          showTeamLink: false,
          titles: []
        };
      } else {
        groupKey = null;
        groupData = {
          teamId: null,
          team: null,
          teamName: null,
          teamType: null,
          showTeamLink: false,
          titles: []
        };
      }

      if (!groups.has(groupKey)) {
        groups.set(groupKey, groupData);
      }

      if (job.job_title) {
        groups.get(groupKey).titles.push(job.job_title);
      }
    });

    // Convert to array and sort: Verenigingsbreed (null group, no name) first,
    // then linked teams and text-fallback teams in insertion order.
    const result = Array.from(groups.values())
      .filter(group => group.titles.length > 0)
      .sort((a, b) => {
        const aIsBreed = a.teamId === null && !a.teamName;
        const bIsBreed = b.teamId === null && !b.teamName;
        if (aIsBreed && !bIsBreed) return -1;
        if (!aIsBreed && bIsBreed) return 1;
        return 0;
      });

    return result;
  }, [currentPositions, teamMap]);

  const primaryTeamMetaValue = useMemo(() => {
    const raw = person?.meta?.team;
    if (raw === null || raw === undefined) {
      return '';
    }
    return String(raw).trim();
  }, [person?.meta?.team]);

  const sportlinkPrimaryTeam = useMemo(() => {
    if (!primaryTeamMetaValue) {
      return null;
    }
    return { id: null, name: primaryTeamMetaValue };
  }, [primaryTeamMetaValue]);

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

  // Reset selected case IDs when switching away from discipline tab
  useEffect(() => {
    if (activeTab !== 'discipline') {
      setSelectedCaseIds(new Set());
    }
  }, [activeTab]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  if (error?.response?.status === 403 && error?.response?.data?.code === 'rest_forbidden_age_group') {
    return (
      <div className="card p-6 text-center bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
        <p className="text-amber-700 dark:text-amber-300">Je hebt geen toegang tot dit lid. Dit lid valt buiten je toegewezen leeftijdsgroepen.</p>
        <Link to="/people" className="btn-tertiary mt-4">Terug naar leden</Link>
      </div>
    );
  }

  if (error || !person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400">Lid kon niet worden geladen.</p>
        <Link to="/people" className="btn-tertiary mt-4">Terug naar leden</Link>
      </div>
    );
  }
  
  // Don't render if person is trashed (redirect will happen)
  if (person.status === 'trash') {
    return null;
  }
  
  const acf = person.fields || {};
  const personalName = formatPersonName(acf.first_name, acf.infix, acf.last_name);
  const isFormerMember = acf.former_member === true;
  const isCurrentParent = person.is_current_parent === true;

  // Former members are read-only — Sportlink rejects writes for their
  // lidsoort ("Oud bondslid" / "Oud verenigingslid") so any UI edit
  // would just generate doomed reverse-sync work. Backend enforces the
  // same rule (rest_pre_insert_person filter); hiding edit affordances
  // here just keeps users from trying.
  if (isFormerMember) {
    canEditPeople = false;
    canEditContact = false;
    canEditSponsorFields = false;
  }

  // Build contact display items from fixed fields
  const contactItems = [
    acf.email_1 && { type: 'email', label: 'Email', value: acf.email_1 },
    acf.email_2 && { type: 'email', label: 'Email (2e)', value: acf.email_2 },
    acf.mobile_1 && { type: 'mobile', label: 'Mobiel', value: acf.mobile_1 },
    acf.mobile_2 && { type: 'mobile', label: 'Mobiel (2e)', value: acf.mobile_2 },
    acf.telephone_1 && { type: 'phone', label: 'Telefoon', value: acf.telephone_1 },
    acf.telephone_2 && { type: 'phone', label: 'Telefoon (2e)', value: acf.telephone_2 },
  ].filter(Boolean);

  // Build external links for header display (WhatsApp, Sportlink, FreeScout, Membership Pass)
  const sortedSocialLinks = (() => {
    const links = [];

    // Add WhatsApp if there's a mobile number
    if (acf.mobile_1) {
      links.push({
        contact_type: 'whatsapp',
        contact_value: `https://wa.me/${formatPhoneForTel(acf.mobile_1)}`,
      });
    }

    // Add Sportlink if there's a KNVB ID
    if (acf['knvb_id']) {
      links.push({
        contact_type: 'sportlink',
        contact_value: `https://club.sportlink.com/member/member-details/${acf['knvb_id']}/general`,
      });
    }

    // Add FreeScout if there's a FreeScout ID AND a configured URL
    const freescoutUrl = window.rondoConfig?.freescoutUrl;
    if (acf['freescout_id'] && freescoutUrl) {
      links.push({
        contact_type: 'freescout',
        contact_value: `${freescoutUrl}/customers/${acf['freescout_id']}`,
      });
    }

    // Add Membership Pass if available
    if (person.membership_pass_url) {
      links.push({
        contact_type: 'membership_pass',
        contact_value: person.membership_pass_url,
      });
    }

    return sortSocialLinks(links);
  })();

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
        <button
          onClick={() => {
            if (window.history.state && window.history.length > 1) {
              navigate(-1);
            } else {
              navigate('/people');
            }
          }}
          aria-label="Terug"
          className="flex items-center text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Terug</span>
        </button>
        <div className="flex gap-2">
          {canSyncFromSportlink && acf['knvb_id'] && (
            <button
              onClick={handleSyncFromSportlink}
              disabled={isSyncing}
              className="btn-tertiary"
            >
              <RefreshCw className={`w-4 h-4 md:mr-2 ${isSyncing ? 'animate-spin' : ''}`} />
              <span className="hidden md:inline">
                {syncStatus === 'success' ? 'Bijgewerkt!' : syncStatus === 'error' ? 'Fout' : 'Ververs uit Sportlink'}
              </span>
            </button>
          )}
          <button onClick={handleExportVCard} className="btn-tertiary">
            <Download className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Exporteer vCard</span>
          </button>
          {isSponsorPerson && !isDualRoleSponsor && canEditPeople && (
            <button
              onClick={handleDeleteSponsor}
              disabled={deletePerson.isPending}
              className="btn-tertiary text-red-600 hover:text-red-700 dark:text-red-400"
            >
              <Trash2 className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Sponsor verwijderen</span>
            </button>
          )}
        </div>
      </div>
      {syncErrorMessage && (
        <p className="text-sm text-red-600 dark:text-red-400">{syncErrorMessage}</p>
      )}
      
      {isFormerMember && (
        <div className="mb-4 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
          <span className="font-medium">
            {isCurrentParent ? 'Oud-lid én actuele ouder/verzorger — alleen-lezen.' : 'Oud-lid — alleen-lezen.'}
          </span>{' '}
          {isCurrentParent
            ? 'De historische lidmaatschapsgegevens blijven bewaard. Actuele contact- en adresgegevens worden bijgehouden via de oudergegevens van het kind in Sportlink.'
            : 'Sportlink staat geen contact- of profielwijzigingen toe voor de lidsoort van deze persoon (Oud bondslid / Oud verenigingslid), dus elke aanpassing zou alsnog door de sync afgewezen worden. Vraag een beheerder om eerst de oud-lid-status uit te zetten als je deze gegevens wilt aanpassen.'}
        </div>
      )}

      {/* Profile header */}
      <div className={`card p-6 relative ${acf['financiele_blokkade'] ? 'bg-red-50 dark:bg-red-950/30' : acf.former_member ? 'bg-gray-50 dark:bg-gray-900/30' : ''}`}>

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
                  {person.name?.[0] || acf.company_name?.[0] || '?'}
                </span>
              </div>
            )}
            {/* Upload overlay */}
            {canEditContact && (
              <>
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
              </>
            )}
          </div>

          <div className="flex-1 space-y-3">
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold text-brand-gradient">
                {person.name}
                {isDeceased && <span className="ml-1 text-gray-500 dark:text-gray-400">&#8224;</span>}
              </h1>
              {acf.former_member && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                  {isCurrentParent ? 'Oud-lid · ouder/verzorger' : 'Oud-lid'}
                </span>
              )}
              {acf.person_type === 'contact' && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300">
                  Contact
                </span>
              )}
              {isSponsorPerson && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300">
                  Sponsor
                </span>
              )}
              {!acf.former_member && hasValidLidTot && new Date(acf['lid_tot']) > new Date() && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                  Afmelding in de toekomst
                </span>
              )}
              {acf.wacht_op_overschrijving && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                  Wacht op overschrijving
                </span>
              )}
              {acf['huidig_vrijwilliger'] && (
                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-electric-cyan text-white dark:bg-electric-cyan dark:text-white">
                  Vrijwilliger
                </span>
              )}
            </div>
            {acf.company_name && personalName && (
              <p className="text-base text-gray-600 dark:text-gray-300">{acf.company_name}</p>
            )}
            {(canEditAllPeople || canEditSponsorFields) && (
              <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                {canEditAllPeople && (
                  <label className="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span>Persoonstype</span>
                    <select
                      value={acf.person_type || 'member'}
                      onChange={(event) => handlePersonTypeChange(event.target.value)}
                      className="input py-1 text-sm w-auto"
                      disabled={isSavingPersonType}
                    >
                      <option value="member">Lid / ouder</option>
                      <option value="contact">Contact</option>
                    </select>
                  </label>
                )}
                {(canEditAllPeople || canEditSponsorFields) && (
                  <label className="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span>Sponsorrol</span>
                    <select
                      value={isSponsorPerson ? (acf.sponsor_pass_variant || '') : ''}
                      onChange={(event) => handleSponsorRoleChange(event.target.value)}
                      className="input py-1 text-sm w-auto"
                      disabled={isSavingSponsorPassVariant}
                    >
                      {canEditAllPeople && <option value="">Geen sponsor</option>}
                      <option value="businessclub">Businessclub AWC</option>
                      <option value="awc_sponsor">AWC Sponsor</option>
                    </select>
                  </label>
                )}
              </div>
            )}
            {isSponsorPerson && canEditSponsorFields && (
              <label className="block max-w-sm text-sm text-gray-500 dark:text-gray-400">
                <span className="mb-1 block">Bedrijfsnaam</span>
                <input
                  type="text"
                  value={companyNameDraft}
                  onChange={(event) => setCompanyNameDraft(event.target.value)}
                  onBlur={handleCompanyNameSave}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') event.currentTarget.blur();
                  }}
                  className="input py-1 text-sm"
                  placeholder="Voorbeeld BV"
                  disabled={updatePerson.isPending}
                />
              </label>
            )}
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
                    {!group.showTeamLink && group.teamName && (
                      <>
                        <span className="text-gray-400 dark:text-gray-500"> bij </span>
                        <span>{group.teamName}</span>
                      </>
                    )}
                  </span>
                ))}
              </p>
            )}
            {acf.nickname && (
              <p className="text-gray-500 dark:text-gray-400">&quot;{acf.nickname}&quot;</p>
            )}
            {(getGenderSymbol(acf.gender) || acf.pronouns || age !== null || acf['financiele_blokkade'] || acf['lid_tot']) && (
              <p className="text-gray-500 dark:text-gray-400 text-sm inline-flex items-center flex-wrap">
                {getGenderSymbol(acf.gender) && <span>{getGenderSymbol(acf.gender)}</span>}
                {getGenderSymbol(acf.gender) && acf.pronouns && <span>&nbsp;—&nbsp;</span>}
                {acf.pronouns && <span>{acf.pronouns}</span>}
                {(getGenderSymbol(acf.gender) || acf.pronouns) && age !== null && <span>&nbsp;—&nbsp;</span>}
                {age !== null && formattedBirthdate && <span>{age} jaar ({formattedBirthdate})</span>}
                {age !== null && !formattedBirthdate && <span>{age} jaar</span>}
                {acf['financiele_blokkade'] && (
                  <>
                    {(getGenderSymbol(acf.gender) || acf.pronouns || age !== null) && <span>&nbsp;—&nbsp;</span>}
                    <span className="text-red-600 dark:text-red-400 font-medium">Financiële blokkade</span>
                  </>
                )}
                {hasValidLidTot && (
                  <>
                    {(getGenderSymbol(acf.gender) || acf.pronouns || age !== null || acf['financiele_blokkade']) && <span>&nbsp;—&nbsp;</span>}
                    <span>Lid tot: {formattedLidTot}</span>
                  </>
                )}
              </p>
            )}
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

                    // Handle Membership Pass with card icon
                    if (contact.contact_type === 'membership_pass') {
                      return (
                        <a
                          key={index}
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex-shrink-0 hover:opacity-80 transition-opacity text-electric-cyan dark:text-electric-cyan"
                          title="Open ledenpas"
                        >
                          <CreditCard className="w-5 h-5" />
                        </a>
                      );
                    }

                    // WhatsApp icon
                    const SocialIcon = getSocialIcon(contact.contact_type);
                    const iconColor = getSocialIconColor(contact.contact_type);

                    if (!SocialIcon) return null;

                    return (
                      <a
                        key={index}
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`flex-shrink-0 hover:opacity-80 transition-opacity ${iconColor}`}
                        title={`${contact.contact_type.charAt(0).toUpperCase() + contact.contact_type.slice(1)}`}
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
      
      {/* Tab Navigation */}
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav className="flex gap-8">
          <TabButton label="Profiel" isActive={activeTab === 'profile'} onClick={() => setActiveTab('profile')} />
          {canAccessClothing && (
            <TabButton label="Kleding" isActive={activeTab === 'clothing'} onClick={() => setActiveTab('clothing')} count={clothingProfile?.current_items?.length || 0} />
          )}
          <TabButton label="Tijdlijn" isActive={activeTab === 'timeline'} onClick={() => setActiveTab('timeline')} count={timeline?.length || 0} />
          <TabButton label="Rollen" isActive={activeTab === 'work'} onClick={() => setActiveTab('work')} count={sortedWorkHistory?.length || 0} />
          {canAccessFairplay && hasDisciplineCases && (
            <TabButton label="Tuchtzaken" isActive={activeTab === 'discipline'} onClick={() => setActiveTab('discipline')} count={disciplineCases?.length || 0} />
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
                {canEditContact && (
                  <button
                    onClick={() => setShowContactModal(true)}
                    className="btn-tertiary text-sm"
                  >
                    <Pencil className="w-4 h-4 md:mr-1" />
                    <span className="hidden md:inline">Bewerken</span>
                  </button>
                )}
              </div>
            {contactItems.length > 0 ? (
              <div className="space-y-2">
                {contactItems.map((contact, index) => {
                  const Icon = contact.type === 'email' ? Mail : Phone;
                  const isEmail = contact.type === 'email';
                  const linkHref = isEmail
                    ? `mailto:${contact.value}`
                    : `tel:${formatPhoneForTel(contact.value)}`;

                  return (
                    <div key={index}>
                      <div className="flex items-center rounded-md -mx-2 px-2 py-1.5">
                        <Icon className="w-4 h-4 text-gray-400 mr-3 flex-shrink-0" />
                        <div className="flex-1 min-w-0 flex items-center gap-2">
                          <span className="text-sm text-gray-500 dark:text-gray-400">{contact.label}: </span>
                          <a
                            href={linkHref}
                            className="text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light hover:underline"
                          >
                            {contact.type === 'email' ? contact.value : formatPhoneForDisplay(contact.value)}
                          </a>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                Nog geen contactgegevens.{canEditContact && <> <button onClick={() => setShowContactModal(true)} className="text-electric-cyan hover:underline">Toevoegen</button></>}
              </p>
            )}
            {/* View in Google Contacts link - only for synced contacts with email */}
            {person.google_contact_id && acf.email_1 && (
              <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <a
                  href={`https://contacts.google.com/${acf.email_1}`}
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
                <div className="flex items-center justify-between mb-3">
                  <h2 className="font-semibold text-brand-gradient">Adressen</h2>
                  {canEditContact && (
                    <button
                      onClick={() => {
                        setEditingAddress(null);
                        setEditingAddressIndex(null);
                        setShowAddressModal(true);
                      }}
                      className="btn-tertiary text-sm"
                      title="Adres toevoegen"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  )}
                </div>
                {acf.addresses?.length > 0 ? (
                  <div className="space-y-3">
                    {acf.addresses.map((address, index) => {
                      const addressLines = [
                        [address.street_name, address.house_number, address.house_number_addition].filter(Boolean).join(' '),
                        [address.city, address.state, address.postal_code].filter(Boolean).join(', '),
                        [address.country, address.country_code ? `(${address.country_code})` : null].filter(Boolean).join(' ')
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
                              className="text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light hover:underline text-sm"
                            >
                              {addressLines.map((line, i) => (
                                <span key={i} className="block">{line}</span>
                              ))}
                            </a>
                          </div>
                          {canEditContact && (
                            <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                              <button
                                onClick={() => {
                                  setEditingAddress(acf.addresses[index]);
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
                          )}
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p className="text-sm text-gray-500 text-center py-4">
                    Nog geen adressen.{canEditContact && <> <button onClick={() => { setEditingAddress(null); setEditingAddressIndex(null); setShowAddressModal(true); }} className="text-electric-cyan hover:underline">Toevoegen</button></>}
                  </p>
                )}
              </div>
            )}

            {/* Custom Fields */}
            <CustomFieldsSection
              postType="person"
              postId={parseInt(id)}
              acfData={person?.fields}
              onUpdate={canEditPeople ? (newAcfValues) => {
                const acfData = sanitizePersonFields(person.fields, newAcfValues);
                updatePerson.mutateAsync({
                  id,
                  data: { fields: acfData },
                });
              } : undefined}
              isUpdating={updatePerson.isPending}
              excludeLabelPrefixes={['Nikki']}
            />
            </div>

            {/* Column 2: Sportlink, Account, Relaties, VOG */}
            <div className="space-y-6">
            {/* Sportlink Card */}
            <SportlinkCard acfData={person?.fields} metaData={person?.meta} primaryTeam={sportlinkPrimaryTeam} />

            {/* Keep the card available for editable people so the first relationship can be added. */}
            {(canEditPeople || sortedRelationships?.length > 0) && (
            <div className="card p-6">
              <div className="flex items-center justify-between mb-3">
                <h2 className="font-semibold text-brand-gradient">Relaties</h2>
                {canEditPeople && (
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => {
                        setEditingRelationship(null);
                        setEditingRelationshipIndex(null);
                        setShowRelationshipModal(true);
                      }}
                      className="btn-tertiary text-sm"
                      title="Relatie toevoegen"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  </div>
                )}
              </div>
              <div className="space-y-2">
                {sortedRelationships.map((rel, index) => {
                  const originalIndex = person?.fields?.relationships?.findIndex(
                    r => r.related_person_id === rel.related_person_id &&
                         r.relationship_type_id === rel.relationship_type_id
                  ) ?? index;
                  
                  return (
                    <div key={index} className="flex items-center p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 group">
                      <Link
                        to={`/people/${rel.related_person_id}`}
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
                            {decodeHtml(rel.person_name) || `Person #${rel.related_person_id}`}
                            {personDeceasedMap[rel.related_person_id] && (
                              <span className="text-gray-400 ml-1" title="Overleden">†</span>
                            )}
                          </p>
                          <p className="text-xs text-gray-500 dark:text-gray-400">{decodeHtml(rel.relationship_name || rel.relationship_label)}</p>
                        </div>
                      </Link>
                      {canEditPeople && (
                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                          <button
                            onClick={() => {
                              const relData = person?.fields?.relationships?.[originalIndex];
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
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
            )}

            {/* VOG Card */}
            <VOGCard
              acfData={person?.fields}
              personId={parseInt(id)}
              onUpdateField={(fieldName, value) => {
                updatePerson.mutateAsync({
                  id,
                  data: { meta: { [fieldName]: value } },
                });
              }}
              isUpdating={updatePerson.isPending}
            />
            </div>

            <PersonShiftOverview overview={shiftOverview} isLoading={isShiftOverviewLoading} />
          </div>
        )}

        {/* Clothing Tab */}
        {activeTab === 'clothing' && canAccessClothing && (
          <div className="space-y-6">
            <div className="card p-6">
              <h2 className="font-semibold text-brand-gradient mb-3">Kledingprofiel</h2>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <p className="text-xs text-gray-500">Eligibility</p>
                  <p className={`font-medium ${clothingProfile?.eligibility?.eligible ? 'text-green-600' : 'text-red-600'}`}>
                    {clothingProfile?.eligibility?.eligible ? 'Geschikt' : 'Niet geschikt'}
                  </p>
                  <p className="text-xs text-gray-500 mt-1">{clothingProfile?.eligibility?.reason || ''}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-500">Items in bezit</p>
                  <p className="font-medium">{clothingProfile?.current_items?.length || 0}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-500">Openstaande borg</p>
                  <p className="font-medium">{(clothingProfile?.outstanding_deposit || 0).toFixed(2)}</p>
                </div>
              </div>
            </div>

            <div className="card p-6">
              <h2 className="font-semibold text-brand-gradient mb-4">Huidige items</h2>
              {(clothingProfile?.current_items || []).length > 0 ? (
                <div className="space-y-2">
                  {clothingProfile.current_items.map((item) => (
                    <div key={item.id} className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 py-2">
                      <div>
                        <p className="text-sm font-medium">{item.item_name}</p>
                        <p className="text-xs text-gray-500">Maat {item.size} • {item.season}</p>
                      </div>
                      <span className="text-xs rounded-full px-2 py-1 bg-cyan-50 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-200">
                        Uitgegeven
                      </span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500">Geen items in bezit.</p>
              )}
            </div>

            <div className="card p-6">
              <h2 className="font-semibold text-brand-gradient mb-4">Kledinghistorie</h2>
              {(clothingProfile?.history || []).length > 0 ? (
                <div className="space-y-2">
                  {clothingProfile.history.map((row) => (
                    <div key={row.id} className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 py-2">
                      <div>
                        <p className="text-sm font-medium">{row.item_name} ({row.size})</p>
                        <p className="text-xs text-gray-500">{row.date} • {row.season}</p>
                      </div>
                      <span className={`text-xs rounded-full px-2 py-1 ${row.in_or_out === 'out' ? 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-200' : 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-200'}`}>
                        {row.in_or_out === 'out' ? 'Uit' : 'In'}
                      </span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500">Nog geen kledinghistorie.</p>
              )}
            </div>
          </div>
        )}

        {/* Timeline Tab */}
        {activeTab === 'timeline' && (
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold text-brand-gradient">Tijdlijn</h2>
              {canEditPeople && (
                <div className="flex gap-2">
                  <button
                    onClick={() => setShowNoteModal(true)}
                    className="btn-tertiary text-sm"
                    title="Notitie toevoegen"
                  >
                    <StickyNote className="w-4 h-4 md:mr-1" />
                    <span className="hidden md:inline">Notitie</span>
                  </button>
                  <button
                    onClick={() => setShowActivityModal(true)}
                    className="btn-tertiary text-sm"
                    title="Activiteit toevoegen"
                  >
                    <MessageCircle className="w-4 h-4 md:mr-1" />
                    <span className="hidden md:inline">Activiteit</span>
                  </button>
                </div>
              )}
            </div>

            {!canAccessPersonNotes && (
              <p className="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                Notities en activiteiten zijn alleen zichtbaar voor de ledenadministratie en de
                penningmeester. Je ziet hier je eigen taken bij deze persoon.
              </p>
            )}

            <TimelineView
              timeline={timeline || []}
              onEdit={canEditPeople ? handleEditTimelineItem : undefined}
              onDelete={canEditPeople ? handleDeleteTimelineItem : undefined}
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
                  const teamData = job.team_id ? teamMap[job.team_id] : null;
                  const originalIndex = job.originalIndex;
                  const startDate = parseAcfDate(job.start_date);
                  const endDate = parseAcfDate(job.end_date);

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
                        {job.team_id && teamData && (
                          <Link
                            to={`/${teamData.type === 'commissie' ? 'commissies' : 'teams'}/${job.team_id}`}
                            className="text-sm text-electric-cyan hover:underline"
                          >
                            {teamData.name}
                          </Link>
                        )}
                        {!job.team_id && job.team_name_text && (
                          <p className="text-sm text-gray-700 dark:text-gray-300">
                            {job.team_name_text}
                          </p>
                        )}
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                          {startDate && format(startDate, 'MMM yyyy')}
                          {' - '}
                          {job.is_current ? 'Heden' : endDate ? format(endDate, 'MMM yyyy') : ''}
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
              invoicedCaseIds={new Set(invoicedCaseIds)}
              selectedCaseIds={selectedCaseIds}
              onSelectionChange={setSelectedCaseIds}
              onCreateInvoice={handleCreateInvoice}
              isCreatingInvoice={createInvoice.isPending}
              canCreateInvoice={canAccessFairplay && canEditFinancieel}
            />
          </div>
        )}

        </div>

        {/* Sidebar - always visible */}
        <div className="hidden lg:block">
          <div className="sticky top-6 space-y-6">
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
                {canEditPeople && (
                  <button
                    onClick={() => {
                      setEditingTodo(null);
                      setShowTodoModal(true);
                    }}
                    className="btn-tertiary text-sm"
                    title="Taak toevoegen"
                  >
                    <Plus className="w-4 h-4" />
                  </button>
                )}
              </div>
              {sortedTodos.length > 0 ? (
                <div className="space-y-2">
                  {sortedTodos.map((todo) => (
                    <TodoItem
                      key={todo.id}
                      todo={todo}
                      currentPersonId={parseInt(id, 10)}
                      onToggle={handleToggleTodo}
                      onEdit={canEditPeople ? (t) => {
                        setEditingTodo(t);
                        setShowTodoModal(true);
                      } : undefined}
                      onDelete={canEditPeople ? handleDeleteTodo : undefined}
                      onSendVerificationEmail={handleSendVerificationEmail}
                      verificationEmailSending={verificationTodoId === todo.id}
                    />
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-4">
                  Nog geen taken.
                </p>
              )}
            </div>

            {/* Account Card (admin only, when account exists) */}
            {config.isAdmin && person?.linked_user_id && (
              <AccountCard personId={id} personData={person} />
            )}
          </div>
        </div>
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
                {canEditPeople && (
                  <button
                    onClick={() => {
                      setEditingTodo(null);
                      setShowTodoModal(true);
                      setShowMobileTodos(false);
                    }}
                    className="btn-tertiary text-sm"
                    title="Taak toevoegen"
                  >
                    <Plus className="w-4 h-4" />
                  </button>
                )}
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
                      onEdit={canEditPeople ? (t) => {
                        setEditingTodo(t);
                        setShowTodoModal(true);
                        setShowMobileTodos(false);
                      } : undefined}
                      onDelete={canEditPeople ? handleDeleteTodo : undefined}
                      onSendVerificationEmail={handleSendVerificationEmail}
                      verificationEmailSending={verificationTodoId === todo.id}
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
          
          {canEditPeople && (
            <ContactEditModal
              isOpen={showContactModal}
              onClose={() => setShowContactModal(false)}
              onSubmit={handleSaveContacts}
              isLoading={isSavingContacts}
              email1={acf.email_1 || ''}
              email2={acf.email_2 || ''}
              mobile1={acf.mobile_1 || ''}
              mobile2={acf.mobile_2 || ''}
              telephone1={acf.telephone_1 || ''}
              telephone2={acf.telephone_2 || ''}
            />
          )}

          {canEditPeople && (
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
          )}

          {canEditPeople && (
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
          )}

      </div>
    </PullToRefreshWrapper>
  );
}
