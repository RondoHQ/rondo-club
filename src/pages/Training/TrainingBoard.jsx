import { DndContext, PointerSensor, KeyboardSensor, useSensor, useSensors, useDraggable, useDroppable, rectIntersection } from '@dnd-kit/core';
import { GripVertical } from 'lucide-react';
import { TRAINING_DAYS, blockTitle, fieldPart, toMinutes, toTime } from '@/utils/training';

const STEP = 32;
const QUARTER_WIDTH = 80;

function TrainingBlock({ block, teams, start, conflicts, onEdit, disabled }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: block.block_id, data: { block }, disabled });
  const title = blockTitle(block, teams);
  return (
    <div ref={setNodeRef} style={{ position: 'absolute', left: `${block.offset * 25}%`, width: `${block.size * 25}%`, top: (toMinutes(block.start) - start) / 15 * STEP, height: block.duration / 15 * STEP, transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined, zIndex: isDragging ? 30 : 1 }} className="p-0.5">
      <div className={`h-full overflow-hidden rounded-lg border shadow-sm ${conflicts.has(block.block_id) ? 'border-red-500 bg-red-100 text-red-950 dark:bg-red-950 dark:text-red-100' : 'border-cyan-300 bg-cyan-50 text-cyan-950 dark:border-cyan-700 dark:bg-cyan-950 dark:text-cyan-100'} ${isDragging ? 'opacity-80 shadow-xl ring-2 ring-cyan-500' : ''}`}>
        {!disabled ? <button type="button" {...attributes} {...listeners} className="flex w-full touch-none cursor-grab items-center justify-center bg-black/5 py-1 active:cursor-grabbing" aria-label={`Versleep ${title}`} title="Verslepen; of spatie en pijltjestoetsen"><GripVertical className="h-4 w-4" aria-hidden="true" /></button> : null}
        <button type="button" disabled={disabled} onClick={() => onEdit(block)} className="block w-full p-1 text-left text-xs leading-snug disabled:opacity-100" aria-label={`Bewerk ${title}, ${block.start}`}><strong className="block break-words">{title}</strong><span className="block">{block.start}–{toTime(toMinutes(block.start) + block.duration)}</span><span className="block">{fieldPart(block)}</span></button>
      </div>
    </div>
  );
}

function PitchColumn({ pitch, day, blocks, teams, start, end, conflicts, onEdit, disabled }) {
  const { setNodeRef, isOver } = useDroppable({ id: `${day}:${pitch.id}`, data: { pitch_id: pitch.id, day }, disabled });
  return (
    <div className="shrink-0" style={{ width: QUARTER_WIDTH * 4 }}>
      <div className="border-b border-l border-gray-200 bg-gray-50 py-2 text-center font-semibold dark:border-gray-700 dark:bg-gray-800">{pitch.name}</div>
      <div className="grid grid-cols-4 text-center text-xs text-gray-500 dark:text-gray-400">{'ABCD'.split('').map((part) => <span key={part} className="border-l border-gray-200 py-1 dark:border-gray-700">{part}</span>)}</div>
      <div ref={setNodeRef} className={`relative border-l border-gray-200 dark:border-gray-700 ${isOver ? 'bg-cyan-50/60 dark:bg-cyan-950/30' : ''}`} style={{ height: (end - start) / 15 * STEP, backgroundImage: 'linear-gradient(to bottom, rgb(156 163 175 / .25) 1px, transparent 1px), linear-gradient(to right, rgb(156 163 175 / .25) 1px, transparent 1px)', backgroundSize: `100% ${STEP}px, 25% 100%` }}>
        {blocks.map((block) => <TrainingBlock key={block.block_id} block={block} teams={teams} start={start} conflicts={conflicts} onEdit={onEdit} disabled={disabled} />)}
      </div>
    </div>
  );
}

export default function TrainingBoard({ schedule, pitches, teams, selectedDay, conflicts, onChange, onEdit, disabled }) {
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor));
  const start = Math.floor(Math.min(17 * 60, ...schedule.blocks.map((block) => toMinutes(block.start))) / 60) * 60;
  const end = Math.min(1440, Math.ceil(Math.max(22 * 60, ...schedule.blocks.map((block) => toMinutes(block.start) + block.duration)) / 60) * 60);
  const days = selectedDay ? [selectedDay] : [1, 2, 3, 4, 5, 6, 7];
  const dragEnd = ({ active, over }) => {
    if (!over || !active.rect.current.translated) return;
    const block = active.data.current.block;
    const rect = active.rect.current.translated;
    const minutes = Math.max(0, Math.min(1440 - block.duration, start + Math.round((rect.top - over.rect.top) / STEP) * 15));
    const offset = Math.max(0, Math.min(4 - block.size, Math.round((rect.left - over.rect.left) / (QUARTER_WIDTH * block.size)) * block.size));
    onChange({ ...block, pitch_id: over.data.current.pitch_id, day: over.data.current.day, start: toTime(minutes), offset });
  };
  return (
    <DndContext sensors={sensors} collisionDetection={rectIntersection} onDragEnd={dragEnd} accessibility={{ screenReaderInstructions: { draggable: 'Druk op spatie om het blok te pakken. Gebruik de pijltjestoetsen en druk opnieuw op spatie om neer te zetten. Escape annuleert. Je kunt ook het blok openen om dag, tijd en veld te wijzigen.' } }}>
      <div className="space-y-6">{days.map((day) => <section key={day} className="card overflow-hidden" aria-label={`Trainingsschema ${TRAINING_DAYS[day - 1]}`}>
        <h2 className="border-b border-gray-200 px-4 py-3 font-semibold dark:border-gray-700">{TRAINING_DAYS[day - 1]}</h2>
        <div className="overflow-x-auto pb-2"><div className="flex w-max min-w-full pr-2"><div className="w-16 shrink-0"><div className="h-[66px]" />{Array.from({ length: (end - start) / 15 }, (_, index) => <div key={index} style={{ height: STEP }} className="pr-2 text-right text-xs text-gray-500 dark:text-gray-400">{toTime(start + index * 15)}</div>)}</div>
          {pitches.map((pitch) => <PitchColumn key={pitch.id} pitch={pitch} day={day} blocks={schedule.blocks.filter((block) => block.day === day && block.pitch_id === pitch.id)} teams={teams} start={start} end={end} conflicts={conflicts} onEdit={onEdit} disabled={disabled} />)}
        </div></div>
      </section>)}</div>
    </DndContext>
  );
}
