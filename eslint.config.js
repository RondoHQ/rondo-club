import js from '@eslint/js';
import globals from 'globals';
import reactPlugin from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import { reactRefresh } from 'eslint-plugin-react-refresh';

export default [
  { ignores: ['dist/'] },

  {
    files: ['**/*.{js,jsx}'],
    ...reactPlugin.configs.flat.recommended,
    languageOptions: {
      ...reactPlugin.configs.flat.recommended.languageOptions,
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
      },
    },
    settings: { react: { version: '18.2' } },
  },

  {
    files: ['**/*.{js,jsx}'],
    ...reactPlugin.configs.flat['jsx-runtime'],
  },

  {
    files: ['**/*.{js,jsx}'],
    ...js.configs.recommended,
  },

  {
    files: ['**/*.{js,jsx}'],
    ...reactHooks.configs['recommended-latest'],
  },

  reactRefresh.configs.vite(),

  {
    files: ['**/*.{js,jsx}'],
    rules: {
      'react/prop-types': 'off',
      'react-refresh/only-export-components': [
        'warn',
        { allowConstantExport: true },
      ],
    },
  },
];
