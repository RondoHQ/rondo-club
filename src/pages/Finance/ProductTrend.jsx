import { useEffect, useRef, useState } from 'react';
import { formatCurrency } from '@/utils/formatters';
import { periodDays, revenueDifference } from '@/utils/revenueComparison';
import { consecutiveSegments, productOptions, productPoints, stackProductValues, trendGroupKeys } from '@/utils/productTrend';

const currency = value => formatCurrency(value, 2);
const number = value => new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 2 }).format(value);
const colors = ['text-cyan-700 dark:text-cyan-300', 'text-amber-600 dark:text-amber-300', 'text-emerald-700 dark:text-emerald-300', 'text-gray-500 dark:text-gray-400'];
const dateLabel = (period, group, compact = false) => new Intl.DateTimeFormat('nl-NL', group === 'month'
  ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short', ...(!compact && { year: 'numeric' }) })
  .format(new Date(`${period.length === 7 ? `${period}-01` : period}T12:00:00`));
const sum = (points, key = 'total') => points.length ? points.reduce((total, point) => total + Math.round(point[key] * 100), 0) / 100 : null;

function TrendChart({ points, series, domain, extent, group, quantity, title, lines = false, productDetails = false }) {
  const container = useRef(null);
  const svg = useRef(null);
  const [width, setWidth] = useState(640);
  const [selected, setSelected] = useState(null);
  useEffect(() => {
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(240, entry.contentRect.width)));
    observer.observe(container.current);
    return () => observer.disconnect();
  }, []);
  const format = quantity ? number : currency;
  const height = 290;
  const left = quantity ? 62 : 95;
  const right = 20;
  const top = 25;
  const bottom = 50;
  const [min, max, step] = domain;
  const x = offset => extent[0] === extent[1] ? (left + width - right) / 2 : left + (offset - extent[0]) / (extent[1] - extent[0]) * (width - left - right);
  const y = value => top + (max - value) / (max - min) * (height - top - bottom);
  const ticks = Array.from({ length: Math.round((max - min) / step) + 1 }, (_, index) => min + step * index);
  const segments = consecutiveSegments(points);
  const selectedPoint = points.find(point => point.periode === selected);
  const keyboard = (event, index) => {
    const moves = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: points.length - 1 };
    if (event.key in moves) {
      event.preventDefault();
      const next = Math.max(0, Math.min(points.length - 1, moves[event.key]));
      setSelected(points[next].periode);
      svg.current.querySelector(`[data-point="${next}"]`).focus();
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      setSelected(points[index].periode);
    }
  };
  const dateTicks = points.filter((point, index) => index === 0 || index === points.length - 1 || (index % Math.max(1, Math.ceil(points.length / (width < 500 ? 3 : 6))) === 0));
  const dates = dateTicks.filter((point, index) => index === 0 || index === dateTicks.length - 1 || (x(point.offset) - x(dateTicks[index - 1].offset) > 80 && x(points.at(-1).offset) - x(point.offset) > 80));
  return <section ref={container} className="min-w-0 space-y-3">
    <h3 className="text-sm font-semibold dark:text-gray-100">{title}</h3>
    {!points.length ? <p className="text-sm text-gray-600 dark:text-gray-300">Geen rapportages in deze periode.</p> : <>
      <svg ref={svg} viewBox={`0 0 ${width} ${height}`} className="block w-full" role="group" aria-label={`${title}. ${quantity ? 'Aantal producten' : 'Productbedragen inclusief btw en no-sale'} per ${group === 'month' ? 'maand' : 'dag'}. Gebruik de pijltjestoetsen om meetpunten te kiezen.`} onClick={event => {
        const bounds = svg.current.getBoundingClientRect();
        const pointer = (event.clientX - bounds.left) * width / bounds.width;
        const nearest = points.reduce((best, point) => Math.abs(x(point.offset) - pointer) < Math.abs(x(best.offset) - pointer) ? point : best);
        setSelected(nearest.periode);
      }}>
        {ticks.map(value => <g key={value}><line x1={left} x2={width - right} y1={y(value)} y2={y(value)} className="stroke-gray-200 dark:stroke-gray-700" /><text x={left - 10} y={y(value) + 4} textAnchor="end" className="fill-gray-600 dark:fill-gray-300 text-xs">{format(value)}</text></g>)}
        {series.map((item, seriesIndex) => <g key={item.key} className={item.color}>
          {lines ? segments.map((segment, index) => <polyline key={index} points={segment.map(point => `${x(point.offset)},${y(point.values[seriesIndex])}`).join(' ')} fill="none" stroke="currentColor" strokeWidth="2.5" strokeDasharray={item.dash} strokeLinejoin="round" />) : segments.flatMap((segment, segmentIndex) => ['positive', 'negative'].map(side => {
            const layers = segment.map(point => stackProductValues(point.values)[seriesIndex][side]);
            if (layers.every(([low, high]) => low === high)) return null;
            if (segment.length === 1) return <rect key={`${segmentIndex}/${side}`} x={x(segment[0].offset) - 8} y={y(layers[0][1])} width="16" height={y(layers[0][0]) - y(layers[0][1])} fill="currentColor" fillOpacity="0.65" />;
            const upper = segment.map((point, index) => `${x(point.offset)},${y(layers[index][1])}`);
            const lower = segment.map((point, index) => `${x(point.offset)},${y(layers[index][0])}`).reverse();
            return <polygon key={`${segmentIndex}/${side}`} points={[...upper, ...lower].join(' ')} fill="currentColor" fillOpacity="0.65" />;
          }))}
        </g>)}
        {selectedPoint && <line x1={x(selectedPoint.offset)} x2={x(selectedPoint.offset)} y1={top} y2={height - bottom} className="stroke-gray-500 dark:stroke-gray-400" strokeDasharray="3 4" />}
        {points.map((point, index) => <g key={point.periode} data-point={index} role="button" tabIndex={selectedPoint ? (selected === point.periode ? 0 : -1) : (index === 0 ? 0 : -1)} aria-pressed={selected === point.periode} aria-label={`${dateLabel(point.periode, group)}: ${series.map((item, i) => `${item.label} ${format(point.values[i])}`).join(', ')}${point.provisional ? ', voorlopig' : ''}`} className="cursor-pointer text-cyan-800 dark:text-cyan-200 group outline-none" onClick={event => { event.stopPropagation(); setSelected(point.periode); }} onKeyDown={event => keyboard(event, index)}>
          {(lines ? point.values : [point.total]).map((value, seriesIndex) => <g key={seriesIndex} className={lines ? series[seriesIndex].color : undefined}>
            <circle cx={x(point.offset)} cy={y(value)} r="15" fill="transparent" className="group-focus:stroke-current" strokeWidth="2" />
            <circle cx={x(point.offset)} cy={y(value)} r={selected === point.periode ? 5 : 3} fill="currentColor" />
          </g>)}
        </g>)}
        {dates.map((point, index) => <text key={point.periode} x={x(point.offset)} y={height - 18} textAnchor={index === 0 ? 'start' : index === dates.length - 1 ? 'end' : 'middle'} className="fill-gray-600 dark:fill-gray-300 text-xs">{dateLabel(point.periode, group, true)}</text>)}
      </svg>
      <div aria-live="polite" aria-atomic="true" className="text-sm min-h-20">
        {selectedPoint ? <><p className="font-semibold">{dateLabel(selectedPoint.periode, group)}{selectedPoint.provisional ? ' · Voorlopig' : ''}</p><dl className="flex flex-wrap gap-x-6 gap-y-1 mt-2">{series.map((item, index) => <div key={item.key}><dt className="inline">{item.label}: </dt><dd className="inline font-medium tabular-nums">{format(selectedPoint.values[index])}{quantity ? ' stuks' : ''}</dd></div>)}</dl>{productDetails && <p className="mt-1">{quantity ? `Productbedrag: ${currency(selectedPoint.amount)}` : `Aantal: ${number(selectedPoint.quantity)}`}</p>}</> : <p className="text-gray-600 dark:text-gray-300">Kies een dag of maand in de grafiek voor de exacte bedragen. Gebruik ook de pijltjestoetsen.</p>}
      </div>
      <details><summary className="text-sm cursor-pointer font-medium text-cyan-800 dark:text-cyan-200">Bekijk {quantity ? 'aantallen' : 'bedragen'}</summary><div className="overflow-x-auto mt-3"><table className="w-full text-sm"><thead><tr><th scope="col" className="text-left py-3 pr-4">{group === 'month' ? 'Maand' : 'Dag'}</th>{series.map(item => <th scope="col" className="text-right py-3 px-3" key={item.key}>{item.label}</th>)}{series.length > 1 && <th scope="col" className="text-right py-3 pl-3">Totaal</th>}</tr></thead><tbody>{points.map(point => <tr key={point.periode} className="border-t border-gray-200 dark:border-gray-700"><th scope="row" className="text-left font-normal py-3 pr-4 whitespace-nowrap">{dateLabel(point.periode, group)}{point.provisional && <span className="block text-xs">Voorlopig</span>}</th>{point.values.map((value, index) => <td key={series[index].key} className="text-right tabular-nums px-3 py-3 whitespace-nowrap">{format(value)}</td>)}{series.length > 1 && <td className="text-right tabular-nums pl-3 py-3 whitespace-nowrap">{format(point.total)}</td>}</tr>)}</tbody></table></div></details>
    </>}
  </section>;
}

