import { useEffect, useState, useMemo, useRef } from 'react';
import { Link, useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { ArrowLeft, Save, X, Plus } from 'lucide-react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { usePerson, useUpdatePerson, usePeople } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { getPersonName, sanitizePersonAcf } from '@/utils/formatters';

function SearchablePersonSelector({ value, onChange, people, isLoading, excludePersonId }) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const inputRef = useRef(null);
  const dropdownRef = useRef(null);

  // Sort people by first name ascending, then filter out excluded person
  const sortedAndFilteredPeople = useMemo(() => {
    const filtered = people.filter(p => p.id !== excludePersonId);
    return filtered.sort((a, b) => {
      const firstNameA = (a.first_name || a.acf?.first_name || '').toLowerCase();
      const firstNameB = (b.first_name || b.acf?.first_name || '').toLowerCase();
      if (firstNameA < firstNameB) return -1;
      if (firstNameA > firstNameB) return 1;
      return 0;
    });
  }, [people, excludePersonId]);

  // Filter by search term
  const filteredPeople = useMemo(() => {
    if (!searchTerm) return sortedAndFilteredPeople.slice(0, 10);
    
    const term = searchTerm.toLowerCase();
    return sortedAndFilteredPeople.filter(p => {
      const name = getPersonName(p).toLowerCase();
      const firstName = (p.first_name || p.acf?.first_name || '').toLowerCase();
      const lastName = (p.last_name || p.acf?.last_name || '').toLowerCase();
      
      return name.includes(term) || firstName.includes(term) || lastName.includes(term);
    }).slice(0, 10);
  }, [sortedAndFilteredPeople, searchTerm]);

  const selectedPerson = useMemo(() => {
    return sortedAndFilteredPeople.find(p => p.id === value);
  }, [sortedAndFilteredPeople, value]);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        inputRef.current &&
        !inputRef.current.contains(event.target)
      ) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSelect = (personId) => {
    onChange(personId);
    setSearchTerm('');
    setIsOpen(false);
  };

  const handleClear = () => {
    onChange(null);
    setSearchTerm('');
    setIsOpen(false);
  };

  const displayValue = selectedPerson
    ? getPersonName(selectedPerson)
    : '';

  return (
    <div className="relative">
      {/* Selected person display / Search input */}
      <div className="relative">
        {selectedPerson && !isOpen ? (
          <div 
            className="flex items-center justify-between input pr-8 cursor-text"
            onClick={() => setIsOpen(true)}
          >
            <span>{displayValue}</span>
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                handleClear();
              }}
              className="absolute right-2 text-gray-400 hover:text-gray-600"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        ) : (
          <input
            ref={inputRef}
            type="text"
            value={searchTerm}
            onChange={(e) => {
              setSearchTerm(e.target.value);
              setIsOpen(true);
            }}
            onFocus={() => setIsOpen(true)}
            placeholder="Search for a person..."
            className="input"
            disabled={isLoading}
          />
        )}
      </div>

      {/* Dropdown */}
      {isOpen && (searchTerm || !selectedPerson) && (
        <div
          ref={dropdownRef}
          className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
        >
          {filteredPeople.length > 0 ? (
            filteredPeople.map(person => (
              <button
                key={person.id}
                type="button"
                onClick={() => handleSelect(person.id)}
                className={`w-full text-left px-4 py-2 hover:bg-gray-50 ${
                  value === person.id ? 'bg-primary-50' : ''
                }`}
              >
                {getPersonName(person)}
              </button>
            ))
          ) : (
            <p className="px-4 py-2 text-gray-500 text-sm">No people found</p>
          )}
        </div>
      )}
    </div>
  );
}

