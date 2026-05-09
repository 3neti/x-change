# Implementation Plan — Evolve x-change into a Package-Owned Default UI Platform

## Objective

Refactor the current x-change UI approach into a package-owned UI platform where:

- the package owns the Vue source of truth;
- the host app remains mostly configuration-only;
- installation automatically publishes the working UI;
- the dashboard and Pay Code UI evolve independently of the host app;
- the UI remains API-first and backend-driven;
- licensees may still customize later by editing published files.

The intended outcome is:

```bash
composer require 3neti/x-change
php artisan x-change:install
npm install
npm run build
```

and immediately:

```text
/x/dashboard
/x/pay-codes
/x/pay-codes/create
/x/balances
```

should work.

---

# Core Architectural Decision

## Package Owns the UI

The package itself becomes the canonical source of:

```text
pages
components
composables
layouts
branding assets
dashboard widgets
Pay Code forms
```

inside:

```text
packages/x-change/resources/js/
```

The host app receives a published copy through:

```bash
php artisan vendor:publish
```

or automatically via:

```bash
php artisan x-change:install
```

---

# Why This Direction Is Necessary

## Problem with Host-App-Owned Vue

The earlier approach attempted to modify:

```text
AppSidebar.vue
AppHeader.vue
Welcome.vue
starter-kit Dashboard.vue
```

inside the host app.

This creates several architectural problems:

### 1. Tight coupling to one starter kit

The package becomes dependent on:
- starter-kit structure
- host app layouts
- host navigation
- host sidebar design
- host shell conventions

This is not sustainable for a reusable package.

---

### 2. Host app stops being “dumb”

The host app now becomes responsible for:
- wiring routes
- importing components
- updating layouts
- adding nav items
- maintaining sidebar state
- merging future package UI changes

That defeats the goal of:

> install-and-run package UX.

---

### 3. Package UI becomes impossible to evolve safely

If every consuming app edits:
- AppSidebar.vue
- AppHeader.vue
- Welcome.vue

then:
- upgrades become difficult
- merge conflicts appear
- UI evolution slows down
- AI agents lose a stable source of truth

---

# Correct Architectural Model

## The Package Owns the Product Surface

The package should behave like:

```text
a mini application platform
```

not merely:
- controllers
- routes
- APIs

The package should own:
- dashboard
- Pay Code management
- balances
- claim flows
- dashboard widgets
- layouts
- composables
- branding defaults

while the host app merely:
- installs
- configures
- optionally overrides later

---

# Design Doctrine

## Host App Responsibilities

The host app should ideally only:
- configure `.env`
- run install command
- compile frontend assets
- optionally override published files later

The host app should NOT be required to:
- manually wire navigation
- manually create layouts
- manually import components
- manually update starter-kit pages

---

## Package Responsibilities

The package should own:
- its own layouts
- its own sidebar
- its own navigation
- its own composables
- its own API bindings
- its own dashboard widgets
- its own branding defaults
- its own page structure

---

# New Canonical Structure

## Package Source of Truth

Inside:

```text
packages/x-change/resources/js/
```

create:

```text
pages/x-change/
components/x-change/
composables/
layouts/x-change/
```

---

# Package-Owned UI Structure

## Pages

```text
pages/x-change/Dashboard.vue
pages/x-change/pay-codes/Index.vue
pages/x-change/pay-codes/Create.vue
pages/x-change/pay-codes/Show.vue
pages/x-change/Balances.vue
```

---

## Components

```text
components/x-change/dashboard/
components/x-change/pay-codes/
components/x-change/layout/
components/x-change/shared/
components/x-change/charts/
```

Examples:

```text
DashboardStatsCard.vue
RecentPayCodesTable.vue
RecentClaimsTable.vue
SystemHealthCard.vue
PayCodeForm.vue
ValidationOptions.vue
RiderSettings.vue
```

---

## Layouts

```text
layouts/x-change/XChangeLayout.vue
```

