import { useState, useEffect } from 'react';
import { X, GripVertical, RotateCcw } from 'lucide-react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { DEFAULT_DASHBOARD_CARDS } from '@/hooks/useDashboard';

// Card definitions with labels and descriptions
const CARD_DEFINITIONS = {
  'stats': { label: 'Statistieken', description: 'Aantallen van leden, teams en evenementen' },
  'reminders': { label: 'Komende herinneringen', description: 'Aankomende belangrijke datums' },
  'todos': { label: 'Open taken', description: 'Taken om af te ronden' },
  'awaiting': { label: 'Openstaand', description: 'Wachten op reacties' },
  'meetings': { label: 'Afspraken vandaag', description: 'Agenda-items voor vandaag' },
  'recent-contacted': { label: 'Recent gecontacteerd', description: 'Contacten met recente activiteit' },
  'recent-edited': { label: 'Recent bewerkt', description: 'Recent gewijzigde leden' },
};

function SortableCard({ id, isVisible, onToggleVisibility }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  const card = CARD_DEFINITIONS[id];

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-center gap-3 p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg ${
        isDragging ? 'shadow-lg opacity-90' : ''
      } ${!isVisible ? 'opacity-50' : ''}`}
    >
      <button
        {...attributes}
        {...listeners}
        className="touch-none p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-grab active:cursor-grabbing"
        aria-label="Slepen om te sorteren"
      >
        <GripVertical className="w-5 h-5" />
      </button>

      <label className="flex-1 flex items-center gap-3 cursor-pointer">
        <input
          type="checkbox"
          checked={isVisible}
          onChange={() => onToggleVisibility(id)}
          className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500 dark:bg-gray-700"
        />
        <div className="flex-1 min-w-0">
          <div className="text-sm font-medium text-gray-900 dark:text-gray-50">
            {card.label}
          </div>
          <div className="text-xs text-gray-500 dark:text-gray-400">
            {card.description}
          </div>
        </div>
      </label>
    </div>
  );
}

export default function DashboardCustomizeModal({
  isOpen,
  onClose,
  settings,
  onSave,
  isSaving,
}) {
  const [cardOrder, setCardOrder] = useState(DEFAULT_DASHBOARD_CARDS);
  const [visibleCards, setVisibleCards] = useState(new Set(DEFAULT_DASHBOARD_CARDS));

  // Initialize state from settings when modal opens
  useEffect(() => {
    if (isOpen && settings) {
      setCardOrder(settings.card_order || DEFAULT_DASHBOARD_CARDS);
      setVisibleCards(new Set(settings.visible_cards || DEFAULT_DASHBOARD_CARDS));
    }
  }, [isOpen, settings]);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(TouchSensor, {
      activationConstraint: {
        delay: 200,
        tolerance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = (event) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      setCardOrder((items) => {
        const oldIndex = items.indexOf(active.id);
        const newIndex = items.indexOf(over.id);
        return arrayMove(items, oldIndex, newIndex);
      });
    }
  };

  const handleToggleVisibility = (cardId) => {
    setVisibleCards((prev) => {
      const next = new Set(prev);
      if (next.has(cardId)) {
        next.delete(cardId);
      } else {
        next.add(cardId);
      }
      return next;
    });
  };

  const handleReset = () => {
    setCardOrder(DEFAULT_DASHBOARD_CARDS);
    setVisibleCards(new Set(DEFAULT_DASHBOARD_CARDS));
  };

  const handleSave = () => {
    onSave({
      card_order: cardOrder,
      visible_cards: cardOrder.filter((id) => visibleCards.has(id)),
    });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">
                Dashboard aanpassen
              </h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Toon, verberg en sorteer kaarten
              </p>
            </div>
            <button
              onClick={onClose}
              className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Content */}
          <div className="px-6 py-4 max-h-[60vh] overflow-y-auto">
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext items={cardOrder} strategy={verticalListSortingStrategy}>
                <div className="space-y-2">
                  {cardOrder.map((cardId) => (
                    <SortableCard
                      key={cardId}
                      id={cardId}
                      isVisible={visibleCards.has(cardId)}
                      onToggleVisibility={handleToggleVisibility}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          </div>

          {/* Footer */}
          <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <button
              type="button"
              onClick={handleReset}
              className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
            >
              <RotateCcw className="w-4 h-4" />
              Herstel standaard
            </button>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={onClose}
                className="btn-secondary"
              >
                Annuleer
              </button>
              <button
                type="button"
                onClick={handleSave}
                disabled={isSaving}
                className="btn-primary"
              >
                {isSaving ? 'Bezig met opslaan...' : 'Opslaan'}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
