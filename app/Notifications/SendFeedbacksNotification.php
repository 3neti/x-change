<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\ModelInput\Data\InputData;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Number;
use Illuminate\Bus\Queueable;

class SendFeedbacksNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected VoucherData $voucher;

    public function __construct(string $voucherCode)
    {
        $model = Voucher::where('code', $voucherCode)->firstOrFail();
        $this->voucher = VoucherData::fromModel($model);
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'engage_spark', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->voucher->cash->amount;
        $formattedAmount = $amount->formatTo(Number::defaultLocale());
        $bank_account = new BankAccount(...$this->voucher->cash->withdrawTransaction->payload['destination_account']);

        $mail_message = (new MailMessage)
            ->subject('Voucher Code Redeemed')
            ->greeting('Yo ' . ',')
            ->line("The cash code **{$this->voucher->code}** with the amount of **{$formattedAmount}** has been successfully redeemed.")
            ->line("It was claimed by **{$this->voucher->contact?->mobile}**.")
            ->line("This amount will be processed and credited to {$bank_account} shortly.")
            ->line('If you did not authorize this transaction, please contact support immediately.')
            ->salutation('Thank you for using our service!');

        $signature = $this->voucher->inputs
            ->first(fn(InputData $input) => $input->name === 'signature')
            ?->value;

        if ($signature && str_starts_with($signature, 'data:image/')) {
            // Extract the actual base64 data
            [, $encodedImage] = explode(',', $signature, 2);

            // Determine mime and file extension
            preg_match('/^data:image\/(\w+);base64/', $signature, $matches);
            $extension = $matches[1] ?? 'png'; // fallback to png
            $mime = "image/{$extension}";

            $mail_message->attachData(
                base64_decode($encodedImage),
                "signature.{$extension}",
                ['mime' => $mime]
            );
        }

        return $mail_message;
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $amount = $this->voucher->cash->amount;

        return (new EngageSparkMessage())
            ->content("The cash code {$this->voucher->code} with the amount of {$amount->getCurrency()->getCurrencyCode()} {$amount->getAmount()} was redeemed by {$this->voucher->contact?->mobile}.");
    }

    public function toArray(object $notifiable): array
    {
        $amount = $this->voucher->cash->amount;

        return [
            'code' => $this->voucher->code,
            'mobile' => $this->voucher->contact?->mobile,
            'amount' => $amount->getAmount()->toFloat(),
            'currency' => $amount->getCurrency()->getCurrencyCode(),
        ];
    }
}
