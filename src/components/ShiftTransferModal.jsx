import { useDeferredValue, useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowRight, Loader2, X } from 'lucide-react';
import { prmApi } from '@/api/client';
import { formatStoredDateTime } from '@/utils/dateFormat';
import { decodeHtml } from '@/utils/formatters';
import { refreshShiftCalendars } from '@/utils/shiftQueryCache';

const errorMessage = (error) => error?.response?.data?.message || 'Laden is niet gelukt. Probeer het opnieuw.';

export default function ShiftTransferModal({ personId, onClose, onTransferred }) {
  const dialog = useRef(null);
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [target, setTarget] = useState(null);
  const [selected, setSelected] = useState([]);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');
  const deferredSearch = useDeferredValue(search.trim());
  const options = useQuery({
    queryKey: ['shift-transfer-options', personId, deferredSearch],
    queryFn: async () => (await prmApi.getShiftTransferOptions(personId, deferredSearch)).data,
    retry: false,
    staleTime: 0,
  });
  const shifts = options.data?.shifts || [];
  const selectedShifts = shifts.filter((shift) => selected.includes(shift.id));
  const mutation = useMutation({
    retry: false,
    mutationFn: async () => {
      const payload = { target_id: target.id, shift_ids: selected };
      if (!preview) return (await prmApi.previewShiftTransfer(personId, payload)).data;
      return (await prmApi.transferShifts(personId, { ...payload, token: preview.token })).data;
    },
  });

  useEffect(() => {
    const element = dialog.current;
    element.showModal();
    return () => element.close();
  }, []);

  async function submit(event) {
    event.preventDefault();
    setError('');
    try {
      const result = await mutation.mutateAsync();
      if (!preview) {
        setPreview(result);
        return;
      }
      // Inactive person profiles must also refresh: the app disables refetch-on-mount.
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['people'], refetchType: 'all' }),
        queryClient.invalidateQueries({ queryKey: ['volunteer'], refetchType: 'all' }),
        queryClient.invalidateQueries({ queryKey: ['my-shifts'], refetchType: 'all' }),
        queryClient.invalidateQueries({ queryKey: ['shift-transfer-options'] }),
        refreshShiftCalendars(queryClient),
      ]);
      onTransferred(result.transferred.length, target.name);
    } catch (failure) {
      setError(errorMessage(failure));
      // Keep the confirmed token after a network failure so a retry is idempotent.
      if (failure.response) {
        setPreview(null);
        void options.refetch();
      }
    }
  }

  function toggle(id) {
    setSelected((current) => current.includes(id) ? current.filter((value) => value !== id) : [...current, id]);
    setError('');
  }

  return (
    <dialog ref={dialog} aria-labelledby="shift-transfer-title" onCancel={(event) => { event.preventDefault(); if (!mutation.isPending) onClose(); }} className="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/50 dark:bg-gray-800 dark:text-gray-100">
      <form onSubmit={submit} className="p-5 sm:p-6">
        <header className="mb-5 flex items-start justify-between gap-4">
          <h2 id="shift-transfer-title" className="text-lg font-semibold">{preview ? 'Overzetten controleren' : 'Inschrijftaken overzetten'}</h2>
          <button type="button" onClick={onClose} disabled={mutation.isPending} aria-label="Sluiten" className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-700"><X className="h-5 w-5" /></button>
        </header>

        {preview ? (
          <div className="space-y-4">
            <p className="flex flex-wrap items-center gap-2 font-medium"><span>{preview.source.name}</span><ArrowRight aria-hidden="true" className="h-4 w-4" /><span>{preview.target.name}</span></p>
            <p className="text-sm text-gray-600 dark:text-gray-300">Je verplaatst {preview.count} {preview.count === 1 ? 'inschrijftaak' : 'inschrijftaken'}. De status en registratiegeschiedenis blijven behouden.</p>
            <ul className="divide-y divide-gray-200 dark:divide-gray-700">{selectedShifts.map((shift) => <ShiftRow key={shift.id} shift={shift} />)}</ul>
          </div>
        ) : (
          <div className="space-y-5">
            <section aria-labelledby="transfer-recipient-label">
              <label id="transfer-recipient-label" htmlFor="shift-transfer-search" className="mb-2 block text-sm font-medium">Naar wie wil je de taken overzetten?</label>
              <input id="shift-transfer-search" type="search" className="input w-full" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Zoek op naam of persoonsnummer" />
              <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">{options.data?.family_only ? 'Je kunt alleen binnen de vastgelegde familie overzetten.' : 'Kies een familielid of zoek een andere persoon.'}</p>
              {target && <p className="mt-2 text-sm font-medium">Gekozen: {target.name} <button type="button" className="ml-2 underline underline-offset-2" onClick={() => setTarget(null)}>Wijzigen</button></p>}
              {options.isFetching && <p role="status" className="mt-3 text-sm text-gray-600 dark:text-gray-300">Personen en taken laden…</p>}
              {options.isError && <div role="alert" className="mt-3 text-sm text-red-700 dark:text-red-300">{errorMessage(options.error)} <button type="button" onClick={() => options.refetch()} className="underline">Opnieuw laden</button></div>}
              {options.data && !target && <div className="mt-3 max-h-44 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                {options.data.people.length ? options.data.people.map((person) => (
                  <button key={person.id} type="button" className="block w-full px-3 py-3 text-left text-sm hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-gray-700 dark:focus-visible:bg-gray-700" onClick={() => { setTarget(person); setError(''); }}>
                    <span className="font-medium">{person.name}</span><span className="ml-2 text-gray-500 dark:text-gray-400">#{person.id}</span>
                  </button>
                )) : <p className="p-3 text-sm text-gray-600 dark:text-gray-300">{options.data.family_only ? 'Geen toegankelijke familieleden gevonden. Controleer de familierelaties op de persoonspagina.' : 'Geen personen gevonden. Zoek op naam of persoonsnummer.'}</p>}
              </div>}
            </section>
            {options.data && <fieldset>
              <legend className="mb-2 text-sm font-medium">Welke taken wil je overzetten?</legend>
              {shifts.length ? <>
                <label className="mb-2 flex min-h-11 cursor-pointer items-center gap-3 text-sm font-medium">
                  <input type="checkbox" checked={shifts.every((shift) => selected.includes(shift.id))} onChange={(event) => setSelected(event.target.checked ? shifts.map((shift) => shift.id) : [])} className="h-4 w-4 rounded" />Alles selecteren ({shifts.length})
                </label>
                <ul className="max-h-64 divide-y divide-gray-200 overflow-y-auto dark:divide-gray-700">{shifts.map((shift) => <ShiftRow key={shift.id} shift={shift} checked={selected.includes(shift.id)} onToggle={() => toggle(shift.id)} />)}</ul>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">{selected.length} geselecteerd. Geannuleerde taken worden niet overgezet.</p>
              </> : <p className="text-sm text-gray-600 dark:text-gray-300">Deze persoon heeft geen ingeplande of uitgevoerde inschrijftaken.</p>}
            </fieldset>}
          </div>
        )}
        {error && <p role="alert" className="mt-4 text-sm text-red-700 dark:text-red-300">{error}</p>}
        <footer className="mt-6 flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
          <button type="button" className="btn-secondary" disabled={mutation.isPending} onClick={() => preview ? setPreview(null) : onClose()}>{preview ? 'Terug' : 'Annuleren'}</button>
          <button type="submit" className="btn-primary" disabled={mutation.isPending || !target || selected.length === 0 || options.isError || (!preview && options.isFetching)}>
            {mutation.isPending && <Loader2 aria-hidden="true" className="mr-2 h-4 w-4 animate-spin" />}{mutation.isPending ? 'Bezig…' : preview ? 'Taken overzetten' : 'Controleren'}
          </button>
        </footer>
      </form>
    </dialog>
  );
}

function ShiftRow({ shift, checked, onToggle }) {
  const details = <span className="min-w-0"><span className="block text-sm font-medium">{decodeHtml(shift.dienst_type_name || shift.title)}</span><span className="mt-1 block text-sm text-gray-600 dark:text-gray-300">{formatStoredDateTime(shift.start_datetime, 'd MMMM yyyy, HH:mm')} · {shift.no_show ? 'No-show' : shift.status === 'voltooid' ? 'Voltooid' : 'Ingepland'}</span></span>;
  return <li className="py-3">{onToggle ? <label className="flex cursor-pointer items-start gap-3"><input type="checkbox" checked={checked} onChange={onToggle} className="mt-1 h-4 w-4 shrink-0 rounded" />{details}</label> : details}</li>;
}
