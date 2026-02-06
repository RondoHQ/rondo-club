# Public REST API Endpoints

This document lists REST API endpoints that do not require WordPress authentication (`permission_callback => __return_true`). Each has a documented security rationale.

## Current Public Endpoints

There are currently no public Rondo Club endpoints that bypass WordPress authentication.

## Security Review Checklist

When adding new public endpoints:
1. Document why authentication cannot be used
2. Implement alternative verification (signatures, tokens, state validation)
3. Rate limit if possible
4. Log suspicious activity
5. Add to this document
