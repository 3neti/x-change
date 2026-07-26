<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

final class RiderOgPreviewPayloadFactory
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function make(array $fixture): array
    {
        $rider = data_get($fixture, 'rider', []);
        $source = $this->source(data_get($rider, 'og_source'));
        $message = trim((string) data_get($rider, 'message', ''));
        $url = trim((string) data_get($rider, 'url', ''));
        $splash = trim((string) data_get($rider, 'splash', ''));
        $amount = (string) data_get($fixture, 'amount', '15.00');

        $preview = match ($source) {
            'message' => [
                'label' => 'Message preview',
                'title' => $message === '' ? 'No message yet' : $message,
                'description' => 'Beneficiary preview is based on the rider message/purpose.',
                'reference' => 'rider.message',
                'render_mode' => 'summary',
            ],
            'url' => [
                'label' => 'CTA URL preview',
                'title' => $url === '' ? 'No CTA URL yet' : $url,
                'description' => 'Beneficiary preview is based on the selected CTA destination.',
                'reference' => 'rider.url',
                'render_mode' => 'summary',
            ],
            'splash' => [
                'label' => 'Splash preview',
                'title' => $this->splashTitle($splash),
                'description' => $splash === '' ? 'Splash body is empty.' : $this->plainText($splash),
                'reference' => 'rider.splash',
                'render_mode' => $splash === '' ? 'summary' : 'html',
            ],
            default => [
                'label' => 'Default preview',
                'title' => $this->firstFilled(
                    $this->splashTitle($splash, allowFallback: false),
                    $message,
                    $url,
                    'Default beneficiary preview',
                ),
                'description' => $this->firstFilled(
                    $this->plainText($splash),
                    $message,
                    'Cockpit will submit only operator-safe rider fields.',
                ),
                'reference' => 'rider.og_source: default',
                'render_mode' => $splash === '' ? 'summary' : 'html',
            ],
        };

        return [
            'source' => $source,
            ...$preview,
            'html' => $preview['render_mode'] === 'html' ? $splash : null,
            'og_meta' => [
                'title' => (string) $preview['title'],
                'description' => (string) $preview['description'],
                'status' => 'active',
                'headline' => '{code}',
                'subtitle' => 'PHP '.number_format((float) $amount, 2),
                'tagline' => 'Tap to claim this Pay Code.',
                'url' => '{claim_url}',
                'imageUrl' => null,
                'cacheKey' => '{code}',
                'httpMaxAge' => 300,
                'message' => $message === '' ? null : $message,
                'splashHtml' => $splash === '' ? null : $splash,
                'typeBadge' => 'cash',
                'payeeBadge' => 'Pay Code',
            ],
        ];
    }

    private function source(mixed $source): string
    {
        return in_array($source, ['message', 'url', 'splash'], true)
            ? (string) $source
            : 'default';
    }

    private function splashTitle(string $splash, bool $allowFallback = true): string
    {
        $plain = $this->plainText($splash);

        if ($plain !== '') {
            return mb_substr($plain, 0, 90);
        }

        return $allowFallback ? 'No splash content yet' : '';
    }

    private function plainText(string $value): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function firstFilled(string ...$values): string
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }
}
