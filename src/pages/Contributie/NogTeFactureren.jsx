import { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { RefreshCw, Coins, FileText, Loader2 } from 'lucide-react';
import { useFeeList, feeKeys } from '@/hooks/useFees';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency, getCategoryColor } from '@/utils/formatters';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import SeasonSelector from './SeasonSelector';
import SortableHeader from '@/components/SortableHeader';

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

function FeeRow({ member, isOdd, categories, isAdmin, onCreateInvoice, isCreating }) {
  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    }`}>
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
      <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-50 text-right">
        {formatCurrency(member.final_fee, 2)}
      </td>
      <td className="px-4 py-3 whitespace-nowrap">
        {member.invoice_status ? (
          <Link to={`/financien/facturen/${member.invoice_id}`}>
            <StatusBadge status={member.invoice_status} />
          </Link>
        ) : (
          <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
            Geen factuur
          </span>
        )}
      </td>
      <td className="px-4 py-3 whitespace-nowrap">
        {isAdmin && !member.invoice_id && member.final_fee > 0 && (
          <button
            onClick={() => onCreateInvoice(member.id)}
            disabled={isCreating}
            className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-md bg-electric-cyan text-white hover:bg-electric-cyan/90 disabled:opacity-50 transition-colors"
          >
            {isCreating ? (
              <Loader2 className="w-3 h-3 animate-spin" />
            ) : (
              <FileText className="w-3 h-3" />
            )}
            Maak factuur
          </button>
        )}
      </td>
    </tr>
  );
}

export function NogTeFactureren() {
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [creatingForId, setCreatingForId] = useState(null);
  const [bulkCreating, setBulkCreating] = useState(false);
  const [bulkProgress, setBulkProgress] = useState(null);
  const queryClient = useQueryClient();

  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  const { data, isLoading, error } = useFeeList();

  const handleSort = useCallback((field, order) => {
    setSortField(field);
    setSortOrder(order);
  }, []);

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: feeKeys.all });
  };

  // Single invoice creation mutation
  const createInvoice = useMutation({
    mutationFn: ({ personId, season }) =>
      prmApi.createMembershipInvoice({ person_id: personId, season }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: feeKeys.all });
    },
  });

  const handleCreateInvoice = (personId) => {
    setCreatingForId(personId);
    createInvoice.mutate(
      { personId, season: data?.season },
      { onSettled: () => setCreatingForId(null) },
    );
  };

  // Bulk invoice creation
  const handleBulkCreate = async () => {
    if (bulkCreating) return;
    setBulkCreating(true);
    setBulkProgress({ created: 0, skipped: 0, total: 0 });

    const toCreate = noNikkiMembers.filter(m => !m.invoice_id && m.final_fee > 0);
    setBulkProgress(prev => ({ ...prev, total: toCreate.length }));

    let created = 0;
    let skipped = 0;

    for (const member of toCreate) {
      try {
        await prmApi.createMembershipInvoice({ person_id: member.id, season: data?.season });
        created++;
      } catch (err) {
        // 409 = already exists, skip silently
        if (err.response?.status === 409) {
          skipped++;
        } else {
          skipped++;
        }
      }
      setBulkProgress({ created, skipped, total: toCreate.length });
    }

    setBulkCreating(false);
    queryClient.invalidateQueries({ queryKey: feeKeys.all });
  };

  // Filter to members without Nikki data
  const noNikkiMembers = (data?.members ?? []).filter(m => m.nikki_total === null);

  // Sort
  const categoryOrder = {};
  Object.entries(data?.categories || {}).forEach(([slug, meta]) => {
    categoryOrder[slug] = meta.sort_order ?? 999;
  });

  const sortedMembers = [...noNikkiMembers].sort((a, b) => {
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
      case 'final_fee':
        cmp = a.final_fee - b.final_fee;
        break;
      default:
        cmp = 0;
    }
    return sortOrder === 'asc' ? cmp : -cmp;
  });

  const totalFee = sortedMembers.reduce((acc, m) => acc + m.final_fee, 0);
  const uninvoicedCount = sortedMembers.filter(m => !m.invoice_id && m.final_fee > 0).length;

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
          Gegevens konden niet worden geladen.
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

  if (!sortedMembers.length) {
    return (
      <PullToRefreshWrapper onRefresh={handleRefresh}>
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
      </PullToRefreshWrapper>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <SeasonSelector
            season={data?.season}
            isForecast={false}
            onForecastChange={() => {}}
            memberCount={sortedMembers.length}
          />
          <div className="flex items-center gap-3">
            <div className="text-sm text-gray-500 dark:text-gray-400">
              Totaal: <span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(totalFee, 2)}</span>
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

        {/* Table */}
        <div className="card overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <SortableHeader label="Voornaam" columnId="first_name" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} />
                <SortableHeader label="Achternaam" columnId="last_name" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} />
                <SortableHeader label="Categorie" columnId="category" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} />
                <SortableHeader label="Bedrag" columnId="final_fee" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} className="text-right" />
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Status
                </th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Actie
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {sortedMembers.map((member, index) => (
                <FeeRow
                  key={member.id}
                  member={member}
                  isOdd={index % 2 === 1}
                  categories={data?.categories}
                  isAdmin={isAdmin}
                  onCreateInvoice={handleCreateInvoice}
                  isCreating={creatingForId === member.id}
                />
              ))}
            </tbody>
            <tfoot className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <td colSpan="3" className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                  Totaal
                </td>
                <td className="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                  {formatCurrency(totalFee, 2)}
                </td>
                <td colSpan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </PullToRefreshWrapper>
  );
}
