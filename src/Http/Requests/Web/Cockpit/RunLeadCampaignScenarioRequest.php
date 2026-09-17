<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Services\Leads\LeadCampaignBrowserScenarioCatalog;

final class RunLeadCampaignScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(LeadCampaignBrowserScenarioCatalog $scenarios): array
    {
        return [
            'scenario' => [
                'required',
                'string',
                Rule::in(array_column($scenarios->all(), 'key')),
            ],
        ];
    }
}
