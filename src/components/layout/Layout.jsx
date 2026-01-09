import { useState, useRef, useEffect } from 'react';
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom';
import { 
  Users, 
  Building2, 
  Calendar, 
  Settings, 
  Menu, 
  X,
  Home,
  LogOut,
  Search,
  User,
  ChevronDown,
  Sparkles,
  CheckSquare,
  Plus,
  Command
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import { useSearch } from '@/hooks/useDashboard';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi, wpApi } from '@/api/client';
import { APP_NAME } from '@/constants/app';
import GlobalTodoModal from '@/components/Timeline/GlobalTodoModal';
import PersonEditModal from '@/components/PersonEditModal';
import CompanyEditModal from '@/components/CompanyEditModal';
import ImportantDateModal from '@/components/ImportantDateModal';
import { usePeople } from '@/hooks/usePeople';

const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'People', href: '/people', icon: Users },
  { name: 'Organizations', href: '/companies', icon: Building2 },
  { name: 'Dates', href: '/dates', icon: Calendar },
  { name: 'Todos', href: '/todos', icon: CheckSquare },
  { name: 'Settings', href: '/settings', icon: Settings },
];

function Sidebar({ mobile = false, onClose }) {
  const { logoutUrl } = useAuth();
  
  return (
    <div className="flex flex-col h-full bg-white border-r border-gray-200">
      {/* Logo */}
      <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200">
        <Link to="/" className="flex items-center gap-2 text-xl font-bold text-primary-600">
          <Sparkles className="w-5 h-5" />
          {APP_NAME}
        </Link>
        {mobile && (
          <button onClick={onClose} className="p-2 -mr-2">
            <X className="w-5 h-5" />
          </button>
        )}
      </div>
      
      {/* Navigation */}
      <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
        {navigation.map((item) => (
          <NavLink
            key={item.name}
            to={item.href}
            onClick={mobile ? onClose : undefined}
            className={({ isActive }) =>
              `flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors ${
                isActive
                  ? 'bg-primary-50 text-primary-700'
                  : 'text-gray-700 hover:bg-gray-50'
              }`
            }
          >
            <item.icon className="w-5 h-5 mr-3" />
            {item.name}
          </NavLink>
        ))}
      </nav>
      
      {/* Logout */}
      <div className="p-4 border-t border-gray-200">
        <a
          href={logoutUrl}
          className="flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <LogOut className="w-5 h-5 mr-3" />
          Log Out
        </a>
      </div>
    </div>
  );
}

