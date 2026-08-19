import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertTriangle,
  ArrowRight,
  CalendarCheck,
  CalendarClock,
  ChartPie,
  Gauge,
  RefreshCw,
  Users,
} from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const CHART_COLORS = ['#0047FF', '#00B8D9', '#7C3AED', '#F59E0B', '#10B981', '#EF4444', '#EC4899', '#64748B'];

const numberFormat = new Intl.NumberFormat('nl-NL');
const decimalFormat = new Intl.NumberFormat('nl-NL', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
const dateFormat = new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short' });
const dateTimeFormat = new Intl.DateTimeFormat('nl-NL', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
});

function toDate(value) {
  if (!value) return null;
  const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(value) {
  const date = toDate(value);
  return date ? dateFormat.format(date) : 'Onbekende datum';
}

function formatDateTime(value) {
  const date = toDate(value);
  return date ? dateTimeFormat.format(date) : 'Onbekend';
}

function taskColor(task, index) {
  return task.color || CHART_COLORS[index % CHART_COLORS.length];
}

function StatCard({ label, value, sub, icon: Icon }) {
  return (
    <div className="card p-5">
      <div className="flex items-start gap-3">
        <div className="p-2 rounded-lg bg-cyan-50 dark:bg-gray-700 shrink-0">
          <Icon className="w-5 h-5 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div className="min-w-0">
          <p className="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">{label}</p>
          <p className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{value}</p>
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{sub}</p>
        </div>
      </div>
    </div>
  );
}

function Panel({ title, description, children, className = '' }) {
  return (
    <section className={`card p-5 ${className}`}>
      <div className="mb-5">
        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
        {description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>}
      </div>
      {children}
    </section>
  );
}

function TaskTypeDonut({ taskTypes, total }) {
  const segments = useMemo(() => {
    let offset = 0;
    return taskTypes
      .filter((task) => task.assignments > 0)
      .map((task, index) => {
        const share = total > 0 ? (task.assignments / total) * 100 : 0;
        const segment = { ...task, share, offset, color: taskColor(task, index) };
        offset += share;
        return segment;
      });
  }, [taskTypes, total]);

  if (total === 0) {
    return <EmptyState message="Er zijn in dit seizoen nog geen inschrijvingen." />;
  }

  return (
    <div
      className="grid gap-6 items-center"
      style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 22rem), 1fr))' }}
    >
      <div className="relative w-44 h-44 mx-auto">
        <svg viewBox="0 0 120 120" className="w-full h-full" role="img" aria-label="Verdeling van inschrijvingen per taaksoort">
          <circle cx="60" cy="60" r="45" fill="none" stroke="currentColor" strokeWidth="17" className="text-gray-100 dark:text-gray-700" />
          {segments.map((segment) => (
            <circle
              key={segment.id}
              cx="60"
              cy="60"
              r="45"
              fill="none"
              stroke={segment.color}
              strokeWidth="17"
              pathLength="100"
              strokeDasharray={`${segment.share} ${100 - segment.share}`}
              strokeDashoffset={-segment.offset}
              transform="rotate(-90 60 60)"
            >
              <title>{`${segment.name}: ${numberFormat.format(segment.assignments)} (${decimalFormat.format(segment.share)}%)`}</title>
            </circle>
          ))}
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <span className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{numberFormat.format(total)}</span>
          <span className="text-xs text-gray-500 dark:text-gray-400">inschrijvingen</span>
        </div>
      </div>
      <ul className="min-w-0 space-y-2.5">
        {segments.map((segment) => (
          <li key={segment.id} className="flex min-w-0 items-center gap-2 text-sm">
            <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: segment.color }} />
            <span className="flex-1 min-w-0 truncate text-gray-700 dark:text-gray-300" title={segment.name}>{segment.name}</span>
            <span className="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">{numberFormat.format(segment.assignments)}</span>
            <span className="w-14 shrink-0 text-right tabular-nums text-gray-500 dark:text-gray-400">{decimalFormat.format(segment.share)}%</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function CoverageBars({ taskTypes }) {
  if (taskTypes.length === 0) {
    return <EmptyState message="Er zijn in dit seizoen nog geen gepubliceerde inschrijftaken." />;
  }

  return (
    <div className="space-y-4">
      {taskTypes.map((task, index) => {
        const width = Math.min(100, Math.max(0, task.fill_rate));
        return (
          <div key={task.id}>
            <div className="flex items-baseline justify-between gap-3 mb-1.5 text-sm">
              <span className="font-medium text-gray-800 dark:text-gray-200 truncate" title={task.name}>{task.name}</span>
              <span className="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">
                {numberFormat.format(task.assignments)} / {numberFormat.format(task.capacity)} · {decimalFormat.format(task.fill_rate)}%
              </span>
            </div>
            <div className="h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
              <div className="h-full rounded-full" style={{ width: `${width}%`, backgroundColor: taskColor(task, index) }} />
            </div>
          </div>
        );
      })}
    </div>
  );
}

function SignupTrend({ points, undatedAssignments, generatedAt }) {
  const todayDate = generatedAt?.slice(0, 10) || '';
  const todayCount = points.find((point) => point.date === todayDate)?.count || 0;

  const chart = useMemo(() => {
    if (points.length === 0) return null;
    const chartPoints = [...points];
    const finalPoint = chartPoints.at(-1);
    if (todayDate && finalPoint.date < todayDate) {
      chartPoints.push({ date: todayDate, count: 0, cumulative: finalPoint.cumulative });
    }

    const width = 640;
    const height = 240;
    const margins = { top: 16, right: 16, bottom: 28, left: 48 };
    const plotWidth = width - margins.left - margins.right;
    const plotHeight = height - margins.top - margins.bottom;
    const max = Math.max(...chartPoints.map((point) => point.cumulative), 1);
    const coordinates = chartPoints.map((point, index) => ({
      ...point,
      x: chartPoints.length === 1 ? margins.left + (plotWidth / 2) : margins.left + (index / (chartPoints.length - 1)) * plotWidth,
      y: margins.top + ((max - point.cumulative) / max) * plotHeight,
    }));
    const line = coordinates.map((point) => `${point.x},${point.y}`).join(' ');
    const baseline = height - margins.bottom;
    const area = `${coordinates[0].x},${baseline} ${line} ${coordinates.at(-1).x},${baseline}`;
    const yTicks = [...new Set([max, Math.ceil(max / 2), 0])].map((value) => ({
      value,
      y: margins.top + ((max - value) / max) * plotHeight,
    }));
    return { width, height, margins, max, coordinates, line, area, yTicks, baseline };
  }, [points, todayDate]);

  if (!chart) {
    return (
      <div>
        <p className="text-sm text-gray-600 dark:text-gray-300">
          Vandaag: <strong className="text-gray-900 dark:text-gray-100">0 inschrijvingen</strong>
        </p>
        <EmptyState message="Er zijn nog geen inschrijfmomenten vastgelegd." />
      </div>
    );
  }

  const first = chart.coordinates[0];
  const last = chart.coordinates.at(-1);

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2 text-sm">
        <p className="text-gray-600 dark:text-gray-300">
          Vandaag: <strong className="text-gray-900 dark:text-gray-100">{numberFormat.format(todayCount)} {todayCount === 1 ? 'inschrijving' : 'inschrijvingen'}</strong>
        </p>
        <p className="text-gray-500 dark:text-gray-400">
          In grafiek: <strong className="font-medium text-gray-700 dark:text-gray-200">{numberFormat.format(last.cumulative)}</strong>
        </p>
      </div>
      <div className="h-60">
        <svg viewBox={`0 0 ${chart.width} ${chart.height}`} className="w-full h-full" role="img" aria-label={`Cumulatieve ontwikkeling van inschrijvingen. Vandaag ${todayCount}.`}>
          <defs>
            <linearGradient id="signup-area" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#0047FF" stopOpacity="0.25" />
              <stop offset="100%" stopColor="#0047FF" stopOpacity="0" />
            </linearGradient>
          </defs>
          {chart.yTicks.map((tick) => (
            <g key={tick.value}>
              <line x1={chart.margins.left} y1={tick.y} x2={chart.width - chart.margins.right} y2={tick.y} stroke="currentColor" className="text-gray-200 dark:text-gray-700" strokeDasharray="4 5" />
              <text x={chart.margins.left - 9} y={tick.y + 4} textAnchor="end" className="fill-gray-500 dark:fill-gray-400 text-[12px]">{numberFormat.format(tick.value)}</text>
            </g>
          ))}
          <line x1={chart.margins.left} y1={chart.margins.top} x2={chart.margins.left} y2={chart.baseline} stroke="currentColor" className="text-gray-300 dark:text-gray-600" />
          <line x1={chart.margins.left} y1={chart.baseline} x2={chart.width - chart.margins.right} y2={chart.baseline} stroke="currentColor" className="text-gray-300 dark:text-gray-600" />
          <polygon points={chart.area} fill="url(#signup-area)" />
          <polyline points={chart.line} fill="none" stroke="#0047FF" strokeWidth="4" strokeLinejoin="round" strokeLinecap="round" />
          {chart.coordinates.map((point) => (
            <circle key={point.date} cx={point.x} cy={point.y} r={point.date === todayDate ? 5 : 4} fill="#0047FF">
              <title>{`${formatDate(point.date)}: ${numberFormat.format(point.count)} erbij, ${numberFormat.format(point.cumulative)} totaal`}</title>
            </circle>
          ))}
          <text x="13" y={chart.height / 2} textAnchor="middle" transform={`rotate(-90 13 ${chart.height / 2})`} className="fill-gray-500 dark:fill-gray-400 text-[12px]">Aantal</text>
          <text x={first.x} y={chart.height - 6} textAnchor={first.x === last.x ? 'middle' : 'start'} className="fill-gray-500 dark:fill-gray-400 text-[12px]">{formatDate(first.date)}</text>
          {first.x !== last.x && <text x={last.x} y={chart.height - 6} textAnchor="end" className="fill-gray-500 dark:fill-gray-400 text-[12px]">{last.date === todayDate ? 'Vandaag' : formatDate(last.date)}</text>}
        </svg>
      </div>
      {undatedAssignments > 0 && (
        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
          {numberFormat.format(undatedAssignments)} huidige {undatedAssignments === 1 ? 'inschrijving heeft' : 'inschrijvingen hebben'} geen vastgelegd inschrijfmoment en staat daarom niet in deze lijn.
        </p>
      )}
    </div>
  );
}

