import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Users } from 'lucide-react';
import { wpApi } from '@/api/client';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';
import { useVolunteerRoleSettings } from '@/hooks/useVolunteerRoleSettings';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { decodeHtml, formatPhoneForTel, getTeamName } from '@/utils/formatters';

const collator = new Intl.Collator('nl-NL', { numeric: true, sensitivity: 'base' });

const AGE_GROUP_ORDER = {
  Junioren: 0,
  Pupillen: 1,
  Senioren: 2,
};
const FETCH_CONCURRENCY = 4;

function getPrimaryContactByType(person, type) {
  const contactInfo = person?.acf?.contact_info || [];
  const contact = contactInfo.find((item) => item.contact_type === type && item.contact_value);
  return contact?.contact_value || '';
}

function getPrimaryPhone(person) {
  const contactInfo = person?.acf?.contact_info || [];
  const mobile = contactInfo.find((item) => item.contact_type === 'mobile' && item.contact_value);
  if (mobile?.contact_value) return mobile.contact_value;

  const phone = contactInfo.find((item) => item.contact_type === 'phone' && item.contact_value);
  return phone?.contact_value || '';
}

function parseYearFromLabel(label) {
  if (!label) return null;
  const normalized = String(label).toLowerCase();
  if (normalized.includes("mini")) return 7;

  const match = String(label).toUpperCase().match(/\bJ?O\s?(\d{1,2})\b/);
  if (!match) return null;

  const year = Number.parseInt(match[1], 10);
  return Number.isNaN(year) ? null : year;
}

function parseYearGroupFromLabel(label) {
  const year = parseYearFromLabel(label);
  return year ? `JO${year}` : '';
}

function parseAgeGroupFromLabel(label) {
  const normalized = String(label || '').toLowerCase();
  if (normalized.includes('junior')) return 'Junioren';
  if (normalized.includes('pupil')) return 'Pupillen';
  if (normalized.includes('senior')) return 'Senioren';
  return '';
}

function getAgeGroupFromYear(year) {
  if (!year) return 'Senioren';
  if (year >= 12 && year <= 19) return 'Junioren';
  if (year >= 6 && year <= 11) return 'Pupillen';
  return 'Senioren';
}

function buildLineage(team, teamsById) {
  const lineage = [team];
  const seen = new Set([team.id]);
  let currentParent = team.parent || 0;

  while (currentParent && teamsById.has(currentParent) && !seen.has(currentParent)) {
    const parent = teamsById.get(currentParent);
    lineage.push(parent);
    seen.add(parent.id);
    currentParent = parent.parent || 0;
  }

  return lineage;
}

function isTruthy(value) {
  return value === true || value === 1 || value === '1';
}

function isCurrentJob(job) {
  const hasEndDate = !!job?.end_date;
  const endDate = hasEndDate ? new Date(job.end_date) : null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (isTruthy(job?.is_current)) {
    if (!hasEndDate) return true;
    return !Number.isNaN(endDate.getTime()) && endDate >= today;
  }

  if (!hasEndDate) return true;
  return !Number.isNaN(endDate.getTime()) && endDate >= today;
}

function deriveGrouping(team, teamsById) {
  const lineage = buildLineage(team, teamsById);

  let yearGroup = '';
  let yearNumber = null;
  for (const node of lineage) {
    const parsed = parseYearGroupFromLabel(node.name);
    if (parsed) {
      yearGroup = parsed;
      yearNumber = parseYearFromLabel(parsed);
      break;
    }
  }

  let ageGroup = getAgeGroupFromYear(yearNumber);
  if (!ageGroup || ageGroup === 'Senioren') {
    for (const node of lineage) {
      const parsed = parseAgeGroupFromLabel(node.name);
      if (parsed) {
        ageGroup = parsed;
        break;
      }
    }
  }

  if (!ageGroup) ageGroup = 'Senioren';

  return {
    ageGroup,
    yearGroup,
    yearNumber,
  };
}

