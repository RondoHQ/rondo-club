import { Coins, FileText, Receipt, TrendingUp } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { useAllInvoicedCaseIds, useInvoices, useInvoiceStatistics } from '@/hooks/useInvoices';
import { useFeeList, feeKeys } from '@/hooks/useFees';
import { useCurrentSeason, useDisciplineCases } from '@/hooks/useDisciplineCases';
import { formatCurrency } from '@/utils/formatters';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContributieOverzicht } from '@/pages/Contributie/ContributieOverzicht';
import { isDoorbelastException, isDoorbelastNVT } from '@/utils/disciplineCases';

function toAmount(value) {
  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function FacturenStatusCard({ byStatus, href }) {
  const statusItems = [
    { key: 'draft', label: 'Concept', count: byStatus.draft || 0 },
    { key: 'sent', label: 'Verstuurd', count: byStatus.sent || 0 },
    { key: 'paid', label: 'Betaald', count: byStatus.paid || 0 },
    { key: 'overdue', label: 'Achterstallig', count: byStatus.overdue || 0 },
    { key: 'cancelled', label: 'Vervallen', count: byStatus.cancelled || 0 },
  ].filter((item) => item.count > 0);

  const content = (
    <div className="card p-4">
      <div className="flex items-center justify-between mb-2">
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Facturen</p>
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <Coins className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        </div>
      </div>
      {statusItems.length === 0 ? (
        <p className="text-xs text-gray-400 dark:text-gray-500 text-center py-3">Geen facturen</p>
      ) : (
        <div className="grid grid-cols-2 gap-2">
          {statusItems.map((item) => (
            <div key={item.key} className="text-center">
              <p className="text-xl font-semibold dark:text-gray-50">{item.count}</p>
              <p className="text-xs text-gray-400 dark:text-gray-500">{item.label}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );

  return href ? <Link to={href}>{content}</Link> : content;
}

function FacturenBedragenCard({ openstaandBedrag, openstaandCount, betaaldBedrag, betaaldCount, href }) {
  const content = (
    <div className="card p-4">
      <div className="flex items-center justify-between mb-2">
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Factuurbedragen</p>
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <Receipt className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        </div>
      </div>
      <div className="flex items-center justify-between gap-4">
        <div className="text-center flex-1">
          <p className="text-xl font-semibold dark:text-gray-50">{formatCurrency(openstaandBedrag, 2)}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">Openstaand ({openstaandCount})</p>
        </div>
        <div className="text-center flex-1 border-l border-gray-200 dark:border-gray-600 pl-4">
          <p className="text-xl font-semibold dark:text-gray-50">{formatCurrency(betaaldBedrag, 2)}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">Betaald ({betaaldCount})</p>
        </div>
      </div>
    </div>
  );

  return href ? <Link to={href}>{content}</Link> : content;
}

function NikkiStatsCard({ ontvangen, openstaand, href }) {
  const content = (
    <div className="card p-4">
      <div className="flex items-center justify-between mb-2">
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Nikki</p>
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <Coins className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        </div>
      </div>
      <div className="flex items-center justify-between gap-4">
        <div className="text-center flex-1">
          <p className="text-xl font-semibold dark:text-gray-50">{formatCurrency(ontvangen, 2)}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">Ontvangen</p>
        </div>
        <div className="text-center flex-1 border-l border-gray-200 dark:border-gray-600 pl-4">
          <p className="text-xl font-semibold dark:text-gray-50">{formatCurrency(openstaand, 2)}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">Nog te ontvangen</p>
        </div>
      </div>
    </div>
  );

  return href ? <Link to={href}>{content}</Link> : content;
}

function NogTeFacturerenCard({ contributieCount, tuchtzakenCount, href }) {
  const content = (
    <div className="card p-4">
      <div className="flex items-center justify-between mb-2">
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Nog te factureren</p>
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <FileText className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        </div>
      </div>
      <div className="flex items-center justify-between gap-4">
        <div className="text-center flex-1">
          <p className="text-xl font-semibold dark:text-gray-50">{contributieCount}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">Contributie</p>
        </div>
        <div className="text-center flex-1 border-l border-gray-200 dark:border-gray-600 pl-4">
          <p className="text-xl font-semibold dark:text-gray-50">{tuchtzakenCount}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">Tuchtzaken</p>
        </div>
      </div>
    </div>
  );

  return href ? <Link to={href}>{content}</Link> : content;
}

function formatDays(value) {
  if (value === null || value === undefined) return 'Geen data';
  return `${new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 1 }).format(value)} dagen`;
}

function PaymentStatisticsCard({ statistics, isLoading, error }) {
  const items = [
    {
      key: 'week',
      value: statistics ? formatCurrency(statistics.week.received_amount, 2) : '—',
      label: 'Afgelopen 7 dagen',
      detail: statistics ? `${statistics.week.payment_count} betalingen` : '',
    },
    {
      key: 'month',
      value: statistics ? formatCurrency(statistics.month.received_amount, 2) : '—',
      label: 'Afgelopen 30 dagen',
      detail: statistics ? `${statistics.month.payment_count} betalingen` : '',
    },
    {
      key: 'average',
      value: statistics ? formatDays(statistics.average_days_open) : '—',
      label: 'Gemiddeld open',
      detail: statistics ? `${statistics.paid_invoice_count} betaalde facturen in 30 dagen` : '',
    },
    {
      key: 'installments',
      value: statistics ? `${statistics.installment_plans.total_people} mensen` : '—',
      label: 'Betalen in termijnen',
      detail: statistics
        ? `${statistics.installment_plans.quarterly_3} in 3 · ${statistics.installment_plans.monthly_8} in 8`
        : '',
    },
  ];

  return (
    <section className="card">
      <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Betaalstatistieken</h2>
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <TrendingUp className="w-5 h-5 text-electric-cyan" />
        </div>
      </div>
      {error ? (
        <p className="p-4 text-sm text-red-600 dark:text-red-400">
          Betaalstatistieken konden niet worden geladen.
        </p>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 p-4 gap-4">
          {items.map((item, index) => (
            <div
              key={item.key}
              className={`text-center ${index % 2 === 1 ? 'sm:border-l sm:border-gray-200 sm:dark:border-gray-700 sm:pl-4' : ''} ${index > 0 ? 'xl:border-l xl:border-gray-200 xl:dark:border-gray-700 xl:pl-4' : 'xl:border-l-0 xl:pl-0'}`}
            >
              <p className="text-xl font-semibold text-gray-900 dark:text-gray-50">
                {isLoading ? '—' : item.value}
              </p>
              <p className="text-sm text-gray-500 dark:text-gray-400">{item.label}</p>
              <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">{isLoading ? '' : item.detail}</p>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

export default function FinanceDashboard() {
  useDocumentTitle('Financiën');

  const queryClient = useQueryClient();
  const { data: invoices = [], error: invoicesError } = useInvoices();
  const {
    data: invoiceStatistics,
    isLoading: isLoadingInvoiceStatistics,
    error: invoiceStatisticsError,
  } = useInvoiceStatistics();
  const { data: feeList } = useFeeList();
  const { data: currentSeason, isLoading: isLoadingSeason } = useCurrentSeason();
  const { data: disciplineCases = [] } = useDisciplineCases({
    seizoen: currentSeason?.id || null,
    enabled: !isLoadingSeason,
  });
  const { data: invoicedCaseIds = [] } = useAllInvoicedCaseIds();

  const handleRefresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['invoices'] }),
      queryClient.invalidateQueries({ queryKey: ['invoice-statistics'] }),
      queryClient.invalidateQueries({ queryKey: feeKeys.all }),
      queryClient.invalidateQueries({ queryKey: ['discipline-cases'] }),
      queryClient.invalidateQueries({ queryKey: ['invoiced-case-ids'] }),
    ]);
  };

  if (invoicesError) {
    return (
      <div className="card p-8 text-center">
        <p className="text-red-600 dark:text-red-400">
          Financieel dashboard kon niet worden geladen: {invoicesError.message}
        </p>
      </div>
    );
  }

  const invoiceTotals = invoices.reduce((acc, invoice) => {
    const amount = toAmount(invoice.total_amount);
    const status = invoice.status || 'draft';

    acc.count += 1;
    acc.amount += amount;
    acc.byStatus[status] = (acc.byStatus[status] || 0) + 1;
    acc.amountByStatus[status] = (acc.amountByStatus[status] || 0) + amount;
    return acc;
  }, {
    count: 0,
    amount: 0,
    byStatus: { draft: 0, sent: 0, paid: 0, overdue: 0, cancelled: 0 },
    amountByStatus: { draft: 0, sent: 0, paid: 0, overdue: 0, cancelled: 0 },
  });

  const members = feeList?.members ?? [];
  const billingMethod = feeList?.billing_method ?? 'nikki';
  const membersWithoutNikki = members.filter((m) => m.nikki_total === null);
  const uninvoicedMembers = membersWithoutNikki.filter((m) => !m.invoice_id && toAmount(m.final_fee) > 0);
  const invoicedCaseIdSet = new Set(invoicedCaseIds);
  const uninvoicedDisciplineCases = disciplineCases.filter((disciplineCase) => {
    if (invoicedCaseIdSet.has(disciplineCase.id)) return false;
    if (isDoorbelastNVT(disciplineCase.fields || {})) return false;
    if (isDoorbelastException(disciplineCase.fields || {})) return false;
    return true;
  });
  const nikkiMembers = members.filter((m) => m.nikki_total !== null);

  const nikkiOntvangen = nikkiMembers.reduce((sum, member) => {
    const total = toAmount(member.nikki_total);
    const saldo = toAmount(member.nikki_saldo);
    return sum + Math.max(total - saldo, 0);
  }, 0);

  const nikkiNogTeOntvangen = nikkiMembers.reduce(
    (sum, member) => sum + Math.max(toAmount(member.nikki_saldo), 0),
    0
  );

  const openstaandFacturenCount = invoiceTotals.byStatus.sent + invoiceTotals.byStatus.overdue;
  const openstaandFacturenBedrag = invoiceTotals.amountByStatus.sent + invoiceTotals.amountByStatus.overdue;
  const betaaldFacturenCount = invoiceTotals.byStatus.paid;
  const betaaldFacturenBedrag = invoiceTotals.amountByStatus.paid;

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <FacturenBedragenCard
            openstaandBedrag={openstaandFacturenBedrag}
            openstaandCount={openstaandFacturenCount}
            betaaldBedrag={betaaldFacturenBedrag}
            betaaldCount={betaaldFacturenCount}
            href="/financien/facturen"
          />
          <NogTeFacturerenCard
            contributieCount={uninvoicedMembers.length}
            tuchtzakenCount={uninvoicedDisciplineCases.length}
            href="/financien/contributie/nog-te-factureren"
          />
          <FacturenStatusCard byStatus={invoiceTotals.byStatus} href="/financien/facturen" />
          {billingMethod === 'nikki' && (
            <NikkiStatsCard
              ontvangen={nikkiOntvangen}
              openstaand={nikkiNogTeOntvangen}
              href="/financien/contributie"
            />
          )}
        </div>

        <PaymentStatisticsCard
          statistics={invoiceStatistics}
          isLoading={isLoadingInvoiceStatistics}
          error={invoiceStatisticsError}
        />

        <section className="card">
          <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Contributie overzicht</h2>
          </div>
          <div className="p-4">
            <ContributieOverzicht />
          </div>
        </section>

      </div>
    </PullToRefreshWrapper>
  );
}
