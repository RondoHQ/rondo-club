# Contributing to Rondo Club

Thanks for contributing to Rondo Club.

## Before you start

- Open an issue or discuss the change before large feature work.
- Keep pull requests focused. Small, reviewable PRs merge faster.
- Update documentation and the changelog when behavior changes.

## Local setup

### Requirements

- PHP 8.0+
- Node.js 18+
- Composer
- WordPress 6+

### Install

```bash
git clone git@github.com:RondoHQ/rondo-club.git
cd rondo-club
composer install
npm install
```

## Development workflow

### Frontend

Start the Vite dev server:

```bash
npm run dev
```

Build production assets:

```bash
npm run build
```

Lint JavaScript/JSX:

```bash
npm run lint
```

### PHP

Run PHP coding standards:

```bash
composer lint
```

Auto-fix what PHPCS can fix:

```bash
composer lint:fix
```

### Tests

Run the wp-browser / Codeception wpunit suite:

```bash
composer test
```

If you add or change business logic, add or update tests in `tests/Wpunit/`.

## Project conventions

- Follow existing naming and file structure conventions.
- Keep changes narrow and avoid unrelated refactors in the same PR.
- Prefer adding changelog entries under `## [Unreleased]` in `CHANGELOG.md`.
- For API or UI changes, include screenshots or a short explanation in the PR.
- For WordPress code, follow the rules in `phpcs.xml.dist`.

## Pull requests

Before opening a PR, make sure you have:

- [ ] run `npm run lint`
- [ ] run `composer lint`
- [ ] run relevant tests
- [ ] updated `CHANGELOG.md` when needed
- [ ] updated docs/README if setup or behavior changed

PRs should clearly describe:

- what changed
- why it changed
- how it was tested
- whether any manual migration or deploy step is needed

## Security

Please do not report security issues in public GitHub issues. See [SECURITY.md](SECURITY.md).
