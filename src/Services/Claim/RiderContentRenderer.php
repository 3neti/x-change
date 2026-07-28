<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Str;
use LBHurtado\XRider\Support\RiderHtmlSanitizer;

final class RiderContentRenderer
{
    public function __construct(
        private RiderHtmlSanitizer $sanitizer,
    ) {}

    public function message(?string $content, ?string $format): string
    {
        return $this->render($content, $format ?? 'plain');
    }

    public function splash(?string $content, ?string $format): string
    {
        return $this->render($content, $format ?? 'html');
    }

    private function render(?string $content, string $format): string
    {
        $value = trim((string) $content);

        if ($value === '') {
            return '';
        }

        $html = match ($format) {
            'plain' => nl2br(e($value)),
            'markdown' => (string) Str::markdown($value, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            default => $value,
        };

        return $this->sanitizer->sanitizeSplash($html);
    }
}