This layout owns:
- sidebar
- navigation
- breadcrumbs
- dashboard shell
- auth wrapper
- branding

The package no longer depends on:
- AppSidebar.vue
- AppHeader.vue
- starter-kit layouts

---

## Composables

```text
composables/useXChangeRoutes.ts
composables/useXChangeDashboardApi.ts
composables/usePayCodeApi.ts
composables/usePayCodeForm.ts
```

These composables belong to the package UI layer.

---

# Phase 1 — Move Vue Source of Truth into Package

## Goal

Move all x-change-specific UI into package resources.

---

## Step 1. Move package pages

Move from host app:

```text
resources/js/pages/x-change/*
```

into:

```text
packages/x-change/resources/js/pages/x-change/*
```

---

## Step 2. Move package components

Move from host app:

```text
resources/js/components/x-change/*
```

into:

```text
packages/x-change/resources/js/components/x-change/*
```

---

## Step 3. Move package composables

Move:

```text
useXChangeDashboardApi.ts
usePayCodeApi.ts
usePayCodeForm.ts
```

into:

```text
packages/x-change/resources/js/composables/
```

---

## Step 4. Create package layout

Create:

```text
packages/x-change/resources/js/layouts/x-change/XChangeLayout.vue
```

This layout replaces dependency on:
- AppSidebar.vue
- AppHeader.vue
- starter-kit dashboard layouts

---

# Phase 2 — Remove Host-App Shell Coupling

## Goal

Prevent dependency on starter-kit internals.

---

## Step 5. STOP modifying host shell files

Do NOT refactor:
- AppSidebar.vue
- AppHeader.vue
- Welcome.vue
- auth layouts
- starter-kit Dashboard.vue

These belong to the host app.

The package should not depend on them.

---

## Step 6. Use package layout instead

Each package page should use:

```ts
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';

defineOptions({
    layout: XChangeLayout,
});
```

---

# Phase 3 — Normalize Publish Strategy

## Goal

Make the package installable as a product surface.

---

## Step 7. Rename publish tag

Replace:

```php
'x-change-pages'
```

with:

```php
'x-change-ui'
```

Reason:
- pages
- components
- composables
- layouts

are all part of UI.

---

## Step 8. Publish layouts too

Add:

```php
$this->packagePath('resources/js/layouts/x-change')
    => resource_path('js/layouts/x-change'),
```

---

## Step 9. Final UI publish block

Final shape:

```php
$this->publishes([
    $this->packagePath('resources/js/pages/x-change')
        => resource_path('js/pages/x-change'),

    $this->packagePath('resources/js/components/x-change')
        => resource_path('js/components/x-change'),

    $this->packagePath('resources/js/layouts/x-change')
        => resource_path('js/layouts/x-change'),

    $this->packagePath('resources/js/composables')
        => resource_path('js/composables'),
], 'x-change-ui');
```

---

# Phase 4 — Normalize Branding Assets

## Goal

Avoid host-app collisions.

---

## Step 10. Publish assets under namespace

Replace:

```php
public_path('images')
```

with:

```php
public_path('vendor/x-change/images')
```

and:

```php
public_path('favicon.ico')
```

with:

```php
public_path('vendor/x-change/favicon.ico')
```

---

## Step 11. Update Vue asset references

Replace:

```ts
'/images/logo-orange.png'
```

with:

```ts
'/vendor/x-change/images/logo-orange.png'
```

---

# Phase 5 — Introduce Package Route Composable

## Goal

Remove hardcoded URLs.

---

## Step 12. Create route composable

Create:

```text
packages/x-change/resources/js/composables/useXChangeRoutes.ts
```

---

## Step 13. Add canonical routes

Example:

```ts
export function useXChangeRoutes() {
    return {
        dashboard: '/x/dashboard',

        payCodes: {
            index: '/x/pay-codes',
            create: '/x/pay-codes/create',
            show: (code: string) => `/x/pay-codes/${code}`,
        },

        balances: '/x/balances',

        api: {
            dashboardStats: '/api/x/v1/dashboard/stats',
            dashboardActivity: '/api/x/v1/dashboard/activity',

            payCodes: '/api/x/v1/pay-codes',

            estimatePayCode:
                '/api/x/v1/pay-codes/estimate',
        },
    };
}
```

