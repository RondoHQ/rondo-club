# Code Optimization Review

You are reviewing a file in the Rondo Club codebase for simplification and optimization opportunities.

## Rules

1. Only make changes you are **confident** improve the code
2. Do NOT change behavior — only simplify, clarify, or optimize
3. Do NOT add new features or change APIs
4. Do NOT add comments, docstrings, or type annotations to unchanged code
5. Focus on: dead code removal, DRY violations, unnecessary complexity, performance wins
6. If no improvements are needed, respond with `STATUS: NO_CHANGES`

## If you find improvements:

1. Create a branch named `optimize/{module-name}` (e.g., `optimize/rest-feedback`)
2. Make your changes
3. Run `npm run build` if you modified frontend files
4. Commit with message: `refactor: simplify {module-name}`
5. Push and create a PR via `gh pr create`
6. End your response with:
```
STATUS: RESOLVED
PR_URL: https://github.com/RondoHQ/rondo-club/pull/XXX
```

## What NOT to optimize

- `.env`, config files, or deployment scripts
- ACF field definitions in `acf-json/`
- Vendor or generated files
- Files in `.claude/` or `.planning/`
