import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Search, Building2, Filter, X, CheckSquare, Square, MinusSquare, ArrowUp, ArrowDown, Check, Pencil } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { getTeamName } from '@/utils/formatters';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import InlineFieldInput from '@/components/InlineFieldInput';

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

function OrganizationListRow({ team, listViewFields, isSelected, onToggleSelection, isOdd, onSaveRow, isUpdating, isEditing, onStartEdit, onCancelEdit }) {
  // Local state for edited field values (includes name, website, and custom fields)
  const [editedFields, setEditedFields] = useState({});

  // Reset edited fields when entering/exiting edit mode
  useEffect(() => {
    if (isEditing) {
      // Initialize with current values for core fields and custom fields
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
    // Save on Enter (but not in textareas or selects where Enter might have other meaning)
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
      <td className="w-10 px-2 py-3">
        <Link to={`/teams/${team.id}`} className="flex items-center justify-center">
          {team._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
            <img
              src={team._embedded['wp:featuredmedia'][0].source_url}
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
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
        {getSpeeldag(team.acf?.activiteit)}
      </td>
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
        {getGenderLabel(team.acf?.gender)}
      </td>
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

function OrganizationListView({ teams, listViewFields, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected, sortField, sortOrder, onSort, onSaveRow, isUpdating, editingRowId, onStartEdit, onCancelEdit }) {
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
            <th scope="col" className="w-10 px-2 bg-gray-50 dark:bg-gray-800"></th>
            <SortableHeader field="name" label="Naam" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="speeldag" label="Speeldag" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="gender" label="Gender" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
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
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function TeamsList() {
  const [search, setSearch] = useState('');
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [speeldagFilter, setSpeeldagFilter] = useState(new Set());
  const [genderFilter, setGenderFilter] = useState(new Set());
  const [sortField, setSortField] = useState('name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [editingRowId, setEditingRowId] = useState(null);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const navigate = useNavigate();

  const queryClient = useQueryClient();

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['teams'] });
  };

  // Mutation for updating row custom fields
  // ACF fields that require array type (repeaters, multi-select post_object, etc.)
  // These cannot be null - must be empty array [] when empty
  const arrayTypeAcfFields = ['contact_info', 'investors'];

  const updateRowMutation = useMutation({
    mutationFn: async ({ teamId, editedFields, existingAcf }) => {
      // Extract core fields (prefixed with _) from custom fields
      const { _name, ...customFields } = editedFields;

      // Merge custom fields with existing ACF data
      const mergedAcf = {
        ...existingAcf,
        ...customFields
      };

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

      const response = await wpApi.updateTeam(teamId, updatePayload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teams'] });
      setEditingRowId(null);
    },
  });

  // Handler for saving all edited fields in a row
  const handleSaveRow = async (teamId, editedFields, existingAcf) => {
    // Convert empty strings to null for number fields (REST API requires number or null)
    const processedFields = { ...editedFields };
    listViewFields.forEach(field => {
      if (field.type === 'number' && processedFields[field.name] === '') {
        processedFields[field.name] = null;
      }
    });
    await updateRowMutation.mutateAsync({ teamId, editedFields: processedFields, existingAcf });
  };

  // Row edit mode handlers
  const handleStartEdit = (teamId) => {
    setEditingRowId(teamId);
  };

  const handleCancelEdit = () => {
    setEditingRowId(null);
  };

  const { data: teams, isLoading, error } = useQuery({
    queryKey: ['teams', search],
    queryFn: async () => {
      const response = await wpApi.getTeams({ search, per_page: 100, _embed: true });
      return response.data;
    },
  });

  // Fetch custom field definitions for list view columns
  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'team'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('team');
      return response.data;
    },
  });

  // Filter to list-view-enabled fields, sorted by order
  const listViewFields = useMemo(() => {
    return customFields
      .filter(f => f.show_in_list_view)
      .sort((a, b) => (a.list_view_order || 999) - (b.list_view_order || 999));
  }, [customFields]);

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
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const hasActiveFilters = speeldagFilter.size > 0 || genderFilter.size > 0;

  const clearFilters = () => {
    setSpeeldagFilter(new Set());
    setGenderFilter(new Set());
  };

  // Selection helper functions
  const toggleSelection = (teamId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(teamId)) {
        next.delete(teamId);
      } else {
        next.add(teamId);
      }
      return next;
    });
  };

  const clearSelection = () => setSelectedIds(new Set());

  // Compute unique filter options from teams data
  const speeldagOptions = useMemo(() => {
    if (!teams) return [];
    return [...new Set(teams.map(t => getSpeeldag(t.acf?.activiteit)).filter(Boolean))].sort();
  }, [teams]);

  const genderOptions = useMemo(() => {
    if (!teams) return [];
    return [...new Set(teams.map(t => getGenderLabel(t.acf?.gender)).filter(Boolean))].sort();
  }, [teams]);

  // Filter teams
  const filteredTeams = useMemo(() => {
    if (!teams) return [];

    let filtered = [...teams];

    if (speeldagFilter.size > 0) {
      filtered = filtered.filter(t => speeldagFilter.has(getSpeeldag(t.acf?.activiteit)));
    }
    if (genderFilter.size > 0) {
      filtered = filtered.filter(t => genderFilter.has(getGenderLabel(t.acf?.gender)));
    }

    return filtered;
  }, [teams, speeldagFilter, genderFilter]);

  // Sort filtered teams
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
  }, [filteredTeams, sortField, sortOrder, listViewFields]);

  // Computed selection state
  const isAllSelected = sortedTeams.length > 0 &&
    selectedIds.size === sortedTeams.length;
  const isSomeSelected = selectedIds.size > 0 &&
    selectedIds.size < sortedTeams.length;

  const toggleSelectAll = () => {
    if (selectedIds.size === sortedTeams.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(sortedTeams.map(c => c.id)));
    }
  };

  // Clear selection when filters change
  useEffect(() => {
    setSelectedIds(new Set());
  }, [speeldagFilter, genderFilter, teams]);
  
  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative flex-1 min-w-48 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
              type="search"
              placeholder="Teams zoeken..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input pl-9"
            />
          </div>

          <div className="relative" ref={filterRef}>
            <button
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${hasActiveFilters ? 'bg-cyan-50 text-bright-cobalt border-cyan-200' : ''}`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-electric-cyan text-white text-xs rounded-full">
                  {speeldagFilter.size + genderFilter.size}
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
                  {/* Speeldag Filter */}
                  {speeldagOptions.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Speeldag
                      </h3>
                      <div className="space-y-1">
                        {speeldagOptions.map(option => (
                          <label
                            key={option}
                            className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded"
                          >
                            <input
                              type="checkbox"
                              checked={speeldagFilter.has(option)}
                              onChange={() => {
                                setSpeeldagFilter(prev => {
                                  const next = new Set(prev);
                                  if (next.has(option)) next.delete(option);
                                  else next.add(option);
                                  return next;
                                });
                              }}
                              className="sr-only"
                            />
                            <div className={`flex items-center justify-center w-4 h-4 border-2 rounded mr-3 ${
                              speeldagFilter.has(option)
                                ? 'border-electric-cyan bg-electric-cyan'
                                : 'border-gray-300 dark:border-gray-500'
                            }`}>
                              {speeldagFilter.has(option) && (
                                <Check className="w-3 h-3 text-white" />
                              )}
                            </div>
                            <span className="text-sm text-gray-700 dark:text-gray-200">{option}</span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Gender Filter */}
                  {genderOptions.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Gender
                      </h3>
                      <div className="space-y-1">
                        {genderOptions.map(option => (
                          <label
                            key={option}
                            className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded"
                          >
                            <input
                              type="checkbox"
                              checked={genderFilter.has(option)}
                              onChange={() => {
                                setGenderFilter(prev => {
                                  const next = new Set(prev);
                                  if (next.has(option)) next.delete(option);
                                  else next.add(option);
                                  return next;
                                });
                              }}
                              className="sr-only"
                            />
                            <div className={`flex items-center justify-center w-4 h-4 border-2 rounded mr-3 ${
                              genderFilter.has(option)
                                ? 'border-electric-cyan bg-electric-cyan'
                                : 'border-gray-300 dark:border-gray-500'
                            }`}>
                              {genderFilter.has(option) && (
                                <Check className="w-3 h-3 text-white" />
                              )}
                            </div>
                            <span className="text-sm text-gray-700 dark:text-gray-200">{option}</span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Clear Filters */}
                  {hasActiveFilters && (
                    <button
                      onClick={clearFilters}
                      className="w-full text-sm text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light font-medium pt-2 border-t border-gray-200 dark:border-gray-700"
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
              {[...speeldagFilter].map(val => (
                <span key={`speeldag-${val}`} className="inline-flex items-center gap-1 px-2 py-1 bg-cyan-100 dark:bg-obsidian/50 text-deep-midnight dark:text-cyan-200 rounded-full text-xs">
                  {val}
                  <button onClick={() => setSpeeldagFilter(prev => { const next = new Set(prev); next.delete(val); return next; })} className="hover:text-electric-cyan dark:hover:text-electric-cyan-light">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              ))}
              {[...genderFilter].map(val => (
                <span key={`gender-${val}`} className="inline-flex items-center gap-1 px-2 py-1 bg-cyan-100 dark:bg-obsidian/50 text-deep-midnight dark:text-cyan-200 rounded-full text-xs">
                  {val}
                  <button onClick={() => setGenderFilter(prev => { const next = new Set(prev); next.delete(val); return next; })} className="hover:text-electric-cyan dark:hover:text-electric-cyan-light">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              ))}
            </div>
          )}
        </div>
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
      
      {/* Selection toolbar - sticky */}
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

      {/* Organizations list */}
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

      </div>
    </PullToRefreshWrapper>
  );
}
