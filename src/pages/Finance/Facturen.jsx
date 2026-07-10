import { useState, useCallback, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Plus, Receipt, Square, CheckSquare, MinusSquare, Send, Loader2, Trash2, Copy, CalendarClock } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { useInvoices, useSendInvoice, useDeleteInvoice, useScheduleInvoice } from '@/hooks/useInvoices';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format, parseYmd } from '@/utils/dateFormat';
import { formatCurrency } from '@/utils/formatters';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';

const STATUS_FILTER_UNPAID = 'unpaid';
const STATUS_FILTER_ALL = 'all';

// Status badge colors
const statusColors = {
  draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  partially_paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  paid: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  overdue_warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
};

const statusLabels = {
  draft: 'Concept',
  sent: 'Verstuurd',
  partially_paid: 'Deels betaald',
  paid: 'Betaald',
  overdue: 'Achterstallig',
};

const typeLabels = {
  membership: 'Contributie',
  discipline: 'Tuchtzaken',
  manual: 'Handmatig',
  credit: 'Credit',
};

const typeColors = {
  membership: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
  discipline: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  manual: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
  credit: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
};

function StatusBadge({ status, reminderCount = 0, paidInstallments = 0, installmentCount = 0, scheduledSendDate = null }) {
  let colorKey = status;
  let label = statusLabels[status] || status;

  // A draft queued for automatic sending gets its own badge.
  if (status === 'draft' && scheduledSendDate) {
    return (
      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
        Ingepland · {format(parseYmd(scheduledSendDate), 'd MMM yyyy')}
      </span>
    );
  }

  // Derive partially paid status when some (but not all) installments are paid.
  if (status !== 'paid' && paidInstallments > 0 && paidInstallments < installmentCount) {
    colorKey = 'partially_paid';
    label = `Deels betaald (${paidInstallments}/${installmentCount})`;
  } else if (status === 'overdue' && reminderCount > 0) {
    if (reminderCount >= 2) {
      label = '2e herinnering';
      colorKey = 'overdue'; // red
    } else {
      label = '1e herinnering';
      colorKey = 'overdue_warning'; // amber/warning
    }
  }

  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[colorKey] || statusColors.draft}`}>
      {label}
    </span>
  );
}

const PLAN_OPTIONS = [
  { value: 'full', label: 'Volledig' },
  { value: 'quarterly_3', label: '3 termijnen' },
  { value: 'monthly_8', label: '8 termijnen' },
];

export default function Facturen() {
  useDocumentTitle('Facturen');

  const { data: currentUser } = useCurrentUser();
  // financieel_read reaches this list; selecting, bulk-sending and creating are writes.
  const canEditFinancieel = currentUser?.can_edit_financieel ?? false;

  // Selection state for bulk actions
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [bulkSending, setBulkSending] = useState(false);
  const [bulkDeleting, setBulkDeleting] = useState(false);
  const [bulkScheduling, setBulkScheduling] = useState(false);
  const [showScheduleInput, setShowScheduleInput] = useState(false);
  const [bulkScheduleDate, setBulkScheduleDate] = useState('');
  // action is 'send' | 'delete' | 'schedule' — labels the result panel; done counts completed items.
  const [bulkProgress, setBulkProgress] = useState({ action: 'send', done: 0, total: 0, errors: [] });

  const sendInvoice = useSendInvoice();
  const deleteInvoice = useDeleteInvoice();
  const scheduleInvoice = useScheduleInvoice();
  const bulkBusy = bulkSending || bulkDeleting || bulkScheduling;
  const todayInput = useMemo(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }, []);

  const toggleSelect = useCallback((id) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  // URL-based filters for persistence (controlled mode)
  const [searchParams, setSearchParams] = useSearchParams();

  const filters = useMemo(() => ({
    status: (() => {
      const rawStatus = searchParams.get('status');
      if (rawStatus === null) return STATUS_FILTER_UNPAID;
      if (rawStatus === STATUS_FILTER_ALL) return '';
      return rawStatus;
    })(),
    invoice_type: searchParams.get('invoice_type') || '',
    plan: searchParams.get('plan') || '',
    person_name: searchParams.get('person_name') || '',
    invoice_number: searchParams.get('invoice_number') || '',
    sent_by: searchParams.get('sent_by') || '',
  }), [searchParams]);

  const handleFilterChange = useCallback((colId, value) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (colId === 'status' && !value) {
        next.set('status', STATUS_FILTER_ALL);
        return next;
      }

      if (!value) {
        next.delete(colId);
      } else {
        next.set(colId, value);
      }

      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const handleClearFilters = useCallback(() => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      ['status', 'invoice_type', 'plan', 'person_name', 'invoice_number', 'sent_by'].forEach((k) => next.delete(k));
      next.set('status', STATUS_FILTER_ALL);
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const queryClient = useQueryClient();

  // Fetch all invoices — client-side filtering via DataTable
  // refetchOnMount ensures fresh data when navigating back after create/send actions
  const { data: invoices, isLoading, error } = useInvoices({}, { refetchOnMount: 'always' });

  const handleSelectAll = useCallback((visibleDraftIds) => {
    setSelectedIds((prev) => {
      const allSelected = visibleDraftIds.every((id) => prev.has(id));
      if (allSelected) {
        // Deselect all visible drafts
        const next = new Set(prev);
        visibleDraftIds.forEach((id) => next.delete(id));
        return next;
      }
      // Select all visible drafts
      return new Set([...prev, ...visibleDraftIds]);
    });
  }, []);

  const handleBulkSend = async () => {
    const ids = Array.from(selectedIds);
    setBulkSending(true);
    setBulkProgress({ action: 'send', done: 0, total: ids.length, errors: [] });

    const errors = [];
    for (let i = 0; i < ids.length; i++) {
      try {
        await sendInvoice.mutateAsync(ids[i]);
      } catch (err) {
        errors.push({ id: ids[i], message: err?.response?.data?.message || err.message });
      }
      setBulkProgress({ action: 'send', done: i + 1, total: ids.length, errors: [...errors] });
    }

    setBulkSending(false);
    setSelectedIds(new Set());
    queryClient.invalidateQueries({ queryKey: ['invoices'] });
  };

  const handleBulkDelete = async () => {
    const ids = Array.from(selectedIds);
    const message = ids.length === 1
      ? 'Weet je zeker dat je deze conceptfactuur wilt verwijderen? Dit kan niet ongedaan worden gemaakt.'
      : `Weet je zeker dat je ${ids.length} conceptfacturen wilt verwijderen? Dit kan niet ongedaan worden gemaakt.`;
    if (!window.confirm(message)) return;

    setBulkDeleting(true);
    setBulkProgress({ action: 'delete', done: 0, total: ids.length, errors: [] });

    const errors = [];
    for (let i = 0; i < ids.length; i++) {
      try {
        await deleteInvoice.mutateAsync(ids[i]);
      } catch (err) {
        errors.push({ id: ids[i], message: err?.response?.data?.message || err.message });
      }
      setBulkProgress({ action: 'delete', done: i + 1, total: ids.length, errors: [...errors] });
    }

    setBulkDeleting(false);
    setSelectedIds(new Set());
    queryClient.invalidateQueries({ queryKey: ['invoices'] });
  };

  const handleBulkSchedule = async () => {
    if (!bulkScheduleDate) return;
    const ids = Array.from(selectedIds);
    const scheduledSendDate = bulkScheduleDate.replaceAll('-', '');
    setBulkScheduling(true);
    setBulkProgress({ action: 'schedule', done: 0, total: ids.length, errors: [] });

    const errors = [];
    for (let i = 0; i < ids.length; i++) {
      try {
        await scheduleInvoice.mutateAsync({ id: ids[i], scheduledSendDate });
      } catch (err) {
        errors.push({ id: ids[i], message: err?.response?.data?.message || err.message });
      }
      setBulkProgress({ action: 'schedule', done: i + 1, total: ids.length, errors: [...errors] });
    }

    setBulkScheduling(false);
    setShowScheduleInput(false);
    setBulkScheduleDate('');
    setSelectedIds(new Set());
    queryClient.invalidateQueries({ queryKey: ['invoices'] });
  };

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['invoices'] });
  };

  // Build columns with checkbox column prepended
  const columns = useMemo(() => {
    const checkboxCol = {
      id: '_select',
      header: ({ table }) => {
        const visibleDraftIds = table.getFilteredRowModel().rows
          .filter((row) => row.original.status === 'draft')
          .map((row) => row.original.id);

        if (visibleDraftIds.length === 0) return null;

        const allSelected = visibleDraftIds.every((id) => selectedIds.has(id));
        const someSelected = visibleDraftIds.some((id) => selectedIds.has(id));

        return (
          <button
            onClick={() => handleSelectAll(visibleDraftIds)}
            className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
          >
            {allSelected ? (
              <CheckSquare className="w-5 h-5 text-electric-cyan" />
            ) : someSelected ? (
              <MinusSquare className="w-5 h-5 text-electric-cyan" />
            ) : (
              <Square className="w-5 h-5" />
            )}
          </button>
        );
      },
      cell: ({ row }) => {
        if (row.original.status !== 'draft') return null;
        const isSelected = selectedIds.has(row.original.id);
        return (
          <button
            onClick={(e) => { e.preventDefault(); e.stopPropagation(); toggleSelect(row.original.id); }}
            className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
          >
            {isSelected ? (
              <CheckSquare className="w-5 h-5 text-electric-cyan" />
            ) : (
              <Square className="w-5 h-5" />
            )}
          </button>
        );
      },
      enableSorting: false,
      enableHiding: false,
      enableColumnFilter: false,
      size: 44,
      meta: { className: 'w-10 !px-2' },
    };

    return [
      ...(canEditFinancieel ? [checkboxCol] : []),
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
        header: 'Naam',
        accessorFn: (row) => row.person?.name || row.customer_name || '',
        cell: ({ row }) =>
          row.original.person?.name ? (
            <div>
            <Link
              to={`/people/${row.original.person.id}`}
              className="text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan"
            >
              {row.original.person.name}
            </Link>
              {row.original.person.contact_name && (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Contactpersoon: {row.original.person.contact_name}
                </p>
              )}
            </div>
          ) : row.original.customer_name ? (
            <span className="text-gray-900 dark:text-gray-100">{row.original.customer_name}</span>
          ) : (
            <span className="text-gray-400">-</span>
          ),
        filterType: FILTER_TYPES.TEXT,
        filterLabel: 'Naam',
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
        cell: ({ row }) => {
          const effectiveType = row.original.invoice_kind === 'credit' ? 'credit' : row.original.invoice_type;
          return effectiveType ? (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${typeColors[effectiveType] || ''}`}>
              {typeLabels[effectiveType] || effectiveType}
            </span>
          ) : (
            <span className="text-gray-400">-</span>
          );
        },
        filterType: FILTER_TYPES.SELECT,
        filterLabel: 'Type',
        filterFn: (row, colId, value) => {
          if (!value) return true;
          if (value === 'credit') return row.original.invoice_kind === 'credit';
          if (value === 'manual') return row.original.invoice_type === 'manual' && row.original.invoice_kind !== 'credit';
          return row.getValue(colId) === value;
        },
        filterOptions: [
          { value: 'membership', label: 'Contributie' },
          { value: 'discipline', label: 'Tuchtzaken' },
          { value: 'manual', label: 'Handmatig' },
          { value: 'credit', label: 'Credit' },
        ],
        size: 130,
      }),
      createColumn({
        id: 'status',
        header: 'Status',
        accessorKey: 'status',
        cell: ({ row }) => (
          <StatusBadge
            status={row.original.status}
            reminderCount={row.original.reminder_count || 0}
            paidInstallments={row.original.paid_installments || 0}
            installmentCount={row.original.installment_count || 0}
            scheduledSendDate={row.original.scheduled_send_date}
          />
        ),
        filterType: FILTER_TYPES.SELECT,
        filterLabel: 'Status',
        getFilterLabel: (value) => (value === STATUS_FILTER_UNPAID ? 'Alle niet betaalde' : (statusLabels[value] || value)),
        filterFn: (row, colId, value) => {
          if (!value) return true;
          if (value === STATUS_FILTER_UNPAID) return row.getValue(colId) !== 'paid';
          return row.getValue(colId) === value;
        },
        filterOptions: [
          { value: STATUS_FILTER_UNPAID, label: 'Alle niet betaalde' },
          { value: 'draft', label: 'Concept' },
          { value: 'sent', label: 'Verstuurd' },
          { value: 'paid', label: 'Betaald' },
          { value: 'overdue', label: 'Achterstallig' },
        ],
        size: 120,
      }),
      createColumn({
        id: 'plan',
        header: 'Betaalplan',
        accessorKey: 'installment_plan',
        cell: ({ row, getValue }) => {
          const plan = getValue();
          if (!plan) return <span className="text-gray-400">-</span>;
          if (plan === 'full') return 'Volledig';
          const count = row.original.installment_count;
          if (count) return `${count} termijnen`;
          const found = PLAN_OPTIONS.find((o) => o.value === plan);
          return found ? found.label : plan;
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
      ...(canEditFinancieel ? [{
        id: 'actions',
        header: '',
        cell: ({ row }) => (
          <Link
            to={`/financien/facturen/nieuw?copyFrom=${row.original.id}`}
            onClick={(e) => e.stopPropagation()}
            title="Kopiëren naar nieuwe factuur"
            aria-label="Kopiëren naar nieuwe factuur"
            className="inline-flex items-center justify-center text-gray-400 hover:text-electric-cyan dark:hover:text-electric-cyan"
          >
            <Copy className="w-4 h-4" />
          </Link>
        ),
        enableSorting: false,
        enableHiding: false,
        enableColumnFilter: false,
        size: 44,
        meta: { className: 'w-10 !px-2 text-center' },
      }] : []),
    ];
  }, [selectedIds, toggleSelect, handleSelectAll, canEditFinancieel]);

  const rowClassName = useCallback((rowData) => {
    if (selectedIds.has(rowData.id)) {
      return 'bg-cyan-50 dark:bg-obsidian/30';
    }
    return '';
  }, [selectedIds]);

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
        {/* Bulk action toolbar */}
        {selectedIds.size > 0 && (
          <div className="mb-4 flex items-center gap-3 rounded-lg bg-electric-cyan/10 dark:bg-electric-cyan/5 border border-electric-cyan/20 px-4 py-3">
            <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
              {selectedIds.size} {selectedIds.size === 1 ? 'factuur' : 'facturen'} geselecteerd
            </span>
            <button
              onClick={handleBulkSend}
              disabled={bulkBusy}
              className="btn-primary gap-2 text-sm py-1.5 px-3"
            >
              {bulkSending ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Versturen ({bulkProgress.done}/{bulkProgress.total})...
                </>
              ) : (
                <>
                  <Send className="w-4 h-4" />
                  Verstuur {selectedIds.size === 1 ? 'factuur' : 'alle'}
                </>
              )}
            </button>
            {showScheduleInput ? (
              <div className="flex items-center gap-2">
                <input
                  type="date"
                  value={bulkScheduleDate}
                  min={todayInput}
                  onChange={(e) => setBulkScheduleDate(e.target.value)}
                  className="input text-sm py-1 px-2"
                />
                <button
                  onClick={handleBulkSchedule}
                  disabled={bulkBusy || !bulkScheduleDate}
                  className="btn-tertiary gap-2 text-sm py-1.5 px-3"
                >
                  {bulkScheduling ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin" />
                      Inplannen ({bulkProgress.done}/{bulkProgress.total})...
                    </>
                  ) : (
                    <>
                      <CalendarClock className="w-4 h-4" />
                      Inplannen
                    </>
                  )}
                </button>
                {!bulkBusy && (
                  <button
                    onClick={() => { setShowScheduleInput(false); setBulkScheduleDate(''); }}
                    className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                  >
                    Annuleer
                  </button>
                )}
              </div>
            ) : (
              <button
                onClick={() => setShowScheduleInput(true)}
                disabled={bulkBusy}
                className="btn-tertiary gap-2 text-sm py-1.5 px-3"
              >
                <CalendarClock className="w-4 h-4" />
                Inplannen voor…
              </button>
            )}
            <button
              onClick={handleBulkDelete}
              disabled={bulkBusy}
              className="btn-danger gap-2 text-sm py-1.5 px-3"
            >
              {bulkDeleting ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Verwijderen ({bulkProgress.done}/{bulkProgress.total})...
                </>
              ) : (
                <>
                  <Trash2 className="w-4 h-4" />
                  Verwijder {selectedIds.size === 1 ? 'factuur' : 'alle'}
                </>
              )}
            </button>
            {!bulkBusy && (
              <button
                onClick={() => setSelectedIds(new Set())}
                className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
              >
                Deselecteer
              </button>
            )}
          </div>
        )}

        {/* Bulk action result feedback */}
        {!bulkBusy && bulkProgress.total > 0 && (
          <div className={`mb-4 rounded-lg px-4 py-3 text-sm ${
            bulkProgress.errors.length > 0
              ? 'bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800'
              : 'bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800'
          }`}>
            <p className="font-medium text-gray-900 dark:text-gray-100">
              {bulkProgress.done - bulkProgress.errors.length} van {bulkProgress.total} {bulkProgress.total === 1 ? 'factuur' : 'facturen'} {bulkProgress.action === 'delete' ? 'verwijderd' : bulkProgress.action === 'schedule' ? 'ingepland' : 'verstuurd'}
              {bulkProgress.errors.length > 0 && `, ${bulkProgress.errors.length} mislukt`}
            </p>
            {bulkProgress.errors.map((err) => (
              <p key={err.id} className="mt-1 text-red-600 dark:text-red-400">
                Factuur {err.id}: {err.message}
              </p>
            ))}
            <button
              onClick={() => setBulkProgress({ action: 'send', done: 0, total: 0, errors: [] })}
              className="mt-2 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline"
            >
              Sluiten
            </button>
          </div>
        )}

        <DataTable
          storageKey="facturen"
          data={invoices || []}
          columns={columns}
          isLoading={isLoading}
          emptyIcon={<Receipt className="w-8 h-8 text-gray-400 dark:text-gray-500" />}
          emptyTitle="Geen facturen gevonden"
          emptyDescription="Er zijn geen facturen die voldoen aan de geselecteerde filters."
          filters={filters}
          onFilterChange={handleFilterChange}
          onClearFilters={handleClearFilters}
          rowClassName={rowClassName}
          toolbarEnd={canEditFinancieel ? (
            <Link to="/financien/facturen/nieuw" className="btn-primary gap-2">
              <Plus className="w-4 h-4" /> Nieuwe factuur
            </Link>
          ) : null}
        />
      </div>
    </PullToRefreshWrapper>
  );
}
