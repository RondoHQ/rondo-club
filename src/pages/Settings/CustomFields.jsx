import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Database, Plus, Trash2, Edit2, ShieldAlert } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { Link } from 'react-router-dom';

// Tab configuration
const TABS = [
  { id: 'person', label: 'People Fields' },
  { id: 'company', label: 'Organization Fields' },
];

// Field type display labels
const FIELD_TYPE_LABELS = {
  text: 'Text',
  textarea: 'Textarea',
  number: 'Number',
  email: 'Email',
  url: 'URL',
  date: 'Date',
  select: 'Select',
  checkbox: 'Checkbox',
  true_false: 'True/False',
  image: 'Image',
  file: 'File',
  link: 'Link',
  color: 'Color',
  relationship: 'Relationship',
};

const STORAGE_KEY = 'caelis-custom-fields-tab';

export default function CustomFields() {
  useDocumentTitle('Custom Fields - Settings');
  const config = window.prmConfig || {};
  const isAdmin = config.isAdmin || false;

  // Initialize tab from localStorage or default to 'person'
  const [activeTab, setActiveTab] = useState(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    return saved && (saved === 'person' || saved === 'company') ? saved : 'person';
  });

  // State for edit/delete actions (will be wired to panel in Plan 02)
  const [editingField, setEditingField] = useState(null);
  const [showDeleteDialog, setShowDeleteDialog] = useState(null);
  const [showAddPanel, setShowAddPanel] = useState(false);

  // Persist tab selection
  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, activeTab);
  }, [activeTab]);

  // Check if user is admin
  if (!isAdmin) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="card p-8 text-center">
          <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
          <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Access Denied</h1>
          <p className="text-gray-600 dark:text-gray-300 mb-6">
            You don't have permission to manage custom fields. This feature is only available to administrators.
          </p>
          <Link to="/settings" className="btn-primary">
            Back to Settings
          </Link>
        </div>
      </div>
    );
  }

  // Fetch person fields
  const { data: personFields = [], isLoading: personFieldsLoading } = useQuery({
    queryKey: ['custom-fields', 'person'],
    queryFn: async () => {
      const response = await prmApi.getCustomFields('person');
      return response.data;
    },
  });

  // Fetch company fields
  const { data: companyFields = [], isLoading: companyFieldsLoading } = useQuery({
    queryKey: ['custom-fields', 'company'],
    queryFn: async () => {
      const response = await prmApi.getCustomFields('company');
      return response.data;
    },
  });

  // Determine which fields to show based on active tab
  const fields = activeTab === 'person' ? personFields : companyFields;
  const isLoading = activeTab === 'person' ? personFieldsLoading : companyFieldsLoading;

  const handleTabChange = (tabId) => {
    setActiveTab(tabId);
    // Reset state when switching tabs
    setEditingField(null);
    setShowDeleteDialog(null);
  };

  const handleEdit = (field) => {
    setEditingField(field);
    // Panel will be implemented in Plan 02
  };

  const handleDelete = (field) => {
    setShowDeleteDialog(field);
    // Dialog will be implemented in Plan 02
  };

  const handleAdd = () => {
    setShowAddPanel(true);
    // Panel will be implemented in Plan 02
  };

  const getFieldTypeLabel = (type) => {
    return FIELD_TYPE_LABELS[type] || type;
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="card p-6">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Database className="w-6 h-6" />
            Custom Fields
          </h1>
          <button
            onClick={handleAdd}
            className="btn-primary flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            <span className="hidden md:inline">Add Field</span>
          </button>
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

        {/* Loading state */}
        {isLoading && (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
          </div>
        )}

        {/* Field list */}
        {!isLoading && (
          <div className="space-y-2">
            {fields.length === 0 ? (
              <p className="text-gray-500 dark:text-gray-400 text-center py-8">
                No custom fields defined. Click 'Add Field' to create one.
              </p>
            ) : (
              <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                  <thead className="bg-gray-50 dark:bg-gray-800">
                    <tr>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Label
                      </th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Type
                      </th>
                      <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <span className="sr-only">Actions</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    {fields.map((field) => (
                      <tr key={field.key} className="group hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td className="px-4 py-4 whitespace-nowrap">
                          <div className="font-medium text-gray-900 dark:text-gray-100">
                            {field.label}
                          </div>
                          {field.instructions && (
                            <div className="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                              {field.instructions}
                            </div>
                          )}
                        </td>
                        <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                          {getFieldTypeLabel(field.type)}
                        </td>
                        <td className="px-4 py-4 whitespace-nowrap text-right">
                          <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                              onClick={() => handleEdit(field)}
                              className="p-2 text-gray-600 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/30 rounded"
                              title="Edit"
                            >
                              <Edit2 className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleDelete(field)}
                              className="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                              title="Delete"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
