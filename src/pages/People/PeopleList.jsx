import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Filter, X, Check, ArrowUp, ArrowDown, Square, CheckSquare, MinusSquare, ChevronDown, Building2, Tag, Settings } from 'lucide-react';
import { useFilteredPeople, useCreatePerson, useBulkUpdatePeople } from '@/hooks/usePeople';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { getTeamName } from '@/utils/formatters';
import PersonEditModal from '@/components/PersonEditModal';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import Pagination from '@/components/Pagination';
import { useListPreferences } from '@/hooks/useListPreferences';
import { useColumnResize } from '@/hooks/useColumnResize';
import ColumnSettingsModal from './ColumnSettingsModal';

// Helper function to get current team ID from person's work history
function getCurrentTeamId(person) {
  const workHistory = person.acf?.work_history || [];
  if (workHistory.length === 0) return null;

  // First, try to find current position
  const currentJob = workHistory.find(job => job.is_current && job.team);
  if (currentJob) return currentJob.team;

  // Otherwise, get the most recent (by start_date)
  const jobsWithTeam = workHistory
    .filter(job => job.team)
    .sort((a, b) => {
      const dateA = a.start_date ? new Date(a.start_date) : new Date(0);
      const dateB = b.start_date ? new Date(b.start_date) : new Date(0);
      return dateB - dateA; // Most recent first
    });

  return jobsWithTeam.length > 0 ? jobsWithTeam[0].team : null;
}

// Map column IDs to sort field names
const COLUMN_SORT_FIELDS = {
  name: 'first_name',
  first_name: 'first_name',
  last_name: 'last_name',
  team: 'organization',
  labels: 'labels',
  modified: 'modified',
};

function PersonListRow({ person, teamName, visibleColumns, columnMap, columnWidths, customFieldsMap, isSelected, onToggleSelection, isOdd }) {
  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'}`}>
      <td className="pl-4 pr-2 py-3 w-10 sticky left-0 z-[1] bg-inherit">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(person.id); }}
          className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      {/* Photo - sticky with checkbox */}
      <td
        className="w-10 px-2 py-3 sticky left-10 z-[1] bg-inherit"
        style={{ minWidth: '40px' }}
      >
        <Link to={`/people/${person.id}`} className="flex items-center justify-center">
          {person.thumbnail ? (
            <img
              src={person.thumbnail}
              alt=""
              className="w-8 h-8 rounded-full object-cover"
            />
          ) : (
            <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
              <span className="text-sm font-medium text-gray-500 dark:text-gray-300">
                {person.first_name?.[0] || '?'}
              </span>
            </div>
          )}
        </Link>
      </td>
      {/* Name - sticky */}
      <td
        className="px-4 py-3 whitespace-nowrap sticky left-[88px] z-[1] bg-inherit"
        style={{
          width: columnWidths['name'] ? `${columnWidths['name']}px` : '200px',
          minWidth: columnWidths['name'] ? `${columnWidths['name']}px` : '200px',
        }}
      >
        <Link to={`/people/${person.id}`} className="flex items-center">
          <span className="text-sm font-medium text-gray-900 dark:text-gray-50">
            {person.first_name || ''} {person.last_name || ''}
            {person.is_deceased && <span className="ml-1 text-gray-500 dark:text-gray-400">&#8224;</span>}
          </span>
        </Link>
      </td>
      {/* Dynamic columns based on visible_columns order */}
      {visibleColumns.map(colId => {
        const column = columnMap[colId];
        if (!column) return null;

        const width = columnWidths[colId];
        const style = width ? {
          width: `${width}px`,
          minWidth: `${width}px`,
          maxWidth: `${width}px`,
        } : {};

        // Check if this is a custom field
        const customField = customFieldsMap[colId];
        if (customField) {
          return (
            <td
              key={colId}
              className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              <CustomFieldColumn field={customField} value={person.acf?.[customField.name]} />
            </td>
          );
        }

        // Core columns
        if (colId === 'team') {
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {teamName || '-'}
            </td>
          );
        }

        if (colId === 'labels') {
          return (
            <td
              key={colId}
              className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {person.labels && person.labels.length > 0 ? (
                <div className="flex flex-wrap gap-1">
                  {person.labels.slice(0, 3).map((label) => (
                    <span
                      key={label}
                      className="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
                    >
                      {label}
                    </span>
                  ))}
                  {person.labels.length > 3 && (
                    <span className="text-xs text-gray-400 dark:text-gray-500">+{person.labels.length - 3} meer</span>
                  )}
                </div>
              ) : (
                '-'
              )}
            </td>
          );
        }

        if (colId === 'modified') {
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {person.modified ? new Date(person.modified).toLocaleDateString('nl-NL') : '-'}
            </td>
          );
        }

        return null;
      })}
    </tr>
  );
}

