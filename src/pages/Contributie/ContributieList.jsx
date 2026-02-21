import { useState, useCallback, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { RefreshCw, Coins, Download, Filter } from 'lucide-react';
import { useFeeList } from '@/hooks/useFees';
import { useQueryClient } from '@tanstack/react-query';
import { buildCsv, downloadCsv } from '@/utils/csvExport';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { formatCurrency, formatPercentage, getCategoryColor } from '@/utils/formatters';
import SeasonSelector from './SeasonSelector';
import SortableHeader from '@/components/SortableHeader';
import { DataTableToolbar, ColumnSettingsPanel, useColumnVisibility, createColumn, FILTER_TYPES } from '@/components/DataTable';

function FeeRow({ member, isOdd, showNikkiColumns, categories, isColVisible }) {
  const hasDiscount = member.family_discount_rate > 0;
  const hasProrata = member.prorata_percentage < 1.0;

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    } ${hasProrata ? 'bg-amber-50/50 dark:bg-amber-900/10' : ''}`}>
      <td className="px-4 py-3 whitespace-nowrap">
        <Link
          to={`/people/${member.id}`}
          className="text-sm font-medium text-gray-900 dark:text-gray-50 hover:text-electric-cyan dark:hover:text-electric-cyan"
        >
          {member.first_name}
        </Link>
      </td>

      <td className="px-4 py-3 whitespace-nowrap">
        <Link
          to={`/people/${member.id}`}
          className="text-sm text-gray-700 dark:text-gray-300 hover:text-electric-cyan dark:hover:text-electric-cyan"
        >
          {member.last_name}
        </Link>
      </td>

      <td className="px-4 py-3 whitespace-nowrap">
        <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${getCategoryColor(categories?.[member.category]?.sort_order)}`}>
          {categories?.[member.category]?.label ?? member.category}
        </span>
      </td>

      {isColVisible('leeftijdsgroep') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
          {member.leeftijdsgroep || '-'}
        </td>
      )}

      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
        {formatCurrency(member.base_fee, 2)}
      </td>

      {isColVisible('family_discount_rate') && (
        <td className="px-4 py-3 text-sm text-right">
          {hasDiscount ? (
            <span className="text-green-600 dark:text-green-400">
              -{formatPercentage(member.family_discount_rate)}
            </span>
          ) : (
            <span className="text-gray-400 dark:text-gray-500">-</span>
          )}
        </td>
      )}

      {isColVisible('prorata_percentage') && (
        <td className="px-4 py-3 text-sm text-right">
          {hasProrata ? (
            <span className="text-amber-600 dark:text-amber-400">
              {formatPercentage(member.prorata_percentage)}
            </span>
          ) : (
            <span className="text-gray-400 dark:text-gray-500">100%</span>
          )}
        </td>
      )}

      <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-50 text-right">
        {formatCurrency(member.final_fee, 2)}
      </td>

      {showNikkiColumns && (
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

export function ContributieList() {
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [showMismatchOnly, setShowMismatchOnly] = useState(false);
  const [isForecast, setIsForecast] = useState(false);
  const [firstNameFilter, setFirstNameFilter] = useState('');
  const [lastNameFilter, setLastNameFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);
  const queryClient = useQueryClient();

  const { isVisible, toggle } = useColumnVisibility('contributie');

  const { data, isLoading, error } = useFeeList(
    isForecast ? { forecast: true } : {}
  );

  const handleSort = useCallback((field, order) => {
    setSortField(field);
    setSortOrder(order);
  }, []);

  const billingMethod = data?.billing_method ?? 'nikki';
  const showNikkiColumns = billingMethod === 'nikki' && !isForecast;

  useEffect(() => {
    if (!showNikkiColumns && (sortField === 'nikki_total' || sortField === 'nikki_saldo')) {
      setSortField('last_name');
    }
  }, [showNikkiColumns, sortField]);

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['fees'] });
  };

  // Build category filter options from loaded data
  const categoryOptions = useMemo(() => {
    return Object.entries(data?.categories || {})
      .sort(([, a], [, b]) => (a.sort_order ?? 999) - (b.sort_order ?? 999))
      .map(([slug, meta]) => ({ value: slug, label: meta.label ?? slug }));
  }, [data?.categories]);

  const filterColumns = useMemo(() => [
    createColumn({
      id: 'first_name',
      header: 'Voornaam',
      filterType: FILTER_TYPES.TEXT,
    }),
    createColumn({
      id: 'last_name',
      header: 'Achternaam',
      filterType: FILTER_TYPES.TEXT,
    }),
    createColumn({
      id: 'category',
      header: 'Categorie',
      filterType: categoryOptions.length > 0 ? FILTER_TYPES.SELECT : null,
      filterOptions: categoryOptions,
    }),
  ], [categoryOptions]);

  const hasActiveFilters = !!firstNameFilter || !!lastNameFilter || !!categoryFilter;
  const activeFilterCount = (firstNameFilter ? 1 : 0) + (lastNameFilter ? 1 : 0) + (categoryFilter ? 1 : 0);

  const clearFilters = () => {
    setFirstNameFilter('');
    setLastNameFilter('');
    setCategoryFilter('');
  };

  const setFilter = (colId, value) => {
    if (colId === 'first_name') setFirstNameFilter(value || '');
    else if (colId === 'last_name') setLastNameFilter(value || '');
    else if (colId === 'category') setCategoryFilter(value || '');
  };

  const colVisColumns = [
    { id: 'leeftijdsgroep', label: 'Leeftijdsgroep', isVisible: isVisible('leeftijdsgroep') },
    { id: 'family_discount_rate', label: 'Gezinskorting', isVisible: isVisible('family_discount_rate') },
    { id: 'prorata_percentage', label: 'Pro-rata', isVisible: isVisible('prorata_percentage') },
  ];

  const filteredMembers = useMemo(() => {
    return (data?.members ?? []).filter(m => {
      if (showMismatchOnly && !(m.nikki_total !== null && Math.abs(m.nikki_total - m.final_fee) >= 1)) return false;
      if (firstNameFilter && !(m.first_name || '').toLowerCase().includes(firstNameFilter.toLowerCase())) return false;
      if (lastNameFilter && !(m.last_name || '').toLowerCase().includes(lastNameFilter.toLowerCase())) return false;
      if (categoryFilter && m.category !== categoryFilter) return false;
      return true;
    });
  }, [data?.members, showMismatchOnly, firstNameFilter, lastNameFilter, categoryFilter]);

  const categoryOrder = {};
  Object.entries(data?.categories || {}).forEach(([slug, meta]) => {
    categoryOrder[slug] = meta.sort_order ?? 999;
  });

  const sortedMembers = [...filteredMembers].sort((a, b) => {
    let cmp = 0;

    switch (sortField) {
      case 'last_name':
        cmp = (a.last_name || '').localeCompare(b.last_name || '');
        if (cmp === 0) cmp = (a.first_name || '').localeCompare(b.first_name || '');
        break;
      case 'first_name':
        cmp = (a.first_name || '').localeCompare(b.first_name || '');
        if (cmp === 0) cmp = (a.last_name || '').localeCompare(b.last_name || '');
        break;
      case 'category':
        cmp = (categoryOrder[a.category] ?? 99) - (categoryOrder[b.category] ?? 99);
        break;
      case 'leeftijdsgroep':
        cmp = (a.leeftijdsgroep || '').localeCompare(b.leeftijdsgroep || '');
        break;
      case 'base_fee':
      case 'final_fee':
      case 'family_discount_rate':
      case 'prorata_percentage':
        cmp = a[sortField] - b[sortField];
        break;
      case 'nikki_total':
      case 'nikki_saldo':
        cmp = (a[sortField] ?? 0) - (b[sortField] ?? 0);
        break;
      default:
        cmp = 0;
    }

    return sortOrder === 'asc' ? cmp : -cmp;
  });

  const handleExportCsv = () => {
    const baseHeaders = ['Voornaam', 'Achternaam', 'Categorie', 'Leeftijdsgroep', 'Basis', 'Gezinskorting', 'Pro-rata', 'Bedrag'];
    const headers = showNikkiColumns ? [...baseHeaders, 'Nikki', 'Saldo'] : baseHeaders;
    const rows = sortedMembers.map(member => {
      const row = [
        member.first_name || '',
        member.last_name || '',
        data?.categories?.[member.category]?.label ?? member.category,
        member.leeftijdsgroep || '',
        member.base_fee,
        member.family_discount_rate,
        member.prorata_percentage,
        member.final_fee,
      ];
      if (showNikkiColumns) {
        row.push(member.nikki_total ?? '');
        row.push(member.nikki_saldo ?? '');
      }
      return row;
    });
    const csv = buildCsv([headers, ...rows]);
    downloadCsv(csv, `contributie-${data?.season || 'export'}.csv`);
  };

  const allMembers = data?.members ?? [];
  const mismatchCount = allMembers.filter(m => m.nikki_total !== null && Math.abs(m.nikki_total - m.final_fee) >= 1).length;

  const totals = sortedMembers.reduce(
    (acc, m) => ({
      baseFee: acc.baseFee + m.base_fee,
      finalFee: acc.finalFee + m.final_fee,
      nikkiTotal: acc.nikkiTotal + (m.nikki_total || 0),
      nikkiSaldo: acc.nikkiSaldo + (m.nikki_saldo || 0),
    }),
    { baseFee: 0, finalFee: 0, nikkiTotal: 0, nikkiSaldo: 0 }
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

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

  if (!allMembers.length) {
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
        {/* Season indicator + totals + mismatch toggle + CSV export */}
        <div className="flex items-center justify-between">
          <SeasonSelector
            season={data?.season}
            isForecast={isForecast}
            onForecastChange={setIsForecast}
            memberCount={sortedMembers.length}
          />
          <div className="flex items-center gap-3">
            <div className="text-sm text-gray-500 dark:text-gray-400">
              Totaal: <span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(totals.finalFee, 2)}</span>
            </div>
            {showNikkiColumns && (
              <div className="text-sm text-gray-500 dark:text-gray-400">
                Nog te ontvangen: <span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(totals.nikkiSaldo, 2)}</span>
              </div>
            )}
            {showNikkiColumns && mismatchCount > 0 && (
              <button
                onClick={() => setShowMismatchOnly(!showMismatchOnly)}
                className={`btn-secondary inline-flex items-center gap-1.5 ${
                  showMismatchOnly ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 border-red-300 dark:border-red-700' : ''
                }`}
                title={showMismatchOnly ? 'Toon alle leden' : 'Toon alleen leden waar Nikki afwijkt van Bedrag'}
              >
                <Filter className="w-4 h-4" />
                <span className="text-xs">Afwijking ({mismatchCount})</span>
              </button>
            )}
            <button
              onClick={handleExportCsv}
              className="btn-secondary"
              title="Downloaden als CSV"
              disabled={!sortedMembers.length}
            >
              <Download className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Filter toolbar */}
        <DataTableToolbar
          columns={filterColumns}
          filters={{ first_name: firstNameFilter, last_name: lastNameFilter, category: categoryFilter }}
          onFilterChange={setFilter}
          onClearFilters={clearFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
        />

        {/* Fee list table */}
        <div className="card overflow-x-auto">
          {sortedMembers.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <p className="text-sm text-gray-600 dark:text-gray-400">
                Geen leden gevonden voor de geselecteerde filters.
              </p>
              <button onClick={clearFilters} className="mt-3 btn-secondary text-sm">
                Filters wissen
              </button>
            </div>
          ) : (
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
                  {isVisible('leeftijdsgroep') && (
                    <SortableHeader
                      label="Leeftijdsgroep"
                      columnId="leeftijdsgroep"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                    />
                  )}
                  <SortableHeader
                    label="Basis"
                    columnId="base_fee"
                    sortField={sortField}
                    sortOrder={sortOrder}
                    onSort={handleSort}
                    className="text-right"
                  />
                  {isVisible('family_discount_rate') && (
                    <SortableHeader
                      label="Gezin"
                      columnId="family_discount_rate"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                      className="text-right"
                    />
                  )}
                  {isVisible('prorata_percentage') && (
                    <SortableHeader
                      label="Pro-rata"
                      columnId="prorata_percentage"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                      className="text-right"
                    />
                  )}
                  <SortableHeader
                    label="Bedrag"
                    columnId="final_fee"
                    sortField={sortField}
                    sortOrder={sortOrder}
                    onSort={handleSort}
                    className="text-right"
                  />
                  {showNikkiColumns && (
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
                    showNikkiColumns={showNikkiColumns}
                    categories={data?.categories}
                    isColVisible={isVisible}
                  />
                ))}
              </tbody>
              <tfoot className="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <td colSpan={isVisible('leeftijdsgroep') ? 4 : 3} className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                    Totaal
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                    {formatCurrency(totals.baseFee, 2)}
                  </td>
                  {isVisible('family_discount_rate') && <td></td>}
                  {isVisible('prorata_percentage') && <td></td>}
                  <td className="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                    {formatCurrency(totals.finalFee, 2)}
                  </td>
                  {showNikkiColumns && (
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
          )}
        </div>
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
