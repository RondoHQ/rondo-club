import { Link, useParams, useNavigate } from 'react-router-dom';
import { useEffect, useMemo, useState, useRef } from 'react';
import { ArrowLeft, Building2, Globe, Users, GitBranch, TrendingUp, User, Camera, Share2 } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { getCommissieName, decodeHtml, sanitizeCommissieAcf } from '@/utils/formatters';
import ShareModal from '@/components/ShareModal';
import CustomFieldsSection from '@/components/CustomFieldsSection';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';

export default function CommissieDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const fileInputRef = useRef(null);
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
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
  
  // Get investor details from embedded data (already included in commissie response)
  const investorIds = commissie?.acf?.investors || [];

  // Extract featured_media IDs from embedded posts for thumbnail fetching
  const embeddedPosts = commissie?._embedded?.['acf:post'] || [];
  const mediaIds = useMemo(() => {
    return embeddedPosts
      .filter(p => investorIds.includes(p.id) && p.featured_media)
      .map(p => p.featured_media);
  }, [embeddedPosts, investorIds]);

  // Fetch thumbnails for investors (embedded posts don't include nested media)
  const { data: mediaItems = [] } = useQuery({
    queryKey: ['investor-media', mediaIds.join(',')],
    queryFn: async () => {
      if (!mediaIds.length) return [];
      const response = await wpApi.getMedia({ include: mediaIds.join(','), per_page: 100 });
      return response.data;
    },
    enabled: mediaIds.length > 0,
    staleTime: 5 * 60 * 1000, // Cache for 5 minutes - media URLs rarely change
  });

  // Build investor details with thumbnails
  const investorDetails = useMemo(() => {
    if (!investorIds.length || !embeddedPosts.length) return [];

    // Create a map of media ID to source URL
    const mediaMap = new Map(mediaItems.map(m => [m.id, m.source_url]));

    // Map investors in the order they appear in investorIds
    return investorIds.map(investorId => {
      const post = embeddedPosts.find(p => p.id === investorId);
      if (!post) return null;

      const isPerson = post.type === 'person';
      const thumbnail = post.featured_media
        ? mediaMap.get(post.featured_media)
        : post.acf?.photo_gallery?.[0]?.url;

      return {
        id: post.id,
        type: post.type,
        name: isPerson
          ? decodeHtml(post.title?.rendered || '')
          : getCommissieName(post),
        thumbnail,
      };
    }).filter(Boolean);
  }, [investorIds, embeddedPosts, mediaItems]);
  
  // Fetch commissies that this commissie has invested in
  const { data: investments = [] } = useQuery({
    queryKey: ['investments', id],
    queryFn: async () => {
      const response = await prmApi.getInvestments(id);
      return response.data;
    },
    enabled: !!id,
  });
  
  
  const updateCommissie = useMutation({
    mutationFn: (data) => wpApi.updateCommissie(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['commissie', id] });
      queryClient.invalidateQueries({ queryKey: ['commissies'] });
      queryClient.invalidateQueries({ queryKey: ['commissie-investors', id] });
      queryClient.invalidateQueries({ queryKey: ['commissie-investors-edit', parseInt(id)] });
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
  
  
  // Handle logo upload
  const handleLogoUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
      alert('Please select an image file');
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('Image size must be less than 5MB');
      return;
    }

    setIsUploadingLogo(true);

    try {
      await prmApi.uploadCommissieLogo(id, file);

      // Invalidate queries to refresh commissie data
      queryClient.invalidateQueries({ queryKey: ['commissie', id] });
      queryClient.invalidateQueries({ queryKey: ['commissies'] });
    } catch {
      alert('Failed to upload logo. Please try again.');
    } finally {
      setIsUploadingLogo(false);
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };
  
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
        <div className="flex items-center gap-4">
          <div className="relative group">
            {commissie._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
              <img 
                src={commissie._embedded['wp:featuredmedia'][0].source_url}
                alt={getCommissieName(commissie)}
                className="w-24 h-24 rounded-lg object-contain"
              />
            ) : (
              <div className="w-24 h-24 bg-white dark:bg-gray-700 rounded-lg flex items-center justify-center border border-gray-200 dark:border-gray-600">
                <Building2 className="w-12 h-12 text-gray-400" />
              </div>
            )}
            {/* Upload overlay */}
            <div 
              className="absolute inset-0 rounded-lg bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 flex items-center justify-center cursor-pointer"
              onClick={() => fileInputRef.current?.click()}
            >
              {isUploadingLogo ? (
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
              ) : (
                <Camera className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
              )}
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={handleLogoUpload}
              className="hidden"
              disabled={isUploadingLogo}
            />
          </div>
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
          <h2 className="font-semibold mb-4 flex items-center">
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
      
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Current Employees */}
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
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
        
        {/* Former Employees */}
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <Users className="w-5 h-5 mr-2" />
            Voormalige leden
          </h2>
          
          {employees?.former?.length > 0 ? (
            <div className="space-y-2">
              {employees.former.map((person) => (
                <Link
                  key={person.id}
                  to={`/people/${person.id}`}
                  className="flex items-center p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  <PersonAvatar
                    thumbnail={person.thumbnail}
                    name={person.name}
                    size="md"
                    className="opacity-75"
                  />
                  <div className="ml-2">
                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{person.name}</p>
                    {person.job_title && (
                      <p className="text-xs text-gray-500 dark:text-gray-400">{person.job_title}</p>
                    )}
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500 dark:text-gray-400">Geen voormalige leden.</p>
          )}
        </div>
      </div>
      
      {/* Investors */}
      {investorDetails.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <TrendingUp className="w-5 h-5 mr-2" />
            Sponsoren
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {investorDetails.map((investor) => {
              const isPerson = investor.type === 'person';
              const isTeam = investor.type === 'team';
              const linkPath = isPerson
                ? `/people/${investor.id}`
                : isTeam
                  ? `/teams/${investor.id}`
                  : `/commissies/${investor.id}`;
              
              return (
                <Link
                  key={`${investor.type}-${investor.id}`}
                  to={linkPath}
                  className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700"
                >
                  {investor.thumbnail ? (
                    <img
                      src={investor.thumbnail}
                      alt={investor.name}
                      loading="lazy"
                      className={`w-10 h-10 object-cover ${isPerson ? 'rounded-full' : 'rounded'}`}
                    />
                  ) : (
                    <div className={`w-10 h-10 bg-gray-100 dark:bg-gray-700 flex items-center justify-center ${isPerson ? 'rounded-full' : 'rounded'}`}>
                      {isPerson ? (
                        <User className="w-5 h-5 text-gray-400" />
                      ) : (
                        <Building2 className="w-5 h-5 text-gray-400" />
                      )}
                    </div>
                  )}
                  <div className="ml-3">
                    <p className="text-sm font-medium">{investor.name}</p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                      {isPerson ? 'Lid' : isTeam ? 'Team' : 'Commissie'}
                    </p>
                  </div>
                </Link>
              );
            })}
          </div>
        </div>
      )}
      
      {/* Invested in (teams/commissies this organization has invested in) */}
      {investments.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <TrendingUp className="w-5 h-5 mr-2" />
            Investeert in
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {investments.map((investment) => {
              const isTeam = investment.type === 'team';
              const linkPath = isTeam
                ? `/teams/${investment.id}`
                : `/commissies/${investment.id}`;
              return (
                <Link
                  key={investment.id}
                  to={linkPath}
                  className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700"
                >
                  {investment.thumbnail ? (
                    <img
                      src={investment.thumbnail}
                      alt={investment.name}
                      loading="lazy"
                      className="w-10 h-10 object-contain rounded"
                    />
                  ) : (
                    <div className="w-10 h-10 bg-gray-100 dark:bg-gray-700 flex items-center justify-center rounded">
                      <Building2 className="w-5 h-5 text-gray-400" />
                    </div>
                  )}
                  <div className="ml-3">
                    <p className="text-sm font-medium">{investment.name}</p>
                    {investment.industry && (
                      <p className="text-xs text-gray-500 dark:text-gray-400">{investment.industry}</p>
                    )}
                  </div>
                </Link>
              );
            })}
          </div>
        </div>
      )}
      
      {/* Contact info */}
      {acf.contact_info?.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4">Contactgegevens</h2>
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
