/** Cancel the reviewed shifts sequentially; retain per-shift failures for safe retries. */
export async function cancelShiftDay(shifts, reason, cancelShift, onProgress = () => {}) {
  const results = [];
  for (const shift of shifts) {
    try {
      const response = await cancelShift(shift.id, { reason });
      results.push({ shift, cancellation: response.data });
    } catch (error) {
      results.push({ shift, error: error?.response?.data?.message || error?.message || 'Annuleren mislukt.' });
    }
    onProgress([...results]);
  }
  return results;
}

export function retryableCancellations(results) {
  return results.filter((result) => result.error || result.cancellation?.notifications?.failed > 0).map((result) => result.shift);
}
