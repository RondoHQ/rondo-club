import { useCallback, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Plus, Receipt } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { useInvoices } from '@/hooks/useInvoices';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format, parseYmd } from '@/utils/dateFormat';
import { formatCurrency } from '@/utils/formatters';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';

// Status badge colors
const statusColors = {
  draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  paid: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

const statusLabels = {
  draft: 'Concept',
  sent: 'Verstuurd',
  paid: 'Betaald',
  overdue: 'Verlopen',
};

const typeLabels = {
  membership: 'Contributie',
  discipline: 'Tuchtrecht',
  manual: 'Handmatig',
};

const typeColors = {
  membership: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
  discipline: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  manual: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
};

function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[status] || statusColors.draft}`}>
      {statusLabels[status] || status}
    </span>
  );
}

const PLAN_OPTIONS = [
  { value: 'full', label: 'Volledig' },
  { value: 'quarterly_3', label: '3 termijnen' },
  { value: 'monthly_8', label: 'Meerdere termijnen' },
];

const COLUMNS = [
  createColumn({
    id: 'invoice_number',
    header: 'Factuurnummer',
    accessorKey: 'invoice_number',
    cell: ({ row }) => (
      <Link
        to={`/financien/facturen/${row.original.id}`}
        className="text-electric-cyan dark:text-electric-cyan hover:underline font-medium"
      >
        {row.original.invoice_number}
      </Link>
    ),
    filterType: FILTER_TYPES.TEXT,
    size: 160,
  }),
  createColumn({
    id: 'person_name',
    header: 'Lid',
    accessorFn: (row) => row.person?.name || '',
    cell: ({ row }) =>
      row.original.person?.name ? (
        <Link
          to={`/people/${row.original.person.id}`}
          className="text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan"
        >
          {row.original.person.name}
        </Link>
      ) : (
        <span className="text-gray-400">-</span>
      ),
    filterType: FILTER_TYPES.TEXT,
    filterLabel: 'Lid',
  }),
  createColumn({
    id: 'total_amount',
    header: 'Bedrag',
    accessorFn: (row) => parseFloat(row.total_amount) || 0,
    cell: ({ row }) => (
      <span className="font-medium text-gray-900 dark:text-gray-100">
        {formatCurrency(row.original.total_amount, 2)}
      </span>
    ),
    filterType: null,
    headerClassName: 'text-right',
    className: 'text-right',
    size: 110,
  }),
  createColumn({
    id: 'invoice_type',
    header: 'Type',
    accessorKey: 'invoice_type',
    cell: ({ row }) =>
      row.original.invoice_type ? (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${typeColors[row.original.invoice_type] || ''}`}>
          {typeLabels[row.original.invoice_type] || row.original.invoice_type}
        </span>
      ) : (
        <span className="text-gray-400">-</span>
      ),
    filterType: FILTER_TYPES.SELECT,
    filterLabel: 'Type',
    filterOptions: [
      { value: 'membership', label: 'Contributie' },
      { value: 'discipline', label: 'Tuchtrecht' },
      { value: 'manual', label: 'Handmatig' },
    ],
    size: 130,
  }),
  createColumn({
    id: 'status',
    header: 'Status',
    accessorKey: 'status',
    cell: ({ row }) => <StatusBadge status={row.original.status} />,
    filterType: FILTER_TYPES.SELECT,
    filterLabel: 'Status',
    filterOptions: [
      { value: 'draft', label: 'Concept' },
      { value: 'sent', label: 'Verstuurd' },
      { value: 'paid', label: 'Betaald' },
      { value: 'overdue', label: 'Verlopen' },
    ],
    size: 120,
  }),
  createColumn({
    id: 'plan',
    header: 'Betaalplan',
    accessorKey: 'payment_plan',
    cell: ({ getValue }) => {
      const plan = getValue();
      const found = PLAN_OPTIONS.find((o) => o.value === plan);
      return found ? found.label : plan ? plan : <span className="text-gray-400">-</span>;
    },
    filterType: FILTER_TYPES.SELECT,
    filterLabel: 'Betaalplan',
    filterOptions: PLAN_OPTIONS,
    defaultHidden: true,
  }),
  createColumn({
    id: 'sent_date',
    header: 'Verstuurd',
    accessorFn: (row) => (row.sent_date ? parseYmd(row.sent_date).getTime() : 0),
    cell: ({ row }) =>
      row.original.sent_date ? format(parseYmd(row.original.sent_date), 'd MMM yyyy') : '-',
    filterType: null,
    size: 130,
  }),
  createColumn({
    id: 'reminder_sent_at',
    header: 'Herinnering',
    accessorFn: (row) => (row.reminder_sent_at ? new Date(row.reminder_sent_at).getTime() : 0),
    cell: ({ row }) =>
      row.original.reminder_sent_at ? format(new Date(row.original.reminder_sent_at), 'd MMM yyyy') : '-',
    filterType: null,
    size: 130,
  }),
  createColumn({
    id: 'sent_by',
    header: 'Verstuurd door',
    accessorFn: (row) => row.sent_by?.name || '',
    cell: ({ row }) => row.original.sent_by?.name || '-',
    filterType: FILTER_TYPES.TEXT,
    filterLabel: 'Verstuurd door',
    size: 170,
  }),
  createColumn({
    id: 'created',
    header: 'Aangemaakt',
    accessorFn: (row) => new Date(row.created).getTime(),
    cell: ({ row }) => format(new Date(row.original.created), 'd MMM yyyy'),
    filterType: null,
    size: 130,
  }),
];

export default function Facturen() {
  useDocumentTitle('Facturen');

  // URL-based filters for persistence (controlled mode)
  const [searchParams, setSearchParams] = useSearchParams();

  const filters = useMemo(() => ({
    status: searchParams.get('status') || '',
    invoice_type: searchParams.get('invoice_type') || '',
    plan: searchParams.get('plan') || '',
    person_name: searchParams.get('person_name') || '',
    invoice_number: searchParams.get('invoice_number') || '',
    sent_by: searchParams.get('sent_by') || '',
  }), [searchParams]);

  const handleFilterChange = useCallback((colId, value) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (!value) next.delete(colId);
      else next.set(colId, value);
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const handleClearFilters = useCallback(() => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      ['status', 'invoice_type', 'plan', 'person_name', 'invoice_number', 'sent_by'].forEach((k) => next.delete(k));
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const queryClient = useQueryClient();

  // Fetch all invoices — client-side filtering via DataTable
  // refetchOnMount ensures fresh data when navigating back after create/send actions
  const { data: invoices, isLoading, error } = useInvoices({}, { refetchOnMount: 'always' });

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['invoices'] });
  };

  if (error) {
    return (
      <div className="card p-8 text-center">
        <p className="text-red-600 dark:text-red-400">
          Facturen konden niet worden geladen: {error.message}
        </p>
      </div>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div>
        <DataTable
          storageKey="facturen"
          data={invoices || []}
          columns={COLUMNS}
          isLoading={isLoading}
          emptyIcon={<Receipt className="w-8 h-8 text-gray-400 dark:text-gray-500" />}
          emptyTitle="Geen facturen gevonden"
          emptyDescription="Er zijn geen facturen die voldoen aan de geselecteerde filters."
          filters={filters}
          onFilterChange={handleFilterChange}
          onClearFilters={handleClearFilters}
          toolbarEnd={(
            <Link to="/financien/facturen/nieuw" className="btn btn-primary inline-flex items-center gap-2">
              <Plus className="w-4 h-4" /> Nieuwe factuur
            </Link>
          )}
        />
      </div>
    </PullToRefreshWrapper>
  );
}
