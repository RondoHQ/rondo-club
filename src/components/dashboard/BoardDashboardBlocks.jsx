import { Link } from 'react-router-dom';
import PersonAvatar from '@/components/PersonAvatar';
import { format, parseYmd, isValid } from '@/utils/dateFormat';

const panel = 'rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800';
const muted = 'text-sm text-gray-500 dark:text-gray-400';
const action = 'text-sm text-cyan-800 underline underline-offset-4 dark:text-cyan-200';
const number = value => value.toLocaleString('nl-NL');
const date = value => {
  const parsed = parseYmd(value);
  return isValid(parsed) ? format(parsed, 'd MMMM') : 'Datum onbekend';
};

export function BoardAnniversaries({ items }) {
  return <section aria-labelledby="dashboard-anniversaries" className="lg:col-span-12">
    <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2"><h2 id="dashboard-anniversaries" className="text-lg font-semibold">Jubilarissen</h2><Link className={action} to="/people/jubilarissen">Alle jubilarissen</Link></div>
    <p className={`mb-3 ${muted}`}>Lidmaatschapsjubilea in de komende 90 dagen.</p>
    <ul className={`${panel} divide-y divide-gray-100 px-4 dark:divide-gray-700`}>
      {items.slice(0, 6).map(item => <li key={item.id}><Link to={`/people/${item.person.id}`} className="flex flex-wrap items-center gap-3 py-3 hover:underline">
        <PersonAvatar thumbnail={item.person.thumbnail} name={item.person.name} size="md" />
        <div className="min-w-0 flex-1"><p className="text-sm font-semibold">{item.person.name}</p><p className={muted}>{item.title}</p></div>
        <span className={`text-sm ${item.days_until === 0 ? 'font-semibold text-cyan-800 dark:text-cyan-200' : 'text-gray-500 dark:text-gray-400'}`}>{item.days_until === 0 ? 'Vandaag' : date(item.anniversary_date)}</span>
      </Link></li>)}
      {!items.length && <li className={`py-4 ${muted}`}>Geen lidmaatschapsjubilea in de komende 90 dagen.</li>}
    </ul>
    {items.length > 6 && <p className={`mt-2 ${muted}`}>De eerstvolgende 6 van {number(items.length)} jubilea. <Link className={action} to="/people/jubilarissen">Bekijk alle jubilarissen</Link></p>}
  </section>;
}

export function BoardMembership({ data }) {
  return <section aria-labelledby="dashboard-membership" className="lg:col-span-12">
    <h2 id="dashboard-membership" className="mb-1 text-lg font-semibold">Ledenontwikkeling</h2>
    <p className={`mb-3 ${muted}`}>Alleen spelende bondsleden · Seizoen {data.season} · {date(data.from)} t/m {date(data.to)}</p>
    <dl className={`${panel} grid divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-gray-700`}>
      {[['Spelende bondsleden', data.active, 'Huidige ledenstand'], ['Instroom', data.joined, 'Lid geworden dit seizoen'], ['Uitstroom', data.left, data.left_unknown > 0 ? 'Bevestigd · telling onvolledig' : 'Lidmaatschap geëindigd dit seizoen']].map(([label, value, description]) => <div key={label} className="p-4"><dt className="text-sm font-medium">{label}</dt><dd className="mt-2 text-2xl font-semibold tabular-nums">{number(value)}</dd><dd className={`mt-1 ${muted}`}>{description}</dd></div>)}
    </dl>
    {data.left_unknown > 0 && <p className={`mt-2 ${muted}`}>Bij {number(data.left_unknown)} {data.left_unknown === 1 ? 'uitgestroomd bondslid ontbreekt' : 'uitgestroomde bondsleden ontbreekt'} de spelactiviteit. {data.left_unknown === 1 ? 'Dit lid is' : 'Deze leden zijn'} niet meegeteld bij uitstroom.</p>}
  </section>;
}

export function BoardVolunteers({ data }) {
  return <section aria-labelledby="dashboard-volunteers" className="lg:col-span-12">
    <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2"><h2 id="dashboard-volunteers" className="text-lg font-semibold">Vrijwilligersbezetting</h2><Link className={action} to="/vrijwilligers/diensten">Naar diensten</Link></div>
    <div className={`${panel} px-4`}>
      <p className="border-b border-gray-100 py-4 text-sm dark:border-gray-700"><strong className="tabular-nums">{number(data.open_spots)} open {data.open_spots === 1 ? 'plek' : 'plekken'}</strong> bij {number(data.total_shifts)} {data.total_shifts === 1 ? 'dienst' : 'diensten'} in de komende {data.window_days} dagen.</p>
      <ul className="divide-y divide-gray-100 dark:divide-gray-700">{data.shifts.slice(0, 5).map(shift => <li key={shift.id}><Link to={`/vrijwilligers/diensten/${shift.id}`} className="flex flex-wrap items-center justify-between gap-3 py-3 hover:underline"><div className="min-w-0"><p className="text-sm font-medium">{shift.title}</p><p className={muted}>{date(shift.start_datetime.slice(0, 10))} · {shift.start_datetime.slice(11, 16)}</p></div><span className="text-sm font-semibold text-amber-800 dark:text-amber-200">{shift.spots_remaining} {shift.spots_remaining === 1 ? 'plek' : 'plekken'} open</span></Link></li>)}</ul>
      {!data.total_shifts && <p className={`py-4 ${muted}`}>Geen open plekken in de geplande diensten binnen deze periode.</p>}
      {data.total_shifts > 5 && <p className={`border-t border-gray-100 py-3 dark:border-gray-700 ${muted}`}>De eerstvolgende 5 diensten met open plekken. <Link className={action} to="/vrijwilligers/statistieken">Bekijk de bezetting</Link></p>}
    </div>
  </section>;
}

export function BoardVog({ data }) {
  return <section aria-labelledby="dashboard-vog" className="lg:col-span-12">
    <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2"><h2 id="dashboard-vog" className="text-lg font-semibold">VOG-aandachtspunten</h2><Link className={action} to="/vrijwilligers/vog">Naar VOG-overzicht</Link></div>
    <dl className={`${panel} divide-y divide-gray-100 px-4 dark:divide-gray-700`}>
      {[['Ontbreekt of verlopen · nog niet aangevraagd', data.not_submitted_to_justis], ['Ontbreekt of verlopen · aangevraagd bij Justis', data.submitted_to_justis], ['Verloopt binnen 30 dagen', data.expiring_soon]].map(([label, value]) => <div key={label} className="flex items-baseline justify-between gap-4 py-3 text-sm"><dt>{label}</dt><dd className="font-semibold tabular-nums">{number(value)}</dd></div>)}
    </dl>
  </section>;
}
