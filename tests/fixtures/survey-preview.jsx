import { createRoot } from 'react-dom/client';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Surveys from '../../src/pages/Surveys/Surveys';
import api from '../../src/api/client';
import './dashboard-preview.css';

// All requests use synthetic data. No messages or production requests are sent.
const survey = { id: 1, title: 'Ledenenquête', intro: '', thanks: 'Bedankt!', closes_at: '', linked: false, access: 'invitation', reminder_days: 7, status: 'published', revision: 'fixture', current_version: 2, document: { sections: [] }, versions: [], can_manage: true, can_results: true, url: '/enquete/1' };
let email = { subject: 'Wat vind jij van onze club?', body: 'Hoi {naam},\n\nWe horen graag je mening. Bedankt voor je hulp!' };
const people = Array.from({ length: 503 }, (_, i) => ({ id: i + 1, name: `Testlid ${i + 1}`, email: `lid${i}@example.org` }));
people[1].email = people[0].email;
const invites = [];
window.surveyFixture = { calls: [], invites };
const escape = (value) => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
api.defaults.adapter = async (config) => {
  const path = config.url;
  const input = config.data ? JSON.parse(config.data) : {};
  window.surveyFixture.calls.push({ path, method: config.method, input });
  let data;
  if (path.endsWith('/user/me')) data = { can_access_enquetes: true };
  else if (path === '/rondo/v1/surveys/1') data = survey;
  else if (path.includes('/recipients?')) data = people;
  else if (path.endsWith('/email')) { if (config.method === 'put') email = input.email; data = email; }
  else if (path.endsWith('/email-preview')) data = { subject: input.email.subject, html: `<html><body style="padding:24px;font-family:sans-serif"><h1>${escape(input.email.subject)}</h1><p style="white-space:pre-wrap">${escape(input.email.body.replaceAll('{naam}', 'Testbeheerder'))}</p><a href="#">Vul de enquête in</a></body></html>` };
  else if (path.endsWith('/email-test')) data = { sent_to: 'beheerder@example.org' };
  else if (path.endsWith('/invitations')) {
    if (config.method === 'post') {
      for (const id of input.person_ids) {
        const person = people.find((p) => p.id === id);
        if (!invites.some((i) => i.email === person.email)) invites.push({ ...person, status: 'prepared', completed: false });
      }
      data = { prepared: invites.length };
    } else data = invites;
  } else if (path.endsWith('/action')) {
    const ready = invites.filter((i) => i.status === 'prepared');
    ready.forEach((i) => { i.status = 'queued'; });
    data = { queued: ready.length };
  } else throw new Error(`Unmocked fixture request: ${path}`);
  return { data: structuredClone(data), status: 200, statusText: 'OK', headers: {}, config };
};
createRoot(document.getElementById('root')).render(<MemoryRouter initialEntries={['/communicatie/enquetes/1']}><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><main className="mx-auto max-w-5xl p-4 sm:p-8"><Routes><Route path="/communicatie/enquetes/:id" element={<Surveys />} /></Routes></main></QueryClientProvider></MemoryRouter>);
