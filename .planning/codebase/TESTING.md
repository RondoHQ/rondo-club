# Testing Patterns

**Analysis Date:** 2025-01-13

## Test Framework

**Current State:** No testing framework configured

**Runner:**
- Not configured
- No vitest.config or jest.config files present

**Assertion Library:**
- Not configured

**Run Commands:**
```bash
# No test commands available
# Lint command exists:
npm run lint                              # ESLint checks
```

## Test File Organization

**Location:**
- No test files exist
- No `__tests__/` directories
- No `*.test.*` or `*.spec.*` files found

**Naming:**
- Not established (no tests)

**Recommended Structure:**
```
src/
  utils/
    formatters.js
    formatters.test.js       # Co-located tests
  hooks/
    usePeople.js
    usePeople.test.js
  components/
    PersonEditModal.jsx
    PersonEditModal.test.jsx
```

## Test Structure

**Current:** Not applicable (no tests)

**Recommended Suite Organization:**
```javascript
import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('ModuleName', () => {
  describe('functionName', () => {
    beforeEach(() => {
      // reset state
    });

    it('should handle valid input', () => {
      // arrange
      // act
      // assert
    });

    it('should throw on invalid input', () => {
      expect(() => functionName(null)).toThrow('error');
    });
  });
});
```

## Mocking

**Current:** Not applicable (no tests)

**Recommended Framework:**
- Vitest built-in mocking (aligns with Vite build)

**Recommended Patterns:**
```javascript
import { vi } from 'vitest';
import axios from 'axios';

vi.mock('axios');

describe('API client', () => {
  it('fetches people', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: [] });
    // test code
  });
});
```

**What to Mock:**
- Axios HTTP requests
- WordPress `window.prmConfig` global
- React Query client
- localStorage/sessionStorage

**What NOT to Mock:**
- Pure utility functions
- Simple formatters
- Internal business logic

## Fixtures and Factories

**Current:** Not applicable (no tests)

**Recommended Test Data:**
```javascript
function createTestPerson(overrides = {}) {
  return {
    id: 1,
    title: { rendered: 'John Doe' },
    acf: {
      first_name: 'John',
      last_name: 'Doe',
      ...overrides.acf
    },
    ...overrides
  };
}
```

**Recommended Location:**
- Factory functions in test file near usage
- Shared fixtures in `tests/fixtures/` if needed

## Coverage

**Requirements:**
- No coverage targets currently
- No coverage tooling configured

**Recommended Configuration:**
- Vitest coverage via c8 (built-in)
- Target critical paths: API client, hooks, formatters

**Recommended Commands:**
```bash
npm run test:coverage
open coverage/index.html
```

## Test Types

**Unit Tests (recommended):**
- Scope: Single function/hook in isolation
- Mock: All external dependencies
- Priority: `src/utils/`, `src/hooks/`

**Integration Tests (recommended):**
- Scope: Multiple modules together
- Mock: HTTP layer only
- Priority: Form submissions, data transformations

**E2E Tests:**
- Not currently planned
- Could use Playwright if needed

## Code Quality Tools

**Available:**
- ESLint with React plugins
- Script: `npm run lint`

**Not Available:**
- Prettier (no config)
- TypeScript (JavaScript only)
- Husky/lint-staged (no git hooks)

## Recommended Test Implementation

**Suggested Framework:** Vitest

**Installation:**
```bash
npm install -D vitest @testing-library/react @testing-library/jest-dom
```

**Configuration (`vitest.config.js`):**
```javascript
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.js',
  },
});
```

**Priority Test Areas:**

1. **Utility Functions:** `src/utils/formatters.js`
   - `decodeHtml()`, `getPersonName()`, `getCompanyName()`
   - Pure functions, easy to test

2. **API Client:** `src/api/client.js`
   - Mock Axios responses
   - Test error handling

3. **React Hooks:** `src/hooks/usePeople.js`
   - Mock React Query
   - Test data transformations

4. **Import Logic:** `includes/class-vcard-import.php` (PHPUnit)
   - Complex parsing logic
   - Multiple format support

---

*Testing analysis: 2025-01-13*
*Update when test patterns change*
