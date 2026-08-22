import { useEffect, useState } from 'react';
import { Building2, UserRoundPlus, UsersRound, X } from 'lucide-react';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';
import { SearchablePersonSelector } from '@/components/RelationshipEditModal';

const EMPTY_FORM = { name: '', email: '', phone: '' };

export default function ParentRelationshipModal({
  isOpen,
  onClose,
  onSubmit,
  onAddPerson,
  onAddSponsor,
  canAddParent = false,
  isLoading,
  personId,
}) {
  const [screen, setScreen] = useState('choice');
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState('');
  const [existingParentId, setExistingParentId] = useState(null);
  const isOnline = useOnlineStatus();

  useEffect(() => {
    if (isOpen) {
      setScreen('choice');
      setForm(EMPTY_FORM);
      setError('');
      setExistingParentId(null);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    try {
      await onSubmit({
        mode: 'new',
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
      });
    } catch (submitError) {
      setError(submitError?.response?.data?.message || 'De ouder/verzorger kon niet worden toegevoegd.');
    }
  };

  const handleExistingSubmit = async (event) => {
    event.preventDefault();
    if (!existingParentId) return;
    setError('');
    try {
      await onSubmit({ mode: 'existing', parent_id: existingParentId });
    } catch (submitError) {
      setError(submitError?.response?.data?.message || 'De ouder/verzorger kon niet worden gekoppeld.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="presentation">
      <div
        className="mx-4 w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800"
        role="dialog"
        aria-modal="true"
        aria-labelledby="parent-relationship-title"
      >
        <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
          <h2 id="parent-relationship-title" className="text-lg font-semibold text-gray-900 dark:text-gray-50">
            {screen === 'choice' ? 'Relatie toevoegen' : screen === 'existing' ? 'Bestaande ouder/verzorger' : 'Nieuwe ouder/verzorger'}
          </h2>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" disabled={isLoading} aria-label="Sluiten">
            <X className="h-5 w-5" />
          </button>
        </div>

        {screen === 'choice' ? (
          <div className="space-y-3 p-4">
            <p className="text-sm text-gray-600 dark:text-gray-300">
              {canAddParent
                ? 'Bestaat de persoon al in Rondo, of wil je een nieuwe ouder/verzorger toevoegen?'
                : 'Kies welk soort relatie je wilt toevoegen.'}
            </p>
            {(canAddParent || onAddPerson) && (
              <button
                type="button"
                onClick={canAddParent ? () => setScreen('existing') : onAddPerson}
                className="flex w-full items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-electric-cyan hover:bg-cyan-50 dark:border-gray-700 dark:hover:bg-gray-700"
              >
                <UsersRound className="mt-0.5 h-5 w-5 shrink-0 text-electric-cyan" />
                <span>
                  <span className="block font-medium text-gray-900 dark:text-gray-50">Bestaande persoon</span>
                  <span className="mt-1 block text-sm text-gray-500 dark:text-gray-400">
                    {canAddParent
                      ? 'Koppel een bestaande ouder/verzorger en stuur de relatie naar Sportlink.'
                      : 'Zoek iemand die al in Rondo staat en kies het relatietype.'}
                  </span>
                </span>
              </button>
            )}
            {canAddParent && (
              <button
                type="button"
                onClick={() => setScreen('new')}
                className="flex w-full items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-electric-cyan hover:bg-cyan-50 dark:border-gray-700 dark:hover:bg-gray-700"
              >
                <UserRoundPlus className="mt-0.5 h-5 w-5 shrink-0 text-electric-cyan" />
                <span>
                  <span className="block font-medium text-gray-900 dark:text-gray-50">Nieuwe ouder/verzorger</span>
                  <span className="mt-1 block text-sm text-gray-500 dark:text-gray-400">Maak de persoon aan en stuur de gegevens naar een vrij Sportlink-ouderveld.</span>
                </span>
              </button>
            )}
            {onAddSponsor && (
              <button
                type="button"
                onClick={onAddSponsor}
                className="flex w-full items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-electric-cyan hover:bg-cyan-50 dark:border-gray-700 dark:hover:bg-gray-700"
              >
                <Building2 className="mt-0.5 h-5 w-5 shrink-0 text-electric-cyan" />
                <span>
                  <span className="block font-medium text-gray-900 dark:text-gray-50">Sponsor</span>
                  <span className="mt-1 block text-sm text-gray-500 dark:text-gray-400">Zoek een sponsor en koppel deze persoon als contact.</span>
                </span>
              </button>
            )}
          </div>
        ) : screen === 'existing' ? (
          <form onSubmit={handleExistingSubmit}>
            <div className="space-y-4 p-4">
              <div>
                <label className="label">Persoon *</label>
                <SearchablePersonSelector
                  value={existingParentId}
                  onChange={setExistingParentId}
                  excludePersonId={Number(personId)}
                />
              </div>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Deze persoon wordt als ouder/verzorger gekoppeld en naar een vrij ouderveld in Sportlink gestuurd.
              </p>
              {error && <p className="text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>}
            </div>
            <div className="flex justify-between gap-2 border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
              <button type="button" onClick={() => setScreen('choice')} className="btn-secondary" disabled={isLoading}>Terug</button>
              <button type="submit" className="btn-primary" disabled={!isOnline || isLoading || !existingParentId}>
                {isLoading ? 'Koppelen…' : 'Ouder/verzorger koppelen'}
              </button>
            </div>
          </form>
        ) : (
          <form onSubmit={handleSubmit}>
            <div className="space-y-4 p-4">
              <div>
                <label htmlFor="new-parent-name" className="label">Naam *</label>
                <input
                  id="new-parent-name"
                  className="input"
                  value={form.name}
                  onChange={(event) => setForm(current => ({ ...current, name: event.target.value }))}
                  autoComplete="name"
                  required
                  disabled={isLoading}
                />
              </div>
              <div>
                <label htmlFor="new-parent-email" className="label">E-mailadres *</label>
                <input
                  id="new-parent-email"
                  type="email"
                  className="input"
                  value={form.email}
                  onChange={(event) => setForm(current => ({ ...current, email: event.target.value }))}
                  autoComplete="email"
                  required
                  disabled={isLoading}
                />
              </div>
              <div>
                <label htmlFor="new-parent-phone" className="label">Telefoonnummer</label>
                <input
                  id="new-parent-phone"
                  type="tel"
                  className="input"
                  value={form.phone}
                  onChange={(event) => setForm(current => ({ ...current, phone: event.target.value }))}
                  autoComplete="tel"
                  disabled={isLoading}
                />
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Optioneel</p>
              </div>
              {error && <p className="text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>}
            </div>
            <div className="flex justify-between gap-2 border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
              <button type="button" onClick={() => setScreen('choice')} className="btn-secondary" disabled={isLoading}>Terug</button>
              <button type="submit" className="btn-primary" disabled={!isOnline || isLoading}>
                {isLoading ? 'Toevoegen…' : 'Ouder/verzorger toevoegen'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
