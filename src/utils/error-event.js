// Only diagnostic metadata leaves the browser; member data stays in Rondo.
export function sanitizeErrorEvent(event) {
  return {
    event_id: event.event_id, timestamp: event.timestamp, platform: event.platform,
    level: event.level, release: event.release, environment: event.environment,
    sdk: event.sdk, debug_meta: event.debug_meta,
    exception: event.exception && { values: event.exception.values.map(value => ({
      type: value.type, value: 'Rondo application error',
      mechanism: value.mechanism && { type: value.mechanism.type, handled: value.mechanism.handled },
      stacktrace: value.stacktrace && { frames: value.stacktrace.frames.map(frame => ({
        filename: frame.filename?.split(/[?#]/)[0], function: frame.function,
        lineno: frame.lineno, colno: frame.colno, in_app: frame.in_app,
      })) },
    })) },
  };
}
