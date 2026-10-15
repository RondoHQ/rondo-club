/** Activate the waiting worker before a user-requested reload, without clearing caches. */
export async function reloadForUpdate(registration, reload = () => window.location.reload(), timeoutMs = 3000) {
  const worker = registration?.waiting;
  if (worker) {
    await new Promise((resolve) => {
      const finish = () => {
        clearTimeout(timeout);
        worker.removeEventListener('statechange', onStateChange);
        resolve();
      };
      const onStateChange = () => {
        if (worker.state === 'activated' || worker.state === 'redundant') finish();
      };
      const timeout = setTimeout(finish, timeoutMs);
      worker.addEventListener('statechange', onStateChange);
      try {
        worker.postMessage({ type: 'SKIP_WAITING' });
        onStateChange();
      } catch {
        finish();
      }
    });
  }
  // Navigation uses the network; hashed assets can safely remain cached for other tabs.
  reload();
}
