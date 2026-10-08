<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Enums\CampaignPaymentMonitoringMode;

final class UpdateCampaignPaymentMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(CampaignPaymentMonitoringMode::class)],
            'expected_generation' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:120'],
        ];
    }
}
