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
  getRelationshipTypes: () => api.get('/wp/v2/relationship_type', { params: { per_page: 100, _fields: 'id,name,slug,fields' } }),
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
  
  // Dashboard — uses preloaded fetch from wp_head if available
  getDashboard: async () => {
    if (window.__dashboardPreload) {
      try {
        const preloaded = window.__dashboardPreload;
        window.__dashboardPreload = null; // Use only once
        const response = await preloaded;
        if (response.ok) {
          const data = await response.json();
          return { data };
        }
      } catch {
        // Preload failed, fall through to normal fetch
      }
    }
    return api.get('/rondo/v1/dashboard');
  },

  // Kaderlijst — scoped kader people, visibility enforced server-side.
  getKaderlijstPeople: (params = {}) => api.get('/rondo/v1/kaderlijst/people', { params }),
  getHousehold: () => api.get('/rondo/v1/people/household'),
  addParentRelationship: (personId, data) => api.post(`/rondo/v1/people/${personId}/parents`, data),
  
  // Bulk operations
  bulkUpdatePeople: (ids, updates) => api.post('/rondo/v1/people/bulk-update', { ids, updates }),
  sendOnboardingEmail: (personIds, type) => api.post('/rondo/v1/people/onboarding-email', { person_ids: personIds, type }),
  bulkUpdateTeams: (ids, updates) => api.post('/rondo/v1/teams/bulk-update', { ids, updates }),
  bulkUpdateCommissies: (ids, updates) => api.post('/rondo/v1/commissies/bulk-update', { ids, updates }),

  // Filtered people with server-side pagination/filtering/sorting
  getFilteredPeople: (params = {}) => api.get('/rondo/v1/people/filtered', { params }),

  // Guided person merge (administrator only)
  getPersonMergePreview: (primaryId, duplicateId) =>
    api.get(`/rondo/v1/people/${primaryId}/merge-preview`, { params: { duplicate_id: duplicateId } }),
  mergePeople: (primaryId, data) => api.post(`/rondo/v1/people/${primaryId}/merge`, data),

  // Filter options for dynamic dropdowns
  getFilterOptions: () => api.get('/rondo/v1/people/filter-options'),

  // Current user
  getCurrentUser: () => api.get('/rondo/v1/user/me'),
  changePassword: (data) => api.post('/rondo/v1/user/password', data),
  requestPasswordReset: () => api.post('/rondo/v1/user/password-reset'),
  
  // User management (admin only)
  getUsers: () => api.get('/rondo/v1/users'),
  deleteUser: (userId) => api.delete(`/rondo/v1/users/${userId}`),
  searchLinkablePeople: (query) => api.get('/rondo/v1/users/linkable-people', { params: { search: query } }),
  relinkUser: (userId, personId) => api.post(`/rondo/v1/users/${userId}/linked-person`, { person_id: personId }),
  searchUsers: (query) => api.get('/rondo/v1/users/search', { params: { q: query } }),
  
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
  updateCommissieInfo: (commissieId, fields) => api.post(`/rondo/v1/commissies/${commissieId}/info`, { fields }),
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
  
  // Application Passwords
  getAppPasswords: (userId) => api.get(`/wp/v2/users/${userId}/application-passwords`),
  createAppPassword: (userId, name) => api.post(`/wp/v2/users/${userId}/application-passwords`, { name }),
  deleteAppPassword: (userId, uuid) => api.delete(`/wp/v2/users/${userId}/application-passwords/${uuid}`),

  // Dashboard settings
  getDashboardSettings: () => api.get('/rondo/v1/user/dashboard-settings'),
  updateDashboardSettings: (settings) => api.patch('/rondo/v1/user/dashboard-settings', settings),

  // List preferences (column visibility, order, widths)
  getListPreferences: () => api.get('/rondo/v1/user/list-preferences'),
  updateListPreferences: (prefs) => api.patch('/rondo/v1/user/list-preferences', prefs),

  // Linked person (for filtering current user from attendee lists)
  getLinkedPerson: () => api.get('/rondo/v1/user/linked-person'),
  updateLinkedPerson: (personId) => api.post('/rondo/v1/user/linked-person', { person_id: personId }),
  claimGuardianAccount: (name) => api.post('/rondo/v1/user/guardian-claim', { name }),

  // Person meetings
  getPersonMeetings: (personId, params = {}) => api.get(`/rondo/v1/people/${personId}/meetings`, { params }),
  logMeetingAsActivity: (eventId) => api.post(`/rondo/v1/calendar/events/${eventId}/log`),

  // Meeting notes
  getMeetingNotes: (eventId) => api.get(`/rondo/v1/calendar/events/${eventId}/notes`),
  updateMeetingNotes: (eventId, notes) => api.put(`/rondo/v1/calendar/events/${eventId}/notes`, { notes }),

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
  getCapabilityMatrix: () => api.get('/rondo/v1/settings/capability-matrix'),
  updateCapabilityMatrix: (data) => api.post('/rondo/v1/settings/capability-matrix', data),
  getAgeGroupAccess: () => api.get('/rondo/v1/settings/age-group-access'),
  updateAgeGroupAccess: (data) => api.post('/rondo/v1/settings/age-group-access', data),
  createCustomRole: (data) => api.post('/rondo/v1/settings/roles', data),
  deleteCustomRole: (slug) => api.delete(`/rondo/v1/settings/roles/${slug}`),
  syncAllCapabilities: () => api.post('/rondo/v1/capability-sync/all'),
  syncPersonCapabilities: (personId) => api.post(`/rondo/v1/people/${personId}/capability-sync`),

  // User provisioning (admin only)
  searchProvisionableUsers: (search) => api.get('/rondo/v1/users/provisionable', { params: { search } }),
  provisionUser: (personId) => api.post(`/rondo/v1/people/${personId}/provision`),
  getProvisioningSettings: () => api.get('/rondo/v1/provisioning/settings'),
  updateProvisioningSettings: (data) => api.post('/rondo/v1/provisioning/settings', data),
  getOnboardingEmailSettings: (type) => api.get(`/rondo/v1/onboarding/email-settings/${type}`),
  updateOnboardingEmailSettings: (type, data) => api.post(`/rondo/v1/onboarding/email-settings/${type}`, data),

  // Club configuration (admin only)
  getClubConfig: () => api.get('/rondo/v1/config'),
  updateClubConfig: (data) => api.post('/rondo/v1/config', data),
  getLettermintProjects: () => api.get('/rondo/v1/lettermint/projects'),
  createLettermintWebhook: (data = {}) => api.post('/rondo/v1/lettermint/webhook/create', data),
  sendLettermintTestEmail: (recipient) => api.post('/rondo/v1/lettermint/test-email', { recipient }),
  sendLettermintVerificationEmail: (todoId, recipient = '') => api.post('/rondo/v1/lettermint/verify-email', { todo_id: todoId, recipient }),

  // Club TV content and player management
  getNarrowcastingItems: () => api.get('/rondo/v1/narrowcasting/items'),
  createNarrowcastingItem: (data) => api.post('/rondo/v1/narrowcasting/items', data),
  updateNarrowcastingItem: (id, data) => api.post(`/rondo/v1/narrowcasting/items/${id}`, data),
  deleteNarrowcastingItem: (id) => api.delete(`/rondo/v1/narrowcasting/items/${id}`),
  getNarrowcastingPlaylists: () => api.get('/rondo/v1/narrowcasting/playlists'),
  createNarrowcastingPlaylist: (data) => api.post('/rondo/v1/narrowcasting/playlists', data),
  updateNarrowcastingPlaylist: (id, data) => api.post(`/rondo/v1/narrowcasting/playlists/${id}`, data),
  deleteNarrowcastingPlaylist: (id) => api.delete(`/rondo/v1/narrowcasting/playlists/${id}`),
  setDefaultNarrowcastingPlaylist: (id) => api.post(`/rondo/v1/narrowcasting/playlists/${id}/default`),
  previewNarrowcastingPlaylist: (params = {}) => api.get('/rondo/v1/narrowcasting/preview/playlist', { params }),
  getNarrowcastingSponsors: () => api.get('/rondo/v1/narrowcasting/content/sponsors'),
  getNarrowcastingDisplayChoices: () => api.get('/rondo/v1/narrowcasting/content/displays'),
  assignNarrowcastingPlaylist: (displayId, playlistId) => api.post(`/rondo/v1/narrowcasting/displays/${displayId}/playlist`, { playlist_id: playlistId }),
  getNarrowcastingDisplays: () => api.get('/rondo/v1/narrowcasting/displays'),
  getNarrowcastingSettings: () => api.get('/rondo/v1/narrowcasting/settings'),
  updateNarrowcastingSettings: (data) => api.post('/rondo/v1/narrowcasting/settings', data),
  refreshNarrowcastingMatchday: () => api.post('/rondo/v1/narrowcasting/refresh'),
  claimNarrowcastingDisplay: (data) => api.post('/rondo/v1/narrowcasting/displays/claim', data),
  queueNarrowcastingCommand: (displayId, command) => api.post(`/rondo/v1/narrowcasting/displays/${displayId}/commands`, { command }),
  revokeNarrowcastingDisplay: (displayId) => api.post(`/rondo/v1/narrowcasting/displays/${displayId}/revoke`),

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
  getNextInvoiceNumber: () => api.get('/rondo/v1/invoices/next-number'),
  getInvoices: (params = {}) => api.get('/rondo/v1/invoices', { params }),
  getInvoice: (id) => api.get(`/rondo/v1/invoices/${id}`),
  createInvoice: (data) => api.post('/rondo/v1/invoices', data),
  updateDraftInvoice: (id, data) => api.post(`/rondo/v1/invoices/${id}/draft-details`, data),
  updateInvoiceStatus: (id, status) => api.post(`/rondo/v1/invoices/${id}/status`, { status }),
  addDraftInvoiceLineItem: (id, description, amount) => api.post(`/rondo/v1/invoices/${id}/draft-line-items`, {
    description,
    amount,
  }),
  updateMembershipInvoiceDiscount: (id, familyDiscountPercent, entryDiscountPercent) => api.post(`/rondo/v1/invoices/${id}/membership-discount`, {
    family_discount_percent: familyDiscountPercent,
    entry_discount_percent: entryDiscountPercent,
  }),
  getInvoicedCaseIds: (personId) => api.get('/rondo/v1/invoices/invoiced-cases', { params: { person_id: personId } }),
  getAllInvoicedCaseIds: () => api.get('/rondo/v1/invoices/all-invoiced-cases'),
  bulkCreateInvoices: (caseIds) => api.post('/rondo/v1/invoices/bulk', { case_ids: caseIds }),
  generateInvoicePdf: (id) => api.post(`/rondo/v1/invoices/${id}/generate-pdf`),
  sendInvoice: (id, data = {}) => api.post(`/rondo/v1/invoices/${id}/send`, data),
  scheduleInvoice: (id, data = {}) => api.post(`/rondo/v1/invoices/${id}/schedule`, data),
  resendInvoice: (id, data = {}) => api.post(`/rondo/v1/invoices/${id}/resend`, data),
  deleteInvoice: (id) => api.delete(`/rondo/v1/invoices/${id}`),
  getInvoicePdfUrl: (id) => `${window.rondoConfig?.apiUrl || '/wp-json'}rondo/v1/invoices/${id}/pdf?_wpnonce=${window.rondoConfig?.nonce || ''}`,
  getInvoiceQrUrl: (id) => `${window.rondoConfig?.apiUrl || '/wp-json'}rondo/v1/invoices/${id}/qr?_wpnonce=${window.rondoConfig?.nonce || ''}`,

  // Membership fees
  getFeeList: (params = {}) => api.get('/rondo/v1/fees', { params }),
  getFeeSummary: (params = {}) => api.get('/rondo/v1/fees/summary', { params }),
  getPersonFee: (personId, params = {}) => api.get(`/rondo/v1/fees/person/${personId}`, { params }),
  recalculateAllFees: (params = {}) => api.post('/rondo/v1/fees/recalculate', params),

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

  // Volunteer Policy
  getVolunteerEligibility: (params = {}) => api.get('/rondo/v1/volunteer-eligibility', { params }),
  getVolunteerExemption: (personId, params = {}) => api.get(`/rondo/v1/volunteer-exemption/${personId}`, { params }),
  updateVolunteerExemption: (personId, data) => api.put(`/rondo/v1/volunteer-exemption/${personId}`, data),
  getVolunteerDataQuality: (category, params = {}) => api.get(`/rondo/v1/volunteer-data-quality/${category}`, { params }),
  getRelationshipQuality: () => api.get('/rondo/v1/relationship-quality'),
  refreshVolunteerCache: () => api.post('/rondo/v1/volunteer-cache/refresh'),
  getManagedCommissies: () => api.get('/rondo/v1/managed-commissies'),

  // IVA
  approveIva: (personId, approve = true) => api.post(`/rondo/v1/iva/${personId}/approve`, { approve }),
  getIvaStatus: (personId) => api.get(`/rondo/v1/iva/${personId}/status`),
  getIvaPeople: () => api.get('/rondo/v1/iva/people'),
  getMyIva: () => api.get('/rondo/v1/iva/me'),
  getIvaCertificate: (personId) => api.get(`/rondo/v1/iva/${personId}/certificate`, { responseType: 'blob' }),
  getMyVog: () => api.get('/rondo/v1/vog/me'),
  uploadMyIva: (file, datumIva) => {
    const fd = new FormData();
    fd.append('certificaat', file);
    if (datumIva) fd.append('datum_iva', datumIva);
    return api.post('/rondo/v1/iva/upload', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  // Member-facing shift signup (/vrijwillig)
  getMyShifts: (params = {}) => api.get('/rondo/v1/my-shifts', { params }),
  getPersonShifts: (personId) => api.get(`/rondo/v1/people/${personId}/shifts`),
  getAvailableShifts: () => api.get('/rondo/v1/shifts/available'),
  getRecentShiftSignups: () => api.get('/rondo/v1/shifts/recent-signups'),
  getShiftSignups: () => api.get('/rondo/v1/shifts/signups'),
  getShiftCalendar: (params = {}) => api.get('/rondo/v1/shifts/calendar', { params }),
  signupForShift: (shiftId, opts = {}) => api.post(`/rondo/v1/shifts/${shiftId}/signup`, opts),
  cancelShift: (shiftId) => api.post(`/rondo/v1/shifts/${shiftId}/cancel`),
  cancelDienstShift: (shiftId, data = {}) => api.post(`/rondo/v1/shifts/${shiftId}/cancellation`, data),
  removeShiftAssignee: (shiftId, personId) => api.delete(`/rondo/v1/shifts/${shiftId}/assignees/${personId}`),
  addShiftAssignee: (shiftId, data) => api.post(`/rondo/v1/shifts/${shiftId}/assignees`, data),
  getAssignablePeople: (shiftId, search) =>
    api.get(`/rondo/v1/shifts/${shiftId}/assignable-people`, { params: { search } }),
  copyShiftDay: (sourceDate, targetDate) => api.post('/rondo/v1/shifts/copy-day', {
    source_date: sourceDate,
    target_date: targetDate,
  }),

  // Admin no-show endpoint
  markShiftNoShow: (shiftId, personId, opts = {}) => api.post(`/rondo/v1/shifts/${shiftId}/no-show`, { person_id: personId, ...opts }),

  // Volunteer obligations (admin dashboard)
  getVolunteerObligations: (params = {}) => api.get('/rondo/v1/volunteer-obligations', { params }),

  // Dienst types & shifts & templates — admin CRUD via wp/v2 onder de motorkap.
  getDienstTypes: (params = { per_page: 100 }) => api.get('/wp/v2/dienst-types', { params }),
  getDienstType: (id) => api.get(`/wp/v2/dienst-types/${id}`, { params: { context: 'edit' } }),
  createDienstType: (data) => api.post('/wp/v2/dienst-types', data),
  updateDienstType: (id, data) => api.post(`/wp/v2/dienst-types/${id}`, data),
  deleteDienstType: (id) => api.delete(`/wp/v2/dienst-types/${id}`, { params: { force: true } }),
  getShiftTemplates: (params = { per_page: 100 }) => api.get('/wp/v2/shift-templates', { params }),
  getShiftTemplate: (id) => api.get(`/wp/v2/shift-templates/${id}`),
  createShiftTemplate: (data) => api.post('/wp/v2/shift-templates', data),
  updateShiftTemplate: (id, data) => api.post(`/wp/v2/shift-templates/${id}`, data),
  deleteShiftTemplate: (id) => api.delete(`/wp/v2/shift-templates/${id}`, { params: { force: true } }),
  getDienstShifts: (params = { per_page: 100, orderby: 'date', order: 'desc' }) => api.get('/wp/v2/dienst-shifts', { params }),
  getDienstShift: (id) => api.get(`/wp/v2/dienst-shifts/${id}`),
  createDienstShift: (data) => api.post('/wp/v2/dienst-shifts', data),
  updateDienstShift: (id, data) => api.post(`/wp/v2/dienst-shifts/${id}`, data),
  deleteDienstShift: (id) => api.delete(`/wp/v2/dienst-shifts/${id}`, { params: { force: true } }),
  expandShiftTemplates: (until) => api.post('/rondo/v1/shift-templates/expand', { until }),
  rerunShiftTemplate: (id) => api.post(`/rondo/v1/shift-templates/${id}/rerun`),

  // Taakuitleg — volunteer task instructions (rich text + inline images),
  // linked to dienst_types. The QR codes point at the public /uitleg/{slug} page.
  getTaakuitleg: (params = { per_page: 100, orderby: 'title', order: 'asc' }) => api.get('/wp/v2/taakuitleg', { params }),
  getTaakuitlegItem: (id) => api.get(`/wp/v2/taakuitleg/${id}`, { params: { context: 'edit' } }),
  createTaakuitleg: (data) => api.post('/wp/v2/taakuitleg', data),
  updateTaakuitleg: (id, data) => api.post(`/wp/v2/taakuitleg/${id}`, data),
  deleteTaakuitleg: (id) => api.delete(`/wp/v2/taakuitleg/${id}`, { params: { force: true } }),

  // Sportlink sync
  syncFromSportlink: (knvbId) => api.post(
    '/rondo/v1/sportlink/sync-individual',
    { knvb_id: knvbId },
    { timeout: 180000 }
  ),
};
