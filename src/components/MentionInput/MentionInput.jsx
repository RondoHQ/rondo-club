import { MentionsInput, Mention } from 'react-mentions';
import { prmApi } from '@/api/client';

const mentionStyle = {
  backgroundColor: '#e0f2fe', // light blue highlight
  borderRadius: '2px',
  padding: '1px 2px',
};

const defaultStyle = {
  control: {
    minHeight: 80,
  },
  input: {
    padding: 9,
    border: '1px solid #d1d5db',
    borderRadius: '0.375rem',
  },
  suggestions: {
    list: {
      backgroundColor: 'white',
      border: '1px solid #e5e7eb',
      borderRadius: '0.375rem',
      boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
    },
    item: {
      padding: '8px 12px',
      '&focused': {
        backgroundColor: '#f3f4f6',
      },
    },
  },
};

export default function MentionInput({ value, onChange, placeholder, workspaceIds = [] }) {
  const fetchUsers = async (query, callback) => {
    if (!query || workspaceIds.length === 0) {
      callback([]);
      return;
    }
    try {
      const members = await prmApi.searchWorkspaceMembers(workspaceIds, query);
      callback(members.map(u => ({ id: u.id, display: u.name })));
    } catch {
      callback([]);
    }
  };

  return (
    <MentionsInput
      value={value}
      onChange={(e, newValue) => onChange(newValue)}
      placeholder={placeholder || "Add a note... Use @ to mention someone"}
      style={defaultStyle}
    >
      <Mention
        trigger="@"
        data={fetchUsers}
        markup="@[__display__](__id__)"
        style={mentionStyle}
        appendSpaceOnAdd
      />
    </MentionsInput>
  );
}
