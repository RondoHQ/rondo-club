import test from 'node:test';
import assert from 'node:assert/strict';
import { consecutiveSegments, productOptions, productPoints, stackProductValues } from '../../src/utils/productTrend.js';

const row = (periode, products, groups = [], provisional = false) => ({ periode, provisional, product_trend: { products, groups } });
const coffee = (amount, quantity) => ({ id: 'coffee', name: 'Koffie', amount, quantity });

test('product absence is zero only on imported days, with quantities and provisional state preserved', () => {
  const rows = [row('2026-10-01', [coffee(4.7, 2)]), row('2026-10-03', [], [], true)];
  const points = productPoints(rows, '2026-10-01', 'day', 'coffee');
  assert.deepEqual(points.map(p => [p.offset, p.total, p.quantity, p.provisional]), [[0, 4.7, 2, false], [2, 0, 0, true]]);
  assert.equal(consecutiveSegments(points).length, 2);
  assert.deepEqual(productPoints(rows, '2026-10-01', 'day', 'coffee', 'quantity').map(p => p.total), [2, 0]);
});

test('category totals exclude merchandise and other, retain unassigned and use cents', () => {
  const points = productPoints([row('2026-10', [], [
    { group: 'food', amount: 0.1 }, { group: 'non_food', amount: 0.2 },
    { group: 'entree', amount: 3 }, { group: 'unassigned', amount: -1 },
    { group: 'other', amount: 40 }, { group: 'merchandise', amount: 70 },
  ])], '2026-09-15', 'month');
  assert.deepEqual(points[0].values, [0.1, 0.2, 3, -1]);
  assert.equal(points[0].total, 2.3);
  assert.equal(points[0].offset, 1);
});

test('refunds are stacked below zero independently from positive categories', () => {
  assert.deepEqual(stackProductValues([20, -5, 10, -3]), [
    { positive: [0, 20], negative: [0, 0] },
    { positive: [20, 20], negative: [-5, 0] },
    { positive: [20, 30], negative: [-5, -5] },
    { positive: [30, 30], negative: [-8, -5] },
  ]);
});

test('products in only the comparison range remain selectable without duplicates', () => {
  const rows = [row('2026-10-01', [coffee(4, 2)]), row('2025-10-01', [coffee(2, 1), { id: 'apple', name: 'Appelsap', amount: 3, quantity: 1 }])];
  assert.deepEqual(productOptions(rows).map(p => p.id), ['apple', 'coffee']);
  assert.deepEqual(productPoints([], '2026-10-01', 'day', 'coffee'), []);
  assert.deepEqual(consecutiveSegments([]), []);
});
