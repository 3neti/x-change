<?php

namespace LBHurtado\XChange\Http\Requests\Concerns;

use LBHurtado\XRider\Support\RiderHtmlSanitizer;

trait SanitizesRiderSplashHtml
{
    protected function sanitizeRiderSplashHtmlForValidation(): void
    {
        $rider = $this->input('rider', []);

        if (! is_array($rider)) {
            return;
        }

        $splash = $rider['splash'] ?? null;

        if (! is_string($splash) || trim($splash) === '') {
            return;
        }

        $format = $rider['splash_format'] ?? null;

        if (in_array($format, ['plain', 'markdown'], true)) {
            return;
        }

        $rider['splash'] = app(RiderHtmlSanitizer::class)
            ->sanitizeSplash($splash);

        $rider['splash_meta'] = array_merge(
            is_array($rider['splash_meta'] ?? null) ? $rider['splash_meta'] : [],
            [
                'sanitized' => true,
                'html_profile' => 'rider_splash',
            ],
        );

        $this->merge([
            'rider' => $rider,
        ]);
    }
}
