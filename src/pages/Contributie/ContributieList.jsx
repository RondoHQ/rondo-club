import { useState, useCallback, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUp, ArrowDown, RefreshCw, Coins, Filter, AlertTriangle, X } from 'lucide-react';
import { useFeeList } from '@/hooks/useFees';
import { useQueryClient } from '@tanstack/react-query';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';

// Format currency in euros
function formatCurrency(amount) {
  return new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

// Format percentage
function formatPercentage(rate) {
  return `${Math.round(rate * 100)}%`;
}

// Category badge colors
const categoryColors = {
  mini: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  pupil: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  junior: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  senior: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  recreant: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
  donateur: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
};

// Category labels
const categoryLabels = {
  mini: 'Mini',
  pupil: 'Pupil',
  junior: 'Junior',
  senior: 'Senior',
  recreant: 'Recreant',
  donateur: 'Donateur',
};

// Sortable header component
function SortableHeader({ label, columnId, sortField, sortOrder, onSort, className = '' }) {
  const isActive = sortField === columnId;
  const nextOrder = isActive && sortOrder === 'asc' ? 'desc' : 'asc';

  return (
    <th
      scope="col"
      className={`px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 ${className}`}
      onClick={() => onSort(columnId, nextOrder)}
    >
      <div className="flex items-center gap-1">
        {label}
        {isActive && (
          sortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </div>
    </th>
  );
}

// Fee row component
function FeeRow({ member, isOdd }) {
  const hasDiscount = member.family_discount_rate > 0;
  const hasProrata = member.prorata_percentage < 1.0;
  const hasMismatch = member.has_mismatch;

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    } ${hasProrata ? 'bg-amber-50/50 dark:bg-amber-900/10' : ''}`}>
      {/* Name with mismatch indicator */}
      <td className="px-4 py-3 whitespace-nowrap">
        <div className="flex items-center gap-2">
          <Link
            to={`/people/${member.id}`}
            className="text-sm font-medium text-gray-900 dark:text-gray-50 hover:text-accent-600 dark:hover:text-accent-400"
          >
            {member.name}
          </Link>
          {hasMismatch && (
            <span
              className="text-amber-500 dark:text-amber-400"
              title="Mogelijk adres afwijking: zelfde achternaam, ander adres"
            >
              <AlertTriangle className="w-4 h-4" />
            </span>
          )}
        </div>
      </td>

      {/* Category */}
      <td className="px-4 py-3 whitespace-nowrap">
        <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${categoryColors[member.category] || 'bg-gray-100 text-gray-700'}`}>
          {categoryLabels[member.category] || member.category}
        </span>
      </td>

      {/* Age Group */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {member.leeftijdsgroep || '-'}
      </td>

      {/* Base Fee */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
        {formatCurrency(member.base_fee)}
      </td>

      {/* Family Discount */}
      <td className="px-4 py-3 text-sm text-right">
        {hasDiscount ? (
          <span className="text-green-600 dark:text-green-400">
            -{formatPercentage(member.family_discount_rate)}
          </span>
        ) : (
          <span className="text-gray-400 dark:text-gray-500">-</span>
        )}
      </td>

      {/* Pro-rata */}
      <td className="px-4 py-3 text-sm text-right">
        {hasProrata ? (
          <span className="text-amber-600 dark:text-amber-400">
            {formatPercentage(member.prorata_percentage)}
          </span>
        ) : (
          <span className="text-gray-400 dark:text-gray-500">100%</span>
        )}
      </td>

      {/* Final Fee */}
      <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-50 text-right">
        {formatCurrency(member.final_fee)}
      </td>
    </tr>
  );
}

// Empty state component
function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-3 bg-gray-100 rounded-full dark:bg-gray-700">
          <Coins className="w-8 h-8 text-gray-400 dark:text-gray-500" />
        </div>
      </div>
      <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
        Geen leden gevonden
      </h3>
      <p className="text-sm text-gray-600 dark:text-gray-400">
        Er zijn geen leden met een berekenbare contributie.
      </p>
    </div>
  );
}

