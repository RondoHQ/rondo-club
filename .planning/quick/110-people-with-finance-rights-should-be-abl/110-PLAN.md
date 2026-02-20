---
phase: 110-finance-settings-access
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-base.php
  - includes/class-rest-api.php
  - src/components/layout/Layout.jsx
autonomous: true

must_haves:
  truths:
    - "A user with the financieel capability (but not manage_options) can see the Instellingen menu item under Financiën"
    - "A user with the financieel capability can GET /rondo/v1/finance/settings without a 403"
    - "A user with the financieel capability can POST /rondo/v1/finance/settings without a 403"
    - "A user without financieel capability cannot see the Instellingen menu item"
    - "A user without financieel capability receives 403 on /rondo/v1/finance/settings"
  artifacts:
    - path: "includes/class-rest-base.php"
      provides: "check_financieel_permission() method"
      contains: "check_financieel_permission"
    - path: "includes/class-rest-api.php"
      provides: "finance/settings routes using financieel check"
      contains: "check_financieel_permission"
    - path: "src/components/layout/Layout.jsx"
      provides: "Instellingen nav item without adminOnly"
  key_links:
    - from: "src/components/layout/Layout.jsx"
      to: "canAccessFinancieel"
      via: "requiresFinancieel filter in nav item filter"
      pattern: "requiresFinancieel"
    - from: "includes/class-rest-api.php"
      to: "check_financieel_permission"
      via: "permission_callback on finance/settings routes"
      pattern: "check_financieel_permission"
---

<objective>
Grant users with the `financieel` capability access to Financiën → Instellingen, which is currently admin-only.

Purpose: Finance officers need to view and update finance settings without requiring WordPress admin privileges.
Output: Frontend shows Instellingen to financieel users; backend accepts their API calls.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add check_financieel_permission to RestBase and update finance/settings routes</name>
  <files>includes/class-rest-base.php, includes/class-rest-api.php</files>
  <action>
    In `includes/class-rest-base.php`, add a new public method directly after `check_admin_permission()`:

    ```php
    /**
     * Check if the current user has the financieel capability.
     *
     * @return bool True if user has financieel capability.
     */
    public function check_financieel_permission() {
        return current_user_can( 'financieel' );
    }
    ```

    In `includes/class-rest-api.php`, find the `// Finance settings (admin only)` comment at line ~833 and the two route registrations beneath it (GET and POST on `/finance/settings`). Change both `permission_callback` values from `check_admin_permission` to `check_financieel_permission`. Also update the comment from `(admin only)` to `(financieel capability required)`.
  </action>
  <verify>
    Run `grep -n "check_financieel_permission" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-base.php` — should return the new method.
    Run `grep -n "check_financieel_permission" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` — should return 2 matches (GET and POST callbacks).
    Run `grep -n "check_admin_permission" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php | grep finance` — should return 0 matches.
  </verify>
  <done>class-rest-base.php has check_financieel_permission(); both finance/settings permission_callbacks use it instead of check_admin_permission.</done>
</task>

<task type="auto">
  <name>Task 2: Remove adminOnly from Instellingen nav item in Layout.jsx</name>
  <files>src/components/layout/Layout.jsx</files>
  <action>
    In `src/components/layout/Layout.jsx`, find line ~51:

    ```js
    { name: 'Instellingen', href: '/financien/instellingen', icon: Settings, indent: true, requiresFinancieel: true, adminOnly: true },
    ```

    Remove `, adminOnly: true` from this entry. Leave `requiresFinancieel: true` intact — it already ensures only financieel users see the item. The existing filter at line ~107 (`if (item.adminOnly && !isAdmin) return false;`) will then no longer exclude this item for non-admins.

    Do NOT remove the `adminOnly` filter logic itself — other nav items may use it in future.
  </action>
  <verify>
    Run `grep -n "Instellingen.*financien" /Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx` — result must NOT contain `adminOnly`.
    Run `npm run lint` from `/Users/joostdevalk/Code/rondo/rondo-club` — must pass with 0 warnings.
    Run `npm run build` from `/Users/joostdevalk/Code/rondo/rondo-club` — must succeed.
  </verify>
  <done>The Instellingen nav item has requiresFinancieel: true but no adminOnly: true. Lint and build pass.</done>
</task>

</tasks>

<verification>
After both tasks complete:
1. `grep -n "check_financieel_permission" includes/class-rest-base.php` returns 1 result (the method definition).
2. `grep -n "check_financieel_permission" includes/class-rest-api.php` returns 2 results (GET + POST on /finance/settings).
3. `grep "adminOnly" src/components/layout/Layout.jsx` does NOT include the Instellingen line.
4. `npm run lint && npm run build` passes clean.
</verification>

<success_criteria>
Users with the `financieel` capability see "Instellingen" in the Financiën sidebar section and can load and save finance settings via the REST API. Users without `financieel` still cannot access it.
</success_criteria>

<output>
After completion, create `.planning/quick/110-people-with-finance-rights-should-be-abl/110-SUMMARY.md` with what was changed and why.
</output>
