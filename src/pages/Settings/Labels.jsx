import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Save, Plus, Trash2, Edit2, X, ShieldAlert, Tag } from 'lucide-react';
import { wpApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { Link } from 'react-router-dom';

// Tab configuration
const TABS = [
  { id: 'person', label: 'Ledenlabels' },
  { id: 'team', label: 'Organisatielabels' },
];

export default function Labels() {
  useDocumentTitle('Labels - Settings');
  const queryClient = useQueryClient();
  const config = window.stadionConfig || {};
  const isAdmin = config.isAdmin || false;

  const [activeTab, setActiveTab] = useState('person');
  const [editingId, setEditingId] = useState(null);
  const [editingName, setEditingName] = useState('');
  const [newName, setNewName] = useState('');
  const [isAdding, setIsAdding] = useState(false);

  // Check if user is admin
  if (!isAdmin) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="card p-8 text-center">
          <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
          <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Toegang geweigerd</h1>
          <p className="text-gray-600 dark:text-gray-300 mb-6">
            Je hebt geen toestemming om labels te beheren. Deze functie is alleen beschikbaar voor beheerders.
          </p>
          <Link to="/settings" className="btn-primary">
            Terug naar Instellingen
          </Link>
        </div>
      </div>
    );
  }

  // Fetch person labels
  const { data: personLabels = [], isLoading: personLabelsLoading } = useQuery({
    queryKey: ['person-labels'],
    queryFn: async () => {
      const response = await wpApi.getPersonLabels();
      return response.data;
    },
  });

  // Fetch team labels
  const { data: teamLabels = [], isLoading: teamLabelsLoading } = useQuery({
    queryKey: ['team-labels'],
    queryFn: async () => {
      const response = await wpApi.getTeamLabels();
      return response.data;
    },
  });

  // Determine which labels to show based on active tab
  const labels = activeTab === 'person' ? personLabels : teamLabels;
  const isLoading = activeTab === 'person' ? personLabelsLoading : teamLabelsLoading;
  const queryKey = activeTab === 'person' ? 'person-labels' : 'team-labels';
  const entityName = activeTab === 'person' ? 'leden' : 'organisaties';

  // Create mutation
  const createMutation = useMutation({
    mutationFn: async (name) => {
      const apiMethod = activeTab === 'person'
        ? wpApi.createPersonLabel
        : wpApi.createTeamLabel;
      return apiMethod({ name });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [queryKey] });
      setNewName('');
      setIsAdding(false);
    },
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: async ({ id, name }) => {
      const apiMethod = activeTab === 'person'
        ? wpApi.updatePersonLabel
        : wpApi.updateTeamLabel;
      return apiMethod(id, { name });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [queryKey] });
      setEditingId(null);
      setEditingName('');
    },
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: async (id) => {
      const apiMethod = activeTab === 'person'
        ? wpApi.deletePersonLabel
        : wpApi.deleteTeamLabel;
      return apiMethod(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [queryKey] });
    },
  });

  const handleEdit = (label) => {
    setEditingId(label.id);
    setEditingName(label.name);
  };

  const handleCancelEdit = () => {
    setEditingId(null);
    setEditingName('');
  };

  const handleSave = () => {
    if (!editingName.trim()) {
      alert('Naam is verplicht');
      return;
    }

    updateMutation.mutate({
      id: editingId,
      name: editingName.trim(),
    });
  };

  const handleCreate = () => {
    if (!newName.trim()) {
      alert('Naam is verplicht');
      return;
    }

    createMutation.mutate(newName.trim());
  };

  const handleDelete = (id, name) => {
    if (!window.confirm(`Weet je zeker dat je "${name}" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.`)) {
      return;
    }

    deleteMutation.mutate(id);
  };

  const handleTabChange = (tabId) => {
    setActiveTab(tabId);
    // Reset state when switching tabs
    setEditingId(null);
    setEditingName('');
    setNewName('');
    setIsAdding(false);
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="card p-6">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Tag className="w-6 h-6" />
            Labels
          </h1>
          {!isAdding && (
            <button
              onClick={() => setIsAdding(true)}
              className="btn-primary flex items-center gap-2"
            >
              <Plus className="w-4 h-4" />
              <span className="hidden md:inline">Label toevoegen</span>
            </button>
          )}
        </div>

        {/* Tab Navigation */}
        <div className="border-b border-gray-200 dark:border-gray-700 mb-6">
          <nav className="-mb-px flex space-x-8" aria-label="Tabs">
            {TABS.map((tab) => {
              const isActive = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => handleTabChange(tab.id)}
                  className={`
                    py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap
                    ${isActive
                      ? 'border-accent-500 text-accent-600 dark:text-accent-400'
                      : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'}
                  `}
                >
                  {tab.label}
                </button>
              );
            })}
          </nav>
        </div>

        {/* Add new form */}
        {isAdding && (
          <div className="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <h3 className="font-semibold dark:text-gray-50 mb-4">Nieuw label toevoegen</h3>
            <div className="space-y-4">
              <div>
                <label className="label">Naam *</label>
                <input
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  className="input"
                  placeholder="bijv. Vriend, Familie, VIP, Klant"
                />
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
                  }}
                  className="btn-secondary"
                >
                  Annuleren
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Loading state */}
        {isLoading && (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
          </div>
        )}

        {/* List of labels */}
        {!isLoading && (
          <div className="space-y-2">
            {labels.length === 0 ? (
              <p className="text-gray-500 dark:text-gray-400 text-center py-8">Geen labels gevonden. Maak er een aan om te beginnen.</p>
            ) : (
              labels.map((label) => {
                const isEditing = editingId === label.id;

                return (
                  <div
                    key={label.id}
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
                          <div className="font-medium dark:text-gray-50">{label.name}</div>
                          <div className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {label.count || 0} {entityName}
                          </div>
                        </div>
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleEdit(label)}
                            className="p-2 text-gray-600 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/30 rounded"
                            title="Bewerken"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(label.id, label.name)}
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
        )}
      </div>
    </div>
  );
}
