import { useState, useEffect } from 'react';
import { useLocation } from 'react-router-dom';

const PAGE_VIEWS_KEY = 'pwa-page-views';
const NOTES_KEY = 'pwa-notes-added';

/**
 * Last path counted, shared across every consumer of the hook.
 *
 * InstallPrompt and IOSInstallModal both track engagement and both re-run on
 * the same navigation, so without this guard a single route change would count
 * as two page views.
 */
let lastCountedPath = null;

function readCount(key) {
  return parseInt(sessionStorage.getItem(key) || '0', 10);
}

/**
 * Count one page view for `pathname`, at most once per navigation.
 *
 * @param {string} pathname Current route path.
 * @returns {number} The page view count after this navigation.
 */
function recordPageView(pathname) {
  if (lastCountedPath === pathname) {
    return readCount(PAGE_VIEWS_KEY);
  }

  lastCountedPath = pathname;
  const pageViews = readCount(PAGE_VIEWS_KEY) + 1;
  sessionStorage.setItem(PAGE_VIEWS_KEY, pageViews.toString());

  return pageViews;
}

/**
 * React hook that tracks user engagement for timing install prompts.
 * Uses sessionStorage to track page views and note additions.
 *
 * Counts client-side navigations, not document loads. Rondo is a single page
 * app, so App mounts once per document: counting mounts meant a member had to
 * hard-reload the tab several times before any install prompt could appear.
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
  const { pathname } = useLocation();

  useEffect(() => {
    const pageViews = recordPageView(pathname);
    const notesAdded = readCount(NOTES_KEY);

    // Compare after counting this view — the previous version read the counter
    // before incrementing, so the threshold effectively landed one view late.
    if (pageViews >= minPageViews || notesAdded >= minNotes) {
      setIsEngaged(true);
    }
  }, [pathname, minPageViews, minNotes]);

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
  const count = readCount(NOTES_KEY);
  sessionStorage.setItem(NOTES_KEY, (count + 1).toString());
}
