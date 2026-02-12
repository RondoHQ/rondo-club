import axios from 'axios';

// Get config from WordPress
const config = window.rondoConfig || {};

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
  if (window.rondoConfig?.nonce) {
    config.headers['X-WP-Nonce'] = window.rondoConfig.nonce;
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
  
  // Teams
  getTeams: (params) => api.get('/wp/v2/teams', { params }),
  getTeam: (id, params = {}) => api.get(`/wp/v2/teams/${id}`, { params }),
  createTeam: (data) => api.post('/wp/v2/teams', data),
  updateTeam: (id, data) => api.put(`/wp/v2/teams/${id}`, data),
  deleteTeam: (id, params = {}) => api.delete(`/wp/v2/teams/${id}`, { params }),

  // Commissies
  getCommissies: (params) => api.get('/wp/v2/commissies', { params }),
  getCommissie: (id, params = {}) => api.get(`/wp/v2/commissies/${id}`, { params }),
  createCommissie: (data) => api.post('/wp/v2/commissies', data),
  updateCommissie: (id, data) => api.put(`/wp/v2/commissies/${id}`, data),
  deleteCommissie: (id, params = {}) => api.delete(`/wp/v2/commissies/${id}`, { params }),

  // Entity lookup (team or commissie by ID - avoids 404 fallback)
  getEntity: (id) => api.get(`/rondo/v1/entity/${id}`),

  // Taxonomies
  getPersonLabels: () => api.get('/wp/v2/person_label', { params: { per_page: 100, _fields: 'id,name,slug,count' } }),
  createPersonLabel: (data) => api.post('/wp/v2/person_label', data),
  updatePersonLabel: (id, data) => api.post(`/wp/v2/person_label/${id}`, data),
  deletePersonLabel: (id) => api.delete(`/wp/v2/person_label/${id}?force=true`),

  getTeamLabels: () => api.get('/wp/v2/team_label', { params: { per_page: 100, _fields: 'id,name,slug,count' } }),
  createTeamLabel: (data) => api.post('/wp/v2/team_label', data),
  updateTeamLabel: (id, data) => api.post(`/wp/v2/team_label/${id}`, data),
  deleteTeamLabel: (id) => api.delete(`/wp/v2/team_label/${id}?force=true`),

  getCommissieLabels: () => api.get('/wp/v2/commissie_label', { params: { per_page: 100, _fields: 'id,name,slug,count' } }),
  createCommissieLabel: (data) => api.post('/wp/v2/commissie_label', data),
  updateCommissieLabel: (id, data) => api.post(`/wp/v2/commissie_label/${id}`, data),
  deleteCommissieLabel: (id) => api.delete(`/wp/v2/commissie_label/${id}?force=true`),

  getRelationshipTypes: () => api.get('/wp/v2/relationship_type', { params: { per_page: 100, _fields: 'id,name,slug,acf' } }),
  createRelationshipType: (data) => api.post('/wp/v2/relationship_type', data),
  updateRelationshipType: (id, data) => api.post(`/wp/v2/relationship_type/${id}`, data),
  deleteRelationshipType: (id) => api.delete(`/wp/v2/relationship_type/${id}?force=true`),
  restoreRelationshipTypeDefaults: () => api.post('/rondo/v1/relationship-types/restore-defaults'),
  // Discipline Cases
  getDisciplineCases: (params) => api.get('/wp/v2/discipline-cases', { params }),
  getDisciplineCase: (id, params = {}) => api.get(`/wp/v2/discipline-cases/${id}`, { params }),

  // Seizoen taxonomy (for season filter)
  getSeasons: (params) => api.get('/wp/v2/seizoen', { params: { per_page: 100, orderby: 'name', order: 'desc', ...params } }),

  // Media
  getMedia: (params) => api.get('/wp/v2/media', { params }),
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
  getVersion: () => api.get('/rondo/v1/version'),

  // Current season helper
  getCurrentSeason: () => api.get('/rondo/v1/current-season'),
  
  // Dashboard
  getDashboard: () => api.get('/rondo/v1/dashboard'),
  
  // Gravatar
  sideloadGravatar: (personId, email) => api.post(`/rondo/v1/people/${personId}/gravatar`, { email }),

  // Bulk operations
  bulkUpdatePeople: (ids, updates) => api.post('/rondo/v1/people/bulk-update', { ids, updates }),
  bulkUpdateTeams: (ids, updates) => api.post('/rondo/v1/teams/bulk-update', { ids, updates }),
  bulkUpdateCommissies: (ids, updates) => api.post('/rondo/v1/commissies/bulk-update', { ids, updates }),

  // Filtered people with server-side pagination/filtering/sorting
  getFilteredPeople: (params = {}) => api.get('/rondo/v1/people/filtered', { params }),

  // Filter options for dynamic dropdowns
  getFilterOptions: () => api.get('/rondo/v1/people/filter-options'),

  // Current user
  getCurrentUser: () => api.get('/rondo/v1/user/me'),
  
  // User management (admin only)
  getUsers: () => api.get('/rondo/v1/users'),
  deleteUser: (userId) => api.delete(`/rondo/v1/users/${userId}`),
  
  // Search
  search: (query) => api.get('/rondo/v1/search', { params: { q: query } }),
  
  // Reminders
  getReminders: (daysAhead = 30) => 
    api.get('/rondo/v1/reminders', { params: { days_ahead: daysAhead } }),
  triggerReminders: () => api.post('/rondo/v1/reminders/trigger'),
  rescheduleCronJobs: () => api.post('/rondo/v1/reminders/reschedule-cron'),
  
  // Notification channels
  getNotificationChannels: () => api.get('/rondo/v1/user/notification-channels'),
  updateNotificationChannels: (channels) => api.post('/rondo/v1/user/notification-channels', { channels }),
  updateNotificationTime: (time) => api.post('/rondo/v1/user/notification-time', { time }),
  updateMentionNotifications: (preference) => api.post('/rondo/v1/user/mention-notifications', { preference }),
  
  // Person-specific
  getPersonTimeline: (personId) => api.get(`/rondo/v1/people/${personId}/timeline`),
  getPersonNotes: (personId) => api.get(`/rondo/v1/people/${personId}/notes`),
  createNote: (personId, content, visibility = 'private') =>
    api.post(`/rondo/v1/people/${personId}/notes`, { content, visibility }),
  updateNote: (noteId, content, visibility = null) =>
    api.put(`/rondo/v1/notes/${noteId}`, { content, ...(visibility && { visibility }) }),
  deleteNote: (noteId) => api.delete(`/rondo/v1/notes/${noteId}`),
  
  // Activities
  getPersonActivities: (personId) => api.get(`/rondo/v1/people/${personId}/activities`),
  createActivity: (personId, data) => 
    api.post(`/rondo/v1/people/${personId}/activities`, data),
  updateActivity: (activityId, data) => 
    api.put(`/rondo/v1/activities/${activityId}`, data),
  deleteActivity: (activityId) => api.delete(`/rondo/v1/activities/${activityId}`),
  
  // Todos
  getAllTodos: (status = 'open') =>
    api.get('/rondo/v1/todos', { params: { status } }),
  
  // Investments (teams where entity is an investor)
  getInvestments: (investorId) => api.get(`/rondo/v1/investments/${investorId}`),
  getPersonTodos: (personId) => api.get(`/rondo/v1/people/${personId}/todos`),
  createTodo: (personId, data) => 
    api.post(`/rondo/v1/people/${personId}/todos`, data),
  updateTodo: (todoId, data) => 
    api.put(`/rondo/v1/todos/${todoId}`, data),
  deleteTodo: (todoId) => api.delete(`/rondo/v1/todos/${todoId}`),
  
  // Team-specific
  getTeamPeople: (teamId) => api.get(`/rondo/v1/teams/${teamId}/people`),
  setTeamLogo: (teamId, mediaId) => api.post(`/rondo/v1/teams/${teamId}/logo`, { media_id: mediaId }),

  // Commissie-specific
  getCommissiePeople: (commissieId) => api.get(`/rondo/v1/commissies/${commissieId}/people`),
  setCommissieLogo: (commissieId, mediaId) => api.post(`/rondo/v1/commissies/${commissieId}/logo`, { media_id: mediaId }),

  // Photo uploads with proper naming
  uploadPersonPhoto: (personId, file) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post(`/rondo/v1/people/${personId}/photo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  uploadTeamLogo: (teamId, file) => {
    const formData = new FormData();
    formData.append('file', file);
    return api.post(`/rondo/v1/teams/${teamId}/logo/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  
  // CardDAV & Application Passwords
  getCardDAVUrls: () => api.get('/rondo/v1/carddav/urls'),
  getAppPasswords: (userId) => api.get(`/wp/v2/users/${userId}/application-passwords`),
  createAppPassword: (userId, name) => api.post(`/wp/v2/users/${userId}/application-passwords`, { name }),
  deleteAppPassword: (userId, uuid) => api.delete(`/wp/v2/users/${userId}/application-passwords/${uuid}`),

  // iCal feed
  getIcalUrl: () => api.get('/rondo/v1/user/ical-url'),

  // Dashboard settings
  getDashboardSettings: () => api.get('/rondo/v1/user/dashboard-settings'),
  updateDashboardSettings: (settings) => api.patch('/rondo/v1/user/dashboard-settings', settings),

  // List preferences (column visibility, order, widths)
  getListPreferences: () => api.get('/rondo/v1/user/list-preferences'),
  updateListPreferences: (prefs) => api.patch('/rondo/v1/user/list-preferences', prefs),

  // Linked person (for filtering current user from attendee lists)
  getLinkedPerson: () => api.get('/rondo/v1/user/linked-person'),
  updateLinkedPerson: (personId) => api.post('/rondo/v1/user/linked-person', { person_id: personId }),

  // Person meetings
  getPersonMeetings: (personId, params = {}) => api.get(`/rondo/v1/people/${personId}/meetings`, { params }),
  logMeetingAsActivity: (eventId) => api.post(`/rondo/v1/calendar/events/${eventId}/log`),
  getTodayMeetings: () => api.get('/rondo/v1/calendar/today-meetings'),
  getMeetingsForDate: (date) => api.get('/rondo/v1/calendar/today-meetings', { params: { date } }),

  // Meeting notes
  getMeetingNotes: (eventId) => api.get(`/rondo/v1/calendar/events/${eventId}/notes`),
  updateMeetingNotes: (eventId, notes) => api.put(`/rondo/v1/calendar/events/${eventId}/notes`, { notes }),

  // Google Contacts OAuth
  getGoogleContactsStatus: () => api.get('/rondo/v1/google-contacts/status'),
  initiateGoogleContactsAuth: (readonly = true) => api.get('/rondo/v1/google-contacts/auth', { params: { readonly } }),
  disconnectGoogleContacts: () => api.delete('/rondo/v1/google-contacts'),
  triggerGoogleContactsImport: () => api.post('/rondo/v1/google-contacts/import'),
  getGoogleContactsUnlinkedCount: () => api.get('/rondo/v1/google-contacts/unlinked-count'),
  bulkExportGoogleContacts: () => api.post('/rondo/v1/google-contacts/bulk-export'),
  triggerContactsSync: () => api.post('/rondo/v1/google-contacts/sync'),
  updateContactsSyncFrequency: (frequency) => api.post('/rondo/v1/google-contacts/sync-frequency', { frequency }),

  // Google Sheets OAuth
  getSheetsStatus: () => api.get('/rondo/v1/google-sheets/status'),
  getSheetsAuthUrl: () => api.get('/rondo/v1/google-sheets/auth'),
  disconnectSheets: () => api.delete('/rondo/v1/google-sheets/disconnect'),
  exportPeopleToSheets: (data) => api.post('/rondo/v1/google-sheets/export-people', data),

  // Custom Fields management (admin only)
  getCustomFields: (postType) => api.get(`/rondo/v1/custom-fields/${postType}`),
  createCustomField: (postType, data) => api.post(`/rondo/v1/custom-fields/${postType}`, data),
  updateCustomField: (postType, fieldKey, data) => api.put(`/rondo/v1/custom-fields/${postType}/${fieldKey}`, data),
  deleteCustomField: (postType, fieldKey) => api.delete(`/rondo/v1/custom-fields/${postType}/${fieldKey}`),
  reorderCustomFields: (postType, order) => api.put(`/rondo/v1/custom-fields/${postType}/order`, { order }),

  // Custom Fields metadata (read-only, for display)
  getCustomFieldsMetadata: (postType) => api.get(`/rondo/v1/custom-fields/${postType}/metadata`),

  // Calendar connections
  getCalendarConnections: () => api.get('/rondo/v1/calendar/connections'),
  createCalendarConnection: (data) => api.post('/rondo/v1/calendar/connections', data),
  updateCalendarConnection: (id, data) => api.put(`/rondo/v1/calendar/connections/${id}`, data),
  deleteCalendarConnection: (id) => api.delete(`/rondo/v1/calendar/connections/${id}`),
  triggerCalendarSync: (id) => api.post(`/rondo/v1/calendar/connections/${id}/sync`),
  getConnectionCalendars: (id) => api.get(`/rondo/v1/calendar/connections/${id}/calendars`),
  getGoogleAuthUrl: () => api.get('/rondo/v1/calendar/auth/google'),
  testCalDAVConnection: (credentials) => api.post('/rondo/v1/calendar/auth/caldav/test', credentials),

  // Feedback
  getFeedbackList: (params) => api.get('/rondo/v1/feedback', { params }),
  getFeedback: (id) => api.get(`/rondo/v1/feedback/${id}`),
  createFeedback: (data) => api.post('/rondo/v1/feedback', data),
  updateFeedback: (id, data) => api.put(`/rondo/v1/feedback/${id}`, data),
  deleteFeedback: (id) => api.delete(`/rondo/v1/feedback/${id}`),

  // VOG Settings (admin only)
  getVOGSettings: () => api.get('/rondo/v1/vog/settings'),
  updateVOGSettings: (settings) => api.post('/rondo/v1/vog/settings', settings),

  // Volunteer Role Classification (admin only)
  getAvailableRoles: () => api.get('/rondo/v1/volunteer-roles/available'),
  getVolunteerRoleSettings: () => api.get('/rondo/v1/volunteer-roles/settings'),
  updateVolunteerRoleSettings: (settings) => api.post('/rondo/v1/volunteer-roles/settings', settings),

  // Membership Fee Settings (admin only)
  getMembershipFeeSettings: () => api.get('/rondo/v1/membership-fees/settings'),
  updateMembershipFeeSettings: (settings, season) => api.post('/rondo/v1/membership-fees/settings', { ...settings, season }),
  copySeasonCategories: (fromSeason, toSeason) => api.post('/rondo/v1/membership-fees/copy-season', { from_season: fromSeason, to_season: toSeason }),
  getAvailableWerkfuncties: () => api.get('/rondo/v1/werkfuncties/available'),

  // Club configuration (admin only)
  getClubConfig: () => api.get('/rondo/v1/config'),
  updateClubConfig: (data) => api.post('/rondo/v1/config', data),

  // Membership fees
  getFeeList: (params = {}) => api.get('/rondo/v1/fees', { params }),
  getFeeSummary: (params = {}) => api.get('/rondo/v1/fees/summary', { params }),
  getPersonFee: (personId, params = {}) => api.get(`/rondo/v1/fees/person/${personId}`, { params }),
  recalculateAllFees: (params = {}) => api.post('/rondo/v1/fees/recalculate', params),
  exportFeesToSheets: (data) => api.post('/rondo/v1/google-sheets/export-fees', data),

  // VOG Bulk Operations
  bulkSendVOGEmails: (ids) => api.post('/rondo/v1/vog/bulk-send', { ids }),
  bulkMarkVOGJustis: (ids) => api.post('/rondo/v1/vog/bulk-mark-justis', { ids }),
  bulkSendVOGReminders: (ids) => api.post('/rondo/v1/vog/bulk-send-reminder', { ids }),

  // Sportlink sync
  syncFromSportlink: (knvbId) => api.post('/rondo/v1/sportlink/sync-individual', { knvb_id: knvbId }),
};