function Distribution({ distribution }) {
  const rows = [
    { key: 'one', label: '1 inschrijftaak', color: '#0047FF' },
    { key: 'two', label: '2 inschrijftaken', color: '#00B8D9' },
    { key: 'three_plus', label: '3 of meer', color: '#7C3AED' },
  ];
  const max = Math.max(...rows.map((row) => distribution[row.key] || 0), 1);

  return (
    <div className="space-y-4">
      {rows.map((row) => {
        const count = distribution[row.key] || 0;
        return (
          <div key={row.key} className="grid grid-cols-[110px_1fr_auto] gap-3 items-center text-sm">
            <span className="text-gray-700 dark:text-gray-300">{row.label}</span>
            <div className="h-7 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
              <div className="h-full rounded" style={{ width: `${(count / max) * 100}%`, backgroundColor: row.color }} />
            </div>
            <span className="w-10 text-right font-semibold tabular-nums text-gray-900 dark:text-gray-100">{numberFormat.format(count)}</span>
          </div>
        );
      })}
    </div>
  );
}

function ObligationProgress({ progress }) {
  const rows = [
    { key: 'completed', label: 'Voldaan', color: '#10B981' },
    { key: 'fully_scheduled', label: 'Voldoende ingepland', color: '#0047FF' },
    { key: 'partial', label: 'Gedeeltelijk ingepland', color: '#F59E0B' },
    { key: 'not_started', label: 'Nog niets gepland', color: '#EF4444' },
    { key: 'exempt', label: 'Vrijgesteld', color: '#94A3B8' },
  ];
  const total = progress.total_units || 0;

  if (total === 0) {
    return <EmptyState message="Er zijn geen vrijwilligerseenheden voor dit seizoen." />;
  }

  return (
    <div>
      <div className="flex h-5 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700" aria-label="Voortgang vrijwilligersplicht">
        {rows.map((row) => {
          const count = progress[row.key] || 0;
          return count > 0 ? (
            <div key={row.key} style={{ width: `${(count / total) * 100}%`, backgroundColor: row.color }} title={`${row.label}: ${count}`} />
          ) : null;
        })}
      </div>
      <ul className="mt-5 grid gap-3 sm:grid-cols-2">
        {rows.map((row) => (
          <li key={row.key} className="flex items-center gap-2 text-sm">
            <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: row.color }} />
            <span className="flex-1 text-gray-600 dark:text-gray-400">{row.label}</span>
            <strong className="tabular-nums text-gray-900 dark:text-gray-100">{numberFormat.format(progress[row.key] || 0)}</strong>
          </li>
        ))}
      </ul>
      <p className="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
        {numberFormat.format(progress.total_completed || 0)} van {numberFormat.format(progress.total_required || 0)} vereiste inschrijftaken uitgevoerd; {numberFormat.format(progress.total_pending || 0)} staan nog ingepland.
      </p>
    </div>
  );
}

