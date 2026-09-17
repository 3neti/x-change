<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Leads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Leads\StartLeadCampaign;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceBusy;
use LBHurtado\XChange\Models\CampaignDisplaySession;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Payment\PaymentAttemptPresenter;

final readonly class CampaignDisplaySessions
{
    public function __construct(
        private StartLeadCampaign $start,
        private ClaimUrlQrRendererContract $qr,
        private PaymentAttemptPresenter $attempts,
        private VoucherFlowCapabilityResolverContract $capabilities,
    ) {}

    public function authorize(CampaignDisplaySession $session, ?Model $owner): void
    {
        $campaign = $session->campaign;
        abort_unless($owner !== null && $campaign->owner_type === $owner->getMorphClass()
            && (string) $campaign->owner_id === (string) $owner->getKey(), 404);
    }

    public function create(LeadCampaign $campaign): CampaignDisplaySession
    {
        $this->start->ensureStartable($campaign);
        $token = Str::random(64);

        return CampaignDisplaySession::query()->create([
            'lead_campaign_id' => $campaign->getKey(),
            'entry_token' => $token,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function pair(LeadCampaign $campaign, string $token, Request $request): string
    {
        abort_unless(strlen($token) === 64, 404);
        $browserHash = $this->browserHash($request);

        return retry(3, fn (): string => DB::transaction(function () use ($campaign, $token, $browserHash): string {
            $lockedCampaign = LeadCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            $session = CampaignDisplaySession::query()->where('lead_campaign_id', $campaign->getKey())
                ->where('token_hash', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            abort_if($session->ended_at !== null || $session->expires_at->isPast(), 410, 'This display session has ended. Ask the seller for a new QR.');
            abort_if($session->browser_hash !== null && ! hash_equals($session->browser_hash, $browserHash), 409, 'This display is already serving another customer.');

            if ($session->voucher_id !== null) {
                return (string) $session->voucher->code;
            }

            $result = $this->start->handle($lockedCampaign);
            $voucher = Voucher::query()->where('code', $result->code)->firstOrFail();
            $session->update(['browser_hash' => $browserHash, 'voucher_id' => $voucher->getKey()]);

            return (string) $voucher->code;
        }), 100, fn (\Throwable $exception): bool => $exception instanceof PayCodeIssuanceBusy);
    }

    public function forPayer(Voucher $voucher, Request $request): ?CampaignDisplaySession
    {
        $session = CampaignDisplaySession::query()->where('voucher_id', $voucher->getKey())->first();
        if ($session !== null) {
            abort_unless(hash_equals((string) $session->browser_hash, $this->browserHash($request)), 403);
        }

        return $session;
    }

    public function assertOpen(CampaignDisplaySession $session): void
    {
        abort_if($session->ended_at !== null || $session->expires_at->isPast(), 410, 'The seller display has ended. Ask the seller to start a new session.');
    }

    public function intakeReady(CampaignDisplaySession $session, Voucher $voucher): bool
    {
        return ! $this->capabilities->resolve($voucher)->can_disburse
            || $session->intake_completed_at !== null;
    }

    /** Record validated application input only; payment authorization remains with the collection engine. */
    public function recordIntake(Voucher $voucher): void
    {
        CampaignDisplaySession::query()->where('voucher_id', $voucher->getKey())
            ->whereNull('intake_completed_at')->update(['intake_completed_at' => now()]);
    }

    public function attachAttempt(CampaignDisplaySession $session, PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($session, $attempt): void {
            $locked = CampaignDisplaySession::query()->lockForUpdate()->findOrFail($session->getKey());
            $this->assertOpen($locked);
            abort_unless((string) $locked->voucher_id === (string) $attempt->voucher_id, 409);
            $locked->update(['payment_attempt_id' => $attempt->getKey()]);
        });
    }

    public function end(CampaignDisplaySession $session): void
    {
        $this->synchronized($session, fn () => CampaignDisplaySession::query()
            ->whereKey($session->getKey())->whereNull('ended_at')->update(['ended_at' => now()]));
    }

    public function synchronized(CampaignDisplaySession $session, callable $operation): mixed
    {
        return Cache::lock('x-change:display-session:'.$session->getKey(), 120)->block(5, $operation);
    }

    /** @return array<string, mixed> */
    public function present(CampaignDisplaySession $session): array
    {
        $session->loadMissing(['campaign', 'voucher', 'attempt']);
        $status = $session->voucher_id === null ? 'ready' : 'claimed';
        if ($session->voucher?->redeemed_at !== null && ! $this->capabilities->resolve($session->voucher)->can_collect) {
            $status = 'completed';
        }
        $attempt = $session->attempt;
        if ($attempt !== null) {
            $status = match ($attempt->status) {
                PaymentAttemptStatus::Settled => 'paid',
                PaymentAttemptStatus::Suspense => 'review',
                PaymentAttemptStatus::Expired, PaymentAttemptStatus::Cancelled => 'expired',
                default => 'awaiting_payment',
            };
            if ($attempt->expires_at?->isPast() && $status !== 'paid') {
                $status = 'expired';
            }
        }
        if ($session->expires_at->isPast() && ! in_array($status, ['paid', 'completed'], true)) {
            $status = 'expired';
        }
        if ($session->ended_at !== null) {
            $status = 'ended';
        }
        $campaign = $session->campaign;
        $url = route('x-change.leads.start', [
            'merchant_slug' => $campaign->merchant_slug,
            'endpoint_slug' => $campaign->endpoint_slug,
            'display' => $session->entry_token,
        ]);

        return [
            'reference' => $session->reference,
            'status' => $status,
            'campaign' => $campaign->only(['reference', 'title', 'description', 'merchant_display_name']),
            'entry_qr_data_uri' => $status === 'ready' ? $this->qr->render($url) : null,
            'public_url' => $status === 'ready' ? $url : null,
            'pay_code' => $session->voucher?->code,
            'expires_at' => $session->expires_at->toIso8601String(),
            'attempt' => $attempt !== null && $status === 'awaiting_payment' ? $this->attempts->present($attempt) : null,
            'links' => [
                'show' => route('x-change.cockpit.display-sessions.show', $session->reference),
                'end' => route('x-change.cockpit.display-sessions.end', $session->reference),
                'reset' => route('x-change.cockpit.display-sessions.reset', $session->reference),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function campaignsFor(Model $owner): array
    {
        return LeadCampaign::query()->with('payCodeTemplate')
            ->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getKey())
            ->where('status', 'active')->latest('id')->limit(100)->get()
            ->map(function (LeadCampaign $campaign): array {
                $available = true;
                $availability = 'Available';
                try {
                    $this->start->ensureStartable($campaign);
                } catch (ValidationException $exception) {
                    $available = false;
                    $availability = collect($exception->errors())->flatten()->first() ?? 'Unavailable';
                }

                return [...$campaign->only(['reference', 'title', 'description', 'merchant_display_name']),
                    'available' => $available, 'availability' => $availability];
            })->all();
    }

    private function browserHash(Request $request): string
    {
        $key = 'x-change.payment.browser-key';
        if (! $request->session()->has($key)) {
            $request->session()->put($key, Str::random(64));
        }

        return hash('sha256', (string) $request->session()->get($key));
    }
}
