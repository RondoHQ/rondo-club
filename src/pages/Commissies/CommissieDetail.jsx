import { Link, useParams, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { ArrowLeft, Building2, Globe, Users, GitBranch, Share2 } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { getCommissieName, sanitizeCommissieAcf } from '@/utils/formatters';
import ShareModal from '@/components/ShareModal';
import CustomFieldsSection from '@/components/CustomFieldsSection';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';

export default function CommissieDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [showShareModal, setShowShareModal] = useState(false);
  
  const { data: commissie, isLoading, error } = useQuery({
    queryKey: ['commissie', id],
    queryFn: async () => {
      const response = await wpApi.getCommissie(id, { _embed: true });
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
      const response = await wpApi.getCommissie(commissie.parent, { _embed: true });
      return response.data;
    },
    enabled: !!commissie?.parent,
  });
  
  // Fetch child commissies (subsidiaries)
  const { data: childCommissies = [] } = useQuery({
    queryKey: ['commissie-children', id],
    queryFn: async () => {
      const response = await wpApi.getCommissies({ parent: id, per_page: 100, _embed: true });
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

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['commissies', parseInt(id, 10)] });
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
        <Link to="/commissies" className="btn-secondary mt-4">Terug naar commissies</Link>
      </div>
    );
  }
  
  // Don't render if commissie is trashed (redirect will happen)
  if (commissie.status === 'trash') {
    return null;
  }
  
  const acf = commissie.acf || {};

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
          <button onClick={() => setShowShareModal(true)} className="btn-secondary" title="Delen">
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
            {acf.website && (
              <a 
                href={acf.website} 
                target="_blank" 
                rel="noopener noreferrer"
                className="text-electric-cyan dark:text-electric-cyan hover:underline flex items-center mt-1"
              >
                <Globe className="w-4 h-4 mr-1" />
                {acf.website}
              </a>
            )}
          </div>
        </div>
      </div>
      
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
                  {child.acf?.industry && (
                    <p className="text-xs text-gray-500 dark:text-gray-400">{child.acf.industry}</p>
                  )}
                </div>
              </Link>
            ))}
          </div>
        </div>
      )}
      
      {/* Members */}
      <div className="card p-6">
        <h2 className="font-semibold text-brand-gradient mb-4 flex items-center">
          <Users className="w-5 h-5 mr-2" />
          Leden
        </h2>

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
      {acf.contact_info?.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold text-brand-gradient mb-4">Contactgegevens</h2>
          <div className="space-y-3">
            {acf.contact_info.map((contact, index) => (
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
        acfData={commissie?.acf}
        onUpdate={(newAcfValues) => {
          const acfData = sanitizeCommissieAcf(commissie?.acf, newAcfValues);
          updateCommissie.mutateAsync({ acf: acfData });
        }}
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
