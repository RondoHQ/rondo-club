import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const INPUT = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-electric-cyan';
const INITIAL_SCENARIO = { type: 'member', age: 'adult', recipient: 'self', account: 'activate', vog: 'none', clothing: 'no', returning: 'no' };
const errorMessage = (error) => error?.response?.data?.message || 'Dit is niet gelukt. Probeer opnieuw.';

function Choice({ name, label, value, options, onChange, disabled = false }) {
  return (
    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300" htmlFor={`preview-${name}`}>
      {label}
      <select id={`preview-${name}`} className={`${INPUT} mt-1`} value={value} onChange={(event) => onChange(name, event.target.value)} disabled={disabled}>
        {options.map(([key, text]) => <option key={key} value={key}>{text}</option>)}
      </select>
    </label>
  );
}

export default function OnboardingTemplates() {
  const { data, isPending, error, refetch } = useQuery({ queryKey: ['onboarding-templates'], queryFn: async () => (await prmApi.getOnboardingTemplates()).data });
  if (isPending) return <p role="status">Mailblokken laden…</p>;
  if (error) return <div role="alert"><p>{errorMessage(error)}</p><button className="btn-secondary mt-2" onClick={() => refetch()}>Opnieuw laden</button></div>;
  // Keep unsaved work intact if the query refreshes in the background.
  return <TemplateEditor initial={data} />;
}