export default function ContributieList() {
  const [sortField, setSortField] = useState('category');
  const [sortOrder, setSortOrder] = useState('asc');
  const [addressFilter, setAddressFilter] = useState('all');
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const filterRef = useRef(null);
  const queryClient = useQueryClient();

  // Fetch fee data
  const { data, isLoading, error } = useFeeList({ filter: addressFilter });

  // Click outside handler for filter dropdown
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (filterRef.current && !filterRef.current.contains(event.target)) {
        setIsFilterOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Handle sort
  const handleSort = useCallback((field, order) => {
    setSortField(field);
    setSortOrder(order);
  }, []);

  // Handle refresh
  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['fees'] });
  };

  // Sort members client-side
  const sortedMembers = data?.members ? [...data.members].sort((a, b) => {
    let cmp = 0;
    switch (sortField) {
      case 'name':
        cmp = a.name.localeCompare(b.name);
        break;
      case 'category': {
        const catOrder = { mini: 1, pupil: 2, junior: 3, senior: 4, recreant: 5, donateur: 6 };
        cmp = (catOrder[a.category] || 99) - (catOrder[b.category] || 99);
        break;
      }
      case 'base_fee':
        cmp = a.base_fee - b.base_fee;
        break;
      case 'final_fee':
        cmp = a.final_fee - b.final_fee;
        break;
      default:
        cmp = 0;
    }
    return sortOrder === 'asc' ? cmp : -cmp;
  }) : [];

  // Calculate totals
  const totals = sortedMembers.reduce(
    (acc, m) => ({
      baseFee: acc.baseFee + m.base_fee,
      finalFee: acc.finalFee + m.final_fee,
    }),
    { baseFee: 0, finalFee: 0 }
  );

  // Loading state
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400 mb-4">
          Contributie kon niet worden geladen.
        </p>
        <button
          onClick={handleRefresh}
          className="btn-secondary inline-flex items-center gap-2"
        >
          <RefreshCw className="w-4 h-4" />
          Opnieuw proberen
        </button>
      </div>
    );
  }

  // Empty state
  if (!sortedMembers.length) {
    return (
      <PullToRefreshWrapper onRefresh={handleRefresh}>
        <div className="card">
          <EmptyState />
        </div>
      </PullToRefreshWrapper>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Filter Section */}
        <div className="flex flex-wrap items-center gap-2">
          {/* Filter Dropdown */}
          <div className="relative" ref={filterRef}>
            <button
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${addressFilter !== 'all' ? 'bg-accent-50 text-accent-700 border-accent-200 dark:bg-accent-900/30 dark:text-accent-300 dark:border-accent-700' : ''}`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {addressFilter !== 'all' && (
                <span className="ml-2 px-1.5 py-0.5 bg-accent-600 text-white text-xs rounded-full">1</span>
              )}
            </button>

            {isFilterOpen && (
              <div className="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
                <div className="p-4 space-y-2">
                  <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                    Adres filter
                  </h3>
                  <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-2 rounded">
                    <input
                      type="radio"
                      name="addressFilter"
                      checked={addressFilter === 'all'}
                      onChange={() => { setAddressFilter('all'); setIsFilterOpen(false); }}
                      className="mr-3"
                    />
                    <span className="text-sm text-gray-700 dark:text-gray-200">
                      Alle leden ({data?.total || 0})
                    </span>
                  </label>
                  <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-2 rounded">
                    <input
                      type="radio"
                      name="addressFilter"
                      checked={addressFilter === 'mismatches'}
                      onChange={() => { setAddressFilter('mismatches'); setIsFilterOpen(false); }}
                      className="mr-3"
                    />
                    <span className="text-sm text-gray-700 dark:text-gray-200">
                      Adres afwijkingen ({data?.mismatch_count || 0})
                    </span>
                  </label>
                </div>
              </div>
            )}
          </div>

          {/* Active Filter Chip */}
          {addressFilter !== 'all' && (
            <span className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-700 rounded">
              <AlertTriangle className="w-3 h-3" />
              Adres afwijkingen
              <button
                onClick={() => setAddressFilter('all')}
                className="hover:text-amber-900 dark:hover:text-amber-100"
              >
                <X className="w-3 h-3" />
              </button>
            </span>
          )}
        </div>

        {/* Season indicator */}
        <div className="flex items-center justify-between">
          <div className="text-sm text-gray-500 dark:text-gray-400">
            Seizoen: <span className="font-medium text-gray-900 dark:text-gray-100">{data?.season}</span>
            <span className="ml-4">{sortedMembers.length} leden</span>
          </div>
          <div className="text-sm text-gray-500 dark:text-gray-400">
            Totaal: <span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(totals.finalFee)}</span>
          </div>
        </div>

        {/* Fee list table */}
        <div className="card overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <SortableHeader
                  label="Naam"
                  columnId="name"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                />
                <SortableHeader
                  label="Categorie"
                  columnId="category"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                />
                <th
                  scope="col"
                  className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800"
                >
                  Leeftijdsgroep
                </th>
                <SortableHeader
                  label="Basis"
                  columnId="base_fee"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                  className="text-right"
                />
                <th
                  scope="col"
                  className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800"
                >
                  Gezin
                </th>
                <th
                  scope="col"
                  className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800"
                >
                  Pro-rata
                </th>
                <SortableHeader
                  label="Bedrag"
                  columnId="final_fee"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                  className="text-right"
                />
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {sortedMembers.map((member, index) => (
                <FeeRow
                  key={member.id}
                  member={member}
                  isOdd={index % 2 === 1}
                />
              ))}
            </tbody>
            <tfoot className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <td colSpan="3" className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                  Totaal
                </td>
                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                  {formatCurrency(totals.baseFee)}
                </td>
                <td colSpan="2"></td>
                <td className="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                  {formatCurrency(totals.finalFee)}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </PullToRefreshWrapper>
  );
}
