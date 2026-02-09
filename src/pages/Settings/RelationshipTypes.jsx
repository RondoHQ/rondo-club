import { useState, useMemo, useRef, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Save, Plus, Trash2, Edit2, X, RotateCcw, ShieldAlert } from 'lucide-react';
import { wpApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { Link } from 'react-router-dom';

// Searchable Relationship Type Selector Component
function SearchableRelationshipTypeSelector({ value, onChange, relationshipTypes, currentTypeId = null }) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const inputRef = useRef(null);
  const dropdownRef = useRef(null);

  // Include all types (including current type itself)
  const availableTypes = useMemo(() => {
    return relationshipTypes.sort((a, b) => {
      const nameA = (a.name || '').toLowerCase();
      const nameB = (b.name || '').toLowerCase();
      if (nameA < nameB) return -1;
      if (nameA > nameB) return 1;
      return 0;
    });
  }, [relationshipTypes]);

  // Filter by search term
  const filteredTypes = useMemo(() => {
    if (!searchTerm) return availableTypes.slice(0, 10);

    const term = searchTerm.toLowerCase();
    return availableTypes.filter(t => {
      const name = (t.name || '').toLowerCase();
      return name.includes(term);
    }).slice(0, 10);
  }, [availableTypes, searchTerm]);

  const selectedType = useMemo(() => {
    return availableTypes.find(t => t.id === parseInt(value, 10));
  }, [availableTypes, value]);

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

  const handleSelect = (typeId) => {
    onChange(typeId ? typeId.toString() : '');
    setSearchTerm('');
    setIsOpen(false);
  };

  const handleClear = () => {
    onChange('');
    setSearchTerm('');
    setIsOpen(false);
  };

  const displayValue = selectedType ? selectedType.name : '';

  return (
    <div className="relative">
      {/* Selected type display / Search input */}
      <div className="relative">
        {selectedType && !isOpen ? (
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
            placeholder="Zoek een relatietype..."
            className="input"
          />
        )}
      </div>

      {/* Dropdown */}
      {isOpen && (searchTerm || !selectedType) && (
        <div
          ref={dropdownRef}
          className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-48 overflow-y-auto"
        >
          {filteredTypes.length > 0 ? (
            <>
              <button
                type="button"
                onClick={() => handleSelect(null)}
                className={`w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 ${
                  !value ? 'bg-cyan-50 dark:bg-deep-midnight' : ''
                }`}
              >
                <span className="text-gray-500 dark:text-gray-400 italic">Geen (geen omgekeerd)</span>
              </button>
              {filteredTypes.map(type => (
                <button
                  key={type.id}
                  type="button"
                  onClick={() => handleSelect(type.id)}
                  className={`w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-50 ${
                    value === type.id.toString() ? 'bg-cyan-50 dark:bg-deep-midnight' : ''
                  }`}
                >
                  {type.name}
                </button>
              ))}
            </>
          ) : (
            <p className="px-4 py-2 text-gray-500 dark:text-gray-400 text-sm">Geen relatietypes gevonden</p>
          )}
        </div>
      )}
    </div>
  );
}

