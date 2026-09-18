<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Leads;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use LBHurtado\XCampaign\Contracts\EndpointCampaignRepository;
use LBHurtado\XChange\Actions\Leads\StartLeadCampaign;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class LeadCampaignEndpointController extends Controller
{
    public function __construct(private readonly EndpointCampaignRepository $endpoints) {}

    public function show(
        string $merchant_slug,
        string $endpoint_slug,
        StartLeadCampaign $startLeadCampaign,
        Request $request,
        CampaignDisplaySessions $displays,
    ): InertiaResponse|RedirectResponse {
        $campaign = $this->endpoints->findByPublicEndpointOrFail($merchant_slug, $endpoint_slug);

        if ($request->has('display')) {
            $token = $request->query('display');
            abort_unless(is_string($token), 404);
            $code = $displays->pair($campaign, $token, $request);

            return redirect()->route('x-change.claim.show', ['code' => $code]);
        }

        [$canStart, $message] = $this->startability($campaign, $startLeadCampaign);

        return Inertia::render('x-change/leads/Start', [
            'campaign' => [
                'title' => $campaign->title,
                'description' => $campaign->description,
                'merchant_display_name' => $campaign->merchant_display_name,
                'merchant_slug' => $campaign->merchant_slug,
                'endpoint_slug' => $campaign->endpoint_slug,
                'usage_label' => data_get((array) $campaign->settings, 'usage_label'),
                'usage_key' => data_get((array) $campaign->settings, 'usage_key', data_get((array) $campaign->settings, 'kind', 'lead')),
            ],
            'start_url' => route('x-change.leads.start.submit', [
                'merchant_slug' => $campaign->merchant_slug,
                'endpoint_slug' => $campaign->endpoint_slug,
            ]),
            'can_start' => $canStart,
            'unavailable_message' => $message,
        ]);
    }

    public function start(
        string $merchant_slug,
        string $endpoint_slug,
        StartLeadCampaign $startLeadCampaign,
        Request $request,
        AuditLoggerContract $audit,
    ): RedirectResponse {
        $campaign = $this->endpoints->findByPublicEndpointOrFail($merchant_slug, $endpoint_slug);

        try {
            $startLeadCampaign->ensureStartable($campaign);

            if ($code = $this->existingStartedCode($request, $campaign)) {
                $audit->log('campaign.endpoint.start_resumed', $this->auditPayload($campaign, $request, [
                    'pay_code' => $code,
                ]));

                return redirect()->route('x-change.claim.show', ['code' => $code]);
            }

            $result = Cache::lock(
                $this->lockKey($request, $campaign),
                max(1, (int) config('x-change.leads.idempotency.lock_seconds', 15)),
            )->block(
                max(0, (int) config('x-change.leads.idempotency.lock_wait_seconds', 3)),
                function () use ($audit, $campaign, $request, $startLeadCampaign) {
                    if ($code = $this->existingStartedCode($request, $campaign)) {
                        $audit->log('campaign.endpoint.start_resumed', $this->auditPayload($campaign, $request, [
                            'pay_code' => $code,
                        ]));

                        return redirect()->route('x-change.claim.show', ['code' => $code]);
                    }

                    $audit->log('campaign.endpoint.start_requested', $this->auditPayload($campaign, $request));

                    $result = $startLeadCampaign->handle($campaign);
                    $this->rememberStartedCode($request, $campaign, $result->code);

                    $audit->log('campaign.endpoint.pay_code_minted', $this->auditPayload($campaign, $request, [
                        'pay_code' => $result->code,
                    ]));

                    return redirect()->route('x-change.claim.show', [
                        'code' => $result->code,
                    ]);
                },
            );
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $audit->log('campaign.endpoint.start_blocked', $this->auditPayload($campaign, $request, [
                'reason' => $message,
            ]));

            return $this->redirectToEndpoint($campaign)->withErrors([
                'campaign' => $message !== '' ? $message : 'This campaign is not accepting new starts.',
            ]);
        } catch (LockTimeoutException) {
            $audit->log('campaign.endpoint.start_blocked', $this->auditPayload($campaign, $request, [
                'reason' => 'A start request is already being processed.',
            ]));

            return $this->redirectToEndpoint($campaign)->withErrors([
                'campaign' => 'We are already starting this campaign journey. Please try again in a moment.',
            ]);
        }

        return $result;
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function startability(LeadCampaign $campaign, StartLeadCampaign $startLeadCampaign): array
    {
        try {
            $startLeadCampaign->ensureStartable($campaign);

            return [true, null];
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();

            return [false, $message !== '' ? $message : 'This campaign is not accepting new starts.'];
        }
    }

    private function existingStartedCode(Request $request, LeadCampaign $campaign): ?string
    {
        $stored = $request->session()->get($this->sessionKey($campaign));

        if (! is_array($stored)) {
            return null;
        }

        $code = $stored['code'] ?? null;
        $expiresAt = $stored['expires_at'] ?? null;

        if (! is_string($code) || trim($code) === '' || ! is_string($expiresAt)) {
            $request->session()->forget($this->sessionKey($campaign));

            return null;
        }

        if (Carbon::parse($expiresAt)->isPast()) {
            $request->session()->forget($this->sessionKey($campaign));

            return null;
        }

        return trim($code);
    }

    private function rememberStartedCode(Request $request, LeadCampaign $campaign, string $code): void
    {
        $request->session()->put($this->sessionKey($campaign), [
            'code' => $code,
            'expires_at' => now()
                ->addMinutes(max(1, (int) config('x-change.leads.idempotency.window_minutes', 10)))
                ->toIso8601String(),
        ]);
    }

    private function sessionKey(LeadCampaign $campaign): string
    {
        return sprintf('x-change.leads.starts.%s', $campaign->reference);
    }

    private function redirectToEndpoint(LeadCampaign $campaign): RedirectResponse
    {
        return redirect()
            ->route('x-change.leads.start', [
                'merchant_slug' => $campaign->merchant_slug,
                'endpoint_slug' => $campaign->endpoint_slug,
            ], Response::HTTP_SEE_OTHER);
    }

    private function lockKey(Request $request, LeadCampaign $campaign): string
    {
        $fingerprint = sha1(implode('|', [
            $request->session()->getId(),
            $request->ip() ?? 'unknown',
            (string) $request->userAgent(),
        ]));

        return sprintf('x-change:leads:start:%s:%s', $campaign->reference, $fingerprint);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function auditPayload(LeadCampaign $campaign, Request $request, array $extra = []): array
    {
        return [
            'campaign_reference' => $campaign->reference,
            'merchant_slug' => $campaign->merchant_slug,
            'endpoint_slug' => $campaign->endpoint_slug,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            ...$extra,
        ];
    }
}
