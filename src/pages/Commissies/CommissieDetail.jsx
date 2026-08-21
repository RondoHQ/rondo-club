import { Link, useParams, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { ArrowLeft, Building2, Globe, Users, GitBranch, Share2, Info, Pencil, Check, X, Clock, CalendarDays, ListOrdered, Mail } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { getCommissieName, sanitizeCommissieFields } from '@/utils/formatters';
import ShareModal from '@/components/ShareModal';
import CustomFieldsSection from '@/components/CustomFieldsSection';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';

const PERIOD_LABELS = {
  week: 'per week',
  maand: 'per maand',
};

/**
 * Editable card showing the Rondo-local commissie information
 * (long description, task description, time investment, member limits).
 *
 * These fields are stored as native field meta on the commissie post and round-trip
 * through the dedicated local-info endpoint. The card has a view mode and an inline edit form.
 */
function CommissieInfoCard({ fields, canEdit, onSave, isSaving }) {
  const [isEditing, setIsEditing] = useState(false);
  const [form, setForm] = useState({});
  const [saveError, setSaveError] = useState('');

  const startEdit = () => {
    setSaveError('');
    setForm({
      lange_omschrijving: fields.lange_omschrijving ?? '',
      taakomschrijving: fields.taakomschrijving ?? '',
      uren_aantal: fields.uren_aantal ?? '',
      uren_periode: fields.uren_periode ?? '',
      dagen_flexibel: fields.dagen_flexibel ?? '',
      max_leden: fields.max_leden ?? '',
      max_wachtlijst: fields.max_wachtlijst ?? '',
    });
    setIsEditing(true);
  };

  const setField = (name, value) => setForm((prev) => ({ ...prev, [name]: value }));

  const handleSave = async () => {
    try {
      setSaveError('');
      await onSave(form);
      setIsEditing(false);
    } catch (error) {
      setSaveError(error?.response?.data?.message || 'Opslaan is niet gelukt. Probeer het opnieuw.');
    }
  };

  const hours = fields.uren_aantal !== '' && fields.uren_aantal !== null && fields.uren_aantal !== undefined
    ? `${fields.uren_aantal} uur ${PERIOD_LABELS[fields.uren_periode] || ''}`.trim()
    : null;

  const hasAnyValue = [
    fields.lange_omschrijving,
    fields.taakomschrijving,
    fields.dagen_flexibel,
  ].some((v) => v != null && v !== '')
    || hours
    || fields.max_leden != null && fields.max_leden !== ''
    || fields.max_wachtlijst != null && fields.max_wachtlijst !== '';

  return (
    <div className="card p-6">
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-semibold text-brand-gradient flex items-center">
          <Info className="w-5 h-5 mr-2" />
          Commissie-informatie
        </h2>
        {canEdit && !isEditing && (
          <button onClick={startEdit} className="btn-tertiary" title="Bewerken">
            <Pencil className="w-4 h-4 mr-2" />
            Bewerken
          </button>
        )}
      </div>

      {isEditing ? (
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Uitgebreide omschrijving
            </label>
            <textarea
              value={form.lange_omschrijving}
              onChange={(e) => setField('lange_omschrijving', e.target.value)}
              rows={5}
              className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
              disabled={isSaving}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Taakomschrijving
            </label>
            <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Wat doet een lid van deze commissie?</p>
            <textarea
              value={form.taakomschrijving}
              onChange={(e) => setField('taakomschrijving', e.target.value)}
              rows={5}
              className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
              disabled={isSaving}
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Geschat aantal uren
              </label>
              <input
                type="number"
                min="0"
                step="0.5"
                value={form.uren_aantal}
                onChange={(e) => setField('uren_aantal', e.target.value)}
                className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
                disabled={isSaving}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Periode
              </label>
              <select
                value={form.uren_periode}
                onChange={(e) => setField('uren_periode', e.target.value)}
                className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
                disabled={isSaving}
              >
                <option value="">—</option>
                <option value="week">Per week</option>
                <option value="maand">Per maand</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Dagen / flexibiliteit
            </label>
            <input
              type="text"
              value={form.dagen_flexibel}
              onChange={(e) => setField('dagen_flexibel', e.target.value)}
              placeholder="Bijv. 'Voornamelijk op zaterdag' of 'Flexibel'"
              className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
              disabled={isSaving}
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Maximum aantal leden
              </label>
              <input
                type="number"
                min="0"
                value={form.max_leden}
                onChange={(e) => setField('max_leden', e.target.value)}
                className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
                disabled={isSaving}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Maximum op wachtlijst
              </label>
              <input
                type="number"
                min="0"
                value={form.max_wachtlijst}
                onChange={(e) => setField('max_wachtlijst', e.target.value)}
                className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-electric-cyan focus:border-electric-cyan dark:bg-gray-700 dark:text-gray-100"
                disabled={isSaving}
              />
            </div>
          </div>

          <div className="flex items-center gap-2 pt-2">
            <button onClick={handleSave} disabled={isSaving} className="btn-primary">
              {isSaving ? (
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2" />
              ) : (
                <Check className="w-4 h-4 mr-2" />
              )}
              Opslaan
            </button>
            <button onClick={() => setIsEditing(false)} disabled={isSaving} className="btn-tertiary">
              <X className="w-4 h-4 mr-2" />
              Annuleren
            </button>
          </div>
          {saveError && (
            <p className="text-sm text-red-600 dark:text-red-400" role="alert">{saveError}</p>
          )}
        </div>
      ) : (
        <div className="space-y-4">
          {!hasAnyValue && (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {canEdit
                ? <>Nog geen informatie ingevuld. Klik op &lsquo;Bewerken&rsquo; om dit aan te vullen.</>
                : 'Nog geen informatie ingevuld.'}
            </p>
          )}

          {fields.lange_omschrijving && (
            <div>
              <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Omschrijving</h3>
              <p className="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{fields.lange_omschrijving}</p>
            </div>
          )}

          {fields.taakomschrijving && (
            <div>
              <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Taakomschrijving</h3>
              <p className="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{fields.taakomschrijving}</p>
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {hours && (
              <div className="flex items-center text-sm text-gray-600 dark:text-gray-400">
                <Clock className="w-4 h-4 mr-2 text-gray-400" />
                <span><span className="font-medium text-gray-700 dark:text-gray-300">Tijdsinvestering:</span> {hours}</span>
              </div>
            )}
            {fields.dagen_flexibel && (
              <div className="flex items-center text-sm text-gray-600 dark:text-gray-400">
                <CalendarDays className="w-4 h-4 mr-2 text-gray-400" />
                <span><span className="font-medium text-gray-700 dark:text-gray-300">Dagen:</span> {fields.dagen_flexibel}</span>
              </div>
            )}
            {fields.max_leden != null && fields.max_leden !== '' && (
              <div className="flex items-center text-sm text-gray-600 dark:text-gray-400">
                <Users className="w-4 h-4 mr-2 text-gray-400" />
                <span><span className="font-medium text-gray-700 dark:text-gray-300">Max. leden:</span> {fields.max_leden}</span>
              </div>
            )}
            {fields.max_wachtlijst != null && fields.max_wachtlijst !== '' && (
              <div className="flex items-center text-sm text-gray-600 dark:text-gray-400">
                <ListOrdered className="w-4 h-4 mr-2 text-gray-400" />
                <span><span className="font-medium text-gray-700 dark:text-gray-300">Max. wachtlijst:</span> {fields.max_wachtlijst}</span>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default function CommissieDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [showShareModal, setShowShareModal] = useState(false);
  const { data: currentUser } = useCurrentUser();
  
  const { data: commissie, isLoading, error } = useQuery({
    queryKey: ['commissie', id],
    queryFn: async () => {
      const response = await wpApi.getCommissie(id, {
        _fields: 'id,title,status,parent,fields',
      });
      return response.data;
    },
  });
  
  const { data: employees } = useQuery({
    queryKey: ['commissie-people', id],
    queryFn: async () => {
      const response = await prmApi.getCommissiePeople(id);
      return response.data;
    },
  });
  
  // Fetch parent commissie if exists
  const { data: parentCommissie } = useQuery({
    queryKey: ['commissie', commissie?.parent],
    queryFn: async () => {
      const response = await wpApi.getCommissie(commissie.parent, { _fields: 'id,title' });
      return response.data;
    },
    enabled: !!commissie?.parent,
  });
  
  // Fetch child commissies (subsidiaries)
  const { data: childCommissies = [] } = useQuery({
    queryKey: ['commissie-children', id],
    queryFn: async () => {
      const response = await wpApi.getCommissies({
        parent: id,
        per_page: 100,
        _embed: true,
        _fields: 'id,title,featured_media,_links,_embedded',
      });
      return response.data;
    },
  });
  
  const updateCommissie = useMutation({
    mutationFn: (data) => wpApi.updateCommissie(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['commissie', id] });
      queryClient.invalidateQueries({ queryKey: ['commissies'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });

  const updateCommissieInfo = useMutation({
    mutationFn: (fields) => prmApi.updateCommissieInfo(id, fields),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['commissie', id] }),
        queryClient.invalidateQueries({ queryKey: ['commissies'] }),
      ]);
    },
  });

  const handleRefresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['commissie', id] }),
      queryClient.invalidateQueries({ queryKey: ['commissie-people', id] }),
      queryClient.invalidateQueries({ queryKey: ['commissie-children', id] }),
    ]);
  };

  // Update document title with commissie's name - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(getCommissieName(commissie) || 'Organization');
  
  // Redirect if commissie is trashed
  useEffect(() => {
    if (commissie?.status === 'trash') {
      navigate('/commissies', { replace: true });
    }
  }, [commissie, navigate]);
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }
  
  if (error || !commissie) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400">Commissie kon niet worden geladen.</p>
        <Link to="/commissies" className="btn-tertiary mt-4">Terug naar commissies</Link>
      </div>
    );
  }
  
  // Don't render if commissie is trashed (redirect will happen)
  if (commissie.status === 'trash') {
    return null;
  }
  
  const fields = commissie.fields || {};
  const memberEmails = [...new Set(
    (employees?.current || [])
      .map((person) => person.email?.trim().toLowerCase())
      .filter(Boolean)
  )];
  const memberMailto = memberEmails.length > 0
    ? `mailto:${memberEmails.join(',')}?subject=${encodeURIComponent(getCommissieName(commissie))}`
    : null;

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
        <Link to="/commissies" className="flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Terug naar commissies</span>
        </Link>
        <div className="flex gap-2">
          <button onClick={() => setShowShareModal(true)} className="btn-tertiary" title="Delen">
            <Share2 className="w-4 h-4 mr-2" />
            Delen
          </button>
        </div>
      </div>
      
      {/* Commissie header */}
      <div className="card p-6">
        <div>
          <div>
            {/* Parent commissie link */}
            {parentCommissie && (
              <Link 
                to={`/commissies/${parentCommissie.id}`}
                className="text-sm text-electric-cyan dark:text-electric-cyan hover:underline flex items-center mb-1"
              >
                <GitBranch className="w-3 h-3 mr-1" />
                Subcommissie van {getCommissieName(parentCommissie)}
              </Link>
            )}
            <h1 className="text-2xl font-bold text-brand-gradient">{getCommissieName(commissie)}</h1>
            {fields.website && (
              <a 
                href={fields.website}
                target="_blank" 
                rel="noopener noreferrer"
                className="text-electric-cyan dark:text-electric-cyan hover:underline flex items-center mt-1"
              >
                <Globe className="w-4 h-4 mr-1" />
                {fields.website}
              </a>
            )}
          </div>
        </div>
      </div>

      {/* Rondo-local commissie information */}
      <CommissieInfoCard
        fields={fields}
        canEdit={currentUser?.can_edit_commissie_info ?? false}
        isSaving={updateCommissieInfo.isPending}
        onSave={(values) => {
          const fieldData = sanitizeCommissieFields(commissie?.fields, values);
          return updateCommissieInfo.mutateAsync(fieldData);
        }}
      />

      {/* Subsidiaries */}
      {childCommissies.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold text-brand-gradient mb-4 flex items-center">
            <GitBranch className="w-5 h-5 mr-2" />
            Subcommissies
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {childCommissies.map((child) => (
              <Link
                key={child.id}
                to={`/commissies/${child.id}`}
                className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700"
              >
                {child._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                  <img
                    src={child._embedded['wp:featuredmedia'][0].source_url}
                    alt={getCommissieName(child)}
                    className="w-10 h-10 rounded object-contain "
                  />
                ) : (
                  <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center">
                    <Building2 className="w-5 h-5 text-gray-400" />
                  </div>
                )}
                <div className="ml-3">
                  <p className="text-sm font-medium">{getCommissieName(child)}</p>
                </div>
              </Link>
            ))}
          </div>
        </div>
      )}
      
      {/* Members */}
      <div className="card p-6">
        <div className="flex items-center justify-between gap-4 mb-4">
          <h2 className="font-semibold text-brand-gradient flex items-center">
            <Users className="w-5 h-5 mr-2" />
            Leden
          </h2>
          {memberMailto && (
            <a
              href={memberMailto}
              className="btn-tertiary shrink-0"
              title={`E-mail opstellen aan ${memberEmails.length} commissieleden`}
            >
              <Mail className="w-4 h-4 mr-2" />
              E-mail leden
            </a>
          )}
        </div>

        {employees?.current?.length > 0 ? (
          <div className="space-y-2">
            {employees.current.map((person) => (
              <Link
                key={person.id}
                to={`/people/${person.id}`}
                className="flex items-center p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                <PersonAvatar
                  thumbnail={person.thumbnail}
                  name={person.name}
                  size="md"
                />
                <div className="ml-2">
                  <p className="text-sm font-medium">{person.name}</p>
                  {person.job_title && (
                    <p className="text-xs text-gray-500 dark:text-gray-400">{person.job_title}</p>
                  )}
                </div>
              </Link>
            ))}
          </div>
        ) : (
          <p className="text-sm text-gray-500 dark:text-gray-400">Geen leden.</p>
        )}
      </div>
      
      {/* Contact info */}
      {fields.contact_info?.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold text-brand-gradient mb-4">Contactgegevens</h2>
          <div className="space-y-3">
            {fields.contact_info.map((contact, index) => (
              <div key={index}>
                <span className="text-sm text-gray-500 dark:text-gray-400">
                  {contact.contact_label || contact.contact_type}:
                </span>
                <span className="ml-2">{contact.contact_value}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Custom Fields */}
      <CustomFieldsSection
        postType="commissie"
        postId={parseInt(id)}
        fieldData={commissie?.fields}
        onUpdate={currentUser?.is_admin ? (newFieldValues) => {
          const fieldData = sanitizeCommissieFields(commissie?.fields, newFieldValues);
          updateCommissie.mutateAsync({ fields: fieldData });
        } : undefined}
        isUpdating={updateCommissie.isPending}
      />

      <ShareModal
        isOpen={showShareModal}
        onClose={() => setShowShareModal(false)}
        postType="commissies"
        postId={commissie.id}
        postTitle={getCommissieName(commissie)}
      />
      </div>
    </PullToRefreshWrapper>
  );
}
