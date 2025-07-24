<?php

namespace App\Services;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Brick\Money\Money;
use Brick\Money\Currency;

/** @deprecated  */
class InstructionPricingService
{
    public function computeTotal(VoucherInstructionsData $instructions): Money
    {
//        $total = 0;
//        $pricing = config('instruction_pricing');
//
//        // Rider
//        if ($instructions->rider->message) {
//            $total += $pricing['rider']['message'] ?? 0;
//        }
//        if ($instructions->rider->url) {
//            $total += $pricing['rider']['url'] ?? 0;
//        }
//
//        // Feedback
//        if ($instructions->feedback->email) {
//            $total += $pricing['feedback']['email'] ?? 0;
//        }
//        if ($instructions->feedback->mobile) {
//            $total += $pricing['feedback']['mobile'] ?? 0;
//        }
//        if ($instructions->feedback->webhook) {
//            $total += $pricing['feedback']['webhook'] ?? 0;
//        }
//
//        // Inputs
//        foreach ($instructions->inputs->toArray() as $field) {
//            $total += $pricing['inputs'][$field] ?? 0;
//        }
//
//        return Money::ofMinor($total, Currency::of(config('wallet.currency', 'PHP')));
    }
}
