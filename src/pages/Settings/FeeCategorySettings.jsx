import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { GripVertical, Edit2, Trash2, Plus, Loader2, AlertCircle } from 'lucide-react';
import { prmApi } from '@/api/client';
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

// Utility function to slugify strings
function slugify(text) {
  return text
    .toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/--+/g, '-')
    .trim();
}

// SortableCategoryCard component for drag-and-drop category items
function SortableCategoryCard({ slug, category, onEdit, onDelete }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: slug });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`card p-4 flex items-start gap-4 ${isDragging ? 'shadow-lg opacity-90' : ''}`}
    >
      {/* Drag handle */}
      <button
        {...attributes}
        {...listeners}
        className="mt-1 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-grab active:cursor-grabbing touch-none"
        aria-label="Sleep om te herordenen"
      >
        <GripVertical className="w-5 h-5" />
      </button>

      {/* Category info */}
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-4">
          <div className="flex-1">
            <h4 className="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
              {category.label}
              {category.is_youth && (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                  Familiekorting
                </span>
              )}
            </h4>
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mt-1">
              Bedrag: <span className="text-accent-600 dark:text-accent-400">&euro; {category.amount}</span>
            </p>
            {category.age_classes && category.age_classes.length > 0 ? (
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Leeftijdsklassen: {category.age_classes.join(', ')}
              </p>
            ) : (
              <p className="text-sm italic text-gray-500 dark:text-gray-500 mt-1">
                Catch-all voor niet-toegewezen klassen
              </p>
            )}
          </div>

          {/* Actions */}
          <div className="flex items-center gap-2">
            <button
              onClick={() => onEdit(slug, category)}
              className="p-2 text-gray-600 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/30 rounded"
              title="Bewerken"
            >
              <Edit2 className="w-4 h-4" />
            </button>
            <button
              onClick={() => onDelete(slug, category)}
              className="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
              title="Verwijderen"
            >
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// EditCategoryForm component for inline editing
function EditCategoryForm({ slug, category, onSave, onCancel, isSaving, isNew = false, availableAgeGroups = [] }) {
  const [formData, setFormData] = useState({
    label: category?.label || '',
    amount: category?.amount ?? 0,
    age_classes: category?.age_classes || [],
    is_youth: category?.is_youth ?? false,
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const effectiveSlug = isNew ? slugify(formData.label) : slug;
    onSave(effectiveSlug, {
      label: formData.label,
      amount: parseFloat(formData.amount) || 0,
      age_classes: formData.age_classes,
      is_youth: formData.is_youth,
    });
  };

  const toggleAgeClass = (value) => {
    setFormData(prev => ({
      ...prev,
      age_classes: prev.age_classes.includes(value)
        ? prev.age_classes.filter(v => v !== value)
        : [...prev.age_classes, value],
    }));
  };

  return (
    <form onSubmit={handleSubmit} className="card p-4 space-y-4 border-2 border-accent-200 dark:border-accent-800">
      {/* Label field */}
      <div>
        <label htmlFor="label" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Label
        </label>
        <input
          type="text"
          id="label"
          value={formData.label}
          onChange={(e) => setFormData(prev => ({ ...prev, label: e.target.value }))}
          required
          className="w-full px-3 py-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-accent-500 focus:ring-accent-500"
        />
      </div>

      {/* Amount field */}
      <div>
        <label htmlFor="amount" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Bedrag
        </label>
        <div className="relative">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            &euro;
          </span>
          <input
            type="number"
            id="amount"
            min="0"
            step="1"
            value={formData.amount}
            onChange={(e) => setFormData(prev => ({ ...prev, amount: e.target.value }))}
            required
            className="w-full pl-8 pr-3 py-2 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-accent-500 focus:ring-accent-500"
          />
        </div>
      </div>

      {/* Age classes multi-select from database */}
      <div>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Leeftijdsgroepen
        </label>
        {availableAgeGroups.length > 0 ? (
          <div className="border border-gray-300 dark:border-gray-600 rounded-md p-3 max-h-48 overflow-y-auto space-y-1.5 bg-white dark:bg-gray-700">
            {availableAgeGroups.map(({ value, count }) => (
              <label key={value} className="flex items-center gap-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 rounded px-1 py-0.5">
                <input
                  type="checkbox"
                  checked={formData.age_classes.includes(value)}
                  onChange={() => toggleAgeClass(value)}
                  className="rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                />
                <span className="text-sm text-gray-700 dark:text-gray-300">{value}</span>
                <span className="text-xs text-gray-400 dark:text-gray-500">({count})</span>
              </label>
            ))}
          </div>
        ) : (
          <p className="text-sm text-gray-500 dark:text-gray-400 italic">Laden...</p>
        )}
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
          Selecteer geen groepen voor catch-all (alle niet-toegewezen groepen)
        </p>
      </div>

      {/* Is youth checkbox */}
      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          id="is_youth"
          checked={formData.is_youth}
          onChange={(e) => setFormData(prev => ({ ...prev, is_youth: e.target.checked }))}
          className="rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
        />
        <label htmlFor="is_youth" className="text-sm font-medium text-gray-700 dark:text-gray-300">
          Familiekorting mogelijk?
        </label>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-3 pt-2">
        <button
          type="submit"
          disabled={isSaving || !formData.label}
          className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-accent-600 hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-500 disabled:opacity-50"
        >
          {isSaving ? (
            <>
              <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              Opslaan...
            </>
          ) : (
            'Opslaan'
          )}
        </button>
        <button
          type="button"
          onClick={onCancel}
          disabled={isSaving}
          className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md shadow-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-500 disabled:opacity-50"
        >
          Annuleren
        </button>
      </div>
    </form>
  );
}

// AgeCoverageSummary component
function AgeCoverageSummary({ categories, warnings }) {
  const sortedCategories = Object.entries(categories)
    .sort(([, a], [, b]) => (a.sort_order || 0) - (b.sort_order || 0));

  const ageClassWarnings = warnings.filter(w => w.field && w.field.includes('age_classes'));

  return (
    <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
      <h4 className="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-3">
        Leeftijdsklasse dekking
      </h4>
      <div className="space-y-2">
        {sortedCategories.map(([slug, category]) => (
          <div key={slug} className="text-sm">
            <span className="font-medium text-gray-900 dark:text-gray-100">{category.label}</span>
            <span className="text-gray-500 dark:text-gray-400">: </span>
            {category.age_classes && category.age_classes.length > 0 ? (
              <span className="text-gray-700 dark:text-gray-300">
                {category.age_classes.join(', ')}
              </span>
            ) : (
              <span className="italic text-gray-500 dark:text-gray-500">
                (Catch-all voor niet-toegewezen klassen)
              </span>
            )}
          </div>
        ))}
      </div>

      {ageClassWarnings.length > 0 && (
        <>
          <hr className="my-3 border-amber-200 dark:border-amber-800" />
          <div className="text-sm">
            <p className="font-medium text-amber-800 dark:text-amber-200 mb-2">
              Overlappende toewijzingen:
            </p>
            <ul className="list-disc list-inside space-y-1 text-amber-700 dark:text-amber-300">
              {ageClassWarnings.map((warning, idx) => (
                <li key={idx}>{warning.message}</li>
              ))}
            </ul>
          </div>
        </>
      )}
    </div>
  );
}

// Main FeeCategorySettings component
export default function FeeCategorySettings() {
  const queryClient = useQueryClient();
  const [selectedSeason, setSelectedSeason] = useState('current');
  const [editingSlug, setEditingSlug] = useState(null);
  const [isAddingNew, setIsAddingNew] = useState(false);
  const [saveErrors, setSaveErrors] = useState([]);
  const [saveWarnings, setSaveWarnings] = useState([]);
  const [successMessage, setSuccessMessage] = useState('');

  // Fetch membership fee settings
  const { data, isLoading } = useQuery({
    queryKey: ['membership-fee-settings'],
    queryFn: async () => {
      const response = await prmApi.getMembershipFeeSettings();
      return response.data;
    },
  });

  // Fetch available age groups from the database (same as Leeftijdsgroep filter on /people)
  const { data: filterOptions } = useQuery({
    queryKey: ['people', 'filter-options'],
    queryFn: async () => {
      const response = await prmApi.getFilterOptions();
      return response.data;
    },
    staleTime: 5 * 60 * 1000,
  });
  const availableAgeGroups = filterOptions?.age_groups || [];

  // Save mutation with optimistic updates
  const saveMutation = useMutation({
    mutationFn: async ({ categories, season }) => {
      const response = await prmApi.updateMembershipFeeSettings({ categories }, season);
      return response.data;
    },
    onMutate: async ({ categories, season }) => {
      // Cancel outgoing queries
      await queryClient.cancelQueries({ queryKey: ['membership-fee-settings'] });

      // Snapshot previous value
      const previousData = queryClient.getQueryData(['membership-fee-settings']);

      // Optimistically update
      queryClient.setQueryData(['membership-fee-settings'], (old) => {
        const seasonKey = season === 'current' ? 'current_season' : 'next_season';
        return {
          ...old,
          [seasonKey]: {
            ...old[seasonKey],
            categories,
          },
        };
      });

      return { previousData };
    },
    onSuccess: (responseData) => {
      // Update cache with server response
      queryClient.setQueryData(['membership-fee-settings'], responseData);

      // Handle warnings
      if (responseData.warnings && responseData.warnings.length > 0) {
        setSaveWarnings(responseData.warnings);
      } else {
        setSaveWarnings([]);
      }

      // Clear errors
      setSaveErrors([]);

      // Show success message
      setSuccessMessage('Instellingen opgeslagen');
      setTimeout(() => setSuccessMessage(''), 3000);

      // Close edit forms
      setEditingSlug(null);
      setIsAddingNew(false);
    },
    onError: (error, variables, context) => {
      // Rollback on error
      if (context?.previousData) {
        queryClient.setQueryData(['membership-fee-settings'], context.previousData);
      }

      // Parse WP_Error structure (nested in data.data)
      const errorData = error.response?.data?.data;
      if (errorData?.errors) {
        setSaveErrors(errorData.errors);
      } else {
        setSaveErrors([{ field: 'general', message: error.message || 'Er is een fout opgetreden' }]);
      }

      // Clear warnings
      setSaveWarnings([]);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['membership-fee-settings'] });
    },
  });

  // Drag-and-drop sensors
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    }),
    useSensor(TouchSensor, {
      activationConstraint: { delay: 200, tolerance: 8 },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  // Get active season data
  const activeSeason = selectedSeason === 'current' ? data?.current_season : data?.next_season;
  const activeSeasonKey = activeSeason?.key;
  const categories = activeSeason?.categories || {};

  // Sort categories by sort_order
  const sortedCategorySlugs = Object.entries(categories)
    .sort(([, a], [, b]) => (a.sort_order || 0) - (b.sort_order || 0))
    .map(([slug]) => slug);

  // Handle drag end
  const handleDragEnd = (event) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const oldIndex = sortedCategorySlugs.indexOf(active.id);
      const newIndex = sortedCategorySlugs.indexOf(over.id);
      const newOrder = arrayMove(sortedCategorySlugs, oldIndex, newIndex);

      // Rebuild categories with new sort_order
      const reorderedCategories = {};
      newOrder.forEach((slug, index) => {
        reorderedCategories[slug] = {
          ...categories[slug],
          sort_order: index,
        };
      });

      // Save immediately
      saveMutation.mutate({ categories: reorderedCategories, season: activeSeasonKey });
    }
  };

  // Handle edit
  const handleEdit = (slug) => {
    clearMessages();
    setEditingSlug(slug);
    setIsAddingNew(false);
  };

  // Handle delete
  const handleDelete = (slug, category) => {
    if (window.confirm(`Categorie '${category.label}' verwijderen?`)) {
      clearMessages();
      const newCategories = { ...categories };
      delete newCategories[slug];

      // Recalculate sort_order
      const reorderedCategories = {};
      Object.entries(newCategories)
        .sort(([, a], [, b]) => (a.sort_order || 0) - (b.sort_order || 0))
        .forEach(([s, c], index) => {
          reorderedCategories[s] = { ...c, sort_order: index };
        });

      saveMutation.mutate({ categories: reorderedCategories, season: activeSeasonKey });
    }
  };

  // Handle save (edit or new)
  const handleSave = (slug, categoryData) => {
    clearMessages();

    const newCategories = { ...categories };

    if (isAddingNew) {
      // Add new category with max sort_order + 1
      const maxSortOrder = Math.max(-1, ...Object.values(categories).map(c => c.sort_order || 0));
      newCategories[slug] = {
        ...categoryData,
        sort_order: maxSortOrder + 1,
      };
    } else {
      // Update existing category (preserve sort_order)
      newCategories[slug] = {
        ...categoryData,
        sort_order: categories[slug]?.sort_order || 0,
      };
    }

    saveMutation.mutate({ categories: newCategories, season: activeSeasonKey });
  };

  // Handle cancel
  const handleCancel = () => {
    setEditingSlug(null);
    setIsAddingNew(false);
    clearMessages();
  };

  // Handle add new
  const handleAddNew = () => {
    clearMessages();
    setIsAddingNew(true);
    setEditingSlug(null);
  };

  // Handle season change
  const handleSeasonChange = (season) => {
    setSelectedSeason(season);
    setEditingSlug(null);
    setIsAddingNew(false);
    clearMessages();
  };

  // Clear messages
  const clearMessages = () => {
    setSaveErrors([]);
    setSaveWarnings([]);
    setSuccessMessage('');
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="w-8 h-8 animate-spin text-accent-500" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
          Contributiecategorieën
        </h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Configureer categorieën met bedragen en leeftijdsklassen per seizoen.
        </p>
      </div>

      {/* Season selector */}
      <div className="flex gap-2">
        <button
          onClick={() => handleSeasonChange('current')}
          className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
            selectedSeason === 'current'
              ? 'bg-accent-600 text-white'
              : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
          }`}
        >
          Huidig seizoen ({data?.current_season?.key})
        </button>
        <button
          onClick={() => handleSeasonChange('next')}
          className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
            selectedSeason === 'next'
              ? 'bg-accent-600 text-white'
              : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
          }`}
        >
          Volgend seizoen ({data?.next_season?.key})
        </button>
      </div>

      {/* Error display */}
      {saveErrors.length > 0 && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <div className="flex items-start gap-2">
            <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <h4 className="font-medium text-red-900 dark:text-red-100 mb-2">Kan niet opslaan:</h4>
              <ul className="list-disc list-inside space-y-1 text-sm text-red-800 dark:text-red-200">
                {saveErrors.map((error, idx) => (
                  <li key={idx}>
                    {error.field && <span className="font-mono text-xs">{error.field}:</span>} {error.message}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* Category list */}
      {sortedCategorySlugs.length === 0 && !isAddingNew ? (
        <div className="card p-8 text-center">
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Nog geen categorieën gedefinieerd voor dit seizoen.
          </p>
        </div>
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragEnd={handleDragEnd}
        >
          <SortableContext items={sortedCategorySlugs} strategy={verticalListSortingStrategy}>
            <div className="space-y-3">
              {sortedCategorySlugs.map((slug) => {
                if (editingSlug === slug) {
                  return (
                    <EditCategoryForm
                      key={slug}
                      slug={slug}
                      category={categories[slug]}
                      onSave={handleSave}
                      onCancel={handleCancel}
                      isSaving={saveMutation.isPending}
                      availableAgeGroups={availableAgeGroups}
                    />
                  );
                }
                return (
                  <SortableCategoryCard
                    key={slug}
                    slug={slug}
                    category={categories[slug]}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                  />
                );
              })}
            </div>
          </SortableContext>
        </DndContext>
      )}

      {/* Add new category */}
      {isAddingNew ? (
        <EditCategoryForm
          slug=""
          category={null}
          onSave={handleSave}
          onCancel={handleCancel}
          isSaving={saveMutation.isPending}
          isNew={true}
          availableAgeGroups={availableAgeGroups}
        />
      ) : (
        <button
          onClick={handleAddNew}
          disabled={saveMutation.isPending}
          className="w-full card p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-accent-400 dark:hover:border-accent-600 hover:bg-accent-50 dark:hover:bg-accent-900/10 transition-colors flex items-center justify-center gap-2 text-gray-600 dark:text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 disabled:opacity-50"
        >
          <Plus className="w-5 h-5" />
          Nieuwe categorie
        </button>
      )}

      {/* Warning display */}
      {saveWarnings.length > 0 && (
        <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
          <div className="flex items-start gap-2">
            <AlertCircle className="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <h4 className="font-medium text-amber-900 dark:text-amber-100 mb-2">Let op:</h4>
              <ul className="list-disc list-inside space-y-1 text-sm text-amber-800 dark:text-amber-200">
                {saveWarnings.map((warning, idx) => (
                  <li key={idx}>
                    {warning.field && <span className="font-mono text-xs">{warning.field}:</span>} {warning.message}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* Success message */}
      {successMessage && (
        <div className="text-sm text-green-600 dark:text-green-400 font-medium">
          {successMessage}
        </div>
      )}

      {/* Age coverage summary */}
      {Object.keys(categories).length > 0 && (
        <AgeCoverageSummary categories={categories} warnings={saveWarnings} />
      )}
    </div>
  );
}
