/* global __RONDO_RELEASE__ */
import { init, captureException, reactErrorHandler, reactRouterBrowserTracingIntegration } from '@sentry/react';
import { useEffect } from 'react';
import { useLocation, useNavigationType, createRoutesFromChildren, matchRoutes } from 'react-router-dom';
import { sanitizeTransaction, safePath } from './performance-monitoring';
import { sanitizeErrorEvent } from './error-event';

const config = window.rondoConfig?.errorMonitoring;
if (config?.dsn) {
  init({
    dsn: config.dsn,
    release: __RONDO_RELEASE__,
    environment: config.environment || 'production',
    sendDefaultPii: false,
    dataCollection: { userInfo: false, cookies: false, httpHeaders: false, httpBodies: [], urlQueryParams: false },
    maxBreadcrumbs: 0,
    traceLifecycle: 'static',
    tracesSampleRate: config.performanceEnabled === false ? 0 : 0.1,
    tracePropagationTargets: [new RegExp(`^${window.location.origin.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/wp-json/`), /^\/wp-json\//],
    beforeSendTransaction: sanitizeTransaction,
    sendClientReports: false,
    beforeSend: sanitizeErrorEvent,
    integrations: defaults => [
      ...defaults.filter(integration => !['BrowserSession', 'Breadcrumbs', 'HttpContext'].includes(integration.name)),
      ...(config.performanceEnabled === false ? [] : [reactRouterBrowserTracingIntegration({
        useEffect, useLocation, useNavigationType, createRoutesFromChildren, matchRoutes,
        beforeStartSpan: options => ({ ...options, name: safePath(options.name) }),
      })]),
    ],
  });
}

export const reportReactError = reactErrorHandler();
export function reportError(error) {
  if (config?.dsn) captureException(error);
}
