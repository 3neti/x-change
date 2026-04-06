<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Onboarding;

use LBHurtado\XChange\Contracts\IssuerOnboardingContract;

class OnboardIssuer
{
    public function __construct(
        protected IssuerOnboardingContract $issuers,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $issuer = $this->issuers->onboard($input);

        return [
            'issuer' => [
                'id' => is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id'),
                'name' => is_object($issuer) ? ($issuer->name ?? null) : data_get($issuer, 'name'),
                'email' => is_object($issuer) ? ($issuer->email ?? null) : data_get($issuer, 'email'),
                'mobile' => is_object($issuer) ? ($issuer->mobile ?? null) : data_get($issuer, 'mobile'),
                'country' => is_object($issuer) ? ($issuer->country ?? null) : data_get($issuer, 'country'),
            ],
        ];
    }
}
