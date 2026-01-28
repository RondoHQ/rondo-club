import { useState, useEffect } from 'react';

/**
 * React hook that tracks user engagement for timing install prompts.
 * Uses sessionStorage to track page views and note additions.
 *
 * @param {Object} options - Configuration options
 * @param {number} options.minPageViews - Show prompt after this many page views (default: 2)
 * @param {number} options.minNotes - Show prompt after this many notes added (default: 1)
 * @returns {boolean} - True if user has met engagement criteria
 */
export function useEngagementTracking({
  minPageViews = 2,
  minNotes = 1
} = {}) {
  const [isEngaged, setIsEngaged] = useState(false);

  useEffect(() => {
    // Track page view
    const pageViews = parseInt(sessionStorage.getItem('pwa-page-views') || '0', 10);
    sessionStorage.setItem('pwa-page-views', (pageViews + 1).toString());

    // Check notes added
    const notesAdded = parseInt(sessionStorage.getItem('pwa-notes-added') || '0', 10);

    // User is engaged if they've viewed enough pages OR added enough notes
    if (pageViews >= minPageViews || notesAdded >= minNotes) {
      setIsEngaged(true);
    }
  }, [minPageViews, minNotes]);

  return isEngaged;
}

/**
 * Track when user adds a note or activity.
 * Call this from note/activity creation success handlers.
 *
 * Increments counter in sessionStorage, which is used by useEngagementTracking
 * to determine when to show install prompts.
 */
export function trackNoteAdded() {
  const count = parseInt(sessionStorage.getItem('pwa-notes-added') || '0', 10);
  sessionStorage.setItem('pwa-notes-added', (count + 1).toString());
}
