import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { APP_NAME } from '@/constants/app';

/**
 * Hook to update document title based on current route
 */
export function useDocumentTitle(title) {
  useEffect(() => {
    if (title) {
      const siteName = window.stadionConfig?.siteName || APP_NAME;
      document.title = `${title} - ${siteName}`;
    }
  }, [title]);
}

/**
 * Extract route ID from pathname
 * Examples: /people/123 -> '123', /teams/456/edit -> '456'
 */
function extractRouteId(pathname) {
  const match = pathname.match(/\/(?:people|teams|dates)\/(\d+)/);
  return match ? match[1] : null;
}

/**
 * Hook to automatically set document title based on route
 * Can optionally accept a custom title override
 */
export function useRouteTitle(customTitle = null) {
  const location = useLocation();
  
  useEffect(() => {
    let title = customTitle;
    
    if (!title) {
      const path = location.pathname;
      const routeId = extractRouteId(path);
      
      // Handle specific routes
      if (path === '/') {
        title = 'Dashboard';
      } else if (path === '/login') {
        title = 'Login';
      } else if (path.startsWith('/people')) {
        if (path === '/people' || path === '/people/') {
          title = 'People';
        } else if (path === '/people/new') {
          title = 'New person';
        } else if (path.endsWith('/edit')) {
          title = 'Edit person';
        } else if (routeId) {
          // For detail pages, we'll use a generic title
          // Individual pages can override with useDocumentTitle
          title = 'Person';
        } else {
          title = 'People';
        }
      } else if (path.startsWith('/teams')) {
        if (path === '/teams' || path === '/teams/') {
          title = 'Organizations';
        } else if (path === '/teams/new') {
          title = 'New organization';
        } else if (path.endsWith('/edit')) {
          title = 'Edit organization';
        } else if (routeId) {
          title = 'Organization';
        } else {
          title = 'Organizations';
        }
      } else if (path.startsWith('/dates')) {
        if (path === '/dates' || path === '/dates/') {
          title = 'Events';
        } else if (path === '/dates/new') {
          title = 'New date';
        } else if (path.endsWith('/edit')) {
          title = 'Edit date';
        } else {
          title = 'Events';
        }
      } else if (path.startsWith('/settings')) {
        title = 'Settings';
      } else {
        title = APP_NAME;
      }
    }
    
    const siteName = window.stadionConfig?.siteName || APP_NAME;
    document.title = `${title} - ${siteName}`;
  }, [location.pathname, customTitle]);
}

