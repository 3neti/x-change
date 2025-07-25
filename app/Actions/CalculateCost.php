<?php

namespace App\Actions;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use App\Services\InstructionCostEvaluator;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;
use App\Data\CostBreakdownData;
use Brick\Money\Money;
use App\Models\User;

class CalculateCost
{
    use AsAction;

    public function __construct(
        protected InstructionCostEvaluator $evaluator
    ) {}

    public function handle(User $user, VoucherInstructionsData $instructions): CostBreakdownData
    {
        $breakdown = ['Cash' => $instructions->cash->amount];

        $charges = $this->evaluator->evaluate($user, $instructions);
        foreach ($charges as $charge) {
            $breakdown[$charge['item']->index] = Money::ofMinor($charge['price'], 'PHP')->getAmount()->toFloat();
//            $breakdown[$charge['item']->index] = $charge['price'] / 100;
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

        return $this->handle($request->user(), $instructions);
    }
}
