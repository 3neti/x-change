<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StageCampaignWorksheetIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['csv', 'txt', 'xlsx'])->max(5 * 1024),
            ],
        ];
    }
}
