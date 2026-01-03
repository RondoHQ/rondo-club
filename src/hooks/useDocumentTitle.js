import { useEffect } from 'react';
import { useLocation, useParams } from 'react-router-dom';

/**
 * Hook to update document title based on current route
 */
export function useDocumentTitle(title) {
  useEffect(() => {
    if (title) {
      const siteName = window.prmConfig?.siteName || 'Personal CRM';
      document.title = `${title} - ${siteName}`;
    }
  }, [title]);
}

/**
 * Hook to automatically set document title based on route
 * Can optionally accept a custom title override
 */
export function useRouteTitle(customTitle = null) {
  const location = useLocation();
  const params = useParams();
  
  useEffect(() => {
    let title = customTitle;
    
    if (!title) {
      const path = location.pathname;
      
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
        } else if (params.id) {
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
        } else if (params.id) {
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
        title = 'Personal CRM';
      }
    }
    
    const siteName = window.prmConfig?.siteName || 'Personal CRM';
    document.title = `${title} - ${siteName}`;
  }, [location.pathname, params.id, customTitle]);
}

