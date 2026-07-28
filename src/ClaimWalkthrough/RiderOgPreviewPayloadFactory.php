<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

/**
 * @deprecated Use RiderStampPreviewPayloadFactory.
 */
final class RiderOgPreviewPayloadFactory
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function make(array $fixture): array
    {
        $payload = (new RiderStampPreviewPayloadFactory)->make($fixture);

        if ($payload['source'] === 'automatic') {
            $payload['source'] = 'default';
        }

        return $payload;
    }
}
