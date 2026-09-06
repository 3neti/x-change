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
                'collection_wallet_id' => 'shared-wallet-17',
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
        ->and(data_get($instructions, 'metadata.collection_wallet_id'))->toBeNull()
        ->and(data_get($instructions, 'metadata.custom.cockpit.recipient_reference'))->toBeNull()
        ->and(data_get($instructions, 'starts_at'))->toBeNull()
        ->and($raw)->not->toContain('09173011987')
        ->and($raw)->not->toContain('shared-wallet-17')
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

it('updates an owner template in place through the shared sanitizer', function () {
    $operator = actingAsTestUser();

    $template = PayCodeTemplate::query()->create([
        'owner_type' => $operator->getMorphClass(),
        'owner_id' => (string) $operator->getKey(),
        'name' => 'Weekly Allowance',
        'base_template_key' => 'money-changer',
        'instructions_ciphertext' => ['cash' => ['currency' => 'PHP']],
        'include_amount' => false,
        'include_purpose' => false,
        'status' => 'active',
    ]);

    $this->patch(
        route('x-change.cockpit.pay-code-templates.update', $template),
        [
            'name' => 'Monthly Allowance',
            'description' => 'Updated for future Pay Codes.',
            'base_template_key' => 'money-changer',
            'include_amount' => false,
            'include_purpose' => true,
            'expected_updated_at' => $template->updated_at?->toIso8601String(),
            'instructions' => [
                'cash' => [
                    'amount' => 750,
                    'currency' => 'PHP',
                    'validation' => [
                        'mobile' => '09173011987',
                        'secret' => 'never-store-this',
                    ],
                ],
                'rider' => [
                    'message' => 'Monthly allowance',
                ],
            ],
        ],
    )->assertRedirect();

    $template->refresh();

    expect(PayCodeTemplate::query()->count())->toBe(1)
        ->and($template->name)->toBe('Monthly Allowance')
        ->and($template->description)->toBe('Updated for future Pay Codes.')
        ->and($template->include_purpose)->toBeTrue()
        ->and(data_get($template->instructions_ciphertext, 'cash.amount'))->toBeNull()
        ->and(data_get($template->instructions_ciphertext, 'cash.validation.mobile'))->toBeNull()
        ->and(data_get($template->instructions_ciphertext, 'cash.validation.secret'))->toBeNull()
        ->and(data_get($template->instructions_ciphertext, 'rider.message'))->toBe('Monthly allowance');
});

it('forbids updating another owners pay code template', function () {
    $owner = actingAsTestUser();

    $template = PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'name' => 'Private Template',
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['cash' => ['currency' => 'PHP']],
        'include_amount' => false,
        'include_purpose' => false,
        'status' => 'active',
    ]);

    actingAsTestUser();

    $this->patch(
        route('x-change.cockpit.pay-code-templates.update', $template),
        [
            'name' => 'Changed Name',
            'base_template_key' => 'blank-pay-code',
            'instructions' => [],
            'include_amount' => false,
            'include_purpose' => false,
            'expected_updated_at' => $template->updated_at?->toIso8601String(),
        ],
    )->assertForbidden();

    expect($template->fresh()->name)->toBe('Private Template');
});

it('rejects a stale pay code template update', function () {
    $operator = actingAsTestUser();

    $template = PayCodeTemplate::query()->create([
        'owner_type' => $operator->getMorphClass(),
        'owner_id' => (string) $operator->getKey(),
        'name' => 'Branch Cash Out',
        'base_template_key' => 'money-changer',
        'instructions_ciphertext' => ['cash' => ['currency' => 'PHP']],
        'include_amount' => false,
        'include_purpose' => false,
        'status' => 'active',
    ]);
    $staleUpdatedAt = $template->updated_at?->toIso8601String();

    $template->forceFill([
        'name' => 'Updated Elsewhere',
        'updated_at' => $template->updated_at?->addSecond(),
    ])->saveQuietly();

    $this->patch(
        route('x-change.cockpit.pay-code-templates.update', $template),
        [
            'name' => 'Overwrite Attempt',
            'base_template_key' => 'money-changer',
            'instructions' => ['cash' => ['currency' => 'PHP']],
            'include_amount' => false,
            'include_purpose' => false,
            'expected_updated_at' => $staleUpdatedAt,
        ],
    )->assertSessionHasErrors('expected_updated_at');

    expect($template->fresh()->name)->toBe('Updated Elsewhere');
});
