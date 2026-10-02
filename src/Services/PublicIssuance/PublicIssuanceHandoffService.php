<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use LBHurtado\XChange\Data\PublicIssuance\PublicIssuanceHandoffData;

final readonly class PublicIssuanceHandoffService
{
    public function __construct(private PublicIssuanceEstimateService $estimates) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function prepare(array $input): PublicIssuanceHandoffData
    {
        $estimate = $this->estimates->estimate($input);

        return new PublicIssuanceHandoffData(
            schema: 'x-change.public-issuance-handoff.v1',
            method: 'GET',
            url: route('x-change.public-auto-generate.show', [
                'amount' => number_format($estimate->principal_minor / 100, 2, '.', ''),
                'currency' => $estimate->currency,
            ]),
            estimate: $estimate->toArray(),
            creates_order: false,
            next_step: 'Open the URL so the person can review the instructions and authorize payment.',
        );
    }
}
