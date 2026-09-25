<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

final class PublishCampaignWorkflowDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Model;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['expected_snapshot_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']];
    }
}
