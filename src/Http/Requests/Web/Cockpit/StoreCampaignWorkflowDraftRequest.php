<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCampaignWorkflowDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Model;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'pay_code_template_id' => ['required', 'integer'],
            'workflow_id' => ['required', 'string', 'max:120'],
            'workflow_version' => ['required', 'string', 'max:40'],
            'plan_code' => ['nullable', 'string', 'max:120'],
            'plan_version' => ['nullable', 'string', 'max:40'],
            'entry_method' => ['required', 'string', 'in:public_endpoint,payment_qr'],
            'parameters' => ['prohibited'],
            'notifications' => ['prohibited'],
            'connection' => ['prohibited'],
            'instructions' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
