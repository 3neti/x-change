<?php

declare(strict_types=1);

it('keeps cockpit pages inside the single package-owned host shell', function (): void {
    $root = dirname(__DIR__, 3);
    $layout = file_get_contents($root.'/resources/js/cockpit/layouts/CockpitLayout.vue');
    $sidebar = file_get_contents($root.'/stubs/resources/js/components/AppSidebar.vue.stub');
    $documentationPage = file_get_contents($root.'/resources/js/pages/x-change/cockpit/Documentation.vue');
    $documentationWorkspace = file_get_contents($root.'/resources/js/cockpit/pages/Documentation.vue');

    expect($layout)->not->toContain('CockpitSidebar')
        ->and($sidebar)->toContain(
            'X-CHANGE HOST SHELL',
            "title: 'Cockpit'",
            "title: 'Documentation'",
            '<NavUser />',
        )
        ->not->toContain('Repository')
        ->and($documentationPage)->toContain(
            "import Documentation from '../../../cockpit/pages/Documentation.vue';",
            '<Documentation v-bind="$attrs" />',
        )
        ->and($documentationWorkspace)->toContain('defineOptions({ inheritAttrs: false });');
});
