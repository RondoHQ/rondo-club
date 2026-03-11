import { useState, useMemo, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Building2, Filter, X, CheckSquare, Square, MinusSquare, Check, Pencil } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { getCommissieName } from '@/utils/formatters';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import InlineFieldInput from '@/components/InlineFieldInput';
import SortableHeader from '@/components/SortableHeader';
import { DataTableToolbar, ColumnSettingsPanel, useColumnVisibility, createColumn, FILTER_TYPES } from '@/components/DataTable';

function OrganizationListRow({ commissie, listViewFields, isSelected, onToggleSelection, isOdd, onSaveRow, isUpdating, isEditing, onStartEdit, onCancelEdit, isColVisible }) {
  const [editedFields, setEditedFields] = useState({});

  useEffect(() => {
    if (isEditing) {
      const initialValues = {
        _name: commissie.title?.rendered || commissie.title || '',
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
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
      e.preventDefault();
      handleSave();
    }
  };

  return (
    <tr
      className={`group hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'} ${isEditing ? 'ring-2 ring-electric-cyan ring-inset' : ''}`}
      onKeyDown={isEditing ? handleKeyDown : undefined}
    >
      <td className="pl-4 pr-2 py-3 w-10">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(commissie.id); }}
          className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      <td className="px-4 py-3 whitespace-nowrap" onDoubleClick={() => !isEditing && onStartEdit(commissie.id)}>
        {isEditing ? (
          <input
            type="text"
            value={editedFields._name ?? ''}
            onChange={(e) => handleFieldChange('_name', e.target.value)}
            onKeyDown={handleKeyDown}
            className="w-full px-2 py-1 text-sm font-medium border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
            disabled={isUpdating}
            autoFocus
          />
        ) : (
          <Link to={`/commissies/${commissie.id}`} className="text-sm font-medium text-gray-900 dark:text-gray-50 hover:text-electric-cyan dark:hover:text-electric-cyan">
            {getCommissieName(commissie)}
          </Link>
        )}
      </td>
      {isColVisible('member_count') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
          {commissie.member_count ?? 0}
        </td>
      )}
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
            className="p-1.5 text-gray-400 hover:text-electric-cyan hover:bg-cyan-50 dark:hover:text-electric-cyan dark:hover:bg-obsidian/20 rounded opacity-0 group-hover:opacity-100 transition-opacity"
            title="Edit row"
          >
            <Pencil className="w-4 h-4" />
          </button>
        )}
      </td>
    </tr>
  );
}

