/* global __RONDO_RELEASE__ */
import { init, captureException, reactErrorHandler } from '@sentry/react';
import { sanitizeErrorEvent } from './error-event';

const config = window.rondoConfig?.errorMonitoring;
if (config?.dsn) {
  init({
    dsn: config.dsn,
    release: __RONDO_RELEASE__,
    environment: config.environment || 'production',
    sendDefaultPii: false,
    maxBreadcrumbs: 0,
    tracesSampleRate: 0,
    sendClientReports: false,
    beforeSend: sanitizeErrorEvent,
    integrations: defaults => defaults.filter(integration => !['BrowserSession', 'Breadcrumbs', 'HttpContext'].includes(integration.name)),
  });
}

export const reportReactError = reactErrorHandler();
export function reportError(error) {
  if (config?.dsn) captureException(error);
}
