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
  changePassword: (data) => api.post('/rondo/v1/user/password', data),
  
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

  // Anniversaries
  getAnniversaries: (daysAhead = 365, limit = 100, daysBack = 0) =>
    api.get('/rondo/v1/anniversaries', {
      params: { days_ahead: daysAhead, days_back: daysBack, limit },
      timeout: 30000,
    }),
  getAnniversarySettings: () => api.get('/rondo/v1/anniversaries/settings'),
  updateAnniversarySettings: (milestones) => api.post('/rondo/v1/anniversaries/settings', { milestones }),
  
  // Notification channels
  getNotificationChannels: () => api.get('/rondo/v1/user/notification-channels'),
  updateNotificationChannels: (channels) => api.post('/rondo/v1/user/notification-channels', { channels }),
  updateNotificationTime: (time) => api.post('/rondo/v1/user/notification-time', { time }),
  updateMentionNotifications: (preference) => api.post('/rondo/v1/user/mention-notifications', { preference }),
  
  // Person-specific
  getPersonTimeline: (personId) => api.get(`/rondo/v1/people/${personId}/timeline`),
  getPersonNotes: (personId) => api.get(`/rondo/v1/people/${personId}/notes`),
  issueMembershipPassQrToken: (personId, params = {}) =>
    api.get(`/rondo/v1/membership-passes/people/${personId}/qr-token`, { params }),
  getMembershipPassLandingUrl: (personId) =>
    api.get(`/rondo/v1/membership-passes/people/${personId}/landing-url`),
  verifyMembershipPassQrToken: (token) =>
    api.post('/rondo/v1/membership-passes/verify', { token }),
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

  // Clothing
  getClothingItems: () => api.get('/rondo/v1/clothing/items'),
  createClothingItem: (data) => api.post('/rondo/v1/clothing/items', data),
  updateClothingItem: (id, data) => api.put(`/rondo/v1/clothing/items/${id}`, data),
  deleteClothingItem: (id) => api.delete(`/rondo/v1/clothing/items/${id}`),
  getClothingAssignments: (params = {}) => api.get('/rondo/v1/clothing/assignments', { params }),
  createClothingAssignment: (data) => api.post('/rondo/v1/clothing/assignments', data),
  getClothingPersonProfile: (personId) => api.get(`/rondo/v1/clothing/person/${personId}/profile`),
  getClothingOverview: () => api.get('/rondo/v1/clothing/overview'),
  exportClothingCsv: () => api.get('/rondo/v1/clothing/export', { responseType: 'blob' }),
  getClothingSettings: () => api.get('/rondo/v1/clothing/settings'),
  updateClothingSettings: (data) => api.post('/rondo/v1/clothing/settings', data),
  
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

  // Feedback
  getFeedbackList: (params) => api.get('/rondo/v1/feedback', { params }),
  getFeedback: (id) => api.get(`/rondo/v1/feedback/${id}`),
  createFeedback: (data) => api.post('/rondo/v1/feedback', data),
  updateFeedback: (id, data) => api.put(`/rondo/v1/feedback/${id}`, data),
  deleteFeedback: (id) => api.delete(`/rondo/v1/feedback/${id}`),
  getFeedbackComments: (id) => api.get(`/rondo/v1/feedback/${id}/comments`),
  createFeedbackComment: (id, data) => api.post(`/rondo/v1/feedback/${id}/comments`, data),

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

  // Functie-to-capability mapping (admin only)
  getFunctieCapabilityMap: () => api.get('/rondo/v1/functie-capability-map'),
  updateFunctieCapabilityMap: (data) => api.post('/rondo/v1/functie-capability-map', data),
  getCommissieCapabilityMap: () => api.get('/rondo/v1/commissie-capability-map'),
  updateCommissieCapabilityMap: (data) => api.post('/rondo/v1/commissie-capability-map', data),
  syncAllCapabilities: () => api.post('/rondo/v1/capability-sync/all'),
  syncPersonCapabilities: (personId) => api.post(`/rondo/v1/people/${personId}/capability-sync`),

  // User provisioning (admin only)
  searchProvisionableUsers: (search) => api.get('/rondo/v1/users/provisionable', { params: { search } }),
  provisionUser: (personId) => api.post(`/rondo/v1/people/${personId}/provision`),
  getProvisioningSettings: () => api.get('/rondo/v1/provisioning/settings'),
  updateProvisioningSettings: (data) => api.post('/rondo/v1/provisioning/settings', data),

  // Club configuration (admin only)
  getClubConfig: () => api.get('/rondo/v1/config'),
  updateClubConfig: (data) => api.post('/rondo/v1/config', data),

  // Finance settings (admin only)
  getFinanceSettings: () => api.get('/rondo/v1/finance/settings'),
  updateFinanceSettings: (data) => api.post('/rondo/v1/finance/settings', data),
  getFinanceBranding: () => api.get('/rondo/v1/finance/branding'),
  updateFinanceBranding: (data) => api.post('/rondo/v1/finance/branding', data),

  // Rabobank OAuth
  getRabobankStatus: () => api.get('/rondo/v1/rabobank/status'),
  getRabobankAuthorizeUrl: () => api.get('/rondo/v1/rabobank/authorize'),
  disconnectRabobank: () => api.post('/rondo/v1/rabobank/disconnect'),

  // Payment
  createPaymentLink: (invoiceId) => api.post(`/rondo/v1/invoices/${invoiceId}/regenerate-payment-link`),
  regeneratePaymentLink: (invoiceId) => api.post(`/rondo/v1/invoices/${invoiceId}/regenerate-payment-link`),
  resetPaymentState: (invoiceId) => api.post(`/rondo/v1/invoices/${invoiceId}/reset-payment-state`),
  toggleInstallments: (invoiceId, disabled) => api.post(`/rondo/v1/invoices/${invoiceId}/toggle-installments`, { disabled }),
  getRabobankCertificate: () => api.get('/rondo/v1/rabobank/certificate'),

  // Invoice endpoints
  getInvoices: (params = {}) => api.get('/rondo/v1/invoices', { params }),
  getInvoice: (id) => api.get(`/rondo/v1/invoices/${id}`),
  createInvoice: (data) => api.post('/rondo/v1/invoices', data),
  updateInvoiceStatus: (id, status) => api.post(`/rondo/v1/invoices/${id}/status`, { status }),
  getInvoicedCaseIds: (personId) => api.get('/rondo/v1/invoices/invoiced-cases', { params: { person_id: personId } }),
  getAllInvoicedCaseIds: () => api.get('/rondo/v1/invoices/all-invoiced-cases'),
  bulkCreateInvoices: (caseIds) => api.post('/rondo/v1/invoices/bulk', { case_ids: caseIds }),
  generateInvoicePdf: (id) => api.post(`/rondo/v1/invoices/${id}/generate-pdf`),
  sendInvoice: (id) => api.post(`/rondo/v1/invoices/${id}/send`),
  resendInvoice: (id) => api.post(`/rondo/v1/invoices/${id}/resend`),
  deleteInvoice: (id) => api.delete(`/rondo/v1/invoices/${id}`),
  getInvoicePdfUrl: (id) => `${window.rondoConfig?.apiUrl || '/wp-json'}rondo/v1/invoices/${id}/pdf?_wpnonce=${window.rondoConfig?.nonce || ''}`,
  getInvoiceQrUrl: (id) => `${window.rondoConfig?.apiUrl || '/wp-json'}rondo/v1/invoices/${id}/qr?_wpnonce=${window.rondoConfig?.nonce || ''}`,

  // Membership fees
  getFeeList: (params = {}) => api.get('/rondo/v1/fees', { params }),
  getFeeSummary: (params = {}) => api.get('/rondo/v1/fees/summary', { params }),
  getPersonFee: (personId, params = {}) => api.get(`/rondo/v1/fees/person/${personId}`, { params }),
  recalculateAllFees: (params = {}) => api.post('/rondo/v1/fees/recalculate', params),
  exportFeesToSheets: (data) => api.post('/rondo/v1/google-sheets/export-fees', data),

  // Bulk invoice creation
  startBulkInvoiceJob: (data) => api.post('/rondo/v1/fees/bulk-create-invoices', data),
  getBulkInvoiceJobStatus: () => api.get('/rondo/v1/fees/bulk-invoice-job'),
  createMembershipInvoice: (data) => api.post('/rondo/v1/fees/create-membership-invoice', data),

  // Billing settings
  getBillingSettings: (params = {}) => api.get('/rondo/v1/fees/billing-settings', { params }),
  updateBillingSettings: (data) => api.post('/rondo/v1/fees/billing-settings', data),

  // VOG Bulk Operations
  bulkSendVOGEmails: (ids) => api.post('/rondo/v1/vog/bulk-send', { ids }),
  bulkMarkVOGJustis: (ids) => api.post('/rondo/v1/vog/bulk-mark-justis', { ids }),
  bulkSendVOGReminders: (ids) => api.post('/rondo/v1/vog/bulk-send-reminder', { ids }),

  // Sportlink sync
  syncFromSportlink: (knvbId) => api.post('/rondo/v1/sportlink/sync-individual', { knvb_id: knvbId }),
};
