import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CircleAlert, ImagePlus, Pencil, Plus, Trash2, X } from 'lucide-react';
import { prmApi, wpApi } from '@/api/client';

const typeLabels = {
  announcement: 'Mededeling',
  sponsor: 'Sponsor',
  image: 'Afbeelding',
  video: 'Video',
  matches: 'Wedstrijden, velden en kleedkamers',
  rooms: 'Wedstrijden, velden en kleedkamers',
  cancellations: 'Afgelastingen',
  results: 'Uitslagen',
  fallback: 'Welkomstbeeld',
};

const emptyForm = {
  title: '', content_type: 'announcement', body: '', cta_text: '', duration_seconds: 12,
  enabled: true, valid_from: '', valid_until: '', sponsor_id: '', media_attachment_id: '',
  background_color: '#0f172a', text_color: '#ffffff', accent_color: '#22d3ee',
  use_club_colors: true,
  is_override: false, override_display_ids: [], priority: 50,
};

function message(error) {
  return error?.response?.data?.message || 'Opslaan is niet gelukt.';
}

function toInputDate(value) {
  return value ? value.slice(0, 16) : '';
}

function fromItem(item) {
  return {
    ...emptyForm,
    ...item.fields,
    title: item.title,
    content_type: item.fields.content_type === 'rooms' ? 'matches' : item.fields.content_type,
    sponsor_id: item.fields.sponsor_id || item.fields.sponsor_person_id || '',
    media_attachment_id: item.fields.media_attachment_id || '',
    valid_from: toInputDate(item.fields.valid_from),
    valid_until: toInputDate(item.fields.valid_until),
    override_display_ids: item.fields.override_display_ids || [],
  };
}

