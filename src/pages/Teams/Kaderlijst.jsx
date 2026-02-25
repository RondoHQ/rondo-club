import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Users } from 'lucide-react';
import { wpApi, prmApi } from '@/api/client';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';
import { useVolunteerRoleSettings } from '@/hooks/useVolunteerRoleSettings';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { decodeHtml, formatPhoneForTel, getTeamName } from '@/utils/formatters';

const collator = new Intl.Collator('nl-NL', { numeric: true, sensitivity: 'base' });

const AGE_GROUP_ORDER = {
  Junioren: 0,
  Pupillen: 1,
  Overig: 2,
};

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
  return '';
}

function getAgeGroupFromYear(year) {
  if (!year) return '';
  if (year >= 12 && year <= 19) return 'Junioren';
  if (year >= 6 && year <= 11) return 'Pupillen';
  return 'Overig';
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
  if (!ageGroup || ageGroup === 'Overig') {
    for (const node of lineage) {
      const parsed = parseAgeGroupFromLabel(node.name);
      if (parsed) {
        ageGroup = parsed;
        break;
      }
    }
  }

  if (!ageGroup) ageGroup = 'Overig';

  return {
    ageGroup,
    yearGroup,
    yearNumber,
  };
}

async function fetchAllTeams() {
  const allTeams = [];
  const perPage = 100;
  let page = 1;
  let hasMore = true;

  while (hasMore) {
    const response = await wpApi.getTeams({ page, per_page: perPage });
    const teams = response.data || [];

    allTeams.push(...teams.map((team) => ({
      id: team.id,
      parent: team.parent || 0,
      name: getTeamName(team),
    })));

    if (teams.length < perPage) {
      hasMore = false;
      break;
    }

    const totalPages = Number.parseInt(
      response.headers['x-wp-totalpages'] || response.headers['X-WP-TotalPages'] || '0',
      10,
    );

    if (totalPages > 0 && page >= totalPages) {
      hasMore = false;
      break;
    }

    page += 1;
  }

  return allTeams;
}

async function fetchAllPeople() {
  const allPeople = [];
  const perPage = 100;
  let page = 1;
  let hasMore = true;

  while (hasMore) {
    const response = await wpApi.getPeople({ page, per_page: perPage });
    const people = response.data || [];
    allPeople.push(...people);

    if (people.length < perPage) {
      hasMore = false;
      break;
    }

    const totalPages = Number.parseInt(
      response.headers['x-wp-totalpages'] || response.headers['X-WP-TotalPages'] || '0',
      10,
    );

    if (totalPages > 0 && page >= totalPages) {
      hasMore = false;
      break;
    }

    page += 1;
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

      const peopleById = new Map(people.map((person) => [person.id, person]));
      const assignments = await Promise.all(
        teams.map(async (team) => {
          try {
            const response = await prmApi.getTeamPeople(team.id);
            return {
              teamId: team.id,
              current: response.data?.current || [],
            };
          } catch {
            return {
              teamId: team.id,
              current: [],
            };
          }
        }),
      );

      const rows = [];
      assignments.forEach((assignment) => {
        const team = teams.find((item) => item.id === assignment.teamId);
        if (!team) return;

        assignment.current.forEach((member) => {
          const person = peopleById.get(member.id);
          const firstName = person?.acf?.first_name || member.first_name || '';
          const infix = person?.acf?.infix || '';
          const lastName = person?.acf?.last_name || member.last_name || '';

          rows.push({
            id: `${team.id}-${member.id}-${member.job_title || 'rol'}`,
            personId: member.id,
            teamId: team.id,
            teamName: team.name,
            firstName: decodeHtml(firstName),
            infix: decodeHtml(infix),
            lastName: decodeHtml(lastName),
            role: decodeHtml(member.job_title || ''),
            mobile: decodeHtml(getPrimaryPhone(person)),
            email: decodeHtml(getPrimaryContactByType(person, 'email')),
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
      const team = teamsById.get(row.teamId);
      const grouping = team
        ? deriveGrouping(team, teamsById)
        : { ageGroup: 'Overig', yearGroup: '', yearNumber: null };

      return {
        ...row,
        ageGroup: grouping.ageGroup,
        yearGroup: grouping.yearGroup,
        yearNumber: grouping.yearNumber,
        surname: [row.infix, row.lastName].filter(Boolean).join(' '),
      };
    });

    const sortedRows = [...enrichedRows].sort((a, b) => {
      const ageCmp = (AGE_GROUP_ORDER[a.ageGroup] ?? 99) - (AGE_GROUP_ORDER[b.ageGroup] ?? 99);
      if (ageCmp !== 0) return ageCmp;

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
        row.original.teamDisplay ? (
          <Link to={`/teams/${row.original.teamId}`} className="font-medium text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan">
            {row.original.teamDisplay}
          </Link>
        ) : ''
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
