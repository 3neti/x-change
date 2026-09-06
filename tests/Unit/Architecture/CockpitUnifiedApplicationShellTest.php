<?php

declare(strict_types=1);

it('keeps cockpit pages inside the single package-owned host shell', function (): void {
    $root = dirname(__DIR__, 3);
    $layout = file_get_contents($root.'/resources/js/cockpit/layouts/CockpitLayout.vue');
    $sidebar = file_get_contents($root.'/stubs/resources/js/components/AppSidebar.vue.stub');
    $appBootstrap = file_get_contents($root.'/stubs/resources/js/app.ts.stub');
    $serviceProvider = file_get_contents($root.'/src/Providers/XChangeServiceProvider.php');
    $documentationPage = file_get_contents($root.'/resources/js/pages/x-change/cockpit/Documentation.vue');
    $documentationWorkspace = file_get_contents($root.'/resources/js/cockpit/pages/Documentation.vue');

    expect($layout)->not->toContain('CockpitSidebar')
        ->and($sidebar)->toContain(
            'X-CHANGE HOST SHELL',
            "title: 'Funding'",
            "title: 'Issuance'",
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
            'cockpitWorkspaceGuides',
            "type XChangeNavigationItem = Omit<NavItem, 'icon'>",
            "icon: NonNullable<NavItem['icon']>;",
            "{ label: 'System', items: systemItems.value }",
            'system_readiness_visible',
            '<NavUser />',
        )
        ->not->toContain('Repository')
        ->and($appBootstrap)->toContain(
            "case name.startsWith('x-change/claim/'):",
            "case name.startsWith('form-flow/'):",
            'return null;',
        )
        ->and($appBootstrap)->toContain(
            "case name.startsWith('auth/'):",
            "case name.startsWith('settings/'):",
            'return AppLayout;',
        )
        ->and($serviceProvider)->toContain(
            "stubs/resources/js/app.ts.stub') => resource_path('js/app.ts')",
        )
        ->and($documentationPage)->toContain(
            "import Documentation from '../../../cockpit/pages/Documentation.vue';",
            '<Documentation v-bind="$attrs" />',
        )
        ->and($documentationWorkspace)->toContain('defineOptions({ inheritAttrs: false });');

    expect(strpos($sidebar, "title: 'Funding'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Issuance'"))
        ->and(strpos($sidebar, "title: 'Issuance'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Campaigns'"))
        ->and(strpos($sidebar, "title: 'Campaigns'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Pay Codes'"))
        ->and(strpos($sidebar, "title: 'Pay Codes'"))
        ->toBeLessThan(strpos($sidebar, "title: 'Overview'"));
});
