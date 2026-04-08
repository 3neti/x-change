<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\DB;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionProcessorContract;
use Propaganistas\LaravelPhone\PhoneNumber;
use RuntimeException;

class DefaultRedemptionProcessorService implements RedemptionProcessorContract
{
    public function process(Voucher $voucher, RedemptionContext $context): bool
    {
        if (! $voucher->processed) {
            throw new RuntimeException(
                'This voucher is still being prepared. Please wait a moment and try again.'
            );
        }

        return DB::transaction(function () use ($voucher, $context): bool {
            if (method_exists($voucher, 'trackRedemptionSubmit')) {
                $voucher->trackRedemptionSubmit();
            }

            $phoneNumber = new PhoneNumber($context->mobile, 'PH');
            $contact = Contact::fromPhoneNumber($phoneNumber);

            $this->validateKycIfRequired($voucher, $contact);

            $meta = $this->prepareMetadata($context);

            $redeemed = RedeemVoucher::run($contact, $voucher->code, $meta);

            if (! $redeemed) {
                throw new RuntimeException('Failed to redeem voucher.');
            }

            return true;
        });
    }

    protected function validateKycIfRequired(Voucher $voucher, Contact $contact): void
    {
        $fields = array_map(
            static function ($field): string {
                if ($field instanceof \BackedEnum) {
                    return (string) $field->value;
                }

                if ($field instanceof \UnitEnum) {
                    return $field->name;
                }

                return (string) $field;
            },
            (array) data_get($voucher->instructions, 'inputs.fields', [])
        );

        $kycRequired = in_array('kyc', $fields, true);

        if (! $kycRequired) {
            return;
        }

        if (! method_exists($contact, 'isKycApproved') || ! $contact->isKycApproved()) {
            throw new RuntimeException(
                'Identity verification required. Please complete KYC before redeeming.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareMetadata(RedemptionContext $context): array
    {
        $meta = [];

        if ($context->inputs !== []) {
            $meta['inputs'] = $context->inputs;
        }

        if (
            ! empty($context->bankAccount['bank_code'])
            && ! empty($context->bankAccount['account_number'])
        ) {
            $meta['bank_account'] = sprintf(
                '%s:%s',
                $context->bankAccount['bank_code'],
                $context->bankAccount['account_number']
            );
        }

        return $meta;
    }
}