export default function RelationshipTypes() {
  useDocumentTitle('Relatietypes - Instellingen');
  const queryClient = useQueryClient();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  // Check if user is admin
  if (!isAdmin) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="card p-8 text-center">
          <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
          <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Toegang geweigerd</h1>
          <p className="text-gray-600 dark:text-gray-300 mb-6">
            Je hebt geen toestemming om relatietypes te beheren. Deze functie is alleen beschikbaar voor beheerders.
          </p>
          <Link to="/settings" className="btn-primary">
            Terug naar Instellingen
          </Link>
        </div>
      </div>
    );
  }

  // Fetch relationship types
  const { data: relationshipTypes = [], isLoading } = useQuery({
    queryKey: ['relationship-types'],
    queryFn: async () => {
      const response = await wpApi.getRelationshipTypes();
      return response.data;
    },
  });

  const [editingId, setEditingId] = useState(null);
  const [editingName, setEditingName] = useState('');
  const [editingInverse, setEditingInverse] = useState('');
  const [newName, setNewName] = useState('');
  const [newInverse, setNewInverse] = useState('');
  const [isAdding, setIsAdding] = useState(false);

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: async ({ id, name, inverseId }) => {
      const data = {
        name: name,
      };

      // Include ACF field if inverse is set
      if (inverseId) {
        data.acf = {
          inverse_relationship_type: parseInt(inverseId, 10),
        };
      }

      return wpApi.updateRelationshipType(id, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relationship-types'] });
      setEditingId(null);
      setEditingName('');
      setEditingInverse('');
    },
  });

  // Create mutation
  const createMutation = useMutation({
    mutationFn: async ({ name, inverseId }) => {
      const data = {
        name: name,
      };

      // Include ACF field if inverse is set
      if (inverseId) {
        data.acf = {
          inverse_relationship_type: parseInt(inverseId, 10),
        };
      }

      return wpApi.createRelationshipType(data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relationship-types'] });
      setNewName('');
      setNewInverse('');
      setIsAdding(false);
    },
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id) => wpApi.deleteRelationshipType(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relationship-types'] });
    },
  });

  // Restore defaults mutation
  const restoreDefaultsMutation = useMutation({
    mutationFn: () => wpApi.restoreRelationshipTypeDefaults(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relationship-types'] });
      alert('Standaard relatietype-configuraties zijn hersteld.');
    },
    onError: () => {
      alert('Kan standaardwaarden niet herstellen. Probeer het opnieuw.');
    },
  });

  // Restore defaults button component
  function RestoreDefaultsButton() {
    return (
      <button
        onClick={() => {
          if (window.confirm('Dit zal alle standaard omgekeerde koppelingen en geslachtsafhankelijke instellingen herstellen. Doorgaan?')) {
            restoreDefaultsMutation.mutate();
          }
        }}
        disabled={restoreDefaultsMutation.isPending}
        className="btn-secondary flex items-center gap-2"
        title="Standaard omgekeerde koppelingen en geslachtsafhankelijke instellingen herstellen"
      >
        {restoreDefaultsMutation.isPending ? (
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600"></div>
        ) : (
          <RotateCcw className="w-4 h-4" />
        )}
        <span className="hidden md:inline">Standaardwaarden herstellen</span>
      </button>
    );
  }

  const handleEdit = (type) => {
    setEditingId(type.id);
    setEditingName(type.name);
    // Handle ACF field - could be ID directly or nested object
    const inverseId = type.acf?.inverse_relationship_type;
    setEditingInverse(
      inverseId
        ? (typeof inverseId === 'object' ? inverseId.toString() : inverseId.toString())
        : ''
    );
  };

  const handleCancelEdit = () => {
    setEditingId(null);
    setEditingName('');
    setEditingInverse('');
  };

  const handleSave = () => {
    if (!editingName.trim()) {
      alert('Naam is verplicht');
      return;
    }

    updateMutation.mutate({
      id: editingId,
      name: editingName.trim(),
      inverseId: editingInverse || null,
    });
  };

  const handleCreate = () => {
    if (!newName.trim()) {
      alert('Naam is verplicht');
      return;
    }

    createMutation.mutate({
      name: newName.trim(),
      inverseId: newInverse || null,
    });
  };

  const handleDelete = (id, name) => {
    if (!window.confirm(`Weet je zeker dat je "${name}" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.`)) {
      return;
    }

    deleteMutation.mutate(id);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="card p-6">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold text-brand-gradient">Relatietypes</h1>
          <div className="flex gap-2">
            {!isAdding && (
              <>
                <RestoreDefaultsButton />
                <button
                  onClick={() => setIsAdding(true)}
                  className="btn-primary flex items-center gap-2"
                >
                  <Plus className="w-4 h-4" />
                  <span className="hidden md:inline">Relatietype toevoegen</span>
                </button>
              </>
            )}
          </div>
        </div>

        {/* Add new form */}
        {isAdding && (
          <div className="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <h3 className="font-semibold dark:text-gray-50 mb-4">Nieuw relatietype toevoegen</h3>
            <div className="space-y-4">
              <div>
                <label className="label">Naam *</label>
                <input
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  className="input"
                  placeholder="bijv. Ouder, Partner, Collega"
                />
              </div>
              <div>
                <label className="label">Omgekeerd relatietype</label>
                <SearchableRelationshipTypeSelector
                  value={newInverse}
                  onChange={setNewInverse}
                  relationshipTypes={relationshipTypes}
                />
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Selecteer het omgekeerde relatietype. Als dit bijvoorbeeld "Ouder" is, selecteer dan "Kind".
                  Als dit "Partner" of "Kennis" is, selecteer dan hetzelfde type (bijv. "Partner" → "Partner").
                </p>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={handleCreate}
                  disabled={createMutation.isPending}
                  className="btn-primary flex items-center gap-2"
                >
                  {createMutation.isPending ? (
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                  ) : (
                    <Save className="w-4 h-4" />
                  )}
                  <span className="hidden md:inline">Opslaan</span>
                </button>
                <button
                  onClick={() => {
                    setIsAdding(false);
                    setNewName('');
                    setNewInverse('');
                  }}
                  className="btn-secondary"
                >
                  Annuleren
                </button>
              </div>
            </div>
          </div>
        )}

        {/* List of relationship types */}
        <div className="space-y-2">
          {relationshipTypes.length === 0 ? (
            <p className="text-gray-500 dark:text-gray-400 text-center py-8">Geen relatietypes gevonden.</p>
          ) : (
            relationshipTypes.map((type) => {
              const isEditing = editingId === type.id;
              // Handle ACF field - could be ID directly or nested object
              const inverseId = type.acf?.inverse_relationship_type;
              const inverseIdValue = typeof inverseId === 'object' && inverseId?.ID
                ? inverseId.ID
                : (typeof inverseId === 'object' && inverseId?.id
                  ? inverseId.id
                  : inverseId);
              const inverseType = inverseIdValue
                ? relationshipTypes.find(t => t.id === inverseIdValue)
                : null;

              return (
                <div
                  key={type.id}
                  className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  {isEditing ? (
                    <div className="space-y-4">
                      <div>
                        <label className="label">Naam *</label>
                        <input
                          type="text"
                          value={editingName}
                          onChange={(e) => setEditingName(e.target.value)}
                          className="input"
                        />
                      </div>
                      <div>
                        <label className="label">Omgekeerd relatietype</label>
                        <SearchableRelationshipTypeSelector
                          value={editingInverse}
                          onChange={setEditingInverse}
                          relationshipTypes={relationshipTypes}
                          currentTypeId={type.id}
                        />
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                          Selecteer het omgekeerde relatietype. Als dit bijvoorbeeld "Ouder" is, selecteer dan "Kind".
                          Als dit "Partner" of "Kennis" is, selecteer dan hetzelfde type (bijv. "Partner" → "Partner").
                        </p>
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={handleSave}
                          disabled={updateMutation.isPending}
                          className="btn-primary flex items-center gap-2"
                        >
                          {updateMutation.isPending ? (
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                          ) : (
                            <Save className="w-4 h-4" />
                          )}
                          <span className="hidden md:inline">Opslaan</span>
                        </button>
                        <button
                          onClick={handleCancelEdit}
                          className="btn-secondary flex items-center gap-2"
                        >
                          <X className="w-4 h-4" />
                          <span className="hidden md:inline">Annuleren</span>
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="font-medium dark:text-gray-50">{type.name}</div>
                        {inverseType && (
                          <div className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Omgekeerd: <span className="font-medium">{inverseType.name}</span>
                          </div>
                        )}
                        {!inverseType && type.acf?.inverse_relationship_type === null && (
                          <div className="text-sm text-gray-400 dark:text-gray-500 mt-1 italic">Geen omgekeerd relatietype</div>
                        )}
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={() => handleEdit(type)}
                          className="p-2 text-gray-600 dark:text-gray-400 hover:text-electric-cyan dark:hover:text-electric-cyan hover:bg-cyan-50 dark:hover:bg-obsidian/30 rounded"
                          title="Bewerken"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(type.id, type.name)}
                          className="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                          title="Verwijderen"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
}
