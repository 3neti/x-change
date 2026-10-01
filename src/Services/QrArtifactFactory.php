<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use InvalidArgumentException;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Data\QrArtifactData;
use LBHurtado\XChange\Enums\QrArtifactKind;

final readonly class QrArtifactFactory
{
    public function __construct(
        private ClaimUrlQrRendererContract $renderer,
    ) {}

    public function payCode(string $code, string $claimUrl): QrArtifactData
    {
        $identifier = $this->requiredIdentifier($code, 'Pay Code');

        return $this->make(
            kind: QrArtifactKind::PayCode,
            destination: $claimUrl,
            title: 'Scan to claim',
            description: "Scan to open Pay Code {$identifier} directly.",
            identifier: $identifier,
        );
    }

    public function claimEntry(?string $code = null): QrArtifactData
    {
        $identifier = $code === null ? null : $this->requiredIdentifier($code, 'Pay Code');

        return $this->make(
            kind: QrArtifactKind::ClaimEntry,
            destination: route('x-change.claim.start'),
            title: 'Enter Pay Code',
            description: $identifier === null
                ? 'Scan to open the Pay Code entry page.'
                : "Scan to open the entry page, then enter {$identifier}.",
            identifier: $identifier,
        );
    }

    public function campaignEndpoint(string $title, string $publicUrl): QrArtifactData
    {
        return $this->make(
            kind: QrArtifactKind::CampaignEndpoint,
            destination: $publicUrl,
            title: 'Start Pay Code journey',
            description: 'Scan to generate a fresh Pay Code from this campaign.',
            identifier: $this->requiredIdentifier($title, 'Campaign title'),
        );
    }

    private function make(
        QrArtifactKind $kind,
        string $destination,
        string $title,
        string $description,
        ?string $identifier,
    ): QrArtifactData {
        return new QrArtifactData(
            kind: $kind,
            destination: $destination,
            image_data_uri: $this->renderer->render($destination),
            title: $title,
            description: $description,
            identifier: $identifier,
        );
    }

    private function requiredIdentifier(string $value, string $label): string
    {
        $identifier = trim($value);

        if ($identifier === '') {
            throw new InvalidArgumentException("{$label} is required for the QR artifact.");
        }

        return $identifier;
    }
}