function deriveGroupingFromText(label) {
  const yearGroup = parseYearGroupFromLabel(label);
  const yearNumber = parseYearFromLabel(yearGroup || label);
  const ageGroup = yearNumber ? getAgeGroupFromYear(yearNumber) : 'Senioren';

  return {
    ageGroup,
    yearGroup,
    yearNumber,
  };
}

function getRowScopePriority(row) {
  const normalizedRole = String(row.role || '').toLowerCase();
  const hasYearInRole = !!parseYearFromLabel(normalizedRole);
  const hasAgeGroupInRole = normalizedRole.includes('junior') || normalizedRole.includes('pupil') || normalizedRole.includes('senior');
  const isCoordinator = normalizedRole.includes('coördinator') || normalizedRole.includes('coordinator');

  // Age-group coordinator (e.g. Coördinator Junioren) should be top within age group.
  if ((isCoordinator && hasAgeGroupInRole && !hasYearInRole) || (!row.hasTeamLink && hasAgeGroupInRole && !hasYearInRole)) {
    return 0;
  }

  // Year-group coordinator (e.g. Technisch/Organisatorisch coördinator JO19) comes before teams in that year group.
  if ((isCoordinator && hasYearInRole) || (!row.hasTeamLink && hasYearInRole)) {
    return 1;
  }

  // Team-linked roles appear after coordinator rows.
  return 2;
}

async function fetchAllTeams() {
  const perPage = 100;
  const params = {
    per_page: perPage,
    _fields: 'id,parent,title',
  };

  const firstResponse = await wpApi.getTeams({ ...params, page: 1 });
  const totalPages = Number.parseInt(
    firstResponse.headers['x-wp-totalpages'] || firstResponse.headers['X-WP-TotalPages'] || '1',
    10,
  ) || 1;

  const allTeams = [...(firstResponse.data || [])];
  if (totalPages > 1) {
    const pageNumbers = Array.from({ length: totalPages - 1 }, (_, index) => index + 2);

    for (let i = 0; i < pageNumbers.length; i += FETCH_CONCURRENCY) {
      const batch = pageNumbers.slice(i, i + FETCH_CONCURRENCY);
      const responses = await Promise.all(batch.map((page) => wpApi.getTeams({ ...params, page })));
      responses.forEach((response) => {
        allTeams.push(...(response.data || []));
      });
    }
  }

  return allTeams.map((team) => ({
    id: team.id,
    parent: team.parent || 0,
    name: getTeamName(team),
  }));
}

async function fetchAllPeople() {
  const perPage = 100;
  const params = {
    per_page: perPage,
    _fields: 'id,acf',
  };

  const firstResponse = await wpApi.getPeople({ ...params, page: 1 });
  const totalPages = Number.parseInt(
    firstResponse.headers['x-wp-totalpages'] || firstResponse.headers['X-WP-TotalPages'] || '1',
    10,
  ) || 1;

  const allPeople = [...(firstResponse.data || [])];
  if (totalPages > 1) {
    const pageNumbers = Array.from({ length: totalPages - 1 }, (_, index) => index + 2);

    for (let i = 0; i < pageNumbers.length; i += FETCH_CONCURRENCY) {
      const batch = pageNumbers.slice(i, i + FETCH_CONCURRENCY);
      const responses = await Promise.all(batch.map((page) => wpApi.getPeople({ ...params, page })));
      responses.forEach((response) => {
        allPeople.push(...(response.data || []));
      });
    }
  }

  return allPeople;
}

