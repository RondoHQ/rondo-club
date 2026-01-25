# Codebase Concerns

**Analysis Date:** 2025-01-13

## Tech Debt

**Large REST API Class:**
- Issue: `includes/class-rest-api.php` is ~107KB with 2800+ lines
- Files: `includes/class-rest-api.php`
- Why: Organic growth as features were added
- Impact: Hard to navigate, test, and maintain
- Fix approach: Split into domain-specific endpoint classes (e.g., `class-rest-people.php`, `class-rest-slack.php`)

**Console.error() in Production Code:**
- Issue: 30+ `console.error()` calls left in production components
- Files: `src/pages/People/PersonDetail.jsx` (lines 203, 239, 282, 323, 366, 409, 440), and many others
- Why: Debug logging not cleaned up
- Impact: Console noise, unprofessional, potential info leak
- Fix approach: Remove or replace with proper error reporting service

**Missing .env.example:**
- Issue: Environment variables required but not documented
- Files: Missing from repository root
- Why: Not created during initial setup
- Impact: New developers don't know required configuration
- Fix approach: Create `.env.example` documenting `STADION_SLACK_CLIENT_ID`, `STADION_SLACK_CLIENT_SECRET`, `STADION_SLACK_SIGNING_SECRET`

**Duplicated HTML Decode Logic:**
- Issue: `decodeHtml()` pattern duplicated
- Files: `src/utils/formatters.js:14-16`, `src/utils/familyTreeBuilder.js:44-46`
- Why: Copy-paste during feature development
- Impact: Maintenance burden, inconsistent behavior possible
- Fix approach: Consolidate to single utility in `formatters.js`

## Known Bugs

**No Known Bugs Found**
- Codebase appears stable based on static analysis
- No TODO comments indicating bugs

## Security Considerations

**Weak Token Storage:**
- Risk: Slack bot token stored with base64 encoding (not encryption)
- Files: `includes/class-rest-api.php:2510`
- Current mitigation: Token in user meta, only accessible via WordPress
- Recommendations: Use proper encryption (sodium_crypto_secretbox or similar)

**Potential XSS via dangerouslySetInnerHTML:**
- Risk: User-supplied HTML content rendered without client-side sanitization
- Files: `src/components/Timeline/TimelineView.jsx:127`
- Current mitigation: Assumes server sanitizes content
- Recommendations: Add DOMPurify or similar client-side sanitization

**Slack Webhook URL Validation:**
- Risk: `filter_var($param, FILTER_VALIDATE_URL)` accepts any URL
- Files: `includes/class-rest-api.php:122`
- Current mitigation: None
- Recommendations: Whitelist only `hooks.slack.com` domain

**Permission Callbacks Using __return_true:**
- Risk: Five REST endpoints publicly accessible
- Files: `includes/class-rest-api.php:31, 170, 220, 241, 248`
- Current mitigation: Some endpoints verify signatures/state internally
- Recommendations: Review each endpoint, document why public access is needed

## Performance Bottlenecks

**N+1 Query Pattern for Company Names:**
- Problem: Loads company names separately for each job in work history
- Files: `src/pages/People/PersonDetail.jsx:905-912`
- Measurement: Not measured, but creates multiple API calls
- Cause: `useQueries()` with array of company IDs
- Improvement path: Embed company data in person response or batch load

**Pagination Without Upper Limit:**
- Problem: `usePeople()` loads ALL people by looping through pages
- Files: `src/hooks/usePeople.js:54-78`
- Measurement: Could load 10K+ contacts if database grows
- Cause: Design for small contact lists
- Improvement path: Implement server-side search/filter, cursor-based pagination

## Fragile Areas

**Large PersonDetail Component:**
- Files: `src/pages/People/PersonDetail.jsx` (~900 lines)
- Why fragile: Handles 15+ different update operations, complex state
- Common failures: State sync issues between tabs
- Safe modification: Extract sub-components, add integration tests
- Test coverage: None

**Slack Integration in REST API:**
- Files: `includes/class-rest-api.php:2400-2700+` (~300 lines)
- Why fragile: OAuth flow, token storage, signature verification all mixed
- Common failures: OAuth state issues, token expiration
- Safe modification: Extract to dedicated `class-slack-integration.php`
- Test coverage: None

**Import Parsers:**
- Files: `includes/class-monica-import.php`, `includes/class-google-contacts-import.php`, `includes/class-vcard-import.php`
- Why fragile: Complex regex parsing, multiple format variations
- Common failures: Unexpected CSV/vCard formats
- Safe modification: Add validation tests with sample files
- Test coverage: None

## Scaling Limits

**Client-Side People Loading:**
- Current capacity: Works well for ~500 contacts
- Limit: Performance degrades with 1000+ contacts
- Symptoms at limit: Slow initial load, high memory usage
- Scaling path: Server-side pagination and search

## Dependencies at Risk

**No Critical Risks Detected**
- Dependencies are current and maintained
- sabre/dav is actively maintained

## Missing Critical Features

**No Automated Testing:**
- Problem: Zero test files (unit, integration, or E2E)
- Current workaround: Manual testing
- Blocks: Confident refactoring, CI/CD automation
- Implementation complexity: Medium (setup Vitest, write initial tests)

## Test Coverage Gaps

**All Code Untested:**
- What's not tested: Entire codebase
- Risk: Regressions undetected, refactoring risky
- Priority: High
- Difficulty to test: Setup required for Vitest + React Testing Library

**Priority Test Areas:**
1. `src/utils/formatters.js` - Pure functions, easy to test
2. `src/api/client.js` - Critical API layer
3. `src/hooks/usePeople.js` - Complex data transformations
4. Import parsers - Complex parsing logic

---

*Concerns audit: 2025-01-13*
*Update as issues are fixed or new ones discovered*
