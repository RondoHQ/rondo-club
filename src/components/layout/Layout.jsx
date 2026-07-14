import { useState, useRef, useEffect, useMemo } from 'react';
import { Link, NavLink, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Users,
  Award,
  Building2,
  Settings,
  Menu,
  X,
  Home,
  IdCard,
  LogOut,
  Search,
  CheckSquare,
  Command,
  UsersRound,
  MessageSquare,
  MessageSquarePlus,
  User,
  FileCheck,
  QrCode,
  Coins,
  Gavel,
  Wallet,
  Receipt,
  Shirt,
  UserPlus,
  HeartHandshake,
  Wine,
  CalendarClock,
  BookOpen,
  ChevronRight
} from 'lucide-react';

// Wordmark URLs from theme directory.
const getLightLogoUrl = () => `${window.rondoConfig?.themeUrl || ''}/rondo-logo-wordmark.svg`;
const getDarkLogoUrl = () => `${window.rondoConfig?.themeUrl || ''}/rondo_logo_white.svg`;
import { useAuth } from '@/hooks/useAuth';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import { useSearch, useDashboard } from '@/hooks/useDashboard';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import FeedbackModal from '@/components/FeedbackModal';
import { useCreateFeedback } from '@/hooks/useFeedback';

import { useVOGCount } from '@/hooks/useVOGCount';
import { useDisciplineCasesCount } from '@/hooks/useDisciplineCases';
import { prmApi } from '@/api/client';

const navigation = [
  { name: 'Mijn inschrijftaken', href: '/vrijwillig', icon: HeartHandshake },
  { name: 'Mijn gegevens', href: '/mijn-gegevens', icon: IdCard, memberOnly: true },
  { name: 'Dashboard', href: '/', icon: Home, requiresKader: true },
  { name: 'Relaties', href: '/people', icon: Users, requiresKader: true },
  { name: 'Onboarding', href: '/people/onboarding', icon: UserPlus, indent: true, requiresLedenadministratie: true },
  { name: 'Jubilarissen', href: '/people/jubilarissen', icon: Award, indent: true, requiresKader: true },
  { name: 'Tuchtzaken', href: '/tuchtzaken', icon: Gavel, indent: true, requiresFairplay: true },
  { name: 'Teams', href: '/teams', icon: Building2, requiresKader: true },
  { name: 'Kaderlijst', href: '/kaderlijst', icon: Users, indent: true, requiresKader: true },
  { name: 'Kleding', href: '/kleding', icon: Shirt, requiresClothing: true },
  { name: 'Commissies', href: '/commissies', icon: UsersRound, requiresKader: true },
  { name: 'Vrijwilligers', href: '/vrijwilligers', icon: HeartHandshake, requiresVrijwilligers: true },
  { name: 'VOG', href: '/vrijwilligers/vog', icon: FileCheck, indent: true, requiresVOG: true },
  { name: 'IVA', href: '/vrijwilligers/iva', icon: Wine, indent: true, requiresVrijwilligers: true },
  { name: 'Inschrijftaken', href: '/vrijwilligers/diensten', icon: CalendarClock, indent: true, requiresVrijwilligers: true },
  { name: 'Taakuitleg', href: '/vrijwilligers/taakuitleg', icon: BookOpen, indent: true, requiresVrijwilligers: true },
  { name: 'Vrijstellingen', href: '/vrijwilligers/vrijstellingen', icon: UsersRound, indent: true, requiresVrijwilligers: true },
  { name: 'Financiën', href: '/financien', icon: Wallet, requiresFinancieel: true },
  { name: 'Contributie', href: '/financien/contributie', icon: Coins, indent: true, requiresFinancieel: true },
  { name: 'Facturen', href: '/financien/facturen', icon: Receipt, indent: true, requiresFinancieel: true },
  { name: 'Lidpas Scanner', href: '/lidpas-scanner', icon: QrCode, requiresToegangscontrole: true, mobileOnly: true },
  { name: 'Taken', href: '/todos', icon: CheckSquare, requiresKader: true },
  { name: 'Feedback', href: '/feedback', icon: MessageSquare, requiresKader: true },
  { name: 'Instellingen', href: '/settings', icon: Settings, requiresKader: true },
  { name: 'Profiel', href: '/profile', icon: User },
];

