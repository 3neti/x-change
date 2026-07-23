<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;

final class RunCockpitAccountScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && (bool) config('x-change.cockpit.account_scenario.enabled', false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
