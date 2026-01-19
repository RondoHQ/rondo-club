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
            placeholder="Search for a relationship type..."
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
                  !value ? 'bg-accent-50 dark:bg-accent-800' : ''
                }`}
              >
                <span className="text-gray-500 dark:text-gray-400 italic">None (no inverse)</span>
              </button>
              {filteredTypes.map(type => (
                <button
                  key={type.id}
                  type="button"
                  onClick={() => handleSelect(type.id)}
                  className={`w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-50 ${
                    value === type.id.toString() ? 'bg-accent-50 dark:bg-accent-800' : ''
                  }`}
                >
                  {type.name}
                </button>
              ))}
            </>
          ) : (
            <p className="px-4 py-2 text-gray-500 dark:text-gray-400 text-sm">No relationship types found</p>
          )}
        </div>
      )}
    </div>
  );
}

export default function RelationshipTypes() {
  useDocumentTitle('Relationship Types - Settings');
  const queryClient = useQueryClient();
  const config = window.prmConfig || {};
  const isAdmin = config.isAdmin || false;
  
  // Check if user is admin
  if (!isAdmin) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="card p-8 text-center">
          <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
          <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Access Denied</h1>
          <p className="text-gray-600 dark:text-gray-300 mb-6">
            You don't have permission to manage relationship types. This feature is only available to administrators.
          </p>
          <Link to="/settings" className="btn-primary">
            Back to Settings
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
      alert('Default relationship type configurations have been restored.');
    },
    onError: () => {
      alert('Failed to restore defaults. Please try again.');
    },
  });
  
  // Restore defaults button component
  function RestoreDefaultsButton() {
    return (
      <button
        onClick={() => {
          if (window.confirm('This will restore all default inverse mappings and gender-dependent settings. Continue?')) {
            restoreDefaultsMutation.mutate();
          }
        }}
        disabled={restoreDefaultsMutation.isPending}
        className="btn-secondary flex items-center gap-2"
        title="Restore default inverse mappings and gender-dependent settings"
      >
        {restoreDefaultsMutation.isPending ? (
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600"></div>
        ) : (
          <RotateCcw className="w-4 h-4" />
        )}
        <span className="hidden md:inline">Restore Defaults</span>
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
      alert('Name is required');
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
      alert('Name is required');
      return;
    }
    
    createMutation.mutate({
      name: newName.trim(),
      inverseId: newInverse || null,
    });
  };
  
  const handleDelete = (id, name) => {
    if (!window.confirm(`Are you sure you want to delete "${name}"? This cannot be undone.`)) {
      return;
    }
    
    deleteMutation.mutate(id);
  };
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
      </div>
    );
  }
  
  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="card p-6">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold dark:text-gray-50">Relationship Types</h1>
          <div className="flex gap-2">
            {!isAdding && (
              <>
                <RestoreDefaultsButton />
                <button
                  onClick={() => setIsAdding(true)}
                  className="btn-primary flex items-center gap-2"
                >
                  <Plus className="w-4 h-4" />
                  <span className="hidden md:inline">Add Relationship Type</span>
                </button>
              </>
            )}
          </div>
        </div>

        {/* Add new form */}
        {isAdding && (
          <div className="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <h3 className="font-semibold dark:text-gray-50 mb-4">Add New Relationship Type</h3>
            <div className="space-y-4">
              <div>
                <label className="label">Name *</label>
                <input
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  className="input"
                  placeholder="e.g., Parent, Spouse, Colleague"
                />
              </div>
              <div>
                <label className="label">Inverse Relationship Type</label>
                <SearchableRelationshipTypeSelector
                  value={newInverse}
                  onChange={setNewInverse}
                  relationshipTypes={relationshipTypes}
                />
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Select the inverse relationship type. For example, if this is "Parent", select "Child".
                  If this is "Spouse" or "Acquaintance", select the same type (e.g., "Spouse" → "Spouse").
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
                  <span className="hidden md:inline">Save</span>
                </button>
                <button
                  onClick={() => {
                    setIsAdding(false);
                    setNewName('');
                    setNewInverse('');
                  }}
                  className="btn-secondary"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>
        )}
        
        {/* List of relationship types */}
        <div className="space-y-2">
          {relationshipTypes.length === 0 ? (
            <p className="text-gray-500 dark:text-gray-400 text-center py-8">No relationship types found.</p>
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
                        <label className="label">Name *</label>
                        <input
                          type="text"
                          value={editingName}
                          onChange={(e) => setEditingName(e.target.value)}
                          className="input"
                        />
                      </div>
                      <div>
                        <label className="label">Inverse Relationship Type</label>
                        <SearchableRelationshipTypeSelector
                          value={editingInverse}
                          onChange={setEditingInverse}
                          relationshipTypes={relationshipTypes}
                          currentTypeId={type.id}
                        />
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                          Select the inverse relationship type. For example, if this is "Parent", select "Child".
                          If this is "Spouse" or "Acquaintance", select the same type (e.g., "Spouse" → "Spouse").
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
                          <span className="hidden md:inline">Save</span>
                        </button>
                        <button
                          onClick={handleCancelEdit}
                          className="btn-secondary flex items-center gap-2"
                        >
                          <X className="w-4 h-4" />
                          <span className="hidden md:inline">Cancel</span>
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="font-medium dark:text-gray-50">{type.name}</div>
                        {inverseType && (
                          <div className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Inverse: <span className="font-medium">{inverseType.name}</span>
                          </div>
                        )}
                        {!inverseType && type.acf?.inverse_relationship_type === null && (
                          <div className="text-sm text-gray-400 dark:text-gray-500 mt-1 italic">No inverse relationship</div>
                        )}
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={() => handleEdit(type)}
                          className="p-2 text-gray-600 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/30 rounded"
                          title="Edit"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(type.id, type.name)}
                          className="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                          title="Delete"
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

