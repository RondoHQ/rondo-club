import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Database, Plus, Trash2, Edit2, ShieldAlert, GripVertical } from 'lucide-react';
import { prmApi } from '@/api/client';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { Link } from 'react-router-dom';
import FieldFormPanel from '@/components/FieldFormPanel';
import DeleteFieldDialog from '@/components/DeleteFieldDialog';
import TabButton from '@/components/TabButton.jsx';

// Tab configuration
const TABS = [
  { id: 'person', label: 'Ledenvelden' },
  { id: 'team', label: 'Teamvelden' },
  { id: 'commissie', label: 'Commissievelden' },
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

const STORAGE_KEY = 'stadion-custom-fields-tab';

// Sortable field row component for drag-and-drop
function SortableFieldRow({ field, onEdit, onDelete, getFieldTypeLabel }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: field.key });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  return (
    <tr
      ref={setNodeRef}
      style={style}
      className={`group hover:bg-gray-50 dark:hover:bg-gray-800 ${isDragging ? 'shadow-lg opacity-90 bg-white dark:bg-gray-900' : ''}`}
    >
      <td className="px-2 py-4 w-10">
        <button
          {...attributes}
          {...listeners}
          className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-grab active:cursor-grabbing touch-none"
          aria-label="Sleep om te herordenen"
        >
          <GripVertical className="w-4 h-4" />
        </button>
      </td>
      <td className="px-4 py-4 whitespace-nowrap">
        <div className="font-medium text-gray-900 dark:text-gray-100">
          {field.label}
          {field.required && <span className="text-red-500 ml-1">*</span>}
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
            onClick={() => onEdit(field)}
            className="p-2 text-gray-600 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/30 rounded"
            title="Bewerken"
          >
            <Edit2 className="w-4 h-4" />
          </button>
          <button
            onClick={() => onDelete(field)}
            className="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
            title="Verwijderen"
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      </td>
    </tr>
  );
}

