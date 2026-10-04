import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency } from '@/utils/formatters';

const currency = value => formatCurrency(value, 2);
const label = (period, group) => new Intl.DateTimeFormat('nl-NL', group === 'month'
  ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short', year: 'numeric' })
  .format(new Date(`${period.length === 7 ? `${period}-01` : period}T12:00:00`));
const timestamp = period => Date.parse(`${period.length === 7 ? `${period}-01` : period}T12:00:00Z`);

function RevenueChart({ rows, group }) {
  const width = 800;
  const height = 300;
  const left = 90;
  const right = 40;
  const top = 20;
  const bottom = 45;
  const values = rows.map(row => row.omzet_totaal);
  const min = Math.min(0, ...values);
  const max = Math.max(1, ...values);
  const start = timestamp(rows[0].periode);
  const end = timestamp(rows.at(-1).periode);
  const x = period => start === end ? width / 2 : left + (timestamp(period) - start) / (end - start) * (width - left - right);
  const y = value => top + (max - value) / (max - min) * (height - top - bottom);
  const ticks = Array.from({ length: 5 }, (_, index) => min + (max - min) * index / 4);
  return <svg viewBox={`0 0 ${width} ${height}`} className="w-full min-w-[520px] text-cyan-800 dark:text-cyan-200" role="img" aria-label={`Kassaomzet plus businessclub per ${group === 'month' ? 'maand' : 'dag'}. Bedragen staan in de tabel hieronder.`}>
    {ticks.map(value => <g key={value}><line x1={left} x2={width - right} y1={y(value)} y2={y(value)} className="stroke-gray-200 dark:stroke-gray-700" /><text x={left - 12} y={y(value) + 4} textAnchor="end" className="fill-gray-600 dark:fill-gray-300 text-xs">{currency(value)}</text></g>)}
    <polyline fill="none" stroke="currentColor" strokeWidth="2.5" points={rows.map(row => `${x(row.periode)},${y(row.omzet_totaal)}`).join(' ')} />
    {rows.map(row => <circle key={row.periode} cx={x(row.periode)} cy={y(row.omzet_totaal)} r="4" fill="currentColor"><title>{label(row.periode, group)}: {currency(row.omzet_totaal)}</title></circle>)}
    <text x={left} y={height - 12} className="fill-gray-600 dark:fill-gray-300 text-xs">{label(rows[0].periode, group)}</text>
    {rows.length > 1 && <text x={width - right} y={height - 12} textAnchor="end" className="fill-gray-600 dark:fill-gray-300 text-xs">{label(rows.at(-1).periode, group)}</text>}
  </svg>;
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
      <div className="overflow-x-auto"><RevenueChart rows={rows} group={group} /></div>
      <p className="text-xs text-gray-500 dark:text-gray-400">Alleen geïmporteerde rapportages. Ontbrekende dagen tellen niet als nul; maandbedragen kunnen onvolledig zijn.</p>
      <details><summary className="cursor-pointer text-sm font-medium text-cyan-800 dark:text-cyan-200">Bekijk bedragen</summary><div className="overflow-x-auto mt-3"><table className="w-full text-sm"><thead><tr><th scope="col" className="py-3 text-left font-medium">{group === 'month' ? 'Maand' : 'Dag'}</th><th scope="col" className="py-3 text-right font-medium">Omzet</th></tr></thead><tbody>{rows.map(row => <tr key={row.periode} className="border-t border-gray-100 dark:border-gray-700"><td className="py-3">{label(row.periode, group)}</td><td className="py-3 text-right tabular-nums">{currency(row.omzet_totaal)}</td></tr>)}</tbody></table></div></details>
    </>}
  </div>;
}
