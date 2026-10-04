import { useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency } from '@/utils/formatters';
import { alignRevenuePeriods, periodDays, previousYear, revenueDifference, revenueTotal } from '@/utils/revenueComparison';
import ProductTrend from './ProductTrend';

const currency = value => formatCurrency(value, 2);
const label = (period, group) => new Intl.DateTimeFormat('nl-NL', group === 'month'
  ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short', year: 'numeric' })
  .format(new Date(`${period.length === 7 ? `${period}-01` : period}T12:00:00`));
const timestamp = period => Date.parse(`${period.length === 7 ? `${period}-01` : period}T12:00:00Z`);

function RevenueChart({ rows, comparisonRows, from, comparisonFrom, group, comparing }) {
  const slots = comparing ? alignRevenuePeriods(rows, comparisonRows, from, comparisonFrom, group) : [];
  const points = comparing ? slots.flatMap(slot => [slot.current && { ...slot.current, position: slot.offset, series: 0 }, slot.previous && { ...slot.previous, position: slot.offset, series: 1 }].filter(Boolean)) : rows.map(row => ({ ...row, position: timestamp(row.periode), series: 0 }));
  points.forEach(point => { point.id = `${point.series}/${point.periode}`; });
  const [selectedPeriod, setSelectedPeriod] = useState(null);
  const svg = useRef(null);
  const selected = points.find(row => row.id === selectedPeriod);
  const selectPoint = (event, index) => {
    const moves = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: points.length - 1 };
    if (event.key in moves) {
      event.preventDefault();
      const next = Math.max(0, Math.min(points.length - 1, moves[event.key]));
      setSelectedPeriod(points[next].id);
      svg.current.querySelector(`[data-point="${next}"]`).focus();
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      setSelectedPeriod(points[index].id);
    }
  };
  const compact = comparing && slots.length === 1;
  const width = compact ? 300 : 800;
  const height = 370;
  const left = 90;
  const right = 40;
  const top = 20;
  const bottom = 115;
  const values = points.map(row => row.omzet_totaal);
  const rawMin = Math.min(0, ...values);
  const rawMax = Math.max(1, ...values);
  const magnitude = 10 ** Math.floor(Math.log10((rawMax - rawMin) / 5));
  const step = [1, 2, 5, 10].find(value => value * magnitude >= (rawMax - rawMin) / 5) * magnitude;
  const min = Math.floor(rawMin / step) * step;
  const max = Math.ceil(rawMax / step) * step;
  const start = points[0].position;
  const end = points.at(-1).position;
  const x = position => start === end ? width / 2 : left + (position - start) / (end - start) * (width - left - right);
  const y = value => top + (max - value) / (max - min) * (height - top - bottom);
  const ticks = Array.from({ length: Math.round((max - min) / step) + 1 }, (_, index) => min + step * index);
  const tooltipX = selected ? Math.max(0, Math.min(width - 205, x(selected.position) - 102)) : 0;
  const tooltipY = selected ? (y(selected.omzet_totaal) < top + 65 ? y(selected.omzet_totaal) + 15 : y(selected.omzet_totaal) - 65) : 0;
  // Keep date labels at least 50 SVG units apart, including the last date.
  const uniqueDates = [...new Map(points.map(point => [point.position, point])).values()];
  const dateTicks = uniqueDates.filter((row, index) => index === 0 || index === uniqueDates.length - 1 || (x(row.position) - left >= 50 && width - right - x(row.position) >= 50));
  const dates = dateTicks.reduce((ticks, row, index) => {
    if (!ticks.length || index === dateTicks.length - 1 || x(row.position) - x(ticks.at(-1).position) >= 50) ticks.push(row);
    return ticks;
  }, []);
  return <>
    <p className="text-sm text-gray-600 dark:text-gray-300">Klik op een punt voor de dag of maand en het bedrag. Met de pijltjestoetsen ga je naar het vorige of volgende punt.</p>
    <div className="overflow-x-auto"><svg ref={svg} onClick={event => {
      const bounds = svg.current.getBoundingClientRect();
      const px = (event.clientX - bounds.left) * width / bounds.width;
      const py = (event.clientY - bounds.top) * height / bounds.height;
      const nearest = points.reduce((best, row) => {
        const distance = Math.hypot(x(row.position) - px, y(row.omzet_totaal) - py);
        return distance < best.distance ? { row, distance } : best;
      }, { distance: Infinity });
      if (nearest.distance <= 24) setSelectedPeriod(nearest.row.id);
    }} viewBox={`0 0 ${width} ${height}`} className={`w-full ${compact ? 'min-w-[300px] max-w-[400px]' : 'min-w-[800px]'} text-cyan-800 dark:text-cyan-200`} role="group" aria-label={`${comparing ? 'Vergelijking van periodes. ' : ''}Kassaomzet plus businessclub per ${group === 'month' ? 'maand' : 'dag'}. Bedragen staan in de tabel hieronder.`}>
    {ticks.map(value => <g key={value}><line x1={left} x2={width - right} y1={y(value)} y2={y(value)} className="stroke-gray-200 dark:stroke-gray-700" /><text x={left - 12} y={y(value) + 4} textAnchor="end" className="fill-gray-600 dark:fill-gray-300 text-xs">{currency(value)}</text></g>)}
    {[0, 1].map(series => {
      const data = points.filter(point => point.series === series);
      const path = data.map((point, index) => `${index === 0 || (comparing && point.position - data[index - 1].position > 1) ? 'M' : 'L'}${x(point.position)},${y(point.omzet_totaal)}`).join(' ');
      return <path key={series} d={path} fill="none" stroke="currentColor" strokeWidth="2.5" strokeDasharray={series ? '7 5' : undefined} className={series ? 'text-amber-800 dark:text-amber-200' : ''} />;
    })}
    {points.map((row, index) => <g key={row.id} data-point={index} role="button" tabIndex={selected ? (selected.id === row.id ? 0 : -1) : (index === 0 ? 0 : -1)} aria-label={`${comparing ? (row.series ? 'Vergelijkingsperiode, ' : 'Gekozen periode, ') : ''}${label(row.periode, group)}: ${currency(row.omzet_totaal)}`} aria-pressed={selectedPeriod === row.id} onClick={event => { event.stopPropagation(); setSelectedPeriod(row.id); }} onKeyDown={event => selectPoint(event, index)} className={`cursor-pointer outline-none group ${row.series ? 'text-amber-800 dark:text-amber-200' : ''}`}>
      <circle cx={x(row.position)} cy={y(row.omzet_totaal)} r="11" fill="transparent" className="group-focus:stroke-current" strokeWidth="2" />
      <circle cx={x(row.position)} cy={y(row.omzet_totaal)} r={selectedPeriod === row.id ? 6 : 4} fill="currentColor" className="group-hover:stroke-current" strokeWidth="3" />
      <title>{label(row.periode, group)}: {currency(row.omzet_totaal)}</title>
    </g>)}
    {selected && <g transform={`translate(${tooltipX},${tooltipY})`} pointerEvents="none" aria-hidden="true">
      <rect width="205" height="52" rx="6" className="fill-white stroke-gray-300 dark:fill-gray-800 dark:stroke-gray-500" />
      <text x="12" y="20" className="fill-gray-900 dark:fill-gray-100 text-xs">{label(selected.periode, group)}</text>
      <text x="12" y="39" className="fill-gray-900 dark:fill-gray-100 text-sm font-semibold">{currency(selected.omzet_totaal)}</text>
    </g>}
    {dates.map(row => <g key={row.id}>
      <line x1={x(row.position)} x2={x(row.position)} y1={height - bottom} y2={height - bottom + 6} className="stroke-gray-400" />
      <text transform={`translate(${x(row.position)},${height - bottom + 15}) rotate(90)`} className="fill-gray-600 dark:fill-gray-300 text-xs">{comparing ? `${group === 'month' ? 'Maand' : 'Dag'} ${row.position + 1}` : label(row.periode, group)}</text>
    </g>)}
  </svg></div>
    <div aria-live="polite" aria-atomic="true" className="text-sm min-h-16">
      {selected && <><p className="font-semibold">{label(selected.periode, group)}{selected.provisional ? ' · Voorlopig' : ''}</p><p className="mt-1">Totale omzet: <strong className="tabular-nums">{currency(selected.omzet_totaal)}</strong></p><p className="text-gray-600 dark:text-gray-300 mt-1">Kassaomzet: {currency(selected.kassaomzet)} · Businessclub: {currency(selected.businessclub)}</p>{comparing && (() => {
        const slot = slots.find(slot => slot.offset === selected.position);
        const other = selected.series ? slot.current : slot.previous;
        return <p className="mt-2">{other ? <>{label(other.periode, group)}: <strong>{currency(other.omzet_totaal)}</strong> · Verschil gekozen periode: <Difference current={slot.current.omzet_totaal} previous={slot.previous.omzet_totaal} /></> : 'Geen rapportage op dezelfde positie in de andere periode.'}</p>;
      })()}</>}
    </div>
  </>;
}

