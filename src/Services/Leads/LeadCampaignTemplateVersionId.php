<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Leads;

use JsonException;
use LBHurtado\XChange\Models\PayCodeTemplate;

final class LeadCampaignTemplateVersionId
{
    public function forTemplate(PayCodeTemplate $template): string
    {
        try {
            $fingerprint = json_encode([
                'template_reference' => $template->reference,
                'base_template_key' => $template->base_template_key,
                'instructions' => $template->instructions_ciphertext,
                'include_amount' => $template->include_amount,
                'include_purpose' => $template->include_purpose,
                'updated_at' => $template->updated_at?->toIso8601String(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $fingerprint = (string) $template->reference.':'.$template->updated_at?->toIso8601String();
        }

        return 'pctv_'.substr(hash('sha256', $fingerprint), 0, 40);
    }
}
