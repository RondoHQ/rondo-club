import { useEffect, useState, useMemo, useRef } from 'react';
import { useForm } from 'react-hook-form';
import { X, ChevronDown, Building2, Search } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { getCommissieName, decodeHtml } from '@/utils/formatters';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

export default function CommissieEditModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  commissie = null // Pass commissie data for editing
}) {
  const isEditing = !!commissie;
  const isOnline = useOnlineStatus();

  // State for parent commissie dropdown
  const [isParentDropdownOpen, setIsParentDropdownOpen] = useState(false);
  const [parentSearchQuery, setParentSearchQuery] = useState('');
  const [selectedParentId, setSelectedParentId] = useState('');

  const parentDropdownRef = useRef(null);

  // Fetch all commissies for parent selection
  const { data: allCommissies = [], isLoading: isLoadingCommissies } = useQuery({
    queryKey: ['commissies', 'all'],
    queryFn: async () => {
      const response = await wpApi.getCommissies({ per_page: 100, _embed: true });
      return response.data;
    },
    enabled: isOpen,
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      title: '',
      website: '',
    },
  });

  // Filter commissies for parent dropdown (exclude self and children)
  const availableParentCommissies = useMemo(() => {
    const query = parentSearchQuery.toLowerCase().trim();
    let filtered = allCommissies.filter(c => {
      // Exclude self
      if (isEditing && c.id === commissie?.id) return false;
      // Exclude commissies that have this commissie as parent (prevents circular references)
      if (isEditing && c.parent === commissie?.id) return false;
      return true;
    });

    if (query) {
      filtered = filtered.filter(c =>
        getCommissieName(c)?.toLowerCase().includes(query)
      );
    }

    // Sort alphabetically
    return [...filtered].sort((a, b) =>
      (getCommissieName(a) || '').localeCompare(getCommissieName(b) || '')
    );
  }, [allCommissies, parentSearchQuery, commissie, isEditing]);

  // Get selected parent commissie details
  const selectedParent = useMemo(() =>
    allCommissies.find(c => c.id === parseInt(selectedParentId)),
    [allCommissies, selectedParentId]
  );

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      setIsParentDropdownOpen(false);
      setParentSearchQuery('');

      if (commissie) {
        // Editing - populate with existing data
        reset({
          title: decodeHtml(commissie.title?.rendered || ''),
          website: commissie.acf?.website || '',
        });
        setSelectedParentId(commissie.parent ? String(commissie.parent) : '');
      } else {
        // Creating - reset to defaults
        reset({
          title: '',
          website: '',
        });
        setSelectedParentId('');
      }
    }
  }, [isOpen, commissie, reset]);

  // Close parent dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (parentDropdownRef.current && !parentDropdownRef.current.contains(event.target)) {
        setIsParentDropdownOpen(false);
      }
    };

    if (isParentDropdownOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isParentDropdownOpen]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      ...data,
      parentId: selectedParentId ? parseInt(selectedParentId) : 0,
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Commissie bewerken' : 'Nieuwe commissie'}</h2>
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
            {/* Commissie name */}
            <div>
              <label className="label">Naam *</label>
              <input
                {...register('title', { required: 'Naam is verplicht' })}
                className="input"
                placeholder="Commissienaam"
                disabled={isLoading}
                autoFocus
              />
              {errors.title && (
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.title.message}</p>
              )}
            </div>

            {/* Website */}
            <div>
              <label className="label">Website</label>
              <input
                {...register('website')}
                type="url"
                className="input"
                placeholder="https://example.com"
                disabled={isLoading}
              />
            </div>

            {/* Parent commissie selection */}
            <div>
              <label className="label">Hoofdcommissie</label>
              <div className="relative" ref={parentDropdownRef}>
                <button
                  type="button"
                  onClick={() => setIsParentDropdownOpen(!isParentDropdownOpen)}
                  className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-left focus:outline-none focus:ring-2 focus:ring-electric-cyan focus:border-transparent"
                  disabled={isLoadingCommissies || isLoading}
                >
                  {selectedParent ? (
                    <div className="flex items-center gap-2">
                      {selectedParent._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                        <img
                          src={selectedParent._embedded['wp:featuredmedia'][0].source_url}
                          alt={getCommissieName(selectedParent)}
                          className="w-6 h-6 rounded object-contain dark:bg-gray-600"
                        />
                      ) : (
                        <div className="w-6 h-6 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                          <Building2 className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                        </div>
                      )}
                      <span className="text-gray-900 dark:text-gray-50">{getCommissieName(selectedParent)}</span>
                    </div>
                  ) : (
                    <span className="text-gray-400 dark:text-gray-500">Geen hoofdcommissie</span>
                  )}
                  <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${isParentDropdownOpen ? 'rotate-180' : ''}`} />
                </button>

                {isParentDropdownOpen && (
                  <div className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-hidden">
                    {/* Search input */}
                    <div className="p-2 border-b border-gray-100 dark:border-gray-700">
                      <div className="relative">
                        <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                          type="text"
                          value={parentSearchQuery}
                          onChange={(e) => setParentSearchQuery(e.target.value)}
                          placeholder="Commissies zoeken..."
                          className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-1 focus:ring-electric-cyan"
                          autoFocus
                        />
                      </div>
                    </div>

                    {/* "None" option */}
                    <div className="overflow-y-auto max-h-48">
                      <button
                        type="button"
                        onClick={() => {
                          setSelectedParentId('');
                          setIsParentDropdownOpen(false);
                          setParentSearchQuery('');
                        }}
                        className={`w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${
                          !selectedParentId ? 'bg-cyan-50 dark:bg-deep-midnight' : ''
                        }`}
                      >
                        <span className="text-sm text-gray-500 dark:text-gray-400 italic">Geen hoofdcommissie</span>
                      </button>

                      {/* Commissies list */}
                      {isLoadingCommissies ? (
                        <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                          Laden...
                        </div>
                      ) : availableParentCommissies.length > 0 ? (
                        availableParentCommissies.map((c) => (
                          <button
                            key={c.id}
                            type="button"
                            onClick={() => {
                              setSelectedParentId(String(c.id));
                              setIsParentDropdownOpen(false);
                              setParentSearchQuery('');
                            }}
                            className={`w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${
                              selectedParentId === String(c.id) ? 'bg-cyan-50 dark:bg-deep-midnight' : ''
                            }`}
                          >
                            {c._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                              <img
                                src={c._embedded['wp:featuredmedia'][0].source_url}
                                alt={getCommissieName(c)}
                                className="w-6 h-6 rounded object-contain"
                              />
                            ) : (
                              <div className="w-6 h-6 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                                <Building2 className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                              </div>
                            )}
                            <span className="text-sm text-gray-900 dark:text-gray-50 truncate">{getCommissieName(c)}</span>
                          </button>
                        ))
                      ) : (
                        <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                          Geen commissies gevonden
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Selecteer als dit een subcommissie is
              </p>
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
              {isLoading ? 'Opslaan...' : (isEditing ? 'Wijzigingen opslaan' : 'Commissie aanmaken')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