function OrganizationListView({ commissies, listViewFields, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected, sortField, sortOrder, onSort, onSaveRow, isUpdating, editingRowId, onStartEdit, onCancelEdit, isColVisible }) {
  return (
    <div className="card !overflow-x-auto max-h-[calc(100vh-12rem)] !overflow-y-auto">
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
                  <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
                ) : isSomeSelected ? (
                  <MinusSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
                ) : (
                  <Square className="w-5 h-5" />
                )}
              </button>
            </th>
            <SortableHeader columnId="name" label="Naam" sortField={sortField} sortOrder={sortOrder} onSort={onSort} />
            {isColVisible('member_count') && (
              <SortableHeader columnId="member_count" label="Leden" sortField={sortField} sortOrder={sortOrder} onSort={onSort} />
            )}
            {listViewFields.map(field => (
              <SortableHeader
                key={field.key}
                columnId={`custom_${field.name}`}
                label={field.label}
                sortField={sortField}
                sortOrder={sortOrder}
                onSort={onSort}
              />
            ))}
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
              isColVisible={isColVisible}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function CommissiesList() {
  const [commissieFilter, setCommissieFilter] = useState('');
  const [ownershipFilter, setOwnershipFilter] = useState(''); // '' = all, 'mine', 'shared'
  const [memberCountFilter, setMemberCountFilter] = useState('');
  const [sortField, setSortField] = useState('name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [editingRowId, setEditingRowId] = useState(null);
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);

  const { isVisible, toggle } = useColumnVisibility('commissies');

  const currentUserId = window.rondoConfig?.userId;

  const queryClient = useQueryClient();

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['commissies'] });
  };

  const arrayTypeAcfFields = ['contact_info'];

  const updateRowMutation = useMutation({
    mutationFn: async ({ commissieId, editedFields, existingAcf }) => {
      const { _name, ...customFields } = editedFields;

      const mergedAcf = {
        ...existingAcf,
        ...customFields
      };

      arrayTypeAcfFields.forEach(fieldName => {
        if (mergedAcf[fieldName] === null || mergedAcf[fieldName] === undefined) {
          mergedAcf[fieldName] = [];
        }
      });

      const updatePayload = { acf: mergedAcf };

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

  const handleSaveRow = async (commissieId, editedFields, existingAcf) => {
    const processedFields = { ...editedFields };
    listViewFields.forEach(field => {
      if (field.type === 'number' && processedFields[field.name] === '') {
        processedFields[field.name] = null;
      }
    });
    await updateRowMutation.mutateAsync({ commissieId, editedFields: processedFields, existingAcf });
  };

  const handleStartEdit = (commissieId) => setEditingRowId(commissieId);
  const handleCancelEdit = () => setEditingRowId(null);

  const { data: commissies, isLoading, error } = useQuery({
    queryKey: ['commissies'],
    queryFn: async () => {
      const response = await wpApi.getCommissies({ per_page: 100 });
      return response.data;
    },
  });

  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'commissie'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('commissie');
      return response.data;
    },
  });

  const listViewFields = useMemo(() => {
    return customFields
      .filter(f => f.show_in_list_view)
      .sort((a, b) => (a.list_view_order || 999) - (b.list_view_order || 999));
  }, [customFields]);

  const hasActiveFilters = !!(commissieFilter || ownershipFilter || memberCountFilter);
  const activeFilterCount = [commissieFilter, ownershipFilter, memberCountFilter].filter(Boolean).length;

  const clearFilters = () => {
    setCommissieFilter('');
    setOwnershipFilter('');
    setMemberCountFilter('');
  };

  const setFilter = (colId, value) => {
    if (colId === 'commissie') setCommissieFilter(value || '');
    else if (colId === 'ownership') setOwnershipFilter(value || '');
    else if (colId === 'member_count_filter') setMemberCountFilter(value || '');
  };

  // Column definitions for the filter toolbar
  const filterColumns = useMemo(() => [
    createColumn({
      id: 'commissie',
      header: 'Commissie',
      filterType: FILTER_TYPES.TEXT,
    }),
    createColumn({
      id: 'ownership',
      header: 'Eigenaar',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'mine', label: 'Mijn commissies' },
        { value: 'shared', label: 'Gedeeld met mij' },
      ],
    }),
    createColumn({
      id: 'member_count_filter',
      header: 'Leden',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'heeft', label: 'Heeft leden' },
        { value: 'geen', label: 'Geen leden' },
      ],
      getFilterLabel: (val) => val === 'heeft' ? 'Heeft leden' : 'Geen leden',
    }),
  ], []);

  // Column definitions for the settings panel
  const colVisColumns = [
    { id: 'member_count', label: 'Leden', isVisible: isVisible('member_count') },
  ];

  const toggleSelection = (commissieId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(commissieId)) next.delete(commissieId);
      else next.add(commissieId);
      return next;
    });
  };

  const clearSelection = () => setSelectedIds(new Set());

  const filteredCommissies = useMemo(() => {
    if (!commissies) return [];

    let filtered = [...commissies];

    if (commissieFilter) {
      const needle = commissieFilter.toLowerCase();
      filtered = filtered.filter(c => getCommissieName(c).toLowerCase().includes(needle));
    }
    if (ownershipFilter === 'mine') filtered = filtered.filter(c => c.author === currentUserId);
    else if (ownershipFilter === 'shared') filtered = filtered.filter(c => c.author !== currentUserId);
    if (memberCountFilter === 'heeft') filtered = filtered.filter(c => (c.member_count ?? 0) > 0);
    else if (memberCountFilter === 'geen') filtered = filtered.filter(c => (c.member_count ?? 0) === 0);

    return filtered;
  }, [commissies, commissieFilter, ownershipFilter, memberCountFilter, currentUserId]);

  const sortedCommissies = useMemo(() => {
    if (!filteredCommissies) return [];

    return [...filteredCommissies].sort((a, b) => {
      let valueA, valueB;

      if (sortField === 'name') {
        valueA = (a.title?.rendered || a.title || '').toLowerCase();
        valueB = (b.title?.rendered || b.title || '').toLowerCase();
      } else if (sortField === 'member_count') {
        valueA = a.member_count ?? 0;
        valueB = b.member_count ?? 0;
        return sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
      } else if (sortField.startsWith('custom_')) {
        const fieldName = sortField.replace('custom_', '');
        const fieldMeta = listViewFields.find(f => f.name === fieldName);
        valueA = a.acf?.[fieldName];
        valueB = b.acf?.[fieldName];

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

        valueA = String(valueA || '').toLowerCase();
        valueB = String(valueB || '').toLowerCase();
      } else {
        valueA = (a.title?.rendered || a.title || '').toLowerCase();
        valueB = (b.title?.rendered || b.title || '').toLowerCase();
      }

      if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
      if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
      if (!valueA && !valueB) return 0;

      const comparison = valueA.localeCompare(valueB);
      return sortOrder === 'asc' ? comparison : -comparison;
    });
  }, [filteredCommissies, sortField, sortOrder, listViewFields]);

  const isAllSelected = sortedCommissies.length > 0 && selectedIds.size === sortedCommissies.length;
  const isSomeSelected = selectedIds.size > 0 && selectedIds.size < sortedCommissies.length;

  const toggleSelectAll = () => {
    if (selectedIds.size === sortedCommissies.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(sortedCommissies.map(c => c.id)));
    }
  };

  useEffect(() => {
    setSelectedIds(new Set());
  }, [commissieFilter, ownershipFilter, memberCountFilter, commissies]);

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        <DataTableToolbar
          columns={filterColumns}
          filters={{ commissie: commissieFilter, ownership: ownershipFilter, member_count_filter: memberCountFilter }}
          onFilterChange={setFilter}
          onClearFilters={clearFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
        />

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
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
              Commissies worden via de API of data import toegevoegd.
            </p>
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
            <button onClick={clearFilters} className="btn-tertiary">
              Filters wissen
            </button>
          </div>
        )}

        {/* Selection toolbar */}
        {selectedIds.size > 0 && (
          <div className="sticky top-0 z-20 flex items-center justify-between bg-cyan-50 border border-cyan-200 rounded-lg px-4 py-2 shadow-sm">
            <span className="text-sm text-deep-midnight font-medium">
              {selectedIds.size} {selectedIds.size === 1 ? 'commissie' : 'commissies'} geselecteerd
            </span>
            <div className="flex items-center gap-3">
              <button
                onClick={clearSelection}
                className="text-sm text-electric-cyan hover:text-deep-midnight font-medium"
              >
                Selectie wissen
              </button>
            </div>
          </div>
        )}

        {/* Commissies list */}
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
            onSort={(field, order) => {
              setSortField(field);
              setSortOrder(order);
            }}
            onSaveRow={handleSaveRow}
            isUpdating={updateRowMutation.isPending}
            editingRowId={editingRowId}
            onStartEdit={handleStartEdit}
            onCancelEdit={handleCancelEdit}
            isColVisible={isVisible}
          />
        )}
      </div>

      <ColumnSettingsPanel
        isOpen={isColumnSettingsOpen}
        onClose={() => setIsColumnSettingsOpen(false)}
        columns={colVisColumns}
        onToggleColumn={toggle}
      />
    </PullToRefreshWrapper>
  );
}
