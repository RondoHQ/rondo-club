import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { RefreshCw } from 'lucide-react';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useTournamentPayments } from '@/hooks/useTournaments';
import { formatTournamentCurrency, formatTournamentDate, tournamentPaymentStatus, tournamentPaymentToneClasses } from './tournamentFormatters';

const EMPTY_PAYMENTS = [];
const paymentGroup = (row) => row.payment_state === 'paid' ? 'paid' : row.total_amount > 0 ? 'open' : 'free';

function PaymentTotals({ rows }) {
  const totals = rows.reduce((result, row) => {
    result[paymentGroup(row)] += Math.round(row.total_amount * 100);
    return result;
  }, { paid: 0, open: 0, free: 0 });
  return (
    <p className="max-w-64 text-sm text-gray-600 sm:max-w-none dark:text-gray-400" aria-live="polite">
      {rows.length} inschrijvingen · Betaald: <strong className="tabular-nums">{formatTournamentCurrency(totals.paid / 100)}</strong> · Openstaand: <strong className="tabular-nums">{formatTournamentCurrency(totals.open / 100)}</strong>
    </p>
  );
}

export default function TournamentPayments() {
  useDocumentTitle('Toernooibetalingen');
  const { data: user } = useCurrentUser();
  const { data: payments = EMPTY_PAYMENTS, isLoading, isFetching, error, refetch } = useTournamentPayments();
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = Object.fromEntries(['tournament_id', 'team_name', 'payment_state'].flatMap((key) => searchParams.get(key) ? [[key, searchParams.get(key)]] : []));
  const canManage = user?.can_manage_tournaments;
  const canViewInvoices = user?.can_access_financieel;
  const columns = useMemo(() => [
    createColumn({
      id: 'tournament_id', header: 'Toernooi', accessorFn: (row) => String(row.tournament_id),
      filterType: FILTER_TYPES.SELECT,
      filterOptions: Array.from(new Map(payments.map((row) => [String(row.tournament_id), row.tournament_name])), ([value, label]) => ({ value, label })).sort((a, b) => a.label.localeCompare(b.label, 'nl')),
      sortingFn: (a, b) => a.original.tournament_name.localeCompare(b.original.tournament_name, 'nl'),
      cell: ({ row }) => canManage ? <Link className="text-bright-cobalt underline underline-offset-2 dark:text-electric-cyan" to={`/toernooien/${row.original.tournament_id}`}>{row.original.tournament_name}</Link> : row.original.tournament_name,
    }),
    createColumn({ id: 'team_name', header: 'Clubteam', accessorKey: 'team_name', filterType: FILTER_TYPES.TEXT }),
    createColumn({ id: 'registered_team_count', header: 'Teams', accessorKey: 'registered_team_count', className: 'tabular-nums' }),
    createColumn({
      id: 'payment_state', header: 'Betaalstatus', accessorFn: paymentGroup,
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [{ value: 'open', label: 'Openstaand' }, { value: 'paid', label: 'Betaald' }, { value: 'free', label: 'Geen betaling nodig' }],
      cell: ({ row }) => {
        const status = paymentGroup(row.original) === 'free' ? { label: 'Geen betaling nodig', tone: 'pending' } : tournamentPaymentStatus(row.original);
        return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${tournamentPaymentToneClasses(status.tone)}`}>{status.label}</span>;
      },
    }),
    createColumn({ id: 'total_amount', header: 'Bedrag', accessorKey: 'total_amount', className: 'text-right tabular-nums', headerClassName: 'text-right', cell: ({ getValue }) => formatTournamentCurrency(getValue()) }),
    createColumn({
      id: 'invoice_number', header: 'Factuur', accessorKey: 'invoice_number',
      cell: ({ row }) => row.original.invoice_id && canViewInvoices ? <Link className="text-bright-cobalt underline underline-offset-2 dark:text-electric-cyan" to={`/financien/facturen/${row.original.invoice_id}`}>{row.original.invoice_number || 'Factuur bekijken'}</Link> : row.original.invoice_number || '—',
    }),
    createColumn({ id: 'paid_at', header: 'Betaald op', accessorKey: 'paid_at', cell: ({ getValue }) => getValue() ? formatTournamentDate(getValue(), true) : '—' }),
    createColumn({ id: 'payment_method', header: 'Betaalwijze', accessorKey: 'payment_method', cell: ({ getValue }) => ({ manual: 'Handmatig geregistreerd', ideal: 'iDEAL', creditcard: 'Creditcard', banktransfer: 'Overboeking' }[getValue()] || getValue() || '—') }),
  ], [payments, canManage, canViewInvoices]);

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Toernooibetalingen</h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Betaalde en openstaande bijdragen van ingeschreven teams, met de actuele status uit de facturen.</p>
        </div>
        <button type="button" className="btn-tertiary inline-flex min-h-11 items-center justify-center gap-2" disabled={isFetching} onClick={() => refetch()}>
          <RefreshCw aria-hidden="true" className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} /> Vernieuwen
        </button>
      </div>
      {error ? <div role="alert" className="card p-6 text-sm text-red-600 dark:text-red-400">De toernooibetalingen konden niet worden geladen. Kies Vernieuwen om het opnieuw te proberen.</div> : (
        <DataTable
          data={payments} columns={columns} isLoading={isLoading} storageKey="tournament-payments"
          filters={filters}
          onFilterChange={(key, value) => setSearchParams((previous) => { const next = new URLSearchParams(previous); if (value) next.set(key, value); else next.delete(key); return next; }, { replace: true })}
          onClearFilters={() => setSearchParams({}, { replace: true })}
          emptyTitle="Geen inschrijvingen gevonden"
          emptyDescription="Zodra een team zich inschrijft, verschijnt de bijdrage hier. Pas eventueel de filters aan."
          toolbarEnd={({ filteredRows }) => isLoading ? null : <PaymentTotals rows={filteredRows} />}
        />
      )}
    </div>
  );
}
