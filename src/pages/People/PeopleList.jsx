import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Filter, X, Check, ArrowUp, ArrowDown, Square, CheckSquare, MinusSquare, ChevronDown, Building2, Download, Info, Loader2, Plus } from 'lucide-react';
import { DataTableToolbar, createColumn, FILTER_TYPES } from '@/components/DataTable';
import { useFilteredPeople, useFilterOptions, useBulkUpdatePeople, useCreatePerson, fetchAllFilteredPeople } from '@/hooks/usePeople';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { buildCsv, downloadCsv } from '@/utils/csvExport';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';
import { getTeamName, formatPhoneForTel, formatPhoneForDisplay } from '@/utils/formatters';
import { format, parseYmd, isValid } from '@/utils/dateFormat';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import Pagination from '@/components/Pagination';
import { useListPreferences } from '@/hooks/useListPreferences';
import { useColumnResize } from '@/hooks/useColumnResize';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import ColumnSettingsModal from './ColumnSettingsModal';
import PersonEditModal from '@/components/PersonEditModal';
import FloatingHorizontalScrollbar from '@/components/FloatingHorizontalScrollbar';

// Helper function to get first email from fixed fields
function getFirstEmail(person) {
  return person.acf?.email_1 || person.acf?.email_2 || null;
}

// Helper function to get first phone from fixed fields
function getFirstPhone(person) {
  return person.acf?.mobile_1 || person.acf?.telephone_1 || person.acf?.mobile_2 || person.acf?.telephone_2 || null;
}

// Primary address is the first row of the ACF `addresses` repeater.
function getPrimaryAddress(person) {
  const addresses = person.acf?.addresses;
  return Array.isArray(addresses) && addresses.length > 0 ? addresses[0] : null;
}

function formatStreetLine(address) {
  if (!address) return '';
  const street = address.street_name || '';
  const number = [address.house_number, address.house_number_addition].filter(Boolean).join('');
  return [street, number].filter(Boolean).join(' ').trim();
}

function formatBirthdateDisplay(birthdate) {
  if (!birthdate) return '-';
  const parsed = parseYmd(birthdate);
  if (!isValid(parsed)) return '-';
  return format(parsed, 'd MMM yyyy');
}

function getMembershipTypeLabel(person) {
  const acf = person.acf || {};

  if (acf.person_type === 'contact') return 'Contact';

  const sportlinkType = String(acf['type-lid'] || '').trim().toLowerCase();
  if (sportlinkType.includes('verenigingslid')) return 'Verenigingslid';
  if (sportlinkType.includes('bondslid')) return 'Bondslid';
  if (sportlinkType.includes('ouder')) return 'Ouder';

  const isParent = acf.isparent === true || acf.isparent === 1 || acf.isparent === '1';
  if (isParent || !acf['knvb-id']) return 'Ouder';

  return 'Bondslid';
}

// Helper function to get current team ID from person's work history
function getCurrentTeamId(person) {
  if (person?.team_id) return person.team_id;

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
  'lid-tot': 'custom_lid-tot',
  'vrijwilliger-sinds': 'custom_vrijwilliger-sinds',
  'datum-foto': 'custom_datum-foto',
  'datum-vog': 'custom_datum-vog',
  'isparent': 'custom_isparent',
  'huidig-vrijwilliger': 'custom_huidig-vrijwilliger',
  'financiele-blokkade': 'custom_financiele-blokkade',
  'freescout-id': 'custom_freescout-id',
};

