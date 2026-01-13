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
    
    // Handle 403 - forbidden (silently handled)
    
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
  deletePerson: (id, params = {}) => api.delete(`/wp/v2/people/${id}`, { params }),
  
  // Companies
  getCompanies: (params) => api.get('/wp/v2/companies', { params }),
  getCompany: (id, params = {}) => api.get(`/wp/v2/companies/${id}`, { params }),
  createCompany: (data) => api.post('/wp/v2/companies', data),
  updateCompany: (id, data) => api.put(`/wp/v2/companies/${id}`, data),
  deleteCompany: (id, params = {}) => api.delete(`/wp/v2/companies/${id}`, { params }),
  
  // Important Dates
  getDates: (params) => api.get('/wp/v2/important-dates', { params }),
  getDate: (id) => api.get(`/wp/v2/important-dates/${id}`),
  createDate: (data) => api.post('/wp/v2/important-dates', data),
  updateDate: (id, data) => api.put(`/wp/v2/important-dates/${id}`, data),
  deleteDate: (id) => api.delete(`/wp/v2/important-dates/${id}`),
  
  // Taxonomies
  getPersonLabels: () => api.get('/wp/v2/person_label'),
  getCompanyLabels: () => api.get('/wp/v2/company_label'),
  getRelationshipTypes: () => api.get('/wp/v2/relationship_type', { params: { per_page: 100, _fields: 'id,name,slug,acf' } }),
  createRelationshipType: (data) => api.post('/wp/v2/relationship_type', data),
  updateRelationshipType: (id, data) => api.post(`/wp/v2/relationship_type/${id}`, data),
  deleteRelationshipType: (id) => api.delete(`/wp/v2/relationship_type/${id}?force=true`),
  restoreRelationshipTypeDefaults: () => api.post('/prm/v1/relationship-types/restore-defaults'),
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
  // Version check (for cache invalidation)
  getVersion: () => api.get('/prm/v1/version'),
  
  // Dashboard
  getDashboard: () => api.get('/prm/v1/dashboard'),
  
  // Gravatar
  sideloadGravatar: (personId, email) => api.post(`/prm/v1/people/${personId}/gravatar`, { email }),

  // Bulk operations
  bulkUpdatePeople: (ids, updates) => api.post('/prm/v1/people/bulk-update', { ids, updates }),
  bulkUpdateCompanies: (ids, updates) => api.post('/prm/v1/companies/bulk-update', { ids, updates }),
  
  // Current user
  getCurrentUser: () => api.get('/prm/v1/user/me'),
  
  // User management (admin only)
  getUsers: () => api.get('/prm/v1/users'),
  approveUser: (userId) => api.post(`/prm/v1/users/${userId}/approve`),
  denyUser: (userId) => api.post(`/prm/v1/users/${userId}/deny`),
  deleteUser: (userId) => api.delete(`/prm/v1/users/${userId}`),
  
  // Search
  search: (query) => api.get('/prm/v1/search', { params: { q: query } }),
  
  // Reminders
  getReminders: (daysAhead = 30) => 
    api.get('/prm/v1/reminders', { params: { days_ahead: daysAhead } }),
  triggerReminders: () => api.post('/prm/v1/reminders/trigger'),
  rescheduleCronJobs: () => api.post('/prm/v1/reminders/reschedule-cron'),
  
  // Notification channels
  getNotificationChannels: () => api.get('/prm/v1/user/notification-channels'),
  updateNotificationChannels: (channels) => api.post('/prm/v1/user/notification-channels', { channels }),
  updateSlackWebhook: (webhook) => api.post('/prm/v1/user/slack-webhook', { webhook }),
  updateNotificationTime: (time) => api.post('/prm/v1/user/notification-time', { time }),
  updateMentionNotifications: (preference) => api.post('/prm/v1/user/mention-notifications', { preference }),
  
  // Slack OAuth
  getSlackStatus: () => api.get('/prm/v1/user/slack-status'),
  disconnectSlack: () => api.post('/prm/v1/slack/disconnect'),
  getSlackChannels: () => api.get('/prm/v1/slack/channels'),
  getSlackTargets: () => api.get('/prm/v1/slack/targets'),
  updateSlackTargets: (targets) => api.post('/prm/v1/slack/targets', { targets }),
  
  // Person-specific
  getPersonDates: (personId) => api.get(`/prm/v1/people/${personId}/dates`),
  getPersonTimeline: (personId) => api.get(`/prm/v1/people/${personId}/timeline`),
  getPersonNotes: (personId) => api.get(`/prm/v1/people/${personId}/notes`),
  createNote: (personId, content, visibility = 'private') =>
    api.post(`/prm/v1/people/${personId}/notes`, { content, visibility }),
  updateNote: (noteId, content, visibility = null) =>
    api.put(`/prm/v1/notes/${noteId}`, { content, ...(visibility && { visibility }) }),
  deleteNote: (noteId) => api.delete(`/prm/v1/notes/${noteId}`),
  
  // Activities
  getPersonActivities: (personId) => api.get(`/prm/v1/people/${personId}/activities`),
  createActivity: (personId, data) => 
    api.post(`/prm/v1/people/${personId}/activities`, data),
  updateActivity: (activityId, data) => 
    api.put(`/prm/v1/activities/${activityId}`, data),
  deleteActivity: (activityId) => api.delete(`/prm/v1/activities/${activityId}`),
  
  // Todos
  getAllTodos: (includeCompleted = false) => 
    api.get('/prm/v1/todos', { params: { completed: includeCompleted } }),
  
  // Investments (companies where entity is an investor)
  getInvestments: (investorId) => api.get(`/prm/v1/investments/${investorId}`),
  getPersonTodos: (personId) => api.get(`/prm/v1/people/${personId}/todos`),
  createTodo: (personId, data) => 
    api.post(`/prm/v1/people/${personId}/todos`, data),
  updateTodo: (todoId, data) => 
    api.put(`/prm/v1/todos/${todoId}`, data),
  deleteTodo: (todoId) => api.delete(`/prm/v1/todos/${todoId}`),
  
  // Company-specific
  getCompanyPeople: (companyId) => api.get(`/prm/v1/companies/${companyId}/people`),
  setCompanyLogo: (companyId, mediaId) => api.post(`/prm/v1/companies/${companyId}/logo`, { media_id: mediaId }),
  
  // Photo uploads with proper naming
  uploadPersonPhoto: (personId, file) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post(`/prm/v1/people/${personId}/photo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  uploadCompanyLogo: (companyId, file) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post(`/prm/v1/companies/${companyId}/logo/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  
  // CardDAV & Application Passwords
  getCardDAVUrls: () => api.get('/prm/v1/carddav/urls'),
  getAppPasswords: (userId) => api.get(`/wp/v2/users/${userId}/application-passwords`),
  createAppPassword: (userId, name) => api.post(`/wp/v2/users/${userId}/application-passwords`, { name }),
  deleteAppPassword: (userId, uuid) => api.delete(`/wp/v2/users/${userId}/application-passwords/${uuid}`),

  // Workspaces
  getWorkspaces: () => api.get('/prm/v1/workspaces'),
  getWorkspace: (id) => api.get(`/prm/v1/workspaces/${id}`),
  createWorkspace: (data) => api.post('/prm/v1/workspaces', data),
  updateWorkspace: (id, data) => api.put(`/prm/v1/workspaces/${id}`, data),
  deleteWorkspace: (id) => api.delete(`/prm/v1/workspaces/${id}`),

  // Workspace members
  addWorkspaceMember: (workspaceId, data) => api.post(`/prm/v1/workspaces/${workspaceId}/members`, data),
  removeWorkspaceMember: (workspaceId, userId) => api.delete(`/prm/v1/workspaces/${workspaceId}/members/${userId}`),
  updateWorkspaceMember: (workspaceId, userId, data) => api.put(`/prm/v1/workspaces/${workspaceId}/members/${userId}`, data),
  searchWorkspaceMembers: async (workspaceIds, query) => {
    const { data } = await api.get('/prm/v1/workspaces/members/search', {
      params: { workspace_ids: workspaceIds.join(','), query },
    });
    return data;
  },

  // Workspace invites
  getWorkspaceInvites: (workspaceId) => api.get(`/prm/v1/workspaces/${workspaceId}/invites`),
  createWorkspaceInvite: (workspaceId, data) => api.post(`/prm/v1/workspaces/${workspaceId}/invites`, data),
  revokeWorkspaceInvite: (workspaceId, inviteId) => api.delete(`/prm/v1/workspaces/${workspaceId}/invites/${inviteId}`),

  // Public invite endpoints (no auth required for validation)
  validateInvite: (token) => api.get(`/prm/v1/invites/${token}`),
  acceptInvite: (token) => api.post(`/prm/v1/invites/${token}/accept`),

  // Sharing (for ShareModal - uses existing _shared_with meta)
  getPostShares: (postId, postType) => api.get(`/prm/v1/${postType}/${postId}/shares`),
  sharePost: (postId, postType, data) => api.post(`/prm/v1/${postType}/${postId}/shares`, data),
  unsharePost: (postId, postType, userId) => api.delete(`/prm/v1/${postType}/${postId}/shares/${userId}`),

  // User search for sharing
  searchUsers: (query) => api.get('/prm/v1/users/search', { params: { q: query } }),

  // iCal feed
  getIcalUrl: () => api.get('/prm/v1/user/ical-url'),
};
