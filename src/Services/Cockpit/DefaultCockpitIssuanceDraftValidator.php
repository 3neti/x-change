<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceTemplateRegistryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftValidationResultData;

class DefaultCockpitIssuanceDraftValidator implements CockpitIssuanceDraftValidatorContract
{
    public function __construct(
        private readonly CockpitIssuanceTemplateRegistryContract $templates,
    ) {}

    public function validate(CockpitIssuanceDraftData $draft): CockpitIssuanceDraftValidationResultData
    {
        $errors = [];
        $warnings = [];
        $template = filled($draft->template_key)
            ? $this->templates->resolve((string) $draft->template_key)
            : null;

        if (! filled($draft->template_key)) {
            $errors[] = 'template_key_required';
        } elseif ($template === null) {
            $errors[] = 'template_unknown';
        } elseif (! $template->enabled) {
            $errors[] = 'template_disabled';
        }

        if ((float) $draft->amount <= 0) {
            $errors[] = 'amount_required';
        }

        if (trim($draft->currency) === '') {
            $errors[] = 'currency_required';
        }

        if ($draft->count < 1) {
            $errors[] = 'count_must_be_positive';
        }

        if ($draft->hasCampaignContext() && ! filled($draft->campaign?->source)) {
            $warnings[] = 'campaign_source_missing';
        }

        return new CockpitIssuanceDraftValidationResultData(
            valid: $errors === [],
            status: $errors === [] ? 'valid' : 'invalid',
            errors: $errors,
            warnings: $warnings,
            metadata: [
                'template_key' => $draft->template_key,
                'template_resolved' => $template !== null,
                'template_enabled' => $template?->enabled,
                'campaign_context' => $draft->hasCampaignContext(),
            ],
        );
    }
}
