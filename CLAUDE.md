# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the official Laravel starter kit built with **Livewire** (`laravel/livewire-starter-kit`). It provides a modern, reactive full-stack experience using only PHP and minimal JavaScript. The user can authenticate via email/password, passkeys, or two-factor authentication.

### Tech Stack

- **PHP** 8.3+ (project requires `^8.3`, rules in Boost assume 8.5)
- **Laravel** v13
- **Livewire** v4 + **Flux UI Free** v2 (free component library)
- **Laravel Fortify** v1 (auth backend: passkeys, 2FA, password confirmation)
- **Laravel Boost** v2 (MCP server for database/schema/doc queries)
- **Pest** v4 (testing) with Laravel plugin
- **Larastan** v3 (PHPStan level 7 — static analysis)
- **Pint** v1 (code style)
- **Tailwind CSS** v4 via `@tailwindcss/vite` (NOT v3 — uses `@theme` directive in `resources/css/app.css`)
- **Vite** v8 + `laravel-vite-plugin` v3 (also bundles Bunny fonts via `Instrument Sans`)

## Common Commands

All commands are run from the project root unless noted.

### Development

| Task | Command |
|---|---|
| First-time setup (install + migrate + build) | `composer setup` |
| Dev server (server + queue + logs + vite) | `composer run dev` |
| Run frontend dev server alone | `npm run dev` |
| Build frontend assets | `npm run build` |
| Tinker (PHP REPL in app context) | `php artisan tinker --execute '...'` (always single-quote) |

### Testing & Quality

| Task | Command |
|---|---|
| Run **all** tests (clears config, lints, type-checks, then tests) | `composer test` |
| Run a single test file (fast, no lint/types) | `php artisan test --compact tests/Feature/DashboardTest.php` |
| Run a single test by name | `php artisan test --compact --filter=testName` |
| Format PHP (only changed files) | `vendor/bin/pint --dirty --format agent` |
| Check formatting without changes | `composer lint:check` |
| Static analysis (Larastan level 7) | `composer types:check` |
| Run CI-equivalent local check | `composer ci:check` |

### Artisan

- List commands: `php artisan list`
- Inspect routes: `php artisan route:list` (filter with `--method`, `--name`, `--except-vendor`)
- Make models with factory + seeder: `php artisan make:model Foo -fs` (always pass `--no-interaction`)
- Make Pest tests: `php artisan make:test --pest SomeFeatureTest` (no `Feature/` prefix in name)

## Architecture

### High-Level Layers

- **Routes** (`routes/web.php`, `routes/settings.php`) — only a handful; most pages are Livewire full-page components via `Route::livewire()`.
- **Livewire components** (`app/Livewire/`) — full-page or nested. `Settings/*` covers profile/appearance/security/2FA. `Actions/Logout.php` is a small action component.
- **Fortify actions** (`app/Actions/Fortify/`) — pure-PHP auth actions invoked by Fortify. `Auth/` subdir holds per-flow actions (e.g. `CreateNewUser`, `ResetUserPassword`). `TwoFactor/RecoveryCodes.php` is a Livewire wrapper.
- **Models** (`app/Models/`) — only `User.php` so far. `User` uses `#[Fillable]` and `#[Hidden]` PHP attributes (not the legacy `$fillable`/`$hidden` arrays). Implements `PasskeyUser` and uses `TwoFactorAuthenticatable` + `PasskeyAuthenticatable` traits.
- **Migrations** (`database/migrations/`) — standard Laravel starter tables plus `passkeys` and 2FA columns added in later migrations.
- **Views** (`resources/views/`) — `welcome.blade.php`, `dashboard.blade.php`, `components/` (shared Blade components), `livewire/` (per-component views, one per Livewire class), `layouts/`, `partials/`, `flux/` (Flux overrides if any).

### Key Conventions

- **Livewire over controllers** — pages are full-page Livewire components. Add a new page = create a `app/Livewire/{Name}.php` + `resources/views/livewire/{name}.blade.php` pair, then register with `Route::livewire()`.
- **Fortify handles auth flow** — don't add custom login/register controllers; customize via `app/Actions/Fortify/`.
- **Passkeys** are first-class — see `resources/views/components/passkey-*.blade.php` and the `@laravel/passkeys` JS bundle. The `.well-known/passkey-endpoints` route advertises enrollment/management URLs.
- **2FA recovery codes** are a Livewire component (`app/Livewire/Settings/TwoFactor/RecoveryCodes.php`).
- **PHP 8 attributes** on models (e.g. `#[Fillable(['name','email','password'])]`) — match this style for new models rather than legacy property arrays.
- **No new base directories** without approval. Stick to `app/{Actions,Console,Http,Livewire,Models,Providers}`.

### Frontend Bundling

`vite.config.js` compiles three entries: `resources/css/app.css`, `resources/js/app.js`, and `resources/js/passkeys.js`. The `bunny()` plugin fetches Instrument Sans weights 400/500/600. If you see a `ViteException: Unable to locate file in Vite manifest` error, run `npm run build` (or `composer run dev`).

## Skills (Activate Before Working)

Project-local skills live in **both** `.agents/skills/` and `.claude/skills/` (kept in sync). Activate the relevant one whenever you touch that domain — don't wait until stuck:

- `fortify-development` — Fortify auth flows, passkeys, 2FA
- `livewire-development` — Livewire v4 components, Alpine integration
- `fluxui-development` — Flux UI Free v2 components (look here before building a new UI primitive)
- `laravel-best-practices` — general Laravel idioms
- `pest-testing` — writing/running Pest tests
- `tailwindcss-development` — Tailwind v4 (uses `@theme`, not v3 config files)

## Boost MCP Tools (Prefer These)

The Laravel Boost MCP server is configured in `.mcp.json`. Prefer its tools over manual alternatives:

- `database-query` — read-only SQL (no tinker SQL)
- `database-schema` — inspect tables/columns/indexes before writing migrations
- `get-absolute-url` — resolve scheme/domain/port before sharing URLs
- `browser-logs` — recent browser console errors (old entries are noise)
- `last-error` / `read-log-entries` — last backend exception / recent logs
- `search-docs` — **always** call this before making code changes; pass a `packages` array to scope results; use multiple broad queries
- `application-info` — returns PHP/Laravel versions and full package list (already in context above)

## Critical Rules

- Every change must be **programmatically tested** (write a test or update one). Run the minimum number of tests needed to confirm.
- Format any modified PHP with `vendor/bin/pint --dirty --format agent` before finalizing.
- Never delete tests without approval.
- Never change application dependencies without approval.
- Don't create verification scripts or tinker scratchpads when tests cover the behavior.
- Don't create documentation files unless explicitly asked.
- **Reply in the language the user uses** (this project has a Spanish-speaking user — see `feedback-idioma-espanol` in memory).

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/cashier (CASHIER) - v16
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
