import { useState, useCallback, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Receipt, Filter, ChevronUp, ChevronDown } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { useInvoices } from '@/hooks/useInvoices';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format, parse } from '@/utils/dateFormat';
import { formatCurrency } from '@/utils/formatters';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';

// Status badge colors
const statusColors = {
  draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  paid: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

// Status display labels
const statusLabels = {
  draft: 'Concept',
  sent: 'Verstuurd',
  paid: 'Betaald',
  overdue: 'Verlopen',
};

/**
 * Status badge component for invoices
 */
function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[status] || statusColors.draft}`}>
      {statusLabels[status] || status}
    </span>
  );
}

export default function Facturen() {
  useDocumentTitle('Facturen');

  // URL-based status filter via useSearchParams
  const [searchParams, setSearchParams] = useSearchParams();
  const statusFilter = searchParams.get('status') || '';

  const updateStatusFilter = useCallback((value) => {
    if (value === '') {
      searchParams.delete('status');
    } else {
      searchParams.set('status', value);
    }
    setSearchParams(searchParams, { replace: true });
  }, [searchParams, setSearchParams]);

  // Sorting state
  const [sortConfig, setSortConfig] = useState({ key: 'created', direction: 'desc' });

  const queryClient = useQueryClient();

  // Fetch invoices with optional status filter
  const { data: invoices, isLoading, error } = useInvoices({
    status: statusFilter || undefined,
  });

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['invoices'] });
  };

  // Sortable column handler
  const handleSort = useCallback((key) => {
    setSortConfig(prev => ({
      key,
      direction: prev.key === key && prev.direction === 'asc' ? 'desc' : 'asc',
    }));
  }, []);

  // Sorted invoices
  const sortedInvoices = useMemo(() => {
    if (!invoices || invoices.length === 0) return [];

    const sorted = [...invoices];
    const { key, direction } = sortConfig;

    sorted.sort((a, b) => {
      let aVal, bVal;

      switch (key) {
        case 'invoice_number':
          aVal = a.invoice_number || '';
          bVal = b.invoice_number || '';
          break;
        case 'person_name':
          aVal = a.person?.name || '';
          bVal = b.person?.name || '';
          break;
        case 'total_amount':
          aVal = parseFloat(a.total_amount) || 0;
          bVal = parseFloat(b.total_amount) || 0;
          break;
        case 'status':
          aVal = a.status || '';
          bVal = b.status || '';
          break;
        case 'sent_date':
          aVal = a.sent_date ? parse(a.sent_date, 'yyyyMMdd', new Date()).getTime() : 0;
          bVal = b.sent_date ? parse(b.sent_date, 'yyyyMMdd', new Date()).getTime() : 0;
          break;
        case 'created':
          aVal = new Date(a.created).getTime();
          bVal = new Date(b.created).getTime();
          break;
        default:
          return 0;
      }

      if (aVal < bVal) return direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return direction === 'asc' ? 1 : -1;
      return 0;
    });

    return sorted;
  }, [invoices, sortConfig]);

  // Sort indicator component
  const SortIndicator = ({ columnKey }) => {
    if (sortConfig.key !== columnKey) return null;
    return sortConfig.direction === 'asc' ? (
      <ChevronUp className="w-4 h-4 inline ml-1" />
    ) : (
      <ChevronDown className="w-4 h-4 inline ml-1" />
    );
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="card p-8 text-center">
        <p className="text-red-600 dark:text-red-400">
          Failed to load invoices: {error.message}
        </p>
      </div>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <h1 className="text-2xl font-bold text-brand-gradient">Facturen</h1>
        </div>

        {/* Filter bar */}
        <div className="flex flex-wrap items-center gap-3">
          <Filter className="w-4 h-4 text-gray-400" />

          {/* Status filter */}
          <select
            value={statusFilter}
            onChange={(e) => updateStatusFilter(e.target.value)}
            className="text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-electric-cyan focus:border-electric-cyan"
          >
            <option value="">Alle statussen</option>
            <option value="draft">Concept</option>
            <option value="sent">Verstuurd</option>
            <option value="paid">Betaald</option>
            <option value="overdue">Verlopen</option>
          </select>
        </div>

        {/* Invoice table */}
        {!sortedInvoices || sortedInvoices.length === 0 ? (
          <div className="card p-12 text-center">
            <Receipt className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
            <h3 className="text-lg font-medium mb-1">Geen facturen gevonden</h3>
            <p className="text-gray-500 dark:text-gray-400">
              Er zijn geen facturen die voldoen aan de geselecteerde filters.
            </p>
          </div>
        ) : (
          <div className="card overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                    onClick={() => handleSort('invoice_number')}
                  >
                    Factuurnummer
                    <SortIndicator columnKey="invoice_number" />
                  </th>
                  <th
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                    onClick={() => handleSort('person_name')}
                  >
                    Lid
                    <SortIndicator columnKey="person_name" />
                  </th>
                  <th
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                    onClick={() => handleSort('total_amount')}
                  >
                    Bedrag
                    <SortIndicator columnKey="total_amount" />
                  </th>
                  <th
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                    onClick={() => handleSort('status')}
                  >
                    Status
                    <SortIndicator columnKey="status" />
                  </th>
                  <th
                    className="hidden sm:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                    onClick={() => handleSort('sent_date')}
                  >
                    Verstuurd
                    <SortIndicator columnKey="sent_date" />
                  </th>
                  <th
                    className="hidden sm:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                    onClick={() => handleSort('created')}
                  >
                    Aangemaakt
                    <SortIndicator columnKey="created" />
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {sortedInvoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td className="px-4 py-3">
                      <Link
                        to={`/financien/facturen/${invoice.id}`}
                        className="text-electric-cyan dark:text-electric-cyan hover:underline font-medium"
                      >
                        {invoice.invoice_number}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      {invoice.person?.name ? (
                        <Link
                          to={`/people/${invoice.person.id}`}
                          className="text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan"
                        >
                          {invoice.person.name}
                        </Link>
                      ) : (
                        <span className="text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">
                      {formatCurrency(invoice.total_amount, 2)}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={invoice.status} />
                    </td>
                    <td className="hidden sm:table-cell px-4 py-3 text-gray-600 dark:text-gray-400 text-sm">
                      {invoice.sent_date ? (
                        format(parse(invoice.sent_date, 'yyyyMMdd', new Date()), 'd MMM yyyy')
                      ) : (
                        '-'
                      )}
                    </td>
                    <td className="hidden sm:table-cell px-4 py-3 text-gray-600 dark:text-gray-400 text-sm">
                      {format(new Date(invoice.created), 'd MMM yyyy')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </PullToRefreshWrapper>
  );
}
