import { Fragment, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency } from '@/utils/formatters';

const panel = 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 sm:p-5';
const muted = 'text-sm text-gray-500 dark:text-gray-400';
const cell = 'py-3 px-3 border-b border-gray-100 dark:border-gray-700';
const money = value => value === null || value === undefined ? '—' : formatCurrency(value, 2);
const decimal = value => new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 4 }).format(value);
const dateLabel = value => value ? new Intl.DateTimeFormat('nl-NL', { dateStyle: 'short' }).format(new Date(`${value}T12:00:00`)) : '—';
const today = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
const errorText = mutation => mutation.error?.response?.data?.message || 'Opslaan mislukt. Je invoer blijft behouden; probeer opnieuw.';

function PriceHistory({ article }) {
  return <details className="text-sm"><summary className="cursor-pointer text-cyan-800 dark:text-cyan-200">{article.description} · {article.article}</summary><div className="overflow-x-auto mt-3"><table className="w-full text-sm"><thead><tr>{['Factuurdatum', 'Factuur', 'Inkoop excl. btw', 'Eenheid'].map(label => <th key={label} className={`${cell} text-left font-medium`}>{label}</th>)}</tr></thead><tbody>{article.history.map(row => <tr key={row.invoice_id}><td className={cell}>{dateLabel(row.date)}</td><td className={cell}>{row.invoice_number}</td><td className={`${cell} tabular-nums`}>{row.price === null ? 'Aantal controleren' : formatCurrency(row.price, 4)}</td><td className={cell}>{row.unit || 'Eenheid controleren'}</td></tr>)}</tbody></table></div></details>;
}