const UNSORTABLE_CORE_COLUMNS = new Set(['email', 'phone', 'address', 'postal_code', 'city', 'country']);
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
            name={person.name || person.company_name}
            firstName={person.first_name || person.company_name}
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
            {person.name || [person.first_name, person.infix, person.last_name].filter(Boolean).join(' ') || person.company_name}
            {person.is_deceased && <span className="ml-1 text-gray-500 dark:text-gray-400">&#8224;</span>}
          </span>
          {person.former_member && (
            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
              Oud-lid
            </span>
          )}
          {person.acf?.person_type === 'contact' && (
            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300">
              Contact
            </span>
          )}
          {person.acf?.wacht_op_overschrijving && (
            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
              Wacht op overschrijving
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

        if (colId === 'type-lid') {
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {getMembershipTypeLabel(person)}
            </td>
          );
        }

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
          const email = getFirstEmail(person);
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
                  {formatPhoneForDisplay(phone)}
                </a>
              ) : '-'}
            </td>
          );
        }

        if (colId === 'address' || colId === 'postal_code' || colId === 'city' || colId === 'country') {
          const address = getPrimaryAddress(person);
          let value = '';
          if (address) {
            if (colId === 'address') value = formatStreetLine(address);
            else value = address[colId] || '';
          }
          return (
            <td
              key={colId}
              className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
              style={style}
            >
              {value || '-'}
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
  const scrollContainerRef = useRef(null);

  return (
    <>
      <div
        ref={scrollContainerRef}
        className="card !overflow-x-auto"
        data-horizontal-scroll="true"
      >
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
      <FloatingHorizontalScrollbar targetRef={scrollContainerRef} />
    </>
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
  const { data: currentUser } = useCurrentUser();
  const navigate = useNavigate();

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
  const personType = searchParams.get('personType') || '';
  const leeftijdsgroep = searchParams.get('leeftijdsgroep') || '';
  const fotoMissing = searchParams.get('fotoMissing') || '';
  const vogMissing = searchParams.get('vogMissing') || '';
  const vogOlderThanYears = searchParams.get('vogOuder') ? parseInt(searchParams.get('vogOuder'), 10) : null;
  const includeFormer = searchParams.get('oudLeden') || '';
  const lidTotFuture = searchParams.get('lidTot') || '';
  const lidTotSeason = searchParams.get('lidTotSeizoen') || '';
  const lidSindsSeason = searchParams.get('lidSindsSeizoen') || '';
  const spelactiviteitNoTeam = searchParams.get('spelactiviteitZonderTeam') || '';
  const spelendLid = searchParams.get('spelendLid') || '';
  const wachtOverschrijving = searchParams.get('wachtOverschrijving') || '';

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

  const setPersonType = useCallback((value) => {
    updateSearchParams({ personType: value });
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

  const setLidTotSeason = useCallback((value) => {
    updateSearchParams({ lidTotSeizoen: value });
  }, [updateSearchParams]);

  const setLidSindsSeason = useCallback((value) => {
    updateSearchParams({ lidSindsSeizoen: value });
  }, [updateSearchParams]);

  const setSpelactiviteitNoTeam = useCallback((value) => {
    updateSearchParams({ spelactiviteitZonderTeam: value });
  }, [updateSearchParams]);

  const setSpelendLid = useCallback((value) => {
    updateSearchParams({ spelendLid: value });
  }, [updateSearchParams]);

  const setWachtOverschrijving = useCallback((value) => {
    updateSearchParams({ wachtOverschrijving: value });
  }, [updateSearchParams]);

  // Local UI state (not persisted in URL)
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkOrganizationModal, setShowBulkOrganizationModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const [showColumnSettings, setShowColumnSettings] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [showContactModal, setShowContactModal] = useState(false);
  const bulkDropdownRef = useRef(null);
  const queryClient = useQueryClient();
  const createPersonMutation = useCreatePerson({
    onSuccess: (createdPerson) => {
      setShowContactModal(false);
      navigate(`/people/${createdPerson.id}`);
    },
  });

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
    personType,
    leeftijdsgroep,
    fotoMissing,
    vogMissing,
    vogOlderThanYears,
    includeFormer: includeFormer || null,
    lidTotFuture: lidTotFuture || null,
    lidTotSeason: lidTotSeason || null,
    lidSindsSeason: lidSindsSeason || null,
    spelactiviteitNoTeam: spelactiviteitNoTeam || null,
    spelendLid: spelendLid || null,
    wachtOverschrijving: wachtOverschrijving || null,
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

  // Get visible columns (excluding 'name' which is always shown in a fixed position).
  // When a season-based membership filter is active, force the relevant date columns
  // to appear first so the matching reason is visible without fiddling with Column
  // Settings. User's stored preferences are unchanged.
  //   lid_tot_season   → lid-sinds, lid-tot
  //   lid_sinds_season → lid-sinds, type-lid
  const visibleColumns = useMemo(() => {
    let forced = [];
    if (lidTotSeason === '1') forced = ['lid-sinds', 'lid-tot'];
    else if (lidSindsSeason === '1') forced = ['lid-sinds', 'type-lid'];

    if (!preferences?.visible_columns || !preferences?.column_order) {
      // Fallback to default columns if preferences not loaded
      return forced.length > 0 ? [...forced, 'team'] : ['team'];
    }

    // Filter column_order to only visible columns, excluding 'name'
    const visibleSet = new Set(preferences.visible_columns);
    const cols = preferences.column_order.filter(colId =>
      colId !== 'name' && visibleSet.has(colId)
    );

    if (forced.length > 0) {
      return [...forced, ...cols.filter(colId => !forced.includes(colId))];
    }

    return cols;
  }, [preferences?.visible_columns, preferences?.column_order, lidTotSeason, lidSindsSeason]);

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
    // Lidmaatschap — who counts as a member right now / cancellations
    createColumn({ id: 'include_former', header: 'Toon oud-leden', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => '', filterSection: 'Lidmaatschap' }),
    createColumn({ id: 'lid_tot_future', header: 'Afmelding in de toekomst', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => '', filterSection: 'Lidmaatschap' }),
    createColumn({ id: 'lid_tot_season', header: 'Afgemeld dit seizoen', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => 'Afgemeld dit seizoen', filterSection: 'Lidmaatschap' }),
    createColumn({ id: 'lid_sinds_season', header: 'Nieuw lid dit seizoen', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => 'Nieuw lid dit seizoen', filterSection: 'Lidmaatschap' }),
    createColumn({ id: 'spelactiviteit_no_team', header: 'Spelactiviteit zonder team', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => '', filterSection: 'Lidmaatschap' }),
    createColumn({
      id: 'spelend_lid', header: 'Spelend lid', filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: '1', label: 'Ja' }, { value: '0', label: 'Nee' }],
      getFilterLabel: (val) => `Spelend lid: ${val === '1' ? 'Ja' : 'Nee'}`,
      filterSection: 'Lidmaatschap',
    }),
    createColumn({ id: 'wacht_overschrijving', header: 'Wacht op overschrijving', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => 'Wacht op overschrijving', filterSection: 'Lidmaatschap' }),

    createColumn({
      id: 'person_type', header: 'Persoonstype', filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'member', label: 'Leden en ouders' },
        { value: 'contact', label: 'Contacten' },
      ],
      getFilterLabel: (val) => val === 'contact' ? 'Persoonstype: Contact' : 'Persoonstype: Lid / ouder',
      filterSection: 'Persoon',
    }),

    // Persoon — birth/age/category attributes
    createColumn({
      id: 'birth_year', header: 'Geboortejaar', filterType: FILTER_TYPES.SELECT,
      filterOptions: availableBirthYears.map(y => ({ value: String(y), label: String(y) })),
      getFilterLabel: (val) => `Geboren ${val}`,
      filterSection: 'Persoon',
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
      filterSection: 'Persoon',
    }),
    createColumn({
      id: 'type_lid', header: 'Type', filterType: FILTER_TYPES.SELECT,
      filterOptions: filterOptions?.member_types?.map(opt => ({ value: opt.value, label: `${opt.value} (${opt.count})` })) || [],
      getFilterLabel: (val) => `Type: ${val}`,
      filterSection: 'Persoon',
    }),
    createColumn({
      id: 'leeftijdsgroep', header: 'Leeftijdsgroep', filterType: FILTER_TYPES.SELECT,
      filterOptions: filterOptions?.age_groups?.map(opt => ({ value: opt.value, label: `${opt.value} (${opt.count})` })) || [],
      filterSection: 'Persoon',
    }),
    createColumn({
      id: 'foto_missing', header: 'Foto datum', filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: '1', label: 'Ontbreekt' }],
      getFilterLabel: () => 'Foto ontbreekt',
      filterSection: 'Persoon',
    }),

    // Vrijwilliger & VOG
    createColumn({
      id: 'vrijwilliger', header: 'Huidig vrijwilliger', filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: '1', label: 'Ja' }, { value: '0', label: 'Nee' }],
      getFilterLabel: (val) => `Vrijwilliger: ${val === '1' ? 'Ja' : 'Nee'}`,
      filterSection: 'Vrijwilliger & VOG',
    }),
    createColumn({
      id: 'vog_datum', header: 'VOG datum', filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'missing', label: 'Ontbreekt' },
        { value: 'older_3', label: 'Ouder dan 3 jaar' },
        { value: 'older_5', label: 'Ouder dan 5 jaar' },
      ],
      getFilterLabel: (val) => ({ missing: 'VOG ontbreekt', older_3: 'VOG ouder dan 3 jaar', older_5: 'VOG ouder dan 5 jaar' }[val] || val),
      filterSection: 'Vrijwilliger & VOG',
    }),

    // Administratief
    createColumn({
      id: 'blokkade', header: 'Financiële blokkade', filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: '1', label: 'Ja' }, { value: '0', label: 'Nee' }],
      getFilterLabel: (val) => `Blokkade: ${val === '1' ? 'Ja' : 'Nee'}`,
      filterSection: 'Administratief',
    }),
    createColumn({
      id: 'last_modified', header: 'Gewijzigd', filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: '7', label: 'Laatste 7 dagen' }, { value: '30', label: 'Laatste 30 dagen' },
        { value: '90', label: 'Laatste 90 dagen' }, { value: '365', label: 'Laatste jaar' },
      ],
      getFilterLabel: (val) => ({ '7': 'Laatste 7 dagen', '30': 'Laatste 30 dagen', '90': 'Laatste 90 dagen', '365': 'Laatste jaar' }[val] || val),
      filterSection: 'Administratief',
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
    lid_tot_season: lidTotSeason,
    lid_sinds_season: lidSindsSeason,
    spelactiviteit_no_team: spelactiviteitNoTeam,
    spelend_lid: spelendLid,
    wacht_overschrijving: wachtOverschrijving,
    birth_year: selectedBirthYear,
    birthday_month: selectedBirthMonth,
    last_modified: lastModifiedFilter,
    vrijwilliger: huidigeVrijwilliger,
    blokkade: financieleBlokkade,
    type_lid: typeLid,
    person_type: personType,
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
      case 'lid_tot_season': setLidTotSeason(value); break;
      case 'lid_sinds_season': setLidSindsSeason(value); break;
      case 'spelactiviteit_no_team': setSpelactiviteitNoTeam(value); break;
      case 'spelend_lid': setSpelendLid(value); break;
      case 'wacht_overschrijving': setWachtOverschrijving(value); break;
      case 'birth_year': setSelectedBirthYear(value); break;
      case 'birthday_month': setSelectedBirthMonth(value); break;
      case 'last_modified': setLastModifiedFilter(value); break;
      case 'vrijwilliger': setHuidigeVrijwilliger(value); break;
      case 'blokkade': setFinancieleBlokkade(value); break;
      case 'type_lid': setTypeLid(value); break;
      case 'person_type': setPersonType(value); break;
      case 'leeftijdsgroep': setLeeftijdsgroep(value); break;
      case 'foto_missing': setFotoMissing(value); break;
      case 'vog_datum':
        if (value === 'missing') updateSearchParams({ vogMissing: '1', vogOuder: null });
        else if (value?.startsWith('older_')) updateSearchParams({ vogMissing: '', vogOuder: parseInt(value.split('_')[1], 10) });
        else updateSearchParams({ vogMissing: '', vogOuder: null });
        break;
      default: break;
    }
  }, [setIncludeFormer, setLidTotFuture, setLidTotSeason, setLidSindsSeason, setSpelactiviteitNoTeam, setSpelendLid, setWachtOverschrijving, setSelectedBirthYear, setSelectedBirthMonth, setLastModifiedFilter, setHuidigeVrijwilliger, setFinancieleBlokkade, setTypeLid, setPersonType, setLeeftijdsgroep, setFotoMissing, updateSearchParams]);

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
  }, [selectedBirthYear, selectedBirthMonth, lastModifiedFilter, huidigeVrijwilliger, financieleBlokkade, typeLid, personType, leeftijdsgroep, fotoMissing, vogMissing, vogOlderThanYears, includeFormer, lidTotFuture, lidTotSeason, lidSindsSeason, spelactiviteitNoTeam, spelendLid, wachtOverschrijving, page, people]);

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

  // Handle CSV export — fetches every matching person across all pages
  // (list view is capped at 100 per page; export must include the full set).
  const handleExportCsv = async () => {
    if (isExporting) return;
    setIsExporting(true);
    try {
      const allPeople = await fetchAllFilteredPeople({
        perPage: 100,
        ownership: 'all',
        modifiedDays: lastModifiedFilter ? parseInt(lastModifiedFilter, 10) : null,
        birthYearFrom: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
        birthYearTo: selectedBirthYear ? parseInt(selectedBirthYear, 10) : null,
        birthMonth: selectedBirthMonth ? parseInt(selectedBirthMonth, 10) : null,
        orderby: resolvedOrderBy,
        order: sortOrder,
        huidigeVrijwilliger,
        financieleBlokkade,
        typeLid,
        personType,
        leeftijdsgroep,
        fotoMissing,
        vogMissing,
        vogOlderThanYears,
        includeFormer: includeFormer || null,
        lidTotFuture: lidTotFuture || null,
        lidTotSeason: lidTotSeason || null,
        lidSindsSeason: lidSindsSeason || null,
        spelactiviteitNoTeam: spelactiviteitNoTeam || null,
        spelendLid: spelendLid || null,
        wachtOverschrijving: wachtOverschrijving || null,
      });

      const allTeamIds = [...new Set(allPeople.map(getCurrentTeamId).filter(Boolean))];
      const exportTeamMap = {};
      for (let i = 0; i < allTeamIds.length; i += 100) {
        const chunk = allTeamIds.slice(i, i + 100);
        const response = await wpApi.getTeams({ per_page: 100, include: chunk.join(',') });
        (response.data || []).forEach(team => {
          exportTeamMap[team.id] = getTeamName(team);
        });
      }

      const headers = ['Naam', 'Bedrijfsnaam', 'Voornaam', 'Tussenvoegsel', 'Achternaam', 'Email', 'Telefoon', 'Team', 'Adres', 'Postcode', 'Plaats', 'Land'];
      const rows = allPeople.map(person => {
        const teamId = getCurrentTeamId(person);
        const address = getPrimaryAddress(person);
        return [
          person.name || [person.first_name, person.infix, person.last_name].filter(Boolean).join(' ') || person.company_name || '',
          person.acf?.company_name || person.company_name || '',
          person.first_name || '',
          person.infix || '',
          person.last_name || '',
          getFirstEmail(person) || '',
          getFirstPhone(person) || '',
          (teamId && exportTeamMap[teamId]) || '',
          formatStreetLine(address),
          address?.postal_code || '',
          address?.city || '',
          address?.country || '',
        ];
      });
      const csv = buildCsv([headers, ...rows]);
      downloadCsv(csv, `leden-${format(new Date(), 'yyyy-MM-dd')}.csv`);
    } finally {
      setIsExporting(false);
    }
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
            <div className="flex items-center gap-2">
              {currentUser?.can_edit_people && (
                <button onClick={() => setShowContactModal(true)} className="btn-primary">
                  <Plus className="w-4 h-4 md:mr-2" />
                  <span className="hidden md:inline">Contact toevoegen</span>
                </button>
              )}
              <button
                onClick={handleExportCsv}
                className="btn-tertiary"
                title={isExporting ? 'Bezig met exporteren…' : 'Downloaden als CSV'}
                disabled={isExporting || totalPeople === 0}
              >
                {isExporting
                  ? <Loader2 className="w-4 h-4 animate-spin" />
                  : <Download className="w-4 h-4" />}
              </button>
            </div>
          }
        />

      {/* Age-group restriction info banner */}
      {Array.isArray(currentUser?.permitted_age_groups) && (
        <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-sm text-blue-700 dark:text-blue-300 flex items-center gap-2">
          <Info className="w-4 h-4 shrink-0" />
          <span>Je ziet alleen leden uit de leeftijdsgroepen: {currentUser.permitted_age_groups.join(', ')}.</span>
        </div>
      )}

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
          <button onClick={clearFilters} className="btn-tertiary">
            Filters wissen
          </button>
        </div>
      )}

      {/* Column Settings Modal */}
      <ColumnSettingsModal
        isOpen={showColumnSettings}
        onClose={() => setShowColumnSettings(false)}
      />

      <PersonEditModal
        isOpen={showContactModal}
        onClose={() => setShowContactModal(false)}
        onSubmit={(data) => createPersonMutation.mutate(data)}
        isLoading={createPersonMutation.isPending}
        initialPersonType="contact"
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
