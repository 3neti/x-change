<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;

final class CampaignPaymentProgressReadModel
{
    /**
     * @param  Collection<int, EndpointCampaign>  $campaigns  Owner-scoped campaigns.
     * @return array<int|string, array{source: string, payments_received: int, received_amounts: list<array{currency: string, amount_minor: int}>, details_submitted: int, demo_summaries_ready: int, awaiting_claim: int, awaiting_invitation: int}>
     */
    public function forCampaigns(Collection $campaigns): array
    {
        $ids = $campaigns->filter(fn (EndpointCampaign $campaign): bool => data_get($campaign->settings, 'entry_mode') === CampaignEntryMode::ReusablePaymentQr->value)->modelKeys();
        if ($ids === []) {
            return [];
        }

        $recognitions = (new CampaignPaymentRecognition)->getTable();
        $bindings = (new CampaignPaymentQrBinding)->getTable();
        $base = CampaignPaymentRecognition::query()
            ->join($bindings.' as binding', 'binding.id', '=', $recognitions.'.campaign_payment_qr_binding_id')
            ->whereIn('binding.endpoint_campaign_id', $ids);
        $totals = (clone $base)
            ->select('binding.endpoint_campaign_id', $recognitions.'.currency')
            ->selectRaw('COUNT(*) as payments_received, SUM(gross_amount_minor) as amount_minor')
            ->groupBy('binding.endpoint_campaign_id', $recognitions.'.currency')
            ->get()->groupBy('endpoint_campaign_id');

        $issuance = 'provisionalCoverage.completionPayCodeIssuance';
        $claim = $issuance.'.evidenceProjection.claim';
        $request = $issuance.'.evidenceProjection.policyCompletionRequest';
        $submitted = fn (Builder $query): Builder => $query->whereNotNull('completed_at');
        $details = $this->counts((clone $base)->whereHas($claim, $submitted));
        $awaiting = $this->counts((clone $base)->whereHas($issuance)->whereDoesntHave($claim, $submitted));
        $pending = $this->counts((clone $base)->whereDoesntHave($issuance));
        $demos = $this->counts((clone $base)->whereHas($request, fn (Builder $query): Builder => $query
            ->where('status', 'succeeded')
            ->whereHas('outcome', fn (Builder $outcome): Builder => $outcome
                ->where('status', 'succeeded')->where('result_code', 'policy_issued_demo'))));

        return collect($ids)->mapWithKeys(function ($id) use ($totals, $details, $awaiting, $pending, $demos): array {
            $currencies = $totals->get($id, collect());

            return [$id => [
                'source' => 'recognized_campaign_payments',
                'payments_received' => (int) $currencies->sum('payments_received'),
                'received_amounts' => $currencies->map(fn ($total): array => [
                    'currency' => $total->currency,
                    'amount_minor' => (int) $total->amount_minor,
                ])->values()->all(),
                'details_submitted' => (int) ($details[$id] ?? 0),
                'demo_summaries_ready' => (int) ($demos[$id] ?? 0),
                'awaiting_claim' => (int) ($awaiting[$id] ?? 0),
                'awaiting_invitation' => (int) ($pending[$id] ?? 0),
            ]];
        })->all();
    }

    /** @return array<int|string, int> */
    private function counts(Builder $query): array
    {
        return $query->select('binding.endpoint_campaign_id')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('binding.endpoint_campaign_id')
            ->pluck('aggregate_count', 'endpoint_campaign_id')->all();
    }
}
