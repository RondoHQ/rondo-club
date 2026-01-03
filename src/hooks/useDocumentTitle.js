import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { APP_NAME } from '@/constants/app';

/**
 * Hook to update document title based on current route
 */
export function useDocumentTitle(title) {
  useEffect(() => {
    if (title) {
      const siteName = window.prmConfig?.siteName || APP_NAME;
      document.title = `${title} - ${siteName}`;
    }
  }, [title]);
}

/**
 * Extract route ID from pathname
 * Examples: /people/123 -> '123', /companies/456/edit -> '456'
 */
function extractRouteId(pathname) {
  const match = pathname.match(/\/(?:people|companies|dates)\/(\d+)/);
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
          title = 'New Person';
        } else if (path.endsWith('/edit')) {
          title = 'Edit Person';
        } else if (routeId) {
          // For detail pages, we'll use a generic title
          // Individual pages can override with useDocumentTitle
          title = 'Person';
        } else {
          title = 'People';
        }
      } else if (path.startsWith('/companies')) {
        if (path === '/companies' || path === '/companies/') {
          title = 'Companies';
        } else if (path === '/companies/new') {
          title = 'New Company';
        } else if (path.endsWith('/edit')) {
          title = 'Edit Company';
        } else if (routeId) {
          title = 'Company';
        } else {
          title = 'Companies';
        }
      } else if (path.startsWith('/dates')) {
        if (path === '/dates' || path === '/dates/') {
          title = 'Important Dates';
        } else if (path === '/dates/new') {
          title = 'New Date';
        } else if (path.endsWith('/edit')) {
          title = 'Edit Date';
        } else {
          title = 'Important Dates';
        }
      } else if (path.startsWith('/settings')) {
        title = 'Settings';
      } else {
        title = APP_NAME;
      }
    }
    
    const siteName = window.prmConfig?.siteName || APP_NAME;
    document.title = `${title} - ${siteName}`;
  }, [location.pathname, customTitle]);
}

