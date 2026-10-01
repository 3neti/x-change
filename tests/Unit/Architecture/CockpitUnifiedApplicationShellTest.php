<?php

declare(strict_types=1);

it('keeps cockpit pages inside the single package-owned host shell', function (): void {
    $root = dirname(__DIR__, 3);
    $layout = file_get_contents($root.'/resources/js/cockpit/layouts/CockpitLayout.vue');
    $sidebar = file_get_contents($root.'/stubs/resources/js/components/AppSidebar.vue.stub');
    $appBootstrap = file_get_contents($root.'/stubs/resources/js/app.ts.stub');
    $cockpitShell = file_get_contents($root.'/stubs/resources/js/layouts/app/AppSidebarLayoutCockpit.vue.stub');
    $serviceProvider = file_get_contents($root.'/src/Providers/XChangeServiceProvider.php');
    $documentationPage = file_get_contents($root.'/resources/js/pages/x-change/cockpit/Documentation.vue');
    $documentationWorkspace = file_get_contents($root.'/resources/js/cockpit/pages/Documentation.vue');

    expect($layout)->not->toContain('CockpitSidebar')
        ->and($sidebar)->toContain(
            'X-CHANGE HOST SHELL',
            "title: 'Funding'",
            "title: 'Issuance'",
            "title: 'Claim'",
            "title: 'Campaigns'",
            "title: 'Pay Codes'",
            "title: 'Overview'",
            "title: 'Guides'",
            "title: 'System Readiness'",
            "description: 'Funds, capacity, and activity'",
            "description: 'Deployment and runtime checks'",
            "step: '1'",
            "step: '2'",
            "step: '3'",
            'branch: true',
            'dividerBefore: true',
            'CockpitWorkspaceNavigationItem',
            'openCockpitClaimEntryLauncher',
            'cockpit-desktop-claim-launcher',
            'cockpitWorkspaceGuides',
            'computed<XChangeNavigationGroup[]>',
            'workspaceItemsForGroup(group)',
            'navigationItemsForGroup(group)',
            "type XChangeNavigationItem = Omit<NavItem, 'icon'>",
            "icon: NonNullable<NavItem['icon']>;",
            "{ label: 'System', items: systemItems.value }",
            'system_readiness_visible',
            '<NavUser />',
        )
        ->not->toContain('Repository')
        ->and($appBootstrap)->toContain(
            "import AppSidebarLayoutCockpit from '@/layouts/app/AppSidebarLayoutCockpit.vue';",
            "case name.startsWith('x-change/claim/'):",
            "case name.startsWith('x-change/public/'):",
            "case name.startsWith('form-flow/'):",
            'return null;',
        )
        ->and($appBootstrap)->not->toContain(
            '@/lib/flashToast',
            'initializeFlashToast',
        )
        ->and($appBootstrap)->toContain(
            "case name.startsWith('auth/'):",
            "case name.startsWith('settings/'):",
            "case name.startsWith('x-change/cockpit/'):",
            'return AppSidebarLayoutCockpit;',
            'return AppLayout;',
        )
        ->and($cockpitShell)->toContain(
            '<div class="hidden md:block">',
            "const mobileBreakpoint = '(max-width: 767px)';",
            'window.scrollY <= 0',
            'distance >= revealDistance',
            'data-testid="cockpit-mobile-revealed-header"',
            'fixed inset-x-0 top-0',
            'md:hidden',
            'window.removeEventListener',
        )
        ->and($serviceProvider)->toContain(
            "stubs/resources/js/app.ts.stub') => resource_path('js/app.ts')",
            "stubs/resources/js/layouts/app/AppSidebarLayoutCockpit.vue.stub') => resource_path('js/layouts/app/AppSidebarLayoutCockpit.vue')",
        )
        ->and($documentationPage)->toContain(
            "import Documentation from '../../../cockpit/pages/Documentation.vue';",
            '<Documentation v-bind="$attrs" />',
        )
        ->and($documentationWorkspace)->toContain('defineOptions({ inheritAttrs: false });');

    expect(strpos($sidebar, "title: 'Funding'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Issuance'"))
        ->and(strpos($sidebar, "title: 'Issuance'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Claim'"))
        ->and(strpos($sidebar, "title: 'Claim'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Campaigns'"))
        ->and(strpos($sidebar, "title: 'Campaigns'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Pay Codes'"))
        ->and(strpos($sidebar, "title: 'Pay Codes'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Overview'"));
});
