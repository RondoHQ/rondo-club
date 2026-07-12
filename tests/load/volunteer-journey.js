import http from 'k6/http';
import exec from 'k6/execution';
import { check, fail, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || '').replace(/\/$/, '');
const PASSWORD = __ENV.LOADTEST_PASSWORD || '';
const MODE = __ENV.MODE || 'browse';
const VUS = Number(__ENV.VUS || 5);
const CONTENTION_SHIFT_ID = Number(__ENV.CONTENTION_SHIFT_ID || 0);
const START_AT_MS = Number(__ENV.START_AT_MS || 0);

if (BASE_URL !== 'https://demo.rondo.club') {
  throw new Error('Safety stop: BASE_URL must be https://demo.rondo.club');
}
if (PASSWORD.length < 16) {
  throw new Error('LOADTEST_PASSWORD must contain at least 16 characters');
}
if (!['browse', 'signup'].includes(MODE)) {
  throw new Error('MODE must be browse or signup');
}
if (MODE === 'signup' && CONTENTION_SHIFT_ID <= 0) {
  throw new Error('CONTENTION_SHIFT_ID is required in signup mode');
}

export const options = {
  scenarios: {
    volunteer_journey: {
      executor: 'per-vu-iterations',
      vus: VUS,
      iterations: 1,
      maxDuration: '5m',
      gracefulStop: '15s',
    },
  },
  thresholds: {
    http_req_failed: [{ threshold: 'rate<0.01', abortOnFail: true, delayAbortEval: '10s' }],
    checks: ['rate>0.99'],
    'http_req_duration{endpoint:user_me}': ['p(95)<750'],
    'http_req_duration{endpoint:my_shifts}': ['p(95)<1500'],
    'http_req_duration{endpoint:available_shifts}': ['p(95)<1500'],
    'http_req_duration{endpoint:signup}': ['p(95)<1500'],
  },
};

const loginDuration = new Trend('volunteer_login_duration', true);
const shellDuration = new Trend('volunteer_shell_duration', true);
const signupSuccesses = new Counter('volunteer_signup_successes');

function usernameForVu() {
  return `loadtest_volunteer_${String(exec.vu.idInTest).padStart(3, '0')}`;
}

function extractNonce(html) {
  const match = html.match(/window\.rondoConfig\s*=\s*(\{[\s\S]*?\});<\/script>/);
  if (!match) return '';
  try {
    return JSON.parse(match[1]).nonce || '';
  } catch {
    return '';
  }
}

function login() {
  const username = usernameForVu();
  const loginPage = http.get(`${BASE_URL}/wp-login.php`, {
    tags: { endpoint: 'login_page' },
  });
  check(loginPage, { 'login page is available': (response) => response.status === 200 });

  const startedAt = Date.now();
  const response = http.post(
    `${BASE_URL}/wp-login.php`,
    {
      log: username,
      pwd: PASSWORD,
      'wp-submit': 'Inloggen',
      redirect_to: `${BASE_URL}/vrijwillig`,
      testcookie: '1',
    },
    {
      redirects: 0,
      tags: { endpoint: 'login' },
    }
  );
  loginDuration.add(Date.now() - startedAt);

  const loggedIn = check(response, {
    'login redirects successfully': (result) => [302, 303].includes(result.status),
  });
  if (!loggedIn) fail(`Login failed for ${username}: HTTP ${response.status}`);

  const shellStartedAt = Date.now();
  const shell = http.get(`${BASE_URL}/vrijwillig`, {
    tags: { endpoint: 'volunteer_shell' },
  });
  shellDuration.add(Date.now() - shellStartedAt);
  const nonce = extractNonce(shell.body || '');
  const shellOk = check(shell, {
    'volunteer shell is available': (result) => result.status === 200,
    'REST nonce is present': () => nonce.length > 0,
  });
  if (!shellOk) fail(`Volunteer shell failed for ${username}`);

  return {
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
  };
}

function waitForContentionStart() {
  if (START_AT_MS <= 0) return;
  const remainingSeconds = (START_AT_MS - Date.now()) / 1000;
  if (remainingSeconds > 0) sleep(remainingSeconds);
}

export default function () {
  const auth = login();

  const currentUser = http.get(`${BASE_URL}/wp-json/rondo/v1/user/me`, {
    ...auth,
    tags: { endpoint: 'user_me' },
  });
  check(currentUser, {
    'current user loads': (response) => response.status === 200,
  });

  const responses = http.batch([
    [
      'GET',
      `${BASE_URL}/wp-json/rondo/v1/my-shifts`,
      null,
      { ...auth, tags: { endpoint: 'my_shifts' } },
    ],
    [
      'GET',
      `${BASE_URL}/wp-json/rondo/v1/shifts/available`,
      null,
      { ...auth, tags: { endpoint: 'available_shifts' } },
    ],
  ]);
  check(responses[0], { 'my shifts loads': (response) => response.status === 200 });
  check(responses[1], { 'available shifts load': (response) => response.status === 200 });

  if (MODE !== 'signup') {
    sleep(0.5 + Math.random());
    return;
  }

  waitForContentionStart();
  const signup = http.post(
    `${BASE_URL}/wp-json/rondo/v1/shifts/${CONTENTION_SHIFT_ID}/signup`,
    JSON.stringify({ force_overlap: false }),
    { ...auth, tags: { endpoint: 'signup' } }
  );
  const signedUp = check(signup, {
    'signup succeeds': (response) => response.status === 200 && response.json('signed_up') === true,
  });
  if (signedUp) signupSuccesses.add(1);
}
