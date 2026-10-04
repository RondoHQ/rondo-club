import { useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency } from '@/utils/formatters';

const currency = value => formatCurrency(value, 2);
const label = (period, group) => new Intl.DateTimeFormat('nl-NL', group === 'month'
  ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short', year: 'numeric' })
  .format(new Date(`${period.length === 7 ? `${period}-01` : period}T12:00:00`));
const timestamp = period => Date.parse(`${period.length === 7 ? `${period}-01` : period}T12:00:00Z`);

function RevenueChart({ rows, group }) {
  const [selectedPeriod, setSelectedPeriod] = useState(null);
  const svg = useRef(null);
  const selected = rows.find(row => row.periode === selectedPeriod);
  const selectPoint = (event, index) => {
    const moves = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: rows.length - 1 };
    if (event.key in moves) {
      event.preventDefault();
      const next = Math.max(0, Math.min(rows.length - 1, moves[event.key]));
      setSelectedPeriod(rows[next].periode);
      svg.current.querySelector(`[data-point="${next}"]`).focus();
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      setSelectedPeriod(rows[index].periode);
    }
  };
  const width = 800;
  const height = 370;
  const left = 90;
  const right = 40;
  const top = 20;
  const bottom = 115;
  const values = rows.map(row => row.omzet_totaal);
  const rawMin = Math.min(0, ...values);
  const rawMax = Math.max(1, ...values);
  const magnitude = 10 ** Math.floor(Math.log10((rawMax - rawMin) / 5));
  const step = [1, 2, 5, 10].find(value => value * magnitude >= (rawMax - rawMin) / 5) * magnitude;
  const min = Math.floor(rawMin / step) * step;
  const max = Math.ceil(rawMax / step) * step;
  const start = timestamp(rows[0].periode);
  const end = timestamp(rows.at(-1).periode);
  const x = period => start === end ? width / 2 : left + (timestamp(period) - start) / (end - start) * (width - left - right);
  const y = value => top + (max - value) / (max - min) * (height - top - bottom);
  const ticks = Array.from({ length: Math.round((max - min) / step) + 1 }, (_, index) => min + step * index);
  const tooltipX = selected ? Math.max(left, Math.min(width - right - 205, x(selected.periode) - 102)) : 0;
  const tooltipY = selected ? (y(selected.omzet_totaal) < top + 65 ? y(selected.omzet_totaal) + 15 : y(selected.omzet_totaal) - 65) : 0;
  // Keep date labels at least 50 SVG units apart, including the last date.
  const dateTicks = rows.filter((row, index) => index === 0 || index === rows.length - 1 || (x(row.periode) - left >= 50 && width - right - x(row.periode) >= 50));
  const dates = dateTicks.reduce((ticks, row, index) => {
    if (!ticks.length || index === dateTicks.length - 1 || x(row.periode) - x(ticks.at(-1).periode) >= 50) ticks.push(row);
    return ticks;
  }, []);
  return <>
    <p className="text-sm text-gray-600 dark:text-gray-300">Klik op een punt voor de dag of maand en het bedrag. Met de pijltjestoetsen ga je naar het vorige of volgende punt.</p>
    <div className="overflow-x-auto"><svg ref={svg} onClick={event => {
      const bounds = svg.current.getBoundingClientRect();
      const px = (event.clientX - bounds.left) * width / bounds.width;
      const py = (event.clientY - bounds.top) * height / bounds.height;
      const nearest = rows.reduce((best, row) => {
        const distance = Math.hypot(x(row.periode) - px, y(row.omzet_totaal) - py);
        return distance < best.distance ? { row, distance } : best;
      }, { distance: Infinity });
      if (nearest.distance <= 24) setSelectedPeriod(nearest.row.periode);
    }} viewBox={`0 0 ${width} ${height}`} className="w-full min-w-[800px] text-cyan-800 dark:text-cyan-200" role="group" aria-label={`Kassaomzet plus businessclub per ${group === 'month' ? 'maand' : 'dag'}. Bedragen staan in de tabel hieronder.`}>
    {ticks.map(value => <g key={value}><line x1={left} x2={width - right} y1={y(value)} y2={y(value)} className="stroke-gray-200 dark:stroke-gray-700" /><text x={left - 12} y={y(value) + 4} textAnchor="end" className="fill-gray-600 dark:fill-gray-300 text-xs">{currency(value)}</text></g>)}
    <polyline fill="none" stroke="currentColor" strokeWidth="2.5" points={rows.map(row => `${x(row.periode)},${y(row.omzet_totaal)}`).join(' ')} />
    {rows.map((row, index) => <g key={row.periode} data-point={index} role="button" tabIndex={selected ? (selected.periode === row.periode ? 0 : -1) : (index === 0 ? 0 : -1)} aria-label={`${label(row.periode, group)}: ${currency(row.omzet_totaal)}`} aria-pressed={selectedPeriod === row.periode} onClick={() => setSelectedPeriod(row.periode)} onKeyDown={event => selectPoint(event, index)} className="cursor-pointer outline-none group">
      <circle cx={x(row.periode)} cy={y(row.omzet_totaal)} r="11" fill="transparent" className="group-focus:stroke-current" strokeWidth="2" />
      <circle cx={x(row.periode)} cy={y(row.omzet_totaal)} r={selectedPeriod === row.periode ? 6 : 4} fill="currentColor" className="group-hover:stroke-current" strokeWidth="3" />
      <title>{label(row.periode, group)}: {currency(row.omzet_totaal)}</title>
    </g>)}
    {selected && <g transform={`translate(${tooltipX},${tooltipY})`} pointerEvents="none" aria-hidden="true">
      <rect width="205" height="52" rx="6" className="fill-white stroke-gray-300 dark:fill-gray-800 dark:stroke-gray-500" />
      <text x="12" y="20" className="fill-gray-900 dark:fill-gray-100 text-xs">{label(selected.periode, group)}</text>
      <text x="12" y="39" className="fill-gray-900 dark:fill-gray-100 text-sm font-semibold">{currency(selected.omzet_totaal)}</text>
    </g>}
    {dates.map(row => <g key={row.periode}>
      <line x1={x(row.periode)} x2={x(row.periode)} y1={height - bottom} y2={height - bottom + 6} className="stroke-gray-400" />
      <text transform={`translate(${x(row.periode)},${height - bottom + 15}) rotate(90)`} className="fill-gray-600 dark:fill-gray-300 text-xs">{label(row.periode, group)}</text>
    </g>)}
  </svg></div>
    <div aria-live="polite" aria-atomic="true" className="text-sm min-h-16">
      {selected && <><p className="font-semibold">{label(selected.periode, group)}{selected.provisional ? ' · Voorlopig' : ''}</p><p className="mt-1">Totale omzet: <strong className="tabular-nums">{currency(selected.omzet_totaal)}</strong></p><p className="text-gray-600 dark:text-gray-300 mt-1">Kassaomzet: {currency(selected.kassaomzet)} · Businessclub: {currency(selected.businessclub)}</p></>}
    </div>
  </>;
}

