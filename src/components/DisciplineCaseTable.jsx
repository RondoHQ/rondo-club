import { useState, useMemo, Fragment, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown, ChevronUp, ArrowUp, ArrowDown } from 'lucide-react';
import PersonAvatar from '@/components/PersonAvatar';
import { formatCurrency, getPersonName } from '@/utils/formatters';
import { format } from '@/utils/dateFormat';

/**
 * Sortable table header component
 */
function SortableHeader({ label, columnId, sortField, sortOrder, onSort, className = '' }) {
  const isActive = sortField === columnId;
  const SortIcon = sortOrder === 'asc' ? ArrowUp : ArrowDown;

  return (
    <th
      scope="col"
      className={`px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 ${className}`}
      onClick={() => onSort(columnId)}
    >
      <div className={`flex items-center gap-1 ${className.includes('text-right') ? 'justify-end' : className.includes('text-center') ? 'justify-center' : ''}`}>
        {label}
        {isActive && <SortIcon className="w-3 h-3" />}
      </div>
    </th>
  );
}

/**
 * Parse ACF date format to Date object
 * Handles both YYYYMMDD format (e.g., "20241015") and YYYY-MM-DD format (e.g., "2025-08-23")
 * @param {string} dateStr - ACF date string
 * @returns {Date} Parsed date or epoch start if invalid
 */
function parseAcfDate(dateStr) {
  if (!dateStr) return new Date(0);

  // Handle YYYY-MM-DD format (10 characters with dashes)
  if (dateStr.length === 10 && dateStr.includes('-')) {
    const date = new Date(dateStr);
    return isNaN(date.getTime()) ? new Date(0) : date;
  }

  // Handle YYYYMMDD format (8 characters, no dashes)
  if (dateStr.length === 8) {
    const year = dateStr.slice(0, 4);
    const month = parseInt(dateStr.slice(4, 6), 10) - 1;
    const day = dateStr.slice(6, 8);
    return new Date(year, month, day);
  }

  return new Date(0);
}

/**
 * Format ACF date to display format
 * Handles both YYYYMMDD format (e.g., "20260118") and YYYY-MM-DD format (e.g., "2025-08-23")
 * @param {string} dateStr - ACF date string
 * @returns {string} Formatted date or '-' if invalid
 */
function formatAcfDate(dateStr) {
  if (!dateStr) return '-';

  // Handle YYYY-MM-DD format (10 characters with dashes)
  if (dateStr.length === 10 && dateStr.includes('-')) {
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '-';
    return format(date, 'd-M-yyyy');
  }

  // Handle YYYYMMDD format (8 characters, no dashes)
  if (dateStr.length === 8) {
    const date = parseAcfDate(dateStr);
    return format(date, 'd-M-yyyy');
  }

  return '-';
}

/**
 * Reusable table for discipline cases
 * @param {Object} props
 * @param {Array} props.cases - Array of discipline case objects
 * @param {boolean} props.showPersonColumn - Whether to show Person column (false on person tab)
 * @param {Map} props.personMap - Map of person ID to person data (for list view)
 * @param {boolean} props.isLoading - Loading state
 */
