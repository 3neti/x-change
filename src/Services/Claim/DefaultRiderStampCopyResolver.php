<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use DOMDocument;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;
use LBHurtado\XChange\Data\Claim\RiderStampCopyData;
use LBHurtado\XChange\Services\Cockpit\RiderUrlArtworkPreviewResolver;

final class DefaultRiderStampCopyResolver implements RiderStampCopyResolverContract
{
    public function __construct(
        private readonly RiderUrlArtworkPreviewResolver $urlArtwork,
    ) {}

    public function resolve(Voucher $voucher): RiderStampCopyData
    {
        $rider = $voucher->instructions->rider;
        $source = $this->source($rider);
        [$title, $description] = $this->sourceCopy($source, $rider);
        $defaultTitle = (string) config(
            'x-change.claim.share.copy.default_title',
            'Pay Code',
        );
        $defaultDescription = (string) config(
            'x-change.claim.share.default_description',
            'A Pay Code is ready to claim securely in X-Change.',
        );

        return new RiderStampCopyData(
            source: $source,
            title: $this->safeText(
                $rider->stamp?->title,
                $title === '' ? $defaultTitle : $title,
                120,
            ),
            description: $this->safeText(
                $rider->stamp?->description,
                $description === '' ? $defaultDescription : $description,
                240,
            ),
            visible: $source !== 'none',
        );
    }

    private function source(RiderInstructionData $rider): string
    {
        $configured = $rider->stamp?->copy_source?->value;

        if ($configured === null) {
            $legacy = $rider->stamp?->source?->value ?? $rider->og_source;
            $configured = in_array(
                $legacy,
                ['message', 'url', 'splash'],
                true,
            ) ? $legacy : 'automatic';
        }

        if ($configured !== 'automatic') {
            return $configured;
        }

        if (filled($rider->message)) {
            return 'message';
        }

        if ($this->splashPlainText($rider->splash) !== '') {
            return 'splash';
        }

        return filled($rider->url) ? 'url' : 'custom';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sourceCopy(
        string $source,
        RiderInstructionData $rider,
    ): array {
        return match ($source) {
            'message' => [
                $this->safeText($rider->message, 'Pay Code', 120),
                filled($rider->message)
                    ? (string) config(
                        'x-change.claim.share.copy.message_description',
                        'Prepared with a message for the recipient.',
                    )
                    : '',
            ],
            'url' => $this->urlCopy($rider->url),
            'splash' => $this->splashCopy($rider->splash),
            default => ['', ''],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function urlCopy(?string $url): array
    {
        if (blank($url)) {
            return ['', ''];
        }

        $resolved = $this->urlArtwork->resolve((string) $url);

        if ($resolved['available']) {
            return [
                $this->safeText($resolved['title'], 'Pay Code', 120),
                $this->safeText(
                    $resolved['description'],
                    (string) config(
                        'x-change.claim.share.copy.url_description',
                        'Continue to this link after the claim.',
                    ),
                    240,
                ),
            ];
        }

        return [
            $this->safeText($url, 'Pay Code', 120),
            (string) config(
                'x-change.claim.share.copy.url_description',
                'Continue to this link after the claim.',
            ),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splashCopy(?string $splash): array
    {
        $document = $this->splashDocument($splash);

        if (! $document instanceof DOMDocument) {
            return ['', ''];
        }

        $title = $this->firstElementText($document, ['h1', 'h2', 'h3']);
        $description = collect(
            $this->elementTexts($document, ['p']),
        )->unique()->implode(' · ');
        $plainText = $this->splashPlainText($splash);

        return [
            $this->safeText(
                $title,
                $plainText === '' ? 'Pay Code Introduction' : $plainText,
                120,
            ),
            $this->safeText(
                $description,
                (string) config(
                    'x-change.claim.share.copy.splash_description',
                    'An introduction appears before the claim.',
                ),
                240,
            ),
        ];
    }

    /**
     * @param  list<string>  $tagNames
     */
    private function firstElementText(
        DOMDocument $document,
        array $tagNames,
    ): ?string {
        return $this->elementTexts($document, $tagNames)[0] ?? null;
    }

    /**
     * @param  list<string>  $tagNames
     * @return list<string>
     */
    private function elementTexts(
        DOMDocument $document,
        array $tagNames,
    ): array {
        $texts = [];

        foreach ($tagNames as $tagName) {
            foreach ($document->getElementsByTagName($tagName) as $element) {
                $text = trim((string) $element->textContent);

                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    private function splashPlainText(?string $splash): string
    {
        if (blank($splash)) {
            return '';
        }

        return Str::of(
            html_entity_decode(strip_tags((string) $splash), ENT_QUOTES | ENT_HTML5),
        )->squish()->toString();
    }

    private function splashDocument(?string $splash): ?DOMDocument
    {
        if (blank($splash)) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadHTML(
                '<?xml encoding="UTF-8">'.(string) $splash,
                LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR,
            )) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function safeText(
        mixed $value,
        string $fallback,
        int $limit,
    ): string {
        if (! is_string($value)) {
            return $fallback;
        }

        $text = Str::of(
            html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5),
        )->squish()->limit($limit)->toString();

        return $text === '' ? $fallback : $text;
    }
}
