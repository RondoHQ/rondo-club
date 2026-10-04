import { periodOffset } from './revenueComparison.js';

export const trendGroupKeys = ['food', 'non_food', 'entree', 'unassigned'];

export function productOptions(rows) {
  return [...new Map(rows.flatMap(row => row.product_trend.products).map(product => [product.id, product])).values()]
    .sort((a, b) => a.name.localeCompare(b.name, 'nl'));
}

// Missing reports remain absent; a product absent from an imported report has zero sales/consumption.
export function productPoints(rows, from, group, productId, metric = 'amount') {
  return rows.map(row => {
    const product = productId ? row.product_trend.products.find(item => item.id === productId) : null;
    const values = productId ? [metric === 'quantity' ? (product?.quantity ?? 0) : (product?.amount ?? 0)]
      : trendGroupKeys.map(key => row.product_trend.groups.find(item => item.group === key)?.amount ?? 0);
    return {
      periode: row.periode,
      provisional: row.provisional,
      offset: periodOffset(row.periode, from, group),
      values,
      total: metric === 'quantity' ? values.reduce((sum, value) => sum + value, 0) : values.reduce((sum, value) => sum + Math.round(value * 100), 0) / 100,
      quantity: product?.quantity ?? 0,
      amount: product?.amount ?? 0,
    };
  });
}

// Stack returns below zero separately, so refunds never cancel another category's visible area.
export function stackProductValues(values) {
  let positive = 0;
  let negative = 0;
  return values.map(value => {
    const layer = { positive: [positive, positive + Math.max(0, value)], negative: [negative + Math.min(0, value), negative] };
    positive = layer.positive[1];
    negative = layer.negative[0];
    return layer;
  });
}

export function consecutiveSegments(points) {
  return points.reduce((segments, point) => {
    if (!segments.length || point.offset - segments.at(-1).at(-1).offset !== 1) segments.push([]);
    segments.at(-1).push(point);
    return segments;
  }, []);
}
