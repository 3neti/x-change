<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\PayCodeTemplate;

it('stores an encrypted owner-scoped reusable pay code blueprint', function () {
    $operator = actingAsTestUser();

    $this->post(route('x-change.cockpit.pay-code-templates.store'), [
        'name' => 'School Allowance',
        'description' => 'Reusable allowance safeguards.',
        'base_template_key' => 'money-changer',
        'include_amount' => false,
        'include_purpose' => true,
        'instructions' => [
            'cash' => [
                'amount' => 500,
                'currency' => 'PHP',
                'validation' => [
                    'mobile' => '09173011987',
                    'secret' => 'never-store-this',
                ],
            ],
            'feedback' => [
                'mobile' => '09173011987',
                'email' => 'recipient@example.test',
            ],
            'rider' => [
                'message' => 'School allowance',
            ],
            'starts_at' => '2026-07-28T09:00:00+08:00',
            'expires_at' => '2026-07-29T09:00:00+08:00',
            'metadata' => [
                'issuer_id' => 'sensitive-issuer',
                'custom' => [
                    'cockpit' => [
                        'template_key' => 'money-changer',
                        'recipient_reference' => '09173011987',
                    ],
                ],
            ],
        ],
    ])->assertRedirect();

    $template = PayCodeTemplate::query()->sole();
    $instructions = $template->instructions_ciphertext;
    $raw = DB::table('x_change_pay_code_templates')
        ->where('id', $template->getKey())
        ->value('instructions_ciphertext');

    expect($template->owner_type)->toBe($operator->getMorphClass())
        ->and($template->owner_id)->toBe((string) $operator->getKey())
        ->and($instructions)->toBeArray()
        ->and(data_get($instructions, 'cash.currency'))->toBe('PHP')
        ->and(data_get($instructions, 'rider.message'))->toBe('School allowance')
        ->and(data_get($instructions, 'metadata.custom.cockpit.template_preferences.mobile_validation'))->toBeTrue()
        ->and(data_get($instructions, 'cash.amount'))->toBeNull()
        ->and(data_get($instructions, 'cash.validation.mobile'))->toBeNull()
        ->and(data_get($instructions, 'cash.validation.secret'))->toBeNull()
        ->and(data_get($instructions, 'feedback.mobile'))->toBeNull()
        ->and(data_get($instructions, 'metadata.issuer_id'))->toBeNull()
        ->and(data_get($instructions, 'metadata.custom.cockpit.recipient_reference'))->toBeNull()
        ->and(data_get($instructions, 'starts_at'))->toBeNull()
        ->and($raw)->not->toContain('09173011987')
        ->and($raw)->not->toContain('never-store-this');
});

it('hydrates only the current owners saved pay code templates', function () {
    $first = actingAsTestUser();

    PayCodeTemplate::query()->create([
        'owner_type' => $first->getMorphClass(),
        'owner_id' => (string) $first->getKey(),
        'name' => 'My Template',
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['cash' => ['currency' => 'PHP']],
        'include_amount' => false,
        'include_purpose' => false,
        'status' => 'active',
    ]);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.saved_templates.0.name', 'My Template')
        ->assertJsonPath('props.saved_templates.0.base_template_key', 'blank-pay-code')
        ->assertJsonPath('props.saved_templates.0.instructions.cash.currency', 'PHP');

    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonCount(0, 'props.saved_templates');
});
