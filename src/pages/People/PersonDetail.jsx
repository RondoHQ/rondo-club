import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft, Edit, Trash2, Star, Mail, Phone,
  MapPin, Globe, Building2, Calendar, Plus, Gift, Heart, Pencil, MessageCircle, Linkedin, X, Camera, Download
} from 'lucide-react';
import { usePerson, usePersonTimeline, usePersonDates, useDeletePerson, useDeleteNote, useDeleteDate, useUpdatePerson } from '@/hooks/usePeople';
import { format, differenceInYears } from 'date-fns';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { decodeHtml, getCompanyName, sanitizePersonAcf } from '@/utils/formatters';
import { downloadVCard } from '@/utils/vcard';

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
  const [isAddingLabel, setIsAddingLabel] = useState(false);
  const [selectedLabelToAdd, setSelectedLabelToAdd] = useState('');
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false);
  const fileInputRef = useRef(null);
  
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

  // Find death date from person dates
  const deathDate = personDates?.find(d => {
    const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
    return dateType?.toLowerCase() === 'died';
  });
  const deathDateValue = deathDate?.date_value ? new Date(deathDate.date_value) : null;
  
  // Calculate age - if died, show age at death, otherwise current age
  const isDeceased = !!deathDateValue;
  const age = birthDate ? (isDeceased 
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
    } catch (error) {
      console.error('Failed to delete person:', error);
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

  // Handle deleting a contact detail
  const handleDeleteContact = async (index) => {
    if (!window.confirm('Are you sure you want to delete this contact detail?')) {
      return;
    }
    
    const updatedContactInfo = [...(person.acf?.contact_info || [])];
    updatedContactInfo.splice(index, 1);
    
    const acfData = sanitizePersonAcf(person.acf, {
      contact_info: updatedContactInfo,
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
    } catch (error) {
      console.error('Failed to upload photo:', error);
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
    } catch (error) {
      console.error('Failed to export vCard:', error);
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
        <Link to="/people" className="btn-secondary mt-4">Back to People</Link>
      </div>
    );
  }
  
  // Don't render if person is trashed (redirect will happen)
  if (person.status === 'trash') {
    return null;
  }
  
  const acf = person.acf || {};
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to="/people" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to People</span>
        </Link>
        <div className="flex gap-2">
          <button onClick={handleExportVCard} className="btn-secondary">
            <Download className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Export vCard</span>
          </button>
          <Link to={`/people/${id}/edit`} className="btn-secondary">
            <Edit className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Edit</span>
          </Link>
          <button onClick={handleDelete} className="btn-danger">
            <Trash2 className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Delete</span>
          </button>
        </div>
      </div>
      
      {/* Profile header */}
      <div className="card p-6 relative">
        <div className="absolute top-4 right-4">
          <Link
            to={`/people/${id}/family-tree`}
            className="btn-secondary text-sm"
          >
            View Family Tree
          </Link>
        </div>
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
          <div className="relative group">
            {person.thumbnail ? (
              <img
                src={person.thumbnail}
                alt={person.name}
                className="w-24 h-24 rounded-full object-cover"
              />
            ) : (
              <div className="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center">
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

          <div className="flex-1">
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
            {isDeceased && deathDateValue && age !== null && (
              <p className="text-gray-500 text-sm mt-1">
                {getGenderSymbol(acf.gender) && <span className="mr-1">{getGenderSymbol(acf.gender)}</span>}
                Died {format(deathDateValue, 'MMMM d, yyyy')} at age {age}
              </p>
            )}
            {!isDeceased && age !== null && (
              <p className="text-gray-500 text-sm mt-1">
                {getGenderSymbol(acf.gender) && <span className="mr-1">{getGenderSymbol(acf.gender)}</span>}
                {age} years old
              </p>
            )}
            <div className="mt-2">
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
            </div>
          </div>
        </div>
      </div>
      
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Contact info - only show for living people */}
          {!isDeceased && (
            <div className="card p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold">Contact Information</h2>
                <Link
                  to={`/people/${id}/contact/new`}
                  className="btn-secondary text-sm"
                >
                  <Plus className="w-4 h-4 md:mr-1" />
                  <span className="hidden md:inline">Add contact detail</span>
                </Link>
              </div>
            {acf.contact_info?.length > 0 ? (
              <div className="space-y-2">
                {acf.contact_info.map((contact, index) => {
                  const Icon = contact.contact_type === 'email' ? Mail :
                               contact.contact_type === 'phone' || contact.contact_type === 'mobile' ? Phone :
                               contact.contact_type === 'address' ? MapPin :
                               contact.contact_type === 'linkedin' ? Linkedin :
                               contact.contact_type === 'website' ? Globe : Globe;

                  // Determine if this should be a clickable link
                  const isEmail = contact.contact_type === 'email';
                  const isPhone = contact.contact_type === 'phone' || contact.contact_type === 'mobile';
                  const isMobile = contact.contact_type === 'mobile';
                  const isWebsite = contact.contact_type === 'website' || 
                                   contact.contact_type === 'linkedin' || 
                                   contact.contact_type === 'twitter' || 
                                   contact.contact_type === 'instagram' || 
                                   contact.contact_type === 'facebook';
                  const isAddress = contact.contact_type === 'address';
                  const isLinkedIn = contact.contact_type === 'linkedin';
                  
                  let linkHref = null;
                  let linkTarget = null;
                  
                  if (isEmail) {
                    linkHref = `mailto:${contact.contact_value}`;
                  } else if (isPhone) {
                    linkHref = `tel:${formatPhoneForTel(contact.contact_value)}`;
                  } else if (isWebsite) {
                    // Ensure URL has protocol
                    let url = contact.contact_value;
                    if (!url.match(/^https?:\/\//i)) {
                      url = `https://${url}`;
                    }
                    linkHref = url;
                    linkTarget = '_blank';
                  } else if (isAddress) {
                    // Link to Google Maps
                    const encodedAddress = encodeURIComponent(contact.contact_value);
                    linkHref = `https://www.google.com/maps/search/?api=1&query=${encodedAddress}`;
                    linkTarget = '_blank';
                  }

                  return (
                    <div key={index} className="group">
                      <div className="flex items-center rounded-md -mx-2 px-2 py-1.5 group-hover:bg-gray-50 transition-colors">
                        {!isLinkedIn && <Icon className="w-4 h-4 text-gray-400 mr-3 flex-shrink-0" />}
                        <div className="flex-1 min-w-0">
                          {isLinkedIn ? (
                            <Linkedin className="w-4 h-4 text-blue-600 inline-block mr-2 align-middle" />
                          ) : (
                            <span className="text-sm text-gray-500">{contact.contact_label || contact.contact_type}: </span>
                          )}
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
                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                        {isMobile && (
                          <a
                            href={`whatsapp:${formatPhoneForTel(contact.contact_value)}`}
                            className="p-1 hover:bg-green-50 rounded"
                            title="Open WhatsApp"
                          >
                            <MessageCircle className="w-4 h-4 text-gray-400 hover:text-green-600" />
                          </a>
                        )}
                        <Link
                          to={`/people/${id}/contact/${index}/edit`}
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Edit contact detail"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </Link>
                        <button
                          onClick={() => handleDeleteContact(index)}
                          className="p-1 hover:bg-red-50 rounded"
                          title="Delete contact detail"
                        >
                          <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                        </button>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                No contact information yet.
              </p>
            )}
            </div>
          )}

          {/* Important Dates */}
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Important Dates</h2>
              <Link
                to={`/dates/new?person=${id}`}
                className="btn-secondary text-sm"
              >
                <Plus className="w-4 h-4 md:mr-1" />
                <span className="hidden md:inline">Add Important Date</span>
              </Link>
            </div>
            {allDates.length > 0 ? (
              <div className="space-y-3">
                {allDates.map((date) => {
                  const dateType = Array.isArray(date.date_type) ? date.date_type[0] : date.date_type;
                  const dateTypeLower = dateType?.toLowerCase() || '';
                  const isDied = dateTypeLower.includes('died');
                  const Icon = dateTypeLower.includes('wedding') || dateTypeLower.includes('marriage') ? Heart :
                               dateTypeLower.includes('birthday') ? Gift : Calendar;

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
                          {date.date_value && format(new Date(date.date_value), 'MMMM d, yyyy')}
                        </p>
                        {dateType && (
                          <p className="text-xs text-gray-400 capitalize">{dateType}</p>
                        )}
                      </div>
                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <Link
                          to={`/dates/${date.id}/edit`}
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Edit date"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </Link>
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
                No important dates yet.
              </p>
            )}
          </div>

          {/* Work history */}
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Work History</h2>
              <Link
                to={`/people/${id}/work-history/new`}
                className="btn-secondary text-sm"
              >
                <Plus className="w-4 h-4 md:mr-1" />
                <span className="hidden md:inline">Add Work History</span>
              </Link>
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
                        <Link
                          to={`/people/${id}/work-history/${originalIndex}/edit`}
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Edit work history"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </Link>
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
                No work history yet.
              </p>
            )}
          </div>
          
          {/* Timeline */}
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Timeline</h2>
              <button className="btn-secondary text-sm">
                <Plus className="w-4 h-4 md:mr-1" />
                <span className="hidden md:inline">Add Note</span>
              </button>
            </div>
            
            {timeline?.length > 0 ? (
              <div className="space-y-4">
                {timeline.map((item) => (
                  <div key={item.id} className="border-l-2 border-gray-200 pl-4 pb-4 group">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2 text-sm text-gray-500">
                        <span className="capitalize">{item.type}</span>
                        <span>•</span>
                        <span>{format(new Date(item.created), 'MMM d, yyyy')}</span>
                      </div>
                      <button
                        onClick={() => handleDeleteNote(item.id)}
                        className="opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-red-50 rounded"
                        title="Delete note"
                      >
                        <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
                      </button>
                    </div>
                    <p className="mt-1">{item.content}</p>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                No notes or activities yet.
              </p>
            )}
          </div>
        </div>
        
        {/* Sidebar */}
        <div className="space-y-6">
          {/* How we met */}
          {(acf.how_we_met || acf.met_date) && (
            <div className="card p-6">
              <h2 className="font-semibold mb-3">How We Met</h2>
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
          <div className="card p-6">
            <div className="flex items-center justify-between mb-3">
              <h2 className="font-semibold">Relationships</h2>
              <Link
                to={`/people/${id}/relationship/new`}
                className="btn-secondary text-sm"
              >
                <Plus className="w-4 h-4 md:mr-1" />
                <span className="hidden md:inline">Add Relationship</span>
              </Link>
            </div>
            {sortedRelationships?.length > 0 ? (
              <div className="space-y-2">
                {sortedRelationships.map((rel, index) => {
                  // Find the original index in the relationships array for edit/delete
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
                        <Link
                          to={`/people/${id}/relationship/${originalIndex}/edit`}
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Edit relationship"
                        >
                          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                        </Link>
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
                No relationships yet.
              </p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
