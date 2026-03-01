import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Filter, X, Check, ArrowUp, ArrowDown, Square, CheckSquare, MinusSquare, ChevronDown, Building2, Download } from 'lucide-react';
import { DataTableToolbar, createColumn, FILTER_TYPES } from '@/components/DataTable';
import { useFilteredPeople, useFilterOptions, useBulkUpdatePeople } from '@/hooks/usePeople';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { buildCsv, downloadCsv } from '@/utils/csvExport';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';
import { getTeamName, formatPhoneForTel } from '@/utils/formatters';
import { format, parseYmd, isValid } from '@/utils/dateFormat';
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

function formatBirthdateDisplay(birthdate) {
  if (!birthdate) return '-';
  const parsed = parseYmd(birthdate);
  if (!isValid(parsed)) return '-';
  return format(parsed, 'd MMM yyyy');
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
  birthdate: 'birthdate',
  modified: 'modified',
  // Sportlink field mappings
  'knvb-id': 'custom_knvb-id',
  'type-lid': 'custom_type-lid',
  'leeftijdsgroep': 'custom_leeftijdsgroep',
  'lid-sinds': 'custom_lid-sinds',
  'vrijwilliger-sinds': 'custom_vrijwilliger-sinds',
  'datum-foto': 'custom_datum-foto',
  'datum-vog': 'custom_datum-vog',
  'isparent': 'custom_isparent',
  'huidig-vrijwilliger': 'custom_huidig-vrijwilliger',
  'financiele-blokkade': 'custom_financiele-blokkade',
  'freescout-id': 'custom_freescout-id',
};

const UNSORTABLE_CORE_COLUMNS = new Set(['email', 'phone']);
const SORTABLE_CUSTOM_TYPES = new Set(['text', 'textarea', 'number', 'date', 'select', 'email', 'url', 'true_false']);