---

## Step 14. Refactor package pages/components

Replace ALL hardcoded:

```ts
'/x/dashboard'
```

with:

```ts
routes.dashboard
```

Pattern:

```ts
const routes = useXChangeRoutes();
```

---

# Phase 6 — Create Installable UI Platform

## Goal

Make installation automatic.

---

## Step 15. Create install command

Create:

```text
src/Console/InstallXChangeCommand.php
```

---

## Step 16. Register command

Inside provider:

```php
$this->commands([
    InstallXChangeCommand::class,
]);
```

---

## Step 17. Install command responsibilities

Initial implementation:

```bash
php artisan vendor:publish --tag=x-change-ui --force
php artisan vendor:publish --tag=x-change-brand-assets --force
php artisan migrate
```

Later:
- env setup
- demo data
- frontend install notes

---

# Phase 7 — White-Label Branding

## Goal

Allow future licensee customization.

---

## Step 18. Add branding config

Inside:

```php
config/x-change.php
```

add:

```php
'branding' => [
    'name' => env('XCHANGE_BRAND_NAME', 'X-Change'),

    'logo_light' => env(
        'XCHANGE_LOGO_LIGHT',
        '/vendor/x-change/images/logo-orange.png'
    ),

    'logo_dark' => env(
        'XCHANGE_LOGO_DARK',
        '/vendor/x-change/images/logo-silver.png'
    ),
],
```

---

## Step 19. Share branding via Inertia

Example:

```php
Inertia::share('xchange.branding', [
    'name' => config('x-change.branding.name'),
    'logo_light' => config('x-change.branding.logo_light'),
    'logo_dark' => config('x-change.branding.logo_dark'),
]);
```

---

## Step 20. Consume branding in package layout

Inside:
- AppLogo.vue
- AppLogoIcon.vue
- XChangeLayout.vue

consume:

```ts
usePage().props.xchange.branding
```

instead of hardcoded asset paths.

---

# Phase 8 — Dashboard Evolution

## Goal

Keep dashboard backend-driven.

---

## Step 21. Create stats provider contract

Create:

```text
DashboardStatsProviderContract.php
```

---

## Step 22. Create default provider

Create:

```text
DefaultDashboardStatsProvider.php
```

This provider computes:
- total Pay Codes
- total claimed
- total pending
- total disbursed
- recent activity

Controllers call provider.

Vue consumes API.

---

# Phase 9 — Future-Proof the Package UI

## Goal

Prevent monolithic frontend logic.

---

## Step 23. Keep business logic out of Vue

Vue/composables may:
- fetch data
- submit forms
- render state
- manage UX

Vue/composables must NOT:
- compute pricing
- evaluate voucher semantics
- run lifecycle orchestration
- validate redemption contracts
- determine settlement logic

All business logic stays backend-side.

---

## Step 24. Treat package UI as reference implementation

The package UI should demonstrate:
- dashboard
- Pay Code issuance
- balances
- claim flows
- statistics

while remaining:
- replaceable
- publishable
- extensible

Licensees may later edit published files.

But the package remains the canonical source of truth.

---

# Final Expected Outcome

After:

```bash
composer require 3neti/x-change
php artisan x-change:install
npm install
npm run build
```

the host app should require almost no manual frontend work.

The package should immediately provide:

```text
/x/dashboard
/x/pay-codes
/x/pay-codes/create
/x/balances
```

with:
- package-owned Vue source
- package-owned layouts
- package-owned composables
- publishable UI
- namespaced assets
- backend-driven APIs
- stable upgrade path
- white-label readiness
- minimal host-app coupling

---

# Final Architectural Principle

> The host app should consume x-change like a product.  
> The package should own the application surface.  
> Publishing should customize behavior, not assemble behavior.
