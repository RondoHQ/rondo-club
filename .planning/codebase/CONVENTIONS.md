# Coding Conventions

**Analysis Date:** 2025-01-13

## Naming Patterns

**Files:**
- React components: PascalCase (`ImportantDateModal.jsx`, `PersonDetail.jsx`)
- React hooks: camelCase with `use` prefix (`usePeople.js`, `useAuth.js`)
- Utilities: camelCase (`formatters.js`, `timeline.js`)
- API client: camelCase (`client.js`)
- PHP classes: kebab-case with `class-` prefix (`class-rest-api.php`)

**Functions:**
- camelCase for all functions (`getPersonName()`, `formatDateValue()`)
- Event handlers: `handle*` prefix (`handleSubmit()`, `handleAdd()`, `handleRemove()`)
- Async functions: no special prefix
- Transform functions: descriptive (`transformPerson()`, `sanitizePersonAcf()`)

**Variables:**
- camelCase for variables (`personData`, `isLoading`)
- UPPER_SNAKE_CASE for constants (`APP_NAME`)
- No underscore prefix for private members

**Types:**
- PascalCase for component props (inline destructuring)
- No TypeScript (JavaScript only)

## Code Style

**Formatting:**
- 2 space indentation
- Semicolons required
- Double quotes preferred for strings
- No explicit Prettier config (manual formatting)

**Linting:**
- ESLint with React plugins
- Config in `package.json`
- Run: `npm run lint`
- Strict mode: `--max-warnings 0`
- Plugins: `react`, `react-hooks`, `react-refresh`

## Import Organization

**Order:**
1. React and React libraries (`react`, `react-router-dom`)
2. Third-party packages (`axios`, `@tanstack/react-query`, `lucide-react`)
3. Internal modules (`./api/client`, `./hooks/usePeople`)
4. Relative imports (`./components/PersonCard`)

**Grouping:**
- Blank line between groups
- Named imports preferred
- Destructured imports from packages

**Path Aliases:**
- No path aliases configured (relative imports used)

## Error Handling

**Patterns:**
- React Query `onError` callbacks for mutations
- `try/catch` in async functions
- Axios interceptor for 401/403 redirects
- `console.error()` for logging errors

**Error Types:**
- Network errors: Redirect to login on 401/403
- Validation errors: Show in form fields
- Mutation errors: Alert with generic message

**Logging:**
- `console.error()` for frontend errors
- `error_log()` for PHP errors
- No structured logging service

## Logging

**Framework:**
- Console methods (`console.error()`, `console.log()`)
- No external logging service

**Patterns:**
- Error logging in catch blocks
- Debug logs should be removed before commit
- 30+ `console.error()` calls currently in production code

## Comments

**When to Comment:**
- JSDoc for utility functions with `@param`, `@returns`
- Inline comments for complex logic
- Avoid obvious comments

**JSDoc/TSDoc:**
- Used for utility functions in `src/utils/formatters.js`
- Not consistently applied to all functions

**TODO Comments:**
- Format: `// TODO: description`
- No standardized tracking system

## Function Design

**Size:**
- Keep functions focused
- Extract helpers for complex logic
- Some large components exist (e.g., `PersonDetail.jsx` ~900 lines)

**Parameters:**
- Destructure objects in parameter list
- Use options object for many parameters
- Props destructured in function signature

**Return Values:**
- Explicit returns preferred
- Early returns for guard clauses
- React Query hooks return `{ data, isLoading, error }`

## Module Design

**Exports:**
- `export default` for React components
- Named exports for hooks and utilities
- One component per file

**Barrel Files:**
- Not used (direct imports to files)

## React Patterns

**Component Structure:**
- Functional components only
- Hooks at top of component
- Event handlers defined inline or above return
- JSX returned at end

**State Management:**
- React Query for server state
- Query keys: `['resource']`, `['resource', 'list']`, `['resource', 'detail', id]`
- Cache invalidation on mutations
- Zustand available for client state

**Form Handling:**
- React Hook Form with `useForm()`
- `Controller` for controlled inputs
- Validation inline in register options

**Modal Pattern:**
- Controlled component with `isOpen`, `onClose`, `onSubmit` props
- Form state managed within modal
- Reset form on close

## PHP Patterns

**Class Structure:**
- Constructor registers WordPress hooks
- Methods handle hook callbacks
- Static methods for utilities
- `STADION_` prefix for all class names

**WordPress Integration:**
- `add_action()` / `add_filter()` in constructor
- `register_rest_route()` for API endpoints
- `wp_die()` for fatal errors
- `WP_Error` for error responses

**Naming:**
- snake_case for functions and variables
- PascalCase for class names
- UPPER_SNAKE_CASE for constants with `STADION_` prefix

---

*Convention analysis: 2025-01-13*
*Update when patterns change*