function getColumnSortField(colId, column) {
  if (UNSORTABLE_CORE_COLUMNS.has(colId)) return null;

  if (COLUMN_SORT_FIELDS[colId]) return COLUMN_SORT_FIELDS[colId];

  // Dynamic custom fields use `custom_{field_name}` orderby in backend.
  if (column?.custom) {
    if (!SORTABLE_CUSTOM_TYPES.has(column.type)) return null;
    return `custom_${colId}`;
  }

  return null;
}

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
      {/* Photo */}
      <td
        className="w-10 px-2 py-3"
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
      {/* Name */}
      <td
        className="px-4 py-3 whitespace-nowrap"
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

        if (colId === 'birthdate') {
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {formatBirthdateDisplay(person.acf?.birthdate)}
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
  column,
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

  const columnSortField = getColumnSortField(colId, column);
  const isSortable = Boolean(columnSortField);
  const isActive = isSortable && sortField === columnSortField;

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
      {isSortable ? (
        <button
          onClick={() => onSort(columnSortField)}
          className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer uppercase tracking-wider"
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
      ) : (
        <span className="flex items-center gap-1 uppercase tracking-wider">
          {label}
        </span>
      )}
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
            {/* Photo column */}
            <th
              scope="col"
              className="w-10 px-2 bg-gray-50 dark:bg-gray-800"
              style={{ minWidth: '40px' }}
            ></th>
            {/* Name column */}
            <ResizableHeader
              colId="name"
              column={{ id: 'name', type: 'core', custom: false }}
              label="Naam"
              width={columnWidths['name'] || 200}
              sortField={sortField}
              sortOrder={sortOrder}
              onSort={onSort}
              onWidthChange={onColumnWidthChange}
            />
            {/* Dynamic columns based on visible_columns order */}
            {visibleColumns.map(colId => {
              const column = columnMap[colId];
              if (!column) return null;

              return (
                <ResizableHeader
                  key={colId}
                  colId={colId}
                  column={column}
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
  const selectedBirthMonth = searchParams.get('birthdayMonth') || '';
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

  const setSelectedBirthMonth = useCallback((value) => {
    updateSearchParams({ birthdayMonth: value });
  }, [updateSearchParams]);

  const setLastModifiedFilter = useCallback((value) => {
    updateSearchParams({ modified: value });
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

  const setIncludeFormer = useCallback((value) => {
    updateSearchParams({ oudLeden: value });
  }, [updateSearchParams]);

  const setLidTotFuture = useCallback((value) => {
    updateSearchParams({ lidTot: value });
  }, [updateSearchParams]);

  // Local UI state (not persisted in URL)
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkOrganizationModal, setShowBulkOrganizationModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const [showColumnSettings, setShowColumnSettings] = useState(false);
  const bulkDropdownRef = useRef(null);
  const queryClient = useQueryClient();

  // Column preferences hook
  const {
    preferences,
    isLoading: prefsLoading,
    updateColumnWidths
  } = useListPreferences();

  const resolvedOrderBy = useMemo(() => {
    if (sortField === 'email' || sortField === 'phone') return 'first_name';
    if (sortField === 'organization') return 'organization';
    if (sortField === 'first_name' || sortField === 'last_name' || sortField === 'modified' || sortField === 'birthdate') {
      return sortField;
    }
    if (sortField.startsWith('custom_')) return sortField;
    return 'first_name';
  }, [sortField]);

  const { data, isLoading, isFetching, error } = useFilteredPeople({
    page,
    perPage: 100,
    ownership: 'all',
    modifiedDays: lastModifiedFilter ? parseInt(lastModifiedFilter, 10) : null,
    birthYearFrom: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
    birthYearTo: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
    birthMonth: selectedBirthMonth ? parseInt(selectedBirthMonth, 10) : null,
    orderby: resolvedOrderBy,
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
  const people = useMemo(() => data?.people || [], [data]);
  const totalPeople = data?.total || 0;
  const totalPages = data?.total_pages || 0;

  // Fetch filter options for dynamic dropdowns
  const {
    data: filterOptions,
    isLoading: filterOptionsLoading,
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

  const filterColumns = useMemo(() => [
    createColumn({ id: 'include_former', header: 'Toon oud-leden', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => '' }),
    createColumn({ id: 'lid_tot_future', header: 'Afmelding in de toekomst', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => '' }),
    createColumn({
      id: 'birth_year', header: 'Geboortejaar', filterType: FILTER_TYPES.SELECT,
      filterOptions: availableBirthYears.map(y => ({ value: String(y), label: String(y) })),
      getFilterLabel: (val) => `Geboren ${val}`,
    }),
    createColumn({
      id: 'birthday_month', header: 'Verjaardagmaand', filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: '1', label: 'Januari' },
        { value: '2', label: 'Februari' },
        { value: '3', label: 'Maart' },
        { value: '4', label: 'April' },
        { value: '5', label: 'Mei' },
        { value: '6', label: 'Juni' },
        { value: '7', label: 'Juli' },
        { value: '8', label: 'Augustus' },
        { value: '9', label: 'September' },
        { value: '10', label: 'Oktober' },
        { value: '11', label: 'November' },
        { value: '12', label: 'December' },
      ],
      getFilterLabel: (val) => {
        const monthLabels = {
          '1': 'Januari',
          '2': 'Februari',
          '3': 'Maart',
          '4': 'April',
          '5': 'Mei',
          '6': 'Juni',
          '7': 'Juli',
          '8': 'Augustus',
          '9': 'September',
          '10': 'Oktober',
          '11': 'November',
          '12': 'December',
        };
        return `Verjaardag: ${monthLabels[val] || val}`;
      },
    }),
    createColumn({
      id: 'last_modified', header: 'Gewijzigd', filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: '7', label: 'Laatste 7 dagen' }, { value: '30', label: 'Laatste 30 dagen' },
        { value: '90', label: 'Laatste 90 dagen' }, { value: '365', label: 'Laatste jaar' },
      ],
      getFilterLabel: (val) => ({ '7': 'Laatste 7 dagen', '30': 'Laatste 30 dagen', '90': 'Laatste 90 dagen', '365': 'Laatste jaar' }[val] || val),
    }),
    createColumn({
      id: 'vrijwilliger', header: 'Huidig vrijwilliger', filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: '1', label: 'Ja' }, { value: '0', label: 'Nee' }],
      getFilterLabel: (val) => `Vrijwilliger: ${val === '1' ? 'Ja' : 'Nee'}`,
    }),
    createColumn({
      id: 'blokkade', header: 'Financiële blokkade', filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: '1', label: 'Ja' }, { value: '0', label: 'Nee' }],
      getFilterLabel: (val) => `Blokkade: ${val === '1' ? 'Ja' : 'Nee'}`,
    }),
    createColumn({
      id: 'type_lid', header: 'Type lid', filterType: FILTER_TYPES.SELECT,
      filterOptions: filterOptions?.member_types?.map(opt => ({ value: opt.value, label: `${opt.value} (${opt.count})` })) || [],
      getFilterLabel: (val) => `Type: ${val}`,
    }),
    createColumn({
      id: 'leeftijdsgroep', header: 'Leeftijdsgroep', filterType: FILTER_TYPES.SELECT,
      filterOptions: filterOptions?.age_groups?.map(opt => ({ value: opt.value, label: `${opt.value} (${opt.count})` })) || [],
    }),
    createColumn({
      id: 'foto_missing', header: 'Foto datum', filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: '1', label: 'Ontbreekt' }],
      getFilterLabel: () => 'Foto ontbreekt',
    }),
    createColumn({
      id: 'vog_datum', header: 'VOG datum', filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'missing', label: 'Ontbreekt' },
        { value: 'older_3', label: 'Ouder dan 3 jaar' },
        { value: 'older_5', label: 'Ouder dan 5 jaar' },
      ],
      getFilterLabel: (val) => ({ missing: 'VOG ontbreekt', older_3: 'VOG ouder dan 3 jaar', older_5: 'VOG ouder dan 5 jaar' }[val] || val),
    }),
  ], [availableBirthYears, filterOptions]);

  // Close bulk dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
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

  const filterValues = {
    include_former: includeFormer,
    lid_tot_future: lidTotFuture,
    birth_year: selectedBirthYear,
    birthday_month: selectedBirthMonth,
    last_modified: lastModifiedFilter,
    vrijwilliger: huidigeVrijwilliger,
    blokkade: financieleBlokkade,
    type_lid: typeLid,
    leeftijdsgroep,
    foto_missing: fotoMissing,
    vog_datum: vogMissing === '1' ? 'missing' : vogOlderThanYears ? `older_${vogOlderThanYears}` : '',
  };
  const hasActiveFilters = Object.values(filterValues).some(Boolean);
  const activeFilterCount = Object.values(filterValues).filter(Boolean).length;

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

  const setFilter = useCallback((colId, value) => {
    switch (colId) {
      case 'include_former': setIncludeFormer(value); break;
      case 'lid_tot_future': setLidTotFuture(value); break;
      case 'birth_year': setSelectedBirthYear(value); break;
      case 'birthday_month': setSelectedBirthMonth(value); break;
      case 'last_modified': setLastModifiedFilter(value); break;
      case 'vrijwilliger': setHuidigeVrijwilliger(value); break;
      case 'blokkade': setFinancieleBlokkade(value); break;
      case 'type_lid': setTypeLid(value); break;
      case 'leeftijdsgroep': setLeeftijdsgroep(value); break;
      case 'foto_missing': setFotoMissing(value); break;
      case 'vog_datum':
        if (value === 'missing') updateSearchParams({ vogMissing: '1', vogOuder: null });
        else if (value?.startsWith('older_')) updateSearchParams({ vogMissing: '', vogOuder: parseInt(value.split('_')[1], 10) });
        else updateSearchParams({ vogMissing: '', vogOuder: null });
        break;
      default: break;
    }
  }, [setIncludeFormer, setLidTotFuture, setSelectedBirthYear, setSelectedBirthMonth, setLastModifiedFilter, setHuidigeVrijwilliger, setFinancieleBlokkade, setTypeLid, setLeeftijdsgroep, setFotoMissing, updateSearchParams]);

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
  }, [selectedBirthYear, selectedBirthMonth, lastModifiedFilter, huidigeVrijwilliger, financieleBlokkade, typeLid, leeftijdsgroep, fotoMissing, vogMissing, vogOlderThanYears, includeFormer, lidTotFuture, page, people]);

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
      updateSearchParams({ order: sortOrder === 'asc' ? 'desc' : 'asc' });
    } else {
      updateSearchParams({ sort: field, order: 'asc' });
    }
  }, [sortField, sortOrder, updateSearchParams]);

  // Handle CSV export
  const handleExportCsv = () => {
    const headers = ['Naam', 'Voornaam', 'Tussenvoegsel', 'Achternaam', 'Email', 'Telefoon', 'Team'];
    const rows = people.map(person => [
      [person.first_name, person.infix, person.last_name].filter(Boolean).join(' '),
      person.first_name || '',
      person.infix || '',
      person.last_name || '',
      getFirstContactByType(person, 'email') || '',
      getFirstPhone(person) || '',
      personTeamMap[person.id] || '',
    ]);
    const csv = buildCsv([headers, ...rows]);
    downloadCsv(csv, `leden-${format(new Date(), 'yyyy-MM-dd')}.csv`);
  };

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Toolbar */}
        <DataTableToolbar
          columns={filterColumns}
          filters={filterValues}
          onFilterChange={setFilter}
          onClearFilters={clearFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onOpenColumnSettings={() => setShowColumnSettings(true)}
          toolbarEnd={
            <button
              onClick={handleExportCsv}
              className="btn-secondary"
              title="Downloaden als CSV"
              disabled={!people.length}
            >
              <Download className="w-4 h-4" />
            </button>
          }
        />

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