function Shortages({ shortages, total, windowDays }) {
  if (shortages.length === 0) {
    return <EmptyState message={`Alle gepubliceerde inschrijftaken in de komende ${windowDays} dagen zijn gevuld.`} />;
  }

  return (
    <div>
      <div className="divide-y divide-gray-100 dark:divide-gray-700">
        {shortages.map((shift) => (
          <Link key={shift.id} to={`/vrijwilligers/diensten/${shift.id}`} className="group flex items-center gap-3 py-3 first:pt-0 last:pb-0">
            <div className={`p-2 rounded-lg shrink-0 ${shift.days_until <= 7 ? 'bg-red-50 dark:bg-red-900/20' : 'bg-amber-50 dark:bg-amber-900/20'}`}>
              <AlertTriangle className={`w-4 h-4 ${shift.days_until <= 7 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'}`} />
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">{shift.title}</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {formatDateTime(shift.start_datetime)} · {shift.task_type}
              </p>
            </div>
            <div className="text-right shrink-0">
              <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{shift.spots_remaining} {shift.spots_remaining === 1 ? 'plek' : 'plekken'} vrij</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">{shift.assigned_count} van {shift.capacity} gevuld</p>
            </div>
            <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-bright-cobalt dark:group-hover:text-electric-cyan" />
          </Link>
        ))}
      </div>
      {total > shortages.length && (
        <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">De {shortages.length} eerstvolgende van {total} inschrijftaken met open plekken worden getoond.</p>
      )}
    </div>
  );
}