export default function CustomFields() {
  useDocumentTitle('Aangepaste velden - Instellingen');
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;
  const queryClient = useQueryClient();

  // Initialize tab from localStorage or default to 'person'
  const [activeTab, setActiveTab] = useState(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    return saved && (saved === 'person' || saved === 'team' || saved === 'commissie') ? saved : 'person';
  });

  // State for panel and dialog
  const [showPanel, setShowPanel] = useState(false);
  const [editingField, setEditingField] = useState(null);
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [deletingField, setDeletingField] = useState(null);

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
          <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Toegang geweigerd</h1>
          <p className="text-gray-600 dark:text-gray-300 mb-6">
            Je hebt geen toestemming om aangepaste velden te beheren. Deze functie is alleen beschikbaar voor beheerders.
          </p>
          <Link to="/settings" className="btn-primary">
            Terug naar Instellingen
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

  // Fetch team fields
  const { data: teamFields = [], isLoading: teamFieldsLoading } = useQuery({
    queryKey: ['custom-fields', 'team'],
    queryFn: async () => {
      const response = await prmApi.getCustomFields('team');
      return response.data;
    },
  });

  // Fetch commissie fields
  const { data: commissieFields = [], isLoading: commissieFieldsLoading } = useQuery({
    queryKey: ['custom-fields', 'commissie'],
    queryFn: async () => {
      const response = await prmApi.getCustomFields('commissie');
      return response.data;
    },
  });

  // Create field mutation
  const createMutation = useMutation({
    mutationFn: async (data) => prmApi.createCustomField(activeTab, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-fields', activeTab] });
      setShowPanel(false);
      setEditingField(null);
    },
  });

  // Update field mutation
  const updateMutation = useMutation({
    mutationFn: async ({ fieldKey, data }) => prmApi.updateCustomField(activeTab, fieldKey, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-fields', activeTab] });
      setShowPanel(false);
      setEditingField(null);
    },
  });

  // Delete field mutation
  const deleteMutation = useMutation({
    mutationFn: async (fieldKey) => prmApi.deleteCustomField(activeTab, fieldKey),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-fields', activeTab] });
      setShowDeleteDialog(false);
      setDeletingField(null);
    },
  });

  // Reorder mutation with optimistic update
  const reorderMutation = useMutation({
    mutationFn: async (order) => prmApi.reorderCustomFields(activeTab, order),
    onMutate: async (order) => {
      await queryClient.cancelQueries({ queryKey: ['custom-fields', activeTab] });
      const previousFields = queryClient.getQueryData(['custom-fields', activeTab]);
      const reorderedFields = order.map(key => previousFields.find(f => f.key === key));
      queryClient.setQueryData(['custom-fields', activeTab], reorderedFields);
      return { previousFields };
    },
    onError: (err, order, context) => {
      queryClient.setQueryData(['custom-fields', activeTab], context.previousFields);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-fields', activeTab] });
    },
  });

  // Drag-and-drop sensors configuration
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    }),
    useSensor(TouchSensor, {
      activationConstraint: { delay: 200, tolerance: 8 },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  // Determine which fields to show based on active tab
  const fields = activeTab === 'person' ? personFields : activeTab === 'team' ? teamFields : commissieFields;
  const isLoading = activeTab === 'person' ? personFieldsLoading : activeTab === 'team' ? teamFieldsLoading : commissieFieldsLoading;

  const handleTabChange = (tabId) => {
    setActiveTab(tabId);
    // Reset state when switching tabs
    setEditingField(null);
    setShowPanel(false);
    setShowDeleteDialog(false);
    setDeletingField(null);
  };

  const handleAddField = () => {
    setEditingField(null);
    setShowPanel(true);
  };

  const handleEditField = (field) => {
    setEditingField(field);
    setShowPanel(true);
  };

  const handleDeleteField = (field) => {
    setDeletingField(field);
    setShowDeleteDialog(true);
  };

  const handleClosePanel = () => {
    setShowPanel(false);
    setEditingField(null);
  };

  const handleCloseDeleteDialog = () => {
    setShowDeleteDialog(false);
    setDeletingField(null);
  };

  const handleSubmitField = async (data) => {
    if (editingField) {
      await updateMutation.mutateAsync({ fieldKey: editingField.key, data });
    } else {
      await createMutation.mutateAsync(data);
    }
  };

  const handleArchiveField = async () => {
    // Archive uses the DELETE endpoint (soft delete/deactivate)
    await deleteMutation.mutateAsync(deletingField.key);
  };

  const handlePermanentDelete = async () => {
    // For now, same as archive - API currently only supports soft delete (deactivate)
    // Phase 94 may add hard delete option
    await deleteMutation.mutateAsync(deletingField.key);
  };

  const getFieldTypeLabel = (type) => {
    return FIELD_TYPE_LABELS[type] || type;
  };

  const handleDragEnd = (event) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const oldIndex = fields.findIndex(f => f.key === active.id);
      const newIndex = fields.findIndex(f => f.key === over.id);
      const newOrder = arrayMove(fields, oldIndex, newIndex).map(f => f.key);
      reorderMutation.mutate(newOrder);
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="card p-6">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Database className="w-6 h-6" />
            Aangepaste velden
          </h1>
          <button
            onClick={handleAddField}
            className="btn-primary flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            <span className="hidden md:inline">Veld toevoegen</span>
          </button>
        </div>

        {/* Tab Navigation */}
        <div className="border-b border-gray-200 dark:border-gray-700 mb-6">
          <nav className="-mb-px flex space-x-8" aria-label="Tabs">
            {TABS.map((tab) => (
              <TabButton
                key={tab.id}
                label={tab.label}
                isActive={activeTab === tab.id}
                onClick={() => handleTabChange(tab.id)}
              />
            ))}
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
                Geen aangepaste velden gedefinieerd. Klik op 'Veld toevoegen' om er een te maken.
              </p>
            ) : (
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
              >
                <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                  <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-800">
                      <tr>
                        <th scope="col" className="px-2 py-3 w-10">
                          <span className="sr-only">Herordenen</span>
                        </th>
                        <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                          Label
                        </th>
                        <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                          Type
                        </th>
                        <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                          <span className="sr-only">Acties</span>
                        </th>
                      </tr>
                    </thead>
                    <SortableContext items={fields.map(f => f.key)} strategy={verticalListSortingStrategy}>
                      <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        {fields.map((field) => (
                          <SortableFieldRow
                            key={field.key}
                            field={field}
                            onEdit={handleEditField}
                            onDelete={handleDeleteField}
                            getFieldTypeLabel={getFieldTypeLabel}
                          />
                        ))}
                      </tbody>
                    </SortableContext>
                  </table>
                </div>
              </DndContext>
            )}
          </div>
        )}
      </div>

      {/* Add/Edit Panel */}
      <FieldFormPanel
        isOpen={showPanel}
        onClose={handleClosePanel}
        onSubmit={handleSubmitField}
        field={editingField}
        postType={activeTab}
        isSubmitting={createMutation.isPending || updateMutation.isPending}
      />

      {/* Delete Dialog */}
      <DeleteFieldDialog
        isOpen={showDeleteDialog}
        onClose={handleCloseDeleteDialog}
        onArchive={handleArchiveField}
        onDelete={handlePermanentDelete}
        field={deletingField}
        isDeleting={deleteMutation.isPending}
        usageCount={0}
      />
    </div>
  );
}
