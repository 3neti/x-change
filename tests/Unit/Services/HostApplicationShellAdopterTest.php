<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Services\Host\HostApplicationShellAdopter;

function pristineLaravelSidebarSource(): string
{
    return <<<'VUE'
<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [];
const repository = 'https://github.com/laravel/vue-starter-kit';
const documentation = 'https://laravel.com/docs/starter-kits#vue';
</script>
<template><NavUser /></template>
VUE;
}

it('adopts a pristine Laravel sidebar as the package-owned Cockpit shell', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'x-change-sidebar-');
    file_put_contents($path, pristineLaravelSidebarSource());

    $result = app(HostApplicationShellAdopter::class)->adoptPath($path);
    $adopted = file_get_contents($path);

    expect($result['status'])->toBe('adopted')
        ->and($result['changed'])->toBeTrue()
        ->and($adopted)->toContain('X-CHANGE HOST SHELL')
        ->toContain("title: 'Cockpit'")
        ->toContain("title: 'Documentation'")
        ->not->toContain('Repository');

    @unlink($path);
    if (is_string($result['backup_path'])) {
        @unlink($result['backup_path']);
    }
});

it('refreshes its own shell and refuses an unknown customized sidebar', function (): void {
    $files = new Filesystem;
    $adopter = new HostApplicationShellAdopter($files);
    $path = tempnam(sys_get_temp_dir(), 'x-change-sidebar-');
    file_put_contents($path, "<!-- X-CHANGE HOST SHELL -->\n<template />\n");

    expect($adopter->adoptPath($path)['status'])->toBe('adopted')
        ->and($adopter->adoptPath($path)['status'])->toBe('already_adopted');

    file_put_contents($path, '<template><CorporateNavigation /></template>');

    expect(fn () => $adopter->adoptPath($path))
        ->toThrow(RuntimeException::class, 'customized beyond the safe automatic adoption pattern');

    @unlink($path);
});

it('supports a no-write shell adoption preview', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'x-change-sidebar-');
    $source = pristineLaravelSidebarSource();
    file_put_contents($path, $source);

    $result = app(HostApplicationShellAdopter::class)->adoptPath($path, commit: false);

    expect($result['status'])->toBe('would_adopt')
        ->and($result['changed'])->toBeFalse()
        ->and(file_get_contents($path))->toBe($source);

    @unlink($path);
});
