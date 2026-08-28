import { useSearchParams } from 'react-router-dom';
import { TrendingUp } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { useInvoiceStatistics } from '@/hooks/useInvoices';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { formatCurrency } from '@/utils/formatters';

const INVOICE_TYPES = [
  { value: '', label: 'Alle factuursoorten' },
  { value: 'membership', label: 'Contributie' },
  { value: 'discipline', label: 'Tuchtzaken' },
  { value: 'manual', label: 'Handmatig' },
  { value: 'volunteer_fine', label: 'Vrijwilligersboetes' },
];

const dateFormatter = new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short' });
const monthFormatter = new Intl.DateTimeFormat('nl-NL', { month: 'short', year: '2-digit' });
const percentageFormatter = new Intl.NumberFormat('nl-NL', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const PAYMENT_CENTER_LABEL = ['betaald +', 'termijnen'];

function parseLocalDate(value) {
  return new Date(`${value}T12:00:00`);
}

function formatDays(value) {
  if (value === null || value === undefined) return 'Geen data';
  return `${new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 1 }).format(value)} dagen`;
}

function StatisticsSummary({ statistics }) {
  const items = [
    {
      key: 'week',
      value: formatCurrency(statistics.week.received_amount, 2),
      label: 'Afgelopen 7 dagen',
      detail: `${statistics.week.payment_count} betalingen`,
    },
    {
      key: 'month',
      value: formatCurrency(statistics.month.received_amount, 2),
      label: 'Afgelopen 30 dagen',
      detail: `${statistics.month.payment_count} betalingen`,
    },
    {
      key: 'average',
      value: formatDays(statistics.average_days_open),
      label: 'Gemiddeld open',
      detail: `${statistics.paid_invoice_count} betaalde facturen in 30 dagen`,
    },
    {
      key: 'installments',
      value: `${statistics.installment_plans.total_people} mensen`,
      label: 'Betalen in termijnen',
      detail: `${statistics.installment_plans.quarterly_3} in 3 · ${statistics.installment_plans.monthly_8} in 8`,
    },
  ];

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      {items.map((item) => (
        <div key={item.key} className="card p-4 text-center">
          <p className="text-xl font-semibold text-gray-900 dark:text-gray-50">{item.value}</p>
          <p className="text-sm text-gray-500 dark:text-gray-400">{item.label}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">{item.detail}</p>
        </div>
      ))}
    </div>
  );
}

function LineChart({ data }) {
  const width = 900;
  const height = 280;
  const padding = { top: 24, right: 20, bottom: 44, left: 70 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const maxAmount = Math.max(...data.map((item) => item.amount), 1);
  const points = data.map((item, index) => ({
    ...item,
    x: padding.left + (index / Math.max(data.length - 1, 1)) * chartWidth,
    y: padding.top + chartHeight - (item.amount / maxAmount) * chartHeight,
  }));
  const linePath = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ');
  const areaPath = points.length > 0
    ? `${linePath} L ${points[points.length - 1].x} ${padding.top + chartHeight} L ${points[0].x} ${padding.top + chartHeight} Z`
    : '';
  const labelIndexes = new Set([0, 5, 10, 15, 20, 25, data.length - 1]);

  return (
    <svg viewBox={`0 0 ${width} ${height}`} className="w-full min-w-[640px]" role="img" aria-label="Inkomsten per dag over de afgelopen 30 dagen">
      {[0, 0.5, 1].map((ratio) => {
        const y = padding.top + chartHeight - ratio * chartHeight;
        return (
          <g key={ratio}>
            <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} className="stroke-gray-200 dark:stroke-gray-700" strokeWidth="1" />
            <text x={padding.left - 10} y={y + 4} textAnchor="end" className="fill-gray-400 text-[11px]">
              {formatCurrency(maxAmount * ratio, 0)}
            </text>
          </g>
        );
      })}
      <path d={areaPath} className="fill-cyan-100/70 dark:fill-cyan-900/20" />
      <path d={linePath} fill="none" className="stroke-electric-cyan" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
      {points.map((point, index) => (
        <g key={point.date}>
          <circle cx={point.x} cy={point.y} r="4" className="fill-electric-cyan stroke-white dark:stroke-gray-800" strokeWidth="2">
            <title>{`${dateFormatter.format(parseLocalDate(point.date))}: ${formatCurrency(point.amount, 2)} (${point.payment_count} betalingen)`}</title>
          </circle>
          {labelIndexes.has(index) && (
            <text x={point.x} y={height - 15} textAnchor="middle" className="fill-gray-400 text-[11px]">
              {dateFormatter.format(parseLocalDate(point.date))}
            </text>
          )}
        </g>
      ))}
    </svg>
  );
}

