/* Shared public questionnaire runner and authenticated preview. No analytics or external assets. */
(async () => {
  const config = window.rondoSurvey;
  const root = document.getElementById('survey-root');
  const element = (tag, text, attrs = {}) => {
    const node = document.createElement(tag);
    if (text !== null) node.textContent = text;
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
    return node;
  };
  const visible = (rule, answers) => !rule || (Array.isArray(answers[rule.question]) ? answers[rule.question].includes(rule.value) : answers[rule.question] === rule.value);
  let answers = {};
  let step = 0;
  let busy = false;
  let data;
  let storageKey;
  const apiUrl = `${config.api}${config.invitation ? `?invitation=${encodeURIComponent(config.invitation)}` : ''}`;
  try {
    if (config.draft) data = config.draft;
    else {
      const response = await fetch(apiUrl, { credentials: 'omit', cache: 'no-store' });
      data = await response.json();
      if (!response.ok) throw new Error(data.message || 'De enquête kon niet worden geladen.');
    }
    storageKey = `rondo-survey-${config.id}-${data.version || 'preview'}-${config.invitation.slice(0, 12)}`;
    if (!data.preview) {
      try { answers = JSON.parse(sessionStorage.getItem(storageKey) || '{}'); } catch { answers = {}; }
      if (!answers || typeof answers !== 'object' || Array.isArray(answers)) answers = {};
    }
  } catch (error) {
    root.replaceChildren(element('h1', 'Enquête'), element('p', error.message, { role: 'alert' }));
    return;
  }
  function sections() {
    const clean = {};
    const list = [];
    for (const section of data.document.sections) {
      if (!visible(section.condition, clean)) continue;
      const questions = [];
      for (const q of section.questions) {
        if (!visible(q.condition, clean)) continue;
        if (Object.hasOwn(answers, q.id)) clean[q.id] = answers[q.id];
        questions.push(q);
      }
      list.push({ ...section, questions });
    }
    answers = clean;
    return list;
  }
  function save() {
    sections();
    if (!data.preview) {
      try { sessionStorage.setItem(storageKey, JSON.stringify(answers)); } catch { /* Storage may be disabled. */ }
    }
  }
  function render(focusId) {
    const list = sections();
    step = Math.min(step, list.length - 1);
    const section = list[step];
    root.replaceChildren();
    if (data.preview) root.append(element('p', 'Voorbeeld — er worden geen antwoorden opgeslagen.', { class: 'survey-note' }));
    root.append(element('h1', data.title));
    if (step === 0) {
      root.append(element('p', data.intro || '', { class: 'survey-note' }));
      root.append(element('p', data.linked ? 'Bij een persoonlijke uitnodiging worden je antwoorden gekoppeld aan je naam en e-mailadres.' : 'Je naam en e-mailadres worden niet bij je antwoorden opgeslagen. Bij een persoonlijke uitnodiging houden we apart bij of je hebt meegedaan. Beheerders met toegang tot de opslag kunnen mogelijk gegevens combineren. Vermeld geen naam in open antwoorden als je onherkenbaar wilt blijven.', { class: 'survey-note' }));
      if (data.closes_at) root.append(element('p', `Invullen kan tot ${new Date(data.closes_at).toLocaleString('nl-NL')}.`, { class: 'survey-note' }));
    }
    root.append(element('p', `Stap ${step + 1} van ${list.length}`));
    root.append(element('progress', null, { class: 'survey-progress', max: list.length, value: step + 1, 'aria-label': 'Voortgang' }));
    const heading = element('h2', section.title, { tabindex: '-1' });
    root.append(heading);
    if (section.description) root.append(element('p', section.description, { class: 'survey-note' }));
    const form = element('form', null);
    form.noValidate = true;
    for (const q of section.questions) {
      const field = element('fieldset', null, { class: 'survey-question', id: `field-${q.id}` });
      field.append(element('legend', `${q.label}${q.required ? ' *' : ''}`));
      if (q.description) field.append(element('p', q.description, { class: 'survey-note' }));
      if (q.type === 'text') {
        const input = element('textarea', null, { id: q.id, maxlength: '10000', 'aria-label': q.label, ...(q.required ? { 'aria-required': 'true' } : {}) });
        input.value = answers[q.id] || '';
        input.addEventListener('input', () => { answers[q.id] = input.value; save(); });
        field.append(input);
      } else {
        const options = element('div', null, { class: 'survey-options' });
        for (const option of q.options) {
          const label = element('label', null, { class: 'survey-option' });
          const input = element('input', null, { type: q.type === 'multiple' ? 'checkbox' : 'radio', name: q.id, id: `${q.id}-${option.id}`, value: option.id });
          input.checked = q.type === 'multiple' ? (answers[q.id] || []).includes(option.id) : answers[q.id] === option.id;
          input.addEventListener('change', () => {
            answers[q.id] = q.type === 'multiple' ? [...options.querySelectorAll('input:checked')].map((node) => node.value) : option.id;
            save();
            render(input.id);
          });
          label.append(input, element('span', option.label));
          options.append(label);
        }
        field.append(options);
      }
      form.append(field);
    }
    const trap = element('input', null, { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true', class: 'survey-trap' });
    form.append(trap);
    const errorBox = element('p', '', { class: 'survey-error', role: 'alert' });
    errorBox.hidden = true;
    form.append(errorBox);
    const actions = element('div', null, { class: 'survey-actions' });
    const previous = element('button', 'Vorige', { type: 'button' });
    previous.disabled = step === 0 || busy;
    previous.addEventListener('click', () => { --step; render(); });
    const last = step === list.length - 1;
    const next = element('button', busy ? 'Verzenden…' : last ? (data.preview ? 'Voorbeeld afronden' : 'Antwoorden verzenden') : 'Volgende', { type: 'submit' });
    next.disabled = busy;
    actions.append(previous, next);
    form.append(actions);
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (busy) return;
      for (const q of section.questions) {
        if (q.required && (!answers[q.id] || (Array.isArray(answers[q.id]) && !answers[q.id].length) || (typeof answers[q.id] === 'string' && !answers[q.id].trim()))) {
          errorBox.textContent = `Vul de vraag in: ${q.label}`;
          errorBox.hidden = false;
          form.querySelector(`#field-${CSS.escape(q.id)} input, #field-${CSS.escape(q.id)} textarea`)?.focus();
          return;
        }
      }
      if (!last) { ++step; render(); return; }
      if (data.preview) { root.replaceChildren(element('h1', 'Voorbeeld afgerond'), element('p', data.thanks, { class: 'survey-note' })); return; }
      busy = true;
      next.disabled = true;
      next.textContent = 'Verzenden…';
      try {
        const response = await fetch(apiUrl, { method: 'POST', credentials: 'omit', cache: 'no-store', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ version: data.version, challenge: data.challenge, answers, website: trap.value }) });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Verzenden is niet gelukt.');
        try { sessionStorage.removeItem(storageKey); } catch { /* Storage may be disabled. */ }
        root.replaceChildren(element('h1', 'Bedankt!'), element('p', result.thanks, { class: 'survey-note' }));
        root.focus();
      } catch (error) {
        busy = false;
        next.disabled = false;
        next.textContent = 'Antwoorden verzenden';
        errorBox.hidden = false;
        errorBox.textContent = error.message;
      }
    });
    root.append(form);
    if (focusId) document.getElementById(focusId)?.focus();
    else { heading.focus(); window.scrollTo({ top: 0, behavior: 'instant' }); }
  }
  render();
})();