function ProductEditor({ product, data, date, onClose, onSaved }) {
  const [ingredients, setIngredients] = useState(() => product.ingredients.map(row => ({ ...row, quantity: row.quantity ?? '' })));
  const [status, setStatus] = useState(product.cost_status);
  const [note, setNote] = useState(product.cost_note || '');
  const [name, setName] = useState(product.product_name);
  const [twelveId, setTwelveId] = useState(product.twelve_id);
  const [active, setActive] = useState(Boolean(product.active));
  const latestPrice = product.sale_price || [...product.sale_prices].sort((a, b) => b.effective_date.localeCompare(a.effective_date))[0];
  const [price, setPrice] = useState(latestPrice?.amount ?? '');
  const [vat, setVat] = useState(latestPrice?.vat_rate ?? 9);
  const [effective, setEffective] = useState(latestPrice?.effective_date || date);
  const save = useMutation({ mutationFn: prmApi.saveKantineProduct, onSuccess: onSaved });
  const updateIngredient = (index, patch) => setIngredients(rows => rows.map((row, i) => i === index ? { ...row, ...patch } : row));
  const submit = event => {
    event.preventDefault();
    const unchangedPrice = latestPrice && Number(price) === latestPrice.amount && Number(vat) === latestPrice.vat_rate && effective === latestPrice.effective_date;
    const prices = unchangedPrice ? product.sale_prices : [...product.sale_prices.filter(row => row.effective_date !== effective), { effective_date: effective, amount: Number(price), vat_rate: Number(vat), source: 'Handmatig in Rondo' }];
    save.mutate({ id: product.id, revision: product.revision, twelve_id: twelveId, product_name: name, active, cost_status: status, cost_note: note, ingredients: ingredients.map(row => ({ ...row, quantity: row.quantity === '' ? null : Number(row.quantity) })), sale_prices: prices });
  };

  return <div className={`${panel} space-y-5`}>
    <div className="flex items-start justify-between gap-4"><div><h3 className="text-lg font-semibold">{product.id ? product.product_name : 'Product toevoegen'}</h3><p className={muted}>Kostprijs en verkoopprijs per consumptie</p></div><button type="button" className="btn-secondary" onClick={onClose}>Sluiten</button></div>
    {data.can_write ? <form onSubmit={submit} className="space-y-5">
      {!product.id && <div className="grid sm:grid-cols-2 gap-4"><label className="text-sm">Productnaam<input className="input w-full mt-1" required value={name} onChange={event => setName(event.target.value)} /></label><label className="text-sm">Twelve-product-ID<input className="input w-full mt-1" required pattern="[0-9]+" value={twelveId} onChange={event => setTwelveId(event.target.value)} /></label></div>}
      <div><h4 className="font-medium">Gekoppelde inkoopartikelen</h4><p className={`${muted} mt-1`}>Gebruik het aantal stuks, liters of kilo’s per consumptie. Voeg ook saus, broodjes en verpakking toe als je die wilt meerekenen.</p></div>
      <div className="space-y-3">{ingredients.map((ingredient, index) => <div key={index} className="grid sm:grid-cols-[minmax(0,1fr)_130px_100px_auto] gap-3 items-end">
        <label className="text-sm">Inkoopartikel<select className="input w-full mt-1" required value={ingredient.article} onChange={event => { const article = data.articles.find(row => row.article === event.target.value); updateIngredient(index, { article: event.target.value, unit: article?.latest?.unit || ingredient.unit || 'stuk' }); }}><option value="">Kies artikel</option>{data.articles.map(article => <option key={article.article} value={article.article}>{article.description} ({article.article})</option>)}</select></label>
        <label className="text-sm">Hoeveelheid<input type="number" step="any" min="0.000001" className="input w-full mt-1" value={ingredient.quantity} onChange={event => updateIngredient(index, { quantity: event.target.value })} /></label>
        <label className="text-sm">Eenheid<select className="input w-full mt-1" value={ingredient.unit} onChange={event => updateIngredient(index, { unit: event.target.value })}>{data.units.map(unit => <option key={unit}>{unit}</option>)}</select></label>
        <button type="button" className="btn-secondary" aria-label={`Verwijder ingrediënt ${index + 1}`} onClick={() => setIngredients(rows => rows.filter((_, i) => i !== index))}>Verwijderen</button>
      </div>)}</div>
      <button type="button" className="btn-secondary" onClick={() => setIngredients(rows => [...rows, { article: '', quantity: '', unit: 'stuk' }])}>Artikel toevoegen</button>
      <div className="grid sm:grid-cols-2 gap-4"><label className="text-sm">Controle van de koppeling<select className="input w-full mt-1" value={status} onChange={event => setStatus(event.target.value)}>{Object.entries(data.statuses).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label><label className="text-sm">Toelichting<textarea className="input w-full mt-1" rows="2" value={note} onChange={event => setNote(event.target.value)} /></label></div>
      <div className="grid sm:grid-cols-3 gap-4"><label className="text-sm">Verkoopprijs incl. btw<input type="number" min="0" step="0.01" className="input w-full mt-1" required value={price} onChange={event => setPrice(event.target.value)} /></label><label className="text-sm">Verkoop-btw<select className="input w-full mt-1" value={vat} onChange={event => setVat(Number(event.target.value))}>{[0, 9, 21].map(rate => <option key={rate} value={rate}>{rate}%</option>)}</select></label><label className="text-sm">Verkoopprijs vanaf<input type="date" className="input w-full mt-1" required value={effective} onChange={event => setEffective(event.target.value)} /></label></div>
      <p className={muted}>Een prijswijziging geldt in Rondo. Pas de kassaprijs ook in Twelve aan. Kies een nieuwe ingangsdatum om de eerdere prijs te bewaren.</p>
      <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={active} onChange={event => setActive(event.target.checked)} />Actief product</label>
      {save.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{errorText(save)}</p>}
      <div className="flex flex-wrap gap-3"><button className="btn-primary" disabled={save.isPending}>{save.isPending ? 'Opslaan…' : 'Kostprijs opslaan'}</button><button type="button" className="btn-secondary" disabled={save.isPending} onClick={onClose}>Annuleren</button></div>
    </form> : <p className={muted}>{product.cost_note || product.calculation_note}</p>}
    {product.cost_sources.length > 0 && <div><h4 className="font-medium mb-2">Berekening op {dateLabel(date)}</h4><ul className="space-y-2 text-sm">{product.cost_sources.map((row, i) => <li key={i}>{row.description}: {decimal(row.used_quantity)} {row.unit} × {row.price === null ? 'Aantal controleren' : formatCurrency(row.price, 4)} = {money(row.used_quantity * row.price)} · factuur {row.invoice_number} ({dateLabel(row.date)})</li>)}</ul></div>}
    <div className="space-y-3">{data.articles.filter(article => ingredients.some(row => row.article === article.article)).map(article => <PriceHistory key={article.article} article={article} />)}</div>
    <details className="text-sm"><summary className="cursor-pointer text-cyan-800 dark:text-cyan-200">Verkoopprijshistorie</summary><ul className="mt-2 space-y-2">{product.sale_prices.map(row => <li key={row.effective_date}>{dateLabel(row.effective_date)}: {money(row.amount)} incl. {row.vat_rate}% btw · {row.source}</li>)}</ul></details>
  </div>;
}

function PurchasePreview({ preview, units, onClose, onSaved }) {
  const [lines, setLines] = useState(() => preview.invoice.lines.map(line => ({ ...line, quantity: line.quantity ?? '' })));
  const save = useMutation({ mutationFn: prmApi.saveKantinePurchase, onSuccess: onSaved });
  const update = (index, patch) => setLines(rows => rows.map((row, i) => i === index ? { ...row, ...patch } : row));
  return <form className={`${panel} space-y-4`} onSubmit={event => { event.preventDefault(); save.mutate({ token: preview.token, lines: lines.map(line => ({ ...line, quantity: line.quantity === '' ? null : Number(line.quantity), units_per_pack: line.packs > 0 && line.quantity !== '' ? Number(line.quantity) / line.packs : line.units_per_pack })) }); }}>
    <div><h3 className="text-lg font-semibold">Factuur {preview.invoice.invoice_number} controleren</h3><p className={muted}>{preview.invoice.supplier} · {dateLabel(preview.invoice.invoice_date)} · totaal {money(preview.invoice.total_amount)}</p></div>
    <p className="text-sm">De bedragen sluiten aan op het factuurtotaal. Controleer de totale aantallen en eenheden; een lege hoeveelheid blijft buiten de kostprijsberekening.</p>
    <div className="overflow-x-auto max-h-96"><table className="w-full text-sm"><thead><tr>{['Artikel', 'Bedrag excl. btw', 'Totaal aantal eenheden', 'Eenheid'].map(label => <th key={label} className={`${cell} text-left`}>{label}</th>)}</tr></thead><tbody>{lines.map((line, index) => <tr key={index}><td className={cell}><span className="block min-w-48">{line.description}</span><span className={muted}>{line.article} · {line.packs} × {line.pack_content}</span></td><td className={`${cell} tabular-nums`}>{money(line.amount)}</td><td className={cell}><input type="number" step="any" className="input w-32" aria-label={`Totaal aantal ${line.description}`} value={line.quantity} onChange={event => update(index, { quantity: event.target.value })} /></td><td className={cell}><select className="input" aria-label={`Eenheid ${line.description}`} value={line.unit} onChange={event => update(index, { unit: event.target.value })}><option value="">Nog bepalen</option>{units.map(unit => <option key={unit}>{unit}</option>)}</select></td></tr>)}</tbody></table></div>
    {save.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{errorText(save)}</p>}
    <div className="flex flex-wrap gap-3"><button className="btn-primary" disabled={save.isPending}>{save.isPending ? 'Opslaan…' : 'Factuur en kostprijzen opslaan'}</button><button type="button" className="btn-secondary" disabled={save.isPending} onClick={onClose}>Annuleren</button></div>
  </form>;
}

function ManualPurchase({ data, onClose, onSaved }) {
  const [article, setArticle] = useState('');
  const [description, setDescription] = useState('');
  const [supplier, setSupplier] = useState('Van Altena');
  const [number, setNumber] = useState('');
  const [date, setDate] = useState(today);
  const [unit, setUnit] = useState('stuk');
  const [price, setPrice] = useState('');
  const [vat, setVat] = useState(9);
  const save = useMutation({ mutationFn: prmApi.saveKantinePurchase, onSuccess: onSaved });
  return <form className={`${panel} space-y-4`} onSubmit={event => { event.preventDefault(); const amount = Number(price); const tax = Math.round(amount * vat) / 100; save.mutate({ invoice_number: number, supplier, invoice_date: date, source_file: '', vat_amount: tax, total_amount: Math.round((amount + tax) * 100) / 100, lines: [{ article, description, amount, deposit: 0, vat_rate: vat, packs: 1, pack_content: '1', quantity: 1, units_per_pack: 1, unit, category: 'Voeding', note: 'Handmatig vastgelegde inkoopprijs per eenheid' }] }); }}>
    <div><h3 className="text-lg font-semibold">Inkoopprijs vastleggen</h3><p className={muted}>Bewaar een prijs per stuk, liter of kilo met een factuurreferentie.</p></div>
    <div className="grid sm:grid-cols-2 gap-4"><label className="text-sm">Artikelnummer<input className="input w-full mt-1" list="kantine-articles" required value={article} onChange={event => { setArticle(event.target.value); const found = data.articles.find(row => row.article === event.target.value); if (found) { setDescription(found.description); setUnit(found.latest?.unit || 'stuk'); } }} /><datalist id="kantine-articles">{data.articles.map(row => <option key={row.article} value={row.article}>{row.description}</option>)}</datalist></label><label className="text-sm">Omschrijving<input className="input w-full mt-1" required value={description} onChange={event => setDescription(event.target.value)} /></label><label className="text-sm">Leverancier<input className="input w-full mt-1" required value={supplier} onChange={event => setSupplier(event.target.value)} /></label><label className="text-sm">Factuurreferentie<input className="input w-full mt-1" required value={number} onChange={event => setNumber(event.target.value)} placeholder="Bijvoorbeeld factuurnummer-regelnummer" /></label><label className="text-sm">Factuurdatum<input type="date" className="input w-full mt-1" required value={date} onChange={event => setDate(event.target.value)} /></label><label className="text-sm">Inkoopprijs excl. btw<input type="number" min="0" step="any" className="input w-full mt-1" required value={price} onChange={event => setPrice(event.target.value)} /></label><label className="text-sm">Eenheid<select className="input w-full mt-1" value={unit} onChange={event => setUnit(event.target.value)}>{data.units.map(value => <option key={value}>{value}</option>)}</select></label><label className="text-sm">Inkoop-btw<select className="input w-full mt-1" value={vat} onChange={event => setVat(Number(event.target.value))}>{[0, 9, 21].map(rate => <option key={rate} value={rate}>{rate}%</option>)}</select></label></div>
    {save.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{errorText(save)}</p>}
    <div className="flex flex-wrap gap-3"><button className="btn-primary" disabled={save.isPending}>{save.isPending ? 'Opslaan…' : 'Inkoopprijs opslaan'}</button><button type="button" className="btn-secondary" disabled={save.isPending} onClick={onClose}>Annuleren</button></div>
  </form>;
}

export default function KantineMargins() {
  const [date, setDate] = useState(today);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');
  const [sort, setSort] = useState('name');
  const [view, setView] = useState('products');
  const [editor, setEditor] = useState(null);
  const [preview, setPreview] = useState(null);
  const [manual, setManual] = useState(false);
  const [notice, setNotice] = useState('');
  const client = useQueryClient();
  const query = useQuery({ queryKey: ['twelve', 'margins', date], queryFn: async () => (await prmApi.getTwelve('margins', { date })).data });
  const upload = useMutation({ mutationFn: prmApi.previewKantinePurchase, onSuccess: response => { setPreview(response.data); setEditor(null); setManual(false); setNotice(''); } });
  const saved = async response => { setEditor(null); setPreview(null); setManual(false); setNotice(response.data?.unchanged ? 'Deze gegevens waren al opgeslagen.' : 'Opgeslagen. De marges zijn bijgewerkt.'); await client.invalidateQueries({ queryKey: ['twelve', 'margins'] }); };
  const data = query.data;
  const products = (data?.products ?? []).filter(product => product.active);
  const computable = products.filter(product => product.margin !== null).length;
  const rows = (data?.products ?? []).filter(product => (filter === 'inactive' ? !product.active : product.active) && product.product_name.toLocaleLowerCase('nl').includes(search.toLocaleLowerCase('nl')) && (filter !== 'check' || product.margin === null && product.cost_status !== 'excluded') && (filter !== 'ready' || product.margin !== null));
  if (sort === 'margin') rows.sort((a, b) => (a.margin_percentage ?? Infinity) - (b.margin_percentage ?? Infinity));

  return <div className="space-y-5">
    <div className={`${panel} space-y-4`}><div className="flex flex-wrap justify-between gap-4"><div><h2 className="text-lg font-semibold">Productmarges</h2><p className={`${muted} mt-1`}>Brutomarge per consumptie op basis van de laatst bekende inkoop- en verkoopprijs op de peildatum.</p></div><label className="text-sm">Peildatum<input type="date" className="input block mt-1" required value={date} onChange={event => { if (event.target.value) { setDate(event.target.value); setEditor(null); } }} /></label></div><p className={muted}>Berekening: verkoopprijs excl. btw − gekoppelde inkoopkosten excl. btw. Btw volgens de productinstelling; geen berekening met het 13%-forfait. Statiegeld, arbeid, energie, tapverlies en verspilling zijn niet meegerekend. Basisinkoop bevat alleen de gekoppelde hoofdingrediënten.</p>{data && <p className="text-sm"><strong>{computable}</strong> van {products.length} actieve producten hebben een berekende marge · {data.invoices.length} inkoopfacturen</p>}</div>
    <div className="flex flex-wrap gap-3" aria-label="Margeweergave">{[['products', 'Marges per product'], ['purchases', 'Inkoopfacturen'], ['articles', 'Inkoopprijshistorie']].map(([key, label]) => <button key={key} type="button" aria-pressed={view === key} className={view === key ? 'btn-primary' : 'btn-secondary'} onClick={() => { setView(key); setEditor(null); }}>{label}</button>)}</div>
    {notice && <p role="status" className="text-sm text-cyan-800 dark:text-cyan-200">{notice}</p>}
    {upload.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{errorText(upload)}</p>}
    {query.isPending ? <p role="status">Kostprijzen laden…</p> : query.isError ? <div className={panel}><p role="alert">Kostprijzen konden niet worden geladen.</p><button type="button" className="btn-secondary mt-3" onClick={() => query.refetch()}>Opnieuw proberen</button></div> : <>
      {data.can_write && <div className="flex flex-wrap gap-3"><label className={`btn-secondary cursor-pointer ${upload.isPending ? 'opacity-60' : ''}`}>{upload.isPending ? 'Factuur lezen…' : 'Factuur uploaden'}<input type="file" accept="application/pdf,.pdf" className="sr-only" disabled={upload.isPending} onChange={event => { const file = event.target.files?.[0]; if (file) upload.mutate(file); event.target.value = ''; }} /></label><button type="button" className="btn-secondary" onClick={() => { setManual(true); setEditor(null); setPreview(null); }}>Inkoopprijs vastleggen</button>{view === 'products' && <button type="button" className="btn-secondary" onClick={() => { setEditor({ id: 0, product_name: '', twelve_id: '', active: true, cost_status: 'missing', cost_note: '', ingredients: [], sale_prices: [], cost_sources: [] }); setManual(false); setPreview(null); }}>Product toevoegen</button>}</div>}
      {preview && <PurchasePreview key={preview.token} preview={preview} units={data.units} onClose={() => setPreview(null)} onSaved={saved} />}
      {manual && <ManualPurchase data={data} onClose={() => setManual(false)} onSaved={saved} />}
      {editor && <ProductEditor key={`${editor.id}-${editor.revision || ''}`} product={editor} data={data} date={date} onClose={() => setEditor(null)} onSaved={saved} />}
      {view === 'products' ? <div className={`${panel} space-y-4`}><div className="flex flex-wrap gap-4"><label className="text-sm">Zoek product<input type="search" className="input block mt-1" value={search} onChange={event => setSearch(event.target.value)} /></label><label className="text-sm">Status<select className="input block mt-1" value={filter} onChange={event => setFilter(event.target.value)}><option value="all">Alle actieve producten</option><option value="ready">Marge berekend</option><option value="check">Nog aanvullen of controleren</option><option value="inactive">Inactieve producten</option></select></label><label className="text-sm">Sorteren<select className="input block mt-1" value={sort} onChange={event => setSort(event.target.value)}><option value="name">Productnaam</option><option value="margin">Laagste marge eerst</option></select></label></div><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr>{['Product', 'Verkoop incl. btw', 'Inkoop excl. btw', 'Marge excl. btw', 'Marge %', 'Controle', ''].map((label, i) => <th key={i} className={`${cell} whitespace-nowrap font-medium ${i > 0 && i < 5 ? 'text-right' : 'text-left'}`}>{label}</th>)}</tr></thead><tbody>{rows.map(product => <Fragment key={product.id}><tr><td className={`${cell} min-w-44`}><span className="font-medium">{product.product_name}</span>{product.sale_price && <span className={`block ${muted}`}>{product.sale_price.vat_rate}% btw · prijs vanaf {dateLabel(product.sale_price.effective_date)}</span>}</td><td className={`${cell} text-right tabular-nums whitespace-nowrap`}>{money(product.sale_price?.amount)}</td><td className={`${cell} text-right tabular-nums whitespace-nowrap`}>{money(product.cost)}</td><td className={`${cell} text-right tabular-nums whitespace-nowrap ${product.margin !== null && product.margin < 0 ? 'text-red-700 dark:text-red-300' : ''}`}>{money(product.margin)}</td><td className={`${cell} text-right tabular-nums whitespace-nowrap font-medium`}>{product.margin_percentage === null ? '—' : `${decimal(product.margin_percentage)}%`}</td><td className={`${cell} min-w-48`}><span>{product.calculation_note || data.statuses[product.cost_status]}</span>{product.cost_note && <span className={`block ${muted}`}>{product.cost_note}</span>}</td><td className={cell}><button type="button" className="text-cyan-800 dark:text-cyan-200 font-medium whitespace-nowrap" aria-label={`${data.can_write ? 'Kostprijs' : 'Details'} ${product.product_name}`} onClick={() => { setEditor(product); setManual(false); setPreview(null); }}>{data.can_write ? 'Kostprijs' : 'Details'}</button></td></tr></Fragment>)}</tbody></table></div>{!rows.length && <p className={muted}>{data.products.length ? 'Geen producten gevonden voor dit filter.' : 'Nog geen verkoopproducten. Voeg een Twelve-product toe en koppel de bijbehorende inkoopartikelen.'}</p>}</div> : view === 'purchases' ? <div className={panel}><h3 className="font-semibold mb-3">Inkoopfacturen</h3><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr>{['Datum', 'Leverancier', 'Factuur', 'Regels', 'Totaal incl. btw', 'Bron'].map(label => <th key={label} className={`${cell} text-left`}>{label}</th>)}</tr></thead><tbody>{data.invoices.map(invoice => <tr key={invoice.id}><td className={`${cell} whitespace-nowrap`}>{dateLabel(invoice.invoice_date)}</td><td className={cell}>{invoice.supplier}</td><td className={cell}>{invoice.invoice_number}</td><td className={cell}>{invoice.line_count}</td><td className={`${cell} whitespace-nowrap tabular-nums`}>{money(invoice.total_amount)}</td><td className={cell}>{invoice.has_pdf ? <a className="text-cyan-800 dark:text-cyan-200" href={prmApi.getKantinePurchasePdfUrl(invoice.id)} target="_blank" rel="noopener noreferrer">Bronfactuur openen</a> : 'Handmatig'}</td></tr>)}</tbody></table></div>{!data.invoices.length && <p className={muted}>Nog geen inkoopfacturen. Upload een Van Altena-PDF of leg een inkoopprijs vast.</p>}</div> : <div className={`${panel} space-y-4`}><h3 className="font-semibold">Inkoopprijshistorie</h3>{data.articles.map(article => <PriceHistory key={article.article} article={article} />)}{!data.articles.length && <p className={muted}>Nog geen inkoopprijzen met een gecontroleerde hoeveelheid en eenheid.</p>}</div>}
    </>}
  </div>;
}
