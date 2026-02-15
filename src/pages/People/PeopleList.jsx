import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Filter, X, Check, ArrowUp, ArrowDown, Square, CheckSquare, MinusSquare, ChevronDown, Building2, Settings, FileSpreadsheet } from 'lucide-react';
import { useFilteredPeople, useFilterOptions, useBulkUpdatePeople } from '@/hooks/usePeople';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';
import { getTeamName, formatPhoneForTel } from '@/utils/formatters';
import { format } from '@/utils/dateFormat';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import Pagination from '@/components/Pagination';
import { useListPreferences } from '@/hooks/useListPreferences';
import { useColumnResize } from '@/hooks/useColumnResize';
import ColumnSettingsModal from './ColumnSettingsModal';

// Helper function to get first contact value by type
function getFirstContactByType(person, type) {
  const contactInfo = person.acf?.contact_info || [];
  const contact = contactInfo.find(c => c.contact_type === type);
  return contact?.contact_value || null;
}

// Helper function to get first phone (includes mobile)
function getFirstPhone(person) {
  const contactInfo = person.acf?.contact_info || [];
  const contact = contactInfo.find(c => c.contact_type === 'phone' || c.contact_type === 'mobile');
  return contact?.contact_value || null;
}

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
  modified: 'modified',
  // Sportlink field mappings
  'knvb-id': 'custom_knvb-id',
  'type-lid': 'custom_type-lid',
  'leeftijdsgroep': 'custom_leeftijdsgroep',
  'lid-sinds': 'custom_lid-sinds',
  'datum-foto': 'custom_datum-foto',
  'datum-vog': 'custom_datum-vog',
  'isparent': 'custom_isparent',
  'huidig-vrijwilliger': 'custom_huidig-vrijwilliger',
  'financiele-blokkade': 'custom_financiele-blokkade',
  'freescout-id': 'custom_freescout-id',
};