// Resizable header component for column resizing
function ResizableHeader({
  colId,
  label,
  width: initialWidth,
  sortField,
  sortOrder,
  onSort,
  onWidthChange,
  isSticky,
  stickyLeft,
  className = '',
}) {
  const { width, isResizing, resizeHandlers } = useColumnResize(colId, initialWidth, 50);

  // Notify parent of width changes
  useEffect(() => {
    if (!isResizing && width !== initialWidth) {
      onWidthChange(colId, width);
    }
  }, [isResizing, width, initialWidth, colId, onWidthChange]);

  // Determine sort field for this column
  const columnSortField = COLUMN_SORT_FIELDS[colId] || (colId.startsWith('custom_') ? colId : `custom_${colId}`);
  const isActive = sortField === columnSortField;

  const stickyStyles = isSticky ? {
    position: 'sticky',
    left: stickyLeft,
    zIndex: 11,
  } : {};

  return (
    <th
      scope="col"
      className={`px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800 relative select-none ${className}`}
      style={{
        width: `${width}px`,
        minWidth: `${width}px`,
        maxWidth: `${width}px`,
        ...stickyStyles,
      }}
    >
      <button
        onClick={() => onSort(columnSortField)}
        className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
      >
        {label}
        {isActive && (
          sortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </button>
      {/* Resize handle */}
      <div
        {...resizeHandlers}
        className={`absolute top-0 right-0 w-2 h-full cursor-col-resize hover:bg-accent-300 dark:hover:bg-accent-600 transition-colors ${
          isResizing ? 'bg-accent-400 dark:bg-accent-500' : ''
        }`}
        style={{ touchAction: 'none' }}
      />
    </th>
  );
}

function PersonListView({
  people,
  teamMap,
  visibleColumns,
  columnMap,
  columnWidths,
  customFieldsMap,
  selectedIds,
  onToggleSelection,
  onToggleSelectAll,
  isAllSelected,
  isSomeSelected,
  sortField,
  sortOrder,
  onSort,
  onColumnWidthChange,
}) {
  return (
    <div className="card overflow-x-auto max-h-[calc(100vh-12rem)] overflow-y-auto">
      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
          <tr className="shadow-sm dark:shadow-gray-900/50">
            {/* Checkbox column - sticky */}
            <th
              scope="col"
              className="pl-4 pr-2 py-3 w-10 bg-gray-50 dark:bg-gray-800 sticky left-0 z-[11]"
            >
              <button
                onClick={onToggleSelectAll}
                className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
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
            {/* Photo column - sticky */}
            <th
              scope="col"
              className="w-10 px-2 bg-gray-50 dark:bg-gray-800 sticky left-10 z-[11]"
              style={{ minWidth: '40px' }}
            ></th>
            {/* Name column - sticky and resizable */}
            <ResizableHeader
              colId="name"
              label="Naam"
              width={columnWidths['name'] || 200}
              sortField={sortField}
              sortOrder={sortOrder}
              onSort={onSort}
              onWidthChange={onColumnWidthChange}
              isSticky={true}
              stickyLeft="88px"
              className="sticky left-[88px] z-[11]"
            />
            {/* Dynamic columns based on visible_columns order */}
            {visibleColumns.map(colId => {
              const column = columnMap[colId];
              if (!column) return null;

              return (
                <ResizableHeader
                  key={colId}
                  colId={colId}
                  label={column.label}
                  width={columnWidths[colId] || 150}
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={onSort}
                  onWidthChange={onColumnWidthChange}
                />
              );
            })}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
          {people.map((person, index) => (
            <PersonListRow
              key={person.id}
              person={person}
              teamName={teamMap[person.id]}
              visibleColumns={visibleColumns}
              columnMap={columnMap}
              columnWidths={columnWidths}
              customFieldsMap={customFieldsMap}
              isSelected={selectedIds.has(person.id)}
              onToggleSelection={onToggleSelection}
              isOdd={index % 2 === 1}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

// Bulk Organization Modal Component
function BulkOrganizationModal({ isOpen, onClose, selectedCount, teams, onSubmit, isLoading }) {
  const [selectedTeamId, setSelectedTeamId] = useState(null);
  const [searchQuery, setSearchQuery] = useState('');

  // Reset when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedTeamId(null);
      setSearchQuery('');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  // Filter teams by search query
  const filteredTeams = (teams || []).filter(team =>
    team.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Team instellen</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" disabled={isLoading}>
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Stel huidig team in voor {selectedCount} {selectedCount === 1 ? 'lid' : 'leden'}:
          </p>

          {/* Search input */}
          <input
            type="text"
            placeholder="Teams zoeken..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg text-sm focus:ring-accent-500 focus:border-accent-500"
          />

          {/* Option to clear organization */}
          <button
            type="button"
            onClick={() => setSelectedTeamId('clear')}
            disabled={isLoading}
            className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
              selectedTeamId === 'clear'
                ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
            }`}
          >
            <X className={`w-5 h-5 ${selectedTeamId === 'clear' ? 'text-accent-600 dark:text-accent-400' : 'text-gray-400 dark:text-gray-500'}`} />
            <div className="flex-1">
              <div className="text-sm font-medium text-gray-900 dark:text-gray-50">Team verwijderen</div>
              <div className="text-xs text-gray-500 dark:text-gray-400">Verwijder huidig team van geselecteerde leden</div>
            </div>
            {selectedTeamId === 'clear' && <Check className="w-5 h-5 text-accent-600 dark:text-accent-400" />}
          </button>

          {/* Team list */}
          <div className="space-y-2 max-h-64 overflow-y-auto">
            {filteredTeams.length === 0 ? (
              <p className="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                {searchQuery ? 'Geen teams gevonden voor je zoekopdracht' : 'Geen teams gevonden'}
              </p>
            ) : (
              filteredTeams.map((team) => {
                const isSelected = selectedTeamId === team.id;
                return (
                  <button
                    key={team.id}
                    type="button"
                    onClick={() => setSelectedTeamId(team.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isSelected
                        ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <Building2 className={`w-5 h-5 ${isSelected ? 'text-accent-600 dark:text-accent-400' : 'text-gray-400 dark:text-gray-500'}`} />
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900 dark:text-gray-50">{team.name}</div>
                    </div>
                    {isSelected && <Check className="w-5 h-5 text-accent-600 dark:text-accent-400" />}
                  </button>
                );
              })
            )}
          </div>
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button type="button" onClick={onClose} className="btn-secondary" disabled={isLoading}>
            Annuleren
          </button>
          <button
            type="button"
            onClick={() => onSubmit(selectedTeamId === 'clear' ? null : selectedTeamId)}
            className="btn-primary"
            disabled={isLoading || selectedTeamId === null}
          >
            {isLoading ? 'Toepassen...' : `Toepassen op ${selectedCount} ${selectedCount === 1 ? 'lid' : 'leden'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

// Bulk Labels Modal Component
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
            {mode === 'add' ? 'Voeg labels toe aan' : 'Verwijder labels van'} {selectedCount} {selectedCount === 1 ? 'lid' : 'leden'}:
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
              : `${selectedLabelIds.length} label${selectedLabelIds.length === 1 ? '' : 's'} ${mode === 'add' ? 'toevoegen' : 'verwijderen'}`
            }
          </button>
        </div>
      </div>
    </div>
  );
}

export default function PeopleList() {
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [selectedLabelIds, setSelectedLabelIds] = useState([]);
  const [selectedBirthYear, setSelectedBirthYear] = useState('');
  const [lastModifiedFilter, setLastModifiedFilter] = useState('');
  const [sortField, setSortField] = useState('first_name'); // 'first_name' or 'last_name'
  const [sortOrder, setSortOrder] = useState('asc'); // 'asc' or 'desc'
  const [page, setPage] = useState(1);
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showPersonModal, setShowPersonModal] = useState(false);
  const [isCreatingPerson, setIsCreatingPerson] = useState(false);
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkOrganizationModal, setShowBulkOrganizationModal] = useState(false);
  const [showBulkLabelsModal, setShowBulkLabelsModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const [showColumnSettings, setShowColumnSettings] = useState(false);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const bulkDropdownRef = useRef(null);
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  // Column preferences hook
  const {
    preferences,
    isLoading: prefsLoading,
    updateColumnWidths
  } = useListPreferences();

  const { data, isLoading, isFetching, error } = useFilteredPeople({
    page,
    perPage: 100,
    labels: selectedLabelIds,
    ownership: 'all',
    modifiedDays: lastModifiedFilter ? parseInt(lastModifiedFilter, 10) : null,
    birthYearFrom: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
    birthYearTo: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
    orderby: sortField.startsWith('custom_') ? sortField : sortField === 'organization' || sortField === 'labels' ? 'first_name' : sortField,
    order: sortOrder,
  });

  // Extract data from response
  const people = data?.people || [];
  const totalPeople = data?.total || 0;
  const totalPages = data?.total_pages || 0;

  const bulkUpdateMutation = useBulkUpdatePeople();

  // Reset page to 1 when filters change
  useEffect(() => {
    setPage(1);
  }, [selectedLabelIds, selectedBirthYear, lastModifiedFilter, sortField, sortOrder]);

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['people', 'list'] });
  };

  // Create person mutation (using shared hook)
  const createPersonMutation = useCreatePerson({
    onSuccess: (result) => {
      setShowPersonModal(false);
      navigate(`/people/${result.id}`);
    },
  });

  const handleCreatePerson = async (data) => {
    setIsCreatingPerson(true);
    try {
      await createPersonMutation.mutateAsync(data);
    } finally {
      setIsCreatingPerson(false);
    }
  };

  // Fetch person labels
  const { data: labelsData } = useQuery({
    queryKey: ['person-labels'],
    queryFn: async () => {
      const response = await wpApi.getPersonLabels();
      return response.data;
    },
  });

  // Labels with IDs for the bulk modal
  const availableLabelsWithIds = labelsData || [];

  // Fetch custom field definitions for list view columns
  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'person'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('person');
      return response.data;
    },
  });

  // Create map of custom field name to field definition
  const customFieldsMap = useMemo(() => {
    const map = {};
    customFields.forEach(field => {
      // Custom fields in available_columns use their name as the ID
      map[field.name] = field;
    });
    return map;
  }, [customFields]);

  // Create column map from preferences
  const columnMap = useMemo(() => {
    const map = {};
    if (preferences?.available_columns) {
      preferences.available_columns.forEach(col => {
        map[col.id] = col;
      });
    }
    return map;
  }, [preferences?.available_columns]);

  // Get visible columns (excluding 'name' which is always shown in a fixed position)
  const visibleColumns = useMemo(() => {
    if (!preferences?.visible_columns || !preferences?.column_order) {
      // Fallback to default columns if preferences not loaded
      return ['team', 'labels'];
    }

    // Filter column_order to only visible columns, excluding 'name'
    const visibleSet = new Set(preferences.visible_columns);
    return preferences.column_order.filter(colId =>
      colId !== 'name' && visibleSet.has(colId)
    );
  }, [preferences?.visible_columns, preferences?.column_order]);

  // Get column widths from preferences
  const columnWidths = preferences?.column_widths || {};

  // Handle column width change from resize
  const handleColumnWidthChange = useCallback((colId, newWidth) => {
    updateColumnWidths({ [colId]: newWidth });
  }, [updateColumnWidths]);

  // Fetch all teams for bulk organization modal (sorted alphabetically)
  const { data: allTeamsData } = useQuery({
    queryKey: ['teams', 'all'],
    queryFn: async () => {
      const response = await wpApi.getTeams({ per_page: 100 });
      return response.data
        .map(team => ({
          id: team.id,
          name: getTeamName(team),
        }))
        .sort((a, b) => a.name.localeCompare(b.name));
    },
  });

  // Generate reasonable birth year range instead of deriving from data
  const availableBirthYears = useMemo(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear - 5; year >= 1950; year--) {
      years.push(year);
    }
    return years;
  }, []);

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

  const hasActiveFilters = selectedLabelIds.length > 0 || selectedBirthYear || lastModifiedFilter;

  const handleLabelToggle = (labelId) => {
    setSelectedLabelIds(prev =>
      prev.includes(labelId)
        ? prev.filter(id => id !== labelId)
        : [...prev, labelId]
    );
  };

  const clearFilters = () => {
    setSelectedLabelIds([]);
    setSelectedBirthYear('');
    setLastModifiedFilter('');
    // page will auto-reset via useEffect
  };

  // Selection helper functions
  const toggleSelection = (personId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(personId)) {
        next.delete(personId);
      } else {
        next.add(personId);
      }
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === people.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(people.map(p => p.id)));
    }
  };

  const clearSelection = () => setSelectedIds(new Set());

  const isAllSelected = people.length > 0 &&
    selectedIds.size === people.length;
  const isSomeSelected = selectedIds.size > 0 &&
    selectedIds.size < people.length;

  // Clear selection when filters change, page changes, or data changes
  useEffect(() => {
    setSelectedIds(new Set());
  }, [selectedLabelIds, selectedBirthYear, lastModifiedFilter, page, people]);

  // Collect all team IDs
  const teamIds = useMemo(() => {
    if (!people) return [];
    const ids = people
      .map(person => getCurrentTeamId(person))
      .filter(Boolean);
    // Remove duplicates
    return [...new Set(ids)];
  }, [people]);

  // Batch fetch all teams at once instead of individual queries
  const { data: teamsData } = useQuery({
    queryKey: ['teams', 'batch', teamIds.sort().join(',')],
    queryFn: async () => {
      if (teamIds.length === 0) return [];
      // Fetch all teams in one request
      const response = await wpApi.getTeams({
        per_page: 100,
        include: teamIds.join(','),
      });
      return response.data;
    },
    enabled: teamIds.length > 0,
  });

  // Create a map of team ID to team name
  const teamMap = useMemo(() => {
    const map = {};
    if (teamsData) {
      teamsData.forEach(team => {
        map[team.id] = getTeamName(team);
      });
    }
    return map;
  }, [teamsData]);

  // Create a map of person ID to team name
  const personTeamMap = useMemo(() => {
    const map = {};
    people.forEach(person => {
      const teamId = getCurrentTeamId(person);
      if (teamId && teamMap[teamId]) {
        map[person.id] = teamMap[teamId];
      }
    });
    return map;
  }, [people, teamMap]);

  // Handle sort from table header
  const handleSort = useCallback((field) => {
    if (field === sortField) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('asc');
    }
  }, [sortField, sortOrder]);

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex flex-wrap items-center gap-2">
          {/* Sort Controls */}
          <div className="flex items-center gap-2 border border-gray-200 dark:border-gray-600 rounded-lg p-1">
            <select
              value={sortField}
              onChange={(e) => setSortField(e.target.value)}
              className="text-sm border-0 bg-transparent dark:text-gray-200 focus:ring-0 focus:outline-none cursor-pointer"
            >
              <option value="first_name">Voornaam</option>
              <option value="last_name">Achternaam</option>
              <option value="modified">Laatst gewijzigd</option>
              <option value="organization">Team</option>
              <option value="labels">Labels</option>
            </select>
            <div className="h-4 w-px bg-gray-300 dark:bg-gray-600"></div>
            <button
              onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
              title={`Sorteer ${sortOrder === 'asc' ? 'aflopend' : 'oplopend'}`}
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
                  {selectedLabelIds.length + (selectedBirthYear ? 1 : 0) + (lastModifiedFilter ? 1 : 0)}
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
                  {/* Labels Filter */}
                  {availableLabelsWithIds.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Labels
                      </h3>
                      <div className="space-y-1 max-h-48 overflow-y-auto">
                        {availableLabelsWithIds.map(label => (
                          <label
                            key={label.id}
                            className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded"
                          >
                            <input
                              type="checkbox"
                              checked={selectedLabelIds.includes(label.id)}
                              onChange={() => handleLabelToggle(label.id)}
                              className="sr-only"
                            />
                            <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                              selectedLabelIds.includes(label.id)
                                ? 'bg-accent-600 border-accent-600'
                                : 'border-gray-300 dark:border-gray-500'
                            }`}>
                              {selectedLabelIds.includes(label.id) && (
                                <Check className="w-3 h-3 text-white" />
                              )}
                            </div>
                            <span className="text-sm text-gray-700 dark:text-gray-200">{label.name}</span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Birth Year Filter */}
                  {availableBirthYears.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Geboortejaar
                      </h3>
                      <select
                        value={selectedBirthYear}
                        onChange={(e) => setSelectedBirthYear(e.target.value)}
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-accent-500 focus:border-accent-500"
                      >
                        <option value="">Alle jaren</option>
                        {availableBirthYears.map(year => (
                          <option key={year} value={year}>{year}</option>
                        ))}
                      </select>
                    </div>
                  )}

                  {/* Last Modified Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Laatst gewijzigd
                    </h3>
                    <select
                      value={lastModifiedFilter}
                      onChange={(e) => setLastModifiedFilter(e.target.value)}
                      className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-accent-500 focus:border-accent-500"
                    >
                      <option value="">Alle tijden</option>
                      <option value="7">Laatste 7 dagen</option>
                      <option value="30">Laatste 30 dagen</option>
                      <option value="90">Laatste 90 dagen</option>
                      <option value="365">Laatste jaar</option>
                    </select>
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
              {selectedLabelIds.map(labelId => {
                const label = availableLabelsWithIds.find(l => l.id === labelId);
                return label ? (
                  <span
                    key={labelId}
                    className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs"
                  >
                    {label.name}
                    <button
                      onClick={() => handleLabelToggle(labelId)}
                      className="hover:text-gray-600 dark:hover:text-gray-300"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                ) : null;
              })}
              {selectedBirthYear && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Geboren {selectedBirthYear}
                  <button
                    onClick={() => setSelectedBirthYear('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {lastModifiedFilter && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Gewijzigd: {lastModifiedFilter === '7' ? 'laatste 7 dagen' :
                             lastModifiedFilter === '30' ? 'laatste 30 dagen' :
                             lastModifiedFilter === '90' ? 'laatste 90 dagen' : 'laatste jaar'}
                  <button
                    onClick={() => setLastModifiedFilter('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
            </div>
          )}
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => setShowPersonModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Lid toevoegen</span>
          </button>
          <button
            onClick={() => setShowColumnSettings(true)}
            className="btn-secondary"
            title="Kolommen aanpassen"
          >
            <Settings className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Loading state */}
      {(isLoading || prefsLoading) && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
        </div>
      )}

      {/* Error state */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600 dark:text-red-400">Leden konden niet worden geladen.</p>
        </div>
      )}

      {/* Empty state - no people at all */}
      {!isLoading && !prefsLoading && !error && totalPeople === 0 && !hasActiveFilters && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Plus className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen leden gevonden</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Voeg je eerste lid toe om te beginnen.
          </p>
          <button onClick={() => setShowPersonModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Lid toevoegen</span>
          </button>
        </div>
      )}

      {/* Selection toolbar - sticky */}
      {selectedIds.size > 0 && (
        <div className="sticky top-0 z-20 flex items-center justify-between bg-accent-50 dark:bg-accent-800 border border-accent-200 dark:border-accent-700 rounded-lg px-4 py-2 shadow-sm">
          <span className="text-sm text-accent-800 dark:text-accent-200 font-medium">
            {selectedIds.size} {selectedIds.size === 1 ? 'lid' : 'leden'} geselecteerd
          </span>
          <div className="flex items-center gap-3">
            {/* Bulk Actions Dropdown */}
            <div className="relative" ref={bulkDropdownRef}>
              <button
                onClick={() => setShowBulkDropdown(!showBulkDropdown)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-accent-700 dark:text-accent-200 bg-white dark:bg-gray-800 border border-accent-300 dark:border-accent-600 rounded-md hover:bg-accent-50 dark:hover:bg-gray-700"
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
                        setShowBulkOrganizationModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      <Building2 className="w-4 h-4" />
                      Team instellen...
                    </button>
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkLabelsModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
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
              className="text-sm text-accent-600 dark:text-accent-400 hover:text-accent-800 dark:hover:text-accent-300 font-medium"
            >
              Selectie wissen
            </button>
          </div>
        </div>
      )}

      {/* Loading indicator for page navigation */}
      {isFetching && !isLoading && (
        <div className="fixed bottom-4 right-4 bg-white dark:bg-gray-800 shadow-lg rounded-lg px-4 py-2 flex items-center gap-2 z-50">
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-accent-600" />
          <span className="text-sm text-gray-600 dark:text-gray-300">Laden...</span>
        </div>
      )}

      {/* People list */}
      {!isLoading && !prefsLoading && !error && people.length > 0 && (
        <>
          <PersonListView
            people={people}
            teamMap={personTeamMap}
            visibleColumns={visibleColumns}
            columnMap={columnMap}
            columnWidths={columnWidths}
            customFieldsMap={customFieldsMap}
            selectedIds={selectedIds}
            onToggleSelection={toggleSelection}
            onToggleSelectAll={toggleSelectAll}
            isAllSelected={isAllSelected}
            isSomeSelected={isSomeSelected}
            sortField={sortField}
            sortOrder={sortOrder}
            onSort={handleSort}
            onColumnWidthChange={handleColumnWidthChange}
          />
          {totalPages > 1 && (
            <Pagination
              currentPage={page}
              totalPages={totalPages}
              totalItems={totalPeople}
              itemsPerPage={100}
              onPageChange={setPage}
            />
          )}
        </>
      )}

      {/* No results with filters */}
      {!isLoading && !prefsLoading && !error && people.length === 0 && totalPeople === 0 && hasActiveFilters && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Filter className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen leden vinden die aan je filters voldoen</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Pas je filters aan om meer resultaten te zien.
          </p>
          <button onClick={clearFilters} className="btn-secondary">
            Filters wissen
          </button>
        </div>
      )}

      {/* Person Modal */}
      <PersonEditModal
        isOpen={showPersonModal}
        onClose={() => setShowPersonModal(false)}
        onSubmit={handleCreatePerson}
        isLoading={isCreatingPerson}
      />

      {/* Column Settings Modal */}
      <ColumnSettingsModal
        isOpen={showColumnSettings}
        onClose={() => setShowColumnSettings(false)}
      />

      {/* Bulk Organization Modal */}
      <BulkOrganizationModal
        isOpen={showBulkOrganizationModal}
        onClose={() => setShowBulkOrganizationModal(false)}
        selectedCount={selectedIds.size}
        teams={allTeamsData || []}
        onSubmit={async (teamId) => {
          setBulkActionLoading(true);
          try {
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates: { organization_id: teamId }
            });
            clearSelection();
            setShowBulkOrganizationModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />

      {/* Bulk Labels Modal */}
      <BulkLabelsModal
        isOpen={showBulkLabelsModal}
        onClose={() => setShowBulkLabelsModal(false)}
        selectedCount={selectedIds.size}
        labels={availableLabelsWithIds}
        onSubmit={async (mode, labelIds) => {
          setBulkActionLoading(true);
          try {
            const updates = mode === 'add'
              ? { labels_add: labelIds }
              : { labels_remove: labelIds };
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates
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
    </PullToRefreshWrapper>
  );
}
