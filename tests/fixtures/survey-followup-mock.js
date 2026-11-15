// Local-only fictional records for the survey analysis browser fixture.
export function followupMock({ questions, replies, flags }) {
  const owners = [{ id: 7, name: 'Testbeheerder' }, { id: 8, name: 'Tweede beheerder' }];
  const today = '2026-11-15T12:00:00+01:00';
  const actions = flags.has('empty') ? [] : [
    { id: 201, version: 100, title: 'Verlichting bij de fietsenstalling bespreken', owner: 7, due: '2026-11-01', status: 'open', notes: 'Agenderen bij de accommodatiecommissie.', reason: '', source: { type: 'response', id: 1 }, revision: 'a1', history: [{ author_name: 'Testbeheerder', at: today, changes: { status: { from: null, to: 'open' } } }] },
    { id: 202, version: 100, title: 'Lage waardering van de kleedkamers beoordelen', owner: 0, due: '', status: 'proposed', notes: '', reason: '', source: { type: 'response', id: 2 }, revision: 'a2', origin: '301:1:2', history: [{ author_name: 'Testbeheerder', at: today, changes: { status: { from: null, to: 'proposed' } } }] },
  ];
  const rules = flags.has('empty') ? [] : [{ id: 301, version: 100, title: 'Lage waardering van de kleedkamers beoordelen', active: true, revision: 'rule1', rule_version: 1, predicate: { kind: 'answer', question: 'rating', options: ['no'] } }];
  const older = [{ answers: { rating: 'yes' } }, { answers: { rating: 'no' } }, { answers: { rating: 'unknown' } }, { answers: {} }];
  const catalog = [
    { id: 90, title: 'Accommodatie · voorjaar', versions: [{ id: 91, date: '2026-04-01 12:00:00', questions }] },
    { id: 99, title: 'Accommodatie · najaar', versions: [{ id: 100, date: '2026-11-15 12:00:00', questions }] },
  ];
  const round = (survey_id, version, start, end) => ({ survey_id, version, question: 'rating', options: ['yes'], exclude: ['unknown'], start, end, audience: 'Alle leden', compatibility: 'comparable', note: '' });
  const comparisons = flags.has('empty') ? [] : [{ id: 401, version: 100, revision: 'round1', title: 'Tevredenheid over de kleedkamers', definition: 'Aandeel tevreden antwoorden, zonder “weet ik niet”.', rounds: [round(90, 91, '2026-04-01', '2026-04-30'), { ...round(99, 100, '2026-11-01', '2026-11-30'), audience: flags.has('incompatible') ? 'Alleen ouders' : 'Alle leden' }] }];
  const decorate = (action) => ({ ...action, owner_name: owners.find((o) => o.id === action.owner)?.name || 'Niet toegewezen', owner_eligible: action.owner > 0, overdue: action.due && action.due < '2026-11-15' && ['open', 'in_progress'].includes(action.status), source: { ...action.source, label: action.source.type === 'response' ? `Inzending ${action.source.id}` : `Thema: ${action.source.id}`, available: true } });
  const calculate = (comparison) => {
    let baseline;
    return { ...comparison, results: comparison.rounds.map((r, index) => {
      const rows = r.survey_id === 90 ? older : replies;
      const included = rows.filter((row) => row.answers[r.question] && !r.exclude.includes(row.answers[r.question]));
      const count = included.filter((row) => r.options.includes(row.answers[r.question])).length;
      const rate = included.length ? count / included.length * 100 : null;
      if (index === 0) baseline = rate;
      const limited = index > 0 && r.audience !== comparison.rounds[0].audience;
      const q = questions.find((item) => item.id === r.question);
      return { ...r, available: true, survey_title: catalog.find((s) => s.id === r.survey_id)?.title, question_label: q.label, options_labels: r.options.map((id) => q.options.find((o) => o.id === id)?.label), exclude_labels: r.exclude.map((id) => q.options.find((o) => o.id === id)?.label), total: rows.length, count, denominator: included.length, rate: rate === null ? null : Math.round(rate * 10) / 10, delta: index && !limited && rate !== null && baseline !== null ? Math.round((rate - baseline) * 10) / 10 : null, small: true, assessment: limited ? 'limited' : r.compatibility, reasons: limited ? ['De beschreven doelgroepen verschillen.'] : [] };
    }) };
  };
  return (config, url, body) => {
    const path = url.pathname;
    if (path.endsWith('/analysis-owners')) return owners;
    if (path.endsWith('/analysis-rounds')) return catalog;
    if (path.endsWith('/analysis/action-detail')) return decorate(actions.find((a) => a.id === Number(url.searchParams.get('action_id'))));
    if (path.endsWith('/analysis/actions')) {
      if (config.method === 'post') {
        const current = actions.find((a) => a.id === body.id);
        if (current && current.revision !== body.revision) throw new Error('Deze actie is gewijzigd. Vernieuw voordat je opnieuw opslaat.');
        if (!['proposed', 'dismissed'].includes(body.status) && (!body.owner || !body.due)) throw new Error('Kies een bevoegde interne eigenaar en geldige deadline voor een actie.');
        const saved = { ...current, ...body, id: current?.id || actions.length + 201, revision: crypto.randomUUID(), history: [...(current?.history || []), { author_name: 'Testbeheerder', at: today, changes: Object.fromEntries(['title', 'owner', 'due', 'status', 'notes', 'reason'].filter((key) => current?.[key] !== body[key]).map((key) => [key, { from: current?.[key] ?? null, to: body[key] }])) }] };
        if (current) Object.assign(current, saved); else actions.push(saved);
        return decorate(saved);
      }
      const rows = actions.map(decorate).filter((a) => (!url.searchParams.get('owner') || Number(url.searchParams.get('owner')) === 0 || a.owner === Number(url.searchParams.get('owner'))) && (!url.searchParams.get('status') || a.status === url.searchParams.get('status')) && (!url.searchParams.get('overdue') || a.overdue));
      return { rows, total: rows.length, page: 1, pages: rows.length ? 1 : 0 };
    }
    if (path.endsWith('/analysis/rules/run')) {
      const rule = rules.find((r) => r.id === body.id);
      let created = 0;
      for (const reply of replies) {
        const origin = `${rule.id}:${rule.rule_version}:${reply.id}`;
        const matches = rule.predicate.kind === 'answer' ? rule.predicate.options.includes(reply.answers[rule.predicate.question]) : reply.themes?.includes(rule.predicate.theme);
        if (matches && !actions.some((a) => a.origin === origin)) { actions.push({ id: actions.length + 201, version: 100, title: rule.title, owner: 0, due: '', status: 'proposed', notes: '', reason: '', source: { type: 'response', id: reply.id }, origin, revision: crypto.randomUUID(), history: [] }); created++; }
      }
      return { processed: replies.length, created, next: null };
    }
    if (path.endsWith('/analysis/rules')) {
      if (config.method === 'post') { const old = rules.find((r) => r.id === body.id); const row = { ...body, id: old?.id || rules.length + 301, revision: crypto.randomUUID(), rule_version: (old?.rule_version || 0) + 1, predicate: { kind: body.kind, question: body.question, options: body.options, theme: body.theme } }; if (old) Object.assign(old, row); else rules.push(row); }
      return rules;
    }
    if (path.endsWith('/analysis/rounds')) {
      if (config.method === 'post') { const old = comparisons.find((c) => c.id === body.id); const row = { ...body, id: old?.id || comparisons.length + 401, revision: crypto.randomUUID() }; if (old) Object.assign(old, row); else comparisons.push(row); }
      return comparisons.map(calculate);
    }
    return undefined;
  };
}
