import { useState } from 'react';

const number = new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 1 });
const euros = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 });
const percent = (value) => value == null ? 'Niet van toepassing' : `${number.format(value)}%`;
const fullDate = new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' });

function Metrics({ rows }) {
  return (
    <dl className="divide-y divide-gray-100 dark:divide-gray-700">
      {rows.map(([label, value]) => (
        <div key={label} className="flex items-baseline justify-between gap-4 py-3 text-sm">
          <dt className="text-gray-600 dark:text-gray-300">{label}</dt>
          <dd className="max-w-1/2 shrink-0 text-right font-semibold tabular-nums text-gray-900 dark:text-gray-100">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

function CostEstimate({ open, baseRequired }) {
  const [hours, setHours] = useState('3');
  const [hourlyRate, setHourlyRate] = useState('20');
  const valid = hours !== '' && hourlyRate !== '' && Number.isFinite(Number(hours)) && Number.isFinite(Number(hourlyRate)) && Number(hours) >= 0 && Number(hourlyRate) >= 0 && Number.isFinite(Number(hours) * Number(hourlyRate) * Math.max(open, baseRequired, 1));
  const perTask = valid ? Number(hours) * Number(hourlyRate) : null;
  const amount = (multiplier) => perTask !== null && Number.isFinite(perTask * multiplier) ? euros.format(perTask * multiplier) : '—';

  return (
    <section className="card p-5">
      <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Wat kost betaalde invulling?</h2>
      <p className="mt-1 max-w-prose text-sm text-gray-500 dark:text-gray-400">Rekenvoorbeeld voor de {number.format(open)} vereiste taken die nog niet zijn uitgevoerd of ingepland. Deze aannames wijzigen het vrijwilligersbeleid niet.</p>
      <div className="mt-5 grid gap-6 lg:grid-cols-2">
        <div className="flex flex-wrap content-start gap-4">
          <label className="block text-sm text-gray-700 dark:text-gray-300">
            Uren per taak
            <input type="number" min="0" step="any" inputMode="decimal" value={hours} onChange={(event) => setHours(event.target.value)} className="input mt-1 w-32" />
          </label>
          <label className="block text-sm text-gray-700 dark:text-gray-300">
            Kosten per uur (€)
            <input type="number" min="0" step="any" inputMode="decimal" value={hourlyRate} onChange={(event) => setHourlyRate(event.target.value)} className="input mt-1 w-40" />
          </label>
          {!valid && <p className="basis-full text-sm text-red-700 dark:text-red-300">Vul voor beide aannames een getal van 0 of hoger in.</p>}
        </div>
        <div aria-live="polite" aria-atomic="true">
          <Metrics rows={[
            ['Kosten per taak', amount(1)],
            [`Afkoopindicatie bij ${baseRequired} taken`, amount(baseRequired)],
            ['Kosten resterende verplichting', amount(open)],
          ]} />
        </div>
      </div>
      <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">Indicatie op basis van uren × uurkosten, zonder aanvullende kosten. Dit is geen vastgesteld afkoopbedrag en geen raming voor alle onbezette dienstplekken.</p>
    </section>
  );
}

function History({ data }) {
  const rows = [
    { ...data.policy, date: data.generated_at.slice(0, 10), current: true },
    ...(data.policy_history || []).toSorted((a, b) => b.date.localeCompare(a.date)),
  ];
  const differentDefinitions = rows.some((row) => row.definition_version !== data.policy.definition_version);
  return (
    <section className="card p-5">
      <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Verloop per peildatum</h2>
      <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Dagelijks opgeslagen standen voor seizoen {data.season}, vanaf ingebruikname. Eerdere standen worden niet gereconstrueerd.</p>
      {differentDefinitions && <p className="mt-2 text-sm text-amber-800 dark:text-amber-200">Standen met een andere rekenbasis zijn niet rechtstreeks vergelijkbaar.</p>}
      <div className="mt-5 max-h-96 overflow-auto" role="region" aria-label="Historische beleidsstatistieken" tabIndex={0}>
        <table className="w-full text-sm text-left whitespace-nowrap">
          <thead className="text-gray-500 dark:text-gray-400">
            <tr>{['Peildatum', 'Bezetting', 'Op schema', 'Niets gepland', 'Uitgevoerd', 'Open taken'].map((label) => <th scope="col" key={label} className="px-3 py-2 first:pl-0 font-medium">{label}</th>)}</tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-300 tabular-nums">
            {rows.map((row) => (
              <tr key={row.current ? 'current' : row.date} className={row.current ? 'font-semibold text-gray-900 dark:text-gray-100' : ''}>
                <th scope="row" className="pr-3 py-3 font-medium">
                  {row.current ? 'Nu herberekend' : <time dateTime={row.generated_at || row.date}>{fullDate.format(new Date(`${row.date}T12:00:00`))}</time>}
                  {row.definition_version !== data.policy.definition_version && <span className="block text-xs text-amber-800 dark:text-amber-200">Andere rekenbasis</span>}
                </th>
                <td className="px-3 py-3">{percent(row.fill_rate)}</td>
                <td className="px-3 py-3">{percent(row.on_track_rate)}</td>
                <td className="px-3 py-3">{number.format(row.not_started)}</td>
                <td className="px-3 py-3">{percent(row.completed_rate)}</td>
                <td className="px-3 py-3">{number.format(row.open)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {rows.length === 1 && <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">Er zijn nog geen dagstanden opgeslagen voor dit seizoen.</p>}
    </section>
  );
}

export default function VolunteerPolicyDashboard({ data, children }) {
  const policy = data.policy;
  if (!policy) return <p className="text-sm text-gray-500 dark:text-gray-400">Het beleidsoverzicht is nog niet beschikbaar. Ververs de statistieken.</p>;
  const rows = [
    ['Bezettingsgraad diensten', percent(policy.fill_rate)],
    ['Plichtige eenheden (excl. vrijgesteld)', number.format(policy.liable_units)],
    ['Op schema (voldaan + voldoende ingepland)', percent(policy.on_track_rate)],
    ['Nog niets gepland of uitgevoerd', `${number.format(policy.not_started)} · ${percent(policy.not_started_rate)}`],
    ['Vereiste taken uitgevoerd', `${number.format(policy.completed)} · ${percent(policy.completed_rate)}`],
    ['Vereiste taken uitgevoerd of ingepland', `${number.format(policy.covered)} · ${percent(policy.covered_rate)}`],
    ['Vereiste taken nog open', number.format(policy.open)],
    ['Totaal vereiste taken', number.format(policy.required)],
    ['Basisverplichting per eenheid', number.format(policy.base_required)],
    ['Max. bezetting vanuit de verplichting', percent(policy.obligation_capacity_rate)],
  ];
  return (
    <div className="space-y-6">
      <section className="card p-5">
        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Laatste stand vrijwilligersbeleid</h2>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Seizoen {data.season} · nu herberekend op basis van actuele diensten en vrijwilligersplicht.</p>
        <div className="mt-5 grid gap-8 xl:grid-cols-2">
          <div>
            <Metrics rows={rows} />
            <p className="mt-4 max-w-prose text-xs text-gray-500 dark:text-gray-400">Bezetting: {number.format(policy.assignments)} inschrijvingen op {number.format(policy.capacity)} beschikbare plekken. Inschrijvingen en nagekomen verplichtingen zijn verschillende tellingen; taakcredits vullen geen dienstplek.</p>
            <p className="mt-4 max-w-prose text-xs text-gray-500 dark:text-gray-400">Een eenheid is een speler of gezin met een eigen verplichting. Gezinsgrootte en instroomdatum kunnen het aantal taken wijzigen. Uitgevoerde en ingeplande taken tellen per eenheid maximaal tot de eigen verplichting mee; extra inzet vult de plicht van een ander niet in. Ook toegekende taakcredits tellen mee.</p>
            <p className="mt-2 max-w-prose text-xs text-gray-500 dark:text-gray-400">De maximale bezetting vergelijkt de totale verplichting met het aanbod. Extra inzet en vrijgestelde vrijwilligers kunnen de werkelijke bezetting verhogen.</p>
          </div>
          <div className="min-w-0 space-y-8">{children}</div>
        </div>
      </section>
      <CostEstimate open={policy.open} baseRequired={policy.base_required} />
      <History data={data} />
    </div>
  );
}