export default function DisciplineCaseTable({
  cases,
  showPersonColumn = true,
  personMap = new Map(),
  isLoading = false,
}) {
  const [expandedId, setExpandedId] = useState(null);
  const [sortField, setSortField] = useState('match_date');
  const [sortOrder, setSortOrder] = useState('desc');

  // Handle sort column click
  const handleSort = useCallback((field) => {
    if (sortField === field) {
      setSortOrder((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortOrder('asc');
    }
  }, [sortField]);

  // Sort cases by selected field
  const sortedCases = useMemo(() => {
    if (!cases) return [];
    return [...cases].sort((a, b) => {
      let cmp = 0;
      const acfA = a.acf || {};
      const acfB = b.acf || {};

      switch (sortField) {
        case 'person': {
          const personA = personMap.get(acfA.person);
          const personB = personMap.get(acfB.person);
          const nameA = personA ? getPersonName(personA) : '';
          const nameB = personB ? getPersonName(personB) : '';
          cmp = nameA.localeCompare(nameB);
          break;
        }
        case 'match_date': {
          const dateA = parseAcfDate(acfA.match_date);
          const dateB = parseAcfDate(acfB.match_date);
          cmp = dateA - dateB;
          break;
        }
        case 'sanction':
          cmp = (acfA.sanction_description || '').localeCompare(acfB.sanction_description || '');
          break;
        case 'card': {
          // Sort by card type: red > yellow > none
          const getCardValue = (codes) => {
            if (!codes) return 0;
            return codes.endsWith('-1') ? 1 : 2; // yellow = 1, red = 2
          };
          cmp = getCardValue(acfA.charge_codes) - getCardValue(acfB.charge_codes);
          break;
        }
        case 'charged':
          cmp = (acfA.is_charged ? 1 : 0) - (acfB.is_charged ? 1 : 0);
          break;
        case 'fee':
          cmp = (parseFloat(acfA.administrative_fee) || 0) - (parseFloat(acfB.administrative_fee) || 0);
          break;
        default:
          cmp = 0;
      }

      return sortOrder === 'asc' ? cmp : -cmp;
    });
  }, [cases, sortField, sortOrder, personMap]);

  const toggleExpand = (id) => {
    setExpandedId((prev) => (prev === id ? null : id));
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-32">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  if (!cases || cases.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500 dark:text-gray-400">
        Geen tuchtzaken gevonden.
      </div>
    );
  }

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead className="bg-gray-50 dark:bg-gray-800">
          <tr>
            {showPersonColumn && (
              <SortableHeader
                label="Persoon"
                columnId="person"
                sortField={sortField}
                sortOrder={sortOrder}
                onSort={handleSort}
                className="text-left"
              />
            )}
            <SortableHeader
              label="Wedstrijd"
              columnId="match_date"
              sortField={sortField}
              sortOrder={sortOrder}
              onSort={handleSort}
              className="text-left"
            />
            <SortableHeader
              label="Sanctie"
              columnId="sanction"
              sortField={sortField}
              sortOrder={sortOrder}
              onSort={handleSort}
              className="text-left"
            />
            <SortableHeader
              label="Kaart"
              columnId="card"
              sortField={sortField}
              sortOrder={sortOrder}
              onSort={handleSort}
              className="text-center"
            />
            <SortableHeader
              label="Doorbelast"
              columnId="charged"
              sortField={sortField}
              sortOrder={sortOrder}
              onSort={handleSort}
              className="text-center"
            />
            <SortableHeader
              label="Boete"
              columnId="fee"
              sortField={sortField}
              sortOrder={sortOrder}
              onSort={handleSort}
              className="text-right"
            />
            <th scope="col" className="w-10"></th>
          </tr>
        </thead>
        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
          {sortedCases.map((dc, index) => {
            const isExpanded = expandedId === dc.id;
            const person = personMap.get(dc.acf?.person);
            const acf = dc.acf || {};

            return (
              <Fragment key={dc.id}>
                <tr
                  className={`hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer ${
                    index % 2 === 1 ? 'bg-gray-50 dark:bg-gray-800/50' : ''
                  }`}
                  onClick={() => toggleExpand(dc.id)}
                >
                  {showPersonColumn && (
                    <td className="px-4 py-3 whitespace-nowrap">
                      {person ? (
                        <Link
                          to={`/people/${dc.acf?.person}`}
                          onClick={(e) => e.stopPropagation()}
                          className="flex items-center gap-2 hover:text-electric-cyan"
                        >
                          <PersonAvatar
                            thumbnail={person.thumbnail}
                            name={getPersonName(person)}
                            firstName={person.first_name}
                            size="sm"
                          />
                          <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {getPersonName(person)}
                          </span>
                        </Link>
                      ) : (
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                          -
                        </span>
                      )}
                    </td>
                  )}
                  <td className="px-4 py-3">
                    <div className="text-sm text-gray-900 dark:text-gray-100">
                      {acf.match_description || '-'}
                    </div>
                    <div className="text-xs text-gray-500 dark:text-gray-400">
                      {formatAcfDate(acf.match_date)}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="text-sm text-gray-900 dark:text-gray-100 max-w-xs truncate">
                      {acf.sanction_description || '-'}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <span className="text-lg">
                      {acf.charge_codes ? (
                        acf.charge_codes.endsWith('-1') ? '🟨' : '🟥'
                      ) : (
                        <span className="text-sm text-gray-500 dark:text-gray-400">-</span>
                      )}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <span className="text-sm text-gray-900 dark:text-gray-100">
                      {acf.is_charged ? 'Ja' : 'Nee'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right whitespace-nowrap">
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {formatCurrency(parseFloat(acf.administrative_fee) || 0, 2)}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-center">
                    {isExpanded ? (
                      <ChevronUp className="w-4 h-4 text-gray-400" />
                    ) : (
                      <ChevronDown className="w-4 h-4 text-gray-400" />
                    )}
                  </td>
                </tr>
                {isExpanded && (
                  <tr className="bg-gray-50 dark:bg-gray-700/50">
                    <td colSpan={showPersonColumn ? 7 : 6} className="px-4 py-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                          <h4 className="font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Tenlastelegging
                          </h4>
                          <p className="text-gray-600 dark:text-gray-400">
                            {acf.charge_description ||
                              'Geen tenlastelegging beschikbaar'}
                          </p>
                          {acf.charge_codes && (
                            <p className="text-xs text-gray-500 dark:text-gray-500 mt-1">
                              Code: {acf.charge_codes}
                            </p>
                          )}
                        </div>
                        <div>
                          <h4 className="font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Sanctie (volledig)
                          </h4>
                          <p className="text-gray-600 dark:text-gray-400">
                            {acf.sanction_description || 'Geen sanctie beschikbaar'}
                          </p>
                        </div>
                        <div>
                          <h4 className="font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Team
                          </h4>
                          <p className="text-gray-600 dark:text-gray-400">
                            {acf.team_name || '-'}
                          </p>
                        </div>
                        <div>
                          <h4 className="font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Details
                          </h4>
                          <p className="text-gray-600 dark:text-gray-400">
                            Dossier: {acf.dossier_id || '-'}
                          </p>
                          <p className="text-gray-600 dark:text-gray-400">
                            Verwerkingsdatum: {formatAcfDate(acf.processing_date)}
                          </p>
                          <p className="text-gray-600 dark:text-gray-400">
                            Doorbelast: {acf.is_charged ? 'Ja' : 'Nee'}
                          </p>
                        </div>
                      </div>
                    </td>
                  </tr>
                )}
              </Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
