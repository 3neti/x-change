<?php

if (! function_exists('documents_path')) {
    function documents_path(?string $path = null): string
    {
        // Resolve the base path properly for the package
        $basePath = defined('TESTING_PACKAGE_PATH')
            ? TESTING_PACKAGE_PATH
            : base_path('resources/documents');

        return $basePath . ($path ? '/' . $path : '');
    }
}
