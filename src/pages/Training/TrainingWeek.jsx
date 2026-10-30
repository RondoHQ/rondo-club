import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTrainingWeek, useTrainingMutation } from '@/hooks/useTraining';
import { TRAINING_DAYS, fieldPart, shiftTrainingDate, trainingDateLabel, toMinutes, toTime } from '@/utils/training';
import TrainingBoard from './TrainingBoard';
import TrainingDialog from './TrainingDialog';

const NO_CONFLICTS = new Set();

export default function TrainingWeek({ teamId, variant = 'default' }) {
  const [date, setDate] = useState('');
  const [day, setDay] = useState(0);
  const [openedId, setOpenedId] = useState(null);
  const [message, setMessage] = useState('');
  const [failure, setFailure] = useState('');
  const { data, isLoading, error, refetch } = useTrainingWeek(date);
  const mutation = useTrainingMutation();
  const teamView = Boolean(teamId);
  const blocks = (data?.schedule?.blocks || []).filter((block) => !teamView || block.team_ids.includes(Number(teamId))).sort((a, b) => a.day - b.day || toMinutes(a.start) - toMinutes(b.start));
  const opened = blocks.find((block) => block.block_id === openedId);
  const changeWeek = (next) => { setDate(next); setOpenedId(null); setMessage(''); setFailure(''); };
  const change = async (block, attendance) => {
    setMessage('');
    setFailure('');
    try {
      await mutation.mutateAsync({ path: 'attendance', method: 'put', data: { schedule_id: data.schedule.id, schedule_revision: data.schedule.revision, block_id: block.block_id, date: block.date, team_id: attendance.team_id, cancelled: !attendance.cancelled, revision: attendance.revision } });
      setMessage(`${attendance.name}: training op ${trainingDateLabel(block.date)} ${attendance.cancelled ? 'gaat weer door' : 'afgemeld'}.`);
    } catch (err) {
      setFailure(err.response?.data?.message || 'Afmelding niet opgeslagen. Probeer het opnieuw.');
      await refetch();
    }
  };
  const attendanceRows = (block) => block.attendance.filter((item) => !teamView || item.team_id === Number(teamId)).map((item) => (
    <div key={item.team_id} className="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 py-3 dark:border-gray-700">
      <p><strong>{item.name}</strong><span className="ml-2">{item.cancelled ? 'Afgemeld' : 'Gaat door'}</span></p>
      {item.can_change ? <button type="button" className="btn-secondary" disabled={mutation.isPending} onClick={() => change(block, item)}>{mutation.isPending ? 'Opslaan…' : item.cancelled ? 'Afmelding intrekken' : 'Training afmelden'}</button> : null}
    </div>
  ));
  const feedback = <>{failure ? <p role="alert" className="text-sm text-red-700 dark:text-red-300">{failure}</p> : null}{message ? <p role="status" className="text-sm text-green-800 dark:text-green-300">{message}</p> : null}</>;
  return (
    <section className={teamView ? `${variant === 'roster' ? 'rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' : 'card'} space-y-4 p-4` : 'space-y-5'} aria-label="Trainingen per datum">
      <div>
        {teamView ? <h2 className="font-semibold text-gray-900 dark:text-gray-100">Trainingen</h2> : <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Trainingen per week</h1>}
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{teamView ? 'Bekijk je trainingen per datum. Trainers en leiders kunnen hun team voor één training afmelden.' : 'Bekijk de veldbezetting per datum. Grijze blokken zijn volledig afgemeld; bij gedeelde blokken zie je welke teams nog trainen.'}</p>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <button type="button" className="btn-secondary" disabled={!data || mutation.isPending} onClick={() => changeWeek(shiftTrainingDate(data.week_start, -7))}>Vorige week</button>
        <label className="text-sm">Datum in week<input type="date" className="input mt-1" value={date || data?.week_start || ''} disabled={mutation.isPending} onChange={(event) => { if (event.target.value) changeWeek(event.target.value); }} /></label>
        <button type="button" className="btn-secondary" disabled={!data || mutation.isPending} onClick={() => changeWeek(shiftTrainingDate(data.week_start, 7))}>Volgende week</button>
        <button type="button" className="btn-tertiary" disabled={mutation.isPending} onClick={() => changeWeek('')}>Deze week</button>
      </div>
      {isLoading ? <p role="status">Trainingen laden…</p> : error ? <p role="alert">Het weekoverzicht kon niet worden geladen. <button className="underline" onClick={() => refetch()}>Opnieuw proberen</button></p> : !data?.schedule ? <p>Er is nog geen actief trainingsschema.</p> : <>
        <p className="text-sm font-medium">{trainingDateLabel(data.week_start)} – {trainingDateLabel(data.week_end)} {data.week_end.slice(0, 4)} · {data.schedule.name}</p>
        {!opened ? feedback : null}
        {!blocks.length ? <p>Geen trainingen gepland in deze week.</p> : teamView ? <ul className="divide-y divide-gray-200 dark:divide-gray-700">{blocks.map((block) => {
          const own = block.attendance.find((item) => item.team_id === Number(teamId));
          return <li key={block.block_id} className={`py-3 ${own?.cancelled ? 'rounded-lg bg-gray-100 px-3 text-gray-700 dark:bg-gray-700 dark:text-gray-100' : ''}`}>
            <p className="font-semibold">{TRAINING_DAYS[block.day - 1]} {trainingDateLabel(block.date)} · {block.start}–{toTime(toMinutes(block.start) + block.duration)}</p>
            <p className="mb-2 text-sm">{data.pitches.find((pitch) => pitch.id === block.pitch_id)?.name} · {fieldPart(block)}{block.label ? ` · ${block.label}` : ''}</p>
            {attendanceRows(block)}
          </li>;
        })}</ul> : <>
          <div className="flex flex-wrap gap-1" aria-label="Dag kiezen">{[{ value: 0, name: 'Hele week' }, ...TRAINING_DAYS.map((name, index) => ({ value: index + 1, name }))].map((item) => <button type="button" key={item.value} aria-pressed={day === item.value} className={day === item.value ? 'btn-primary' : 'btn-tertiary'} onClick={() => setDay(item.value)}>{item.name}</button>)}</div>
          <TrainingBoard schedule={{ ...data.schedule, blocks, week_start: data.week_start }} pitches={data.pitches} selectedDay={day} conflicts={NO_CONFLICTS} disabled onInspect={(block) => { setOpenedId(block.block_id); setMessage(''); setFailure(''); }} />
        </>}
      </>}
      {teamView ? <Link className="inline-block text-sm underline" to="/trainingsschema">Weekoverzicht van alle velden</Link> : null}
      {opened ? <TrainingDialog title={`${TRAINING_DAYS[opened.day - 1]} ${trainingDateLabel(opened.date)}`} onClose={() => setOpenedId(null)}>
        <p className="mb-4 text-sm">{opened.start}–{toTime(toMinutes(opened.start) + opened.duration)} · {data.pitches.find((pitch) => pitch.id === opened.pitch_id)?.name} · {fieldPart(opened)}</p>
        {opened.label ? <p className="mb-3 font-semibold">{opened.label}</p> : null}
        {attendanceRows(opened)}
        {!opened.attendance.length ? <p>Dit blok heeft geen gekoppeld team.</p> : null}
        {feedback}
      </TrainingDialog> : null}
    </section>
  );
}