function BarChart({ data }) {
  const width = 900;
  const height = 300;
  const padding = { top: 24, right: 20, bottom: 54, left: 70 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const maxAmount = Math.max(...data.map((item) => item.amount), 1);
  const slotWidth = chartWidth / Math.max(data.length, 1);
  const barWidth = Math.min(slotWidth * 0.62, 50);

  return (
    <svg viewBox={`0 0 ${width} ${height}`} className="w-full min-w-[640px]" role="img" aria-label="Inkomsten per maand over de afgelopen 12 maanden">
      {[0, 0.5, 1].map((ratio) => {
        const y = padding.top + chartHeight - ratio * chartHeight;
        return (
          <g key={ratio}>
            <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} className="stroke-gray-200 dark:stroke-gray-700" strokeWidth="1" />
            <text x={padding.left - 10} y={y + 4} textAnchor="end" className="fill-gray-400 text-[11px]">
              {formatCurrency(maxAmount * ratio, 0)}
            </text>
          </g>
        );
      })}
      {data.map((item, index) => {
        const barHeight = (item.amount / maxAmount) * chartHeight;
        const x = padding.left + index * slotWidth + (slotWidth - barWidth) / 2;
        const y = padding.top + chartHeight - barHeight;
        return (
          <g key={item.month}>
            <rect x={x} y={y} width={barWidth} height={barHeight} rx="4" className="fill-electric-cyan">
              <title>{`${monthFormatter.format(parseLocalDate(`${item.month}-01`))}: ${formatCurrency(item.amount, 2)} (${item.payment_count} betalingen)`}</title>
            </rect>
            <text x={x + barWidth / 2} y={height - 18} textAnchor="middle" className="fill-gray-400 text-[11px]">
              {monthFormatter.format(parseLocalDate(`${item.month}-01`))}
            </text>
          </g>
        );
      })}
    </svg>
  );
}

