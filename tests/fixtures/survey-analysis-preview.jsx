import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import SurveyAnalysis from '../../src/components/surveys/SurveyAnalysis';
import api from '../../src/api/client';
import './dashboard-preview.css';

// Synthetic browser fixture. All requests are handled locally, including writes.
const flags = new URLSearchParams(location.search);
if (flags.has('dark')) document.documentElement.classList.add('dark');
const questions = [
  { id: 'role', label: 'Hoe gebruik je onze accommodatie?', type: 'multiple', section: 'Jouw ervaring', options: [{ id: 'player', label: 'Ik sport hier' }, { id: 'parent', label: 'Mijn kind sport hier' }, { id: 'staff', label: 'Ik begeleid een team' }] },
  { id: 'rating', label: 'Ben je tevreden over de kleedkamers?', type: 'single', section: 'Jouw ervaring', options: [{ id: 'yes', label: 'Tevreden' }, { id: 'no', label: 'Ontevreden' }, { id: 'unknown', label: 'Weet ik niet' }] },
  { id: 'note', label: 'Wat kunnen we verbeteren?', type: 'text', section: 'Jouw ideeën', options: [] },
];
const replies = flags.has('empty') ? [] : [
  { id: 1, answers: { role: ['player', 'parent'], rating: 'yes', note: 'Meer verlichting bij de fietsenstalling zou fijn zijn.' }, themes: [] },
  { id: 2, answers: { role: ['parent'], rating: 'no', note: 'De kleedkamers zijn niet altijd schoon na de vorige wedstrijd.' }, themes: [] },
  { id: 3, answers: { role: ['staff'], rating: 'unknown', note: 'Fijn dat we onze mening kunnen geven.' }, themes: [] },
  { id: 4, answers: { role: ['player'], rating: 'yes' }, themes: [] },
];
let configuration = { revision: 'r1', profile: { title: 'Accommodatie', source_profile: null, context: '', metrics: [{ id: 'happy', label: 'Tevreden over de kleedkamers', question: 'rating', options: ['yes'], exclude: ['unknown'] }], comparisons: [{ id: 'roles', label: 'Tevredenheid naar gebruik', outcome: 'rating', group: 'role' }], views: [{ id: 'parents', label: 'Ouders', filters: { role: ['parent'] } }], themes: [{ id: 'light', label: 'Verlichting', keywords: ['verlichting'] }, { id: 'clean', label: 'Schoonmaak', keywords: ['schoon'] }] } };
const blank = { title: 'Algemene analyse', source_profile: null, context: '', metrics: [], comparisons: [], views: [], themes: [] };
const templates = [{ id: 'blank', version: 1, profile: blank }, { id: 'accommodation', version: 1, profile: structuredClone(configuration.profile) }];
const reports = [];
const summary = (rows) => questions.map((q) => ({ ...q, answered: rows.filter((r) => Object.hasOwn(r.answers, q.id)).length, texts: [], counts: Object.fromEntries(q.options.map((o) => [o.id, rows.filter((r) => [r.answers[q.id]].flat().includes(o.id)).length])) }));
const rate = (n, d) => d ? Math.round(n / d * 1000) / 10 : null;
const aggregate = (rows) => ({ total: rows.length, summary: summary(rows), metrics: configuration.profile.metrics.map((m) => { const denominator = rows.filter((r) => r.answers[m.question] && !m.exclude.includes(r.answers[m.question])).length; const count = rows.filter((r) => m.options.includes(r.answers[m.question])).length; return { ...m, count, denominator, rate: rate(count, denominator), error: !m.question || !m.options.length ? 'Koppel de vraag en antwoordopties in Analyse instellen.' : null }; }), comparisons: configuration.profile.comparisons.map((c) => ({ ...c, options: questions.find((q) => q.id === c.outcome)?.options || [], groups: (questions.find((q) => q.id === c.group)?.options || []).map((o) => { const subset = rows.filter((r) => [r.answers[c.group]].flat().includes(o.id)); const s = summary(subset).find((q) => q.id === c.outcome); return { ...o, total: subset.length, answered: s.answered, counts: s.counts, small: true }; }), error: !c.outcome || !c.group ? 'Koppel beide vragen in Analyse instellen.' : null })), themes: configuration.profile.themes.map((t) => ({ ...t, count: rows.filter((r) => r.themes.includes(t.id)).length })) });
api.defaults.adapter = async (config) => {
  if (flags.has('error')) throw new Error('De analyse is tijdelijk niet bereikbaar. Probeer het opnieuw.');
  const url = new URL(config.url, 'https://fixture.invalid');
  Object.entries(config.params || {}).forEach(([k, v]) => url.searchParams.set(k, v));
  const body = typeof config.data === 'string' ? JSON.parse(config.data) : config.data;
  const filters = body?.selection?.filters || (url.searchParams.get('view') ? configuration.profile.views.find((v) => v.id === url.searchParams.get('view'))?.filters || {} : JSON.parse(url.searchParams.get('filters') || '{}'));
  const search = body?.selection?.search || url.searchParams.get('search') || '';
  const theme = body?.selection?.theme || url.searchParams.get('theme') || '';
  const rows = replies.filter((r) => Object.entries(filters).every(([q, choices]) => choices.some((o) => [r.answers[q]].flat().includes(o))) && (!search || r.answers.note?.toLowerCase().includes(search.toLowerCase())) && (!theme || r.themes.includes(theme)));
  const version = Number(url.searchParams.get('version') || 100);
  const response = { sent: 5, completed: replies.length, rate: rate(replies.length, 5), invited_submissions: replies.length, public_submissions: 0 };
  const data = { ...aggregate(rows), version, configuration, selection: { filters, search, theme, text_question: url.searchParams.get('text_question') || '' }, source_revision: 'source1', source_count: replies.length, response, can_manage: !flags.has('readonly'), can_context: false };
  let result = data;
  if (url.pathname.endsWith('analysis-profiles')) { if (config.method === 'post') templates.push({ id: templates.length + 1, version: 1, profile: body.profile }); result = templates; }
  else if (url.pathname.endsWith('/analysis/labels')) { replies.find((r) => r.id === body.response_id).themes = body.themes; result = { saved: true }; }
  else if (url.pathname.endsWith('/analysis/texts')) result = { items: rows.filter((r) => r.answers.note).map((r) => ({ response_id: r.id, question: 'note', label: questions[2].label, text: r.answers.note, themes: r.themes, revision: 'labels', suggestions: configuration.profile.themes.filter((t) => !r.themes.includes(t.id) && t.keywords.some((word) => r.answers.note.includes(word))).map((t) => ({ theme: t.id, words: t.keywords })) })), total: rows.filter((r) => r.answers.note).length, page: 1, pages: 1 };
  else if (url.pathname.endsWith('/analysis/reports')) { if (config.method === 'post') reports.unshift({ ...body, id: reports.length + 1, created_at: '2026-11-15T14:00:00+01:00', author_name: 'Testbeheerder', profile: structuredClone(configuration.profile), aggregates: structuredClone(aggregate(rows)), response, calculation_version: 1, references: body.references.map((r) => ({ ...r, available: true })) }); result = reports; }
  else if (url.pathname.endsWith('/analysis/submissions')) result = { rows, page: 1, pages: 1 };
  else if (url.pathname.endsWith('/analysis/source')) result = { row: replies.find((r) => r.id === Number(url.searchParams.get('response_id'))), questions };
  else if (url.pathname.endsWith('/export')) result = { filename: 'fictieve-enquete.csv', csv: 'Inzending\n' + rows.map((r) => r.id).join('\n') };
  else if (config.method === 'put' && url.pathname.endsWith('/analysis')) { configuration = { revision: crypto.randomUUID(), profile: body.profile }; result = configuration; }
  return { data: structuredClone(result), status: 200, statusText: 'OK', headers: {}, config };
};
createRoot(document.getElementById('root')).render(<BrowserRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><div className="dashboard-preview-toolbar">Lokale preview · fictieve accommodatie-enquête <button className="ml-3 underline" onClick={() => replies.push({ id: replies.length + 1, answers: { role: ['parent'], rating: 'yes', note: 'Nieuwe fictieve reactie na het openen van de analyse.' }, themes: [] })}>Fictieve inzending toevoegen</button></div><main className="mx-auto max-w-7xl space-y-5 p-4 sm:p-8"><h1 className="text-2xl font-semibold">Samen een betere accommodatie</h1><SurveyAnalysis survey={{ id: 99, current_version: 100, versions: [{ id: 100, date: '2026-11-15 12:00:00' }, { id: 101, date: '2026-11-16 12:00:00' }] }} /></main></QueryClientProvider></BrowserRouter>);
