import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency } from '@/utils/formatters';

const panel = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800';
const muted = 'text-sm text-gray-600 dark:text-gray-300';
const groups = [
  ['food', 'Alleen eten', 'Eten', 'bg-orange-500'],
  ['non_food', 'Alleen drinken', 'Drank', 'bg-cyan-600'],
  ['mixed', 'Eten + drinken', null, 'bg-violet-500'],
  ['unassigned', 'Nog niet ingedeeld', 'Nog niet ingedeeld', 'bg-gray-500'],
  ['entree', 'Alleen entree', 'Entree', 'bg-emerald-600'],
];
const sum = (values) => Object.values(values).reduce((total, value) => total + value, 0);
const money = (cents) => formatCurrency(cents / 100, 2);
const staffLabel = (staff) => !staff?.scheduled ? 'Geen dienst' : staff.min === staff.max ? `${staff.min}` : `${staff.min}–${staff.max}`;
const defaultDate = () => {
  const local = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
  const date = new Date(`${local}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() - 1);
  return date.toISOString().slice(0, 10);
};

function Fixtures({ matches }) {
  const counted = matches.fixtures.filter(fixture => !fixture.reason);
  const activities = matches.fixtures.filter(fixture => fixture.reason === 'Activiteit');
  const excluded = matches.fixtures.filter(fixture => fixture.reason && fixture.reason !== 'Activiteit');
  const special = (home, time) => home ? `Thuis · ${time}` : home === false ? 'Geen thuiswedstrijd' : 'Niet vastgesteld';
  return <section className={panel} aria-labelledby="kantine-matches-title">
    <div className="flex flex-wrap items-start justify-between gap-5">
      <div><h3 id="kantine-matches-title" className="text-lg font-semibold">Thuiswedstrijden op De Wijchert</h3><p className="mt-2 text-2xl font-semibold tabular-nums">{matches.complete ? matches.count : matches.count ? `Minstens ${matches.count}` : 'Onbekend'}</p><p className={`${muted} mt-1`}>{matches.complete ? 'Volledig opgeslagen dagprogramma' : 'Het opgeslagen programma is onvolledig.'}</p></div>
      <dl className="flex flex-wrap gap-x-8 gap-y-3 text-sm"><div><dt className="font-semibold">AWC</dt><dd className="mt-1">{special(matches.first_home, matches.first_time)}</dd></div><div><dt className="font-semibold">O23-1</dt><dd className="mt-1">{special(matches.u23_home, matches.u23_time)}</dd></div></dl>
    </div>
    <details className="mt-5 border-t border-gray-200 pt-4 dark:border-gray-700"><summary className="cursor-pointer text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-600">Bekijk getelde wedstrijden en activiteiten</summary>
      {[[`Jeugd (${counted.filter(f => f.youth).length})`, counted.filter(f => f.youth)], [`Senioren (${counted.filter(f => !f.youth).length})`, counted.filter(f => !f.youth)], ['Activiteiten, apart gehouden', activities], ['Niet meegeteld', excluded]].filter(([, rows]) => rows.length).map(([title, rows]) => <div key={title} className="mt-5"><h4 className="text-sm font-semibold">{title}</h4><ul className="mt-2 space-y-2 text-sm">{rows.map(fixture => <li key={fixture.id} className="flex gap-3"><span className="tabular-nums">{fixture.time}</span><span>{fixture.home_team} – {fixture.away_team}{fixture.reason && <span className="block text-gray-600 dark:text-gray-300">{fixture.reason}{fixture.reason === 'Andere locatie' ? ` · ${fixture.location}` : ''}</span>}</span></li>)}</ul></div>)}
      {!matches.fixtures.length && <p className={`${muted} mt-3`}>Voor deze dag zijn nog geen wedstrijden opgeslagen. Dat betekent niet dat er geen wedstrijden waren.</p>}
      <p className={`${muted} mt-4`}>AWC is het eerste zondagteam, ook als het op zaterdag speelt. AWC 1 Veld-Zaterdag is een ander team. Afgelastingen, trainingen en andere locaties tellen niet mee.</p>
    </details>
  </section>;
}

function HourDetails({ hour, staffing }) {
  const total = sum(hour.transactions);
  return <section className="mt-6 border-t border-gray-200 pt-5 dark:border-gray-700" aria-labelledby="kantine-hour-title">
    <h3 id="kantine-hour-title" className="text-lg font-semibold">{hour.hour}:00–{String((Number(hour.hour) + 1) % 24).padStart(2, '0')}:00</h3>
    <p className={`${muted} mt-1`}>{total} aankopen · {money(sum(hour.revenue))} omzet · {staffLabel(staffing)} ingepland{!hour.complete ? ' · Uur nog niet volledig ingelezen' : ''}</p>
    <div className="mt-4 grid gap-6 sm:grid-cols-2">
      <div><h4 className="text-sm font-semibold">Aankopen</h4><dl className="mt-2 space-y-2 text-sm">{groups.map(([key, label]) => <div key={key} className="flex justify-between gap-3"><dt>{label}</dt><dd className="tabular-nums">{hour.transactions[key]}</dd></div>)}</dl></div>
      <div><h4 className="text-sm font-semibold">Omzet per productgroep</h4><dl className="mt-2 space-y-2 text-sm">{groups.filter(([, , label]) => label).map(([key, , label]) => <div key={key} className="flex justify-between gap-3"><dt>{label}</dt><dd className="tabular-nums">{money(hour.revenue[key])}</dd></div>)}</dl></div>
    </div>
    <p className={`${muted} mt-4`}>{hour.transactions.food + hour.transactions.mixed} ingedeelde aankopen bevatten eten; {hour.transactions.non_food + hour.transactions.mixed} bevatten drinken. Gemengde aankopen zitten in beide aantallen.</p>
    {hour.corrections > 0 && <p className={`${muted} mt-2`}>{hour.corrections} correcties zijn verwerkt in de omzet en tellen niet als nieuwe aankopen.</p>}
  </section>;
}

export default function KantineDrukte() {
  const [date, setDate] = useState(defaultDate);
  const [metric, setMetric] = useState('transactions');
  const [selectedHour, setSelectedHour] = useState(null);
  const query = useQuery({ queryKey: ['twelve', 'activity', date], queryFn: async () => (await prmApi.getTwelve('activity', { date })).data });
  const data = query.data;
  const hours = data?.hours?.filter(hour => hour.covered) ?? [];
  const visibleGroups = groups.filter(([, , label]) => metric === 'transactions' || label);
  const maximum = Math.max(1, ...hours.map(hour => sum(Object.fromEntries(Object.entries(hour[metric]).map(([key, value]) => [key, Math.max(0, value)])))));
  const chosen = hours.find(hour => hour.hour === selectedHour);
  const totalTransactions = data?.hours ? data.hours.reduce((total, hour) => total + sum(hour.transactions), 0) : null;

  return <div className="space-y-5">
    <div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="text-xl font-semibold">Drukte & bezetting</h2><p className={`${muted} mt-1`}>Aankopen, thuiswedstrijden en ingeplande kantinediensten naast elkaar.</p></div><label className="text-sm font-medium">Kassadag<input type="date" className="input mt-1 block" value={date} onChange={event => { if (event.target.value) { setDate(event.target.value); setSelectedHour(null); } }} /></label></div>
    {query.isPending ? <p role="status">Daggegevens laden…</p> : query.isError ? <div className={panel}><p role="alert">De daggegevens konden niet worden geladen.</p><button type="button" className="btn-secondary mt-3" onClick={() => query.refetch()}>Opnieuw proberen</button></div> : <>
      <Fixtures matches={data.matches} />
      <section className={panel} aria-labelledby="kantine-compare-title"><h3 id="kantine-compare-title" className="text-lg font-semibold">Omzet op vergelijkbare dagen</h3><div className="mt-4 flex flex-wrap gap-x-10 gap-y-4"><div><p className={muted}>Deze kassadag{!data.report_complete && data.revenue !== null ? ' · voorlopig' : ''}</p><p className="mt-1 text-2xl font-semibold tabular-nums">{data.revenue === null ? 'Nog niet ingelezen' : formatCurrency(data.revenue, 2)}</p><p className={`${muted} mt-1`}>{totalTransactions === null ? 'Aantal aankopen nog onbekend' : `${totalTransactions} aankopen`}</p></div><div><p className={muted}>Mediaan vergelijkbare dagen</p><p className="mt-1 text-2xl font-semibold tabular-nums">{data.comparison.median === null ? 'Nog niet beschikbaar' : formatCurrency(data.comparison.median, 2)}</p><p className={`${muted} mt-1`}>{data.comparison.reason === 'incomplete_matches' ? 'Een volledig wedstrijdprogramma is nodig.' : `${data.comparison.count} vergelijkbare dagen; minimaal 5 nodig.`}</p></div></div><details className="mt-4"><summary className="cursor-pointer text-sm font-medium">Hoe worden dagen vergeleken?</summary><p className={`${muted} mt-3 max-w-prose`}>Alleen afgesloten kassadagen met een volledig wedstrijdarchief uit de voorgaande 12 maanden: dezelfde weekdag, maximaal twee thuiswedstrijden meer of minder en dezelfde thuisstatus voor AWC en O23-1. De mediaan is de middelste omzet. Dit laat samenhang zien, geen bewezen oorzaak.</p></details></section>
      <section className={panel} aria-labelledby="kantine-hours-title">
        <div className="flex flex-wrap items-center justify-between gap-4"><h3 id="kantine-hours-title" className="text-lg font-semibold">Per uur</h3><div className="flex gap-2" aria-label="Eenheid">{[['transactions', 'Aankopen'], ['revenue', 'Omzet']].map(([value, label]) => <button key={value} type="button" aria-pressed={metric === value} className={metric === value ? 'btn-primary' : 'btn-secondary'} onClick={() => setMetric(value)}>{label}</button>)}</div></div>
        {data.hours === null ? <p className={`${muted} mt-5`}>Voor deze dag zijn geen aankopen met tijdstippen geïmporteerd. Dagomzet is geen bewijs voor nul aankopen in een bepaald uur.</p> : <>
          <ul className="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Legenda">{visibleGroups.map(([key, label, revenueLabel, color]) => <li key={key} className="flex items-center gap-2"><span aria-hidden="true" className={`h-3 w-3 rounded-sm ${color}`} />{metric === 'transactions' ? label : revenueLabel}</li>)}</ul>
          <div className="mt-5 grid grid-cols-[3rem_minmax(0,1fr)_4.5rem_4.5rem] gap-2 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-[3rem_minmax(0,1fr)_6rem_6rem]"><span>Uur</span><span>{metric === 'transactions' ? 'Aankopen' : 'Omzet'}</span><span className="text-right">Totaal</span><span className="text-right">Ingepland</span></div>
          <div className="mt-2 space-y-1">{hours.map(hour => <button type="button" key={hour.hour} aria-expanded={selectedHour === hour.hour} aria-label={`${hour.hour}:00, ${sum(hour.transactions)} aankopen, ${money(sum(hour.revenue))}, ${staffLabel(data.staffing[hour.hour])} ingepland. Toon details.`} onClick={() => setSelectedHour(selectedHour === hour.hour ? null : hour.hour)} className={`grid min-h-11 w-full grid-cols-[3rem_minmax(0,1fr)_4.5rem_4.5rem] items-center gap-2 rounded-md px-1 text-sm transition-colors hover:bg-cyan-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:hover:bg-gray-700 sm:grid-cols-[3rem_minmax(0,1fr)_6rem_6rem] ${selectedHour === hour.hour ? 'bg-cyan-50 dark:bg-gray-700' : ''}`}><span className="text-left tabular-nums">{hour.hour}:00</span><span aria-hidden="true" className="flex h-6 overflow-hidden rounded-sm">{visibleGroups.map(([key, , , color]) => <span key={key} className={color} style={{ width: `${Math.max(0, hour[metric][key]) / maximum * 100}%` }} />)}</span><span className="text-right text-xs tabular-nums sm:text-sm">{metric === 'transactions' ? sum(hour.transactions) : money(sum(hour.revenue))}</span><span className="text-right text-xs tabular-nums sm:text-sm">{staffLabel(data.staffing[hour.hour])}</span></button>)}</div>
          {chosen && <HourDetails hour={chosen} staffing={data.staffing[chosen.hour]} />}
          <p className={`${muted} mt-5`}>Kies een uur voor de uitsplitsing. Ingepland is het aantal toegewezen vrijwilligers; een bereik betekent dat de bezetting binnen het uur wisselt. Aanwezigheid is niet gemeten.</p>
        </>}
        <details className="mt-5 border-t border-gray-200 pt-4 dark:border-gray-700"><summary className="cursor-pointer text-sm font-medium">Wat telt mee?</summary><div className={`${muted} mt-3 max-w-prose space-y-2`}><p>Elke aankoop telt één keer, ook bij gesplitste betaling. Eten + drinken is een aparte groep. Als een product nog niet is ingedeeld, valt de hele aankoop onder Nog niet ingedeeld. Entree bij een eet- of drankaankoop verandert die indeling niet.</p><p>Omzet bestaat uit kassaverkopen en Businessclub, inclusief btw, verdeeld over de producten op de bon. Merchandise, Overig, overige no-sales en opwaarderingen tellen niet mee. Correcties verlagen de omzet; negatieve bedragen blijven zichtbaar in de totalen en uurdetails.</p><p>De kassadag loopt van 06:00 tot 06:00 in Amsterdam. Bij de overgang naar wintertijd zijn de twee uren van 02:00 samengevoegd. Een onvolledig geïmporteerd uur kan nog veranderen.</p></div></details>
      </section>
    </>}
  </div>;
}
