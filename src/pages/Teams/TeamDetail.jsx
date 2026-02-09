import { Link, useParams, useNavigate } from 'react-router-dom';
import { useEffect, useMemo, useState, useRef } from 'react';
import { ArrowLeft, Building2, Globe, Users, GitBranch, TrendingUp, User, Camera, Share2 } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useVolunteerRoleSettings } from '@/hooks/useVolunteerRoleSettings';
import { getTeamName, decodeHtml, sanitizeTeamAcf } from '@/utils/formatters';
import ShareModal from '@/components/ShareModal';
import CustomFieldsSection from '@/components/CustomFieldsSection';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import PersonAvatar from '@/components/PersonAvatar';

export default function TeamDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const fileInputRef = useRef(null);
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
  const [showShareModal, setShowShareModal] = useState(false);
  
  const { data: team, isLoading, error } = useQuery({
    queryKey: ['team', id],
    queryFn: async () => {
      const response = await wpApi.getTeam(id, { _embed: true });
      return response.data;
    },
  });
  
  const { data: employees } = useQuery({
    queryKey: ['team-people', id],
    queryFn: async () => {
      const response = await prmApi.getTeamPeople(id);
      return response.data;
    },
  });

  // Fetch role settings for player/staff split
  const { data: roleSettings } = useVolunteerRoleSettings();

  // Fetch parent team if exists
  const { data: parentTeam } = useQuery({
    queryKey: ['team', team?.parent],
    queryFn: async () => {
      const response = await wpApi.getTeam(team.parent, { _embed: true });
      return response.data;
    },
    enabled: !!team?.parent,
  });
  
  // Fetch child teams (subsidiaries)
  const { data: childTeams = [] } = useQuery({
    queryKey: ['team-children', id],
    queryFn: async () => {
      const response = await wpApi.getTeams({ parent: id, per_page: 100, _embed: true });
      return response.data;
    },
  });
  
  // Get investor details from embedded data (already included in team response)
  const investorIds = team?.acf?.investors || [];

  // Extract featured_media IDs from embedded posts for thumbnail fetching
  const embeddedPosts = team?._embedded?.['acf:post'] || [];
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
          : getTeamName(post),
        thumbnail,
      };
    }).filter(Boolean);
  }, [investorIds, embeddedPosts, mediaItems]);
  
  // Fetch teams that this team has invested in
  const { data: investments = [] } = useQuery({
    queryKey: ['investments', id],
    queryFn: async () => {
      const response = await prmApi.getInvestments(id);
      return response.data;
    },
    enabled: !!id,
  });
  
  
  const updateTeam = useMutation({
    mutationFn: (data) => wpApi.updateTeam(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['team', id] });
      queryClient.invalidateQueries({ queryKey: ['teams'] });
      queryClient.invalidateQueries({ queryKey: ['team-investors', id] });
      queryClient.invalidateQueries({ queryKey: ['team-investors-edit', parseInt(id)] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['teams', parseInt(id, 10)] });
  };

  // Update document title with team's name - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(getTeamName(team) || 'Team');

  // Redirect if team is trashed
  useEffect(() => {
    if (team?.status === 'trash') {
      navigate('/teams', { replace: true });
    }
  }, [team, navigate]);

  
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
      await prmApi.uploadTeamLogo(id, file);

      // Invalidate queries to refresh team data
      queryClient.invalidateQueries({ queryKey: ['team', id] });
      queryClient.invalidateQueries({ queryKey: ['teams'] });
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
  
  if (error || !team) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400">Team kon niet worden geladen.</p>
        <Link to="/teams" className="btn-secondary mt-4">Terug naar teams</Link>
      </div>
    );
  }
  
  // Don't render if team is trashed (redirect will happen)
  if (team.status === 'trash') {
    return null;
  }
  
  const acf = team.acf || {};

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
        <Link to="/teams" className="flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Terug naar teams</span>
        </Link>
        <div className="flex gap-2">
          <button onClick={() => setShowShareModal(true)} className="btn-secondary" title="Delen">
            <Share2 className="w-4 h-4 mr-2" />
            Delen
          </button>
        </div>
      </div>
      
      {/* Team header */}
      <div className="card p-6">
        <div className="flex items-center gap-4">
          <div className="relative group">
            {team._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
              <img 
                src={team._embedded['wp:featuredmedia'][0].source_url}
                alt={getTeamName(team)}
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
            {/* Parent team link */}
            {parentTeam && (
              <Link
                to={`/teams/${parentTeam.id}`}
                className="text-sm text-electric-cyan dark:text-electric-cyan hover:underline flex items-center mb-1"
              >
                <GitBranch className="w-3 h-3 mr-1" />
                Onderdeel van {getTeamName(parentTeam)}
              </Link>
            )}
            <h1 className="text-2xl font-bold text-brand-gradient">{getTeamName(team)}</h1>
            {/* Subtitle: Activiteit - Gender */}
            {(acf.activiteit || acf.gender) && (
              <p className="text-gray-500 dark:text-gray-400">
                {[acf.activiteit, acf.gender].filter(Boolean).join(' - ')}
              </p>
            )}
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
      {childTeams.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <GitBranch className="w-5 h-5 mr-2" />
            Subteams
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {childTeams.map((child) => (
              <Link
                key={child.id}
                to={`/teams/${child.id}`}
                className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700"
              >
                {child._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                  <img
                    src={child._embedded['wp:featuredmedia'][0].source_url}
                    alt={getTeamName(child)}
                    className="w-10 h-10 rounded object-contain "
                  />
                ) : (
                  <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center">
                    <Building2 className="w-5 h-5 text-gray-400" />
                  </div>
                )}
                <div className="ml-3">
                  <p className="text-sm font-medium">{getTeamName(child)}</p>
                  {child.acf?.industry && (
                    <p className="text-xs text-gray-500 dark:text-gray-400">{child.acf.industry}</p>
                  )}
                </div>
              </Link>
            ))}
          </div>
        </div>
      )}
      
      {/* Members section - 3 columns: Staf, Spelers, Custom Fields */}
      {(() => {
        // Player roles that identify someone as a player vs staff
        const playerRoles = roleSettings?.player_roles || [];
        const isPlayerRole = (jobTitle) => playerRoles.includes(jobTitle);

        // Split current members into players and staff
        const players = employees?.current?.filter(p => isPlayerRole(p.job_title)) || [];
        const staff = employees?.current?.filter(p => !isPlayerRole(p.job_title)) || [];

        // Only show the section if there are any members
        const hasAnyMembers = players.length > 0 || staff.length > 0;

        if (!hasAnyMembers) return null;

        return (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Staf */}
            <div className="card p-6">
              <h2 className="font-semibold mb-4 flex items-center">
                <Users className="w-5 h-5 mr-2" />
                Staf
              </h2>

              {staff.length > 0 ? (
                <div className="space-y-2">
                  {staff.map((person) => (
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
                <p className="text-sm text-gray-500 dark:text-gray-400">Geen staf.</p>
              )}
            </div>

            {/* Spelers */}
            <div className="card p-6">
              <h2 className="font-semibold mb-4 flex items-center">
                <Users className="w-5 h-5 mr-2" />
                Spelers
              </h2>

              {players.length > 0 ? (
                <div className="space-y-2">
                  {players.map((person) => (
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
                <p className="text-sm text-gray-500 dark:text-gray-400">Geen spelers.</p>
              )}
            </div>

            {/* Custom Fields */}
            <CustomFieldsSection
              postType="team"
              postId={parseInt(id)}
              acfData={team?.acf}
              onUpdate={(newAcfValues) => {
                const acfData = sanitizeTeamAcf(team?.acf, newAcfValues);
                updateTeam.mutateAsync({ acf: acfData });
              }}
              isUpdating={updateTeam.isPending}
            />
          </div>
        );
      })()}
      
      {/* Sponsors */}
      {investorDetails.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <TrendingUp className="w-5 h-5 mr-2" />
            Sponsoren
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {investorDetails.map((investor) => {
              const isPerson = investor.type === 'person';
              const isCommissie = investor.type === 'commissie';
              const linkPath = isPerson
                ? `/people/${investor.id}`
                : isCommissie
                  ? `/commissies/${investor.id}`
                  : `/teams/${investor.id}`;

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
                      {isPerson ? 'Lid' : isCommissie ? 'Commissie' : 'Team'}
                    </p>
                  </div>
                </Link>
              );
            })}
          </div>
        </div>
      )}
      
      {/* Invested in (teams/commissies this team sponsors) */}
      {investments.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <TrendingUp className="w-5 h-5 mr-2" />
            Investeert in
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {investments.map((investment) => {
              const isCommissie = investment.type === 'commissie';
              const linkPath = isCommissie
                ? `/commissies/${investment.id}`
                : `/teams/${investment.id}`;
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

      <ShareModal
        isOpen={showShareModal}
        onClose={() => setShowShareModal(false)}
        postType="teams"
        postId={team.id}
        postTitle={getTeamName(team)}
      />
      </div>
    </PullToRefreshWrapper>
  );
}
