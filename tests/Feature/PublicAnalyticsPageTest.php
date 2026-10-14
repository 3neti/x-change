<?php

declare(strict_types=1);

it('renders aggregate public batch analytics without private identifiers', function (): void {
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get(route('x-change.analytics.show', ['period' => '7d']));

    $response->assertOk()
        ->assertJsonPath('component', 'x-change/public/Analytics')
        ->assertJsonPath('props.analytics.period', '7d')
        ->assertJsonPath('props.analytics.totals.batches', 0)
        ->assertJsonPath('props.public_navigation.analytics_url', '/x/analytics')
        ->assertJsonMissingPath('props.analytics.items')
        ->assertJsonMissingPath('props.analytics.contacts')
        ->assertJsonMissingPath('props.analytics.provider_payloads');
});

it('aggregates only public batches by period', function (): void {
    $batch = \LBHurtado\XChange\Models\IssuanceBatch::query()->create([
        'source' => 'public.auto-generate.batch',
        'status' => \LBHurtado\XChange\Enums\IssuanceBatchStatus::Issued,
        'currency' => 'PHP',
        'item_count' => 2,
        'valid_item_count' => 2,
        'total_minor' => 7500,
        'normalized_fingerprint' => str_repeat('a', 64),
        'created_at' => now()->subDay(),
        'issued_at' => now()->subDay()->addMinute(),
    ]);
    \LBHurtado\XChange\Models\IssuanceBatchItem::query()->insert([
        [
            'batch_id' => $batch->id, 'ordinal' => 1, 'source_row' => 2, 'status' => 'issued', 'currency' => 'PHP', 'amount_minor' => 2500,
            'row_fingerprint' => str_repeat('b', 64), 'pay_code' => 'ABCD', 'issued_at' => now()->subDay()->addMinute(), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ],
        [
            'batch_id' => $batch->id, 'ordinal' => 2, 'source_row' => 3, 'status' => 'issued', 'currency' => 'PHP', 'amount_minor' => 5000,
            'row_fingerprint' => str_repeat('c', 64), 'pay_code' => 'EFGH', 'issued_at' => now()->subDay()->addMinute(), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ],
    ]);

    $response = $this->withHeaders(['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
        ->get(route('x-change.analytics.show', ['period' => '7d']));

    $response->assertOk()
        ->assertJsonPath('props.analytics.totals.batches', 1)
        ->assertJsonPath('props.analytics.totals.recipients', 2)
        ->assertJsonPath('props.analytics.totals.issued', 2)
        ->assertJsonPath('props.analytics.totals.issued_value_minor', 7500);
});
