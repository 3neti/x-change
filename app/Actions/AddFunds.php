<?php

namespace App\Actions;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Notifications\FundsRequestNotification;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Brick\Money\Money;
use App\Models\User;

class AddFunds
{
    use AsAction;

    public function __construct(
        protected PaymentGatewayInterface $gateway,
    ) {}

    public function handle(User $user, Money $amount): string
    {
        $qrBase64 = $this->gateway->generate($user->mobile, $amount);

        // Save to disk and get a public URL
        $url = $this->processQRBase64AndGetUrl($qrBase64);

        // Notify user, passing both QR data (for inline) and URL (for email attachments)
        $user->notify(new FundsRequestNotification($url, $amount));

        // Return the URL for rendering
        return $url;
    }

    /**
     * Decode a data:image/png;base64,... string, store it on the 'public' disk,
     * and return the publicly accessible URL.
     */
    private function processQRBase64AndGetUrl(string $qrBase64): string
    {
        // 1. Remove the data URI prefix if present
        if (Str::startsWith($qrBase64, 'data:image')) {
            [$meta, $data] = explode(',', $qrBase64, 2);
        } else {
            $data = $qrBase64;
        }

        // 2. Decode the base64 into binary
        $binary = base64_decode($data);

        // 3. Build a unique filename
        $filename = 'qr_codes/' . Str::uuid() . '.png';

        // 4. Store on the public disk (storage/app/public/qr_codes/...)
        Storage::disk('public')->put($filename, $binary);

        // 5. Return the publicly accessible URL (/storage/qr_codes/...)
        return Storage::disk('public')->url($filename);
    }
}
