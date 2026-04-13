<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\PayCode;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Console\Concerns\InteractsWithPayloadFiles;
use LBHurtado\XChange\Console\Concerns\InteractsWithVoucherInstructionOptions;

class EstimatePayCodeCostCommand extends Command
{
    use InteractsWithJsonOutput;
    use InteractsWithPayloadFiles;
    use InteractsWithVoucherInstructionOptions;

    protected $signature = 'xchange:paycode:estimate
        {--issuer= : Issuer identifier}
        {--wallet= : Wallet identifier}
        {--amount= : Cash amount}
        {--quantity=1 : Number of pay codes to estimate}
        {--settlement-rail=INSTAPAY : Settlement rail}
        {--otp : Include OTP validation}
        {--kyc : Include KYC requirement}
        {--selfie : Include selfie requirement}
        {--signature : Include signature requirement}
        {--location : Include location requirement}
        {--sms : Include SMS feedback}
        {--email : Include email feedback}
        {--webhook= : Webhook URL}
        {--divisible : Mark voucher divisible}
        {--withdrawable : Mark voucher withdrawable}
        {--slice-mode= : fixed|open}
        {--expires-at= : Optional expiration datetime}
        {--idempotency-key= : Optional idempotency key}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON or output}
        {--config= : Path to JSON payload file}';

    protected $description = 'Estimate the cost of generating a Pay Code.';

    public function handle(EstimatePayCodeCost $action): int
    {
        $payload = $this->mergePayloads(
            $this->loadPayloadFromConfigOption(),
            $this->voucherInstructionPayloadFromOptions(),
        );

        $result = $action->handle($payload);

        $this->renderPayload($result->toArray(), 'Pay Code cost estimated successfully.');

        return self::SUCCESS;
    }
}
