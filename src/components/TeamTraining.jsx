import { useActiveTraining } from '@/hooks/useTraining';
import { TRAINING_DAYS, fieldPart, toMinutes, toTime } from '@/utils/training';

export default function TeamTraining({ teamId, variant = 'default' }) {
  const { data, isLoading, error, refetch } = useActiveTraining();
  const blocks = (data?.schedule?.blocks || []).filter((block) => block.team_ids.includes(Number(teamId))).sort((a, b) => a.day - b.day || toMinutes(a.start) - toMinutes(b.start));
  return (
    <section className={variant === 'roster' ? 'space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800' : 'card p-5 space-y-3'} aria-label="Trainingstijden">
      <div className="flex flex-wrap items-baseline gap-2"><h2 className={variant === 'roster' ? 'font-semibold text-gray-900 dark:text-gray-100' : 'font-semibold text-brand-gradient'}>Trainingstijden</h2>{data?.schedule ? <span className="text-sm text-gray-500 dark:text-gray-400">{data.schedule.name} · {data.schedule.season}</span> : null}</div>
      {isLoading ? <p>Trainingstijden laden…</p> : error ? <p role="alert">Trainingstijden konden niet worden geladen. <button className="underline" onClick={() => refetch()}>Opnieuw proberen</button></p> : !data?.schedule ? <p className="text-sm text-gray-500 dark:text-gray-400">Er is nog geen actief trainingsschema.</p> : !blocks.length ? <p className="text-sm text-gray-500 dark:text-gray-400">Geen trainingen ingepland in dit schema.</p> : (
        <ul className="space-y-2">{blocks.map((block) => <li key={block.block_id} className="flex flex-wrap gap-x-4 gap-y-1 text-sm"><strong>{TRAINING_DAYS[block.day - 1]}</strong><span>{block.start}–{toTime(toMinutes(block.start) + block.duration)}</span><span>{data.pitches.find((pitch) => pitch.id === block.pitch_id)?.name} · {fieldPart(block)}</span>{block.label ? <span>{block.label}</span> : null}</li>)}</ul>
      )}
    </section>
  );
}
