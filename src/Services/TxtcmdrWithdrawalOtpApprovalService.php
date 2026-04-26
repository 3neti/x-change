<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;

class TxtcmdrWithdrawalOtpApprovalService implements WithdrawalOtpApprovalServiceContract
{
    public function __construct(
        protected HttpFactory $http,
    ) {}

    public function request(string $mobile, string $reference, array $context = []): array
    {
        $response = $this->client()
            ->post('/api/otp/request', [
                'mobile' => $mobile,
                'reference' => $reference,
                'sender_id' => config('x-change.withdrawal.otp.txtcmdr.sender_id'),
                'label' => config('x-change.withdrawal.otp.label', config('app.name')),
                'context' => $context,
            ])
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function verify(string $mobile, string $reference, string $code, array $context = []): bool
    {
        $response = $this->client()
            ->post('/api/otp/verify', [
                'mobile' => $mobile,
                'reference' => $reference,
                'code' => $code,
                'context' => $context,
            ])
            ->throw()
            ->json();

        return (bool) Arr::get($response, 'verified', false);
    }

    protected function client()
    {
        return $this->http
            ->timeout((int) config('x-change.withdrawal.otp.txtcmdr.timeout', 30))
            ->withToken((string) config('x-change.withdrawal.otp.txtcmdr.api_token'))
            ->withOptions([
                'verify' => (bool) config('x-change.withdrawal.otp.txtcmdr.verify_ssl', true),
            ])
            ->baseUrl((string) config('x-change.withdrawal.otp.txtcmdr.base_url'));
    }
}
