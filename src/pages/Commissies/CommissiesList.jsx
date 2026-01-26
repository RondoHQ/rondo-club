import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, Building2, Filter, X, CheckSquare, Square, MinusSquare, ArrowUp, ArrowDown, ChevronDown, Tag, Check, Pencil } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useCreateCommissie, useBulkUpdateCommissies } from '@/hooks/useCommissies';
import { wpApi, prmApi } from '@/api/client';
import { getCommissieName } from '@/utils/formatters';
import CommissieEditModal from '@/components/CommissieEditModal';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import InlineFieldInput from '@/components/InlineFieldInput';

// Bulk Labels Modal Component for Organizations
function BulkLabelsModal({ isOpen, onClose, selectedCount, labels, onSubmit, isLoading }) {
  const [mode, setMode] = useState('add'); // 'add' or 'remove'
  const [selectedLabelIds, setSelectedLabelIds] = useState([]);

  // Reset when modal opens
  useEffect(() => {
    if (isOpen) {
      setMode('add');
      setSelectedLabelIds([]);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleLabelToggle = (labelId) => {
    setSelectedLabelIds(prev =>
      prev.includes(labelId)
        ? prev.filter(id => id !== labelId)
        : [...prev, labelId]
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Labels beheren</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" disabled={isLoading}>
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Labels {mode === 'add' ? 'toevoegen aan' : 'verwijderen van'} {selectedCount} {selectedCount === 1 ? 'commissie' : 'commissies'}:
          </p>

          {/* Mode toggle */}
          <div className="flex rounded-lg border border-gray-200 dark:border-gray-600 p-1">
            <button
              type="button"
              onClick={() => { setMode('add'); setSelectedLabelIds([]); }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'add' ? 'bg-accent-100 dark:bg-accent-900/50 text-accent-700 dark:text-accent-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Labels toevoegen
            </button>
            <button
              type="button"
              onClick={() => { setMode('remove'); setSelectedLabelIds([]); }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'remove' ? 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Labels verwijderen
            </button>
          </div>

          {/* Label list */}
          {(!labels || labels.length === 0) ? (
            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
              <Tag className="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
              <p className="text-sm">Geen labels beschikbaar.</p>
              <p className="text-xs">Maak eerst labels aan om deze functie te gebruiken.</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {labels.map((label) => {
                const isChecked = selectedLabelIds.includes(label.id);
                return (
                  <button
                    key={label.id}
                    type="button"
                    onClick={() => handleLabelToggle(label.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isChecked
                        ? mode === 'add' ? 'border-accent-500 bg-accent-50 dark:bg-accent-800' : 'border-red-500 bg-red-50 dark:bg-red-900/30'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div className={`flex items-center justify-center w-5 h-5 border-2 rounded ${
                      isChecked
                        ? mode === 'add' ? 'bg-accent-600 border-accent-600' : 'bg-red-600 border-red-600'
                        : 'border-gray-300 dark:border-gray-500'
                    }`}>
                      {isChecked && <Check className="w-3 h-3 text-white" />}
                    </div>
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-50">{label.name}</span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button type="button" onClick={onClose} className="btn-secondary" disabled={isLoading}>
            Annuleren
          </button>
          <button
            type="button"
            onClick={() => onSubmit(mode, selectedLabelIds)}
            className={mode === 'add' ? 'btn-primary' : 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50'}
            disabled={isLoading || selectedLabelIds.length === 0}
          >
            {isLoading
              ? (mode === 'add' ? 'Toevoegen...' : 'Verwijderen...')
              : `${selectedLabelIds.length} ${selectedLabelIds.length === 1 ? 'label' : 'labels'} ${mode === 'add' ? 'toevoegen' : 'verwijderen'}`
            }
          </button>
        </div>
      </div>
    </div>
  );
}

function OrganizationListRow({ commissie, listViewFields, isSelected, onToggleSelection, isOdd, onSaveRow, isUpdating, isEditing, onStartEdit, onCancelEdit }) {
  // Local state for edited field values (includes name, website, and custom fields)
  const [editedFields, setEditedFields] = useState({});

  // Reset edited fields when entering/exiting edit mode
  useEffect(() => {
    if (isEditing) {
      // Initialize with current values for core fields and custom fields
      const initialValues = {
        _name: commissie.title?.rendered || commissie.title || '',
        _website: commissie.acf?.website || '',
      };
      listViewFields.forEach(field => {
        initialValues[field.name] = commissie.acf?.[field.name] ?? '';
      });
      setEditedFields(initialValues);
    } else {
      setEditedFields({});
    }
  }, [isEditing, commissie.acf, commissie.title, listViewFields]);

  const handleFieldChange = (fieldName, value) => {
    setEditedFields(prev => ({ ...prev, [fieldName]: value }));
  };

  const handleSave = () => {
    onSaveRow(commissie.id, editedFields, commissie.acf);
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
      onCancelEdit();
    }
    // Save on Enter (but not in textareas or selects where Enter might have other meaning)
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
      e.preventDefault();
      handleSave();
    }
  };

  return (
    <tr
      className={`group hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'} ${isEditing ? 'ring-2 ring-accent-500 ring-inset' : ''}`}
      onKeyDown={isEditing ? handleKeyDown : undefined}
    >
      <td className="pl-4 pr-2 py-3 w-10">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(commissie.id); }}
          className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      <td className="w-10 px-2 py-3">
        <Link to={`/commissies/${commissie.id}`} className="flex items-center justify-center">
          {commissie._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
            <img
              src={commissie._embedded['wp:featuredmedia'][0].source_url}
              alt=""
              className="w-8 h-8 rounded-lg"
            />
          ) : (
            <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-lg flex items-center justify-center">
              <Building2 className="w-4 h-4 text-gray-400 dark:text-gray-500" />
            </div>
          )}
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap" onDoubleClick={() => !isEditing && onStartEdit(commissie.id)}>
        {isEditing ? (
          <input
            type="text"
            value={editedFields._name ?? ''}
            onChange={(e) => handleFieldChange('_name', e.target.value)}
            onKeyDown={handleKeyDown}
            className="w-full px-2 py-1 text-sm font-medium border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
            disabled={isUpdating}
            autoFocus
          />
        ) : (
          <span className="text-sm font-medium text-gray-900 dark:text-gray-50 cursor-pointer">
            {getCommissieName(commissie)}
          </span>
        )}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 max-w-48">
        {isEditing ? (
          <input
            type="url"
            value={editedFields._website ?? ''}
            onChange={(e) => handleFieldChange('_website', e.target.value)}
            onKeyDown={handleKeyDown}
            className="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
            placeholder="https://..."
            disabled={isUpdating}
          />
        ) : commissie.acf?.website ? (
          <a
            href={commissie.acf.website.startsWith('http') ? commissie.acf.website : `https://${commissie.acf.website}`}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent-600 dark:text-accent-400 hover:underline truncate block"
          >
            {commissie.acf.website}
          </a>
        ) : '-'}
      </td>
      {listViewFields.map(field => (
        <td key={field.key} className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" onDoubleClick={() => !isEditing && onStartEdit(commissie.id)}>
          {isEditing ? (
            <InlineFieldInput
              field={field}
              value={editedFields[field.name]}
              onChange={handleFieldChange}
              onKeyDown={handleKeyDown}
              disabled={isUpdating}
            />
          ) : (
            <span className="cursor-pointer">
              <CustomFieldColumn field={field} value={commissie.acf?.[field.name]} />
            </span>
          )}
        </td>
      ))}
      {/* Actions column */}
      <td className="px-2 py-3 whitespace-nowrap text-sm">
        {isEditing ? (
          <div className="flex items-center gap-1">
            <button
              onClick={handleSave}
              disabled={isUpdating}
              className="p-1.5 text-green-600 hover:text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:text-green-300 dark:hover:bg-green-900/20 rounded"
              title="Save (Enter)"
            >
              {isUpdating ? (
                <div className="w-4 h-4 border-2 border-green-600 border-t-transparent rounded-full animate-spin" />
              ) : (
                <Check className="w-4 h-4" />
              )}
            </button>
            <button
              onClick={onCancelEdit}
              disabled={isUpdating}
              className="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-gray-700 rounded"
              title="Cancel (Esc)"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        ) : (
          <button
            onClick={() => onStartEdit(commissie.id)}
            className="p-1.5 text-gray-400 hover:text-accent-600 hover:bg-accent-50 dark:hover:text-accent-400 dark:hover:bg-accent-900/20 rounded opacity-0 group-hover:opacity-100 transition-opacity"
            title="Edit row"
          >
            <Pencil className="w-4 h-4" />
          </button>
        )}
      </td>
    </tr>
  );
}

// Sortable header component for clickable column headers
function SortableHeader({ field, label, currentSortField, currentSortOrder, onSort }) {
  const isActive = currentSortField === field;

  return (
    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
      >
        {label}
        {isActive && (
          currentSortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </button>
    </th>
  );
}

function OrganizationListView({ commissies, listViewFields, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected, sortField, sortOrder, onSort, onSaveRow, isUpdating, editingRowId, onStartEdit, onCancelEdit }) {
  return (
    <div className="card overflow-x-auto max-h-[calc(100vh-12rem)] overflow-y-auto">
      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
          <tr className="shadow-sm">
            <th scope="col" className="pl-4 pr-2 py-3 w-10 bg-gray-50 dark:bg-gray-800">
              <button
                onClick={onToggleSelectAll}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                title={isAllSelected ? 'Deselect all' : 'Select all'}
              >
                {isAllSelected ? (
                  <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
                ) : isSomeSelected ? (
                  <MinusSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
                ) : (
                  <Square className="w-5 h-5" />
                )}
              </button>
            </th>
            <th scope="col" className="w-10 px-2 bg-gray-50 dark:bg-gray-800"></th>
            <SortableHeader field="name" label="Naam" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="website" label="Website" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            {listViewFields.map(field => (
              <SortableHeader
                key={field.key}
                field={`custom_${field.name}`}
                label={field.label}
                currentSortField={sortField}
                currentSortOrder={sortOrder}
                onSort={onSort}
              />
            ))}
            {/* Actions column header */}
            <th scope="col" className="w-20 px-2 bg-gray-50 dark:bg-gray-800"></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
          {commissies.map((commissie, index) => (
            <OrganizationListRow
              key={commissie.id}
              commissie={commissie}
              listViewFields={listViewFields}
              isSelected={selectedIds.has(commissie.id)}
              onToggleSelection={onToggleSelection}
              isOdd={index % 2 === 1}
              onSaveRow={onSaveRow}
              isUpdating={isUpdating && editingRowId === commissie.id}
              isEditing={editingRowId === commissie.id}
              onStartEdit={onStartEdit}
              onCancelEdit={onCancelEdit}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function CommissiesList() {
  const [search, setSearch] = useState('');
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [ownershipFilter, setOwnershipFilter] = useState('all'); // 'all', 'mine', 'shared'
  const [showCommissieModal, setShowCommissieModal] = useState(false);
  const [isCreatingCommissie, setIsCreatingCommissie] = useState(false);
  const [sortField, setSortField] = useState('name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkLabelsModal, setShowBulkLabelsModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const [editingRowId, setEditingRowId] = useState(null);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const bulkDropdownRef = useRef(null);
  const navigate = useNavigate();

  const bulkUpdateMutation = useBulkUpdateCommissies();
  const queryClient = useQueryClient();

  // Mutation for updating row custom fields
  // ACF fields that require array type (repeaters, multi-select post_object, etc.)
  // These cannot be null - must be empty array [] when empty
  const arrayTypeAcfFields = ['contact_info', 'investors'];

  const updateRowMutation = useMutation({
    mutationFn: async ({ commissieId, editedFields, existingAcf }) => {
      // Extract core fields (prefixed with _) from custom fields
      const { _name, _website, ...customFields } = editedFields;

      // Merge custom fields with existing ACF data
      const mergedAcf = {
        ...existingAcf,
        ...customFields
      };

      // Update website in ACF if changed
      if (_website !== undefined) {
        mergedAcf.website = _website;
      }

      // Sanitize null values for array-type fields (REST API requires [] not null)
      arrayTypeAcfFields.forEach(fieldName => {
        if (mergedAcf[fieldName] === null || mergedAcf[fieldName] === undefined) {
          mergedAcf[fieldName] = [];
        }
      });

      // Build update payload
      const updatePayload = {
        acf: mergedAcf
      };

      // Update title if name changed
      if (_name !== undefined) {
        updatePayload.title = _name;
      }

      const response = await wpApi.updateCommissie(commissieId, updatePayload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['commissies'] });
      setEditingRowId(null);
    },
  });

  // Handler for saving all edited fields in a row
  const handleSaveRow = async (commissieId, editedFields, existingAcf) => {
    // Convert empty strings to null for number fields (REST API requires number or null)
    const processedFields = { ...editedFields };
    listViewFields.forEach(field => {
      if (field.type === 'number' && processedFields[field.name] === '') {
        processedFields[field.name] = null;
      }
    });
    await updateRowMutation.mutateAsync({ commissieId, editedFields: processedFields, existingAcf });
  };

  // Row edit mode handlers
  const handleStartEdit = (commissieId) => {
    setEditingRowId(commissieId);
  };

  const handleCancelEdit = () => {
    setEditingRowId(null);
  };

  // Get current user ID from stadionConfig
  const currentUserId = window.stadionConfig?.userId;

  const { data: commissies, isLoading, error } = useQuery({
    queryKey: ['commissies', search],
    queryFn: async () => {
      const response = await wpApi.getCommissies({ search, per_page: 100, _embed: true });
      return response.data;
    },
  });

  // Fetch commissie labels
  const { data: commissieLabelsData } = useQuery({
    queryKey: ['commissie-labels'],
    queryFn: async () => {
      const response = await wpApi.getCommissieLabels();
      return response.data;
    },
  });
  const commissieLabels = commissieLabelsData || [];

  // Fetch custom field definitions for list view columns
  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'commissie'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('commissie');
      return response.data;
    },
  });

  // Filter to list-view-enabled fields, sorted by order
  const listViewFields = useMemo(() => {
    return customFields
      .filter(f => f.show_in_list_view)
      .sort((a, b) => (a.list_view_order || 999) - (b.list_view_order || 999));
  }, [customFields]);

  // Create commissie mutation
  const createCommissieMutation = useCreateCommissie({
    onSuccess: (result) => {
      setShowCommissieModal(false);
      navigate(`/commissies/${result.id}`);
    },
  });

  const handleCreateCommissie = async (data) => {
    setIsCreatingCommissie(true);
    try {
      await createCommissieMutation.mutateAsync(data);
    } finally {
      setIsCreatingCommissie(false);
    }
  };

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        filterRef.current &&
        !filterRef.current.contains(event.target)
      ) {
        setIsFilterOpen(false);
      }
      // Also close bulk dropdown when clicking outside
      if (
        bulkDropdownRef.current &&
        !bulkDropdownRef.current.contains(event.target)
      ) {
        setShowBulkDropdown(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const hasActiveFilters = ownershipFilter !== 'all';

  const clearFilters = () => {
    setOwnershipFilter('all');
  };

  // Selection helper functions
  const toggleSelection = (commissieId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(commissieId)) {
        next.delete(commissieId);
      } else {
        next.add(commissieId);
      }
      return next;
    });
  };

  const clearSelection = () => setSelectedIds(new Set());

  // Filter commissies
  const filteredCommissies = useMemo(() => {
    if (!commissies) return [];

    let filtered = [...commissies];

    // Apply ownership filter
    if (ownershipFilter === 'mine') {
      filtered = filtered.filter(commissie => commissie.author === currentUserId);
    } else if (ownershipFilter === 'shared') {
      filtered = filtered.filter(commissie => commissie.author !== currentUserId);
    }

    return filtered;
  }, [commissies, ownershipFilter, currentUserId]);

  // Sort filtered commissies
  const sortedCommissies = useMemo(() => {
    if (!filteredCommissies) return [];

    return [...filteredCommissies].sort((a, b) => {
      let valueA, valueB;

      if (sortField === 'name') {
        valueA = (a.title?.rendered || a.title || '').toLowerCase();
        valueB = (b.title?.rendered || b.title || '').toLowerCase();
      } else if (sortField === 'website') {
        valueA = (a.acf?.website || '').toLowerCase();
        valueB = (b.acf?.website || '').toLowerCase();
      } else if (sortField.startsWith('custom_')) {
        // Handle custom field sorting
        const fieldName = sortField.replace('custom_', '');
        const fieldMeta = listViewFields.find(f => f.name === fieldName);
        valueA = a.acf?.[fieldName];
        valueB = b.acf?.[fieldName];

        // Handle different field types
        if (fieldMeta?.type === 'number') {
          valueA = parseFloat(valueA) || 0;
          valueB = parseFloat(valueB) || 0;
          return sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
        }

        if (fieldMeta?.type === 'date') {
          valueA = valueA ? new Date(valueA).getTime() : 0;
          valueB = valueB ? new Date(valueB).getTime() : 0;
          return sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
        }

        // Text comparison for other types
        valueA = String(valueA || '').toLowerCase();
        valueB = String(valueB || '').toLowerCase();
      } else {
        valueA = (a.title?.rendered || a.title || '').toLowerCase();
        valueB = (b.title?.rendered || b.title || '').toLowerCase();
      }

      // Empty values sort last
      if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
      if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
      if (!valueA && !valueB) return 0;

      const comparison = valueA.localeCompare(valueB);
      return sortOrder === 'asc' ? comparison : -comparison;
    });
  }, [filteredCommissies, sortField, sortOrder, listViewFields]);

  // Computed selection state
  const isAllSelected = sortedCommissies.length > 0 &&
    selectedIds.size === sortedCommissies.length;
  const isSomeSelected = selectedIds.size > 0 &&
    selectedIds.size < sortedCommissies.length;

  const toggleSelectAll = () => {
    if (selectedIds.size === sortedCommissies.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(sortedCommissies.map(c => c.id)));
    }
  };

  // Clear selection when filters change
  useEffect(() => {
    setSelectedIds(new Set());
  }, [ownershipFilter, commissies]);
  
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative flex-1 min-w-48 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
              type="search"
              placeholder="Commissies zoeken..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input pl-9"
            />
          </div>

          {/* Sort Controls */}
          <div className="flex items-center gap-2 border border-gray-200 dark:border-gray-600 rounded-lg p-1">
            <select
              value={sortField}
              onChange={(e) => setSortField(e.target.value)}
              className="text-sm border-0 bg-transparent dark:text-gray-200 focus:ring-0 focus:outline-none cursor-pointer"
            >
              <option value="name">Naam</option>
              <option value="website">Website</option>
            </select>
            <div className="h-4 w-px bg-gray-300 dark:bg-gray-600"></div>
            <button
              onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
              title={`Sort ${sortOrder === 'asc' ? 'descending' : 'ascending'}`}
            >
              {sortOrder === 'asc' ? (
                <ArrowUp className="w-4 h-4 text-gray-600 dark:text-gray-300" />
              ) : (
                <ArrowDown className="w-4 h-4 text-gray-600 dark:text-gray-300" />
              )}
            </button>
          </div>

          <div className="relative" ref={filterRef}>
            <button
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${hasActiveFilters ? 'bg-accent-50 text-accent-700 border-accent-200' : ''}`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-accent-600 text-white text-xs rounded-full">
                  {ownershipFilter !== 'all' ? 1 : 0}
                </span>
              )}
            </button>

            {/* Filter Dropdown */}
            {isFilterOpen && (
              <div
                ref={dropdownRef}
                className="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50"
              >
                <div className="p-4 space-y-4">
                  {/* Ownership Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Eigenaar
                    </h3>
                    <div className="space-y-1">
                      {[
                        { value: 'all', label: 'Alle commissies' },
                        { value: 'mine', label: 'Mijn commissies' },
                        { value: 'shared', label: 'Gedeeld met mij' },
                      ].map(option => (
                        <label
                          key={option.value}
                          className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded"
                        >
                          <input
                            type="radio"
                            name="ownership"
                            value={option.value}
                            checked={ownershipFilter === option.value}
                            onChange={(e) => setOwnershipFilter(e.target.value)}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-4 h-4 border-2 rounded-full mr-3 ${
                            ownershipFilter === option.value
                              ? 'border-accent-600'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {ownershipFilter === option.value && (
                              <div className="w-2 h-2 bg-accent-600 rounded-full" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">{option.label}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Clear Filters */}
                  {hasActiveFilters && (
                    <button
                      onClick={clearFilters}
                      className="w-full text-sm text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 font-medium pt-2 border-t border-gray-200 dark:border-gray-700"
                    >
                      Alle filters wissen
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Active Filter Chips */}
          {hasActiveFilters && (
            <div className="flex flex-wrap gap-2">
              {ownershipFilter !== 'all' && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-accent-100 dark:bg-accent-900/50 text-accent-800 dark:text-accent-200 rounded-full text-xs">
                  {ownershipFilter === 'mine' ? 'Mijn commissies' : 'Gedeeld met mij'}
                  <button onClick={() => setOwnershipFilter('all')} className="hover:text-accent-600 dark:hover:text-accent-300">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
            </div>
          )}

          <button onClick={() => setShowCommissieModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Nieuwe commissie</span>
          </button>
        </div>
      </div>
      
      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
        </div>
      )}
      
      {/* Error */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600 dark:text-red-400">Commissies konden niet worden geladen.</p>
        </div>
      )}
      
      {/* Empty - no organizations at all */}
      {!isLoading && !error && commissies?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Building2 className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen commissies gevonden</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            {search ? 'Probeer een andere zoekopdracht.' : 'Voeg je eerste commissie toe.'}
          </p>
          {!search && (
            <button onClick={() => setShowCommissieModal(true)} className="btn-primary">
              <Plus className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Nieuwe commissie</span>
            </button>
          )}
        </div>
      )}

      {/* No results with filters */}
      {!isLoading && !error && commissies?.length > 0 && sortedCommissies?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Filter className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen commissies voldoen aan je filters</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Pas je filters aan om meer resultaten te zien.
          </p>
          <button onClick={clearFilters} className="btn-secondary">
            Filters wissen
          </button>
        </div>
      )}
      
      {/* Selection toolbar - sticky */}
      {selectedIds.size > 0 && (
        <div className="sticky top-0 z-20 flex items-center justify-between bg-accent-50 border border-accent-200 rounded-lg px-4 py-2 shadow-sm">
          <span className="text-sm text-accent-800 font-medium">
            {selectedIds.size} {selectedIds.size === 1 ? 'commissie' : 'commissies'} geselecteerd
          </span>
          <div className="flex items-center gap-3">
            {/* Bulk Actions Dropdown */}
            <div className="relative" ref={bulkDropdownRef}>
              <button
                onClick={() => setShowBulkDropdown(!showBulkDropdown)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-accent-700 bg-white border border-accent-300 rounded-md hover:bg-accent-50"
              >
                Acties
                <ChevronDown className={`w-4 h-4 transition-transform ${showBulkDropdown ? 'rotate-180' : ''}`} />
              </button>
              {showBulkDropdown && (
                <div className="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
                  <div className="py-1">
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkLabelsModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50"
                    >
                      <Tag className="w-4 h-4" />
                      Labels beheren...
                    </button>
                  </div>
                </div>
              )}
            </div>
            <button
              onClick={clearSelection}
              className="text-sm text-accent-600 hover:text-accent-800 font-medium"
            >
              Selectie wissen
            </button>
          </div>
        </div>
      )}

      {/* Organizations list */}
      {!isLoading && !error && sortedCommissies?.length > 0 && (
        <OrganizationListView
          commissies={sortedCommissies}
          listViewFields={listViewFields}
          selectedIds={selectedIds}
          onToggleSelection={toggleSelection}
          onToggleSelectAll={toggleSelectAll}
          isAllSelected={isAllSelected}
          isSomeSelected={isSomeSelected}
          sortField={sortField}
          sortOrder={sortOrder}
          onSort={(field) => {
            if (field === sortField) {
              setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
            } else {
              setSortField(field);
              setSortOrder('asc');
            }
          }}
          onSaveRow={handleSaveRow}
          isUpdating={updateRowMutation.isPending}
          editingRowId={editingRowId}
          onStartEdit={handleStartEdit}
          onCancelEdit={handleCancelEdit}
        />
      )}
      
      {/* Commissie Modal */}
      <CommissieEditModal
        isOpen={showCommissieModal}
        onClose={() => setShowCommissieModal(false)}
        onSubmit={handleCreateCommissie}
        isLoading={isCreatingCommissie}
      />

      {/* Bulk Labels Modal */}
      <BulkLabelsModal
        isOpen={showBulkLabelsModal}
        onClose={() => setShowBulkLabelsModal(false)}
        selectedCount={selectedIds.size}
        labels={commissieLabels}
        onSubmit={async (mode, labelIds) => {
          setBulkActionLoading(true);
          try {
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates: {
                labels: labelIds,
                label_mode: mode
              }
            });
            clearSelection();
            setShowBulkLabelsModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />

    </div>
  );
}
