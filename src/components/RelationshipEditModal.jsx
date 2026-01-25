import { useEffect, useState, useMemo, useRef } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, Plus } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { getPersonName } from '@/utils/formatters';

function SearchablePersonSelector({ value, onChange, people, isLoading, excludePersonId }) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const inputRef = useRef(null);
  const dropdownRef = useRef(null);

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

  const displayValue = selectedPerson ? getPersonName(selectedPerson) : '';

  return (
    <div className="relative">
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
              className="absolute right-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
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
            placeholder="Zoek naar een persoon..."
            className="input"
            disabled={isLoading}
          />
        )}
      </div>

      {isOpen && (searchTerm || !selectedPerson) && (
        <div
          ref={dropdownRef}
          className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-48 overflow-y-auto"
        >
          {filteredPeople.length > 0 ? (
            filteredPeople.map(person => (
              <button
                key={person.id}
                type="button"
                onClick={() => handleSelect(person.id)}
                className={`w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-50 ${
                  value === person.id ? 'bg-accent-50 dark:bg-accent-800' : ''
                }`}
              >
                {getPersonName(person)}
              </button>
            ))
          ) : (
            <p className="px-4 py-2 text-gray-500 dark:text-gray-400 text-sm">Geen personen gevonden</p>
          )}
        </div>
      )}
    </div>
  );
}

export default function RelationshipEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading, 
  relationship = null,
  personId,
  allPeople = [],
  isPeopleLoading = false,
  onCreatePerson
}) {
  const isEditing = !!relationship;

  // Fetch relationship types
  const { data: relationshipTypes = [], isLoading: isRelationshipTypesLoading } = useQuery({
    queryKey: ['relationship-types'],
    queryFn: async () => {
      const response = await wpApi.getRelationshipTypes();
      return response.data;
    },
  });

  const { register, handleSubmit, reset, control, formState: { errors } } = useForm({
    defaultValues: {
      related_person: null,
      relationship_type: '',
      relationship_label: '',
    },
  });

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      if (relationship) {
        reset({
          related_person: relationship.related_person || null,
          relationship_type: relationship.relationship_type ? String(relationship.relationship_type) : '',
          relationship_label: relationship.relationship_label || '',
        });
      } else {
        reset({
          related_person: null,
          relationship_type: '',
          relationship_label: '',
        });
      }
    }
  }, [isOpen, relationship, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      related_person: data.related_person || null,
      relationship_type: data.relationship_type ? parseInt(data.relationship_type, 10) : null,
      relationship_label: data.relationship_label || '',
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Relatie bewerken' : 'Relatie toevoegen'}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Related Person */}
            <div>
              <label className="label">Gerelateerde persoon *</label>
              <Controller
                name="related_person"
                control={control}
                rules={{ required: 'Selecteer een persoon' }}
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
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.related_person.message}</p>
              )}
              {!isEditing && onCreatePerson && (
                <div className="mt-2">
                  <button
                    type="button"
                    onClick={onCreatePerson}
                    className="inline-flex items-center gap-1 text-sm text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300"
                  >
                    <Plus className="w-4 h-4" />
                    Nieuwe persoon toevoegen
                  </button>
                </div>
              )}
            </div>

            {/* Relationship Type */}
            <div>
              <label className="label">Type relatie</label>
              <select
                {...register('relationship_type')}
                className="input"
                disabled={isRelationshipTypesLoading || isLoading}
              >
                <option value="">Selecteer een type...</option>
                {relationshipTypes.map(type => (
                  <option key={type.id} value={type.id}>
                    {type.name}
                  </option>
                ))}
              </select>
            </div>

            {/* Custom Label */}
            <div>
              <label className="label">Aangepast label</label>
              <input
                {...register('relationship_label')}
                className="input"
                placeholder="bijv. Zwager"
                disabled={isLoading}
              />
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Optioneel: Vervang het relatietype met een aangepast label
              </p>
            </div>
          </div>

          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Annuleren
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading}
            >
              {isLoading ? 'Opslaan...' : (isEditing ? 'Wijzigingen opslaan' : 'Relatie toevoegen')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
