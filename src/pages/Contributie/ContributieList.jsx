import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUp, ArrowDown, RefreshCw, Coins, FileSpreadsheet, Filter, TrendingUp } from 'lucide-react';
import { useFeeList } from '@/hooks/useFees';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';

// Format currency in euros
function formatCurrency(amount, decimals = 0) {
  if (amount === null || amount === undefined) return '-';
  return new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(amount);
}

// Format percentage
function formatPercentage(rate) {
  return `${Math.round(rate * 100)}%`;
}

// Get next season label from current season
function getNextSeasonLabel(currentSeason) {
  const startYear = parseInt(currentSeason.substring(0, 4));
  return `${startYear + 1}-${startYear + 2}`;
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
function FeeRow({ member, isOdd, isForecast }) {
  const hasDiscount = member.family_discount_rate > 0;
  const hasProrata = member.prorata_percentage < 1.0;

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    } ${hasProrata ? 'bg-amber-50/50 dark:bg-amber-900/10' : ''}`}>
      {/* First Name */}
      <td className="px-4 py-3 whitespace-nowrap">
        <Link
          to={`/people/${member.id}`}
          className="text-sm font-medium text-gray-900 dark:text-gray-50 hover:text-accent-600 dark:hover:text-accent-400"
        >
          {member.first_name}
        </Link>
      </td>

      {/* Last Name */}
      <td className="px-4 py-3 whitespace-nowrap">
        <Link
          to={`/people/${member.id}`}
          className="text-sm text-gray-700 dark:text-gray-300 hover:text-accent-600 dark:hover:text-accent-400"
        >
          {member.last_name}
        </Link>
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
        {formatCurrency(member.base_fee, 2)}
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
        {formatCurrency(member.final_fee, 2)}
      </td>

      {/* Nikki Total - Only in current season mode */}
      {!isForecast && (
        <>
          <td className="px-4 py-3 text-sm text-right">
            {member.nikki_total !== null ? (
              <span className="text-gray-700 dark:text-gray-300">
                {formatCurrency(member.nikki_total, 2)}
              </span>
            ) : (
              <span className="text-gray-400 dark:text-gray-500">-</span>
            )}
          </td>

          {/* Saldo (Outstanding) */}
          <td className="px-4 py-3 text-sm text-right">
            {member.nikki_saldo !== null ? (
              <span className="text-gray-700 dark:text-gray-300">
                {formatCurrency(member.nikki_saldo, 2)}
              </span>
            ) : (
              <span className="text-gray-400 dark:text-gray-500">-</span>
            )}
          </td>
        </>
      )}
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
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [showNoNikkiOnly, setShowNoNikkiOnly] = useState(false);
  const [showMismatchOnly, setShowMismatchOnly] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [isForecast, setIsForecast] = useState(false);
  const queryClient = useQueryClient();

  // Fetch fee data
  const { data, isLoading, error } = useFeeList(
    isForecast ? { forecast: true } : {}
  );

  // Check Google Sheets connection status
  const { data: sheetsStatus } = useQuery({
    queryKey: ['google-sheets-status'],
    queryFn: async () => {
      const response = await prmApi.getSheetsStatus();
      return response.data;
    },
  });

  // Handle sort
  const handleSort = useCallback((field, order) => {
    setSortField(field);
    setSortOrder(order);
  }, []);

  // Reset sort field if switching to forecast while sorting by nikki columns
  useEffect(() => {
    if (isForecast && (sortField === 'nikki_total' || sortField === 'nikki_saldo')) {
      setSortField('last_name');
    }
  }, [isForecast, sortField]);

  // Handle refresh
  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['fees'] });
  };

  // Handle export to Google Sheets
  const handleExportToSheets = async () => {
    if (isExporting) return;
    setIsExporting(true);

    // Open window immediately to avoid popup blocker
    const newWindow = window.open('about:blank', '_blank');

    try {
      const response = await prmApi.exportFeesToSheets({
        sort_field: sortField,
        sort_order: sortOrder,
      });

      if (response.data.spreadsheet_url && newWindow) {
        newWindow.location.href = response.data.spreadsheet_url;
      }
    } catch (error) {
      console.error('Export error:', error);
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

  // Filter and sort members client-side
  const filteredMembers = data?.members
    ? data.members.filter(m => {
        if (showNoNikkiOnly) return m.nikki_total === null;
        if (showMismatchOnly) return m.nikki_total !== null && Math.abs(m.nikki_total - m.final_fee) >= 1;
        return true;
      })
    : [];

  const sortedMembers = filteredMembers.length ? [...filteredMembers].sort((a, b) => {
    let cmp = 0;
    switch (sortField) {
      case 'last_name':
        cmp = (a.last_name || '').localeCompare(b.last_name || '');
        // Secondary sort by first_name
        if (cmp === 0) {
          cmp = (a.first_name || '').localeCompare(b.first_name || '');
        }
        break;
      case 'first_name':
        cmp = (a.first_name || '').localeCompare(b.first_name || '');
        // Secondary sort by last_name
        if (cmp === 0) {
          cmp = (a.last_name || '').localeCompare(b.last_name || '');
        }
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
      case 'leeftijdsgroep':
        cmp = (a.leeftijdsgroep || '').localeCompare(b.leeftijdsgroep || '');
        break;
      case 'family_discount_rate':
        cmp = a.family_discount_rate - b.family_discount_rate;
        break;
      case 'prorata_percentage':
        cmp = a.prorata_percentage - b.prorata_percentage;
        break;
      case 'nikki_total':
        cmp = (a.nikki_total || 0) - (b.nikki_total || 0);
        break;
      case 'nikki_saldo':
        cmp = (a.nikki_saldo || 0) - (b.nikki_saldo || 0);
        break;
      default:
        cmp = 0;
    }
    return sortOrder === 'asc' ? cmp : -cmp;
  }) : []

  // Count members without Nikki data
  const noNikkiCount = data?.members?.filter(m => m.nikki_total === null).length || 0;

  // Count members with mismatch between Nikki and calculated fee (difference >= 1 euro, excluding those without Nikki data)
  const mismatchCount = data?.members?.filter(m => m.nikki_total !== null && Math.abs(m.nikki_total - m.final_fee) >= 1).length || 0;

  // Calculate totals
  const totals = sortedMembers.reduce(
    (acc, m) => ({
      baseFee: acc.baseFee + m.base_fee,
      finalFee: acc.finalFee + m.final_fee,
      nikkiTotal: acc.nikkiTotal + (m.nikki_total || 0),
      nikkiSaldo: acc.nikkiSaldo + (m.nikki_saldo || 0),
    }),
    { baseFee: 0, finalFee: 0, nikkiTotal: 0, nikkiSaldo: 0 }
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
        {/* Season indicator */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
              <span>Seizoen:</span>
              <select
                value={isForecast ? 'forecast' : 'current'}
                onChange={(e) => setIsForecast(e.target.value === 'forecast')}
                className="btn-secondary appearance-none pr-8 bg-no-repeat bg-right"
                style={{
                  backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
                  backgroundSize: '1.25rem',
                  paddingRight: '2rem',
                }}
              >
                <option value="current">{data?.season || '2025-2026'} (huidig)</option>
                <option value="forecast">
                  {data?.season ? getNextSeasonLabel(data.season) : '2026-2027'} (prognose)
                </option>
              </select>
            </div>
            {isForecast && (
              <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300">
                <TrendingUp className="w-4 h-4" />
                <span className="font-medium">Prognose</span>
                <span className="text-blue-600 dark:text-blue-400">
                  (o.b.v. huidige ledenstand)
                </span>
              </div>
            )}
            <div className="text-sm text-gray-500 dark:text-gray-400">
              {sortedMembers.length} leden
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="text-sm text-gray-500 dark:text-gray-400">
              Totaal: <span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(totals.finalFee, 2)}</span>
            </div>
            {!isForecast && (
              <div className="text-sm text-gray-500 dark:text-gray-400">
                Nog te ontvangen: <span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(totals.nikkiSaldo, 2)}</span>
              </div>
            )}
            {/* Filter: Mismatch - Only in current season mode */}
            {!isForecast && mismatchCount > 0 && (
              <button
                onClick={() => {
                  setShowMismatchOnly(!showMismatchOnly);
                  if (!showMismatchOnly) setShowNoNikkiOnly(false);
                }}
                className={`btn-secondary inline-flex items-center gap-1.5 ${
                  showMismatchOnly ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 border-red-300 dark:border-red-700' : ''
                }`}
                title={showMismatchOnly ? 'Toon alle leden' : 'Toon alleen leden waar Nikki afwijkt van Bedrag'}
              >
                <Filter className="w-4 h-4" />
                <span className="text-xs">Afwijking ({mismatchCount})</span>
              </button>
            )}
            {/* Filter: No Nikki Data - Only in current season mode */}
            {!isForecast && noNikkiCount > 0 && (
              <button
                onClick={() => {
                  setShowNoNikkiOnly(!showNoNikkiOnly);
                  if (!showNoNikkiOnly) setShowMismatchOnly(false);
                }}
                className={`btn-secondary inline-flex items-center gap-1.5 ${
                  showNoNikkiOnly ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700' : ''
                }`}
                title={showNoNikkiOnly ? 'Toon alle leden' : 'Toon alleen leden zonder Nikki data'}
              >
                <Filter className="w-4 h-4" />
                <span className="text-xs">Geen Nikki ({noNikkiCount})</span>
              </button>
            )}
            {/* Export Button */}
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
          </div>
        </div>

        {/* Fee list table */}
        <div className="card overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <SortableHeader
                  label="Voornaam"
                  columnId="first_name"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                />
                <SortableHeader
                  label="Achternaam"
                  columnId="last_name"
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
                <SortableHeader
                  label="Leeftijdsgroep"
                  columnId="leeftijdsgroep"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                />
                <SortableHeader
                  label="Basis"
                  columnId="base_fee"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                  className="text-right"
                />
                <SortableHeader
                  label="Gezin"
                  columnId="family_discount_rate"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                  className="text-right"
                />
                <SortableHeader
                  label="Pro-rata"
                  columnId="prorata_percentage"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                  className="text-right"
                />
                <SortableHeader
                  label="Bedrag"
                  columnId="final_fee"
                  sortField={sortField}
                  sortOrder={sortOrder}
                  onSort={handleSort}
                  className="text-right"
                />
                {!isForecast && (
                  <>
                    <SortableHeader
                      label="Nikki"
                      columnId="nikki_total"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                      className="text-right"
                    />
                    <SortableHeader
                      label="Saldo"
                      columnId="nikki_saldo"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                      className="text-right"
                    />
                  </>
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {sortedMembers.map((member, index) => (
                <FeeRow
                  key={member.id}
                  member={member}
                  isOdd={index % 2 === 1}
                  isForecast={isForecast}
                />
              ))}
            </tbody>
            <tfoot className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <td colSpan="4" className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                  Totaal
                </td>
                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                  {formatCurrency(totals.baseFee, 2)}
                </td>
                <td colSpan="2"></td>
                <td className="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                  {formatCurrency(totals.finalFee, 2)}
                </td>
                {!isForecast && (
                  <>
                    <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                      {formatCurrency(totals.nikkiTotal, 2)}
                    </td>
                    <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">
                      {formatCurrency(totals.nikkiSaldo, 2)}
                    </td>
                  </>
                )}
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </PullToRefreshWrapper>
  );
}
