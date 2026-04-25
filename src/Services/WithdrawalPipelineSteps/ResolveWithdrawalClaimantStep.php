<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use InvalidArgumentException;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;
use Propaganistas\LaravelPhone\PhoneNumber;

// TODO: Revisit mobile canonicalization.
// We currently preserve Contact::fromPhoneNumber() behavior, which stores PH mobiles
// in national format (e.g. 09171234567). This may be intentional because some EMI
// account identifiers such as GCash use national-format mobile numbers as account
// numbers. Before standardizing to E.164 (639...), study impact across:
// - Contact identity matching
// - GCash / Maya / EMI account resolution
// - bank payout payloads
// - voucher validation mobile locks
// - reconciliation/audit metadata
class ResolveWithdrawalClaimantStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::PRE_AUTH;
    }

    public static function description(): string
    {
        return 'Resolve and normalize the withdrawal claimant (contact) from the incoming request payload.';
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        $mobile = data_get($context->payload, 'mobile');
        $country = (string) data_get($context->payload, 'recipient_country', 'PH');

        if (! is_string($mobile) || trim($mobile) === '') {
            throw new InvalidArgumentException('Mobile number is required.');
        }

        $phoneNumber = new PhoneNumber($mobile, $country);

        $context->withContact(Contact::fromPhoneNumber($phoneNumber));

        return $next($context);
    }
}
