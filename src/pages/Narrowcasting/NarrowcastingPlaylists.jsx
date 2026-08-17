import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDown, ArrowUp, CircleAlert, ExternalLink, Pencil, Plus, Trash2, X } from 'lucide-react';
import { prmApi } from '@/api/client';

const days = [['mon', 'Ma'], ['tue', 'Di'], ['wed', 'Wo'], ['thu', 'Do'], ['fri', 'Vr'], ['sat', 'Za'], ['sun', 'Zo']];
const emptyForm = { title: '', enabled: true, valid_from: '', valid_until: '', days_of_week: [], start_time: '', end_time: '', fallback_item_id: '', items: [] };

function errorMessage(error) { return error?.response?.data?.message || 'Opslaan is niet gelukt.'; }
function inputDate(value) { return value ? value.slice(0, 16) : ''; }

export default function NarrowcastingPlaylists() {
  const client = useQueryClient();
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const playlists = useQuery({ queryKey: ['narrowcasting', 'playlists'], queryFn: async () => (await prmApi.getNarrowcastingPlaylists()).data });
  const items = useQuery({ queryKey: ['narrowcasting', 'items'], queryFn: async () => (await prmApi.getNarrowcastingItems()).data });
  const refresh = () => client.invalidateQueries({ queryKey: ['narrowcasting'] });
  const save = useMutation({
    mutationFn: (payload) => editing ? prmApi.updateNarrowcastingPlaylist(editing.id, payload) : prmApi.createNarrowcastingPlaylist(payload),
    onSuccess: () => { refresh(); setEditing(null); setForm(emptyForm); },
  });
  const remove = useMutation({ mutationFn: (id) => prmApi.deleteNarrowcastingPlaylist(id), onSuccess: refresh });
  const makeDefault = useMutation({ mutationFn: (id) => prmApi.setDefaultNarrowcastingPlaylist(id), onSuccess: refresh });

  const edit = (playlist) => {
    setEditing(playlist);
    setForm({ ...emptyForm, ...playlist.fields, title: playlist.title, valid_from: inputDate(playlist.fields.valid_from), valid_until: inputDate(playlist.fields.valid_until), fallback_item_id: playlist.fields.fallback_item_id || '' });
  };
  const submit = (event) => {
    event.preventDefault();
    save.mutate({ ...form, valid_from: form.valid_from ? new Date(form.valid_from).toISOString() : '', valid_until: form.valid_until ? new Date(form.valid_until).toISOString() : '', fallback_item_id: Number(form.fallback_item_id) || 0 });
  };
  const addItem = (itemId) => setForm({ ...form, items: [...form.items, { item_id: Number(itemId), duration_seconds: 0, weight: 1 }] });
  const move = (index, direction) => {
    const next = [...form.items];
    const target = index + direction;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    setForm({ ...form, items: next });
  };
  const patchRow = (index, values) => setForm({ ...form, items: form.items.map((row, rowIndex) => rowIndex === index ? { ...row, ...values } : row) });
  const titleFor = (id) => (items.data || []).find((item) => item.id === Number(id))?.title || `Item ${id}`;

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,0.7fr)_minmax(460px,1fr)]">
      <section className="card p-6">
        <div className="flex items-start justify-between gap-4"><div><h2 className="text-lg font-semibold">Afspeellijsten</h2><p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Bepaal volgorde, frequentie en planning.</p></div><button type="button" className="btn-primary" onClick={() => { setEditing(null); setForm(emptyForm); }}><Plus className="mr-2 h-4 w-4" />Nieuw</button></div>
        <div className="mt-5 space-y-3">
          {(playlists.data || []).map((playlist) => <article key={playlist.id} className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"><div className="flex items-start gap-3"><div className="min-w-0 flex-1"><div className="flex flex-wrap gap-2"><h3 className="font-medium">{playlist.title}</h3>{playlist.is_default && <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">Standaard</span>}{!playlist.fields.enabled && <span className="text-xs text-amber-700">Uitgeschakeld</span>}</div><p className="mt-1 text-sm text-gray-500">{playlist.fields.items.length} regels</p></div><button type="button" className="btn-tertiary p-2" onClick={() => edit(playlist)}><Pencil className="h-4 w-4" /></button><button type="button" className="btn-tertiary p-2 text-red-600" onClick={() => window.confirm(`“${playlist.title}” verwijderen?`) && remove.mutate(playlist.id)}><Trash2 className="h-4 w-4" /></button></div><div className="mt-3 flex flex-wrap gap-2">{!playlist.is_default && <button type="button" className="btn-tertiary text-sm" onClick={() => makeDefault.mutate(playlist.id)}>Als standaard instellen</button>}<a href={`/display?preview=1&playlist=${playlist.id}`} target="_blank" rel="noreferrer" className="btn-tertiary text-sm"><ExternalLink className="mr-2 h-4 w-4" />Voorbeeld</a></div></article>)}
          {!playlists.isLoading && !(playlists.data || []).length && <p className="rounded-lg bg-gray-50 p-6 text-center text-gray-500 dark:bg-gray-800/50">Maak je eerste afspeellijst.</p>}
        </div>
      </section>

      <section className="card p-6">
        <div className="flex items-center justify-between"><h2 className="text-lg font-semibold">{editing ? 'Afspeellijst bewerken' : 'Nieuwe afspeellijst'}</h2>{editing && <button type="button" className="btn-tertiary p-2" onClick={() => { setEditing(null); setForm(emptyForm); }}><X className="h-4 w-4" /></button>}</div>
        {save.error && <p className="mt-4 flex gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-800"><CircleAlert className="h-5 w-5 shrink-0" />{errorMessage(save.error)}</p>}
        <form onSubmit={submit} className="mt-5 space-y-5">
          <div className="grid gap-4 md:grid-cols-[1fr_auto]"><label><span className="mb-1 block text-sm font-medium">Naam</span><input className="input w-full" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required /></label><label className="flex items-end gap-2 pb-2"><input type="checkbox" checked={form.enabled} onChange={(e) => setForm({ ...form, enabled: e.target.checked })} /> Actief</label></div>
          <div><p className="mb-2 text-sm font-medium">Dagen (leeg = iedere dag)</p><div className="flex flex-wrap gap-2">{days.map(([value, label]) => <label key={value} className={`cursor-pointer rounded-full border px-3 py-1 text-sm ${form.days_of_week.includes(value) ? 'border-cyan-500 bg-cyan-50 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200' : 'border-gray-300 dark:border-gray-700'}`}><input type="checkbox" className="sr-only" checked={form.days_of_week.includes(value)} onChange={(e) => setForm({ ...form, days_of_week: e.target.checked ? [...form.days_of_week, value] : form.days_of_week.filter((day) => day !== value) })} />{label}</label>)}</div></div>
          <div className="grid grid-cols-2 gap-4"><label><span className="mb-1 block text-sm font-medium">Dagelijks vanaf</span><input type="time" className="input w-full" value={form.start_time || ''} onChange={(e) => setForm({ ...form, start_time: e.target.value })} /></label><label><span className="mb-1 block text-sm font-medium">Tot</span><input type="time" className="input w-full" value={form.end_time || ''} onChange={(e) => setForm({ ...form, end_time: e.target.value })} /></label></div>
          <div className="grid grid-cols-2 gap-4"><label><span className="mb-1 block text-sm font-medium">Geldig vanaf</span><input type="datetime-local" className="input w-full" value={form.valid_from} onChange={(e) => setForm({ ...form, valid_from: e.target.value })} /></label><label><span className="mb-1 block text-sm font-medium">Geldig tot</span><input type="datetime-local" className="input w-full" value={form.valid_until} onChange={(e) => setForm({ ...form, valid_until: e.target.value })} /></label></div>
          <div><div className="flex items-center justify-between"><h3 className="font-medium">Volgorde</h3><select className="input max-w-56" value="" onChange={(e) => { if (e.target.value) addItem(e.target.value); }}><option value="">Item toevoegen…</option>{(items.data || []).map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select></div><div className="mt-3 space-y-2">{form.items.map((row, index) => <div key={`${row.item_id}-${index}`} className="grid grid-cols-[auto_minmax(0,1fr)_80px_70px_auto] items-center gap-2 rounded-lg bg-gray-50 p-2 dark:bg-gray-800"><div className="flex"><button type="button" className="p-1" onClick={() => move(index, -1)}><ArrowUp className="h-4 w-4" /></button><button type="button" className="p-1" onClick={() => move(index, 1)}><ArrowDown className="h-4 w-4" /></button></div><span className="truncate text-sm font-medium">{titleFor(row.item_id)}</span><label className="text-xs">Duur<input aria-label="Duur" type="number" min="0" max="120" className="input mt-1 w-full" value={row.duration_seconds} onChange={(e) => patchRow(index, { duration_seconds: Number(e.target.value) })} /></label><label className="text-xs">Gewicht<input aria-label="Gewicht" type="number" min="1" max="10" className="input mt-1 w-full" value={row.weight} onChange={(e) => patchRow(index, { weight: Number(e.target.value) })} /></label><button type="button" className="p-2 text-red-600" onClick={() => setForm({ ...form, items: form.items.filter((_, rowIndex) => rowIndex !== index) })}><Trash2 className="h-4 w-4" /></button></div>)}</div></div>
          <label className="block"><span className="mb-1 block text-sm font-medium">Reservebeeld als niets actief is</span><select className="input w-full" value={form.fallback_item_id} onChange={(e) => setForm({ ...form, fallback_item_id: e.target.value })}><option value="">Ingebouwd welkomstbeeld</option>{(items.data || []).map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select></label>
          <button type="submit" className="btn-primary w-full" disabled={save.isPending}>{save.isPending ? 'Opslaan…' : editing ? 'Wijzigingen opslaan' : 'Afspeellijst maken'}</button>
        </form>
      </section>
    </div>
  );
}