export default function NarrowcastingContent({ sponsorOnly = false }) {
  const client = useQueryClient();
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ ...emptyForm, content_type: sponsorOnly ? 'sponsor' : 'announcement' });
  const [uploading, setUploading] = useState(false);

  const itemsQuery = useQuery({ queryKey: ['narrowcasting', 'items'], queryFn: async () => (await prmApi.getNarrowcastingItems()).data });
  const sponsorsQuery = useQuery({ queryKey: ['narrowcasting', 'sponsors'], queryFn: async () => (await prmApi.getNarrowcastingSponsors()).data });
  const displaysQuery = useQuery({
    queryKey: ['narrowcasting', 'display-choices'],
    queryFn: async () => (await prmApi.getNarrowcastingDisplayChoices()).data,
    enabled: !sponsorOnly,
  });

  const refresh = () => client.invalidateQueries({ queryKey: ['narrowcasting'] });
  const save = useMutation({
    mutationFn: (payload) => editing
      ? prmApi.updateNarrowcastingItem(editing.id, payload)
      : prmApi.createNarrowcastingItem(payload),
    onSuccess: () => { refresh(); closeForm(); },
  });
  const remove = useMutation({
    mutationFn: (id) => prmApi.deleteNarrowcastingItem(id),
    onSuccess: refresh,
  });

  const openNew = () => {
    setEditing(null);
    setForm({ ...emptyForm, content_type: sponsorOnly ? 'sponsor' : 'announcement' });
  };
  const closeForm = () => { setEditing(null); setForm({ ...emptyForm, content_type: sponsorOnly ? 'sponsor' : 'announcement' }); };
  const edit = (item) => { setEditing(item); setForm(fromItem(item)); };
  const submit = (event) => {
    event.preventDefault();
    save.mutate({
      ...form,
      sponsor_id: Number(form.sponsor_id) || 0,
      media_attachment_id: Number(form.media_attachment_id) || 0,
      valid_from: form.valid_from ? new Date(form.valid_from).toISOString() : '',
      valid_until: form.valid_until ? new Date(form.valid_until).toISOString() : '',
    });
  };
  const upload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try {
      const response = await wpApi.uploadMedia(file);
      setForm((current) => ({ ...current, media_attachment_id: response.data.id }));
    } finally {
      setUploading(false);
      event.target.value = '';
    }
  };

  const showMedia = ['image', 'video', 'sponsor'].includes(form.content_type);
  const showText = ['announcement', 'fallback', 'sponsor'].includes(form.content_type);

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]">
      <section className="card p-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Content</h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Maak beelden die je later in een afspeellijst zet.</p>
          </div>
          <button type="button" className="btn-primary" onClick={openNew}><Plus className="mr-2 h-4 w-4" />Nieuw</button>
        </div>

        <div className="mt-5 space-y-3">
          {(itemsQuery.data || []).map((item) => (
            <article key={item.id} className="flex items-center gap-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
              {item.media?.url ? <img src={item.media.url} alt="" className="h-16 w-24 rounded bg-gray-100 object-contain dark:bg-gray-800" /> : <div className="h-16 w-24 rounded bg-gray-100 dark:bg-gray-800" />}
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="truncate font-medium text-gray-900 dark:text-gray-100">{item.title}</h3>
                  <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">{typeLabels[item.fields.content_type]}</span>
                  {!item.fields.enabled && <span className="text-xs text-amber-700 dark:text-amber-300">Uitgeschakeld</span>}
                  {item.fields.is_override && <span className="text-xs font-medium text-red-700 dark:text-red-300">Override</span>}
                </div>
                <p className="mt-1 truncate text-sm text-gray-500">{item.fields.body || `${item.fields.duration_seconds} seconden`}</p>
              </div>
              <button type="button" className="btn-tertiary p-2" onClick={() => edit(item)} aria-label="Bewerken"><Pencil className="h-4 w-4" /></button>
              <button type="button" className="btn-tertiary p-2 text-red-600" onClick={() => window.confirm(`“${item.title}” verwijderen?`) && remove.mutate(item.id)} aria-label="Verwijderen"><Trash2 className="h-4 w-4" /></button>
            </article>
          ))}
          {!itemsQuery.isLoading && !(itemsQuery.data || []).length && <p className="rounded-lg bg-gray-50 p-6 text-center text-gray-500 dark:bg-gray-800/50">Nog geen Club TV-content.</p>}
        </div>
      </section>

      <section className="card p-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{editing ? 'Item bewerken' : 'Nieuw item'}</h2>
          {editing && <button type="button" onClick={closeForm} className="btn-tertiary p-2" aria-label="Sluiten"><X className="h-4 w-4" /></button>}
        </div>
        {save.error && <p className="mt-4 flex gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><CircleAlert className="h-5 w-5 shrink-0" />{message(save.error)}</p>}
        <form onSubmit={submit} className="mt-5 space-y-4">
          <label className="block"><span className="mb-1 block text-sm font-medium">Titel</span><input className="input w-full" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required /></label>
          {!sponsorOnly && <label className="block"><span className="mb-1 block text-sm font-medium">Type</span><select className="input w-full" value={form.content_type} onChange={(e) => setForm({ ...form, content_type: e.target.value, media_attachment_id: '' })}>{Object.entries(typeLabels).filter(([value]) => value !== 'rooms').map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>}
          {form.content_type === 'sponsor' && <label className="block"><span className="mb-1 block text-sm font-medium">Sponsor</span><select className="input w-full" value={form.sponsor_id} onChange={(e) => setForm({ ...form, sponsor_id: e.target.value })} required><option value="">Kies sponsor</option>{(sponsorsQuery.data || []).map((sponsor) => <option key={sponsor.id} value={sponsor.id} disabled={sponsor.club_tv_opt_out}>{sponsor.name}{sponsor.legacy ? ' · nog te migreren' : ''}{sponsor.club_tv_opt_out ? ' · afgemeld' : ''}</option>)}</select></label>}
          {showText && <label className="block"><span className="mb-1 block text-sm font-medium">Tekst</span><textarea className="input min-h-24 w-full" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} /></label>}
          {showMedia && <div><span className="mb-1 block text-sm font-medium">{form.content_type === 'video' ? 'MP4-video' : 'Afbeelding'}</span><label className="btn-tertiary inline-flex cursor-pointer"><ImagePlus className="mr-2 h-4 w-4" />{uploading ? 'Uploaden…' : 'Bestand kiezen'}<input type="file" className="sr-only" accept={form.content_type === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp'} onChange={upload} disabled={uploading} /></label>{form.media_attachment_id && <span className="ml-3 text-sm text-green-700 dark:text-green-300">Bestand gekoppeld</span>}</div>}
          <div className="grid grid-cols-2 gap-4"><label><span className="mb-1 block text-sm font-medium">Duur (sec.)</span><input type="number" min="5" max="120" className="input w-full" value={form.duration_seconds} onChange={(e) => setForm({ ...form, duration_seconds: Number(e.target.value) })} /></label><label className="flex items-end gap-2 pb-2"><input type="checkbox" checked={form.enabled} onChange={(e) => setForm({ ...form, enabled: e.target.checked })} /> Actief</label></div>
          <div className="grid grid-cols-2 gap-4"><label><span className="mb-1 block text-sm font-medium">Vanaf</span><input type="datetime-local" className="input w-full" value={form.valid_from} onChange={(e) => setForm({ ...form, valid_from: e.target.value })} /></label><label><span className="mb-1 block text-sm font-medium">Tot</span><input type="datetime-local" className="input w-full" value={form.valid_until} onChange={(e) => setForm({ ...form, valid_until: e.target.value })} /></label></div>
          {!sponsorOnly && <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"><label className="flex items-center gap-2 font-medium"><input type="checkbox" checked={form.is_override} onChange={(e) => setForm({ ...form, is_override: e.target.checked })} /> Tijdelijk alle normale content vervangen</label>{form.is_override && <><label className="mt-3 block text-sm">Prioriteit <input type="number" min="0" max="100" className="input mt-1 w-full" value={form.priority} onChange={(e) => setForm({ ...form, priority: Number(e.target.value) })} /></label><div className="mt-3"><p className="text-sm font-medium">Alleen op deze schermen (leeg = alle)</p>{(displaysQuery.data || []).map((display) => <label key={display.id} className="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" checked={form.override_display_ids.includes(display.id)} onChange={(e) => setForm({ ...form, override_display_ids: e.target.checked ? [...form.override_display_ids, display.id] : form.override_display_ids.filter((id) => id !== display.id) })} />{display.name}{display.location ? ` · ${display.location}` : ''}</label>)}</div></>}</div>}
          <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <label className="flex items-center gap-2 font-medium"><input type="checkbox" checked={form.use_club_colors} onChange={(e) => setForm({ ...form, use_club_colors: e.target.checked })} /> Clubkleuren gebruiken</label>
            <p className="mt-1 text-sm text-gray-500">Gebruikt het centrale clublogo, de clubkleur en een lichte clubachtergrond.</p>
            {!form.use_club_colors && <div className="mt-4 grid grid-cols-3 gap-3">{[['background_color', 'Achtergrond'], ['text_color', 'Tekst'], ['accent_color', 'Accent']].map(([key, label]) => <label key={key} className="text-sm"><span className="mb-1 block font-medium">{label}</span><input type="color" className="h-10 w-full rounded border" value={form[key]} onChange={(e) => setForm({ ...form, [key]: e.target.value })} /></label>)}</div>}
          </div>
          <button type="submit" className="btn-primary w-full" disabled={save.isPending || uploading}>{save.isPending ? 'Opslaan…' : editing ? 'Wijzigingen opslaan' : 'Item maken'}</button>
        </form>
      </section>
    </div>
  );
}
