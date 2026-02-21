import { useState, useMemo, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Search, Building2, Filter, X, CheckSquare, Square, MinusSquare, Check, Pencil } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { getTeamName } from '@/utils/formatters';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import InlineFieldInput from '@/components/InlineFieldInput';
import SortableHeader from '@/components/SortableHeader';
import { DataTableToolbar, ColumnSettingsPanel, useColumnVisibility, createColumn, FILTER_TYPES } from '@/components/DataTable';

function getSpeeldag(activiteit) {
  if (!activiteit) return '';
  const parts = activiteit.split(/veld\s*-\s*/i);
  return parts.length > 1 ? parts[parts.length - 1].trim() : activiteit;
}

function getGenderLabel(gender) {
  if (!gender) return '';
  const map = { male: 'Man', female: 'Vrouw', Mannen: 'Man', Vrouwen: 'Vrouw', Gemengd: 'Gemengd' };
  return map[gender] || gender;
}

function OrganizationListRow({ team, listViewFields, isSelected, onToggleSelection, isOdd, onSaveRow, isUpdating, isEditing, onStartEdit, onCancelEdit, isColVisible }) {
  const [editedFields, setEditedFields] = useState({});

  useEffect(() => {
    if (isEditing) {
      const initialValues = {
        _name: team.title?.rendered || team.title || '',
      };
      listViewFields.forEach(field => {
        initialValues[field.name] = team.acf?.[field.name] ?? '';
      });
      setEditedFields(initialValues);
    } else {
      setEditedFields({});
    }
  }, [isEditing, team.acf, team.title, listViewFields]);

  const handleFieldChange = (fieldName, value) => {
    setEditedFields(prev => ({ ...prev, [fieldName]: value }));
  };

  const handleSave = () => {
    onSaveRow(team.id, editedFields, team.acf);
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
          onClick={(e) => { e.preventDefault(); onToggleSelection(team.id); }}
          className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      <td className="px-4 py-3 whitespace-nowrap" onDoubleClick={() => !isEditing && onStartEdit(team.id)}>
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
          <Link to={`/teams/${team.id}`} className="text-sm font-medium text-gray-900 dark:text-gray-50 hover:text-electric-cyan dark:hover:text-electric-cyan">
            {getTeamName(team)}
          </Link>
        )}
      </td>
      {isColVisible('player_count') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
          {team.player_count ?? 0}
        </td>
      )}
      {isColVisible('staff_count') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
          {team.staff_count ?? 0}
        </td>
      )}
      {isColVisible('speeldag') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
          {getSpeeldag(team.acf?.activiteit)}
        </td>
      )}
      {isColVisible('gender') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
          {getGenderLabel(team.acf?.gender)}
        </td>
      )}
      {listViewFields.map(field => (
        <td key={field.key} className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" onDoubleClick={() => !isEditing && onStartEdit(team.id)}>
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
              <CustomFieldColumn field={field} value={team.acf?.[field.name]} />
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
            onClick={() => onStartEdit(team.id)}
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

function OrganizationListView({ teams, listViewFields, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected, sortField, sortOrder, onSort, onSaveRow, isUpdating, editingRowId, onStartEdit, onCancelEdit, isColVisible }) {
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
            {isColVisible('player_count') && (
              <SortableHeader columnId="player_count" label="Spelers" sortField={sortField} sortOrder={sortOrder} onSort={onSort} />
            )}
            {isColVisible('staff_count') && (
              <SortableHeader columnId="staff_count" label="Staf" sortField={sortField} sortOrder={sortOrder} onSort={onSort} />
            )}
            {isColVisible('speeldag') && (
              <SortableHeader columnId="speeldag" label="Speeldag" sortField={sortField} sortOrder={sortOrder} onSort={onSort} />
            )}
            {isColVisible('gender') && (
              <SortableHeader columnId="gender" label="Gender" sortField={sortField} sortOrder={sortOrder} onSort={onSort} />
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
          {teams.map((team, index) => (
            <OrganizationListRow
              key={team.id}
              team={team}
              listViewFields={listViewFields}
              isSelected={selectedIds.has(team.id)}
              onToggleSelection={onToggleSelection}
              isOdd={index % 2 === 1}
              onSaveRow={onSaveRow}
              isUpdating={isUpdating && editingRowId === team.id}
              isEditing={editingRowId === team.id}
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

export default function TeamsList() {
  const [search, setSearch] = useState('');
  const [speeldagFilter, setSpeeldagFilter] = useState('');
  const [genderFilter, setGenderFilter] = useState('');
  const [sortField, setSortField] = useState('name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [editingRowId, setEditingRowId] = useState(null);
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);

  const { isVisible, toggle } = useColumnVisibility('teams');

  const queryClient = useQueryClient();

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['teams'] });
  };

  const arrayTypeAcfFields = ['contact_info', 'investors'];

  const updateRowMutation = useMutation({
    mutationFn: async ({ teamId, editedFields, existingAcf }) => {
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

      const response = await wpApi.updateTeam(teamId, updatePayload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teams'] });
      setEditingRowId(null);
    },
  });

  const handleSaveRow = async (teamId, editedFields, existingAcf) => {
    const processedFields = { ...editedFields };
    listViewFields.forEach(field => {
      if (field.type === 'number' && processedFields[field.name] === '') {
        processedFields[field.name] = null;
      }
    });
    await updateRowMutation.mutateAsync({ teamId, editedFields: processedFields, existingAcf });
  };

  const handleStartEdit = (teamId) => setEditingRowId(teamId);
  const handleCancelEdit = () => setEditingRowId(null);

  const { data: teams, isLoading, error } = useQuery({
    queryKey: ['teams', search],
    queryFn: async () => {
      const response = await wpApi.getTeams({ search, per_page: 100 });
      return response.data;
    },
  });

  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'team'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('team');
      return response.data;
    },
  });

  const listViewFields = useMemo(() => {
    return customFields
      .filter(f => f.show_in_list_view)
      .sort((a, b) => (a.list_view_order || 999) - (b.list_view_order || 999));
  }, [customFields]);

  const hasActiveFilters = !!speeldagFilter || !!genderFilter;
  const activeFilterCount = (speeldagFilter ? 1 : 0) + (genderFilter ? 1 : 0);

  const clearFilters = () => {
    setSpeeldagFilter('');
    setGenderFilter('');
  };

  const setFilter = (colId, value) => {
    if (colId === 'speeldag') setSpeeldagFilter(value || '');
    else if (colId === 'gender') setGenderFilter(value || '');
  };

  const toggleSelection = (teamId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(teamId)) next.delete(teamId);
      else next.add(teamId);
      return next;
    });
  };

  const clearSelection = () => setSelectedIds(new Set());

  const speeldagOptions = useMemo(() => {
    if (!teams) return [];
    return [...new Set(teams.map(t => getSpeeldag(t.acf?.activiteit)).filter(Boolean))].sort();
  }, [teams]);

  const genderOptions = useMemo(() => {
    if (!teams) return [];
    return [...new Set(teams.map(t => getGenderLabel(t.acf?.gender)).filter(Boolean))].sort();
  }, [teams]);

  // Column definitions for the filter toolbar
  const filterColumns = useMemo(() => [
    createColumn({
      id: 'speeldag',
      header: 'Speeldag',
      filterType: speeldagOptions.length > 0 ? FILTER_TYPES.SELECT : null,
      filterOptions: speeldagOptions.map(v => ({ value: v, label: v })),
    }),
    createColumn({
      id: 'gender',
      header: 'Gender',
      filterType: genderOptions.length > 0 ? FILTER_TYPES.SELECT : null,
      filterOptions: genderOptions.map(v => ({ value: v, label: v })),
    }),
  ], [speeldagOptions, genderOptions]);

  // Column definitions for the settings panel
  const colVisColumns = [
    { id: 'player_count', label: 'Spelers', isVisible: isVisible('player_count') },
    { id: 'staff_count', label: 'Staf', isVisible: isVisible('staff_count') },
    { id: 'speeldag', label: 'Speeldag', isVisible: isVisible('speeldag') },
    { id: 'gender', label: 'Gender', isVisible: isVisible('gender') },
  ];

  const filteredTeams = useMemo(() => {
    if (!teams) return [];

    let filtered = [...teams];

    if (speeldagFilter) {
      filtered = filtered.filter(t => getSpeeldag(t.acf?.activiteit) === speeldagFilter);
    }
    if (genderFilter) {
      filtered = filtered.filter(t => getGenderLabel(t.acf?.gender) === genderFilter);
    }

    return filtered;
  }, [teams, speeldagFilter, genderFilter]);

  const sortedTeams = useMemo(() => {
    if (!filteredTeams) return [];

    return [...filteredTeams].sort((a, b) => {
      let valueA, valueB;

      if (sortField === 'name') {
        valueA = (a.title?.rendered || a.title || '').toLowerCase();
        valueB = (b.title?.rendered || b.title || '').toLowerCase();
      } else if (sortField === 'speeldag') {
        valueA = getSpeeldag(a.acf?.activiteit).toLowerCase();
        valueB = getSpeeldag(b.acf?.activiteit).toLowerCase();
      } else if (sortField === 'gender') {
        valueA = getGenderLabel(a.acf?.gender).toLowerCase();
        valueB = getGenderLabel(b.acf?.gender).toLowerCase();
      } else if (sortField === 'player_count') {
        valueA = a.player_count ?? 0;
        valueB = b.player_count ?? 0;
        return sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
      } else if (sortField === 'staff_count') {
        valueA = a.staff_count ?? 0;
        valueB = b.staff_count ?? 0;
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
  }, [filteredTeams, sortField, sortOrder, listViewFields]);

  const isAllSelected = sortedTeams.length > 0 && selectedIds.size === sortedTeams.length;
  const isSomeSelected = selectedIds.size > 0 && selectedIds.size < sortedTeams.length;

  const toggleSelectAll = () => {
    if (selectedIds.size === sortedTeams.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(sortedTeams.map(c => c.id)));
    }
  };

  useEffect(() => {
    setSelectedIds(new Set());
  }, [speeldagFilter, genderFilter, teams]);

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Header: search + filter toolbar */}
        <div className="space-y-2">
          <div className="relative max-w-md">
            <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
              type="search"
              placeholder="Teams zoeken..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input pl-3 pr-9"
            />
          </div>

          <DataTableToolbar
            columns={filterColumns}
            filters={{ speeldag: speeldagFilter, gender: genderFilter }}
            onFilterChange={setFilter}
            onClearFilters={clearFilters}
            hasActiveFilters={hasActiveFilters}
            activeFilterCount={activeFilterCount}
            onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
          />
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
          </div>
        )}

        {/* Error */}
        {error && (
          <div className="card p-6 text-center">
            <p className="text-red-600 dark:text-red-400">Teams konden niet worden geladen.</p>
          </div>
        )}

        {/* Empty - no teams at all */}
        {!isLoading && !error && teams?.length === 0 && (
          <div className="card p-12 text-center">
            <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
              <Building2 className="w-6 h-6 text-gray-400 dark:text-gray-500" />
            </div>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen teams gevonden</h3>
            <p className="text-gray-500 dark:text-gray-400 mb-4">
              {search ? 'Probeer een andere zoekopdracht.' : 'Teams worden via de API of data import toegevoegd.'}
            </p>
          </div>
        )}

        {/* No results with filters */}
        {!isLoading && !error && teams?.length > 0 && sortedTeams?.length === 0 && (
          <div className="card p-12 text-center">
            <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
              <Filter className="w-6 h-6 text-gray-400 dark:text-gray-500" />
            </div>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen teams voldoen aan je filters</h3>
            <p className="text-gray-500 dark:text-gray-400 mb-4">
              Pas je filters aan om meer resultaten te zien.
            </p>
            <button onClick={clearFilters} className="btn-secondary">
              Filters wissen
            </button>
          </div>
        )}

        {/* Selection toolbar */}
        {selectedIds.size > 0 && (
          <div className="sticky top-0 z-20 flex items-center justify-between bg-cyan-50 border border-cyan-200 rounded-lg px-4 py-2 shadow-sm">
            <span className="text-sm text-deep-midnight font-medium">
              {selectedIds.size} {selectedIds.size === 1 ? 'team' : 'teams'} geselecteerd
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

        {/* Teams list */}
        {!isLoading && !error && sortedTeams?.length > 0 && (
          <OrganizationListView
            teams={sortedTeams}
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