function Sidebar({ mobile = false, onClose, stats }) {
  const { logoutUrl } = useAuth();
  const { notSubmittedToJustis, submittedToJustis } = useVOGCount();
  const { count: disciplineCasesCount } = useDisciplineCasesCount();

  // Fetch current user for capability check
  const { data: currentUser } = useCurrentUser();

  const canAccessFairplay = currentUser?.can_access_fairplay ?? false;
  const canAccessVOG = currentUser?.can_access_vog ?? false;
  const canAccessFinancieel = currentUser?.can_access_financieel ?? false;
  const canAccessToegangscontrole = currentUser?.can_access_toegangscontrole ?? false;
  const canAccessClothing = currentUser?.can_access_clothing ?? false;
  const canAccessLedenadministratie = currentUser?.can_access_ledenadministratie ?? false;
  const canAccessVrijwilligers = currentUser?.can_access_vrijwilligers ?? false;
  const isAdmin = currentUser?.is_admin ?? false;

  // `isKader` = iedereen met een staf-rol. Plain leden (account zonder
  // expliciete rechten) zien alleen hun eigen items in de zijbalk. Server-side
  // bepaald, net als in router.jsx — leid het hier niet opnieuw af.
  const isKader = currentUser?.is_kader ?? false;

  // Finance menu counters
  const { data: invoiceData = [] } = useQuery({
    queryKey: ['sidebar', 'invoices'],
    queryFn: async () => {
      const response = await prmApi.getInvoices({});
      return response.data;
    },
    enabled: canAccessFinancieel,
    staleTime: 30000,
  });

  const { data: feeData } = useQuery({
    queryKey: ['sidebar', 'fees'],
    queryFn: async () => {
      const response = await prmApi.getFeeList();
      return response.data;
    },
    enabled: canAccessFinancieel,
    staleTime: 30000,
  });

  const openInvoicesCount = useMemo(
    () => invoiceData.filter((invoice) => invoice.status === 'sent' || invoice.status === 'overdue').length,
    [invoiceData]
  );

  const payingMembersCount = useMemo(
    () => (feeData?.members || []).filter((member) => (parseFloat(member.final_fee) || 0) > 0).length,
    [feeData]
  );

  const formatMenuCount = (count) => (
    typeof count === 'number' ? count.toLocaleString('nl-NL') : count
  );

  // Map navigation items to their counts
  const getCounts = (name) => {
    switch (name) {
      case 'Relaties': return stats?.total_people || null;
      case 'Teams': return stats?.total_teams || null;
      case 'Commissies': return stats?.total_commissies || null;
      case 'Taken': return stats?.open_todos_count || null;
      case 'Feedback': return stats?.open_feedback_count || null;
      case 'Contributie': return canAccessFinancieel ? payingMembersCount : null;
      case 'Facturen': return canAccessFinancieel ? openInvoicesCount : null;
      case 'VOG': {
        // Show two counts: not submitted to Justis | total needing VOG
        const totaal = notSubmittedToJustis + submittedToJustis;
        return totaal > 0 ? `${notSubmittedToJustis} | ${totaal}` : null;
      }
      case 'Tuchtzaken': return disciplineCasesCount > 0 ? disciplineCasesCount : null;
      default: return null;
    }
  };

  // Collapsible sections — persist collapsed state across visits.
  const COLLAPSE_STORAGE_KEY = 'rondo:sidebar:collapsed';
  const [collapsedSections, setCollapsedSections] = useState(() => {
    try {
      const stored = localStorage.getItem(COLLAPSE_STORAGE_KEY);
      return stored ? JSON.parse(stored) : {};
    } catch {
      return {};
    }
  });

  useEffect(() => {
    try {
      localStorage.setItem(COLLAPSE_STORAGE_KEY, JSON.stringify(collapsedSections));
    } catch {
      // Ignore storage failures (private mode, quota).
    }
  }, [collapsedSections]);

  const toggleSection = (name) => {
    setCollapsedSections((prev) => ({ ...prev, [name]: !prev[name] }));
  };

  // The section containing the current route always shows expanded.
  const location = useLocation();
  const isHrefActive = (href) => {
    if (!href) return false;
    if (href === '/') return location.pathname === '/';
    return location.pathname === href || location.pathname.startsWith(`${href}/`);
  };

  // Filter to the items this user may see, then group each top-level item
  // with its following indented sub-items into a collapsible section.
  const visibleNav = navigation.filter((item) => {
    // Enforce mobile-only items regardless of role.
    if (item.mobileOnly && !mobile) return false;
    if (isAdmin) return true;
    if (item.adminOnly && !isAdmin) return false;
    if (item.requiresFairplay && !canAccessFairplay) return false;
    if (item.requiresVOG && !canAccessVOG) return false;
    if (item.requiresFinancieel && !canAccessFinancieel) return false;
    if (item.requiresToegangscontrole && !canAccessToegangscontrole) return false;
    if (item.requiresClothing && !canAccessClothing) return false;
    if (item.requiresLedenadministratie && !canAccessLedenadministratie) return false;
    if (item.requiresVrijwilligers && !canAccessVrijwilligers) return false;
    if (item.requiresKader && !isKader) return false;
    // Kader normally does not need the member-facing household page.
    if (item.memberOnly && isKader) return false;
    return true;
  });

  const navGroups = [];
  for (const item of visibleNav) {
    if (item.indent && navGroups.length > 0) {
      navGroups[navGroups.length - 1].children.push(item);
    } else {
      navGroups.push({ parent: item, children: [] });
    }
  }

  // Renders a single nav entry (section header, disabled item, or link).
  const renderItem = (item) => {
    const count = getCounts(item.name);

    if (item.type === 'section') {
      return (
        <div className="flex items-center px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider dark:text-gray-500">
          <item.icon className="w-4 h-4 mr-2" />
          {item.name}
        </div>
      );
    }

    if (item.disabled) {
      return (
        <div
          className={`flex items-center py-2 text-sm font-medium rounded-lg opacity-50 cursor-default pointer-events-none ${
            item.indent ? 'pl-8 pr-3' : 'px-3'
          } text-gray-700 dark:text-gray-200`}
        >
          <item.icon className="w-5 h-5 mr-3" />
          {item.name}
        </div>
      );
    }

    return (
      <NavLink
        to={item.href}
        onClick={mobile ? onClose : undefined}
        className={({ isActive }) =>
          `flex items-center py-2 text-sm font-medium rounded-lg transition-colors ${
            item.indent ? 'pl-8 pr-3' : 'px-3'
          } ${
            isActive
              ? 'bg-cyan-50 text-bright-cobalt dark:bg-gray-700 dark:text-electric-cyan dark:border-l-2 dark:border-electric-cyan'
              : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700'
          }`
        }
      >
        <item.icon className="w-5 h-5 mr-3" />
        {item.name}
        {count != null && (
          <span className="ml-auto text-xs text-gray-400 dark:text-gray-500">{formatMenuCount(count)}</span>
        )}
      </NavLink>
    );
  };

  return (
    <div className="flex flex-col h-full bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700">
      {/* Logo */}
      <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700">
        <Link to="/" className="flex items-center">
          <img src={getLightLogoUrl()} alt="Rondo Club" className="h-10 w-auto object-contain shrink-0 dark:hidden" />
          <img src={getDarkLogoUrl()} alt="Rondo Club" className="hidden h-10 w-auto object-contain shrink-0 dark:block" />
        </Link>
        {mobile && (
          <button onClick={onClose} className="p-2 -mr-2 dark:text-gray-300">
            <X className="w-5 h-5" />
          </button>
        )}
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
        {navGroups.map(({ parent, children }) => {
          // Top-level item with no sub-items: render as-is.
          if (children.length === 0) {
            return <div key={parent.href || parent.name}>{renderItem(parent)}</div>;
          }

          // Section with sub-items: collapsible, but always open when active.
          const groupActive = isHrefActive(parent.href) || children.some((c) => isHrefActive(c.href));
          const expanded = groupActive || !collapsedSections[parent.name];

          return (
            <div key={parent.href || parent.name} className="space-y-1">
              <div className="flex items-center">
                <div className="flex-1 min-w-0">{renderItem(parent)}</div>
                <button
                  type="button"
                  onClick={() => toggleSection(parent.name)}
                  aria-expanded={expanded}
                  aria-label={`${expanded ? 'Inklappen' : 'Uitklappen'}: ${parent.name}`}
                  className="flex-shrink-0 p-1.5 ml-1 rounded-lg text-gray-400 transition-colors hover:bg-gray-50 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                >
                  <ChevronRight className={`w-4 h-4 transition-transform ${expanded ? 'rotate-90' : ''}`} />
                </button>
              </div>
              {expanded && children.map((child) => (
                <div key={child.href || child.name}>{renderItem(child)}</div>
              ))}
            </div>
          );
        })}
      </nav>

      {/* User identity + Logout */}
      <div className="p-4 border-t border-gray-200 dark:border-gray-700">
        {/* User identity row */}
        {window.rondoConfig?.isDemoUser ? (
          <div className="flex items-center gap-3 px-1 mb-3">
            {currentUser?.linked_person_photo ? (
              <img
                src={currentUser.linked_person_photo}
                alt={currentUser?.name || ''}
                className="w-8 h-8 rounded-full object-cover flex-shrink-0"
              />
            ) : (
              <div className="w-8 h-8 rounded-full bg-cyan-100 dark:bg-obsidian flex items-center justify-center flex-shrink-0">
                <User className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan-light" />
              </div>
            )}
            {currentUser?.name && (
              <span className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                {currentUser.name}
              </span>
            )}
          </div>
        ) : (
          <Link to="/profile" className="flex items-center gap-3 px-1 mb-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            {currentUser?.linked_person_photo ? (
              <img
                src={currentUser.linked_person_photo}
                alt={currentUser?.name || ''}
                className="w-8 h-8 rounded-full object-cover flex-shrink-0"
              />
            ) : (
              <div className="w-8 h-8 rounded-full bg-cyan-100 dark:bg-obsidian flex items-center justify-center flex-shrink-0">
                <User className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan-light" />
              </div>
            )}
            {currentUser?.name && (
              <span className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                {currentUser.name}
              </span>
            )}
          </Link>
        )}
        {/* Logout link — unchanged styling */}
        <a href={logoutUrl} className="flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-colors dark:text-gray-200 dark:hover:bg-gray-700">
          <LogOut className="w-5 h-5 mr-3" />
          Uitloggen
        </a>
      </div>
    </div>
  );
}

