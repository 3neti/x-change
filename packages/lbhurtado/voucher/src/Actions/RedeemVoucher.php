<?php

namespace LBHurtado\Voucher\Actions;

use FrittenKeeZ\Vouchers\Exceptions\VoucherAlreadyRedeemedException;
use FrittenKeeZ\Vouchers\Exceptions\VoucherNotFoundException;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Contact\Models\Contact;

class RedeemVoucher
{
    use AsAction;

    /**
     * Attempt to redeem a voucher for a given contact.
     *
     * @param Contact $contact     The notifiable contact who redeems the voucher.
     * @param string  $voucher_code  The code of the voucher to redeem.
     * @param array   $meta         Optional metadata about the redemption (e.g. ['location'=>'POS1']).
     * @return bool True if redemption succeeded, false otherwise.
     */
    public function handle(Contact $contact, string $voucher_code, array $meta = []): bool
    {
        try {
            // Facade call into the voucher library.
            // Returns true on success, or throws if not found/already redeemed.
            return Vouchers::redeem($voucher_code, $contact, ['redemption' => $meta]);
        } catch (VoucherNotFoundException $e) {
            // swallow and return false if voucher code doesn't exist
            return false;
        } catch (VoucherAlreadyRedeemedException $e) {
            // swallow and return false if voucher was already redeemed
            return false;
        }
    }
}
