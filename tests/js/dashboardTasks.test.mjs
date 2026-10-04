import test from 'node:test';
import assert from 'node:assert/strict';
import { QueryClient, QueryObserver } from '@tanstack/react-query';
import { syncDashboardTask } from '../../src/utils/dashboardTasks.js';

test('confirmed completion stays removed while an old request finishes and a refresh fails', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: Infinity } } });
  const key = ['role-dashboard', 1];
  const original = { tasks: [{ id: 7, status: 'open' }, { id: 8, status: 'open' }], birthdays: [{ id: 3 }] };
  client.setQueryData(key, original);
  let finishOldRequest;
  const oldRequest = client.fetchQuery({ queryKey: key, queryFn: () => new Promise(resolve => { finishOldRequest = resolve; }) }).catch(() => {});
  const observer = new QueryObserver(client, { queryKey: key, enabled: false });
  const seen = [];
  const unsubscribe = observer.subscribe(result => seen.push(result.data.tasks.map(task => task.id)));

  await syncDashboardTask(client, { id: 7, status: 'completed' });
  assert.deepEqual(seen.at(-1), [8]);
  finishOldRequest(original);
  await oldRequest;
  await assert.rejects(client.fetchQuery({ queryKey: key, queryFn: async () => { throw new Error('offline'); } }), /offline/);
  assert.deepEqual(client.getQueryData(key).tasks, [{ id: 8, status: 'open' }]);
  assert.equal(client.getQueryData(key).birthdays, original.birthdays);
  assert.equal(original.tasks.length, 2);
  unsubscribe();
  client.clear();
});

test('waiting tasks leave the open list; edits preserve metadata and never insert unrelated tasks', async () => {
  const client = new QueryClient();
  const key = ['role-dashboard', 1];
  client.setQueryData(key, { tasks: [{ id: 7, status: 'open', person_name: 'Sam' }] });
  await syncDashboardTask(client, { id: 9, status: 'open' });
  await syncDashboardTask(client, { id: 7, status: 'open', content: 'Gewijzigd' });
  assert.deepEqual(client.getQueryData(key).tasks, [{ id: 7, status: 'open', person_name: 'Sam', content: 'Gewijzigd' }]);
  await syncDashboardTask(client, { id: 7, status: 'awaiting' });
  assert.deepEqual(client.getQueryData(key).tasks, []);
  assert.equal(client.getQueryData(['role-dashboard', 2]), undefined);
  client.clear();
});