export default function ProductTrend({ rows, comparisonRows, from, to, comparisonFrom, comparisonTo, group, comparing, view, selectedProduct, onProductChange, selectedGroup, onGroupChange, metric, onMetricChange }) {
  const allRows = [...rows, ...comparisonRows];
  const products = productOptions(allRows);
  // Retain the chosen product across date changes, including ranges with no sales of it.
  if (selectedProduct && !products.some(item => item.id === selectedProduct.id)) products.push(selectedProduct);
  const product = selectedProduct ?? products[0];
  const individual = view === 'product';
  const lines = view === 'group-lines';
  const quantity = individual && metric === 'quantity';
  const selectedId = individual ? product?.id : null;
  const actualMetric = quantity ? 'quantity' : 'amount';
  const current = productPoints(rows, from, group, selectedId, actualMetric);
  const previous = comparing ? productPoints(comparisonRows, comparisonFrom, group, selectedId, actualMetric) : [];
  const allPoints = [...current, ...previous];
  const labels = new Map(allRows.flatMap(row => row.product_trend.groups).map(item => [item.group, item.label]));
  const hasUnassigned = allRows.some(row => row.product_trend.unassigned_count > 0);
  const groupOptions = trendGroupKeys.map((key, index) => ({
    key, index, label: labels.get(key) ?? ['Eten', 'Drank', 'Entree', 'Nog indelen'][index],
    color: colors[index], dash: [undefined, '8 4', '2 4', '8 3 2 3'][index],
  })).filter(item => item.key !== 'unassigned' || hasUnassigned || (lines && selectedGroup === 'unassigned'));
  const series = individual ? [{ key: product?.id ?? 'product', label: product?.name ?? 'Product', color: colors[0] }]
    : groupOptions.filter(item => !lines || selectedGroup === 'all' || item.key === selectedGroup);
  if (!individual) {
    allPoints.forEach(point => {
      point.values = series.map(item => point.values[item.index]);
      point.total = point.values.reduce((total, value) => total + Math.round(value * 100), 0) / 100;
    });
  }
  const bounds = allPoints.flatMap(point => lines ? point.values : stackProductValues(point.values).flatMap(layer => [...layer.positive, ...layer.negative]));
  const rawMin = Math.min(0, ...bounds);
  const rawMax = Math.max(1, ...bounds);
  const magnitude = 10 ** Math.floor(Math.log10((rawMax - rawMin) / 5));
  const step = Math.max(quantity ? 1 : 0.01, [1, 2, 5, 10].find(value => value * magnitude >= (rawMax - rawMin) / 5) * magnitude);
  const domain = [Math.floor(rawMin / step) * step, Math.ceil(rawMax / step) * step, step];
  const extent = allPoints.length ? [Math.min(...allPoints.map(point => point.offset)), Math.max(...allPoints.map(point => point.offset))] : [0, 1];
  const format = quantity ? number : currency;
  const delta = revenueDifference(sum(current), sum(previous));
  const excluded = data => data.reduce((total, row) => total + row.product_trend.groups.filter(item => ['other', 'merchandise'].includes(item.group)).reduce((cents, item) => cents + Math.round(item.amount * 100), 0), 0) / 100;
  if (individual && !products.length) return <p className="text-sm text-gray-600 dark:text-gray-300">Geen producten in de gekozen periodes.</p>;
  return <div className="space-y-5">
    {lines && <label className="block text-sm w-full sm:w-fit">Productgroep<select className="input block mt-1 w-full" value={selectedGroup} onChange={event => onGroupChange(event.target.value)}><option value="all">Alle productgroepen</option>{groupOptions.map(item => <option key={item.key} value={item.key}>{item.label}</option>)}</select></label>}
    {individual && <div className="flex flex-wrap items-end gap-4">
      <label className="text-sm min-w-0 w-full sm:w-auto sm:max-w-md">Product<select className="input block mt-1 w-full" value={product.id} onChange={event => onProductChange(products.find(item => item.id === event.target.value))}>{products.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
      <label className="text-sm">Toon<select className="input block mt-1" value={metric} onChange={event => onMetricChange(event.target.value)}><option value="amount">Bedrag</option><option value="quantity">Aantal</option></select></label>
    </div>}
    <p className="text-sm text-gray-600 dark:text-gray-300">{individual ? 'Verkochte en verbruikte producten, inclusief no-sale. Productbedragen zijn inclusief btw.' : 'Productbedragen inclusief btw en no-sale. Merchandise en Overig vallen buiten deze grafiek.'}</p>
    {!individual && <ul className="flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-label="Productgroepen">{series.map(item => <li key={item.key} className="flex items-center gap-2">{lines ? <svg aria-hidden="true" width="28" height="12" className={item.color}><line x1="0" x2="28" y1="6" y2="6" stroke="currentColor" strokeWidth="2.5" strokeDasharray={item.dash} /></svg> : <span aria-hidden="true" className={`h-3 w-3 rounded-sm bg-current ${item.color}`} />}{item.label}</li>)}</ul>}
    <div className="text-sm space-y-2">
      <p>Gekozen periode: <strong className="tabular-nums">{sum(current) === null ? 'Geen rapportages' : `${format(sum(current))}${quantity ? ' stuks' : ''}`}</strong>{individual && !quantity && current.length > 0 && <span> · {number(sum(current, 'quantity'))} stuks</span>}</p>
      {comparing && <><p>Vergelijkingsperiode: <strong className="tabular-nums">{sum(previous) === null ? 'Geen rapportages' : `${format(sum(previous))}${quantity ? ' stuks' : ''}`}</strong>{individual && !quantity && previous.length > 0 && <span> · {number(sum(previous, 'quantity'))} stuks</span>}</p><p>Verschil: {delta ? <strong className="tabular-nums">{delta.amount > 0 ? '+' : ''}{format(delta.amount)}{quantity ? ' stuks' : ''}{delta.percent === null ? ' · percentage niet beschikbaar bij nul' : ` (${number(delta.percent)}%)`}</strong> : 'Geen vergelijking'}</p></>}
      {!individual && <p className="text-gray-600 dark:text-gray-300">Merchandise en Overig buiten de grafiek: {currency(excluded(rows))}{comparing ? ` · vergelijkingsperiode: ${currency(excluded(comparisonRows))}` : ''}.</p>}
    </div>
    {comparing && periodDays(from, to) !== periodDays(comparisonFrom, comparisonTo) && <p className="text-sm text-amber-800 dark:text-amber-200">Deze periodes zijn niet even lang: {periodDays(from, to)} en {periodDays(comparisonFrom, comparisonTo)} kalenderdagen. Totalen zijn niet omgerekend naar een gelijke duur.</p>}
    {allPoints.some(point => point.provisional) && <p className="text-sm text-amber-800 dark:text-amber-200">De grafiek bevat voorlopige gegevens van een lopende kassadag.</p>}
    {comparing && <p className="text-sm text-gray-600 dark:text-gray-300">Beide grafieken gebruiken dezelfde schaal en beginnen bij dezelfde kalenderpositie binnen hun periode.</p>}
    <TrendChart key={`${view}/${product?.id}/${metric}/${from}/${to}/${group}`} points={current} series={series} domain={domain} extent={extent} group={group} quantity={quantity} lines={lines} productDetails={individual} title={`Gekozen periode · ${dateLabel(from, 'day')} – ${dateLabel(to, 'day')}`} />
    {comparing && <TrendChart key={`comparison/${view}/${product?.id}/${metric}/${comparisonFrom}/${comparisonTo}/${group}`} points={previous} series={series} domain={domain} extent={extent} group={group} quantity={quantity} lines={lines} productDetails={individual} title={`Vergelijkingsperiode · ${dateLabel(comparisonFrom, 'day')} – ${dateLabel(comparisonTo, 'day')}`} />}
    <p className="text-xs text-gray-500 dark:text-gray-400">Alleen geïmporteerde rapportages. Ontbrekende dagen blijven leeg; maandbedragen kunnen onvolledig zijn. {individual && 'Een product dat niet voorkomt in een geïmporteerd rapport telt voor die dag als nul.'}</p>
  </div>;
}
