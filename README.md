<p align="center">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="art/cachet-core-dark.svg">
      <img alt="Cachet Core" src="art/cachet-core-light.svg">
    </picture>
</p>

<p align="center">
    <a href="https://github.com/cachethq/core/actions">
        <img src="https://github.com/cachethq/core/workflows/run-tests/badge.svg" alt="Build Status">
    </a>
    <a href="https://github.com/sponsors/cachethq/?sponsor=1">
        <img src="https://img.shields.io/github/sponsors/cachethq" alt="GitHub Sponsors">
    </a>
    <a href="https://packagist.org/packages/cachethq/core">
        <img src="https://img.shields.io/packagist/dt/cachethq/core" alt="Total Downloads">
    </a>
    <a href="https://packagist.org/packages/cachethq/core">
        <img src="https://img.shields.io/packagist/v/cachethq/core" alt="Latest Stable Version">
    </a>
</p>

## Introduction

This package provides the core functionality of [Cachet](https://github.com/cachethq/cachet) and may be installed into existing Laravel applications. Cachet 3.x will ship as a standalone Laravel application that requires this package.

## Project Status

Cachet 3.x is currently in development and is not yet completely ready for production use. We are actively working on the project and will provide updates as we progress.

- [x] Incident Management
- [x] Incident Updates
- [x] Scheduled Maintenance
- [x] Scheduled Maintenance Updates
- [x] Components
- [x] Metrics
- [x] Subscribers
  - Limited to global subscriptions for now.
- [x] API
  - Complete but may need some tweaks as we progress.
- [x] Webhooks
- [x] Dashboard
- [x] Localization
- [x] Status Page Customization
- [x] Meta
- [x] Tags
- [x] User Management

## Stack

Cachet is built on:

- [Laravel](https://laravel.com)
- [Filament](https://filamentphp.com)
- [Tailwind CSS](https://tailwindcss.com)
- [Alpine.js](https://alpinejs.dev)

## Development

To get started developing Cachet, please check out the [Development Guide](https://cachethq.io/docs/v3.x/development).

### Dashboard Credentials

When running Cachet via the `composer dev` command, Workbench will seed a user that you can use to log in to the dashboard.
Login to the account at `/dashboard` and use credentials:

- **Email:** `test@test.com`
- **Password:** `test123`

## Large Status Pages

For status pages with hundreds or thousands of components, switch **Settings → Manage Cachet → Component layout** to **List with search**. The list layout renders the same amount of work whatever the number of components:

- a summary of components by effective status, where each count is a filter;
- search by name, group and tag filters, and sorting, all in the URL so results can be shared;
- a paginated list (`CACHET_COMPONENT_LIST_PER_PAGE`, 50 by default);
- a grid view with one colored tile per component (`CACHET_COMPONENT_GRID_LIMIT`, 2000 by default);
- a page per component at `/components/{id}` with its uptime, incidents, maintenance and status changes.

Uptime history is read from the `component_uptime_days` table. The scheduler refreshes the last two days every fifteen minutes. Backfill it once after upgrading:

```bash
php artisan cachet:record-uptime --days=90
```

Rows older than `CACHET_UPTIME_RETENTION_DAYS` (365 by default) are pruned daily.

## Fork Changelog

Changes on top of upstream [`cachethq/core@8ae3e2c`](https://github.com/cachethq/core/commit/8ae3e2c0aed1027ce675f652a91d5745cb15ef14) (2026-11-02). Commits taken from upstream pull requests keep their original authors and reference the pull request.

### Features

- **List layout for large status pages** (`d2a6cac1`). A new *Component layout* setting adds a list layout next to the default group layout. It has:
  - counters per effective status, each acting as a filter;
  - search by name, filters by group and tag, sorting, all kept in the URL;
  - a paginated list (50 per page), most severe first;
  - a grid view with one colored tile per component;
  - a page per component at `/components/{id}` with 90 days of uptime, incidents, maintenance and status changes;
  - 90-day uptime bars for all components and for each group, when *Display system status* is on.
- **Daily uptime rollup** (`9a9da5fe`). The `component_uptime_days` table and the `cachet:record-uptime` command, scheduled every fifteen minutes, store one row per component per day.
- **Server-side component search in the dashboard** (`efbc8fd9`).
  - Every component picker searches on the server and returns at most 50 results: incident and maintenance forms, record update, and attach actions.
  - New component filters on the incident and maintenance tables.
  - New status, group, tag and enabled filters on the components table.
  - The dashboard components widget is a paginated, searchable table with inline status changes.
- **Status page theme mode** (`3e564ecf`, based on [#479](https://github.com/cachethq/core/pull/479), closes [#68](https://github.com/cachethq/core/issues/68)). Automatic, light or dark, with a visitor toggle in automatic mode.
- **Component statuses on incident updates** (`cce87b9e`, `98687d76` by Mitul Patel, [#481](https://github.com/cachethq/core/pull/481), closes [#194](https://github.com/cachethq/core/issues/194)). Recording an update can change the status of the affected components, from the dashboard and the API.
- **Dashboard language in the profile** (`2243f7ab`). Every user can choose the dashboard language, or the system default, which follows the browser.
- **Toasts for dashboard actions** (`b28d10e8`). Verifying a subscriber, completing a schedule, sending a user's verification email, updating a webhook secret and changing a status in the components widget now show a success toast.
- **Custom favicon** (`a1e352fc`). Manage Theme accepts a PNG or ICO favicon for the status page and the dashboard. With dynamic favicons on, the status icon replaces it during incidents and maintenance.
- **Affected components as links** (`6efae483`). Incidents and maintenance on the status page list their affected components as pills linking to each component page.
- **Permission groups** (`8ae68408`). Replaces the admin flag. Users belong to groups, and they can do the union of what their groups allow.
  - Each resource has view, manage and delete permissions. Settings and the user's own API keys have one permission each. Managing or deleting a resource also allows viewing it.
  - The group form lists the actions as checkboxes, like the API key form.
  - Screens, dashboard widgets and actions are hidden from users who cannot view them. Users who can view but not edit get read-only pages.
  - The *Administrators* and *Read only* groups always exist, are translated, and follow the permission list. Existing admins join *Administrators* and everyone else joins *Read only*.
  - API tokens and MCP sessions never exceed the permissions of their owner's groups, including tokens created before this change.
  - Nobody can create a group, assign a group or change a user that holds more permissions than they have.
  - Breaking: the `users.is_admin` column and `CachetUser::isAdmin()` are removed. A custom user model must implement `CachetUser` with the `HasGroups` trait.

### Bug fixes

- **Settings read once per component** (`e1c525f8`). Cachet's settings classes were never registered with laravel-settings, so every read queried the database and skipped the cache. With 1500 components the status page ran 1548 settings queries.
- **Fixed incident reverted on save** (`20035af7`). Recording an update from the incident edit page and then saving the form wrote the old status back. The maintenance edit page lost the completion time set by an update in the same way.
- **Incident status after editing or deleting updates** (`a84c4d2e` by Jay Hemnani, [#497](https://github.com/cachethq/core/pull/497), fixes [#494](https://github.com/cachethq/core/issues/494)).
- **Incidents without "Occurred at"** (`9f1d3e18` by Jay Hemnani, [#498](https://github.com/cachethq/core/pull/498), fixes [#495](https://github.com/cachethq/core/issues/495)). They now show and sort by their created time.
- **Group status in the system status view** (`17a403a5` by Jay Hemnani, [#503](https://github.com/cachethq/core/pull/503), fixes [#484](https://github.com/cachethq/core/issues/484)).
- **Saving Manage Theme with a stored banner** (`3e060ad7` by VincentBorgers, [#480](https://github.com/cachethq/core/pull/480), fixes [#467](https://github.com/cachethq/core/issues/467) and [#471](https://github.com/cachethq/core/issues/471)).
- **New users defaulted to English** (`561230ef`). `users.preferred_locale` no longer defaults to `en`, so new users follow the system default.
- **"System default" language ignored** (`2243f7ab`). An empty preferred locale now falls back to the browser language.
- **Literal `:name` in the "update recorded" toast in pt-BR** (`b632d26d`).
- **Email verification link failed with a 500 error**. The `verify-email` route required a signed-in user but had no session middleware, so it always redirected guests to a `login` route the app does not define. The signed link now verifies the address without signing in.
- **Verification email in English**. Laravel's own email strings had no pt-BR translation, so the verification email ignored the user's language. Cachet now ships `pt_BR.json` for them.

### Performance

- **Redundant index removed** (`aeb76aa3`, fixes [#465](https://github.com/cachethq/core/issues/465)). The single-column `incident_components.incident_id` index was covered by the composite unique.
- With 1500 components, the status page went from 3.6 s, 4.8 MB and 1578 queries to 0.4 s, 1.2 MB and 29 queries in the list layout. The 90-day history went from 94 s and 499 MB of HTML to a precomputed read (`e1c525f8`, `efbc8fd9`, `9a9da5fe`, `d2a6cac1`).

### Translations

- **Hardcoded English labels translated** (`dc85ba56`, `50254bf4` by jnk0, [#504](https://github.com/cachethq/core/pull/504)).
- **pt-BR for the new texts** (`bcd111c1`).
- **pt-BR complete** (`b632d26d`). The 142 keys that only existed in English are translated, and two footer strings left in English were fixed.
- **pt-BR "De" / "Até" labels** on the incident timeline (`6efae483`).

### Tests

- Tests for every change above, including the translated dashboard labels (`7a48f596`) and stricter tests for update component statuses (`af67db18`).
- Tests fail when a pt-BR key is missing, or when a translation drops a placeholder or a plural form (`b632d26d`).
- The status page list runs the same number of queries regardless of the number of components.
- The affected components tests on the status and schedule pages check the component links added in `6efae483` (`68b07559`).

### Documentation

- This README: large status pages and roadmap (`90f3daa6`), cache requirements (`19bf15f3`) and this changelog.

## Cache Requirements

The dashboard's success toasts ("Created", "Saved", and so on) are stored in the application cache between requests, in the store set by `filament.cache_store` (the default cache store when unset). Use a persistent cache:

- `CACHE_STORE=array` drops every toast, because the array store only lives for one request.
- With more than one application server, use a shared store such as `database` or `redis`. With `file`, a toast written on one server is not seen by the others.

## Roadmap

Planned work for large deployments, such as monitoring RPA robots:

- [ ] **Bulk status endpoint.** Update the status of many components in one API request. The API allows 300 requests per minute per user (`CACHET_API_RATE_LIMIT`), so 1500 components reporting every minute through one token would be throttled.
- [ ] **Heartbeat (dead man's switch).** Move a component to unknown or major outage when it has not reported for a configurable time. Today a component that stops reporting stays in its last status.
- [ ] **Overall status by percentage or critical groups.** With thousands of components, at least one is usually degraded, so the status summary nearly always shows a partial outage.

## Sponsors

<p align="center">
    <a href="https://jump24.co.uk"><img width="100px" src="https://github.com/jumptwentyfour.png" alt="Jump24"></a>
    <a href="https://xyphen-it.nl"><img width="100px" src="https://github.com/xyphen-it.png" alt="Xyphen-IT"></a>
    <a href="https://coderabbit.ai/"><img width="100px" src="https://github.com/coderabbitai.png" alt="Code Rabbit"></a>
    <a href="https://scramble.dedoc.co/"><img width="100px" src="https://github.com/dedoc.png" alt="de:doc"></a>
</p>

## Security Vulnerabilities

Please review our [security policy](https://github.com/cachethq/cachet/security/policy) on how to report security vulnerabilities.

## License

Cachet Core is open-sourced software licensed under the [MIT license](LICENSE.md).

## Trademarks

Copyright (c) 2023-present Alt Three Services Limited. The Cachet name and logo are trademarks of Alt Three Services Limited. Please see our [trademark guidelines](https://github.com/cachethq/core/blob/main/TRADEMARKS.md) for info on acceptable usage.

## Community

Have questions, comments or feedback? [Start a discussion](https://github.com/cachethq/cachet/discussions/new). For the latest news and release notes, visit [cachethq.io](https://cachethq.io).

## Contributors

<a href="https://github.com/cachethq/core/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=cachethq/core&max=400&columns=20" width="100%"/>
</a>
