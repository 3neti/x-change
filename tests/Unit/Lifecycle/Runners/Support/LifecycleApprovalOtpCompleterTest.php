<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Lifecycle\Output\ConsoleLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleApprovalOtpCompleter;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayload;

function lifecycleApprovalOtpCompleterContext(Command $command): ScenarioRunContext
{
    return new ScenarioRunContext(
        output: new ConsoleLifecycleOutput($command),
        scenarioKey: 'test',
        scenario: [],
        issuer: new class extends Model {},
        generated: new class {
            public function toArray(): array
            {
                return [];
            }
        },
        voucher: new Voucher,
        attempts: [],
        baseClaimMobile: '639171234567',
        estimate: [],
        idempotencyKey: 'test-idempotency',
        readiness: Mockery::mock(SettlementEnvelopeReadinessContract::class),
    );
}

it('submits approval OTP and replays the claim in interactive lifecycle mode', function () {
    $voucher = new Voucher;
    $voucher->code = 'TEST-OTP';

    $command = new class extends Command
    {
        public array $questions = [];
        public array $lines = [];
        public array $warnings = [];

        public function __construct()
        {
            parent::__construct('lifecycle:test');
        }

        public function option($key = null): mixed
        {
            return false;
        }

        public function ask($question, $default = null): mixed
        {
            $this->questions[] = $question;

            return '441498';
        }

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->lines[] = $string;
        }

        public function info($string, $verbosity = null): void
        {
            $this->lines[] = $string;
        }

        public function warn($string, $verbosity = null): void
        {
            $this->warnings[] = $string;
        }

        public function error($string, $verbosity = null): void {}
    };

    $approval = new ClaimApprovalInitiationResultData(
        voucher_code: 'TEST-OTP',
        status: 'approval_required',
        requirements: ['otp'],
        actions: ['otp'],
        meta: [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-Z3EL-09173011987-S1',
        ],
        messages: [
            'Payout OTP approval required.',
        ],
    );

    app()->bind(
        ClaimApprovalOtpAuthorizer::class,
        fn () => new class implements ClaimApprovalOtpAuthorizer
        {
            public array $seen = [];

            public function authorize(Voucher $voucher, array $payload): array
            {
                $this->seen = [
                    'voucher_code' => (string) $voucher->code,
                    'payload' => $payload,
                ];

                return [
                    'status' => 'completed',
                    'voucher_code' => (string) $voucher->code,
                    'reference_id' => $payload['reference_id'],
                    'provider' => $payload['provider'],
                    'messages' => ['Approval OTP verified.'],
                    'approval_metadata' => [
                        'provider' => $payload['provider'],
                        'authorization_type' => 'otp',
                        'reference_id' => $payload['reference_id'],
                        'otp_required' => false,
                    ],
                ];
            }
        }
    );

    $submitWebClaim = Mockery::mock(SubmitWebPayCodeClaim::class);
    $submitWebClaim
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $payload) use ($voucher): bool {
            return $givenVoucher === $voucher
                && $payload['mobile'] === '639171234567'
                && data_get($payload, 'bank_account.bank_code') === 'GXI'
                && data_get($payload, 'bank_account.account_number') === '09173011987'
                && data_get($payload, 'approval.resume') === true
                && data_get($payload, 'approval.provider') === 'paynamics'
                && data_get($payload, 'approval.reference_id') === 'TEST-Z3EL-09173011987-S1'
                && data_get($payload, 'otp.verified') === true
                && data_get($payload, 'otp.code') === '441498';
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: 'TEST-OTP',
            claim_type: 'withdraw',
            claimed: true,
            status: 'withdrawn',
            requested_amount: 75.00,
            disbursed_amount: 75.00,
            currency: 'PHP',
            remaining_balance: 0,
            fully_claimed: true,
            disbursement: [
                'status' => 'requested',
            ],
            messages: [
                'Voucher withdrawal successful.',
            ],
        ));

    $result = (new LifecycleApprovalOtpCompleter(
        submitApprovalOtp: app(SubmitClaimApprovalOtp::class),
        resumePayload: app(ClaimApprovalResumePayload::class),
        submitWebPayCodeClaim: $submitWebClaim,
    ))->complete(
        context: lifecycleApprovalOtpCompleterContext($command),
        voucher: $voucher,
        approval: $approval,
        baseClaimPayload: [
            'mobile' => '639171234567',
            'recipient_country' => 'PH',
            'bank_account' => [
                'bank_code' => 'GXI',
                'account_number' => '09173011987',
            ],
            'amount' => 75.00,
            'inputs' => [],
        ],
    );

    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class)
        ->and($result->status)->toBe('withdrawn')
        ->and($command->questions)->toBe(['Enter approval OTP'])
        ->and($command->warnings)->toContain('Approval required.')
        ->and($command->lines)->toContain('Provider: paynamics')
        ->and($command->lines)->toContain('Reference: TEST-Z3EL-09173011987-S1');
});
