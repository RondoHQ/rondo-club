import { useState } from 'react';

import { percent, errorText } from './analysisUtils';
export function ErrorMessage({ error }) {
  return error ? <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">{errorText(error)}</p> : null;
}
export function Field({ label, children }) {
  return <label className="block min-w-0 space-y-1 text-sm font-medium"><span>{label}</span>{children}</label>;
}
export function QuestionSelect({ label, value, questions, onChange, text = false }) {
  const choices = questions.filter((q) => text ? q.type === 'text' : q.type !== 'text');
  return <Field label={label}><select className="input w-full" value={value} onChange={(e) => onChange(e.target.value)}><option value="">Selecteer een vraag</option>{value && !choices.some((q) => q.id === value) && <option value={value}>Ontbrekende vraag: {value}</option>}{choices.map((q) => <option key={q.id} value={q.id}>{q.label}</option>)}</select></Field>;
}
export function Options({ label, question, value, onChange }) {
  return <fieldset className="min-w-0 space-y-2"><legend className="mb-2 text-sm font-medium">{label}</legend>{!question && <p className="text-sm text-gray-500 dark:text-gray-400">Koppel eerst een vraag.</p>}{question?.options.map((o) => <label key={o.id} className="flex items-start gap-2 text-sm"><input type="checkbox" className="mt-0.5" checked={value.includes(o.id)} onChange={(e) => onChange(e.target.checked ? [...value, o.id] : value.filter((id) => id !== o.id))} /><span>{o.label}</span></label>)}{value.some((id) => !question?.options.some((o) => o.id === id)) && <p className="text-sm text-red-700 dark:text-red-300">Er ontbreken antwoordopties. Selecteer de vraag opnieuw om de koppeling te herstellen.</p>}</fieldset>;
}
export function Filters({ questions, value, onChange }) {
  const [adding, setAdding] = useState('');
  return <div className="space-y-4"><p className="text-sm text-gray-500 dark:text-gray-400">Alle gekozen vragen moeten overeenkomen. Binnen een vraag is één gekozen optie voldoende.</p>
    {Object.entries(value).map(([id, options]) => <div key={id} className="flex items-start justify-between gap-3 border-t pt-3 dark:border-gray-700"><Options label={questions.find((q) => q.id === id)?.label || `Ontbrekende vraag: ${id}`} question={questions.find((q) => q.id === id)} value={options} onChange={(next) => { const copy = { ...value }; if (next.length) copy[id] = next; else delete copy[id]; onChange(copy); }} /><button type="button" className="btn-tertiary shrink-0" onClick={() => { const copy = { ...value }; delete copy[id]; onChange(copy); }}>Wissen</button></div>)}
    <div className="flex flex-wrap items-end gap-2"><div className="min-w-0 flex-1"><QuestionSelect label="Antwoordfilter toevoegen" questions={questions.filter((q) => !Object.hasOwn(value, q.id))} value={adding} onChange={setAdding} /></div><button type="button" className="btn-secondary" disabled={!questions.some((q) => q.id === adding)} onClick={() => { onChange({ ...value, [adding]: [questions.find((q) => q.id === adding).options[0].id] }); setAdding(''); }}>Toevoegen</button></div>
  </div>;
}
export function QuestionResults({ summary, embedded = false }) {
  return <div className="space-y-5">{summary.map((q) => <section className={embedded ? "border-t pt-4 dark:border-gray-700" : "card p-5"} key={q.id}><h3 className="font-semibold">{q.label}</h3><p className="my-2 text-sm text-gray-500 dark:text-gray-400">{q.section} · {q.answered} antwoorden{q.answered < 10 ? ' · Kleine groep' : ''}</p>{q.type === 'text' ? <p className="text-sm">Lees en label deze antwoorden bij Open antwoorden.</p> : <div className="space-y-3">{q.options.map((o) => { const count = q.counts[o.id] || 0; const rate = q.answered ? 100 * count / q.answered : null; return <div key={o.id}><div className="flex justify-between gap-3 text-sm"><span>{o.label}</span><span className="shrink-0 tabular-nums">{count} / {q.answered} · {percent(rate)}</span></div><div className="mt-1 h-2 rounded bg-gray-100 dark:bg-gray-700"><div className="h-2 rounded bg-bright-cobalt" style={{ width: `${rate || 0}%` }} /></div></div>; })}</div>}</section>)}</div>;
}
export function Comparisons({ comparisons, embedded = false }) {
  return <div className="space-y-5">{comparisons.map((c) => <section key={c.id} className={embedded ? "border-t pt-4 dark:border-gray-700" : "card p-5"}><h3 className="font-semibold">{c.label}</h3>{c.error ? <p className="mt-2 text-sm text-amber-800 dark:text-amber-200">{c.error}</p> : <><p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Noemer: antwoorden op de uitkomstvraag binnen de groep. Groepen kunnen overlappen; tel ze niet op.</p><div className="mt-4 overflow-auto" role="region" aria-label={c.label} tabIndex={0}><table className="w-full text-left text-sm"><thead><tr><th className="min-w-40 p-2">Groep</th><th className="min-w-36 p-2">Antwoorden / inzendingen</th>{c.options.map((o) => <th key={o.id} className="min-w-32 p-2">{o.label}</th>)}</tr></thead><tbody>{c.groups.map((g) => <tr key={g.id} className="border-t dark:border-gray-700"><th scope="row" className="p-2 font-medium">{g.label}{g.small && <span className="block text-xs font-normal text-gray-500 dark:text-gray-400">Kleine groep</span>}</th><td className="p-2 tabular-nums">{g.answered} / {g.total}</td>{c.options.map((o) => <td key={o.id} className="whitespace-nowrap p-2 tabular-nums">{g.counts[o.id] || 0} · {percent(g.answered ? 100 * (g.counts[o.id] || 0) / g.answered : null)}</td>)}</tr>)}</tbody></table></div></>}</section>)}</div>;
}
export function Metrics({ metrics }) {
  return <dl className="divide-y dark:divide-gray-700">{metrics.map((m) => <div key={m.id} className="py-3"><div className="flex flex-wrap justify-between gap-2"><dt className="font-medium">{m.label}</dt><dd className="tabular-nums">{m.error ? 'Koppeling nodig' : `${m.count} / ${m.denominator} · ${percent(m.rate)}`}</dd></div>{m.error ? <p className="mt-1 text-sm text-amber-800 dark:text-amber-200">{m.error}</p> : <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{m.denominator < 10 ? 'Kleine groep. ' : ''}{m.exclude.length ? 'Inzendingen met een uitgesloten optie zijn niet in de noemer opgenomen.' : 'Noemer: inzendingen met een antwoord op deze vraag.'}</p>}</div>)}</dl>;
}