function UserMenu() {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef(null);
  
  const { data: user, isLoading } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
  });
  
  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [isOpen]);
  
  if (isLoading || !user) {
    return (
      <div className="flex items-center ml-auto">
        <div className="w-8 h-8 bg-gray-200 rounded-full animate-pulse"></div>
      </div>
    );
  }
  
  const initials = user.name
    ?.split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2) || '?';
  
  return (
    <div className="relative ml-auto" ref={menuRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors"
        aria-label="User menu"
      >
        {user.avatar_url ? (
          <img
            src={user.avatar_url}
            alt={user.name}
            className="w-8 h-8 rounded-full object-cover"
          />
        ) : (
          <div className="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
            <span className="text-sm font-medium text-primary-700">{initials}</span>
          </div>
        )}
        <ChevronDown className={`w-4 h-4 text-gray-500 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>
      
      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <div className="py-1">
            <a
              href={user.profile_url}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
              onClick={() => setIsOpen(false)}
            >
              <User className="w-4 h-4 mr-2" />
              <span className="hidden md:inline">Edit profile</span>
            </a>
            {user.is_admin && (
              <a
                href={user.admin_url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                onClick={() => setIsOpen(false)}
              >
                <Settings className="w-4 h-4 mr-2" />
                <span className="hidden md:inline">WordPress admin</span>
              </a>
            )}
          </div>
        </div>
      )}
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
  const safeResults = searchResults || { people: [], companies: [] };
  const allResults = [
    ...(safeResults.people || []).map(p => ({ ...p, type: 'person' })),
    ...(safeResults.companies || []).map(c => ({ ...c, type: 'company' })),
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
    } else if (type === 'company') {
      navigate(`/companies/${id}`);
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
        <div className="relative w-full max-w-xl bg-white rounded-xl shadow-2xl overflow-hidden">
          {/* Search input */}
          <div className="flex items-center px-4 border-b border-gray-200">
            <Search className="w-5 h-5 text-gray-400 flex-shrink-0" />
            <input
              ref={inputRef}
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Search people & organizations..."
              className="flex-1 px-4 py-4 text-lg outline-none placeholder:text-gray-400"
              autoComplete="off"
            />
            <div className="flex items-center gap-1 text-xs text-gray-400">
              <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-gray-500 font-mono">esc</kbd>
              <span>to close</span>
            </div>
          </div>
          
          {/* Results */}
          <div className="max-h-96 overflow-y-auto">
            {!showResults ? (
              <div className="px-4 py-8 text-center text-gray-500">
                <Search className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p className="text-sm">Type at least 2 characters to search</p>
              </div>
            ) : isSearchLoading ? (
              <div className="px-4 py-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                <p className="mt-3 text-sm text-gray-500">Searching...</p>
              </div>
            ) : hasResults ? (
              <div className="py-2">
                {/* People results */}
                {safeResults.people && safeResults.people.length > 0 && (
                  <div className="px-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                      People
                    </div>
                    {safeResults.people.map((person, index) => {
                      const globalIndex = index;
                      const isSelected = selectedIndex === globalIndex;
                      return (
                        <button
                          key={person.id}
                          onClick={() => handleResultClick('person', person.id)}
                          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left ${
                            isSelected ? 'bg-primary-50 text-primary-900' : 'hover:bg-gray-50'
                          }`}
                        >
                          {person.thumbnail ? (
                            <img
                              src={person.thumbnail}
                              alt={person.name}
                              className="w-8 h-8 rounded-full object-cover"
                            />
                          ) : (
                            <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                              <User className="w-4 h-4 text-gray-500" />
                            </div>
                          )}
                          <span className="text-sm font-medium flex-1 truncate">
                            {person.name}
                          </span>
                          {isSelected && (
                            <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono">↵</kbd>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}
                
                {/* Organizations results */}
                {safeResults.companies && safeResults.companies.length > 0 && (
                  <div className="px-2 mt-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                      Organizations
                    </div>
                    {safeResults.companies.map((company, index) => {
                      const globalIndex = (safeResults.people?.length || 0) + index;
                      const isSelected = selectedIndex === globalIndex;
                      return (
                        <button
                          key={company.id}
                          onClick={() => handleResultClick('company', company.id)}
                          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left ${
                            isSelected ? 'bg-primary-50 text-primary-900' : 'hover:bg-gray-50'
                          }`}
                        >
                          {company.thumbnail ? (
                            <img
                              src={company.thumbnail}
                              alt={company.name}
                              className="w-8 h-8 rounded object-contain bg-white"
                            />
                          ) : (
                            <div className="w-8 h-8 bg-gray-200 rounded flex items-center justify-center">
                              <Building2 className="w-4 h-4 text-gray-500" />
                            </div>
                          )}
                          <span className="text-sm font-medium flex-1 truncate">
                            {company.name}
                          </span>
                          {isSelected && (
                            <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono">↵</kbd>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>
            ) : (
              <div className="px-4 py-8 text-center text-gray-500">
                <p className="text-sm">No results found for "{searchQuery}"</p>
              </div>
            )}
          </div>
          
          {/* Footer */}
          <div className="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500">
            <div className="flex items-center gap-4">
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono">↑</kbd>
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono">↓</kbd>
                <span>to navigate</span>
              </span>
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono">↵</kbd>
                <span>to select</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function QuickAddMenu({ onAddTodo, onAddPerson, onAddCompany, onAddDate }) {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef(null);
  
  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [isOpen]);
  
  const handleAddTodo = () => {
    setIsOpen(false);
    onAddTodo();
  };
  
  const handleAddPerson = () => {
    setIsOpen(false);
    onAddPerson();
  };
  
  const handleAddCompany = () => {
    setIsOpen(false);
    onAddCompany();
  };
  
  const handleAddDate = () => {
    setIsOpen(false);
    onAddDate();
  };
  
  return (
    <div className="relative" ref={menuRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center justify-center w-9 h-9 rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition-colors"
        aria-label="Quick add"
        title="Quick add"
      >
        <Plus className="w-5 h-5" />
      </button>
      
      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <div className="py-1">
            <button
              onClick={handleAddPerson}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left"
            >
              <User className="w-4 h-4 mr-3 text-gray-400" />
              New Person
            </button>
            <button
              onClick={handleAddCompany}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left"
            >
              <Building2 className="w-4 h-4 mr-3 text-gray-400" />
              New Organization
            </button>
            <button
              onClick={handleAddTodo}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left"
            >
              <CheckSquare className="w-4 h-4 mr-3 text-gray-400" />
              New Todo
            </button>
            <button
              onClick={handleAddDate}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left"
            >
              <Calendar className="w-4 h-4 mr-3 text-gray-400" />
              New Date
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function Header({ onMenuClick, onAddTodo, onAddPerson, onAddCompany, onAddDate, onOpenSearch }) {
  const location = useLocation();
  
  // Get page title from location
  const getPageTitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Dashboard';
    if (path.startsWith('/people')) return 'People';
    if (path.startsWith('/companies')) return 'Organizations';
    if (path.startsWith('/dates')) return 'Important Dates';
    if (path.startsWith('/todos')) return 'Todos';
    if (path.startsWith('/settings')) return 'Settings';
    return '';
  };
  
  return (
    <header className="sticky top-0 z-10 flex items-center h-16 px-4 bg-white border-b border-gray-200 lg:px-6">
      {/* Mobile menu button */}
      <button
        onClick={onMenuClick}
        className="p-2 -ml-2 lg:hidden"
      >
        <Menu className="w-5 h-5" />
      </button>
      
      {/* Page title */}
      <h1 className="ml-2 text-lg font-semibold lg:ml-0">
        {getPageTitle()}
      </h1>
      
      {/* Spacer */}
      <div className="flex-1" />
      
      {/* Search button */}
      <button
        onClick={onOpenSearch}
        className="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors"
        aria-label="Search"
        title="Search (⌘K)"
      >
        <Search className="w-4 h-4" />
        <span className="hidden sm:inline text-sm">Search...</span>
        <kbd className="hidden sm:flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono">
          <Command className="w-3 h-3" />K
        </kbd>
      </button>
      
      {/* Quick Add menu */}
      <div className="ml-2">
        <QuickAddMenu 
          onAddTodo={onAddTodo} 
          onAddPerson={onAddPerson}
          onAddCompany={onAddCompany}
          onAddDate={onAddDate}
        />
      </div>
      
      {/* User menu - right aligned */}
      <div className="ml-2">
        <UserMenu />
      </div>
    </header>
  );
}

export default function Layout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [showTodoModal, setShowTodoModal] = useState(false);
  const [showSearchModal, setShowSearchModal] = useState(false);
  const [showPersonModal, setShowPersonModal] = useState(false);
  const [showCompanyModal, setShowCompanyModal] = useState(false);
  const [showDateModal, setShowDateModal] = useState(false);
  const [isCreatingPerson, setIsCreatingPerson] = useState(false);
  const [isCreatingCompany, setIsCreatingCompany] = useState(false);
  const [isCreatingDate, setIsCreatingDate] = useState(false);
  
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  
  // Fetch people for date modal
  const { data: allPeople = [], isLoading: isPeopleLoading } = usePeople();
  
  // Update document title based on route
  useRouteTitle();
  
  // Create person mutation
  const createPersonMutation = useMutation({
    mutationFn: async (data) => {
      // Build contact_info array
      const contactInfo = [];
      if (data.email) {
        contactInfo.push({
          contact_type: 'email',
          contact_value: data.email,
          contact_label: 'Email',
        });
      }
      if (data.phone) {
        contactInfo.push({
          contact_type: data.phone_type || 'mobile',
          contact_value: data.phone,
          contact_label: data.phone_type === 'mobile' ? 'Mobile' : 'Phone',
        });
      }
      
      const payload = {
        title: `${data.first_name} ${data.last_name}`.trim(),
        status: 'publish',
        acf: {
          first_name: data.first_name,
          last_name: data.last_name,
          nickname: data.nickname,
          gender: data.gender || null,
          how_we_met: data.how_we_met,
          is_favorite: data.is_favorite,
          contact_info: contactInfo,
        },
      };
      
      const response = await wpApi.createPerson(payload);
      const personId = response.data.id;
      
      // Try to sideload Gravatar if email is provided
      if (data.email) {
        try {
          await prmApi.sideloadGravatar(personId, data.email);
        } catch (gravatarError) {
          console.error('Failed to load Gravatar:', gravatarError);
        }
      }
      
      // Create birthday if provided
      if (data.birthday && data.birthdayType) {
        try {
          await wpApi.createDate({
            title: `${data.first_name}'s Birthday`,
            status: 'publish',
            date_type: [data.birthdayType.id],
            acf: {
              date_value: data.birthday,
              is_recurring: true,
              related_people: [personId],
            },
          });
        } catch (dateError) {
          console.error('Failed to create birthday:', dateError);
        }
      }
      
      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['people'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      setShowPersonModal(false);
      navigate(`/people/${result.id}`);
    },
  });
  
  // Create company mutation
  const createCompanyMutation = useMutation({
    mutationFn: async (data) => {
      const payload = {
        title: data.title,
        status: 'publish',
        parent: data.parentId || 0,
        acf: {
          website: data.website,
          industry: data.industry,
          investors: data.investors || [],
        },
      };
      
      const response = await wpApi.createCompany(payload);
      return response.data;
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      setShowCompanyModal(false);
      navigate(`/companies/${result.id}`);
    },
  });
  
  // Create date mutation
  const createDateMutation = useMutation({
    mutationFn: async (data) => {
      const payload = {
        title: data.title,
        status: 'publish',
        date_type: data.date_type,
        acf: {
          date_value: data.date_value,
          related_people: data.related_people,
          is_recurring: data.is_recurring,
          year_unknown: data.year_unknown,
        },
      };
      
      const response = await wpApi.createDate(payload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      setShowDateModal(false);
    },
  });
  
  // Handle creating person
  const handleCreatePerson = async (data) => {
    setIsCreatingPerson(true);
    try {
      await createPersonMutation.mutateAsync(data);
    } finally {
      setIsCreatingPerson(false);
    }
  };
  
  // Handle creating company
  const handleCreateCompany = async (data) => {
    setIsCreatingCompany(true);
    try {
      await createCompanyMutation.mutateAsync(data);
    } finally {
      setIsCreatingCompany(false);
    }
  };
  
  // Handle creating date
  const handleCreateDate = async (data) => {
    setIsCreatingDate(true);
    try {
      await createDateMutation.mutateAsync(data);
    } finally {
      setIsCreatingDate(false);
    }
  };
  
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
  
  return (
    <div className="flex h-screen bg-gray-50">
      {/* Desktop sidebar */}
      <div className="hidden lg:flex lg:w-64 lg:flex-col">
        <Sidebar />
      </div>
      
      {/* Mobile sidebar */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-gray-600 bg-opacity-75"
            onClick={() => setSidebarOpen(false)}
          />
          
          {/* Sidebar */}
          <div className="fixed inset-y-0 left-0 flex flex-col w-64 bg-white">
            <Sidebar mobile onClose={() => setSidebarOpen(false)} />
          </div>
        </div>
      )}
      
      {/* Main content */}
      <div className="flex flex-col flex-1 min-w-0">
        <Header 
          onMenuClick={() => setSidebarOpen(true)} 
          onAddTodo={() => setShowTodoModal(true)}
          onAddPerson={() => setShowPersonModal(true)}
          onAddCompany={() => setShowCompanyModal(true)}
          onAddDate={() => setShowDateModal(true)}
          onOpenSearch={() => setShowSearchModal(true)}
        />
        
        <main className="flex-1 overflow-y-auto p-4 lg:p-6">
          {children}
        </main>
      </div>
      
      {/* Search Modal */}
      <SearchModal
        isOpen={showSearchModal}
        onClose={() => setShowSearchModal(false)}
      />
      
      {/* Global Todo Modal */}
      <GlobalTodoModal
        isOpen={showTodoModal}
        onClose={() => setShowTodoModal(false)}
      />
      
      {/* Person Modal */}
      <PersonEditModal
        isOpen={showPersonModal}
        onClose={() => setShowPersonModal(false)}
        onSubmit={handleCreatePerson}
        isLoading={isCreatingPerson}
      />
      
      {/* Company Modal */}
      <CompanyEditModal
        isOpen={showCompanyModal}
        onClose={() => setShowCompanyModal(false)}
        onSubmit={handleCreateCompany}
        isLoading={isCreatingCompany}
      />
      
      {/* Date Modal */}
      <ImportantDateModal
        isOpen={showDateModal}
        onClose={() => setShowDateModal(false)}
        onSubmit={handleCreateDate}
        isLoading={isCreatingDate}
        allPeople={allPeople}
        isPeopleLoading={isPeopleLoading}
      />
    </div>
  );
}