function Difference({ current, previous }) {
  const difference = revenueDifference(current, previous);
  if (!difference) return <span>Geen vergelijking</span>;
  const percent = difference.percent == null ? 'percentage niet beschikbaar bij € 0,00' : `${new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 1, signDisplay: 'exceptZero' }).format(difference.percent)}%`;
  return <span className="tabular-nums">{difference.amount > 0 ? '+' : ''}{currency(difference.amount)} ({percent})</span>;
}

const rangeLabel = (from, to) => `${label(from, 'day')} – ${label(to, 'day')}`;

function ComparisonSummary({ rows, comparisonRows, from, to, comparisonFrom, comparisonTo }) {
  const metrics = [['Totale omzet', 'omzet_totaal'], ['Kassaomzet', 'kassaomzet'], ['Businessclub', 'businessclub']].map(([name, field]) => ({ name, field, current: revenueTotal(rows, field), previous: revenueTotal(comparisonRows, field) }));
  return <div className="space-y-3">
    <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
      <p className="flex items-center gap-2"><span aria-hidden="true" className="w-7 border-t-2 border-cyan-800 dark:border-cyan-200" />Gekozen periode: {rangeLabel(from, to)}</p>
      <p className="flex items-center gap-2"><span aria-hidden="true" className="w-7 border-t-2 border-dashed border-amber-800 dark:border-amber-200" />Vergelijkingsperiode: {rangeLabel(comparisonFrom, comparisonTo)}</p>
    </div>
    <dl className="sm:hidden text-sm">{metrics.map(metric => <div key={metric.field} className="border-b border-gray-200 dark:border-gray-700 py-4 space-y-2">
      <dt className="font-semibold">{metric.name}</dt><dd><dl className="space-y-1">
        <div className="flex justify-between gap-3"><dt>Gekozen periode</dt><dd className="tabular-nums">{metric.current == null ? 'Geen rapportages' : currency(metric.current)}</dd></div>
        <div className="flex justify-between gap-3"><dt>Vergelijkingsperiode</dt><dd className="tabular-nums">{metric.previous == null ? 'Geen rapportages' : currency(metric.previous)}</dd></div>
        <div><dt className="inline">Verschil: </dt><dd className="inline font-medium"><Difference current={metric.current} previous={metric.previous} /></dd></div>
      </dl></dd>
    </div>)}</dl>
    <div className="hidden sm:block overflow-x-auto"><table className="w-full text-sm"><caption className="sr-only">Omzetvergelijking van de twee gekozen periodes</caption><thead><tr>
      <th scope="col" className="py-3 pr-4 text-left">Omzet</th><th scope="col" className="py-3 px-4 text-right">Gekozen periode</th><th scope="col" className="py-3 px-4 text-right">Vergelijkingsperiode</th><th scope="col" className="py-3 pl-4 text-right">Verschil</th>
    </tr></thead><tbody>{metrics.map(metric => <tr key={metric.field} className="border-t border-gray-200 dark:border-gray-700">
      <th scope="row" className="py-3 pr-4 text-left font-medium">{metric.name}</th>
      {[metric.current, metric.previous].map((amount, index) => <td key={index} className="py-3 px-4 text-right tabular-nums whitespace-nowrap">{amount == null ? 'Geen rapportages' : currency(amount)}</td>)}
      <td className="py-3 pl-4 text-right whitespace-nowrap"><Difference current={metric.current} previous={metric.previous} /></td>
    </tr>)}</tbody></table></div>
    {periodDays(from, to) !== periodDays(comparisonFrom, comparisonTo) && <p className="text-sm text-amber-800 dark:text-amber-200">Deze periodes zijn niet even lang: {periodDays(from, to)} en {periodDays(comparisonFrom, comparisonTo)} kalenderdagen. De totalen zijn niet omgerekend naar een gelijke duur.</p>}
    {(rows.some(row => row.provisional) || comparisonRows.some(row => row.provisional)) && <p className="text-sm text-amber-800 dark:text-amber-200">De vergelijking bevat voorlopige omzet van een lopende kassadag.</p>}
  </div>;
}