export default function Kaderlijst() {
  useDocumentTitle('Kaderlijst');

  const { data: roleSettings } = useVolunteerRoleSettings();

  const { data, isLoading, error } = useQuery({
    queryKey: ['kaderlijst'],
    queryFn: async () => {
      const [teams, people] = await Promise.all([fetchAllTeams(), fetchAllPeople()]);
      const teamsById = new Map(teams.map((team) => [team.id, team]));

      const rows = [];
      people.forEach((person) => {
        if (isTruthy(person?.acf?.former_member)) return;

        const workHistory = Array.isArray(person?.acf?.work_history) ? person.acf.work_history : [];
        if (workHistory.length === 0) return;

        const firstName = person?.acf?.first_name || '';
        const infix = person?.acf?.infix || '';
        const lastName = person?.acf?.last_name || '';
        const mobile = getPrimaryPhone(person);
        const email = getPrimaryContactByType(person, 'email');

        workHistory.forEach((job) => {
          const teamId = Number.parseInt(job?.team, 10);
          if (!isCurrentJob(job)) return;

          const team = teamId ? teamsById.get(teamId) : null;

          const role = decodeHtml(job?.job_title || '');
          if (!role) return;

          const fallbackGrouping = deriveGroupingFromText(role);
          const fallbackTeamName = fallbackGrouping.yearGroup || fallbackGrouping.ageGroup;
          const teamName = team?.name || fallbackTeamName;
          if (!teamName) return;

          rows.push({
            id: `${teamId || 'no-team'}-${person.id}-${role}`,
            personId: person.id,
            teamId: team?.id || null,
            teamName,
            hasTeamLink: !!team?.id,
            firstName: decodeHtml(firstName),
            infix: decodeHtml(infix),
            lastName: decodeHtml(lastName),
            role,
            mobile: decodeHtml(mobile),
            email: decodeHtml(email),
          });
        });
      });

      return { teams, rows };
    },
    staleTime: 5 * 60 * 1000,
  });

  const rosterRows = useMemo(() => {
    const rows = data?.rows || [];
    const teams = data?.teams || [];
    const teamsById = new Map(teams.map((team) => [team.id, team]));
    const playerRoles = new Set(roleSettings?.player_roles || []);

    const filteredRows = rows.filter((row) => !playerRoles.has(row.role));

    const enrichedRows = filteredRows.map((row) => {
      const team = row.teamId ? teamsById.get(row.teamId) : null;
      const grouping = team
        ? deriveGrouping(team, teamsById)
        : deriveGroupingFromText(`${row.teamName} ${row.role}`);

      return {
        ...row,
        ageGroup: grouping.ageGroup,
        yearGroup: grouping.yearGroup,
        yearNumber: grouping.yearNumber,
        surname: [row.infix, row.lastName].filter(Boolean).join(' '),
        scopePriority: getRowScopePriority(row),
      };
    });

    const sortedRows = [...enrichedRows].sort((a, b) => {
      const ageCmp = (AGE_GROUP_ORDER[a.ageGroup] ?? 99) - (AGE_GROUP_ORDER[b.ageGroup] ?? 99);
      if (ageCmp !== 0) return ageCmp;

      const scopeCmp = (a.scopePriority ?? 9) - (b.scopePriority ?? 9);
      if (scopeCmp !== 0) return scopeCmp;

      const yearA = a.yearNumber ?? -1;
      const yearB = b.yearNumber ?? -1;
      if (yearA !== yearB) return yearB - yearA;

      const teamCmp = collator.compare(a.teamName, b.teamName);
      if (teamCmp !== 0) return teamCmp;

      const surnameCmp = collator.compare(a.surname, b.surname);
      if (surnameCmp !== 0) return surnameCmp;

      return collator.compare(a.firstName, b.firstName);
    });

    return sortedRows.map((row, index) => {
      const prev = sortedRows[index - 1];

      return {
        ...row,
        ageGroupDisplay: prev && prev.ageGroup === row.ageGroup ? '' : row.ageGroup,
        yearGroupDisplay: prev && prev.yearGroup === row.yearGroup ? '' : row.yearGroup,
        teamDisplay: prev && prev.teamName === row.teamName ? '' : row.teamName,
      };
    });
  }, [data?.rows, data?.teams, roleSettings?.player_roles]);

  const ageGroupOptions = useMemo(() => {
    const unique = new Set(rosterRows.map((row) => row.ageGroup).filter(Boolean));
    return [...unique]
      .sort((a, b) => (AGE_GROUP_ORDER[a] ?? 99) - (AGE_GROUP_ORDER[b] ?? 99))
      .map((value) => ({ value, label: value }));
  }, [rosterRows]);

  const yearGroupOptions = useMemo(() => {
    const unique = new Set(rosterRows.map((row) => row.yearGroup).filter(Boolean));
    return [...unique]
      .sort((a, b) => (parseYearFromLabel(b) || 0) - (parseYearFromLabel(a) || 0))
      .map((value) => ({ value, label: value }));
  }, [rosterRows]);

  const columns = useMemo(() => [
    createColumn({
      id: 'age_group',
      header: 'Leeftijdsgroep',
      accessorFn: (row) => row.ageGroup,
      cell: ({ row }) => <span className="font-medium text-gray-900 dark:text-gray-100">{row.original.ageGroupDisplay}</span>,
      filterType: FILTER_TYPES.SELECT,
      filterLabel: 'Leeftijdsgroep',
      filterOptions: ageGroupOptions,
      sortable: false,
      size: 150,
    }),
    createColumn({
      id: 'year_group',
      header: 'Jaargroep',
      accessorFn: (row) => row.yearGroup,
      cell: ({ row }) => <span className="font-medium text-gray-900 dark:text-gray-100">{row.original.yearGroupDisplay}</span>,
      filterType: FILTER_TYPES.SELECT,
      filterLabel: 'Jaargroep',
      filterOptions: yearGroupOptions,
      sortable: false,
      size: 120,
    }),
    createColumn({
      id: 'team',
      header: 'Team',
      accessorFn: (row) => row.teamName,
      cell: ({ row }) => (
        row.original.teamDisplay && row.original.hasTeamLink ? (
          <Link to={`/teams/${row.original.teamId}`} className="font-medium text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan">
            {row.original.teamDisplay}
          </Link>
        ) : row.original.teamDisplay
      ),
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Team',
      sortable: false,
      size: 180,
    }),
    createColumn({
      id: 'first_name',
      header: 'Voornaam',
      accessorFn: (row) => row.firstName,
      cell: ({ row }) => (
        <Link to={`/people/${row.original.personId}`} className="font-medium text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan">
          {row.original.firstName}
        </Link>
      ),
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Voornaam',
      sortable: false,
      size: 140,
    }),
    createColumn({
      id: 'surname',
      header: 'Achternaam',
      accessorFn: (row) => row.surname,
      cell: ({ row }) => (
        <Link to={`/people/${row.original.personId}`} className="font-medium text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan">
          {row.original.surname}
        </Link>
      ),
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Achternaam',
      sortable: false,
      size: 180,
    }),
    createColumn({
      id: 'role',
      header: 'Rol',
      accessorFn: (row) => row.role,
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Rol',
      sortable: false,
      size: 260,
    }),
    createColumn({
      id: 'mobile',
      header: 'Mobiel',
      accessorFn: (row) => row.mobile,
      cell: ({ row }) => (
        row.original.mobile
          ? <a href={`tel:${formatPhoneForTel(row.original.mobile)}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">{row.original.mobile}</a>
          : ''
      ),
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Mobiel',
      sortable: false,
      size: 160,
    }),
    createColumn({
      id: 'email',
      header: 'Email',
      accessorFn: (row) => row.email,
      cell: ({ row }) => (
        row.original.email
          ? <a href={`mailto:${row.original.email}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">{row.original.email}</a>
          : ''
      ),
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Email',
      sortable: false,
      size: 220,
    }),
  ], [ageGroupOptions, yearGroupOptions]);

  if (error) {
    return (
      <div className="card p-6 text-sm text-red-600 dark:text-red-400">
        Kaderlijst kon niet worden geladen.
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <DataTable
        storageKey="kaderlijst"
        data={rosterRows}
        columns={columns}
        isLoading={isLoading}
        emptyIcon={<Users className="w-8 h-8 text-gray-400 dark:text-gray-500" />}
        emptyTitle="Geen kaderleden gevonden"
        emptyDescription="Er zijn geen actieve kaderrollen gevonden voor teams of jaargroepen."
      />
    </div>
  );
}
