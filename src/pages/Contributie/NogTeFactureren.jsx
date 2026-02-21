import { useState, useCallback, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { RefreshCw, Coins, FileText, Loader2 } from 'lucide-react';
import { useFeeList, feeKeys } from '@/hooks/useFees';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency, formatPercentage, getCategoryColor } from '@/utils/formatters';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import SeasonSelector from './SeasonSelector';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';

function StatusBadge({ status }) {
  const styles = {
    draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    paid: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  };
  const labels = {
    draft: 'Concept',
    sent: 'Verstuurd',
    paid: 'Betaald',
    overdue: 'Verlopen',
  };
  return (
    <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${styles[status] || styles.draft}`}>
      {labels[status] || status}
    </span>
  );
}

export function NogTeFactureren() {
  const [creatingForId, setCreatingForId] = useState(null);
  const [bulkCreating, setBulkCreating] = useState(false);
  const [bulkProgress, setBulkProgress] = useState(null);
  const queryClient = useQueryClient();

  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  const { data, isLoading, error } = useFeeList();

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: feeKeys.all });
  };

  // Single invoice creation
  const createInvoice = useMutation({
    mutationFn: ({ personId, season }) =>
      prmApi.createMembershipInvoice({ person_id: personId, season }),
    onSuccess: () => {
      queryClient.resetQueries({ queryKey: feeKeys.all });
    },
  });

  const handleCreateInvoice = useCallback((personId) => {
    setCreatingForId(personId);
    createInvoice.mutate(
      { personId, season: data?.season },
      { onSettled: () => setCreatingForId(null) },
    );
  }, [createInvoice, data?.season]);

  // Filter to members without Nikki data
  const noNikkiMembers = useMemo(
    () => (data?.members ?? []).filter((m) => m.nikki_total === null),
    [data?.members],
  );

  // Bulk invoice creation
  const handleBulkCreate = async () => {
    if (bulkCreating) return;
    setBulkCreating(true);
    setBulkProgress({ created: 0, skipped: 0, total: 0 });

    const toCreate = noNikkiMembers.filter((m) => !m.invoice_id && m.final_fee > 0);
    setBulkProgress((prev) => ({ ...prev, total: toCreate.length }));

    let created = 0;
    let skipped = 0;

    for (const member of toCreate) {
      try {
        await prmApi.createMembershipInvoice({ person_id: member.id, season: data?.season });
        created++;
      } catch {
        skipped++;
      }
      setBulkProgress({ created, skipped, total: toCreate.length });
    }

    setBulkCreating(false);
    queryClient.resetQueries({ queryKey: feeKeys.all });
  };

  // Build column definitions — depends on loaded categories + callbacks
  const columns = useMemo(() => {
    const categoryOptions = Object.entries(data?.categories || {})
      .sort(([, a], [, b]) => (a.sort_order ?? 999) - (b.sort_order ?? 999))
      .map(([slug, meta]) => ({ value: slug, label: meta.label ?? slug }));

    return [
      createColumn({
        id: 'first_name',
        header: 'Voornaam',
        accessorKey: 'first_name',
        cell: ({ row }) => (
          <Link
            to={`/people/${row.original.id}`}
            className="text-sm font-medium text-gray-900 dark:text-gray-50 hover:text-electric-cyan dark:hover:text-electric-cyan"
          >
            {row.original.first_name}
          </Link>
        ),
        filterType: FILTER_TYPES.TEXT,
      }),
      createColumn({
        id: 'last_name',
        header: 'Achternaam',
        accessorKey: 'last_name',
        cell: ({ row }) => (
          <Link
            to={`/people/${row.original.id}`}
            className="text-sm text-gray-700 dark:text-gray-300 hover:text-electric-cyan dark:hover:text-electric-cyan"
          >
            {row.original.last_name}
          </Link>
        ),
        filterType: FILTER_TYPES.TEXT,
      }),
      createColumn({
        id: 'category',
        header: 'Categorie',
        accessorKey: 'category',
        cell: ({ row }) => (
          <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${getCategoryColor(data?.categories?.[row.original.category]?.sort_order)}`}>
            {data?.categories?.[row.original.category]?.label ?? row.original.category}
          </span>
        ),
        filterType: categoryOptions.length > 0 ? FILTER_TYPES.SELECT : null,
        filterOptions: categoryOptions,
      }),
      createColumn({
        id: 'base_fee',
        header: 'Basis',
        accessorKey: 'base_fee',
        cell: ({ getValue }) => formatCurrency(getValue(), 2),
        filterType: null,
        sortable: true,
        headerClassName: 'text-right',
        className: 'text-right',
        size: 100,
      }),
      createColumn({
        id: 'family_discount_rate',
        header: 'Gezin',
        accessorKey: 'family_discount_rate',
        cell: ({ row }) =>
          row.original.family_discount_rate > 0 ? (
            <span className="text-green-600 dark:text-green-400">
              -{formatPercentage(row.original.family_discount_rate)}
            </span>
          ) : (
            <span className="text-gray-400 dark:text-gray-500">-</span>
          ),
        filterType: null,
        sortable: true,
        headerClassName: 'text-right',
        className: 'text-right',
        size: 90,
      }),
      createColumn({
        id: 'prorata_percentage',
        header: 'Pro-rata',
        accessorKey: 'prorata_percentage',
        cell: ({ row }) =>
          row.original.prorata_percentage < 1.0 ? (
            <span className="text-amber-600 dark:text-amber-400">
              {formatPercentage(row.original.prorata_percentage)}
            </span>
          ) : (
            <span className="text-gray-400 dark:text-gray-500">100%</span>
          ),
        filterType: null,
        sortable: true,
        headerClassName: 'text-right',
        className: 'text-right',
        size: 90,
      }),
      createColumn({
        id: 'final_fee',
        header: 'Bedrag',
        accessorKey: 'final_fee',
        cell: ({ getValue }) => (
          <span className="font-medium text-gray-900 dark:text-gray-50">
            {formatCurrency(getValue(), 2)}
          </span>
        ),
        filterType: null,
        sortable: true,
        headerClassName: 'text-right',
        className: 'text-right font-medium text-gray-900 dark:text-gray-50',
        size: 100,
      }),
      createColumn({
        id: 'invoice_status',
        header: 'Status',
        accessorKey: 'invoice_status',
        cell: ({ row }) =>
          row.original.invoice_status ? (
            <Link to={`/financien/facturen/${row.original.invoice_id}`}>
              <StatusBadge status={row.original.invoice_status} />
            </Link>
          ) : (
            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
              Geen factuur
            </span>
          ),
        filterType: FILTER_TYPES.SELECT,
        filterLabel: 'Facturastatus',
        filterOptions: [
          { value: 'none', label: 'Geen factuur' },
          { value: 'draft', label: 'Concept' },
          { value: 'sent', label: 'Verstuurd' },
          { value: 'paid', label: 'Betaald' },
          { value: 'overdue', label: 'Verlopen' },
        ],
        // Custom filterFn to handle null invoice_status (represented as 'none')
        filterFn: (row, _id, value) => {
          if (!value) return true;
          if (value === 'none') return row.original.invoice_status === null;
          return row.original.invoice_status === value;
        },
        size: 120,
      }),
      // Action column (admin only): create invoice button
      createColumn({
        id: 'actions',
        header: '',
        accessorFn: () => null,
        cell: ({ row }) => {
          const member = row.original;
          if (!isAdmin || member.invoice_id || member.final_fee <= 0) return null;
          return (
            <button
              onClick={() => handleCreateInvoice(member.id)}
              disabled={creatingForId === member.id}
              className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-md bg-electric-cyan text-white hover:bg-electric-cyan/90 disabled:opacity-50 transition-colors"
            >
              {creatingForId === member.id ? (
                <Loader2 className="w-3 h-3 animate-spin" />
              ) : (
                <FileText className="w-3 h-3" />
              )}
              Maak factuur
            </button>
          );
        },
        sortable: false,
        filterType: null,
        size: 130,
      }),
    ];
  }, [data?.categories, isAdmin, handleCreateInvoice, creatingForId]);

  // Totals (over all noNikkiMembers, regardless of filters)
  const totalBaseFee = noNikkiMembers.reduce((acc, m) => acc + m.base_fee, 0);
  const totalFee = noNikkiMembers.reduce((acc, m) => acc + m.final_fee, 0);
  const uninvoicedCount = noNikkiMembers.filter((m) => !m.invoice_id && m.final_fee > 0).length;

  if (!isLoading && error) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400 mb-4">
          Gegevens konden niet worden geladen.
        </p>
        <button onClick={handleRefresh} className="btn-secondary inline-flex items-center gap-2">
          <RefreshCw className="w-4 h-4" />
          Opnieuw proberen
        </button>
      </div>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Season selector + totals + bulk action */}
        <div className="flex items-center justify-between">
          <SeasonSelector
            season={data?.season}
            isForecast={false}
            onForecastChange={() => {}}
            memberCount={noNikkiMembers.length}
          />
          <div className="flex items-center gap-3">
            <div className="text-sm text-gray-500 dark:text-gray-400">
              Totaal:{' '}
              <span className="font-medium text-gray-900 dark:text-gray-100">
                {formatCurrency(totalFee, 2)}
              </span>
            </div>
            {isAdmin && uninvoicedCount > 0 && (
              <button
                onClick={handleBulkCreate}
                disabled={bulkCreating}
                className="btn-primary inline-flex items-center gap-2"
              >
                {bulkCreating ? (
                  <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                  <FileText className="w-4 h-4" />
                )}
                Maak facturen ({uninvoicedCount})
              </button>
            )}
          </div>
        </div>

        {/* Bulk progress */}
        {bulkProgress && (bulkCreating || bulkProgress.created + bulkProgress.skipped > 0) && (
          <div className="card p-4">
            {bulkCreating ? (
              <div className="flex items-center gap-3">
                <Loader2 className="w-5 h-5 animate-spin text-electric-cyan" />
                <span className="text-sm text-gray-700 dark:text-gray-300">
                  {bulkProgress.created + bulkProgress.skipped} van {bulkProgress.total} verwerkt
                  ({bulkProgress.created} aangemaakt, {bulkProgress.skipped} overgeslagen)
                </span>
              </div>
            ) : (
              <div className="text-sm text-green-700 dark:text-green-400">
                Klaar: {bulkProgress.created} facturen aangemaakt, {bulkProgress.skipped} overgeslagen
              </div>
            )}
          </div>
        )}

        {!isLoading && noNikkiMembers.length === 0 ? (
          <div className="card">
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <div className="flex justify-center mb-4">
                <div className="p-3 bg-gray-100 rounded-full dark:bg-gray-700">
                  <Coins className="w-8 h-8 text-gray-400 dark:text-gray-500" />
                </div>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                Alle leden hebben Nikki data
              </h3>
              <p className="text-sm text-gray-600 dark:text-gray-400">
                Er zijn geen leden die nog gefactureerd moeten worden.
              </p>
            </div>
          </div>
        ) : (
          <DataTable
            storageKey="nog-te-factureren"
            data={noNikkiMembers}
            columns={columns}
            isLoading={isLoading}
            emptyIcon={<Coins className="w-8 h-8 text-gray-400 dark:text-gray-500" />}
            emptyTitle="Geen resultaten"
            emptyDescription="Geen leden gevonden voor de geselecteerde filters."
            footer={
              noNikkiMembers.length > 0 ? (
                <tfoot className="bg-gray-50 dark:bg-gray-800">
                  <tr>
                    <td colSpan="3" className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                      Totaal
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                      {formatCurrency(totalBaseFee, 2)}
                    </td>
                    <td colSpan="2"></td>
                    <td className="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                      {formatCurrency(totalFee, 2)}
                    </td>
                    <td colSpan="2"></td>
                  </tr>
                </tfoot>
              ) : null
            }
          />
        )}
      </div>
    </PullToRefreshWrapper>
  );
}
