import axios from 'axios';

// Get config from WordPress
const config = window.prmConfig || {};

// Create axios instance
const api = axios.create({
  baseURL: config.apiUrl || '/wp-json',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': config.nonce || '',
  },
});

// Request interceptor to ensure nonce is current
api.interceptors.request.use((config) => {
  // Update nonce from window in case it was refreshed
  if (window.prmConfig?.nonce) {
    config.headers['X-WP-Nonce'] = window.prmConfig.nonce;
  }
  return config;
});

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Handle 401 - redirect to login
    if (error.response?.status === 401) {
      window.location.href = config.loginUrl || '/wp-login.php';
    }
    
    // Handle 403 - forbidden
    if (error.response?.status === 403) {
      console.error('Access denied:', error.response.data);
    }
    
    return Promise.reject(error);
  }
);

export default api;

// Helper for WordPress REST API
export const wpApi = {
  // People
  getPeople: (params) => api.get('/wp/v2/people', { params }),
  getPerson: (id, params = {}) => api.get(`/wp/v2/people/${id}`, { params }),
  createPerson: (data) => api.post('/wp/v2/people', data),
  updatePerson: (id, data) => api.put(`/wp/v2/people/${id}`, data),
  deletePerson: (id) => api.delete(`/wp/v2/people/${id}`),
  
  // Companies
  getCompanies: (params) => api.get('/wp/v2/companies', { params }),
  getCompany: (id) => api.get(`/wp/v2/companies/${id}`),
  createCompany: (data) => api.post('/wp/v2/companies', data),
  updateCompany: (id, data) => api.put(`/wp/v2/companies/${id}`, data),
  deleteCompany: (id) => api.delete(`/wp/v2/companies/${id}`),
  
  // Important Dates
  getDates: (params) => api.get('/wp/v2/important-dates', { params }),
  getDate: (id) => api.get(`/wp/v2/important-dates/${id}`),
  createDate: (data) => api.post('/wp/v2/important-dates', data),
  updateDate: (id, data) => api.put(`/wp/v2/important-dates/${id}`, data),
  deleteDate: (id) => api.delete(`/wp/v2/important-dates/${id}`),
  
  // Taxonomies
  getPersonLabels: () => api.get('/wp/v2/person_label'),
  getCompanyLabels: () => api.get('/wp/v2/company_label'),
  getRelationshipTypes: () => api.get('/wp/v2/relationship_type', { params: { per_page: 100 } }),
  getDateTypes: () => api.get('/wp/v2/date_type', { params: { per_page: 100 } }),
  
  // Media
  uploadMedia: (file) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post('/wp/v2/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
};

// Helper for custom PRM API
export const prmApi = {
  // Dashboard
  getDashboard: () => api.get('/prm/v1/dashboard'),
  
  // Gravatar
  sideloadGravatar: (personId, email) => api.post(`/prm/v1/people/${personId}/gravatar`, { email }),
  
  // Current user
  getCurrentUser: () => api.get('/prm/v1/user/me'),
  
  // Search
  search: (query) => api.get('/prm/v1/search', { params: { q: query } }),
  
  // Reminders
  getReminders: (daysAhead = 30) => 
    api.get('/prm/v1/reminders', { params: { days_ahead: daysAhead } }),
  
  // Person-specific
  getPersonDates: (personId) => api.get(`/prm/v1/people/${personId}/dates`),
  getPersonTimeline: (personId) => api.get(`/prm/v1/people/${personId}/timeline`),
  getPersonNotes: (personId) => api.get(`/prm/v1/people/${personId}/notes`),
  createNote: (personId, content) => 
    api.post(`/prm/v1/people/${personId}/notes`, { content }),
  updateNote: (noteId, content) => 
    api.put(`/prm/v1/notes/${noteId}`, { content }),
  deleteNote: (noteId) => api.delete(`/prm/v1/notes/${noteId}`),
  
  // Activities
  getPersonActivities: (personId) => api.get(`/prm/v1/people/${personId}/activities`),
  createActivity: (personId, data) => 
    api.post(`/prm/v1/people/${personId}/activities`, data),
  updateActivity: (activityId, data) => 
    api.put(`/prm/v1/activities/${activityId}`, data),
  deleteActivity: (activityId) => api.delete(`/prm/v1/activities/${activityId}`),
  
  // Company-specific
  getCompanyPeople: (companyId) => api.get(`/prm/v1/companies/${companyId}/people`),
};
