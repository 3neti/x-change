<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Models\PayCodeTemplate;

class UpdatePayCodeTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $owner = $this->user();
        $template = $this->route('template');

        return $owner instanceof Model
            && $template instanceof PayCodeTemplate
            && $template->status === 'active'
            && $template->owner_type === $owner->getMorphClass()
            && (string) $template->owner_id === (string) $owner->getKey();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:240'],
            'base_template_key' => ['required', 'string', 'max:64'],
            'instructions' => ['required', 'array'],
            'include_amount' => ['required', 'boolean'],
            'include_purpose' => ['required', 'boolean'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }
}
