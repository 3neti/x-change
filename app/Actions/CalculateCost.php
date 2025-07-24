<?php

namespace App\Actions;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use App\Services\InstructionCostEvaluator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use App\Data\CostBreakdownData;

class CalculateCost
{
    use AsAction;

    public function __construct(
        protected InstructionCostEvaluator $evaluator
    ) {}

    public function handle(VoucherInstructionsData $instructions): CostBreakdownData
    {
        $breakdown = ['Cash' => $instructions->cash->amount];

        $charges = $this->evaluator->evaluate($instructions);
        foreach ($charges as $charge) {
            $breakdown[$charge['item']->index] = $charge['price'] / 100;
//            $breakdown[$charge['label']] = $charge['price'] / 100;
        }

        $total = array_sum($breakdown);

        return new CostBreakdownData(
            breakdown: $breakdown,
            total: $total
        );
    }

    public function asController(ActionRequest $request)
    {
        $instructions = VoucherInstructionsData::createFromAttribs($request->all());

        return $this->handle($instructions);
    }
}
