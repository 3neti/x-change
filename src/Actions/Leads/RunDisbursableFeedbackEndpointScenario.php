<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Leads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;

final readonly class RunDisbursableFeedbackEndpointScenario
{
    public function __construct(
        private CreateLeadCampaign $createLeadCampaign,
    ) {}

    public function handle(Model $owner): LeadCampaign
    {
        $template = PayCodeTemplate::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'name' => '₱25 Feedback Endpoint',
            'description' => 'Browser lifecycle scenario for one disbursable Pay Code with SMS feedback.',
            'base_template_key' => 'blank-pay-code',
            'instructions_ciphertext' => $this->instructions(),
            'include_amount' => true,
            'include_purpose' => true,
            'status' => 'active',
        ]);

        return $this->createLeadCampaign->handle($owner, $template, [
            'title' => '₱25 Feedback Endpoint',
            'endpoint_slug' => 'feedback-endpoint-'.Str::lower(Str::random(6)),
            'description' => 'One-use public endpoint for the disbursable feedback lifecycle scenario.',
            'starts_limit' => 1,
            'expires_at' => now()->addDay(),
            'settings' => [
                'usage_key' => 'custom',
                'usage_label' => 'Feedback lifecycle test',
                'capabilities' => ['distribution', 'feedback'],
                'limits' => [
                    'budget_cap_minor' => 2_500,
                    'claims_limit' => 1,
                    'per_identity_claim_limit' => 1,
                ],
                'availability' => [
                    'timezone' => (string) config('app.timezone', 'UTC'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function instructions(): array
    {
        return [
            'cash' => [
                'amount' => 25,
                'currency' => 'PHP',
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => '09173011987',
            ],
            'rider' => [
                'message' => 'test feedback',
            ],
            'count' => 1,
            'prefix' => 'FDBK',
            'mask' => '****',
            'metadata' => [
                'flow_type' => 'disbursable',
                'custom' => [
                    'lead_campaign' => [
                        'scenario' => 'disbursable_feedback_endpoint',
                    ],
                ],
            ],
            'claim' => [],
        ];
    }
}