function TemplateEditor({ initial }) {
  const [settings, setSettings] = useState(initial);
  const [blocks, setBlocks] = useState(initial.blocks);
  const [scenario, setScenario] = useState(INITIAL_SCENARIO);
  const [group, setGroup] = useState('Welkom');
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const definitions = settings.definitions;
  const groups = [...new Set(Object.values(definitions).map((item) => item.group))];
  const dirty = JSON.stringify(blocks) !== JSON.stringify(settings.blocks);
  const disabled = busy !== '';
  const volunteer = scenario.type !== 'member';
  const missingMemberBlocks = ['member_fees', 'member_clothing', 'member_training', 'member_volunteering'].filter((id) => !blocks[id].trim()).map((id) => definitions[id].label);

  useEffect(() => {
    if (!dirty) return undefined;
    const warn = (event) => { event.preventDefault(); event.returnValue = ''; };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  const changeBlock = (id, value) => {
    setBlocks((previous) => ({ ...previous, [id]: value }));
    setPreview(null);
    setSaved(false);
  };
  const changeScenario = (name, value) => {
    setScenario((previous) => ({ ...previous, [name]: value, ...(name === 'age' && value === 'adult' ? { recipient: 'self' } : {}) }));
    setPreview(null);
  };
  const save = async () => {
    setBusy('save'); setError(''); setSaved(false);
    try {
      const { data } = await prmApi.updateOnboardingTemplates({ blocks, revision: settings.revision });
      setSettings(data); setBlocks(data.blocks); setPreview(null); setSaved(true);
    } catch (failure) { setError(errorMessage(failure)); }
    finally { setBusy(''); }
  };
  const showPreview = async () => {
    setBusy('preview'); setError(''); setPreview(null);
    try {
      const { data } = await prmApi.previewOnboardingTemplates({ blocks, scenario });
      setPreview(data);
    } catch (failure) { setError(errorMessage(failure)); }
    finally { setBusy(''); }
  };

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 p-4">
        <h3 className="font-semibold text-blue-950 dark:text-blue-100">Welkomstmailblokken voorbereiden</h3>
        <p className="mt-1 text-sm text-blue-900 dark:text-blue-200">Bewerk de teksten en bekijk de volledige mail met verzonnen voorbeeldgegevens. Opslaan bewaart een concept voor automatische onboarding. Automatische verzending staat uit.</p>
        <p className="mt-2 text-sm text-blue-900 dark:text-blue-200">De bestaande handmatige mails blijven actief. Controleer de clubinformatie vóór het latere overschakelen.</p>
        {missingMemberBlocks.length > 0 && <p className="mt-2 text-sm text-blue-900 dark:text-blue-200">Nog in te vullen of bewust leeg te laten: {missingMemberBlocks.join(', ')}.</p>}
      </div>

      <details className="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <summary className="cursor-pointer font-medium text-gray-900 dark:text-gray-100">Bestaande mailteksten bekijken en overnemen</summary>
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">Gebruik deze teksten als bron voor de losse blokken. Vrij geschreven tekst en HTML worden niet automatisch opgesplitst.</p>
        <div className="mt-4 grid gap-4 lg:grid-cols-2">
          {Object.entries(settings.legacy).map(([type, template]) => (
            <label key={type} className="block text-sm font-medium text-gray-700 dark:text-gray-300" htmlFor={`legacy-${type}`}>
              {type === 'lid' ? 'Bestaande ledenmail' : 'Bestaande vrijwilligersmail'}
              <span className="my-2 block font-normal">Onderwerp: {template.subject}</span>
              <textarea id={`legacy-${type}`} readOnly value={template.body} rows={10} className={INPUT} />
            </label>
          ))}
        </div>
      </details>

      {error && <p role="alert" className="text-red-700 dark:text-red-300">{error}</p>}
      <div className="grid gap-6 xl:grid-cols-2">
        <section aria-label="Mailteksten bewerken" className="space-y-4 min-w-0">
          <label htmlFor="mailblock-group" className="block font-medium text-gray-900 dark:text-gray-100">Onderdeel bewerken</label>
          <select id="mailblock-group" className={INPUT} value={group} onChange={(event) => setGroup(event.target.value)}>
            {groups.map((name) => <option key={name}>{name}</option>)}
          </select>
          <p className="text-sm text-gray-600 dark:text-gray-300">Gebruik gewone tekst met alinea’s. Beschikbare invulvelden: {settings.variables.map((name) => `{${name}}`).join(', ')}. Links en e-mailadressen in de tekst worden klikbaar.</p>
          {Object.entries(definitions).filter(([, definition]) => definition.group === group).map(([id, definition]) => (
            <div key={id}>
              <label htmlFor={`block-${id}`} className="block text-sm font-medium text-gray-900 dark:text-gray-100">{definition.label}</label>
              <p id={`condition-${id}`} className="mt-1 mb-2 text-xs text-gray-600 dark:text-gray-300">{definition.condition}</p>
              {definition.single_line ? (
                <input id={`block-${id}`} aria-describedby={`condition-${id}`} className={INPUT} value={blocks[id]} onChange={(event) => changeBlock(id, event.target.value)} maxLength={300} disabled={disabled} />
              ) : (
                <textarea id={`block-${id}`} aria-describedby={`condition-${id}`} className={INPUT} rows={4} value={blocks[id]} onChange={(event) => changeBlock(id, event.target.value)} placeholder={definition.optional ? 'Neem hier de relevante tekst uit de bestaande mail over.' : ''} disabled={disabled} />
              )}
            </div>
          ))}
          <div className="flex flex-wrap items-center gap-3">
            <button className="btn-primary" disabled={disabled || !dirty} onClick={save}>{busy === 'save' ? 'Opslaan…' : 'Conceptblokken opslaan'}</button>
            <span role="status" className="text-sm text-gray-600 dark:text-gray-300">{saved ? 'Concept opgeslagen; verzending staat uit.' : dirty ? 'Je hebt niet-opgeslagen wijzigingen.' : ''}</span>
          </div>
        </section>

        <section aria-label="Volledige voorbeeldmail" className="space-y-4 min-w-0">
          <h3 className="font-semibold text-gray-900 dark:text-gray-100">Voorbeeld per situatie</h3>
          <fieldset disabled={disabled} className="grid gap-3 sm:grid-cols-2">
            <legend className="sr-only">Kies de voorbeeldsituatie</legend>
            <Choice name="type" label="Welkomstmail" value={scenario.type} onChange={changeScenario} options={[[ 'member', 'Nieuw lid' ], [ 'volunteer', 'Nieuwe vrijwilliger' ], [ 'combined', 'Nieuw lid én vrijwilliger' ]]} />
            <Choice name="age" label="Leeftijd lid of vrijwilliger" value={scenario.age} onChange={changeScenario} options={[[ 'adult', 'Vanaf 18 jaar' ], [ 'minor', 'Jonger dan 18 jaar' ]]} />
            <Choice name="recipient" label="Ontvanger" value={scenario.recipient} onChange={changeScenario} options={scenario.age === 'minor' ? [[ 'self', 'Lid of vrijwilliger zelf' ], [ 'parent', 'Ouder/verzorger' ]] : [[ 'self', 'Lid of vrijwilliger zelf' ]]} />
            <Choice name="account" label="Account van deze ontvanger" value={scenario.account} onChange={changeScenario} options={[[ 'activate', 'Nog activeren' ], [ 'exists', 'Passend account vastgesteld' ]]} />
            {volunteer && <>
              <Choice name="vog" label="VOG-situatie" value={scenario.vog} onChange={changeScenario} options={[[ 'none', 'Geen VOG-plicht' ], [ 'missing', 'VOG ontbreekt' ], [ 'requested', 'Aanvraag loopt' ], [ 'review', 'In controle / ter beoordeling' ], [ 'valid', 'VOG geldig' ], [ 'renew', 'Vernieuwen' ], [ 'resubmit', 'Opnieuw aanleveren' ]]} />
              <Choice name="clothing" label="Functie geeft recht op kleding" value={scenario.clothing} onChange={changeScenario} options={[[ 'no', 'Nee' ], [ 'yes', 'Ja' ]]} />
              <Choice name="returning" label="Terugkerende vrijwilliger" value={scenario.returning} onChange={changeScenario} options={[[ 'no', 'Nee' ], [ 'yes', 'Ja' ]]} />
            </>}
          </fieldset>
          <p className="text-sm text-gray-600 dark:text-gray-300">Dit voorbeeld gebruikt je huidige teksten, ook vóór opslaan. De gekozen situatie bewijst geen account- of VOG-status van een echte persoon.</p>
          <button className="btn-secondary" disabled={disabled} onClick={showPreview}>{busy === 'preview' ? 'Voorbeeld maken…' : 'Volledige voorbeeldmail tonen'}</button>
          {preview && <>
            <div className="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-sm text-gray-900 dark:text-gray-100">
              <p><strong>Aan:</strong> {preview.recipient} (voorbeeld)</p>
              <p><strong>Onderwerp:</strong> {preview.subject}</p>
              <details className="mt-2"><summary className="cursor-pointer">Opgenomen blokken</summary><ul className="list-disc pl-5 mt-2">{preview.block_ids.map((id) => <li key={id}>{definitions[id].label}</li>)}</ul></details>
            </div>
            <iframe title="Volledige onboardingmail als voorbeeld" sandbox="" referrerPolicy="no-referrer" srcDoc={preview.html.replace('<head>', '<head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\';">')} className="w-full h-[720px] rounded-lg border border-gray-200 bg-white" />
          </>}
        </section>
      </div>
    </div>
  );
}
