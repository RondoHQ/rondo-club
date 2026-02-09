import { useEffect, useMemo } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

function EntitySelector({ value, onChange, entities, isLoading }) {
  // value is now "{type}:{id}" format, e.g., "team:123" or "commissie:456"
  return (
    <select
      value={value || ''}
      onChange={(e) => onChange(e.target.value || null)}
      className="input"
      disabled={isLoading}
    >
      <option value="">Selecteer een organisatie...</option>
      {entities.map(entity => (
        <option key={`${entity.type}:${entity.id}`} value={`${entity.type}:${entity.id}`}>
          {entity.title?.rendered || entity.title}
        </option>
      ))}
    </select>
  );
}

export default function WorkHistoryEditModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  workHistoryItem = null
}) {
  const isEditing = !!workHistoryItem;
  const isOnline = useOnlineStatus();

  // Fetch teams for the selector
  const { data: teamsData = [], isLoading: isTeamsLoading } = useQuery({
    queryKey: ['teams', 'all'],
    queryFn: async () => {
      const response = await wpApi.getTeams({ per_page: 100 });
      return response.data;
    },
  });

  // Fetch commissies for the selector
  const { data: commissiesData = [], isLoading: isCommissiesLoading } = useQuery({
    queryKey: ['commissies', 'all'],
    queryFn: async () => {
      const response = await wpApi.getCommissies({ per_page: 100 });
      return response.data;
    },
  });

  // Combine and sort entities alphabetically by title
  const entities = useMemo(() => {
    const teams = teamsData.map(t => ({ ...t, type: 'team' }));
    const commissies = commissiesData.map(c => ({ ...c, type: 'commissie' }));
    return [...teams, ...commissies].sort((a, b) => {
      const titleA = (a.title?.rendered || a.title || '').toLowerCase();
      const titleB = (b.title?.rendered || b.title || '').toLowerCase();
      return titleA.localeCompare(titleB);
    });
  }, [teamsData, commissiesData]);

  const isEntitiesLoading = isTeamsLoading || isCommissiesLoading;

  const { register, handleSubmit, reset, watch, control, formState: { errors } } = useForm({
    defaultValues: {
      entity: null, // format: "type:id" e.g., "team:123" or "commissie:456"
      job_title: '',
      description: '',
      start_date: '',
      end_date: '',
      is_current: false,
    },
  });

  const isCurrent = watch('is_current');

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      if (workHistoryItem) {
        // Convert old format (team ID only) to new format (type:id)
        let entityValue = null;
        if (workHistoryItem.team) {
          const entityType = workHistoryItem.entity_type || 'team'; // default to team for backward compatibility
          entityValue = `${entityType}:${workHistoryItem.team}`;
        }
        reset({
          entity: entityValue,
          job_title: workHistoryItem.job_title || '',
          description: workHistoryItem.description || '',
          start_date: workHistoryItem.start_date || '',
          end_date: workHistoryItem.end_date || '',
          is_current: workHistoryItem.is_current || false,
        });
      } else {
        reset({
          entity: null,
          job_title: '',
          description: '',
          start_date: '',
          end_date: '',
          is_current: false,
        });
      }
    }
  }, [isOpen, workHistoryItem, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    // Parse entity value ("type:id" format) back to team ID and entity_type
    let teamId = null;
    let entityType = null;
    if (data.entity) {
      const [type, id] = data.entity.split(':');
      entityType = type;
      teamId = parseInt(id, 10);
    }

    onSubmit({
      team: teamId,
      entity_type: entityType,
      job_title: data.job_title || '',
      description: data.description || '',
      start_date: data.start_date || '',
      end_date: data.end_date || '',
      is_current: data.is_current || false,
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Functie bewerken' : 'Functie toevoegen'}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Entity (Team or Commissie) */}
            <div>
              <label className="label">Organisatie</label>
              <Controller
                name="entity"
                control={control}
                render={({ field }) => (
                  <EntitySelector
                    value={field.value}
                    onChange={field.onChange}
                    entities={entities}
                    isLoading={isEntitiesLoading || isLoading}
                  />
                )}
              />
            </div>

            {/* Job Title */}
            <div>
              <label className="label">Functie</label>
              <input
                {...register('job_title')}
                className="input"
                placeholder="bijv. Software Engineer"
                disabled={isLoading}
              />
            </div>

            {/* Description */}
            <div>
              <label className="label">Beschrijving</label>
              <textarea
                {...register('description')}
                className="input"
                rows={3}
                placeholder="Functiebeschrijving, verantwoordelijkheden, prestaties..."
                disabled={isLoading}
              />
            </div>

            {/* Dates */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Startdatum</label>
                <input
                  type="date"
                  {...register('start_date')}
                  className="input"
                  disabled={isLoading}
                />
              </div>
              
              <div>
                <label className="label">Einddatum</label>
                <input
                  type="date"
                  {...register('end_date')}
                  className="input"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Current Position */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is_current"
                {...register('is_current')}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-electric-cyan focus:ring-electric-cyan dark:bg-gray-700"
                disabled={isLoading}
              />
              <label htmlFor="is_current" className="ml-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Werkt hier momenteel
              </label>
            </div>
          </div>
          
          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Annuleren
            </button>
            <button
              type="submit"
              className={`btn-primary ${!isOnline ? 'opacity-50 cursor-not-allowed' : ''}`}
              disabled={!isOnline || isLoading}
            >
              {isLoading ? 'Opslaan...' : (isEditing ? 'Wijzigingen opslaan' : 'Functie toevoegen')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