function PersonListRow({ person, teamName, visibleColumns, columnMap, columnWidths, customFieldsMap, isSelected, onToggleSelection, isOdd }) {
  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'} ${person.former_member ? 'opacity-60' : ''}`}>
      <td className="pl-4 pr-2 py-3 w-10 sticky left-0 z-[1] bg-inherit">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(person.id); }}
          className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
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
          <PersonAvatar
            thumbnail={person.thumbnail}
            name={person.first_name}
            firstName={person.first_name}
            size="md"
          />
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
            {[person.first_name, person.infix, person.last_name].filter(Boolean).join(' ')}
            {person.is_deceased && <span className="ml-1 text-gray-500 dark:text-gray-400">&#8224;</span>}
          </span>
          {person.former_member && (
            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
              Oud-lid
            </span>
          )}
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

        if (colId === 'modified') {
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {person.modified ? format(new Date(person.modified), 'yyyy-MM-dd') : '-'}
            </td>
          );
        }

        if (colId === 'email') {
          const email = getFirstContactByType(person, 'email');
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {email ? (
                <a href={`mailto:${email}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">
                  {email}
                </a>
              ) : '-'}
            </td>
          );
        }

        if (colId === 'phone') {
          const phone = getFirstPhone(person);
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {phone ? (
                <a href={`tel:${formatPhoneForTel(phone)}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">
                  {phone}
                </a>
              ) : '-'}
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
  // Handle resize end - callback is stored in ref inside hook to avoid loops
  const handleResizeEnd = useCallback((newWidth) => {
    onWidthChange(colId, newWidth);
  }, [colId, onWidthChange]);

  const { width, isResizing, resizeHandlers } = useColumnResize(initialWidth, 50, handleResizeEnd);

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
        className={`absolute top-0 right-0 w-2 h-full cursor-col-resize group/resize flex items-center justify-center hover:bg-electric-cyan-light/50 dark:hover:bg-electric-cyan/50 transition-colors ${
          isResizing ? 'bg-electric-cyan/50 dark:bg-electric-cyan/50' : ''
        }`}
        style={{ touchAction: 'none' }}
      >
        {/* Visual indicator line */}
        <div className={`w-px h-4 rounded-full transition-colors ${
          isResizing
            ? 'bg-electric-cyan dark:bg-electric-cyan'
            : 'bg-gray-300 dark:bg-gray-600 group-hover/resize:bg-electric-cyan dark:group-hover/resize:bg-electric-cyan'
        }`} />
      </div>
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
    <div className="card !overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead className="bg-gray-50 dark:bg-gray-800">
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
                  <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
                ) : isSomeSelected ? (
                  <MinusSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
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
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
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
            className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg text-sm focus:ring-electric-cyan focus:border-electric-cyan"
          />

          {/* Option to clear organization */}
          <button
            type="button"
            onClick={() => setSelectedTeamId('clear')}
            disabled={isLoading}
            className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
              selectedTeamId === 'clear'
                ? 'border-electric-cyan bg-cyan-50 dark:bg-deep-midnight'
                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
            }`}
          >
            <X className={`w-5 h-5 ${selectedTeamId === 'clear' ? 'text-electric-cyan dark:text-electric-cyan' : 'text-gray-400 dark:text-gray-500'}`} />
            <div className="flex-1">
              <div className="text-sm font-medium text-gray-900 dark:text-gray-50">Team verwijderen</div>
              <div className="text-xs text-gray-500 dark:text-gray-400">Verwijder huidig team van geselecteerde leden</div>
            </div>
            {selectedTeamId === 'clear' && <Check className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />}
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
                        ? 'border-electric-cyan bg-cyan-50 dark:bg-deep-midnight'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <Building2 className={`w-5 h-5 ${isSelected ? 'text-electric-cyan dark:text-electric-cyan' : 'text-gray-400 dark:text-gray-500'}`} />
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900 dark:text-gray-50">{team.name}</div>
                    </div>
                    {isSelected && <Check className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />}
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

export default function PeopleList() {
  // URL-based filter state for persistence on back navigation
  const [searchParams, setSearchParams] = useSearchParams();

  // Parse filters from URL
  const selectedBirthYear = searchParams.get('birthYear') || '';
  const lastModifiedFilter = searchParams.get('modified') || '';
  const sortField = searchParams.get('sort') || 'first_name';
  const sortOrder = searchParams.get('order') || 'asc';
  const page = parseInt(searchParams.get('page') || '1', 10);

  // Custom field filters from URL
  const huidigeVrijwilliger = searchParams.get('vrijwilliger') || '';
  const financieleBlokkade = searchParams.get('blokkade') || '';
  const typeLid = searchParams.get('typeLid') || '';
  const leeftijdsgroep = searchParams.get('leeftijdsgroep') || '';
  const fotoMissing = searchParams.get('fotoMissing') || '';
  const vogMissing = searchParams.get('vogMissing') || '';
  const vogOlderThanYears = searchParams.get('vogOuder') ? parseInt(searchParams.get('vogOuder'), 10) : null;
  const includeFormer = searchParams.get('oudLeden') || '';
  const lidTotFuture = searchParams.get('lidTot') || '';

  // Helper to update URL params
  const updateSearchParams = useCallback((updates) => {
    setSearchParams(prev => {
      const next = new URLSearchParams(prev);
      Object.entries(updates).forEach(([key, value]) => {
        if (value === null || value === '' || value === undefined || (Array.isArray(value) && value.length === 0)) {
          next.delete(key);
        } else if (Array.isArray(value)) {
          next.set(key, value.join(','));
        } else {
          next.set(key, String(value));
        }
      });
      // Reset page when filters change (except when explicitly setting page)
      if (!('page' in updates)) {
        next.delete('page');
      }
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  // Filter setters that update URL
  const setSelectedBirthYear = useCallback((value) => {
    updateSearchParams({ birthYear: value });
  }, [updateSearchParams]);

  const setLastModifiedFilter = useCallback((value) => {
    updateSearchParams({ modified: value });
  }, [updateSearchParams]);

  const setSortField = useCallback((value) => {
    updateSearchParams({ sort: value });
  }, [updateSearchParams]);

  const setSortOrder = useCallback((value) => {
    updateSearchParams({ order: value });
  }, [updateSearchParams]);

  const setPage = useCallback((value) => {
    updateSearchParams({ page: value === 1 ? null : value });
  }, [updateSearchParams]);

  // Custom field filter setters
  const setHuidigeVrijwilliger = useCallback((value) => {
    updateSearchParams({ vrijwilliger: value });
  }, [updateSearchParams]);

  const setFinancieleBlokkade = useCallback((value) => {
    updateSearchParams({ blokkade: value });
  }, [updateSearchParams]);

  const setTypeLid = useCallback((value) => {
    updateSearchParams({ typeLid: value });
  }, [updateSearchParams]);

  const setLeeftijdsgroep = useCallback((value) => {
    updateSearchParams({ leeftijdsgroep: value });
  }, [updateSearchParams]);

  const setFotoMissing = useCallback((value) => {
    updateSearchParams({ fotoMissing: value });
  }, [updateSearchParams]);

  const setVogMissing = useCallback((value) => {
    updateSearchParams({ vogMissing: value });
  }, [updateSearchParams]);

  const setVogOlderThanYears = useCallback((value) => {
    updateSearchParams({ vogOuder: value });
  }, [updateSearchParams]);

  const setIncludeFormer = useCallback((value) => {
    updateSearchParams({ oudLeden: value });
  }, [updateSearchParams]);

  const setLidTotFuture = useCallback((value) => {
    updateSearchParams({ lidTot: value });
  }, [updateSearchParams]);

  // Local UI state (not persisted in URL)
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkOrganizationModal, setShowBulkOrganizationModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const [showColumnSettings, setShowColumnSettings] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const bulkDropdownRef = useRef(null);
  const queryClient = useQueryClient();

  // Google Sheets connection status
  const { data: sheetsStatus } = useQuery({
    queryKey: ['google-sheets-status'],
    queryFn: async () => {
      const response = await prmApi.getSheetsStatus();
      return response.data;
    },
  });

  // Column preferences hook
  const {
    preferences,
    isLoading: prefsLoading,
    updateColumnWidths
  } = useListPreferences();

  const { data, isLoading, isFetching, error } = useFilteredPeople({
    page,
    perPage: 100,
    ownership: 'all',
    modifiedDays: lastModifiedFilter ? parseInt(lastModifiedFilter, 10) : null,
    birthYearFrom: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
    birthYearTo: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
    orderby: sortField.startsWith('custom_') ? sortField : sortField === 'organization' ? 'first_name' : sortField,
    order: sortOrder,
    // Custom field filters
    huidigeVrijwilliger,
    financieleBlokkade,
    typeLid,
    leeftijdsgroep,
    fotoMissing,
    vogMissing,
    vogOlderThanYears,
    includeFormer: includeFormer || null,
    lidTotFuture: lidTotFuture || null,
  });

  // Extract data from response
  const people = data?.people || [];
  const totalPeople = data?.total || 0;
  const totalPages = data?.total_pages || 0;

  // Fetch filter options for dynamic dropdowns
  const {
    data: filterOptions,
    isLoading: filterOptionsLoading,
    error: filterOptionsError,
    refetch: refetchFilterOptions,
  } = useFilterOptions();

  const bulkUpdateMutation = useBulkUpdatePeople();

  // Note: Page reset is handled automatically in updateSearchParams when filters change

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['people', 'list'] });
  };

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
      return ['team'];
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

  const hasActiveFilters = selectedBirthYear || lastModifiedFilter ||
    huidigeVrijwilliger || financieleBlokkade || typeLid || leeftijdsgroep || fotoMissing || vogMissing || vogOlderThanYears ||
    includeFormer || lidTotFuture;

  // Update filteredCount URL param when filters are active and data is loaded
  useEffect(() => {
    if (hasActiveFilters && !isLoading) {
      // Set filteredCount param when filters are active
      updateSearchParams({ filteredCount: totalPeople });
    } else {
      // Remove filteredCount param when no filters
      const current = searchParams.get('filteredCount');
      if (current !== null) {
        updateSearchParams({ filteredCount: null });
      }
    }
  }, [hasActiveFilters, totalPeople, isLoading, searchParams, updateSearchParams]);

  // Validate URL filter params against loaded filter options
  // If a filter value in the URL doesn't exist in the database, clear it
  useEffect(() => {
    if (!filterOptions || filterOptionsLoading) return;

    const validTypeValues = filterOptions.member_types.map(o => o.value);
    const validAgeValues = filterOptions.age_groups.map(o => o.value);

    if (typeLid && !validTypeValues.includes(typeLid)) {
      setTypeLid('');
    }
    if (leeftijdsgroep && !validAgeValues.includes(leeftijdsgroep)) {
      setLeeftijdsgroep('');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterOptions, filterOptionsLoading]);
  // NOTE: Deliberately not including typeLid/leeftijdsgroep in deps to avoid infinite loop

  const clearFilters = () => {
    setSearchParams(prev => {
      const next = new URLSearchParams();
      // Keep sort preferences
      if (prev.get('sort')) next.set('sort', prev.get('sort'));
      if (prev.get('order')) next.set('order', prev.get('order'));
      return next;
    }, { replace: true });
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
  }, [selectedBirthYear, lastModifiedFilter, huidigeVrijwilliger, financieleBlokkade, typeLid, leeftijdsgroep, fotoMissing, vogMissing, vogOlderThanYears, includeFormer, lidTotFuture, page, people]);

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

  // Handle export to Google Sheets
  const handleExportToSheets = async () => {
    if (isExporting) return;

    setIsExporting(true);

    // Open window immediately (before async) to avoid popup blocker
    const newWindow = window.open('about:blank', '_blank');

    try {
      // Build column list: always include 'name', then visible columns
      const columns = ['name', ...visibleColumns];

      // Build filter params from current URL state (matching useFilteredPeople params)
      const filters = {
        search: searchParams.get('search') || undefined,
        modified_days: lastModifiedFilter ? parseInt(lastModifiedFilter, 10) : undefined,
        birth_year_from: selectedBirthYear || undefined,
        birth_year_to: selectedBirthYear || undefined,
        huidig_vrijwilliger: huidigeVrijwilliger || undefined,
        financiele_blokkade: financieleBlokkade || undefined,
        type_lid: typeLid || undefined,
        foto_missing: fotoMissing || undefined,
        vog_missing: vogMissing || undefined,
        vog_older_than_years: vogOlderThanYears || undefined,
        include_former: includeFormer || undefined,
        lid_tot_future: lidTotFuture || undefined,
        orderby: sortField,
        order: sortOrder,
      };

      // Call export endpoint
      const response = await prmApi.exportPeopleToSheets({ columns, filters });

      // Navigate the opened window to the spreadsheet
      if (response.data.spreadsheet_url && newWindow) {
        newWindow.location.href = response.data.spreadsheet_url;
      }

    } catch (error) {
      console.error('Export error:', error);
      // Close the blank window on error
      if (newWindow) newWindow.close();
      const message = error.response?.data?.message || 'Export mislukt. Probeer het opnieuw.';
      alert(message);
    } finally {
      setIsExporting(false);
    }
  };

  // Handle connect to Google Sheets
  const handleConnectSheets = async () => {
    try {
      const response = await prmApi.getSheetsAuthUrl();
      if (response.data.auth_url) {
        window.location.href = response.data.auth_url;
      }
    } catch (error) {
      console.error('Auth error:', error);
      alert('Kon geen verbinding maken met Google Sheets. Probeer het opnieuw.');
    }
  };

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Header */}
      <div className="flex flex-row flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative" ref={filterRef}>
            <button
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${hasActiveFilters ? 'bg-cyan-50 text-bright-cobalt border-cyan-200' : ''}`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-electric-cyan text-white text-xs rounded-full">
                  {(selectedBirthYear ? 1 : 0) + (lastModifiedFilter ? 1 : 0) +
                   (huidigeVrijwilliger ? 1 : 0) + (financieleBlokkade ? 1 : 0) + (typeLid ? 1 : 0) +
                   (leeftijdsgroep ? 1 : 0) + (fotoMissing ? 1 : 0) + (vogMissing ? 1 : 0) + (vogOlderThanYears ? 1 : 0) +
                   (includeFormer ? 1 : 0) + (lidTotFuture ? 1 : 0)}
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
                  {/* Former Members Toggle */}
                  <div>
                    <label className="flex items-center cursor-pointer">
                      <div className="relative">
                        <input
                          type="checkbox"
                          checked={includeFormer === '1'}
                          onChange={() => setIncludeFormer(includeFormer === '1' ? '' : '1')}
                          className="sr-only peer"
                        />
                        <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:bg-electric-cyan transition-colors"></div>
                        <div className="absolute left-[2px] top-[2px] bg-white w-4 h-4 rounded-full transition-transform peer-checked:translate-x-4"></div>
                      </div>
                      <span className="ml-3 text-sm font-medium text-gray-700 dark:text-gray-200">Toon oud-leden</span>
                    </label>
                  </div>

                  {/* Lid-tot in Future Toggle */}
                  <div>
                    <label className="flex items-center cursor-pointer">
                      <div className="relative">
                        <input
                          type="checkbox"
                          checked={lidTotFuture === '1'}
                          onChange={() => setLidTotFuture(lidTotFuture === '1' ? '' : '1')}
                          className="sr-only peer"
                        />
                        <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:bg-electric-cyan transition-colors"></div>
                        <div className="absolute left-[2px] top-[2px] bg-white w-4 h-4 rounded-full transition-transform peer-checked:translate-x-4"></div>
                      </div>
                      <span className="ml-3 text-sm font-medium text-gray-700 dark:text-gray-200">Afmelding in de toekomst</span>
                    </label>
                  </div>

                  {/* Birth Year Filter */}
                  {availableBirthYears.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Geboortejaar
                      </h3>
                      <select
                        value={selectedBirthYear}
                        onChange={(e) => setSelectedBirthYear(e.target.value)}
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
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
                      className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                    >
                      <option value="">Alle tijden</option>
                      <option value="7">Laatste 7 dagen</option>
                      <option value="30">Laatste 30 dagen</option>
                      <option value="90">Laatste 90 dagen</option>
                      <option value="365">Laatste jaar</option>
                    </select>
                  </div>

                  {/* Huidig Vrijwilliger Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Huidig vrijwilliger
                    </h3>
                    <select
                      value={huidigeVrijwilliger}
                      onChange={(e) => setHuidigeVrijwilliger(e.target.value)}
                      className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                    >
                      <option value="">Alle</option>
                      <option value="1">Ja</option>
                      <option value="0">Nee</option>
                    </select>
                  </div>

                  {/* Financiele Blokkade Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Financiële blokkade
                    </h3>
                    <select
                      value={financieleBlokkade}
                      onChange={(e) => setFinancieleBlokkade(e.target.value)}
                      className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                    >
                      <option value="">Alle</option>
                      <option value="1">Ja</option>
                      <option value="0">Nee</option>
                    </select>
                  </div>

                  {/* Type Lid Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Type lid
                    </h3>
                    {filterOptionsLoading ? (
                      <select
                        disabled
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                      >
                        <option value="">Laden...</option>
                      </select>
                    ) : filterOptionsError ? (
                      <div>
                        <select
                          disabled
                          className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                        >
                          <option value="">Fout bij laden</option>
                        </select>
                        <button
                          onClick={() => refetchFilterOptions()}
                          className="text-xs text-electric-cyan dark:text-electric-cyan hover:underline mt-1"
                        >
                          Opnieuw proberen
                        </button>
                      </div>
                    ) : (
                      <select
                        value={typeLid}
                        onChange={(e) => setTypeLid(e.target.value)}
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                      >
                        <option value="">Alle ({filterOptions?.total || 0})</option>
                        {filterOptions?.member_types?.map(opt => (
                          <option key={opt.value} value={opt.value}>
                            {opt.value} ({opt.count})
                          </option>
                        ))}
                      </select>
                    )}
                  </div>

                  {/* Leeftijdsgroep Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Leeftijdsgroep
                    </h3>
                    {filterOptionsLoading ? (
                      <select
                        disabled
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                      >
                        <option value="">Laden...</option>
                      </select>
                    ) : filterOptionsError ? (
                      <div>
                        <select
                          disabled
                          className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                        >
                          <option value="">Fout bij laden</option>
                        </select>
                        <button
                          onClick={() => refetchFilterOptions()}
                          className="text-xs text-electric-cyan dark:text-electric-cyan hover:underline mt-1"
                        >
                          Opnieuw proberen
                        </button>
                      </div>
                    ) : (
                      <select
                        value={leeftijdsgroep}
                        onChange={(e) => setLeeftijdsgroep(e.target.value)}
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                      >
                        <option value="">Alle ({filterOptions?.total || 0})</option>
                        {filterOptions?.age_groups?.map(opt => (
                          <option key={opt.value} value={opt.value}>
                            {opt.value} ({opt.count})
                          </option>
                        ))}
                      </select>
                    )}
                  </div>

                  {/* Foto Missing Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Foto datum
                    </h3>
                    <select
                      value={fotoMissing}
                      onChange={(e) => setFotoMissing(e.target.value)}
                      className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                    >
                      <option value="">Alle</option>
                      <option value="1">Ontbreekt</option>
                    </select>
                  </div>

                  {/* VOG Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      VOG datum
                    </h3>
                    <select
                      value={vogMissing === '1' ? 'missing' : (vogOlderThanYears ? `older_${vogOlderThanYears}` : '')}
                      onChange={(e) => {
                        const val = e.target.value;
                        // Update both params in single call to avoid race conditions
                        if (val === 'missing') {
                          updateSearchParams({ vogMissing: '1', vogOuder: null });
                        } else if (val.startsWith('older_')) {
                          updateSearchParams({ vogMissing: '', vogOuder: parseInt(val.split('_')[1], 10) });
                        } else {
                          updateSearchParams({ vogMissing: '', vogOuder: null });
                        }
                      }}
                      className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
                    >
                      <option value="">Alle</option>
                      <option value="missing">Ontbreekt</option>
                      <option value="older_3">Ouder dan 3 jaar</option>
                      <option value="older_5">Ouder dan 5 jaar</option>
                    </select>
                  </div>

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
              {huidigeVrijwilliger && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Vrijwilliger: {huidigeVrijwilliger === '1' ? 'Ja' : 'Nee'}
                  <button
                    onClick={() => setHuidigeVrijwilliger('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {financieleBlokkade && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Blokkade: {financieleBlokkade === '1' ? 'Ja' : 'Nee'}
                  <button
                    onClick={() => setFinancieleBlokkade('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {typeLid && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Type: {typeLid}
                  <button
                    onClick={() => setTypeLid('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {leeftijdsgroep && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Leeftijdsgroep: {leeftijdsgroep}
                  <button
                    onClick={() => setLeeftijdsgroep('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {fotoMissing === '1' && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Foto ontbreekt
                  <button
                    onClick={() => setFotoMissing('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {vogMissing === '1' && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  VOG ontbreekt
                  <button
                    onClick={() => setVogMissing('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {vogOlderThanYears && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  VOG ouder dan {vogOlderThanYears} jaar
                  <button
                    onClick={() => setVogOlderThanYears(null)}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {lidTotFuture === '1' && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Afmelding in de toekomst
                  <button
                    onClick={() => setLidTotFuture('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
            </div>
          )}
        </div>
        <div className="flex gap-2">
          {/* Google Sheets Export Button */}
          {sheetsStatus?.connected ? (
            <button
              onClick={handleExportToSheets}
              disabled={isExporting}
              className="btn-secondary"
              title="Exporteren naar Google Sheets"
            >
              {isExporting ? (
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-current"></div>
              ) : (
                <FileSpreadsheet className="w-4 h-4" />
              )}
            </button>
          ) : sheetsStatus?.google_configured ? (
            <button
              onClick={handleConnectSheets}
              className="btn-secondary"
              title="Verbinden met Google Sheets"
            >
              <FileSpreadsheet className="w-4 h-4" />
            </button>
          ) : null}

          {/* Column Settings Button */}
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
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
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
            <Filter className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen leden gevonden</h3>
          <p className="text-gray-500 dark:text-gray-400">
            Leden worden gesynchroniseerd vanuit Sportlink.
          </p>
        </div>
      )}

      {/* Selection toolbar - sticky */}
      {selectedIds.size > 0 && (
        <div className="sticky top-0 z-20 flex items-center justify-between bg-cyan-50 dark:bg-deep-midnight border border-cyan-200 dark:border-bright-cobalt rounded-lg px-4 py-2 shadow-sm">
          <span className="text-sm text-deep-midnight dark:text-cyan-200 font-medium">
            {selectedIds.size} {selectedIds.size === 1 ? 'lid' : 'leden'} geselecteerd
          </span>
          <div className="flex items-center gap-3">
            {/* Bulk Actions Dropdown */}
            <div className="relative" ref={bulkDropdownRef}>
              <button
                onClick={() => setShowBulkDropdown(!showBulkDropdown)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-bright-cobalt dark:text-cyan-200 bg-white dark:bg-gray-800 border border-electric-cyan-light dark:border-electric-cyan rounded-md hover:bg-cyan-50 dark:hover:bg-gray-700"
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
                  </div>
                </div>
              )}
            </div>
            <button
              onClick={clearSelection}
              className="text-sm text-electric-cyan dark:text-electric-cyan hover:text-deep-midnight dark:hover:text-electric-cyan-light font-medium"
            >
              Selectie wissen
            </button>
          </div>
        </div>
      )}

      {/* Loading indicator for page navigation */}
      {isFetching && !isLoading && (
        <div className="fixed bottom-4 right-4 bg-white dark:bg-gray-800 shadow-lg rounded-lg px-4 py-2 flex items-center gap-2 z-50">
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-electric-cyan" />
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
      </div>
    </PullToRefreshWrapper>
  );
}