function DonutChart({ total, segments, percentage, centerLabel, ariaLabel }) {
  const radius = 68;
  const circumference = 2 * Math.PI * radius;
  const centerLabelLines = Array.isArray(centerLabel) ? centerLabel : [centerLabel];
  let previousLength = 0;

  return (
    <div className="flex flex-col items-center justify-center min-h-[280px] gap-5">
      <svg viewBox="0 0 180 180" className="w-44 h-44" role="img" aria-label={ariaLabel}>
        <circle cx="90" cy="90" r={radius} fill="none" className="stroke-gray-200 dark:stroke-gray-700" strokeWidth="32" />
        {segments.map((segment) => {
          const length = total > 0 ? (segment.value / total) * circumference : 0;
          const dashOffset = -previousLength;
          previousLength += length;

          if (length === 0) return null;

          return (
            <circle
              key={segment.key}
              cx="90"
              cy="90"
              r={radius}
              fill="none"
              className={segment.strokeClass}
              strokeWidth="32"
              strokeDasharray={`${length} ${circumference - length}`}
              strokeDashoffset={dashOffset}
              transform="rotate(-90 90 90)"
            />
          );
        })}
        <text x="90" y="85" textAnchor="middle" className="fill-gray-900 dark:fill-gray-50 text-[30px] font-semibold">
          {percentage}%
        </text>
        <text
          x="90"
          y={centerLabelLines.length > 1 ? 103 : 108}
          textAnchor="middle"
          className="fill-gray-500 dark:fill-gray-400 text-[12px]"
        >
          {centerLabelLines.map((line, index) => (
            <tspan key={`${line}-${index}`} x="90" dy={index === 0 ? 0 : 14}>
              {line}
            </tspan>
          ))}
        </text>
      </svg>
      <div className="flex flex-wrap justify-center gap-x-4 gap-y-2 text-sm">
        {segments.map((segment) => (
          <div key={segment.key} className="flex items-center gap-2">
            <span className={`w-3 h-3 rounded-full ${segment.dotClass}`} />
            <span className="text-gray-600 dark:text-gray-300">
              {segment.label} <strong className="text-gray-900 dark:text-gray-50">{segment.formattedValue}</strong>
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}

function InvoicePaymentPieChart({ data }) {
  const total = data.total || 0;
  const paid = data.paid || 0;
  const installments = data.installments || 0;
  const unpaid = data.unpaid || 0;
  const selectedPercentage = total > 0 ? ((paid + installments) / total) * 100 : 0;
  const segments = [
    {
      key: 'paid',
      label: 'Betaald',
      value: paid,
      formattedValue: paid,
      strokeClass: 'stroke-electric-cyan',
      dotClass: 'bg-electric-cyan',
    },
    {
      key: 'installments',
      label: 'In termijnen',
      value: installments,
      formattedValue: installments,
      strokeClass: 'stroke-amber-400 dark:stroke-amber-500',
      dotClass: 'bg-amber-400 dark:bg-amber-500',
    },
    {
      key: 'unpaid',
      label: 'Openstaand',
      value: unpaid,
      formattedValue: unpaid,
      strokeClass: 'stroke-gray-200 dark:stroke-gray-700',
      dotClass: 'bg-gray-200 dark:bg-gray-700',
    },
  ];

  return (
    <DonutChart
      total={total}
      segments={segments}
      percentage={percentageFormatter.format(selectedPercentage)}
      centerLabel={PAYMENT_CENTER_LABEL}
      ariaLabel={`${paid} betaald, ${installments} in termijnen en ${unpaid} openstaand van ${total} facturen`}
    />
  );
}

function InvoiceAmountPieChart({ data }) {
  const total = data.total || 0;
  const collected = data.collected || 0;
  const outstanding = data.outstanding || 0;
  const collectedPercentage = total > 0 ? (collected / total) * 100 : 0;
  const segments = [
    {
      key: 'collected',
      label: 'Geïnd',
      value: collected,
      formattedValue: formatCurrency(collected, 2),
      strokeClass: 'stroke-electric-cyan',
      dotClass: 'bg-electric-cyan',
    },
    {
      key: 'outstanding',
      label: 'Openstaand',
      value: outstanding,
      formattedValue: formatCurrency(outstanding, 2),
      strokeClass: 'stroke-gray-200 dark:stroke-gray-700',
      dotClass: 'bg-gray-200 dark:bg-gray-700',
    },
  ];

  return (
    <DonutChart
      total={total}
      segments={segments}
      percentage={percentageFormatter.format(collectedPercentage)}
      centerLabel="geïnd"
      ariaLabel={`${formatCurrency(collected, 2)} van ${formatCurrency(total, 2)} aan facturen geïnd`}
    />
  );
}

function ChartCard({ title, children }) {
  return (
    <section className="card h-full">
      <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
      </div>
      <div className="overflow-x-auto p-4" data-horizontal-scroll="true">
        {children}
      </div>
    </section>
  );
}

export default function Betaalstatistieken() {
  useDocumentTitle('Betaalstatistieken');
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const requestedInvoiceType = searchParams.get('invoice_type') || '';
  const invoiceType = INVOICE_TYPES.some((type) => type.value === requestedInvoiceType)
    ? requestedInvoiceType
    : '';
  const invoiceTypeLabel = INVOICE_TYPES.find((type) => type.value === invoiceType)?.label;
  const { data: statistics, isLoading, error } = useInvoiceStatistics(
    invoiceType ? { invoice_type: invoiceType } : {}
  );

  const handleTypeChange = (event) => {
    const next = new URLSearchParams(searchParams);
    if (event.target.value) {
      next.set('invoice_type', event.target.value);
    } else {
      next.delete('invoice_type');
    }
    setSearchParams(next, { replace: true });
  };

  const handleRefresh = () => queryClient.invalidateQueries({ queryKey: ['invoice-statistics'] });

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        <div className="flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
              <TrendingUp className="w-5 h-5 text-electric-cyan" />
            </div>
            <p className="text-sm text-gray-500 dark:text-gray-400">Ontvangen betalingen</p>
          </div>
          <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <span>Factuursoort</span>
            <select value={invoiceType} onChange={handleTypeChange} className="input w-auto min-w-48">
              {INVOICE_TYPES.map((type) => (
                <option key={type.value} value={type.value}>{type.label}</option>
              ))}
            </select>
          </label>
        </div>

        {isLoading && (
          <div className="card p-8 text-center text-gray-500 dark:text-gray-400">Statistieken laden…</div>
        )}
        {error && (
          <div className="card p-8 text-center text-red-600 dark:text-red-400">Betaalstatistieken konden niet worden geladen.</div>
        )}
        {statistics && (
          <>
            <StatisticsSummary statistics={statistics} />
            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
              <div className="min-w-0">
                <ChartCard title="Inkomsten per maand · laatste 12 maanden">
                  <BarChart data={statistics.monthly_income} />
                </ChartCard>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 min-w-0">
                <ChartCard title={`Facturen · ${invoiceTypeLabel} · ${statistics.invoice_payment_status.season}`}>
                  <InvoicePaymentPieChart data={statistics.invoice_payment_status} />
                </ChartCard>
                <ChartCard title={`Bedrag geïnd · ${invoiceTypeLabel} · ${statistics.invoice_amount_status.season}`}>
                  <InvoiceAmountPieChart data={statistics.invoice_amount_status} />
                </ChartCard>
              </div>
            </div>
            <ChartCard title="Inkomsten per dag · laatste 30 dagen">
              <LineChart data={statistics.daily_income} />
            </ChartCard>
          </>
        )}
      </div>
    </PullToRefreshWrapper>
  );
}