function SearchModal({ isOpen, onClose }) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const navigate = useNavigate();
  const inputRef = useRef(null);
  
  // Use search hook
  const trimmedQuery = searchQuery.trim();
  const { data: searchResults, isLoading: isSearchLoading } = useSearch(trimmedQuery);
  
  // Safe results
  const safeResults = searchResults || { people: [], teams: [], invoices: [] };
  const allResults = [
    ...(safeResults.people || []).map(p => ({ ...p, type: 'person' })),
    ...(safeResults.teams || []).map(c => ({ ...c, type: 'team' })),
    ...(safeResults.invoices || []).map(i => ({ ...i, type: 'invoice' })),
  ];
  const hasResults = allResults.length > 0;
  const showResults = searchQuery.trim().length >= 2;
  
  // Reset state when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      setSearchQuery('');
      setSelectedIndex(0);
      // Focus input after a short delay to ensure modal is rendered
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [isOpen]);
  
  // Reset selection when results change
  useEffect(() => {
    setSelectedIndex(0);
  }, [searchResults]);
  
  // Handle keyboard navigation
  const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
      onClose();
      return;
    }
    
    if (!showResults || !hasResults) return;
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex(prev => Math.min(prev + 1, allResults.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex(prev => Math.max(prev - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const selected = allResults[selectedIndex];
      if (selected) {
        handleResultClick(selected.type, selected.id);
      }
    }
  };
  
  // Handle result click
  const handleResultClick = (type, id) => {
    onClose();
    if (type === 'person') {
      navigate(`/people/${id}`);
    } else if (type === 'team') {
      navigate(`/teams/${id}`);
    } else if (type === 'invoice') {
      navigate(`/financien/facturen/${id}`);
    }
  };
  
  if (!isOpen) return null;
  
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
      />
      
      {/* Modal */}
      <div className="relative min-h-screen flex items-start justify-center pt-[15vh] px-4">
        <div className="relative w-full max-w-xl bg-white rounded-xl shadow-2xl overflow-hidden dark:bg-gray-800">
          {/* Search input */}
          <div className="flex items-center px-4 border-b border-gray-200 dark:border-gray-700">
            <Search className="w-5 h-5 text-gray-400 flex-shrink-0" />
            <input
              ref={inputRef}
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Zoek relaties en teams..."
              className="flex-1 px-4 py-4 text-lg outline-none placeholder:text-gray-400 bg-transparent dark:text-gray-100"
              autoComplete="off"
            />
            <div className="flex items-center gap-1 text-xs text-gray-400">
              <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">esc</kbd>
              <span>to close</span>
            </div>
          </div>
          
          {/* Results */}
          <div className="max-h-96 overflow-y-auto">
            {!showResults ? (
              <div className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                <Search className="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                <p className="text-sm">Typ minimaal 2 tekens om te zoeken</p>
              </div>
            ) : isSearchLoading ? (
              <div className="px-4 py-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan mx-auto"></div>
                <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">Zoeken...</p>
              </div>
            ) : hasResults ? (
              <div className="py-2">
                {/* People results */}
                {safeResults.people && safeResults.people.length > 0 && (
                  <div className="px-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide dark:text-gray-400">
                      Relaties
                    </div>
                    {safeResults.people.map((person, index) => {
                      const globalIndex = index;
                      const isSelected = selectedIndex === globalIndex;
                      return (
                        <button
                          key={person.id}
                          onClick={() => handleResultClick('person', person.id)}
                          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left ${
                            isSelected ? 'bg-cyan-50 text-obsidian dark:bg-gray-700 dark:text-electric-cyan dark:ring-1 dark:ring-electric-cyan' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200'
                          }`}
                        >
                          {person.thumbnail ? (
                            <img
                              src={person.thumbnail}
                              alt={person.name}
                              className="w-8 h-8 rounded-full object-cover"
                            />
                          ) : (
                            <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center dark:bg-gray-700">
                              <User className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            </div>
                          )}
                          <span className="text-sm font-medium flex-1 truncate">
                            {person.name}
                          </span>
                          {person.former_member && (
                            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300 flex-shrink-0">
                              Oud-lid
                            </span>
                          )}
                          {isSelected && (
                            <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">Enter</kbd>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}

                {/* Organizations results */}
                {safeResults.teams && safeResults.teams.length > 0 && (
                  <div className="px-2 mt-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide dark:text-gray-400">
                      Teams
                    </div>
                    {safeResults.teams.map((team, index) => {
                      const globalIndex = (safeResults.people?.length || 0) + index;
                      const isSelected = selectedIndex === globalIndex;
                      return (
                        <button
                          key={team.id}
                          onClick={() => handleResultClick('team', team.id)}
                          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left ${
                            isSelected ? 'bg-cyan-50 text-obsidian dark:bg-gray-700 dark:text-electric-cyan dark:ring-1 dark:ring-electric-cyan' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200'
                          }`}
                        >
                          {team.thumbnail ? (
                            <img
                              src={team.thumbnail}
                              alt={team.name}
                              className="w-8 h-8 rounded object-contain dark:bg-gray-700"
                            />
                          ) : (
                            <div className="w-8 h-8 bg-gray-200 rounded flex items-center justify-center dark:bg-gray-700">
                              <Building2 className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            </div>
                          )}
                          <span className="text-sm font-medium flex-1 truncate">
                            {team.name}
                          </span>
                          {isSelected && (
                            <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">Enter</kbd>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}

                {/* Invoice results */}
                {safeResults.invoices && safeResults.invoices.length > 0 && (
                  <div className="px-2 mt-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide dark:text-gray-400">
                      Facturen
                    </div>
                    {safeResults.invoices.map((invoice, index) => {
                      const globalIndex = (safeResults.people?.length || 0) + (safeResults.teams?.length || 0) + index;
                      const isSelected = selectedIndex === globalIndex;
                      const statusMap = {
                        draft: { label: 'Concept', color: 'bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300' },
                        sent: { label: 'Verzonden', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' },
                        paid: { label: 'Betaald', color: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' },
                        overdue: { label: 'Te laat', color: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' },
                      };
                      const statusInfo = statusMap[invoice.status] || {
                        label: invoice.status ? invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1) : '',
                        color: 'bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300',
                      };
                      return (
                        <button
                          key={invoice.id}
                          onClick={() => handleResultClick('invoice', invoice.id)}
                          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left ${
                            isSelected ? 'bg-cyan-50 text-obsidian dark:bg-gray-700 dark:text-electric-cyan dark:ring-1 dark:ring-electric-cyan' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200'
                          }`}
                        >
                          <div className="w-8 h-8 bg-gray-200 rounded flex items-center justify-center dark:bg-gray-700 flex-shrink-0">
                            <Receipt className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                          </div>
                          <div className="flex-1 min-w-0">
                            <span className="text-sm font-medium truncate block">
                              {invoice.invoice_number}
                            </span>
                            {invoice.person_name && (
                              <span className="text-xs text-gray-500 dark:text-gray-400 truncate block">
                                {invoice.person_name}
                              </span>
                            )}
                          </div>
                          <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium flex-shrink-0 ${statusInfo.color}`}>
                            {statusInfo.label}
                          </span>
                          {isSelected && (
                            <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">Enter</kbd>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>
            ) : (
              <div className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                <p className="text-sm">Geen resultaten gevonden voor &quot;{searchQuery}&quot;</p>
              </div>
            )}
          </div>
          
          {/* Footer */}
          <div className="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400">
            <div className="flex items-center gap-4">
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono dark:bg-gray-800 dark:border-gray-600">up</kbd>
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono dark:bg-gray-800 dark:border-gray-600">down</kbd>
                <span>to navigate</span>
              </span>
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono dark:bg-gray-800 dark:border-gray-600">enter</kbd>
                <span>to select</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function Header({ onMenuClick, onOpenSearch, onOpenFeedback }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const filteredCount = searchParams.get('filteredCount');

  // Detect platform for keyboard shortcut display
  const isMac = useMemo(() => {
    if (typeof navigator === 'undefined') {
      return false;
    }

    const platform =
      navigator.userAgentData?.platform ||
      navigator.platform ||
      navigator.userAgent ||
      '';

    return typeof platform === 'string' && platform.toLowerCase().includes('mac');
  }, []);

  // Get page title from location
  const getPageTitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Dashboard';
    if (path.startsWith('/people/jubilarissen')) return 'Jubilarissen';
    if (path.startsWith('/people/onboarding')) return 'Onboarding';
    if (path.startsWith('/people')) return 'Relaties';
    if (path === '/financien' || path === '/financien/') return 'Financiën';
    if (path.startsWith('/financien/contributie')) return 'Contributie';
    if (path.startsWith('/financien/facturen')) return 'Facturen';
    if (path.startsWith('/contributie')) return 'Contributie';
    if (path.startsWith('/vog')) return 'VOG';
    if (path.startsWith('/tuchtzaken')) return 'Tuchtzaken';
    if (path.startsWith('/teams')) return 'Teams';
    if (path.startsWith('/commissies')) return 'Commissies';
    if (path.startsWith('/todos')) return 'Taken';
    if (path.startsWith('/lidpas-scanner')) return 'Lidpas Scanner';
    if (path.startsWith('/feedback')) return 'Feedback';
    if (path.startsWith('/settings')) return 'Instellingen';
    if (path.startsWith('/profile')) return 'Profiel';
    return '';
  };

  const isDashboard = location.pathname === '/';

  const handleCustomizeClick = () => {
    navigate('/?customize=true');
  };

  return (
    <header className="sticky top-0 z-30 flex items-center h-16 px-4 bg-white border-b border-gray-200 lg:px-6 dark:bg-gray-800 dark:border-gray-700">
      {/* Mobile menu button */}
      <button
        onClick={onMenuClick}
        className="p-2 -ml-2 lg:hidden dark:text-gray-300"
      >
        <Menu className="w-5 h-5" />
      </button>

      {/* Page title */}
      <h1 className="ml-2 text-lg font-semibold lg:ml-0 dark:text-gray-100">
        {getPageTitle()}
        {filteredCount && (
          <span className="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
            ({filteredCount})
          </span>
        )}
      </h1>

      {/* Dashboard customize button */}
      {isDashboard && (
        <button
          onClick={handleCustomizeClick}
          className="ml-3 flex items-center gap-1.5 px-2 py-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
        >
          <Settings className="w-4 h-4" />
          <span className="hidden sm:inline">Aanpassen</span>
        </button>
      )}

      {/* Spacer */}
      <div className="flex-1" />

      {/* Feedback button */}
      <button
        onClick={onOpenFeedback}
        className="p-2 rounded-lg text-gray-500 hover:bg-gray-50 transition-colors dark:text-gray-400 dark:hover:bg-gray-700"
        aria-label="Feedback verzenden"
        title="Feedback verzenden"
      >
        <MessageSquarePlus className="w-5 h-5" />
      </button>

      {/* Search button */}
      <button
        onClick={onOpenSearch}
        className="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors dark:border-gray-600 dark:hover:bg-gray-700 dark:text-gray-400"
        aria-label="Zoeken"
        title={`Zoeken (${isMac ? 'Cmd' : 'Ctrl'}+K)`}
      >
        <Search className="w-4 h-4" />
        <span className="hidden sm:inline text-sm">Zoek...</span>
        <kbd className="hidden sm:flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">
          {isMac ? (
            <>
              <Command className="w-3 h-3" />K
            </>
          ) : (
            'Ctrl+K'
          )}
        </kbd>
      </button>
    </header>
  );
}

function DemoBanner() {
  const isDemo = window.rondoConfig?.isDemo;
  if (!isDemo) return null;

  return (
    <div className="h-7 bg-amber-400 text-amber-900 text-xs font-semibold flex items-center justify-center shrink-0">
      DEMO OMGEVING — Dit is geen echte data
    </div>
  );
}

export default function Layout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [showSearchModal, setShowSearchModal] = useState(false);
  const [showFeedbackModal, setShowFeedbackModal] = useState(false);
  const isDemo = window.rondoConfig?.isDemo;
  const createFeedback = useCreateFeedback();

  // Fetch dashboard stats for navigation counts
  const { data: dashboardData } = useDashboard();
  const stats = dashboardData?.stats;

  // Update document title based on route
  useRouteTitle();

  // Handle Cmd+K keyboard shortcut
  useEffect(() => {
    const handleKeyDown = (e) => {
      // Cmd+K (Mac) or Ctrl+K (Windows/Linux)
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setShowSearchModal(true);
      }
    };
    
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, []);

  const appMinHeight = isDemo
    ? 'calc(100vh - 28px - var(--rondo-admin-bar-offset, 0px))'
    : 'calc(100vh - var(--rondo-admin-bar-offset, 0px))';
  const appHeight = isDemo
    ? 'calc(100dvh - 28px - var(--rondo-admin-bar-offset, 0px))'
    : 'calc(100dvh - var(--rondo-admin-bar-offset, 0px))';
  
  return (
    <>
      <DemoBanner />
      <div
        className="flex min-h-[var(--app-min-height)] lg:h-[var(--app-height)] bg-gray-50 dark:bg-gray-900"
        style={{ '--app-min-height': appMinHeight, '--app-height': appHeight }}
      >
        {/* Desktop sidebar */}
        <div className="hidden lg:flex lg:w-64 lg:flex-col">
          <Sidebar stats={stats} />
        </div>

      {/* Mobile sidebar */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-gray-600/75 dark:bg-black/50"
            onClick={() => setSidebarOpen(false)}
          />

          {/* Sidebar */}
          <div className="fixed inset-y-0 left-0 flex flex-col w-64 bg-white dark:bg-gray-800">
            <Sidebar mobile onClose={() => setSidebarOpen(false)} stats={stats} />
          </div>
        </div>
      )}

      {/* Main content */}
      <div className="flex flex-col flex-1 min-w-0 lg:min-h-0">
        <Header
          onMenuClick={() => setSidebarOpen(true)}
          onOpenSearch={() => setShowSearchModal(true)}
          onOpenFeedback={() => setShowFeedbackModal(true)}
        />

        <main className="flex-1 px-4 pt-4 pb-[calc(6rem+env(safe-area-inset-bottom))] overflow-visible lg:min-h-0 lg:overflow-y-auto lg:p-6 [overscroll-behavior-y:none]">
          {children}
        </main>
      </div>

      {/* Search Modal */}
      <SearchModal
        isOpen={showSearchModal}
        onClose={() => setShowSearchModal(false)}
      />

      {/* Feedback Modal */}
      <FeedbackModal
        isOpen={showFeedbackModal}
        onClose={() => setShowFeedbackModal(false)}
        onSubmit={async (data) => {
          await createFeedback.mutateAsync(data);
          setShowFeedbackModal(false);
        }}
        isLoading={createFeedback.isPending}
        urlContext={location.pathname}
      />
      </div>
    </>
  );
}
