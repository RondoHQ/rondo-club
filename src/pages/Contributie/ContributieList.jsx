import { useState, useCallback, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { RefreshCw, Coins, Download } from 'lucide-react';
import { useFeeList } from '@/hooks/useFees';
import { useInvoices } from '@/hooks/useInvoices';
import { useQueryClient } from '@tanstack/react-query';
import { buildCsv, downloadCsv } from '@/utils/csvExport';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { formatCurrency, formatPercentage, getCategoryColor } from '@/utils/formatters';
import SeasonSelector from './SeasonSelector';
import SortableHeader from '@/components/SortableHeader';
import { DataTableToolbar, ColumnSettingsPanel, useColumnVisibility, createColumn, FILTER_TYPES } from '@/components/DataTable';

function formatCurrencyOrDash(value) {
  const amount = parseFloat(value) || 0;
  return amount === 0 ? '-' : formatCurrency(amount, 2);
}

function FeeRow({ member, isOdd, showNikkiColumns, categories, isColVisible }) {
  const hasDiscount = member.family_discount_rate > 0;
  const hasProrata = member.prorata_percentage < 1.0;

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    } ${hasProrata ? 'bg-amber-50/50 dark:bg-amber-900/10' : ''}`}>
      {isColVisible('first_name') && (
        <td className="px-4 py-3 whitespace-nowrap">
          <Link
            to={`/people/${member.id}`}
            className="text-sm font-medium text-gray-900 dark:text-gray-50 hover:text-electric-cyan dark:hover:text-electric-cyan"
          >
            {member.first_name}
          </Link>
        </td>
      )}

      {isColVisible('last_name') && (
        <td className="px-4 py-3 whitespace-nowrap">
          <Link
            to={`/people/${member.id}`}
            className="text-sm text-gray-700 dark:text-gray-300 hover:text-electric-cyan dark:hover:text-electric-cyan"
          >
            {member.last_name}
          </Link>
        </td>
      )}

      {isColVisible('category') && (
        <td className="px-4 py-3 whitespace-nowrap">
          <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${getCategoryColor(categories?.[member.category]?.sort_order)}`}>
            {categories?.[member.category]?.label ?? member.category}
          </span>
        </td>
      )}

      {isColVisible('leeftijdsgroep') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
          {member.leeftijdsgroep || '-'}
        </td>
      )}

      {isColVisible('base_fee') && (
        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
          {formatCurrencyOrDash(member.base_fee)}
        </td>
      )}

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

      {isColVisible('final_fee') && (
        <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-50 text-right">
          {formatCurrencyOrDash(member.final_fee)}
        </td>
      )}

      {isColVisible('rondo_invoiced') && (
        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 text-right">
          {formatCurrencyOrDash(member.rondo_invoiced || 0)}
        </td>
      )}

      {showNikkiColumns && (
        <>
          <td className="px-4 py-3 text-sm text-right">
            {member.nikki_total !== null ? (
              <span className="text-gray-700 dark:text-gray-300">
                {formatCurrencyOrDash(member.nikki_total)}
              </span>
            ) : (
              <span className="text-gray-400 dark:text-gray-500">-</span>
            )}
          </td>

          <td className="px-4 py-3 text-sm text-right">
            {member.nikki_saldo !== null ? (
              <span className="text-gray-700 dark:text-gray-300">
                {formatCurrencyOrDash(member.nikki_saldo)}
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

export function ContributieList({ onlyMismatch = false }) {
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [isForecast, setIsForecast] = useState(false);
  const [firstNameFilter, setFirstNameFilter] = useState('');
  const [lastNameFilter, setLastNameFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [leeftijdsgroepFilter, setLeeftijdsgroepFilter] = useState('');
  const [familyDiscountFilter, setFamilyDiscountFilter] = useState('');
  const [prorataFilter, setProrataFilter] = useState('');
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);
  const queryClient = useQueryClient();

  const { isVisible, toggle } = useColumnVisibility('contributie');
  const { data: invoices = [] } = useInvoices();

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

  const leeftijdsgroepOptions = useMemo(() => {
    return [...new Set((data?.members ?? []).map(m => m.leeftijdsgroep).filter(Boolean))].sort()
      .map(v => ({ value: v, label: v }));
  }, [data?.members]);

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
    createColumn({
      id: 'leeftijdsgroep',
      header: 'Leeftijdsgroep',
      filterType: leeftijdsgroepOptions.length > 0 ? FILTER_TYPES.SELECT : null,
      filterOptions: leeftijdsgroepOptions,
    }),
    createColumn({
      id: 'family_discount',
      header: 'Gezinskorting',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'heeft', label: 'Heeft gezinskorting' },
        { value: 'geen', label: 'Geen gezinskorting' },
      ],
      getFilterLabel: (val) => val === 'heeft' ? 'Heeft gezinskorting' : 'Geen gezinskorting',
    }),
    createColumn({
      id: 'prorata',
      header: 'Pro-rata',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'heeft', label: 'Heeft pro-rata' },
        { value: 'geen', label: 'Geen pro-rata' },
      ],
      getFilterLabel: (val) => val === 'heeft' ? 'Heeft pro-rata' : 'Geen pro-rata',
    }),
  ], [categoryOptions, leeftijdsgroepOptions]);

  const hasActiveFilters = !!(firstNameFilter || lastNameFilter || categoryFilter || leeftijdsgroepFilter || familyDiscountFilter || prorataFilter);
  const activeFilterCount = [firstNameFilter, lastNameFilter, categoryFilter, leeftijdsgroepFilter, familyDiscountFilter, prorataFilter].filter(Boolean).length;

  const clearFilters = () => {
    setFirstNameFilter('');
    setLastNameFilter('');
    setCategoryFilter('');
    setLeeftijdsgroepFilter('');
    setFamilyDiscountFilter('');
    setProrataFilter('');
  };

  const setFilter = (colId, value) => {
    if (colId === 'first_name') setFirstNameFilter(value || '');
    else if (colId === 'last_name') setLastNameFilter(value || '');
    else if (colId === 'category') setCategoryFilter(value || '');
    else if (colId === 'leeftijdsgroep') setLeeftijdsgroepFilter(value || '');
    else if (colId === 'family_discount') setFamilyDiscountFilter(value || '');
    else if (colId === 'prorata') setProrataFilter(value || '');
  };

  const colVisColumns = [
    { id: 'first_name', label: 'Voornaam', isVisible: isVisible('first_name') },
    { id: 'last_name', label: 'Achternaam', isVisible: isVisible('last_name') },
    { id: 'category', label: 'Categorie', isVisible: isVisible('category') },
    { id: 'leeftijdsgroep', label: 'Leeftijdsgroep', isVisible: isVisible('leeftijdsgroep') },
    { id: 'base_fee', label: 'Basis', isVisible: isVisible('base_fee') },
    { id: 'family_discount_rate', label: 'Gezinskorting', isVisible: isVisible('family_discount_rate') },
    { id: 'prorata_percentage', label: 'Pro-rata', isVisible: isVisible('prorata_percentage') },
    { id: 'final_fee', label: 'Bedrag', isVisible: isVisible('final_fee') },
    { id: 'rondo_invoiced', label: 'Rondo', isVisible: isVisible('rondo_invoiced') },
  ];

  const rondoByPerson = useMemo(() => {
    const map = new Map();
    invoices
      .filter((invoice) => invoice?.invoice_type === 'membership' && invoice?.person?.id)
      .forEach((invoice) => {
        const personId = invoice.person.id;
        const current = map.get(personId) || 0;
        map.set(personId, current + (parseFloat(invoice.total_amount) || 0));
      });
    return map;
  }, [invoices]);

  const disciplineByPerson = useMemo(() => {
    const map = new Map();
    invoices
      .filter((invoice) => invoice?.invoice_type === 'discipline' && invoice?.person?.id)
      .forEach((invoice) => {
        const personId = invoice.person.id;
        const current = map.get(personId) || 0;
        map.set(personId, current + (parseFloat(invoice.total_amount) || 0));
      });
    return map;
  }, [invoices]);

  const allMembers = useMemo(() => {
    const sourceMembers = data?.members ?? [];
    const map = new Map();

    sourceMembers.forEach((member) => {
      map.set(member.id, {
        ...member,
        rondo_invoiced: rondoByPerson.get(member.id) || 0,
        discipline_invoiced: disciplineByPerson.get(member.id) || 0,
      });
    });

    rondoByPerson.forEach((rondoAmount, personId) => {
      if (map.has(personId)) return;
      const invoicePerson = invoices.find((invoice) => invoice?.person?.id === personId)?.person;
      map.set(personId, {
        id: personId,
        first_name: invoicePerson?.name || `Lid #${personId}`,
        last_name: '',
        category: '',
        leeftijdsgroep: '',
        base_fee: 0,
        family_discount_rate: 0,
        prorata_percentage: 1,
        final_fee: 0,
        nikki_total: null,
        nikki_saldo: null,
        rondo_invoiced: rondoAmount,
        discipline_invoiced: disciplineByPerson.get(personId) || 0,
      });
    });

    return [...map.values()];
  }, [data?.members, rondoByPerson, disciplineByPerson, invoices]);

  const filteredMembers = useMemo(() => {
    return allMembers.filter(m => {
      const contributiePlusTuchtzaken = (parseFloat(m.final_fee) || 0) + (parseFloat(m.discipline_invoiced) || 0);
      if (onlyMismatch && !(m.nikki_total !== null && Math.abs(m.nikki_total - contributiePlusTuchtzaken) >= 1)) return false;
      if (firstNameFilter && !(m.first_name || '').toLowerCase().includes(firstNameFilter.toLowerCase())) return false;
      if (lastNameFilter && !(m.last_name || '').toLowerCase().includes(lastNameFilter.toLowerCase())) return false;
      if (categoryFilter && m.category !== categoryFilter) return false;
      if (leeftijdsgroepFilter && m.leeftijdsgroep !== leeftijdsgroepFilter) return false;
      if (familyDiscountFilter === 'heeft' && !(m.family_discount_rate > 0)) return false;
      if (familyDiscountFilter === 'geen' && m.family_discount_rate > 0) return false;
      if (prorataFilter === 'heeft' && !(m.prorata_percentage < 1.0)) return false;
      if (prorataFilter === 'geen' && m.prorata_percentage < 1.0) return false;
      return true;
    });
  }, [allMembers, onlyMismatch, firstNameFilter, lastNameFilter, categoryFilter, leeftijdsgroepFilter, familyDiscountFilter, prorataFilter]);

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
      case 'rondo_invoiced':
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
    const headers = showNikkiColumns ? [...baseHeaders, 'Rondo', 'Nikki', 'Saldo'] : [...baseHeaders, 'Rondo'];
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
        member.rondo_invoiced || 0,
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

  const totals = sortedMembers.reduce(
    (acc, m) => ({
      baseFee: acc.baseFee + m.base_fee,
      finalFee: acc.finalFee + m.final_fee,
      rondoInvoiced: acc.rondoInvoiced + (m.rondo_invoiced || 0),
      nikkiTotal: acc.nikkiTotal + (m.nikki_total || 0),
      nikkiSaldo: acc.nikkiSaldo + (m.nikki_saldo || 0),
    }),
    { baseFee: 0, finalFee: 0, rondoInvoiced: 0, nikkiTotal: 0, nikkiSaldo: 0 }
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
          className="btn-tertiary inline-flex items-center gap-2"
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
        {/* Filter toolbar */}
        <DataTableToolbar
          columns={filterColumns}
          filters={{ first_name: firstNameFilter, last_name: lastNameFilter, category: categoryFilter, leeftijdsgroep: leeftijdsgroepFilter, family_discount: familyDiscountFilter, prorata: prorataFilter }}
          onFilterChange={setFilter}
          onClearFilters={clearFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
          toolbarEnd={(
            <div className="flex items-center gap-2">
              <SeasonSelector
                season={data?.season}
                isForecast={isForecast}
                onForecastChange={setIsForecast}
              />
              <button
                onClick={handleExportCsv}
                className="btn-tertiary"
                title="Downloaden als CSV"
                disabled={!sortedMembers.length}
              >
                <Download className="w-4 h-4" />
              </button>
            </div>
          )}
        />

        {/* Fee list table */}
        <div
          className="card !overflow-x-auto"
          style={{ WebkitOverflowScrolling: 'touch', touchAction: 'pan-x' }}
          data-horizontal-scroll="true"
        >
          {sortedMembers.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <p className="text-sm text-gray-600 dark:text-gray-400">
                Geen leden gevonden voor de geselecteerde filters.
              </p>
              <button onClick={clearFilters} className="mt-3 btn-tertiary text-sm">
                Filters wissen
              </button>
            </div>
          ) : (
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-800">
                <tr>
                  {isVisible('first_name') && (
                    <SortableHeader
                      label="Voornaam"
                      columnId="first_name"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                    />
                  )}
                  {isVisible('last_name') && (
                    <SortableHeader
                      label="Achternaam"
                      columnId="last_name"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                    />
                  )}
                  {isVisible('category') && (
                    <SortableHeader
                      label="Categorie"
                      columnId="category"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                    />
                  )}
                  {isVisible('leeftijdsgroep') && (
                    <SortableHeader
                      label="Leeftijdsgroep"
                      columnId="leeftijdsgroep"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                    />
                  )}
                  {isVisible('base_fee') && (
                    <SortableHeader
                      label="Basis"
                      columnId="base_fee"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                      className="text-right"
                    />
                  )}
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
                  {isVisible('final_fee') && (
                    <SortableHeader
                      label="Bedrag"
                      columnId="final_fee"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                      className="text-right"
                    />
                  )}
                  {isVisible('rondo_invoiced') && (
                    <SortableHeader
                      label="Rondo"
                      columnId="rondo_invoiced"
                      sortField={sortField}
                      sortOrder={sortOrder}
                      onSort={handleSort}
                      className="text-right"
                    />
                  )}
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
                  <td
                    colSpan={
                      [isVisible('first_name'), isVisible('last_name'), isVisible('category'), isVisible('leeftijdsgroep')]
                        .filter(Boolean).length || 1
                    }
                    className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100"
                  >
                    Totaal
                  </td>
                  {isVisible('base_fee') && (
                    <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                      {formatCurrency(totals.baseFee, 2)}
                    </td>
                  )}
                  {isVisible('family_discount_rate') && <td></td>}
                  {isVisible('prorata_percentage') && <td></td>}
                  {isVisible('final_fee') && (
                    <td className="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                      {formatCurrency(totals.finalFee, 2)}
                    </td>
                  )}
                  {isVisible('rondo_invoiced') && (
                    <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                      {formatCurrency(totals.rondoInvoiced, 2)}
                    </td>
                  )}
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
