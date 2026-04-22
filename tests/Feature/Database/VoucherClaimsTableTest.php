<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates the voucher_claims table with expected columns', function () {
    expect(Schema::hasTable('voucher_claims'))->toBeTrue();

    foreach ([
        'id',
        'voucher_id',
        'claim_number',
        'claim_type',
        'status',
        'requested_amount_minor',
        'disbursed_amount_minor',
        'remaining_balance_minor',
        'currency',
        'claimer_mobile',
        'recipient_country',
        'bank_code',
        'account_number_masked',
        'idempotency_key',
        'reference',
        'attempted_at',
        'completed_at',
        'failure_message',
        'meta',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('voucher_claims', $column))->toBeTrue();
    }
});
