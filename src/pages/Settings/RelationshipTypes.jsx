import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Save, Plus, Trash2, Edit2, X } from 'lucide-react';
import { wpApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function RelationshipTypes() {
  useDocumentTitle('Relationship Types - Settings');
  const queryClient = useQueryClient();
  
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
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="card p-6">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold">Relationship Types</h1>
          {!isAdding && (
            <button
              onClick={() => setIsAdding(true)}
              className="btn-primary flex items-center gap-2"
            >
              <Plus className="w-4 h-4" />
              Add Relationship Type
            </button>
          )}
        </div>
        
        {/* Add new form */}
        {isAdding && (
          <div className="mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50">
            <h3 className="font-semibold mb-4">Add New Relationship Type</h3>
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
                <select
                  value={newInverse}
                  onChange={(e) => setNewInverse(e.target.value)}
                  className="input"
                >
                  <option value="">None (no inverse)</option>
                  {relationshipTypes.map((type) => (
                    <option key={type.id} value={type.id}>
                      {type.name}
                    </option>
                  ))}
                </select>
                <p className="text-xs text-gray-500 mt-1">
                  Select the inverse relationship type. For example, if this is "Parent", select "Child".
                  If this is "Spouse", select "Spouse" (same type).
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
                  Save
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
            <p className="text-gray-500 text-center py-8">No relationship types found.</p>
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
                  className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
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
                        <select
                          value={editingInverse}
                          onChange={(e) => setEditingInverse(e.target.value)}
                          className="input"
                        >
                          <option value="">None (no inverse)</option>
                          {relationshipTypes
                            .filter(t => t.id !== type.id)
                            .map((t) => (
                              <option key={t.id} value={t.id}>
                                {t.name}
                              </option>
                            ))}
                        </select>
                        <p className="text-xs text-gray-500 mt-1">
                          Select the inverse relationship type. For example, if this is "Parent", select "Child".
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
                          Save
                        </button>
                        <button
                          onClick={handleCancelEdit}
                          className="btn-secondary flex items-center gap-2"
                        >
                          <X className="w-4 h-4" />
                          Cancel
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="font-medium">{type.name}</div>
                        {inverseType && (
                          <div className="text-sm text-gray-500 mt-1">
                            Inverse: <span className="font-medium">{inverseType.name}</span>
                          </div>
                        )}
                        {!inverseType && type.acf?.inverse_relationship_type === null && (
                          <div className="text-sm text-gray-400 mt-1 italic">No inverse relationship</div>
                        )}
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={() => handleEdit(type)}
                          className="p-2 text-gray-600 hover:text-primary-600 hover:bg-primary-50 rounded"
                          title="Edit"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(type.id, type.name)}
                          className="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded"
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

