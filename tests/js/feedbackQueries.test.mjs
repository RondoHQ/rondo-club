import test from 'node:test';
import assert from 'node:assert/strict';
import { QueryClient, QueryObserver } from '@tanstack/react-query';
import { feedbackKeys, feedbackListOptions, refreshUpdatedFeedback } from '../../src/utils/feedbackQueries.js';

const createClient = () => new QueryClient({ defaultOptions: { queries: {
  staleTime: 300000, refetchOnMount: false, retry: false, gcTime: Infinity,
} } });

test('feedback sorting and filtering receive every page while preserving filters', async () => {
  const calls = [];
  const result = await feedbackListOptions({ status: 'open', per_page: 100 }, async (params) => {
    calls.push(params);
    return { data: [{ id: params.page }], headers: { 'x-wp-totalpages': '3' } };
  }).queryFn();
  assert.deepEqual(result.map(({ id }) => id), [1, 2, 3]);
  assert.deepEqual(calls, [1, 2, 3].map((page) => ({ status: 'open', per_page: 100, page })));
});

test('a failed later page rejects the overview instead of silently hiding older issues', async () => {
  await assert.rejects(feedbackListOptions({}, async ({ page }) => {
    if (page === 2) throw new Error('offline');
    return { data: [{ id: 1 }], headers: { 'x-wp-totalpages': '2' } };
  }).queryFn(), /offline/);
});

test('resolving feedback refreshes inactive lists and the same numeric/string detail cache', async () => {
  const client = createClient();
  let item = { id: 12715, meta: { status: 'approved' } };
  const options = feedbackListOptions({}, async () => ({ data: [item], headers: {} }));
  await client.fetchQuery(options);
  client.setQueryData(feedbackKeys.detail('12715'), item);
  item = { ...item, meta: { status: 'resolved' } };
  await refreshUpdatedFeedback(client, 12715, item);
  assert.equal(client.getQueryData(options.queryKey)[0].meta.status, 'resolved');
  assert.equal(client.getQueryData(feedbackKeys.detail('12715')).meta.status, 'resolved');
  assert.equal(client.getQueryCache().findAll({ queryKey: feedbackKeys.details() }).length, 1);
  client.clear();
});

test('returning to a fresh cached overview still fetches external feedback changes', async () => {
  const client = createClient();
  let status = 'approved';
  const options = feedbackListOptions({}, async () => ({ data: [{ status }], headers: {} }));
  await client.fetchQuery(options);
  status = 'resolved';
  const observer = new QueryObserver(client, options);
  let unsubscribe;
  await new Promise((resolve) => {
    unsubscribe = observer.subscribe((result) => {
      if (result.data?.[0]?.status === 'resolved') resolve();
    });
  });
  assert.equal(client.getQueryData(options.queryKey)[0].status, 'resolved');
  unsubscribe();
  client.clear();
});
