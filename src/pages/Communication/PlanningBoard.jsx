import { useState } from 'react';
import { DndContext, KeyboardSensor, MouseSensor, closestCenter, pointerWithin, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Check, FileText, GripVertical, Repeat2 } from 'lucide-react';
import { isPlanningOverdue, openPlanningStatuses, planningColumns, sortPlanningItems } from './planningUtils';

function cardDate(date) {
  if (!date) return 'Nog niet gepland';
  return new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Europe/Amsterdam' }).format(new Date(`${date}T12:00:00Z`));
}

export function PlanningCard({ item, today, onOpen, moving, dragHandle }) {
  const completed = item.channels.filter((channel) => channel.actual_date).length;
  const overdue = isPlanningOverdue(item, today);
  return (
    <article
      aria-label={item.title}
      aria-busy={moving}
      className={`min-w-0 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800 ${moving ? 'opacity-60' : ''}`}
    >
      {dragHandle}
      {overdue && <span className="mb-2 inline-block rounded bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-300">Te laat</span>}
      <button type="button" onClick={() => onOpen(item)} className="block min-h-9 w-full break-words pr-5 text-left text-sm font-semibold text-gray-900 hover:text-bright-cobalt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bright-cobalt dark:text-gray-100 dark:hover:text-electric-cyan">{item.title}</button>
      <p className="mt-2 text-xs text-gray-600 dark:text-gray-300">{item.status === 'sent' ? 'Afgerond · ' : ''}{cardDate(item.status === 'sent' ? item.actual_date : item.planned_date)}</p>
      <p className="mt-1 break-words text-xs text-gray-600 dark:text-gray-300">{item.assignee_name || 'Niet toegewezen'}</p>
      {['skipped', 'cancelled'].includes(item.status) && <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">{item.status === 'skipped' ? 'Overgeslagen' : 'Geannuleerd'}</p>}
      <ul aria-label="Kanaalvoortgang" className="mt-3 flex flex-wrap gap-1">
        {item.channels.map((channel) => (
          <li key={channel.channel_id} className={`inline-flex max-w-full items-center gap-1 rounded border px-1.5 py-0.5 text-xs ${channel.actual_date ? 'border-emerald-200 text-emerald-800 dark:border-emerald-700 dark:text-emerald-300' : 'border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-300'}`}>
            {channel.actual_date && <Check aria-hidden="true" className="h-3 w-3 shrink-0" />}
            <span className="break-words">{channel.label}</span><span className="sr-only">{channel.actual_date ? ': afgerond' : ': nog te doen'}</span>
          </li>
        ))}
      </ul>
      <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-600 dark:text-gray-300">
        <button type="button" onClick={() => onOpen(item)} className="min-h-8 text-left underline-offset-2 hover:underline" aria-label={`Kanalen beheren voor ${item.title}: ${completed} van ${item.channels.length} afgerond`}>{completed}/{item.channels.length} kanalen afgerond</button>
        <div className="flex items-center gap-2">
          {Boolean(item.series_id) && <span title={item.recurrence === 'yearly' ? 'Jaarlijks' : 'Maandelijks'}><Repeat2 aria-hidden="true" className="h-3.5 w-3.5" /><span className="sr-only">{item.recurrence === 'yearly' ? 'Jaarlijks' : 'Maandelijks'}</span></span>}
          {item.google_docs_url && <a href={item.google_docs_url} target="_blank" rel="noreferrer" aria-label={`Open Google-document voor ${item.title}`} className="inline-flex min-h-8 min-w-8 items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-gray-700"><FileText aria-hidden="true" className="h-4 w-4" /></a>}
        </div>
      </div>
    </article>
  );
}

function keyboardColumnCoordinates(event, { currentCoordinates, context }) {
  if (!['ArrowLeft', 'ArrowRight'].includes(event.code) || !context.collisionRect) return;
  event.preventDefault();
  const current = context.over?.id || context.active?.data.current?.item.status;
  const index = openPlanningStatuses.indexOf(current);
  const target = openPlanningStatuses[index + (event.code === 'ArrowRight' ? 1 : -1)];
  const rect = context.droppableRects.get(target);
  if (!rect) return;
  return {
    x: currentCoordinates.x + rect.left - context.collisionRect.left,
    y: currentCoordinates.y + rect.top + 56 - context.collisionRect.top,
  };
}

function DraggablePlanningCard({ item, today, onOpen, movingId }) {
  const disabled = !openPlanningStatuses.includes(item.status) || Boolean(movingId);
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, isDragging } = useDraggable({ id: item.id, data: { item }, disabled });
  return <div ref={setNodeRef} className="relative" style={transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)`, zIndex: isDragging ? 30 : undefined } : undefined}>
    <PlanningCard item={item} today={today} onOpen={onOpen} moving={movingId === item.id} dragHandle={openPlanningStatuses.includes(item.status) ? <button ref={setActivatorNodeRef} type="button" {...attributes} {...listeners} aria-label={`Verplaats ${item.title}`} disabled={disabled} className="absolute right-1 top-1 hidden h-8 w-8 cursor-grab items-center justify-center rounded text-gray-400 hover:bg-gray-100 active:cursor-grabbing md:flex dark:hover:bg-gray-700"><GripVertical aria-hidden="true" className="h-4 w-4" /></button> : null} />
  </div>;
}

function PlanningColumn({ column, mobileStatus, today, onOpen, movingId }) {
  const { setNodeRef, isOver } = useDroppable({ id: column.id, disabled: column.id === 'sent' || Boolean(movingId) });
  return <section
    ref={setNodeRef}
    aria-labelledby={`planning-${column.id}`}
    className={`min-w-0 rounded-md ${mobileStatus === column.id ? '' : 'hidden md:block'} ${isOver ? 'bg-cyan-50 ring-2 ring-bright-cobalt dark:bg-cyan-950 dark:ring-electric-cyan' : ''}`}
  >
    <div className="mb-3 flex min-h-12 items-center gap-2 border-b border-gray-200 px-1 py-2 dark:border-gray-700">
      <span aria-hidden="true" className={`h-2 w-2 shrink-0 rounded-full ${column.dot}`} />
      <h2 id={`planning-${column.id}`} className="min-w-0 flex-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{column.label}</h2>
      <span className="text-xs tabular-nums text-gray-600 dark:text-gray-300">{column.items.length}</span>
    </div>
    <div className="min-h-28 space-y-3 pb-3">
      {column.items.map((item) => <DraggablePlanningCard key={item.id} item={item} today={today} onOpen={onOpen} movingId={movingId} />)}
      {!column.items.length && <p className="px-1 py-3 text-sm text-gray-500 dark:text-gray-400">Geen berichten</p>}
    </div>
  </section>;
}

export default function PlanningBoard({ items, today, onOpen, onMove, movingId }) {
  const [mobileStatus, setMobileStatus] = useState('concept');
  const sensors = useSensors(useSensor(MouseSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor, { coordinateGetter: keyboardColumnCoordinates }));
  const columns = planningColumns.map((column) => ({ ...column, items: sortPlanningItems(items.filter((item) => item.status === column.id), column.id === 'sent') }));
  const statusLabel = (id) => planningColumns.find((column) => column.id === id)?.label;

  return (
    <div>
      <div className="mb-3 grid grid-cols-2 gap-2 md:hidden" aria-label="Status kiezen">
        {columns.map((column) => <button key={column.id} type="button" aria-pressed={mobileStatus === column.id} onClick={() => setMobileStatus(column.id)} className={`min-h-11 rounded-md border px-2 py-2 text-sm ${mobileStatus === column.id ? 'border-bright-cobalt bg-cyan-50 text-bright-cobalt dark:border-electric-cyan dark:bg-cyan-950 dark:text-electric-cyan' : 'border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200'}`}>{column.label} · {column.items.length}</button>)}
      </div>
      <DndContext
        sensors={sensors}
        collisionDetection={(args) => args.pointerCoordinates ? pointerWithin(args) : closestCenter(args)}
        onDragEnd={({ active, over }) => {
          const item = items.find((entry) => entry.id === active.id);
          if (item && over) onMove(item, over.id);
        }}
        accessibility={{
          screenReaderInstructions: { draggable: 'Druk op spatie om het bericht te pakken. Gebruik links en rechts om een status te kiezen en spatie om neer te zetten. Escape annuleert. Je kunt de status ook in het bericht wijzigen.' },
          announcements: {
            onDragStart: ({ active }) => `${active.data.current.item.title} opgepakt.`,
            onDragOver: ({ over }) => over ? `Boven ${statusLabel(over.id)}.` : 'Geen beschikbare status.',
            onDragEnd: ({ over }) => over ? `Losgelaten bij ${statusLabel(over.id)}.` : 'Verplaatsing geannuleerd.',
            onDragCancel: () => 'Verplaatsing geannuleerd.',
          },
        }}
      >
        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-4" aria-label="Communicatieplanning per status">
          {columns.map((column) => <PlanningColumn key={column.id} column={column} mobileStatus={mobileStatus} today={today} onOpen={onOpen} movingId={movingId} />)}
        </div>
      </DndContext>
    </div>
  );
}
