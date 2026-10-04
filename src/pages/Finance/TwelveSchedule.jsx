import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const weekdays = [[1, 'Maandag'], [2, 'Dinsdag'], [3, 'Woensdag'], [4, 'Donderdag'], [5, 'Vrijdag'], [6, 'Zaterdag'], [0, 'Zondag']];
const hourLabel = hour => hour === 24 ? '00.00 (volgende dag)' : `${String(hour).padStart(2, '0')}.00`;

function ScheduleForm({ schedule, onSaved }) {
  const [days, setDays] = useState(schedule.days);
  const queryClient = useQueryClient();
  const save = useMutation({ mutationFn: () => prmApi.setTwelveSchedule({ days }), onSuccess: result => { queryClient.setQueryData(['twelve', 'schedule'], result.data); onSaved(true); } });
  const update = (day, values) => { save.reset(); onSaved(false); setDays(current => current.map(window => window.day === day ? { ...window, ...values } : window)); };
  const valid = days.every(window => window.end > window.start);
  return <form onSubmit={event => { event.preventDefault(); if (valid) save.mutate(); }} className="space-y-5">
    <div className="space-y-4">{weekdays.map(([day, name]) => {
      const window = days.find(item => item.day === day);
      return <div key={day} className="flex flex-wrap items-center gap-3 border-b border-gray-100 dark:border-gray-700 pb-4">
        <label className="flex items-center gap-2 w-32 text-sm font-medium"><input type="checkbox" checked={Boolean(window)} disabled={save.isPending} onChange={event => { save.reset(); onSaved(false); setDays(current => event.target.checked ? [...current, { day, start: day === 0 || day === 6 ? 10 : 20, end: 24 }] : current.filter(item => item.day !== day)); }} />{name}</label>
        {window ? <><label className="text-sm">Van<select aria-label={`${name} begintijd`} value={window.start} disabled={save.isPending} onChange={event => update(day, { start: Number(event.target.value) })} className="input ml-2">{Array.from({ length: 24 }, (_, hour) => <option key={hour} value={hour}>{hourLabel(hour)}</option>)}</select></label><label className="text-sm">Tot<select aria-label={`${name} eindtijd`} value={window.end} disabled={save.isPending} onChange={event => update(day, { end: Number(event.target.value) })} className="input ml-2">{Array.from({ length: 24 }, (_, index) => index + 1).map(hour => <option key={hour} value={hour}>{hourLabel(hour)}</option>)}</select></label></> : <span className="text-sm text-gray-500 dark:text-gray-400">Geen synchronisatie</span>}
      </div>;
    })}</div>
    {!valid && <p role="alert" className="text-sm text-red-700 dark:text-red-300">Kies voor iedere actieve dag een eindtijd na de begintijd.</p>}
    {save.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{save.error?.response?.data?.message || 'Opslaan is mislukt. Probeer opnieuw.'}</p>}
    <button type="submit" className="btn-primary" disabled={!valid || save.isPending}>{save.isPending ? 'Opslaan…' : 'Planning opslaan'}</button>
  </form>;
}

export default function TwelveSchedule() {
  const [saved, setSaved] = useState(false);
  const query = useQuery({ queryKey: ['twelve', 'schedule'], queryFn: async () => (await prmApi.getTwelve('schedule')).data });
  return <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-5">
    <div><h2 className="text-lg font-semibold">Synchronisatietijden</h2><p className="text-sm text-gray-600 dark:text-gray-300 mt-2">Kies wanneer deze club omzet verwacht. Rondo haalt de gegevens iedere twee uur vanaf de begintijd op, en eenmaal op de eindtijd. Alle tijden zijn Nederlandse tijd.</p><p className="text-sm text-gray-600 dark:text-gray-300 mt-2">Een eindtijd van 00.00 hoort bij de avond ervoor. Wijzigingen gelden vanaf het volgende hele uur. Zet alle dagen uit om automatisch ophalen te stoppen.</p></div>
    {query.isPending ? <p role="status">Planning laden…</p> : query.isError ? <div><p role="alert">De planning kon niet worden geladen.</p><button className="btn-secondary mt-3" onClick={() => query.refetch()}>Opnieuw proberen</button></div> : <ScheduleForm key={JSON.stringify(query.data)} schedule={query.data} onSaved={setSaved} />}
    {saved && <p role="status" className="text-sm">Synchronisatietijden opgeslagen.</p>}
  </div>;
}
