<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Actions\Lifecycle\RunQrPhFundingSimulation;

final class RunCockpitQrPhFundingSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && (bool) config('x-change.cockpit.qrph_funding_simulation.enabled', false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'scenario' => [
                'sometimes',
                'string',
                Rule::in([
                    RunQrPhFundingSimulation::SCENARIO_KEY,
                    RunQrPhFundingSimulation::ON_DEMAND_SCENARIO_KEY,
                ]),
            ],
        ];
    }
}