export default function Omzetontwikkeling() {
  const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
  const [from, setFrom] = useState(`${Number(today.slice(0, 4)) - 1}-${today.slice(5, 7)}-01`);
  const [to, setTo] = useState(today);
  const [group, setGroup] = useState('day');
  const valid = Boolean(from && to && from <= to);
  const query = useQuery({ queryKey: ['twelve', 'trend', from, to, group], queryFn: async () => (await prmApi.getTwelve('summary', { from, to, group })).data, enabled: valid });
  const rows = query.data?.buckets ?? [];
  return <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-5">
    <div><h2 className="text-lg font-semibold">Omzetontwikkeling</h2><p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Kassaomzet + businessclub, inclusief btw. Overig no-sale-verbruik telt niet mee.</p></div>
    <div className="flex flex-wrap items-end gap-4">
      <label className="text-sm">Vanaf<input type="date" required value={from} onChange={event => setFrom(event.target.value)} className="input block mt-1" /></label>
      <label className="text-sm">Tot en met<input type="date" required value={to} onChange={event => setTo(event.target.value)} className="input block mt-1" /></label>
      <label className="text-sm">Groeperen<select value={group} onChange={event => setGroup(event.target.value)} className="input block mt-1"><option value="day">Per dag</option><option value="month">Per maand</option></select></label>
    </div>
    {!valid ? <p role="alert" className="text-sm text-red-700 dark:text-red-300">Kies een begindatum die vóór of op de einddatum ligt.</p> : query.isPending ? <p role="status">Omzet laden…</p> : query.isError ? <div><p role="alert">De omzet kon niet worden geladen.</p><button className="btn-secondary mt-3" onClick={() => query.refetch()}>Opnieuw proberen</button></div> : !rows.length ? <p className="text-sm text-gray-500 dark:text-gray-400">Geen rapportages in deze periode. Kies een andere periode.</p> : <>
      <p className="text-sm">Totaal in deze periode: <strong className="tabular-nums">{currency(rows.reduce((sum, row) => sum + row.omzet_totaal, 0))}</strong></p>
      <RevenueChart key={`${from}/${to}/${group}`} rows={rows} group={group} />
      <p className="text-xs text-gray-500 dark:text-gray-400">Alleen geïmporteerde rapportages. Ontbrekende dagen tellen niet als nul; maandbedragen kunnen onvolledig zijn.</p>
      <details><summary className="cursor-pointer text-sm font-medium text-cyan-800 dark:text-cyan-200">Bekijk bedragen</summary><div className="overflow-x-auto mt-3"><table className="w-full text-sm"><thead><tr><th scope="col" className="py-3 text-left font-medium">{group === 'month' ? 'Maand' : 'Dag'}</th><th scope="col" className="py-3 text-right font-medium">Omzet</th></tr></thead><tbody>{rows.map(row => <tr key={row.periode} className="border-t border-gray-100 dark:border-gray-700"><td className="py-3">{label(row.periode, group)}</td><td className="py-3 text-right tabular-nums">{currency(row.omzet_totaal)}</td></tr>)}</tbody></table></div></details>
    </>}
  </div>;
}
