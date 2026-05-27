<?php

use LBHurtado\XRider\Support\RiderHtmlSanitizer;

it('removes script tags from rider splash html', function () {
    $html = <<<HTML
<div>Hello</div>
<script>alert('xss')</script>
HTML;

    $clean = app(RiderHtmlSanitizer::class)
        ->sanitizeSplash($html);

    expect($clean)
        ->toContain('<div>Hello</div>')
        ->not->toContain('<script>');
});

it('removes javascript urls', function () {
    $html = <<<HTML
<a href="javascript:alert(1)">Click me</a>
HTML;

    $clean = app(RiderHtmlSanitizer::class)
        ->sanitizeSplash($html);

    expect($clean)
        ->not->toContain('javascript:');
});

it('removes inline event handlers', function () {
    $html = <<<HTML
<img src="https://example.com/cat.jpg" onerror="alert(1)">
HTML;

    $clean = app(RiderHtmlSanitizer::class)
        ->sanitizeSplash($html);

    expect($clean)
        ->not->toContain('onerror');
});

it('preserves safe formatting', function () {
    $html = <<<HTML
<div class="text-center">
    <strong>Hello</strong>
</div>
HTML;

    $clean = app(RiderHtmlSanitizer::class)
        ->sanitizeSplash($html);

    expect($clean)
        ->toContain('<strong>Hello</strong>');
});
