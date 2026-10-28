// Only bounded technical metadata is retained; never keep the response body,
// request config, headers, member records, or server-provided error messages.
const diagnostics = new WeakMap();
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const list = (value, item = object) => Array.isArray(value) && value.every(item);
const strings = value => list(value, item => typeof item === 'string');

const validators = {
  workspace: data => object(data)
    && object(data.layout) && strings(data.layout.order) && strings(data.layout.hidden)
    && (data.layout.defaults === undefined || strings(data.layout.defaults))
    && (data.layout.birthday_days == null || (Number.isInteger(data.layout.birthday_days) && data.layout.birthday_days >= 1 && data.layout.birthday_days <= 30))
    && object(data.context) && list(data.teams)
    && list(data.birthdays, person => object(person) && Array.isArray(person.team_ids))
    && list(data.training, block => object(block) && typeof block.start === 'string')
    && (data.tasks === undefined || list(data.tasks))
    && typeof data.today === 'string' && typeof data.end_date === 'string',
  invoices: data => list(data, invoice => object(invoice) && typeof invoice.status === 'string'),
  fees: data => object(data) && list(data.members),
};

function dataShape(data) {
  if (data === null) return 'null';
  if (Array.isArray(data)) return 'array';
  return ['object', 'string', 'number', 'boolean', 'undefined'].includes(typeof data) ? typeof data : 'other';
}

export function getInvalidResponseDiagnostics(error) {
  return error && typeof error === 'object' ? diagnostics.get(error) : undefined;
}

export function readDashboardResponse(response, kind, reportError = () => {}) {
  if (!Object.prototype.hasOwnProperty.call(validators, kind)) throw new Error('Unknown dashboard response contract');
  if (validators[kind](response.data)) return response.data;

  const error = new Error('Invalid dashboard API response');
  const contentType = String(response.headers?.['content-type'] || '').split(';')[0].trim().toLowerCase();
  diagnostics.set(error, Object.freeze({
    api_contract: kind,
    response_shape: dataShape(response.data),
    response_content_type: contentType === 'application/json' || contentType.endsWith('+json') ? 'json'
      : contentType === 'text/html' ? 'html' : contentType ? 'other' : 'missing',
    ...(Number.isInteger(response.status) && response.status >= 100 && response.status <= 599
      ? { response_status: String(response.status) } : {}),
  }));
  reportError(error);
  throw error;
}

// An invalid success response needs an explicit retry, not a burst of identical
// reports. Ordinary request failures retain TanStack Query's three retries.
export function retryDashboardRequest(failureCount, error) {
  return !getInvalidResponseDiagnostics(error) && failureCount < 3;
}