export default function Omzetontwikkeling() {
  const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
  const [from, setFrom] = useState(`${Number(today.slice(0, 4)) - 1}-${today.slice(5, 7)}-01`);
  const [to, setTo] = useState(today);
  const [group, setGroup] = useState('day');
  const [view, setView] = useState('revenue');
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [productMetric, setProductMetric] = useState('amount');
  const productTrends = view !== 'revenue';
  const [comparing, setComparing] = useState(false);
  const [comparisonMode, setComparisonMode] = useState('year');
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');
  const comparisonFrom = comparisonMode === 'year' ? previousYear(from) : customFrom;
  const comparisonTo = comparisonMode === 'year' ? previousYear(to) : customTo;
  const valid = Boolean(from && to && from <= to);
  const comparisonValid = Boolean(comparisonFrom && comparisonTo && comparisonFrom <= comparisonTo);
  const query = useQuery({ queryKey: ['twelve', 'trend', from, to, group, productTrends], queryFn: async () => (await prmApi.getTwelve('summary', { from, to, group, include_product_trend: productTrends })).data, enabled: valid });
  const comparisonQuery = useQuery({ queryKey: ['twelve', 'trend', comparisonFrom, comparisonTo, group, productTrends], queryFn: async () => (await prmApi.getTwelve('summary', { from: comparisonFrom, to: comparisonTo, group, include_product_trend: productTrends })).data, enabled: comparing && valid && comparisonValid });
  const rows = query.data?.buckets ?? [];
  const comparisonRows = comparing ? comparisonQuery.data?.buckets ?? [] : [];
  const slots = comparing && valid && comparisonValid ? alignRevenuePeriods(rows, comparisonRows, from, comparisonFrom, group) : [];
  const loading = query.isPending || (comparing && comparisonQuery.isPending);
  const error = query.isError || (comparing && comparisonQuery.isError);
  return <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-5">
    <div><h2 className="text-lg font-semibold">Omzetontwikkeling</h2><p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{productTrends ? 'Ontwikkeling van productbedragen en verbruik, inclusief btw en no-sale.' : 'Kassaomzet + businessclub, inclusief btw. Merchandise, Overig en ander no-sale-verbruik tellen niet mee.'}</p></div>
    <div className="flex flex-wrap items-end gap-4">
      <label className="text-sm">Weergave<select value={view} onChange={event => setView(event.target.value)} className="input block mt-1"><option value="revenue">Totale omzet · lijn</option><option value="groups">Productgroepen · vlakken</option><option value="product">Per product</option></select></label>
      <label className="text-sm">Vanaf<input type="date" required value={from} onChange={event => setFrom(event.target.value)} className="input block mt-1" /></label>
      <label className="text-sm">Tot en met<input type="date" required value={to} onChange={event => setTo(event.target.value)} className="input block mt-1" /></label>
      <label className="text-sm">Groeperen<select value={group} onChange={event => setGroup(event.target.value)} className="input block mt-1"><option value="day">Per dag</option><option value="month">Per maand</option></select></label>
    </div>
    <div className="space-y-3">
      <label className="inline-flex items-center gap-2 text-sm font-medium"><input type="checkbox" checked={comparing} onChange={event => setComparing(event.target.checked)} className="rounded border-gray-300 text-cyan-800 focus:ring-cyan-800" />Vergelijk met een andere periode</label>
      {comparing && <div className="flex flex-wrap items-end gap-4">
        <label className="text-sm">Vergelijken met<select value={comparisonMode} onChange={event => {
          if (event.target.value === 'custom') { setCustomFrom(comparisonFrom); setCustomTo(comparisonTo); }
          setComparisonMode(event.target.value);
        }} className="input block mt-1"><option value="year">Dezelfde periode vorig jaar</option><option value="custom">Zelf een periode kiezen</option></select></label>
        {comparisonMode === 'custom' ? <>
          <label className="text-sm">Vergelijken vanaf<input type="date" required value={customFrom} onChange={event => setCustomFrom(event.target.value)} className="input block mt-1" /></label>
          <label className="text-sm">Vergelijken tot en met<input type="date" required value={customTo} onChange={event => setCustomTo(event.target.value)} className="input block mt-1" /></label>
        </> : comparisonValid && <p className="text-sm pb-2 text-gray-600 dark:text-gray-300">{rangeLabel(comparisonFrom, comparisonTo)}</p>}
      </div>}
    </div>
    {!valid || (comparing && !comparisonValid) ? <p role="alert" className="text-sm text-red-700 dark:text-red-300">Kies voor elke periode een begindatum die vóór of op de einddatum ligt.</p> : loading ? <p role="status">Omzet laden…</p> : error ? <div><p role="alert">{query.isError ? 'De omzet van de gekozen periode' : 'De omzet van de vergelijkingsperiode'} kon niet worden geladen.</p><button className="btn-secondary mt-3" onClick={() => { if (query.isError) query.refetch(); if (comparing && comparisonQuery.isError) comparisonQuery.refetch(); }}>Opnieuw proberen</button></div> : <>
      {productTrends ? <ProductTrend rows={rows} comparisonRows={comparisonRows} from={from} to={to} comparisonFrom={comparisonFrom} comparisonTo={comparisonTo} group={group} comparing={comparing} view={view} selectedProduct={selectedProduct} onProductChange={setSelectedProduct} metric={productMetric} onMetricChange={setProductMetric} /> : <>
      {comparing ? <ComparisonSummary rows={rows} comparisonRows={comparisonRows} from={from} to={to} comparisonFrom={comparisonFrom} comparisonTo={comparisonTo} /> : rows.length > 0 && <p className="text-sm">Totaal in deze periode: <strong className="tabular-nums">{currency(revenueTotal(rows))}</strong></p>}
      {!rows.length && <p className="text-sm text-gray-600 dark:text-gray-300">Geen rapportages in de gekozen periode. Kies een andere periode.</p>}
      {comparing && !comparisonRows.length && <p className="text-sm text-gray-600 dark:text-gray-300">Geen rapportages in de vergelijkingsperiode. Kies een andere vergelijkingsperiode.</p>}
      {comparing && (rows.length > 0 || comparisonRows.length > 0) && <p className="text-sm text-gray-600 dark:text-gray-300">De lijnen beginnen bij {group === 'month' ? 'de eerste kalendermaand' : 'de eerste kalenderdag'} van elke periode. {group === 'month' ? 'Bij een deel van een maand telt alleen het gekozen datumbereik mee.' : 'Dagen zonder rapportage blijven leeg in de grafiek.'}</p>}
      {(rows.length > 0 || comparisonRows.length > 0) && <RevenueChart key={`${from}/${to}/${group}/${comparing}/${comparisonFrom}/${comparisonTo}`} rows={rows} comparisonRows={comparisonRows} from={from} comparisonFrom={comparisonFrom} group={group} comparing={comparing} />}
      <p className="text-xs text-gray-500 dark:text-gray-400">Kortingen en gedeeltelijke no-sales zijn naar productwaarde verdeeld. Alleen geïmporteerde rapportages. Ontbrekende dagen tellen niet als nul; maandbedragen kunnen onvolledig zijn.</p>
      {(rows.length > 0 || comparisonRows.length > 0) && <details><summary className="cursor-pointer text-sm font-medium text-cyan-800 dark:text-cyan-200">Bekijk bedragen</summary><div className="overflow-x-auto mt-3"><table className="w-full text-sm"><thead><tr><th scope="col" className="py-3 pr-4 text-left font-medium">{group === 'month' ? 'Maand' : 'Dag'}</th><th scope="col" className="py-3 px-4 text-right font-medium">Omzet</th>{comparing && <><th scope="col" className="py-3 px-4 text-left font-medium">Vergelijkingsperiode</th><th scope="col" className="py-3 px-4 text-right font-medium">Omzet</th><th scope="col" className="py-3 pl-4 text-right font-medium">Verschil</th></>}</tr></thead><tbody>{(comparing ? slots : rows.map((row, index) => ({ offset: index, current: row, previous: null }))).map(slot => <tr key={slot.offset} className="border-t border-gray-100 dark:border-gray-700">
        <td className="py-3 pr-4 whitespace-nowrap">{slot.current ? label(slot.current.periode, group) : 'Geen rapportage'}</td><td className="py-3 px-4 text-right tabular-nums whitespace-nowrap">{slot.current ? currency(slot.current.omzet_totaal) : '—'}</td>
        {comparing && <><td className="py-3 px-4 whitespace-nowrap">{slot.previous ? label(slot.previous.periode, group) : 'Geen rapportage'}</td><td className="py-3 px-4 text-right tabular-nums whitespace-nowrap">{slot.previous ? currency(slot.previous.omzet_totaal) : '—'}</td><td className="py-3 pl-4 text-right whitespace-nowrap"><Difference current={slot.current?.omzet_totaal} previous={slot.previous?.omzet_totaal} /></td></>}
      </tr>)}</tbody></table></div></details>}
      </>}
    </>}
  </div>;
}