export default function RelationshipForm() {
  const { personId, index } = useParams();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const relationshipIndex = parseInt(index, 10);
  const isEditing = !isNaN(relationshipIndex);
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(personId);
  const updatePerson = useUpdatePerson();
  
  // Fetch all people for the selector
  const { data: allPeople = [], isLoading: isPeopleLoading } = usePeople();
  
  // Fetch relationship types
  const { data: relationshipTypes = [], isLoading: isRelationshipTypesLoading } = useQuery({
    queryKey: ['relationship-types'],
    queryFn: async () => {
      const response = await wpApi.getRelationshipTypes();
      return response.data;
    },
  });

  const { register, handleSubmit, reset, control, setValue, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      related_person: null,
      relationship_type: '',
      relationship_label: '',
    },
  });
  
  // Handle pre-selecting newly created person
  useEffect(() => {
    const newPersonId = searchParams.get('newPersonId');
    if (newPersonId && !isEditing && !isPeopleLoading) {
      const personIdNum = parseInt(newPersonId, 10);
      if (!isNaN(personIdNum)) {
        // Check if person is already in the list
        const personExists = allPeople.some(p => p.id === personIdNum);
        
        if (personExists) {
          // Person is in the list, set the value immediately
          setValue('related_person', personIdNum);
          
          // Remove query parameter from URL
          const newSearchParams = new URLSearchParams(searchParams);
          newSearchParams.delete('newPersonId');
          navigate(
            { search: newSearchParams.toString() },
            { replace: true }
          );
        } else {
          // Person not in list yet, refetch and wait
          const fetchAndSelectPerson = async () => {
            try {
              // Invalidate and refetch people query
              await queryClient.invalidateQueries({ queryKey: ['people'] });
              const { data: refetchedPeople = [] } = await queryClient.refetchQueries({ queryKey: ['people'] });
              
              // Check if person is now in the list
              const personNowExists = refetchedPeople.some(p => p.id === personIdNum);
              
              if (personNowExists) {
                // Set the related person value
                setValue('related_person', personIdNum);
              }
              
              // Remove query parameter from URL
              const newSearchParams = new URLSearchParams(searchParams);
              newSearchParams.delete('newPersonId');
              navigate(
                { search: newSearchParams.toString() },
                { replace: true }
              );
            } catch (error) {
              console.error('Failed to load new person:', error);
              // Still remove query param even if fetch fails
              const newSearchParams = new URLSearchParams(searchParams);
              newSearchParams.delete('newPersonId');
              navigate(
                { search: newSearchParams.toString() },
                { replace: true }
              );
            }
          };
          
          fetchAndSelectPerson();
        }
      }
    }
  }, [searchParams, isEditing, isPeopleLoading, allPeople, setValue, navigate, queryClient]);

  // Load relationship item when editing
  useEffect(() => {
    if (person && isEditing) {
      const relationships = person.acf?.relationships || [];
      const relationshipItem = relationships[relationshipIndex];
      
      if (relationshipItem) {
        reset({
          related_person: relationshipItem.related_person || null,
          relationship_type: relationshipItem.relationship_type ? String(relationshipItem.relationship_type) : '',
          relationship_label: relationshipItem.relationship_label || '',
        });
      }
    }
  }, [person, relationshipIndex, isEditing, reset]);

  // Update document title
  useDocumentTitle(
    isEditing
      ? `Edit relationship - ${person?.title?.rendered || person?.title || 'person'}`
      : `Add relationship - ${person?.title?.rendered || person?.title || 'person'}`
  );

  const onSubmit = async (data) => {
    try {
      const relationships = [...(person.acf?.relationships || [])];
      
      const relationshipItem = {
        related_person: data.related_person || null,
        relationship_type: data.relationship_type || null,
        relationship_label: data.relationship_label || '',
      };

      if (isEditing) {
        // Update existing item
        relationships[relationshipIndex] = relationshipItem;
      } else {
        // Add new item
        relationships.push(relationshipItem);
      }

      // Sanitize ACF data and set the updated relationships
      const acfData = sanitizePersonAcf(person.acf, {
        relationships: relationships,
      });

      await updatePerson.mutateAsync({
        id: personId,
        data: {
          acf: acfData,
        },
      });
      
      navigate(`/people/${personId}`);
    } catch (error) {
      console.error('Failed to save relationship:', error);
    }
  };

  if (isLoadingPerson) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  if (!person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600">Failed to load person.</p>
        <Link to="/people" className="btn-secondary mt-4">Back to people</Link>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to={`/people/${personId}`} className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to person</span>
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit relationship' : 'Add relationship'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Related Person */}
          <div>
            <label className="label">Related person *</label>
            <Controller
              name="related_person"
              control={control}
              rules={{ required: 'Please select a person' }}
              render={({ field }) => (
                <SearchablePersonSelector
                  value={field.value}
                  onChange={field.onChange}
                  people={allPeople}
                  isLoading={isPeopleLoading}
                  excludePersonId={parseInt(personId, 10)}
                />
              )}
            />
            {errors.related_person && (
              <p className="text-sm text-red-600 mt-1">{errors.related_person.message}</p>
            )}
            {!isEditing && (
              <div className="mt-2">
                <Link
                  to={`/people/new?returnTo=relationship&sourcePersonId=${personId}`}
                  className="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700"
                >
                  <Plus className="w-4 h-4" />
                  Add new person
                </Link>
              </div>
            )}
          </div>

          {/* Relationship Type */}
          <div>
            <label className="label">Relationship type</label>
            <select
              {...register('relationship_type', { 
                setValueAs: (v) => v ? parseInt(v, 10) : null 
              })}
              className="input"
              disabled={isRelationshipTypesLoading}
            >
              <option value="">Select a type...</option>
              {relationshipTypes.map(type => (
                <option key={type.id} value={type.id}>
                  {type.name}
                </option>
              ))}
            </select>
          </div>

          {/* Custom Label */}
          <div>
            <label className="label">Custom label</label>
            <input
              {...register('relationship_label')}
              className="input"
              placeholder="e.g., Brother-in-law"
            />
            <p className="text-xs text-gray-500 mt-1">
              Optional: Override the relationship type with a custom label
            </p>
          </div>
          
          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to={`/people/${personId}`} className="btn-secondary">
              Cancel
            </Link>
            <button 
              type="submit" 
              className="btn-primary"
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              ) : (
                <Save className="w-4 h-4 mr-2" />
              )}
              {isEditing ? 'Save changes' : 'Add relationship'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