function EmptyState({ message }) {
  return <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">{message}</p>;
}

export default function VrijwilligersStatistieken() {
  useDocumentTitle('Vrijwilligersstatistieken');
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const selectedSeason = searchParams.get('seizoen') || '';

  const { data, isLoading, isFetching, error } = useQuery({
    queryKey: ['volunteer', 'statistics', selectedSeason || 'current'],
    queryFn: async () => {
      const response = await prmApi.getVolunteerStatistics(selectedSeason ? { season: selectedSeason } : {});
      return response.data;
    },
    staleTime: 5 * 60 * 1000,
  });

  const setSeason = (season) => {
    if (season) {
      setSearchParams({ seizoen: season });
    } else {
      setSearchParams({});
    }
  };

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['volunteer', 'statistics'] });

  if (isLoading) {
    return <div className="card p-8 text-center text-sm text-gray-500 dark:text-gray-400">Statistieken worden berekend…</div>;
  }

  if (error || !data) {
    return (
      <div className="space-y-4">
        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijwilligersstatistieken</h1>
        <div className="card p-5 border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20 text-sm text-red-700 dark:text-red-300">
          De statistieken konden niet worden opgehaald. Probeer het later opnieuw.
        </div>
      </div>
    );
  }

  const summary = data.summary;

  return (
    <div className="space-y-6">
      <header className="flex flex-col gap-4 lg:flex-row lg:items-center">
        <div className="flex items-center gap-3 flex-1 min-w-0">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <ChartPie className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijwilligersstatistieken</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Actuele bezetting en voortgang · peildatum {formatDateTime(data.generated_at)}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <label htmlFor="statistics-season" className="sr-only">Seizoen</label>
          <select
            id="statistics-season"
            value={data.season}
            onChange={(event) => setSeason(event.target.value)}
            className="input min-w-36"
          >
            {(data.available_seasons || [data.season]).map((season) => (
              <option key={season} value={season}>Seizoen {season}</option>
            ))}
          </select>
          <button type="button" onClick={refresh} disabled={isFetching} className="btn-tertiary inline-flex items-center gap-2">
            <RefreshCw className={`w-4 h-4 ${isFetching ? 'animate-spin' : ''}`} />
            Ververs
          </button>
        </div>
      </header>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <StatCard label="Inschrijvingen" value={numberFormat.format(summary.total_assignments)} sub={`${numberFormat.format(summary.total_capacity)} beschikbare plekken`} icon={CalendarCheck} />
        <StatCard label="Vrijwilligers" value={numberFormat.format(summary.unique_volunteers)} sub="Unieke ingeschreven personen" icon={Users} />
        <StatCard label="Bezettingsgraad" value={`${decimalFormat.format(summary.fill_rate)}%`} sub="Over alle diensten dit seizoen" icon={Gauge} />
        <StatCard label="Uitgevoerd / komend" value={`${numberFormat.format(summary.completed_assignments)} / ${numberFormat.format(summary.upcoming_assignments)}`} sub="Huidige koppelingen" icon={CalendarClock} />
        <StatCard label="Gemiddeld" value={decimalFormat.format(summary.average_assignments_per_volunteer)} sub="Inschrijftaken per vrijwilliger" icon={ChartPie} />
      </section>

      <div className="grid gap-6 xl:grid-cols-2">
        <Panel title="Inschrijvingen per taaksoort" description="Verdeling van alle huidige koppelingen aan gepubliceerde inschrijftaken.">
          <TaskTypeDonut taskTypes={data.by_task_type} total={summary.total_assignments} />
        </Panel>
        <Panel title="Bezettingsgraad per taaksoort" description="Ingeschreven vrijwilligers ten opzichte van het aantal beschikbare plekken.">
          <CoverageBars taskTypes={data.by_task_type} />
        </Panel>
      </div>

      <Panel title="Ontwikkeling van de inschrijvingen" description="Cumulatief aantal huidige inschrijvingen op basis van het vastgelegde inschrijfmoment.">
        <SignupTrend points={data.signup_trend} undatedAssignments={data.undated_assignments} generatedAt={data.generated_at} />
      </Panel>

      <div className="grid gap-6 xl:grid-cols-2">
        <Panel title="Spreiding over vrijwilligers" description="Hoeveel inschrijftaken de ingeschreven vrijwilligers op zich hebben genomen.">
          <Distribution distribution={data.assignment_distribution} />
        </Panel>
        <Panel title="Voortgang vrijwilligersplicht" description={`Status van alle vrijwilligerseenheden in seizoen ${data.season}.`}>
          <ObligationProgress progress={data.obligation_progress} />
        </Panel>
      </div>

      <Panel
        title="Komende inschrijftaken met open plekken"
        description={`Gepubliceerde diensten in de komende ${data.shortage_window_days} dagen, gesorteerd op datum.`}
      >
        <Shortages shortages={data.upcoming_shortages} total={data.upcoming_shortages_total} windowDays={data.shortage_window_days} />
      </Panel>
    </div>
  );
}
